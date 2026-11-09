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

    public $wpCreateSelections = [];

    public function updatedWpCreateSelections()
    {
        $this->wpCreateConfirmed = false;
        $this->wpCreateSuccess = '';
    }


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

        $this->wpCreateSelections = [];

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
        $this->assertDataSyncAdmin();

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
            $this->wpCreateSelections = [];

            foreach ($result['rows'] as $row) {
                if (
                    $row['status'] === 'Ready to create'
                    && empty($row['issues'])
                ) {
                    $this->wpCreateSelections[(int) $row['wordpress_id']] = false;
                }
            }

            $this->wpCreatePreview = $result;
            $this->wpCreateToken = $token;
        } catch (\Throwable $e) {
            report($e);

            $this->wpCreatePreviewError =
                mb_substr($e->getMessage(), 0, 600);
        }
    }


    public function selectAllWordPressSessionCreation($checked = true)
    {
        $this->assertDataSyncAdmin();

        $this->wpCreateConfirmed = false;
        $this->wpCreateSuccess = '';
        $this->wpCreatePreviewError = '';

        try {
            if (!is_bool($checked)) {
                throw new RuntimeException('Invalid selection value.');
            }

            $token = (string) $this->wpCreateToken;

            if (!preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
                throw new RuntimeException(
                    'Generate a new creation preview first.'
                );
            }

            $plan = Cache::get($this->wpCreateCacheKey($token));

            $event = Event::whereKey($this->eventData['eventId'])
                ->where('category', $this->eventData['eventCategory'])
                ->firstOrFail();

            if (
                !is_array($plan)
                || !isset(
                    $plan['event_id'],
                    $plan['category'],
                    $plan['post_type'],
                    $plan['config_hash'],
                    $plan['preview']['rows']
                )
                || !is_array($plan['preview']['rows'])
                || $plan['event_id'] !== (int) $event->id
                || $plan['category'] !== (string) $event->category
                || $plan['post_type'] !== (string) $this->wpPreviewPostType
            ) {
                throw new RuntimeException(
                    'Preview expired or feature changed. Preview again.'
                );
            }

            if (
                $plan['config_hash'] !== $this->wpCreateConfigHash($event)
            ) {
                throw new RuntimeException(
                    'Saved settings changed. Preview again.'
                );
            }

            $selections = [];

            // Use cached server-side rows, not browser preview values.
            foreach ($plan['preview']['rows'] as $row) {
                if (
                    $row['status'] === 'Ready to create'
                    && empty($row['issues'])
                ) {
                    $selections[(int) $row['wordpress_id']] = $checked;
                }
            }

            $this->wpCreateSelections = $selections;
        } catch (\Throwable $e) {
            report($e);

            $this->wpCreatePreviewError = mb_substr(
                $e->getMessage(),
                0,
                600
            );
        }
    }

    public function createReviewedWordPressSessions()
    {

        $this->assertDataSyncAdmin();
        $this->wpCreatePreviewError = '';
        $this->wpCreateSuccess = '';

        try {
            if ($this->wpCreateConfirmed !== true) {
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

            if (!is_array($this->wpCreateSelections)) {
                throw new RuntimeException('Invalid session selections.');
            }

            $selections = $this->wpCreateSelections;


            $count = DB::transaction(function () use (
                $key,
                $eventId,
                $category,
                $type,
                $selections
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

                $eligible = [];

                foreach ($plan['preview']['rows'] as $plannedRow) {
                    if (
                        $plannedRow['status'] === 'Ready to create'
                        && empty($plannedRow['issues'])
                    ) {
                        $eligible[(int) $plannedRow['wordpress_id']] = true;
                    }
                }

                $selectedIds = [];

                foreach ($selections as $wpId => $checked) {
                    if ($checked === false) {
                        continue;
                    }

                    if (
                        $checked !== true
                        || !ctype_digit((string) $wpId)
                        || !isset($eligible[(int) $wpId])
                    ) {
                        throw new RuntimeException(
                            'A selected session is not eligible in this preview.'
                        );
                    }

                    $selectedIds[(int) $wpId] = true;
                }

                if (!$selectedIds) {
                    throw new RuntimeException(
                        'Select at least one session to create.'
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
                    if (!isset($selectedIds[(int) $row['wordpress_id']])) {
                        continue;
                    }

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
            $this->wpCreateSelections = [];

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
