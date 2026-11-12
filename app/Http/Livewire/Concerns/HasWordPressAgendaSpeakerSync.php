<?php

namespace App\Http\Livewire\Concerns;

use App\Models\Event;
use App\Models\Session;
use App\Models\SessionSpeaker;
use App\Models\SessionSpeakerType;
use App\Models\Speaker;
use App\Services\WordPressAgendaSpeakerPreview;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * @mixin \App\Http\Livewire\EventDataSync
 */
trait HasWordPressAgendaSpeakerSync
{
    public $wpAgendaSpeakerRows = [];
    public $wpAgendaSpeakerSelections = [];
    public $wpAgendaSpeakerToken = '';
    public $wpAgendaSpeakerConfirmed = false;
    public $wpAgendaSpeakerLoaded = false;
    public $wpAgendaSpeakerError = '';
    public $wpAgendaSpeakerSuccess = '';
    public $wpAgendaSpeakerNotices = [];

    protected function wpAgendaSpeakerCacheKey(string $token): string
    {
        $owner = hash('sha256', json_encode([
            session()->getId(),
            (string) auth()->id(),
            (int) $this->eventData['eventId'],
            (string) $this->eventData['eventCategory'],
        ], JSON_THROW_ON_ERROR));

        return 'wp-agenda-speakers:' . $owner . ':' . $token;
    }

    protected function wpAgendaSpeakerEvent(bool $lock = false): Event
    {
        $query = Event::whereKey($this->eventData['eventId'])
            ->where('category', $this->eventData['eventCategory']);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    protected function wpAgendaSpeakerConfigHash(Event $event): string
    {
        return hash('sha256', json_encode([
            $event->wordpress_source_url,
            $event->wordpress_agenda_config ?? [],
        ], JSON_THROW_ON_ERROR));
    }

    protected function wpAgendaSpeakerIdentity(
        array $context,
        string $postType,
        int $wpId
    ): string {
        return hash('sha256', json_encode([
            (int) $context['event_id'],
            $context['source_url'],
            $postType,
            $wpId,
        ]));
    }

    protected function wpAgendaSpeakerLink(
        string $table,
        string $targetColumn,
        array $context,
        string $postType,
        int $wpId,
        bool $lock
    ) {
        $identity = $this->wpAgendaSpeakerIdentity(
            $context,
            $postType,
            $wpId
        );

        $query = DB::table($table)
            ->where('identity_hash', $identity);

        if ($lock) {
            $query->lockForUpdate();
        }

        $link = $query->first();

        if (!$link) {
            return null;
        }

        if (
            (int) $link->event_id !== (int) $context['event_id']
            || (string) $link->source_url !== $context['source_url']
            || (string) $link->post_type !== $postType
            || (int) $link->wordpress_post_id !== $wpId
            || (int) $link->{$targetColumn} < 1
        ) {
            throw new RuntimeException(
                'An invalid saved WordPress link was found. '
                    . 'Review the link before syncing assignments.'
            );
        }

        return $link;
    }

    protected function wpAgendaSpeakerRoleKey(string $role): string
    {
        return mb_strtolower(trim($role), 'UTF-8');
    }

    protected function wpAgendaSpeakerTypes(
        int $eventId,
        int $sessionId,
        string $role,
        bool $lock = false
    ) {
        $query = SessionSpeakerType::where('event_id', $eventId)
            ->where('session_id', $sessionId)
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $key = $this->wpAgendaSpeakerRoleKey($role);

        return $query->get()->filter(function ($type) use ($key) {
            return $this->wpAgendaSpeakerRoleKey(
                (string) $type->name
            ) === $key;
        })->values();
    }

    protected function wpAgendaSpeakerBuildRows(
        array $data,
        bool $lock = false
    ): array {
        $context = $data['context'];
        $rows = [];

        foreach ($data['rows'] as $sourceRow) {
            $wpSessionId = (int) $sourceRow['wordpress_session_id'];
            $wpSpeakerId = (int) $sourceRow['wordpress_speaker_id'];

            $key = 'a_' . $wpSessionId . '_s_' . $wpSpeakerId;

            if (isset($rows[$key])) {
                throw new RuntimeException(
                    'Duplicate agenda–speaker connection.'
                );
            }

            $row = [
                'key' => $key,
                'wordpress_session_id' => $wpSessionId,
                'wordpress_speaker_id' => $wpSpeakerId,
                'session_title' => (string) $sourceRow['session_title'],
                'speaker_name' => 'WP #' . $wpSpeakerId,
                'session_id' => null,
                'speaker_id' => null,
                'type_id' => null,
                'role' => (string) $sourceRow['session_role'],
                'role_origin' => (string) $sourceRow['role_origin'],
                'source_role' => (string) $sourceRow['source_role'],
                'status' => 'blocked',
                'message' => '',
                'will_create_type' => false,
                'source_snapshot' => $sourceRow,
                'agenda_link_id' => null,
                'speaker_link_id' => null,
            ];

            if (!empty($sourceRow['issues'])) {
                $row['message'] = 'Agenda validation issues: '
                    . implode(' ', $sourceRow['issues']);

                $rows[$key] = $row;
                continue;
            }

            $agendaLink = $this->wpAgendaSpeakerLink(
                'wordpress_agenda_links',
                'session_id',
                $context,
                $context['agenda_post_type'],
                $wpSessionId,
                $lock
            );

            $speakerLink = $this->wpAgendaSpeakerLink(
                'wordpress_speaker_links',
                'speaker_id',
                $context,
                $context['speaker_post_type'],
                $wpSpeakerId,
                $lock
            );

            if (!$agendaLink || !$speakerLink) {
                $missing = [];

                if (!$agendaLink) {
                    $missing[] = 'agenda';
                }

                if (!$speakerLink) {
                    $missing[] = 'speaker';
                }

                $row['message'] = 'Missing saved app link for '
                    . implode(' and ', $missing)
                    . '. Link or import the record first.';

                $rows[$key] = $row;
                continue;
            }

            $row['agenda_link_id'] = (int) $agendaLink->id;
            $row['speaker_link_id'] = (int) $speakerLink->id;

            $sessionQuery = Session::where(
                'event_id',
                $context['event_id']
            )->whereKey($agendaLink->session_id);

            $speakerQuery = Speaker::where(
                'event_id',
                $context['event_id']
            )->whereKey($speakerLink->speaker_id);

            if ($lock) {
                $sessionQuery->lockForUpdate();
                $speakerQuery->lockForUpdate();
            }

            $session = $sessionQuery->first();
            $speaker = $speakerQuery->first();

            if (!$session || !$speaker) {
                $row['message'] =
                    'A saved link points to a missing record '
                    . 'or a record outside this event.';

                $rows[$key] = $row;
                continue;
            }

            $row['session_id'] = (int) $session->id;
            $row['speaker_id'] = (int) $speaker->id;

            $row['speaker_name'] = implode(' ', array_filter(
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

            if (
                (int) $session->feature_id
                !== (int) $context['feature_id']
                || (int) $speaker->feature_id
                !== (int) $context['feature_id']
            ) {
                $row['message'] =
                    'The linked agenda or speaker does not belong '
                    . 'to this source’s mapped app category.';

                $rows[$key] = $row;
                continue;
            }

            $types = $this->wpAgendaSpeakerTypes(
                (int) $context['event_id'],
                (int) $session->id,
                $row['role'],
                $lock
            );

            if ($types->count() > 1) {
                $row['message'] =
                    'This agenda has multiple Speaker Types with '
                    . 'the same role name. Resolve them before syncing.';

                $rows[$key] = $row;
                continue;
            }

            $type = $types->first();

            $row['type_id'] = $type ? (int) $type->id : null;
            $row['will_create_type'] = !$type;

            $assignmentQuery = SessionSpeaker::where(
                'event_id',
                $context['event_id']
            )
                ->where('session_id', $session->id)
                ->where('speaker_id', $speaker->id)
                ->orderBy('id');

            if ($lock) {
                $assignmentQuery->lockForUpdate();
            }

            $assignments = $assignmentQuery->get();

            if ($assignments->isNotEmpty()) {
                if (
                    $assignments->count() === 1
                    && $type
                    && (int) $assignments->first()
                        ->session_speaker_type_id === (int) $type->id
                ) {
                    $row['status'] = 'existing';
                    $row['message'] =
                        'Already assigned under the correct Speaker Type.';
                } else {
                    $row['message'] =
                        'This speaker is already assigned to the agenda '
                        . 'under another type, or has duplicate assignments. '
                        . 'Review manually; nothing will be moved or removed.';
                }

                $rows[$key] = $row;
                continue;
            }

            $row['status'] = 'add';
            $row['message'] = $type
                ? 'Add speaker under the existing Speaker Type.'
                : 'Create the Speaker Type and add the speaker.';

            $rows[$key] = $row;
        }

        return $rows;
    }

    protected function resetWordPressAgendaSpeakerSync()
    {
        $token = (string) $this->wpAgendaSpeakerToken;

        if (preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            Cache::forget(
                $this->wpAgendaSpeakerCacheKey($token)
            );
        }

        $this->wpAgendaSpeakerRows = [];
        $this->wpAgendaSpeakerSelections = [];
        $this->wpAgendaSpeakerToken = '';
        $this->wpAgendaSpeakerConfirmed = false;
        $this->wpAgendaSpeakerLoaded = false;
        $this->wpAgendaSpeakerNotices = [];
    }

    public function updatedWpAgendaSpeakerSelections()
    {
        $this->wpAgendaSpeakerConfirmed = false;
        $this->wpAgendaSpeakerError = '';
        $this->wpAgendaSpeakerSuccess = '';
    }

    protected function wpAgendaSpeakerGetPlan(): array
    {
        $token = (string) $this->wpAgendaSpeakerToken;

        if (!preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            throw new RuntimeException(
                'Load a fresh assignment preview first.'
            );
        }

        $plan = Cache::get(
            $this->wpAgendaSpeakerCacheKey($token)
        );

        if (
            !is_array($plan)
            || !isset($plan['context'], $plan['rows'])
            || !is_array($plan['context'])
            || !is_array($plan['rows'])
        ) {
            throw new RuntimeException(
                'The assignment preview expired. Load it again.'
            );
        }

        $event = $this->wpAgendaSpeakerEvent();

        if (
            (int) $plan['context']['event_id'] !== (int) $event->id
            || $plan['context']['agenda_post_type']
            !== (string) $this->wpPreviewPostType
            || $plan['context']['config_hash']
            !== $this->wpAgendaSpeakerConfigHash($event)
        ) {
            throw new RuntimeException(
                'The selected source or saved settings changed. '
                    . 'Reload the assignment preview.'
            );
        }

        return $plan;
    }


    protected function wpAgendaSpeakerBuildCombinedRows(
        array $data,
        bool $lock = false
    ): array {
        // Preserve the existing addition / conflict checks.
        $rows = $this->wpAgendaSpeakerBuildRows($data, $lock);

        $context = $data['context'];
        $eventId = (int) $context['event_id'];

        $connectedIds = [];
        $connectionSnapshots = [];

        foreach ($data['rows'] as $connection) {
            $wpSessionId = (int) $connection['wordpress_session_id'];

            $connectedIds[$wpSessionId][] =
                (int) $connection['wordpress_speaker_id'];

            $connectionSnapshots[$wpSessionId][] = $connection;
        }

        $seenSessions = [];

        foreach ($data['sessions'] as $sourceSession) {
            // Invalid agenda data must never be used to infer removals.
            if (!empty($sourceSession['issues'])) {
                continue;
            }

            $wpSessionId = (int) $sourceSession['wordpress_session_id'];

            $agendaLink = $this->wpAgendaSpeakerLink(
                'wordpress_agenda_links',
                'session_id',
                $context,
                $context['agenda_post_type'],
                $wpSessionId,
                $lock
            );

            if (!$agendaLink) {
                continue;
            }

            $sessionQuery = Session::where('event_id', $eventId)
                ->whereKey($agendaLink->session_id);

            if ($lock) {
                $sessionQuery->lockForUpdate();
            }

            $session = $sessionQuery->first();

            if (
                !$session
                || (int) $session->feature_id !== (int) $context['feature_id']
            ) {
                continue;
            }

            if (isset($seenSessions[$session->id])) {
                throw new RuntimeException(
                    'Multiple WordPress agendas map to the same app agenda. '
                        . 'Resolve those links before syncing assignments.'
                );
            }

            $seenSessions[$session->id] = true;

            $assignmentQuery = SessionSpeaker::where('event_id', $eventId)
                ->where('session_id', $session->id)
                ->orderBy('id');

            if ($lock) {
                $assignmentQuery->lockForUpdate();
            }

            foreach ($assignmentQuery->get() as $assignment) {
                $speakerQuery = Speaker::where('event_id', $eventId)
                    ->whereKey($assignment->speaker_id);

                if ($lock) {
                    $speakerQuery->lockForUpdate();
                }

                $speaker = $speakerQuery->first();

                // Broken or cross-event assignments require manual repair.
                if (!$speaker) {
                    continue;
                }

                $linkQuery = DB::table('wordpress_speaker_links')
                    ->where('event_id', $eventId)
                    ->where('source_url', $context['source_url'])
                    ->where('post_type', $context['speaker_post_type'])
                    ->where('speaker_id', $speaker->id)
                    ->orderBy('id');

                if ($lock) {
                    $linkQuery->lockForUpdate();
                }

                $links = $linkQuery->get();

                if ($links->count() > 1) {
                    throw new RuntimeException(
                        'App speaker #' . $speaker->id
                            . ' has multiple WordPress mappings for this source. '
                            . 'Resolve the mappings before syncing assignments.'
                    );
                }

                $speakerLink = $links->first();
                $wpSpeakerId = null;

                if ($speakerLink) {
                    $wpSpeakerId = (int) $speakerLink->wordpress_post_id;

                    if (
                        $wpSpeakerId < 1
                        || (string) $speakerLink->identity_hash
                        !== $this->wpAgendaSpeakerIdentity(
                            $context,
                            $context['speaker_post_type'],
                            $wpSpeakerId
                        )
                    ) {
                        throw new RuntimeException(
                            'Invalid WordPress mapping for app speaker #'
                                . $speaker->id . '.'
                        );
                    }

                    if (in_array(
                        $wpSpeakerId,
                        $connectedIds[$wpSessionId] ?? [],
                        true
                    )) {
                        // Present in WordPress. Never offer removal merely
                        // because the existing app Speaker Type differs.
                        continue;
                    }
                }

                $typeQuery = SessionSpeakerType::where('event_id', $eventId)
                    ->where('session_id', $session->id)
                    ->whereKey($assignment->session_speaker_type_id);

                if ($lock) {
                    $typeQuery->lockForUpdate();
                }

                $type = $typeQuery->first();

                $key = 'r_' . (int) $assignment->id;

                if (count($rows) >= 10000) {
                    throw new RuntimeException(
                        'Combined preview limit reached: 10,000 rows.'
                    );
                }

                $rows[$key] = [
                    'key' => $key,
                    'wordpress_session_id' => $wpSessionId,
                    'wordpress_speaker_id' => $wpSpeakerId,
                    'session_title' => (string) $sourceSession['title'],
                    'speaker_name' => trim(implode(' ', array_filter([
                        $speaker->salutation,
                        $speaker->first_name,
                        $speaker->middle_name,
                        $speaker->last_name,
                    ]))),
                    'session_id' => (int) $session->id,
                    'speaker_id' => (int) $speaker->id,
                    'type_id' => $assignment->session_speaker_type_id === null
                        ? null
                        : (int) $assignment->session_speaker_type_id,
                    'role' => $type
                        ? (string) $type->name
                        : 'Unknown Speaker Type',
                    'role_origin' => 'app_assignment',
                    'source_role' => $type ? (string) $type->name : '',
                    'status' => 'remove',
                    'message' => $speakerLink
                        ? 'Remove from this agenda: mapped speaker is absent '
                        . 'from its WordPress connections.'
                        : 'Review manually: no matching WordPress speaker link. '
                        . 'This may be an intentional app-only assignment '
                        . 'or a profile that has not been linked.',
                    'will_create_type' => false,
                    'agenda_link_id' => (int) $agendaLink->id,
                    'speaker_link_id' => $speakerLink
                        ? (int) $speakerLink->id
                        : null,
                    'assignment_id' => (int) $assignment->id,

                    // Kept in the server-side cached plan, not the public table.
                    'source_snapshot' => [
                        'assignment' => $assignment->getRawOriginal(),
                        'session' => $sourceSession,
                        'connections' =>
                        $connectionSnapshots[$wpSessionId] ?? [],
                    ],
                ];
            }
        }

        return $rows;
    }

    public function loadWordPressAgendaSpeakerSync()
    {
        $this->assertDataSyncAdmin();

        $this->resetWordPressAgendaSpeakerSync();

        $this->wpAgendaSpeakerError = '';
        $this->wpAgendaSpeakerSuccess = '';

        try {
            set_time_limit(120);

            if (!in_array(
                config('cache.default'),
                ['file', 'database', 'redis', 'memcached', 'dynamodb'],
                true
            )) {
                throw new RuntimeException(
                    'A persistent cache driver is required.'
                );
            }

            $postType = (string) $this->wpPreviewPostType;

            if ($postType === '') {
                throw new RuntimeException(
                    'Select an agenda feature first.'
                );
            }

            $data = app(
                WordPressAgendaSpeakerPreview::class
            )->fetch(
                $this->wpAgendaSpeakerEvent(),
                $postType
            );

            $rows = $this->wpAgendaSpeakerBuildCombinedRows($data);

            $eligible = [];

            foreach ($rows as $key => $row) {
                $display = $row;
                unset($display['source_snapshot']);

                $this->wpAgendaSpeakerRows[] = $display;

                if (in_array($row['status'], ['add', 'remove'], true)) {
                    $eligible[$key] = $row;
                    $this->wpAgendaSpeakerSelections[$key] = false;
                }
            }

            if ($eligible) {
                $token = Str::random(48);

                $stored = Cache::put(
                    $this->wpAgendaSpeakerCacheKey($token),
                    [
                        'context' => $data['context'],
                        'rows' => $eligible,
                    ],
                    now()->addMinutes(15)
                );

                if ($stored === false) {
                    throw new RuntimeException(
                        'Could not store the assignment preview.'
                    );
                }

                $this->wpAgendaSpeakerToken = $token;
            }

            $this->wpAgendaSpeakerLoaded = true;
        } catch (Throwable $exception) {
            report($exception);

            $this->resetWordPressAgendaSpeakerSync();

            $this->wpAgendaSpeakerError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }

    public function selectAllWordPressAgendaSpeakerSync($checked = true)
    {
        $this->assertDataSyncAdmin();

        $this->wpAgendaSpeakerConfirmed = false;
        $this->wpAgendaSpeakerError = '';
        $this->wpAgendaSpeakerSuccess = '';

        try {
            if (!is_bool($checked)) {
                throw new RuntimeException('Invalid selection value.');
            }

            $plan = $this->wpAgendaSpeakerGetPlan();
            $selections = [];

            foreach ($plan['rows'] as $key => $row) {
                // Select-all applies to additions only.
                // Clear-all clears additions and removals.
                $selections[$key] = $checked && $row['status'] === 'add';
            }

            $this->wpAgendaSpeakerSelections = $selections;
        } catch (Throwable $exception) {
            report($exception);

            $this->wpAgendaSpeakerError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }

    public function applyWordPressAgendaSpeakerSync()
    {
        $this->assertDataSyncAdmin();

        $this->wpAgendaSpeakerError = '';
        $this->wpAgendaSpeakerSuccess = '';

        try {
            set_time_limit(120);

            if ($this->wpAgendaSpeakerConfirmed !== true) {
                throw new RuntimeException(
                    'Review the selected additions and removals, '
                        . 'then tick confirmation.'
                );
            }

            $plan = $this->wpAgendaSpeakerGetPlan();

            if (!is_array($this->wpAgendaSpeakerSelections)) {
                throw new RuntimeException('Invalid selections.');
            }

            $selected = [];

            foreach ($this->wpAgendaSpeakerSelections as $key => $checked) {
                if (
                    !is_string($key)
                    || !preg_match(
                        '/^(?:a_[1-9][0-9]*_s_[1-9][0-9]*|r_[1-9][0-9]*)$/',
                        $key
                    )
                    || !is_bool($checked)
                ) {
                    throw new RuntimeException(
                        'Invalid assignment selection.'
                    );
                }

                if (!$checked) {
                    continue;
                }

                if (
                    !isset($plan['rows'][$key])
                    || !in_array(
                        $plan['rows'][$key]['status'],
                        ['add', 'remove'],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'A selected change is not in the saved preview.'
                    );
                }

                $selected[$key] = $plan['rows'][$key];
            }

            if (!$selected) {
                throw new RuntimeException(
                    'Select at least one addition or removal.'
                );
            }

            ksort($selected);

            // Network requests happen before database locks.
            $freshData = app(
                WordPressAgendaSpeakerPreview::class
            )->fetch(
                $this->wpAgendaSpeakerEvent(),
                $plan['context']['agenda_post_type']
            );

            if ($freshData['context'] !== $plan['context']) {
                throw new RuntimeException(
                    'Source settings changed. Reload the preview.'
                );
            }

            $cacheKey = $this->wpAgendaSpeakerCacheKey(
                (string) $this->wpAgendaSpeakerToken
            );

            $result = DB::transaction(function () use (
                $plan,
                $selected,
                $freshData,
                $cacheKey
            ) {
                $event = $this->wpAgendaSpeakerEvent(true);

                if (
                    Cache::get($cacheKey) !== $plan
                    || $this->wpAgendaSpeakerConfigHash($event)
                    !== $plan['context']['config_hash']
                    || (string) $this->wpPreviewPostType
                    !== $plan['context']['agenda_post_type']
                ) {
                    throw new RuntimeException(
                        'The preview expired or source settings changed.'
                    );
                }

                $currentRows = $this->wpAgendaSpeakerBuildCombinedRows(
                    $freshData,
                    true
                );

                // Validate every selected change before performing any writes.
                foreach ($selected as $key => $plannedRow) {
                    if (
                        !isset($currentRows[$key])
                        || $currentRows[$key] !== $plannedRow
                    ) {
                        throw new RuntimeException(
                            'An assignment, mapping, role or WordPress '
                                . 'connection changed. Reload the preview.'
                        );
                    }
                }

                $counts = [
                    'added' => 0,
                    'removed' => 0,
                    'types' => 0,
                ];

                foreach ($selected as $row) {
                    if ($row['status'] === 'remove') {
                        // Delete only the exact approved assignment.
                        $deleted = SessionSpeaker::whereKey(
                            $row['assignment_id']
                        )
                            ->where('event_id', $event->id)
                            ->where('session_id', $row['session_id'])
                            ->where('speaker_id', $row['speaker_id'])
                            ->delete();

                        if ($deleted !== 1) {
                            throw new RuntimeException(
                                'A selected assignment could not be removed. '
                                    . 'The selected changes were rolled back.'
                            );
                        }

                        $counts['removed']++;
                        continue;
                    }

                    $types = $this->wpAgendaSpeakerTypes(
                        (int) $event->id,
                        (int) $row['session_id'],
                        $row['role'],
                        true
                    );

                    if ($types->count() > 1) {
                        throw new RuntimeException(
                            'Duplicate session Speaker Types were found.'
                        );
                    }

                    $type = $types->first();

                    if (!$type) {
                        $type = SessionSpeakerType::create([
                            'event_id' => (int) $event->id,
                            'session_id' => (int) $row['session_id'],
                            'name' => $row['role'],
                            'description' => null,
                            'text_color' => null,
                            'background_color' => null,
                            'datetime_added' => now(),
                        ]);

                        $counts['types']++;
                    }

                    $alreadyAssigned = SessionSpeaker::where(
                        'event_id',
                        $event->id
                    )
                        ->where('session_id', $row['session_id'])
                        ->where('speaker_id', $row['speaker_id'])
                        ->exists();

                    if ($alreadyAssigned) {
                        throw new RuntimeException(
                            'A selected speaker is already assigned. '
                                . 'Reload the preview.'
                        );
                    }

                    SessionSpeaker::create([
                        'event_id' => (int) $event->id,
                        'session_id' => (int) $row['session_id'],
                        'session_speaker_type_id' => (int) $type->id,
                        'speaker_id' => (int) $row['speaker_id'],
                    ]);

                    $counts['added']++;
                }

                return $counts;
            });

            $this->resetWordPressAgendaSpeakerSync();

            $this->wpAgendaSpeakerSuccess =
                'Added ' . $result['added']
                . ' assignment(s), removed ' . $result['removed']
                . ' assignment(s), and created ' . $result['types']
                . ' Speaker Type(s). '
                . 'No speaker profiles, WordPress mappings or existing '
                . 'Speaker Types were deleted. Reload the preview to verify.';
        } catch (Throwable $exception) {
            report($exception);

            $this->wpAgendaSpeakerConfirmed = false;

            $this->wpAgendaSpeakerError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }
}
