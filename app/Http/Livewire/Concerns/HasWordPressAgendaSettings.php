<?php

namespace App\Http\Livewire\Concerns;

use App\Models\Event;
use App\Models\Feature;
use App\Models\SessionDate;
use App\Models\SessionDay;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @mixin \App\Http\Livewire\EventDataSync
 */
trait HasWordPressAgendaSettings
{
    public $wpSourceUrl = '';
    public $wpAgendaYear = '';
    public $wpAgendaSources = [];
    public $wpAgendaDays = [];

    protected function wpAgendaRelationshipDefaults(): array
    {
        return [
            'enabled' => false,
            'relation_id' => '',
            'speaker_post_type' => '',
            'role_meta_key' => '',
            'speaker_role_meta_key' => 'role',
            'default_role' => 'Speaker',
            'role_labels_text' => '',
        ];
    }

    protected function wpAgendaSettingsEvent(bool $lock = false): Event
    {
        $query = Event::whereKey($this->eventData['eventId'])
            ->where('category', $this->eventData['eventCategory']);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    protected function loadWordPressAgendaSettings()
    {
        $event = $this->wpAgendaSettingsEvent();
        $config = $event->wordpress_agenda_config ?? [];

        $this->wpSourceUrl = $event->wordpress_source_url ?? '';
        $this->wpAgendaYear = (string) (
            $config['year'] ?? $event->year ?? ''
        );

        $sources = $config['sources'] ?? [
            ['post_type' => 'main-plenary-agenda', 'feature_id' => '0'],
            ['post_type' => 'suspav-agenda', 'feature_id' => ''],
            ['post_type' => 'knowledge-agenda', 'feature_id' => ''],
        ];

        $this->wpAgendaSources = [];

        foreach ($sources as $source) {
            $saved = $source['speaker_relationship'] ?? [];
            $labels = $saved['role_labels'] ?? [];
            $labelLines = [];

            if (is_array($labels)) {
                foreach ($labels as $value => $label) {
                    $labelLines[] = (string) $value
                        . '|' . (string) $label;
                }
            }

            $this->wpAgendaSources[] = [
                'post_type' => (string) ($source['post_type'] ?? ''),
                'feature_id' => (string) ($source['feature_id'] ?? ''),
                'speaker_relationship' => [
                    'enabled' => (bool) ($saved['enabled'] ?? false),
                    'relation_id' => (string) (
                        $saved['relation_id'] ?? ''
                    ),
                    'speaker_post_type' => (string) (
                        $saved['speaker_post_type'] ?? ''
                    ),
                    'role_meta_key' => (string) (
                        $saved['role_meta_key'] ?? ''
                    ),
                    'speaker_role_meta_key' => (string) (
                        $saved['speaker_role_meta_key'] ?? 'role'
                    ),
                    'default_role' => (string) (
                        $saved['default_role'] ?? 'Speaker'
                    ),
                    'role_labels_text' => implode("\n", $labelLines),
                ],
            ];
        }

        $this->wpAgendaDays = $config['days'] ?? [
            [
                'wordpress_day' => 'Pre-conference',
                'session_day' => '',
                'session_date' => '',
            ],
            [
                'wordpress_day' => 'Day 1',
                'session_day' => '',
                'session_date' => '',
            ],
            [
                'wordpress_day' => 'Day 2',
                'session_day' => '',
                'session_date' => '',
            ],
        ];
    }

    public function getWpFeatureOptionsProperty()
    {
        return Feature::where('event_id', $this->eventData['eventId'])
            ->orderBy('id')
            ->get();
    }

    public function getWpDateOptionsProperty()
    {
        return SessionDate::where('event_id', $this->eventData['eventId'])
            ->orderBy('session_date')
            ->get();
    }

    public function getWpDayOptionsProperty()
    {
        return SessionDay::where('event_id', $this->eventData['eventId'])
            ->orderBy('id')
            ->get();
    }

    public function addWpAgendaSource()
    {
        $this->assertDataSyncAdmin();

        if (count($this->wpAgendaSources) < 30) {
            $this->wpAgendaSources[] = [
                'post_type' => '',
                'feature_id' => '',
                'speaker_relationship' =>
                $this->wpAgendaRelationshipDefaults(),
            ];
        }
    }

    public function removeWpAgendaSource($index)
    {
        $this->assertDataSyncAdmin();

        if (
            !ctype_digit((string) $index)
            || !isset($this->wpAgendaSources[(int) $index])
        ) {
            return;
        }

        unset($this->wpAgendaSources[(int) $index]);

        $this->wpAgendaSources = array_values(
            $this->wpAgendaSources
        );
    }

    public function addWpAgendaDay()
    {
        $this->assertDataSyncAdmin();

        if (count($this->wpAgendaDays) < 30) {
            $this->wpAgendaDays[] = [
                'wordpress_day' => '',
                'session_day' => '',
                'session_date' => '',
            ];
        }
    }

    public function removeWpAgendaDay($index)
    {
        $this->assertDataSyncAdmin();

        if (
            !ctype_digit((string) $index)
            || !isset($this->wpAgendaDays[(int) $index])
        ) {
            return;
        }

        unset($this->wpAgendaDays[(int) $index]);

        $this->wpAgendaDays = array_values($this->wpAgendaDays);
    }

    protected function wpAgendaValidateRoleName(
        string $value,
        string $errorKey
    ): void {
        if (
            $value === ''
            || mb_strlen($value, 'UTF-8') > 255
            || $value !== strip_tags($value)
            || preg_match('/[\x00-\x1F\x7F|]/', $value)
        ) {
            throw ValidationException::withMessages([
                $errorKey =>
                'Use a plain-text role name of 1–255 characters, '
                    . 'without HTML, control characters or |.',
            ]);
        }
    }

    protected function wpAgendaParseRoleLabels(
        string $text,
        string $errorKey
    ): array {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $text);
        $labels = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (substr_count($line, '|') !== 1) {
                throw ValidationException::withMessages([
                    $errorKey =>
                    'Use one stored-value|App type name mapping per line.',
                ]);
            }

            [$value, $label] = array_map(
                'trim',
                explode('|', $line, 2)
            );

            $this->wpAgendaValidateRoleName($value, $errorKey);
            $this->wpAgendaValidateRoleName($label, $errorKey);

            if (array_key_exists($value, $labels)) {
                throw ValidationException::withMessages([
                    $errorKey =>
                    'A stored role value appears more than once.',
                ]);
            }

            $labels[$value] = $label;

            if (count($labels) > 100) {
                throw ValidationException::withMessages([
                    $errorKey => 'At most 100 role mappings are supported.',
                ]);
            }
        }

        return $labels;
    }

    public function saveWordPressAgendaSettings()
    {
        $this->assertDataSyncAdmin();

        $this->validate([
            'wpAgendaYear' => 'required|digits:4',
            'wpAgendaSources' => 'required|array|min:1|max:30',
            'wpAgendaSources.*' => 'required|array',
            'wpAgendaSources.*.post_type' =>
            'required|string|max:100',
            'wpAgendaSources.*.feature_id' =>
            'required|integer|min:0',
            'wpAgendaSources.*.speaker_relationship' =>
            'required|array',
            'wpAgendaSources.*.speaker_relationship.enabled' =>
            'required|boolean',
            'wpAgendaSources.*.speaker_relationship.relation_id' =>
            'nullable|integer|min:1|max:2147483647',
            'wpAgendaSources.*.speaker_relationship.speaker_post_type' =>
            'nullable|string|max:100',
            'wpAgendaSources.*.speaker_relationship.role_meta_key' =>
            'nullable|string|max:100',
            'wpAgendaSources.*.speaker_relationship.speaker_role_meta_key' =>
            'nullable|string|max:100',
            'wpAgendaSources.*.speaker_relationship.default_role' =>
            'required|string|max:255',
            'wpAgendaSources.*.speaker_relationship.role_labels_text' =>
            'nullable|string|max:30000',
            'wpAgendaDays' => 'required|array|min:1|max:30',
            'wpAgendaDays.*' => 'required|array',
            'wpAgendaDays.*.wordpress_day' =>
            'required|string|max:255',
            'wpAgendaDays.*.session_day' =>
            'required|string|max:255',
            'wpAgendaDays.*.session_date' =>
            'required|date_format:Y-m-d',
        ]);

        $sources = [];
        $seenSources = [];

        foreach ($this->wpAgendaSources as $i => $source) {
            $type = trim($source['post_type']);
            $prefix = "wpAgendaSources.$i";
            $relationship = $source['speaker_relationship'];

            if (!preg_match('/^[a-z0-9_-]+$/', $type)) {
                throw ValidationException::withMessages([
                    "$prefix.post_type" =>
                    'Use the exact WordPress post-type slug.',
                ]);
            }

            if (isset($seenSources[$type])) {
                throw ValidationException::withMessages([
                    "$prefix.post_type" =>
                    'This agenda source is already configured.',
                ]);
            }

            $seenSources[$type] = true;

            $enabled = (bool) $relationship['enabled'];
            $relationId = $relationship['relation_id'] ?? '';
            $speakerType = trim(
                (string) ($relationship['speaker_post_type'] ?? '')
            );
            $roleKey = trim(
                (string) ($relationship['role_meta_key'] ?? '')
            );
            $profileRoleKey = trim(
                (string) ($relationship['speaker_role_meta_key'] ?? '')
            );
            $defaultRole = trim(
                (string) $relationship['default_role']
            );

            if ($enabled && !$relationId) {
                throw ValidationException::withMessages([
                    "$prefix.speaker_relationship.relation_id" =>
                    'Enter the JetEngine relation ID.',
                ]);
            }

            if (
                ($enabled && $speakerType === '')
                || (
                    $speakerType !== ''
                    && !preg_match('/^[a-z0-9_-]+$/', $speakerType)
                )
            ) {
                throw ValidationException::withMessages([
                    "$prefix.speaker_relationship.speaker_post_type" =>
                    'Enter the exact related speaker post-type slug.',
                ]);
            }

            foreach (
                [
                    'role_meta_key' => $roleKey,
                    'speaker_role_meta_key' => $profileRoleKey,
                ] as $field => $value
            ) {
                if (
                    $value !== ''
                    && !preg_match('/^[A-Za-z0-9_-]+$/', $value)
                ) {
                    throw ValidationException::withMessages([
                        "$prefix.speaker_relationship.$field" =>
                        'Use a metadata key containing only letters, '
                            . 'numbers, underscores or hyphens.',
                    ]);
                }
            }

            $this->wpAgendaValidateRoleName(
                $defaultRole,
                "$prefix.speaker_relationship.default_role"
            );

            $labels = $this->wpAgendaParseRoleLabels(
                (string) ($relationship['role_labels_text'] ?? ''),
                "$prefix.speaker_relationship.role_labels_text"
            );

            $sources[] = [
                'post_type' => $type,
                'feature_id' => (int) $source['feature_id'],
                'speaker_relationship' => [
                    'enabled' => $enabled,
                    'relation_id' => $relationId !== ''
                        && $relationId !== null
                        ? (int) $relationId
                        : null,
                    'speaker_post_type' => $speakerType,
                    'role_meta_key' => $roleKey,
                    'speaker_role_meta_key' => $profileRoleKey,
                    'default_role' => $defaultRole,
                    'role_labels' => $labels,
                ],
            ];
        }

        $days = [];
        $seenDays = [];

        foreach ($this->wpAgendaDays as $i => $day) {
            $label = trim($day['wordpress_day']);
            $normalized = mb_strtolower($label, 'UTF-8');

            if ($label === '' || isset($seenDays[$normalized])) {
                throw ValidationException::withMessages([
                    "wpAgendaDays.$i.wordpress_day" =>
                    'Use a non-empty, unique WordPress day label.',
                ]);
            }

            $seenDays[$normalized] = true;

            if (
                substr($day['session_date'], 0, 4)
                !== (string) $this->wpAgendaYear
            ) {
                throw ValidationException::withMessages([
                    "wpAgendaDays.$i.session_date" =>
                    'The date must match the selected event year.',
                ]);
            }

            $days[] = [
                'wordpress_day' => $label,
                'session_day' => $day['session_day'],
                'session_date' => $day['session_date'],
            ];
        }

        DB::transaction(function () use ($sources, $days) {
            $event = $this->wpAgendaSettingsEvent(true);

            $featureIds = Feature::where('event_id', $event->id)
                ->pluck('id')
                ->map(function ($id) {
                    return (int) $id;
                })
                ->all();

            $dateValues = SessionDate::where('event_id', $event->id)
                ->get()
                ->map(function ($row) {
                    return \Carbon\Carbon::parse(
                        $row->session_date
                    )->format('Y-m-d');
                })
                ->all();

            $dayValues = SessionDay::where('event_id', $event->id)
                ->pluck('session_day')
                ->all();

            foreach ($sources as $i => $source) {
                if (
                    $source['feature_id'] !== 0
                    && !in_array(
                        $source['feature_id'],
                        $featureIds,
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        "wpAgendaSources.$i.feature_id" =>
                        'Choose a feature belonging to this event.',
                    ]);
                }
            }

            foreach ($days as $i => $day) {
                if (
                    !in_array($day['session_day'], $dayValues, true)
                    || !in_array(
                        $day['session_date'],
                        $dateValues,
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        "wpAgendaDays.$i.session_date" =>
                        'Choose a date and day already configured '
                            . 'for this event.',
                    ]);
                }
            }

            // Preserve unrelated top-level configuration.
            $config = $event->wordpress_agenda_config ?? [];

            $config['version'] = $config['version'] ?? 1;
            $config['year'] = (string) $this->wpAgendaYear;
            $config['sources'] = $sources;
            $config['days'] = $days;

            $event->wordpress_agenda_config = $config;

            if ($event->save() !== true) {
                throw ValidationException::withMessages([
                    'wpAgendaSources' =>
                    'The Event model rejected the settings save. '
                        . 'Check Event observers and saving/updating listeners.',
                ]);
            }

            $stored = $event->newQuery()
                ->useWritePdo()
                ->whereKey($event->id)
                ->firstOrFail();

            if ($stored->wordpress_agenda_config != $config) {
                throw ValidationException::withMessages([
                    'wpAgendaSources' =>
                    'Settings verification failed: the database did not '
                        . 'return the configuration that was submitted. '
                        . 'The transaction has been rolled back.',
                ]);
            }
        });

        session()->flash(
            'wordpressAgendaSettingsSaved',
            'WordPress agenda and relationship settings saved. '
                . 'No sessions, speakers or assignments were changed.'
        );
    }

    public function saveWordPressGeneralSettings()
    {
        $this->assertDataSyncAdmin();

        $this->wpSourceUrl = rtrim(
            trim((string) $this->wpSourceUrl),
            '/'
        );

        $this->validate([
            'wpSourceUrl' => 'required|url|max:2048',
        ]);

        $parts = parse_url($this->wpSourceUrl);

        if (
            !is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (
                isset($parts['port'])
                && (int) $parts['port'] !== 443
            )
        ) {
            throw ValidationException::withMessages([
                'wpSourceUrl' =>
                'Use an HTTPS WordPress base URL without credentials, '
                    . 'query, fragment or nonstandard port. '
                    . 'A subdirectory path is allowed.',
            ]);
        }

        DB::transaction(function () {
            $event = $this->wpAgendaSettingsEvent(true);

            $savedUrl = rtrim(
                trim((string) $event->wordpress_source_url),
                '/'
            );

            if (
                $savedUrl !== ''
                && $savedUrl !== $this->wpSourceUrl
            ) {
                throw ValidationException::withMessages([
                    'wpSourceUrl' =>
                    'This event already has a WordPress website '
                        . 'configured. Changing websites requires a '
                        . 'source-change review to protect existing '
                        . 'mappings and links. For a new website, '
                        . 'configure it on the new event.',
                ]);
            }

            $event->wordpress_source_url = $this->wpSourceUrl;
            $event->save();
        });

        $this->resetWordPressSpeakerPreview();

        session()->flash(
            'wordpressGeneralSettingsSaved',
            'WordPress general settings saved. '
                . 'No sessions, speaker profiles or links were changed.'
        );
    }
}
