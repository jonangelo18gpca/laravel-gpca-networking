<?php

namespace App\Http\Livewire\Concerns;

use App\Models\Speaker;
use App\Services\WordPressSpeakerPreview;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

trait HasWordPressSpeakerLinking
{
    public $wpSpeakerLinkRows = [];
    public $wpSpeakerLinkOptions = [];
    public $wpSpeakerLinkSelections = [];
    public $wpSpeakerLinkExisting = [];
    public $wpSpeakerLinkToken = '';
    public $wpSpeakerLinkError = '';
    public $wpSpeakerLinkConfirmed = false;
    public $wpSpeakerLinkSuccess = '';

    protected function wpSpeakerLinkCacheKey($token)
    {
        return 'wp-speaker-link:'
            . hash(
                'sha256',
                session()->getId() . '|' . (string) auth()->id()
            )
            . ':' . $token;
    }

    // protected function wpSpeakerLinkContext()
    // {
    //     $roleMappings = $this->wpSpeakerRoleMappings;
    //     ksort($roleMappings);

    //     return [
    //         'event_id' => (int) $this->eventData['eventId'],
    //         'source_url' => 'https://www.gpcaforum.com',
    //         'post_type' => (string) $this->wpSpeakerSource,
    //         'year' => (string) $this->wpSpeakerYear,
    //         'feature_id' => (int) $this->wpSpeakerDestination,
    //         'role_mappings' => $roleMappings,
    //     ];
    // }


    protected function wpSpeakerLinkContext()
    {
        $identity = $this->wpSpeakerSettingsIdentity();

        $roleMappings = $this->wpSpeakerRoleMappings;
        ksort($roleMappings);

        return [
            'event_id' => $identity['event_id'],
            'source_url' => $identity['source_url'],
            'post_type' => $identity['post_type'],
            'year' => $identity['source_year'],
            'feature_id' => (int) $this->wpSpeakerDestination,
            'role_mappings' => $roleMappings,
        ];
    }


    protected function resetWordPressSpeakerLinking()
    {
        $token = (string) $this->wpSpeakerLinkToken;

        if (preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            Cache::forget($this->wpSpeakerLinkCacheKey($token));
        }

        $this->wpSpeakerLinkRows = [];
        $this->wpSpeakerLinkOptions = [];
        $this->wpSpeakerLinkSelections = [];
        $this->wpSpeakerLinkExisting = [];
        $this->wpSpeakerLinkToken = '';
        $this->wpSpeakerLinkError = '';
    }

    public function loadWordPressSpeakerLinking()
    {
        $this->assertDataSyncAdmin();
        $this->resetWordPressSpeakerLinking();

        // Revalidate mapping selections, not just the success message.
        $this->checkWordPressSpeakerMapping();

        try {
            if (!Schema::hasTable('wordpress_speaker_links')) {
                throw new RuntimeException(
                    'Run the wordpress_speaker_links migration first.'
                );
            }

            if (!in_array(
                config('cache.default'),
                ['file', 'database', 'redis', 'memcached', 'dynamodb'],
                true
            )) {
                throw new RuntimeException(
                    'A persistent cache driver is required.'
                );
            }

            $context = $this->wpSpeakerLinkContext();

            // Fetch fresh source records instead of trusting public rows.
            $sourceRows = app(WordPressSpeakerPreview::class)->fetch(
                $context['post_type'],
                $context['year'],
                $context['source_url']
            );

            $rows = [];
            $selections = [];
            $existing = [];
            $serverRows = [];
            $seen = [];

            foreach ($sourceRows as $row) {
                $wpId = (int) $row['wp_id'];

                if ($wpId < 1 || isset($seen[$wpId])) {
                    throw new RuntimeException(
                        'Invalid or duplicate WordPress speaker ID.'
                    );
                }

                $seen[$wpId] = true;

                if ($row['eligibility'] !== 'Eligible') {
                    continue;
                }

                $roleKey = sha1(trim((string) $row['role']));

                if (empty($context['role_mappings'][$roleKey])) {
                    throw new RuntimeException(
                        'Source roles changed. Reload the preview and mapping.'
                    );
                }

                $identity = hash('sha256', json_encode([
                    $context['event_id'],
                    $context['source_url'],
                    $context['post_type'],
                    $wpId,
                ]));

                $link = DB::table('wordpress_speaker_links')
                    ->where('identity_hash', $identity)
                    ->first();

                $selections[$wpId] = '';

                if ($link) {
                    $speaker = Speaker::where(
                        'event_id',
                        $context['event_id']
                    )
                        ->where('feature_id', $context['feature_id'])
                        ->whereKey($link->speaker_id)
                        ->first();

                    if (!$speaker) {
                        $linkedSpeaker = Speaker::whereKey($link->speaker_id)->first();

                        if (!$linkedSpeaker) {
                            throw new RuntimeException(
                                'WP #' . $wpId
                                    . ' links to missing App speaker #'
                                    . $link->speaker_id
                                    . '. This stale link needs cleanup before a fresh import.'
                            );
                        }

                        throw new RuntimeException(
                            'WP #' . $wpId
                                . ' links to App speaker #' . $link->speaker_id
                                . ', but its event/category does not match the selected destination. '
                                . 'Check the destination mapping; the link was not removed.'
                        );
                    }

                    $existing[$wpId] = [
                        'id' => (int) $speaker->id,
                        'name' => $this->wpSpeakerLinkName($speaker),
                    ];

                    $selections[$wpId] = (string) $speaker->id;
                }

                $rows[] = [
                    'wp_id' => $wpId,
                    'name' => $row['name'],
                    'job_title' => $row['job_title'],
                    'company_name' => $row['company_name'],
                    'role' => $row['role'],
                ];

                $serverRows[$wpId] = [
                    'identity_hash' => $identity,
                    'feature_id' => $context['feature_id'],
                    'speaker_type_id' =>
                    (int) $context['role_mappings'][$roleKey],
                ];
            }

            if (!$rows) {
                throw new RuntimeException(
                    'No eligible WordPress speakers were returned.'
                );
            }

            $speakers = Speaker::where(
                'event_id',
                $context['event_id']
            )
                ->where('feature_id', $context['feature_id'])
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->orderBy('id')
                ->get();

            $linkedIds = DB::table('wordpress_speaker_links')
                ->whereIn('speaker_id', $speakers->pluck('id'))
                ->pluck('speaker_id')
                ->map(function ($id) {
                    return (int) $id;
                })
                ->all();

            $options = [];

            foreach ($speakers as $speaker) {
                if (in_array((int) $speaker->id, $linkedIds, true)) {
                    continue;
                }

                $options[] = [
                    'id' => (int) $speaker->id,
                    'label' => '#' . $speaker->id
                        . ' | ' . $this->wpSpeakerLinkName($speaker)
                        . ' | ' . ($speaker->company_name ?: 'No company'),
                ];
            }

            $token = Str::random(48);

            Cache::put(
                $this->wpSpeakerLinkCacheKey($token),
                [
                    'context' => $context,
                    'rows' => $serverRows,
                ],
                now()->addMinutes(15)
            );

            $this->wpSpeakerLinkRows = $rows;
            $this->wpSpeakerLinkOptions = $options;
            $this->wpSpeakerLinkSelections = $selections;
            $this->wpSpeakerLinkExisting = $existing;
            $this->wpSpeakerLinkToken = $token;
        } catch (Throwable $exception) {
            report($exception);

            $this->resetWordPressSpeakerLinking();

            $this->wpSpeakerLinkError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }


    public function updatedWpSpeakerLinkSelections()
    {
        $this->wpSpeakerLinkConfirmed = false;
        $this->wpSpeakerLinkSuccess = '';
    }



    protected function wpSpeakerLinkName(Speaker $speaker)
    {
        return implode(' ', array_filter(
            [
                $speaker->salutation,
                $speaker->first_name,
                $speaker->middle_name,
                $speaker->last_name,
            ],
            function ($value) {
                return trim((string) $value) !== '';
            }
        ));
    }


    public function saveWordPressSpeakerLinks()
    {
        $this->assertDataSyncAdmin();

        $this->wpSpeakerLinkError = '';
        $this->wpSpeakerLinkSuccess = '';

        try {
            if ($this->wpSpeakerLinkConfirmed !== true) {
                throw new RuntimeException(
                    'Review the matches and tick the confirmation checkbox.'
                );
            }

            $token = (string) $this->wpSpeakerLinkToken;

            if (!preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
                throw new RuntimeException(
                    'Load the speaker matching list first.'
                );
            }

            $cacheKey = $this->wpSpeakerLinkCacheKey($token);
            $plan = Cache::get($cacheKey);

            if (
                !is_array($plan)
                || !isset($plan['context'], $plan['rows'])
            ) {
                throw new RuntimeException(
                    'The matching list expired. Load it again.'
                );
            }

            $context = $this->wpSpeakerLinkContext();

            if ($plan['context'] !== $context) {
                throw new RuntimeException(
                    'The source, year, category or role mappings changed. '
                        . 'Load the matching list again.'
                );
            }

            if (!is_array($this->wpSpeakerLinkSelections)) {
                throw new RuntimeException('Invalid matching selections.');
            }

            $selected = [];
            $usedTargets = [];

            foreach ($this->wpSpeakerLinkSelections as $wpId => $rawId) {
                if ($rawId === '' || $rawId === null) {
                    continue;
                }

                if (
                    !is_scalar($rawId)
                    || !ctype_digit((string) $rawId)
                    || (int) $rawId < 1
                    || !isset($plan['rows'][$wpId])
                ) {
                    throw new RuntimeException(
                        'Invalid WordPress or app speaker selection.'
                    );
                }

                $speakerId = (int) $rawId;

                if (isset($usedTargets[$speakerId])) {
                    throw new RuntimeException(
                        'App speaker #' . $speakerId
                            . ' was selected for more than one WordPress record.'
                    );
                }

                $usedTargets[$speakerId] = true;
                $selected[(int) $wpId] = $speakerId;
            }

            if (!$selected) {
                throw new RuntimeException(
                    'Choose at least one existing app speaker to link.'
                );
            }

            // Recheck the source without trusting the displayed public rows.
            $freshRows = app(WordPressSpeakerPreview::class)->fetch(
                $context['post_type'],
                $context['year'],
                $context['source_url']
            );

            $freshById = [];

            foreach ($freshRows as $row) {
                $wpId = (int) $row['wp_id'];

                if ($wpId < 1 || isset($freshById[$wpId])) {
                    throw new RuntimeException(
                        'Invalid or duplicate WordPress speaker ID.'
                    );
                }

                $freshById[$wpId] = $row;
            }

            foreach ($selected as $wpId => $speakerId) {
                $fresh = $freshById[$wpId] ?? null;

                if (!$fresh || $fresh['eligibility'] !== 'Eligible') {
                    throw new RuntimeException(
                        'WordPress speaker #' . $wpId
                            . ' is no longer eligible in this source/year. '
                            . 'Reload the preview and matching list.'
                    );
                }

                $roleKey = sha1(trim((string) $fresh['role']));
                $mappedType = $context['role_mappings'][$roleKey] ?? null;

                if (
                    !$mappedType
                    || (int) $mappedType !==
                    (int) $plan['rows'][$wpId]['speaker_type_id']
                ) {
                    throw new RuntimeException(
                        'A selected speaker’s WordPress role changed. '
                            . 'Reload the preview and mapping.'
                    );
                }
            }

            $count = DB::transaction(function () use (
                $cacheKey,
                $plan,
                $context,
                $selected
            ) {
                // Serialize this workflow's writes for the event.
                $event = \App\Models\Event::whereKey($context['event_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $currentPlan = Cache::get($cacheKey);

                if ($currentPlan !== $plan) {
                    throw new RuntimeException(
                        'The matching list expired or was already used. '
                            . 'Load it again.'
                    );
                }

                if (
                    $context['feature_id'] !== 0
                    && !\App\Models\Feature::where('event_id', $event->id)
                        ->whereKey($context['feature_id'])
                        ->exists()
                ) {
                    throw new RuntimeException(
                        'The destination category no longer belongs to this event.'
                    );
                }

                $created = 0;

                foreach ($selected as $wpId => $speakerId) {
                    $row = $plan['rows'][$wpId];

                    if (
                        !\App\Models\SpeakerType::where(
                            'event_id',
                            $event->id
                        )
                            ->whereKey($row['speaker_type_id'])
                            ->exists()
                    ) {
                        throw new RuntimeException(
                            'A mapped speaker type is no longer available.'
                        );
                    }

                    $speaker = Speaker::where('event_id', $event->id)
                        ->where('feature_id', $row['feature_id'])
                        ->whereKey($speakerId)
                        ->lockForUpdate()
                        ->first();

                    if (!$speaker) {
                        throw new RuntimeException(
                            'App speaker #' . $speakerId
                                . ' does not belong to this event/category.'
                        );
                    }

                    $existing = DB::table('wordpress_speaker_links')
                        ->where('identity_hash', $row['identity_hash'])
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        if ((int) $existing->speaker_id !== $speakerId) {
                            throw new RuntimeException(
                                'WordPress speaker #' . $wpId
                                    . ' is already linked to another app speaker.'
                            );
                        }

                        // Matching saved link: no additional insert.
                        continue;
                    }

                    if (
                        DB::table('wordpress_speaker_links')
                        ->where('speaker_id', $speakerId)
                        ->exists()
                    ) {
                        throw new RuntimeException(
                            'App speaker #' . $speakerId
                                . ' is already linked to another WordPress record.'
                        );
                    }

                    DB::table('wordpress_speaker_links')->insert([
                        'event_id' => (int) $event->id,
                        'speaker_id' => $speakerId,
                        'source_url' => $context['source_url'],
                        'post_type' => $context['post_type'],
                        'wordpress_post_id' => $wpId,
                        'identity_hash' => $row['identity_hash'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $created++;
                }

                return $created;
            });

            // Invalidate the plan only after the transaction succeeds.
            $this->resetWordPressSpeakerLinking();

            $this->wpSpeakerLinkSuccess =
                'Saved ' . $count . ' new speaker link(s). '
                . 'No speaker details, images, order or session assignments '
                . 'were changed. Reload the matching list to verify.';


            $this->resetWordPressSpeakerUpdates();
            $this->resetWordPressSpeakerCreation();
        } catch (Throwable $exception) {
            report($exception);

            $this->wpSpeakerLinkConfirmed = false;

            $this->wpSpeakerLinkError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }


    public function repairMissingWordPressSpeakerLink()
    {
        $this->assertDataSyncAdmin();

        $eventId = (int) $this->eventData['eventId'];

        $removed = DB::transaction(function () use ($eventId) {
            \App\Models\Event::whereKey($eventId)
                ->lockForUpdate()
                ->firstOrFail();

            // Check the raw table, including any soft-deleted records.
            $speakerExists = DB::table('speakers')
                ->where('id', 11)
                ->exists();

            if ($speakerExists) {
                throw new RuntimeException(
                    'App speaker #11 still exists in the database. '
                        . 'No link was removed. Review its category or deletion state.'
                );
            }

            $links = DB::table('wordpress_speaker_links')
                ->where('event_id', $eventId)
                ->where('wordpress_post_id', 15242)
                ->where('speaker_id', 11)
                ->lockForUpdate()
                ->get();

            if ($links->count() > 1) {
                throw new RuntimeException(
                    'More than one matching link exists. '
                        . 'No links were removed; review them first.'
                );
            }

            if ($links->isEmpty()) {
                return 0;
            }

            return DB::table('wordpress_speaker_links')
                ->where('id', $links->first()->id)
                ->where('event_id', $eventId)
                ->where('wordpress_post_id', 15242)
                ->where('speaker_id', 11)
                ->delete();
        });

        $this->resetWordPressSpeakerPreview();

        $this->wpSpeakerSettingsMessage = $removed
            ? 'The stale link for WP #15242 → App #11 was removed. '
            . 'Reload the speaker source and missing-speaker preview.'
            : 'No matching stale link remains. '
            . 'Reload the speaker source and missing-speaker preview.';
    }
}
