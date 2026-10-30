<?php

namespace App\Http\Livewire\Concerns;

use App\Models\Event;
use App\Services\WordPressAgendaCreatePreview;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

trait HasWordPressSessionCreation
{
    public $wpCreatePreview = [];
    public $wpCreatePreviewError = '';
    public $wpCreateSuccess = '';
    public $wpCreateToken = '';
    public $wpCreateConfirmed = false;

    protected function wpCreateCacheKey($token)
    {
        $owner = hash(
            'sha256',
            session()->getId() . '|' . (string) auth()->id()
        );

        return 'wp-session-create:' . $owner . ':' . $token;
    }

    protected function wpCreateConfigHash($event)
    {
        return hash('sha256', json_encode([
            $event->wordpress_source_url,
            $event->wordpress_agenda_config,
        ]));
    }

    public function clearWordPressCreatePreview()
    {
        if ($this->wpCreateToken) {
            Cache::forget(
                $this->wpCreateCacheKey($this->wpCreateToken)
            );
        }

        $this->wpCreatePreview = [];
        $this->wpCreatePreviewError = '';
        $this->wpCreateSuccess = '';
        $this->wpCreateToken = '';
        $this->wpCreateConfirmed = false;
    }

    public function previewWordPressCreates()
    {
        $this->clearWordPressCreatePreview();

        try {
            if (!in_array(config('cache.default'), [
                'file',
                'database',
                'redis',
                'memcached',
                'dynamodb',
            ], true)) {
                throw new RuntimeException(
                    'A persistent cache driver is required.'
                );
            }

            $type = trim((string) $this->wpPreviewPostType);

            if ($type === '') {
                throw new RuntimeException(
                    'Select an agenda feature first.'
                );
            }

            $event = Event::whereKey($this->eventData['eventId'])
                ->where('category', $this->eventData['eventCategory'])
                ->firstOrFail();

            $result = app(WordPressAgendaCreatePreview::class)
                ->build($event, $type);

            $token = Str::random(48);

            Cache::put(
                $this->wpCreateCacheKey($token),
                [
                    'event_id' => (int) $event->id,
                    'category' => (string) $event->category,
                    'post_type' => $type,
                    'config_hash' => $this->wpCreateConfigHash($event),
                    'preview' => $result,
                ],
                now()->addMinutes(15)
            );

            $this->wpCreatePreview = $result;
            $this->wpCreateToken = $token;
        } catch (\Throwable $e) {
            report($e);

            $this->wpCreatePreviewError =
                mb_substr($e->getMessage(), 0, 600);
        }
    }

    public function createReviewedWordPressSessions()
    {
        $this->wpCreatePreviewError = '';
        $this->wpCreateSuccess = '';

        try {
            if (!$this->wpCreateConfirmed) {
                throw new RuntimeException(
                    'Review the records and tick the confirmation checkbox.'
                );
            }

            $token = (string) $this->wpCreateToken;

            if (!preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
                throw new RuntimeException(
                    'Generate a new creation preview first.'
                );
            }

            $key = $this->wpCreateCacheKey($token);
            $eventId = (int) $this->eventData['eventId'];
            $category = (string) $this->eventData['eventCategory'];
            $type = (string) $this->wpPreviewPostType;

            $count = DB::transaction(function () use (
                $key, $eventId, $category, $type
            ) {
                $event = Event::whereKey($eventId)
                    ->where('category', $category)
                    ->lockForUpdate()
                    ->firstOrFail();

                $plan = Cache::get($key);

                if (
                    !$plan ||
                    $plan['event_id'] !== $eventId ||
                    $plan['category'] !== $category ||
                    $plan['post_type'] !== $type
                ) {
                    throw new RuntimeException(
                        'Preview expired or feature changed. Preview again.'
                    );
                }

                if (
                    $plan['config_hash'] !==
                    $this->wpCreateConfigHash($event)
                ) {
                    throw new RuntimeException(
                        'Saved settings changed. Preview again.'
                    );
                }

                if (
                    $plan['preview']['review'] > 0 ||
                    $plan['preview']['ready'] < 1
                ) {
                    throw new RuntimeException(
                        'There must be ready records and no records needing review.'
                    );
                }

                DB::table('sessions')
                    ->where('event_id', $eventId)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                DB::table('wordpress_agenda_links')
                    ->where('event_id', $eventId)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $fresh = app(WordPressAgendaCreatePreview::class)
                    ->build($event, $type);

                if ($fresh !== $plan['preview']) {
                    throw new RuntimeException(
                        'Source data, saved links or app records changed. Nothing was created. Preview again.'
                    );
                }

                $base = rtrim(
                    (string) $event->wordpress_source_url,
                    '/'
                );

                $created = 0;
                $now = now();

                foreach ($plan['preview']['rows'] as $row) {
                    if (
                        $row['status'] !== 'Ready to create' ||
                        $row['issues']
                    ) {
                        throw new RuntimeException(
                            'A record is not ready for creation.'
                        );
                    }

                    validator($row, [
                        'wordpress_id' => 'required|integer|min:1',
                        'feature_id' => 'required|integer|min:0',
                        'title' => 'required|string|max:255',
                        'date' => 'required|date_format:Y-m-d',
                        'day' => 'required|string|max:255',
                        'start_time' => [
                            'required',
                            'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                        ],
                        'end_time' => [
                            'required',
                            'regex:/^(?:onwards|(?:[01]\d|2[0-3]):[0-5]\d)$/',
                        ],
                    ])->validate();

                    $featureId = (int) $row['feature_id'];

                    if (
                        $featureId !== 0 &&
                        !DB::table('features')
                            ->where('event_id', $eventId)
                            ->where('id', $featureId)
                            ->exists()
                    ) {
                        throw new RuntimeException(
                            'The destination feature no longer exists.'
                        );
                    }

                    $identity = hash('sha256', json_encode([
                        $eventId,
                        $base,
                        $type,
                        (int) $row['wordpress_id'],
                    ]));

                    if (
                        DB::table('wordpress_agenda_links')
                            ->where('identity_hash', $identity)
                            ->exists()
                    ) {
                        throw new RuntimeException(
                            'A source record was already linked. Preview again.'
                        );
                    }

                    $sessionId = DB::table('sessions')->insertGetId([
                        'event_id' => $eventId,
                        'feature_id' => $featureId,
                        'session_date' => $row['date'],
                        'session_day' => $row['day'],
                        'session_type' => null,
                        'title' => $row['title'],
                        'description_html_text' => null,
                        'start_time' => $row['start_time'],
                        'end_time' => $row['end_time'] === 'onwards'
                            ? 'none'
                            : $row['end_time'],
                        'location' => null,
                        'sponsor_id' => null,
                        'is_active' => 0,
                        'datetime_added' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('wordpress_agenda_links')->insert([
                        'event_id' => $eventId,
                        'session_id' => $sessionId,
                        'source_url' => $base,
                        'post_type' => $type,
                        'wordpress_post_id' =>
                            (int) $row['wordpress_id'],
                        'identity_hash' => $identity,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $created++;
                }

                return $created;
            });

            Cache::forget($key);

            $this->wpCreateToken = '';
            $this->wpCreateConfirmed = false;
            $this->wpCreatePreview = [];

            $this->wpCreateSuccess =
                'Created ' . $count .
                ' inactive session(s) and saved their WordPress links. ' .
                'Existing sessions were not updated. ' .
                'Descriptions were left empty. Preview again to verify.';
        } catch (\Throwable $e) {
            report($e);

            $this->wpCreateConfirmed = false;
            $this->wpCreatePreviewError =
                mb_substr($e->getMessage(), 0, 600);
        }
    }
}