<?php

namespace App\Http\Livewire\Concerns;

use App\Models\Event;
use App\Services\WordPressAgendaUpdatePreview;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

trait HasWordPressLinkedUpdates
{
    public $wpUpdatePreview = [];
    public $wpUpdatePreviewError = '';
    public $wpUpdateSuccess = '';
    public $wpUpdateToken = '';
    public $wpUpdateConfirmed = false;

    public $wpUpdateSelections = [];

    public function updatedWpUpdateSelections()
    {
        $this->wpUpdateConfirmed = false;
        $this->wpUpdateSuccess = '';
    }


    protected function wpUpdateCacheKey($token)
    {
        $owner = hash(
            'sha256',
            session()->getId() . '|' . (string) auth()->id()
        );

        return 'wp-linked-update:' . $owner . ':' . $token;
    }

    protected function wpUpdateConfigHash($event)
    {
        return hash('sha256', json_encode([
            $event->wordpress_source_url,
            $event->wordpress_agenda_config,
        ]));
    }

    public function previewLinkedWordPressUpdates()
    {
        $this->assertDataSyncAdmin();
        $this->wpUpdateSelections = [];

        if ($this->wpUpdateToken) {
            Cache::forget(
                $this->wpUpdateCacheKey($this->wpUpdateToken)
            );
        }

        $this->wpUpdatePreview = [];
        $this->wpUpdatePreviewError = '';
        $this->wpUpdateSuccess = '';
        $this->wpUpdateToken = '';
        $this->wpUpdateConfirmed = false;

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
                ->where(
                    'category',
                    $this->eventData['eventCategory']
                )
                ->firstOrFail();

            $result = app(WordPressAgendaUpdatePreview::class)
                ->build($event, $type);

            $token = Str::random(48);

            $stored = Cache::put(
                $this->wpUpdateCacheKey($token),
                [
                    'event_id' => (int) $event->id,
                    'event_category' => (string) $event->category,
                    'post_type' => $type,
                    'config_hash' => $this->wpUpdateConfigHash($event),
                    'preview' => $result,
                ],
                now()->addMinutes(15)
            );

            if ($stored === false) {
                throw new RuntimeException(
                    'Could not store the update preview. Please try again.'
                );
            }


            // Keep the complete result in cache for the existing freshness check.
            // Filter only the browser's displayed rows.
            $display = $result;

            $display['rows'] = array_values(array_filter(
                $result['rows'],
                function ($row) {
                    return $row['status'] === 'Would update'
                        && !empty($row['changes']);
                }
            ));

            $this->wpUpdateSelections = [];

            foreach ($display['rows'] as $row) {
                $selectionKey = 'session_' . (int) $row['session_id'];

                $this->wpUpdateSelections[$selectionKey] = false;
            }

            $this->wpUpdatePreview = $display;
            $this->wpUpdateToken = $token;
        } catch (\Throwable $e) {
            report($e);

            $this->wpUpdatePreviewError =
                mb_substr($e->getMessage(), 0, 600);
        }
    }

    public function updatedWpPreviewPostType()
    {


        $this->wpUpdateSelections = [];

        $this->clearWordPressCreatePreview();

        if ($this->wpUpdateToken) {
            Cache::forget(
                $this->wpUpdateCacheKey($this->wpUpdateToken)
            );
        }

        $this->wpUpdatePreview = [];
        $this->wpUpdatePreviewError = '';
        $this->wpUpdateSuccess = '';
        $this->wpUpdateToken = '';
        $this->wpUpdateConfirmed = false;
    }

    public function applyLinkedWordPressUpdates()
    {

        $this->assertDataSyncAdmin();


        $this->wpUpdatePreviewError = '';
        $this->wpUpdateSuccess = '';

        try {
            if ($this->wpUpdateConfirmed !== true) {
                throw new RuntimeException(
                    'Review the changes and tick the confirmation checkbox.'
                );
            }

            $token = (string) $this->wpUpdateToken;

            if (!preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
                throw new RuntimeException(
                    'Generate a new update preview first.'
                );
            }

            $key = $this->wpUpdateCacheKey($token);
            $eventId = (int) $this->eventData['eventId'];
            $category = (string) $this->eventData['eventCategory'];
            $type = (string) $this->wpPreviewPostType;

            if (!is_array($this->wpUpdateSelections)) {
                throw new RuntimeException('Invalid session selections.');
            }

            $selections = $this->wpUpdateSelections;


            $updated = DB::transaction(function () use (
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
                    !is_array($plan)
                    || !isset(
                        $plan['event_id'],
                        $plan['event_category'],
                        $plan['post_type'],
                        $plan['config_hash'],
                        $plan['preview']['rows'],
                        $plan['preview']['blocked'],
                        $plan['preview']['update']
                    )
                    || !is_array($plan['preview']['rows'])
                    || $plan['event_id'] !== $eventId
                    || $plan['event_category'] !== $category
                    || $plan['post_type'] !== $type
                ) {
                    throw new RuntimeException(
                        'Preview expired or the selected feature changed. Preview again.'
                    );
                }

                if (
                    $plan['config_hash'] !==
                    $this->wpUpdateConfigHash($event)
                ) {
                    throw new RuntimeException(
                        'Saved settings changed. Preview again.'
                    );
                }

                if ($plan['preview']['blocked'] > 0) {
                    throw new RuntimeException(
                        'Resolve the blocked linked records before applying updates.'
                    );
                }

                if ($plan['preview']['update'] < 1) {
                    throw new RuntimeException(
                        'There are no changes to apply.'
                    );
                }


                $eligible = [];

                foreach ($plan['preview']['rows'] as $plannedRow) {
                    if (
                        $plannedRow['status'] === 'Would update'
                        && !empty($plannedRow['changes'])
                    ) {
                        $selectionKey =
                            'session_' . (int) $plannedRow['session_id'];

                        $eligible[$selectionKey] = true;
                    }
                }

                $selected = [];

                foreach ($selections as $selectionKey => $checked) {
                    if ($checked === false) {
                        continue;
                    }

                    if (
                        $checked !== true
                        || !isset($eligible[$selectionKey])
                    ) {
                        throw new RuntimeException(
                            'A selected session is not eligible in this preview.'
                        );
                    }

                    $selected[$selectionKey] = true;
                }

                if (!$selected) {
                    throw new RuntimeException(
                        'Select at least one session to update.'
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

                $fresh = app(WordPressAgendaUpdatePreview::class)
                    ->build($event, $type);

                if ($fresh !== $plan['preview']) {
                    throw new RuntimeException(
                        'Source data, saved links, or app values changed. Nothing was updated. Preview again.'
                    );
                }

                $allowed = [
                    'title',
                    'session_date',
                    'session_day',
                    'start_time',
                    'end_time',
                ];

                $count = 0;

                foreach ($plan['preview']['rows'] as $row) {
                    if ($row['status'] !== 'Would update') {
                        continue;
                    }

                    $selectionKey = 'session_' . (int) $row['session_id'];

                    if (!isset($selected[$selectionKey])) {
                        continue;
                    }

                    $values = [];

                    foreach ($row['changes'] as $change) {
                        if (
                            !in_array(
                                $change['field'],
                                $allowed,
                                true
                            )
                        ) {
                            throw new RuntimeException(
                                'The plan contains an unsupported field.'
                            );
                        }

                        $values[$change['field']] = $change['after'];
                    }

                    if (!$values) {
                        continue;
                    }

                    $affected = DB::table('sessions')
                        ->where('event_id', $eventId)
                        ->where('id', $row['session_id'])
                        ->update($values);

                    if ($affected !== 1) {
                        throw new RuntimeException(
                            'A target session could not be updated. Changes rolled back.'
                        );
                    }

                    $count++;
                }

                return $count;
            });

            Cache::forget($key);

            $this->wpUpdateToken = '';
            $this->wpUpdateConfirmed = false;
            $this->wpUpdatePreview = [];
            $this->wpUpdateSelections = [];

            $this->wpUpdateSuccess =
                'Updated ' . $updated . ' selected linked session(s). '
                . 'Created 0 sessions. '
                . 'Unchecked sessions, unlinked records and descriptions '
                . 'were left untouched. '
                . 'Run the preview again to verify.';
        } catch (\Throwable $e) {
            report($e);

            $this->wpUpdateConfirmed = false;
            $this->wpUpdatePreviewError =
                mb_substr($e->getMessage(), 0, 600);
        }
    }


    public function selectAllWordPressSessionUpdates($checked = true)
    {
        $this->assertDataSyncAdmin();

        $this->wpUpdateConfirmed = false;
        $this->wpUpdateSuccess = '';
        $this->wpUpdatePreviewError = '';

        try {
            if (!is_bool($checked)) {
                throw new RuntimeException('Invalid selection value.');
            }

            $token = (string) $this->wpUpdateToken;

            if (!preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
                throw new RuntimeException(
                    'Generate a new update preview first.'
                );
            }

            $plan = Cache::get($this->wpUpdateCacheKey($token));

            $event = Event::whereKey($this->eventData['eventId'])
                ->where('category', $this->eventData['eventCategory'])
                ->firstOrFail();

            if (
                !is_array($plan)
                || !isset(
                    $plan['event_id'],
                    $plan['event_category'],
                    $plan['post_type'],
                    $plan['config_hash'],
                    $plan['preview']['rows']
                )
                || !is_array($plan['preview']['rows'])
                || $plan['event_id'] !== (int) $event->id
                || $plan['event_category'] !== (string) $event->category
                || $plan['post_type'] !== (string) $this->wpPreviewPostType
            ) {
                throw new RuntimeException(
                    'Preview expired or feature changed. Preview again.'
                );
            }

            if (
                $plan['config_hash'] !== $this->wpUpdateConfigHash($event)
            ) {
                throw new RuntimeException(
                    'Saved settings changed. Preview again.'
                );
            }

            $selections = [];

            foreach ($plan['preview']['rows'] as $row) {
                if (
                    $row['status'] === 'Would update'
                    && !empty($row['changes'])
                ) {
                    $selectionKey =
                        'session_' . (int) $row['session_id'];

                    $selections[$selectionKey] = $checked;
                }
            }

            $this->wpUpdateSelections = $selections;
        } catch (\Throwable $e) {
            report($e);

            $this->wpUpdatePreviewError = mb_substr(
                $e->getMessage(),
                0,
                600
            );
        }
    }
}
