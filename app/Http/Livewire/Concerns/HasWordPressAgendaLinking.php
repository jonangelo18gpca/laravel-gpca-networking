<?php
namespace App\Http\Livewire\Concerns;

use App\Models\Event;
use App\Models\Session;
use App\Services\WordPressAgendaPreview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

trait HasWordPressAgendaLinking
{
    public $wpLinkRows=[];
    public $wpLinkOptions=[];
    public $wpLinkSelections=[];
    public $wpLinkExisting=[];
    public $wpLinkToken='';
    public $wpLinkConfirmed=false;
    public $wpLinkError='';
    public $wpLinkSuccess='';

    protected function wpLinkCacheKey($token)
    {
        return 'wp-agenda-link:'.hash('sha256',session()->getId().'|'.(string)auth()->id()).':'.$token;
    }
    protected function wpLinkConfigHash($event)
    {
        return hash('sha256',json_encode([$event->wordpress_source_url,$event->wordpress_agenda_config]));
    }
    public function loadWordPressAgendaLinking()
    {
        $this->wpLinkRows=[]; $this->wpLinkOptions=[]; $this->wpLinkSelections=[];
        $this->wpLinkExisting=[]; $this->wpLinkConfirmed=false; $this->wpLinkError='';
        $this->wpLinkSuccess=''; $this->wpLinkToken='';
        try {
            if (!Schema::hasTable('wordpress_agenda_links')) throw new RuntimeException('Run the wordpress_agenda_links migration first.');
            if (!in_array(config('cache.default'),['file','database','redis','memcached','dynamodb'],true)) throw new RuntimeException('A persistent cache driver is required; do not use array/null.');
            $event=Event::findOrFail($this->eventData['eventId']);
            $type=(string)$this->wpPreviewPostType;
            if ($type==='') throw new RuntimeException('Select a feature in WordPress Agenda Preview first.');
            $data=app(WordPressAgendaPreview::class)->fetch($event,$type);
            if (!$data['rows']) throw new RuntimeException('No WordPress sessions returned for that feature/year.');
            $base=rtrim((string)$event->wordpress_source_url,'/');
            $serverRows=[]; $featureId=null;
            foreach ($data['rows'] as $row) {
                $wpId=(int)$row['wordpress_id'];
                if (!$wpId || isset($serverRows[$wpId])) throw new RuntimeException('Invalid/duplicate WordPress ID.');
                if ($featureId!==null && $featureId!==(int)$row['feature_id']) throw new RuntimeException('Fetch must contain only one mapped feature.');
                $featureId=(int)$row['feature_id'];
                $identity=hash('sha256',json_encode([(int)$event->id,$base,$type,$wpId]));
                $link=DB::table('wordpress_agenda_links')->where('identity_hash',$identity)->first();
                if ($link) {
                    $linked=Session::where('event_id',$event->id)->where('feature_id',$featureId)->find($link->session_id);
                    if (!$linked) throw new RuntimeException('A saved link points to a deleted, moved or recategorized session. Repair that link before proceeding.');
                    $this->wpLinkSelections[$wpId]=(string)$linked->id;
                    $this->wpLinkExisting[$wpId]=(int)$linked->id;
                } else {
                    $this->wpLinkSelections[$wpId]='';
                }
                $this->wpLinkRows[]=[
                    'wp_id'=>$wpId,'title'=>$row['title'],'date'=>$row['date'],'day'=>$row['day'],
                    'time'=>$row['start_time'].' – '.$row['end_time'],
                ];
                $serverRows[$wpId]=['hash'=>$identity,'feature_id'=>$featureId];
            }
            $sessions=Session::where('event_id',$event->id)->where('feature_id',$featureId)
                ->orderBy('session_date')->orderBy('start_time')->get();
            foreach ($sessions as $session) {
                $this->wpLinkOptions[]=[
                    'id'=>(int)$session->id,
                    'label'=>'#'.$session->id.' | '.\Carbon\Carbon::parse($session->session_date)->format('Y-m-d').' | '.$session->start_time.' | '.$session->title,
                ];
            }
            $token=Str::random(48);
            Cache::put($this->wpLinkCacheKey($token),[
                'event_id'=>(int)$event->id,'post_type'=>$type,'source_url'=>$base,
                'config_hash'=>$this->wpLinkConfigHash($event),'rows'=>$serverRows,
            ],now()->addMinutes(15));
            $this->wpLinkToken=$token;
        } catch (\Throwable $e) {
            report($e); $this->wpLinkError=mb_substr($e->getMessage(),0,600);
            $this->wpLinkToken=''; $this->wpLinkRows=[];
        }
    }
    public function saveWordPressAgendaLinks()
    {
        $this->wpLinkError=''; $this->wpLinkSuccess='';
        try {
            if (!$this->wpLinkConfirmed) throw new RuntimeException('Review your selections and tick confirmation.');
            $token=(string)$this->wpLinkToken;
            if (!preg_match('/^[A-Za-z0-9]{48}$/',$token)) throw new RuntimeException('Load the linking list first.');
            $eventId=(int)$this->eventData['eventId'];
            $cacheKey=$this->wpLinkCacheKey($token);
            $selected=$this->wpLinkSelections;
            $currentType=(string)$this->wpPreviewPostType;
            $count=DB::transaction(function () use ($eventId,$cacheKey,$selected,$currentType) {
                $event=Event::whereKey($eventId)->lockForUpdate()->firstOrFail();
                $plan=Cache::get($cacheKey);
                if (!$plan || $plan['event_id']!==$eventId) throw new RuntimeException('Linking list expired or already saved. Load it again.');
                if ($plan['post_type']!==$currentType || $plan['config_hash']!==$this->wpLinkConfigHash($event)) throw new RuntimeException('Feature or saved settings changed. Load the linking list again.');
                $used=[]; $created=0;
                foreach ($selected as $wpId=>$rawId) {
                    if ($rawId==='' || $rawId===null) continue;
                    if (!ctype_digit((string)$rawId) || (int)$rawId<1 || !isset($plan['rows'][$wpId])) throw new RuntimeException('Invalid source/target selection.');
                    $id=(int)$rawId;
                    if (isset($used[$id])) throw new RuntimeException('App session '.$id.' was selected more than once.');
                    $used[$id]=true;
                    $row=$plan['rows'][$wpId];
                    $session=Session::where('event_id',$eventId)->where('feature_id',$row['feature_id'])->whereKey($id)->lockForUpdate()->first();
                    if (!$session) throw new RuntimeException('The selected session does not belong to this event/category.');
                    $link=DB::table('wordpress_agenda_links')->where('identity_hash',$row['hash'])->lockForUpdate()->first();
                    if ($link) {
                        if ((int)$link->session_id!==$id) throw new RuntimeException('A source is already linked to a different session. Existing links cannot be reassigned here.');
                        continue;
                    }
                    if (DB::table('wordpress_agenda_links')->where('session_id',$id)->exists()) throw new RuntimeException('App session '.$id.' is already linked to another WordPress post.');
                    DB::table('wordpress_agenda_links')->insert([
                        'event_id'=>$eventId,'session_id'=>$id,'source_url'=>$plan['source_url'],
                        'post_type'=>$plan['post_type'],'wordpress_post_id'=>(int)$wpId,
                        'identity_hash'=>$row['hash'],'created_at'=>now(),'updated_at'=>now(),
                    ]);
                    $created++;
                }
                Cache::forget($cacheKey);
                return $created;
            });
            $this->wpLinkSuccess='Saved '.$count.' new source link(s). No session details were changed. Reload the linking list to verify.';
            $this->wpLinkToken=''; $this->wpLinkConfirmed=false; $this->wpLinkRows=[];
        } catch (\Throwable $e) {
            report($e); $this->wpLinkError=mb_substr($e->getMessage(),0,600);
        }
    }
}
