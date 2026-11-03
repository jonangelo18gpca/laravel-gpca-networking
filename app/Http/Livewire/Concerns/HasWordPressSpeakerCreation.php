<?php

namespace App\Http\Livewire\Concerns;

use App\Models\Speaker;
use App\Models\SpeakerType;
use App\Services\WordPressSpeakerPreview;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;
use RuntimeException;

trait HasWordPressSpeakerCreation
{
    public $wpSpeakerCreateRows = [];
    public $wpSpeakerCreateSelections = [];
    public $wpSpeakerCreateToken = '';
    public $wpSpeakerCreateLoaded = false;
    public $wpSpeakerCreateError = '';
    public $wpSpeakerCreateLinkedCount = 0;
    public $wpSpeakerCreateExcludedCount = 0;
    public $wpSpeakerCreateConfirmed = false;
    public $wpSpeakerCreateSuccess = '';



    public function updatedWpSpeakerCreateSelections()
    {
        $this->wpSpeakerCreateConfirmed = false;
        $this->wpSpeakerCreateSuccess = '';
    }



    public function createSelectedWordPressSpeakers()
    {
        $this->assertDataSyncAdmin();

        $this->wpSpeakerCreateError = '';
        $this->wpSpeakerCreateSuccess = '';

        try {
            if ($this->wpSpeakerCreateConfirmed !== true) {
                throw new RuntimeException(
                    'Review the selected speakers and tick confirmation.'
                );
            }

            $token = (string) $this->wpSpeakerCreateToken;

            if (!preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
                throw new RuntimeException(
                    'Load a fresh missing-speaker preview first.'
                );
            }

            $cacheKey = $this->wpSpeakerCreateCacheKey($token);
            $plan = Cache::get($cacheKey);

            if (
                !is_array($plan)
                || !isset(
                    $plan['context'],
                    $plan['settings_hash'],
                    $plan['rows']
                )
                || !is_array($plan['rows'])
            ) {
                throw new RuntimeException(
                    'The creation preview expired. Load it again.'
                );
            }

            $context = $this->wpSpeakerLinkContext();

            if ($plan['context'] !== $context) {
                throw new RuntimeException(
                    'The source, year, category or mappings changed. '
                        . 'Load a fresh creation preview.'
                );
            }

            if (!is_array($this->wpSpeakerCreateSelections)) {
                throw new RuntimeException('Invalid creation selections.');
            }

            $selectedIds = [];
            $photoIds = [];

            foreach (
                $this->wpSpeakerCreateSelections as $wpId => $checked
            ) {
                if ($checked === false) {
                    continue;
                }

                if (
                    $checked !== true
                    || !ctype_digit((string) $wpId)
                    || !isset($plan['rows'][$wpId])
                ) {
                    throw new RuntimeException(
                        'A selected speaker is not in the eligible preview.'
                    );
                }

                $selectedIds[(int) $wpId] = true;
            }

            if (!$selectedIds) {
                throw new RuntimeException(
                    'Select at least one speaker to create.'
                );
            }



            // Preserve preview order and include every allowed planned photo.
            $selected = [];

            foreach ($plan['rows'] as $wpId => $row) {
                $wpId = (int) $wpId;

                if (!isset($selectedIds[$wpId])) {
                    continue;
                }

                $selected[$wpId] = $row;

                if (!empty($row['photo_url'])) {
                    $photoIds[$wpId] = true;
                }
            }

            // Refetch WordPress before opening the database transaction.
            $freshById = [];
            $sourceNameCounts = [];

            foreach (
                app(WordPressSpeakerPreview::class)->fetch(
                    $context['post_type'],
                    $context['year'],
                    $context['source_url']
                ) as $sourceRow
            ) {
                $wpId = (int) $sourceRow['wp_id'];

                if ($wpId < 1 || isset($freshById[$wpId])) {
                    throw new RuntimeException(
                        'Invalid or duplicate WordPress speaker ID.'
                    );
                }

                $freshById[$wpId] = $sourceRow;

                if ($sourceRow['eligibility'] === 'Eligible') {
                    $key = $this->wpSpeakerCreateNameKey(
                        $sourceRow['name']
                    );

                    $sourceNameCounts[$key] =
                        ($sourceNameCounts[$key] ?? 0) + 1;
                }
            }

            foreach ($selected as $wpId => $row) {
                $fresh = $freshById[$wpId] ?? null;

                if (
                    !$fresh
                    || $fresh['eligibility'] !== 'Eligible'
                    || $this->wpSpeakerUpdateHash(
                        $this->wpSpeakerUpdateSourceSnapshot($fresh)
                    ) !== $row['source_hash']
                ) {
                    throw new RuntimeException(
                        'WordPress speaker #' . $wpId
                            . ' changed or is no longer eligible. '
                            . 'Reload the creation preview.'
                    );
                }

                $sourceNameKey = $this->wpSpeakerCreateNameKey(
                    $fresh['name']
                );

                if (
                    $sourceNameKey === ''
                    || $sourceNameKey !== $row['name_key']
                    || ($sourceNameCounts[$sourceNameKey] ?? 0) !== 1
                ) {
                    throw new RuntimeException(
                        'The source name or duplicate checks changed for WP #'
                            . $wpId . '. Reload the creation preview.'
                    );
                }

                $payload = $row['payload'];

                $identity = $this->wpSpeakerUpdateHash([
                    $context['event_id'],
                    $context['source_url'],
                    $context['post_type'],
                    $wpId,
                ]);

                $roleKey = sha1(trim((string) $fresh['role']));
                $typeId = (int) (
                    $context['role_mappings'][$roleKey] ?? 0
                );

                if (
                    $identity !== $row['identity_hash']
                    || (int) $payload['event_id'] !== $context['event_id']
                    || (int) $payload['feature_id'] !== $context['feature_id']
                    || $typeId < 1
                    || (int) $payload['speaker_type_id'] !== $typeId
                ) {
                    throw new RuntimeException(
                        'A planned destination or speaker type changed. '
                            . 'Reload the creation preview.'
                    );
                }

                $name = $this->wpSpeakerProfileName($fresh);

                if ($name === null) {
                    throw new RuntimeException(
                        'WP #' . $wpId
                            . ' requires name review. Reload the preview.'
                    );
                }

                foreach (
                    ['salutation', 'first_name', 'middle_name', 'last_name']
                    as $column
                ) {
                    if ($name[$column] !== $payload[$column]) {
                        throw new RuntimeException(
                            'The reviewed name changed for WP #' . $wpId
                                . '. Reload the creation preview.'
                        );
                    }
                }

                Validator::make($payload, [
                    'salutation' => [
                        'nullable',
                        'string',
                        'max:255',
                        \Illuminate\Validation\Rule::in(
                            config('app.salutations', [])
                        ),
                    ],
                    'first_name' => ['required', 'string', 'max:255'],
                    'middle_name' => ['nullable', 'string', 'max:255'],
                    'last_name' => ['required', 'string', 'max:255'],
                    'job_title' => ['nullable', 'string', 'max:255'],
                    'company_name' => ['nullable', 'string', 'max:255'],
                    'biography_html_text' => ['nullable', 'string'],
                    'is_active' => ['required', 'boolean'],
                ])->validate();

                if (strlen($payload['biography_html_text']) > 65535) {
                    throw new RuntimeException(
                        'Biography exceeds the current TEXT column limit.'
                    );
                }

                if (isset($photoIds[$wpId])) {
                    $freshPhotoUrl = (string) ($fresh['image_url'] ?? '');

                    if (
                        $freshPhotoUrl === ''
                        || $row['photo_url'] !== $freshPhotoUrl
                        || !$this->wpSpeakerProfileImageAllowed($freshPhotoUrl)
                    ) {
                        throw new RuntimeException(
                            'The photo URL changed or is invalid for WP #'
                                . $wpId . '. Reload the creation preview.'
                        );
                    }
                }
            }

            $result = DB::transaction(function () use (
                $cacheKey,
                $plan,
                $context,
                $selected,
                $photoIds
            ) {
                // Use the same event lock as the linking/update workflow.
                $event = \App\Models\Event::whereKey($context['event_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (Cache::get($cacheKey) !== $plan) {
                    throw new RuntimeException(
                        'The creation preview expired or was invalidated.'
                    );
                }

                // Lock the saved settings while validating and writing.
                $settingsIdentity = $this->wpSpeakerSettingsIdentity();

                $settings = DB::table('wordpress_speaker_settings')
                    ->where('event_id', $context['event_id'])
                    ->where(
                        'identity_hash',
                        $settingsIdentity['identity_hash']
                    )
                    ->lockForUpdate()
                    ->first();

                if (
                    !$settings
                    || $this->wpSpeakerUpdateSettingsSnapshot()
                    !== $plan['settings_hash']
                ) {
                    throw new RuntimeException(
                        'Saved source settings changed. Reload the preview.'
                    );
                }

                if (
                    $context['feature_id'] !== 0
                    && !\App\Models\Feature::where('event_id', $event->id)
                        ->whereKey($context['feature_id'])
                        ->lockForUpdate()
                        ->first()
                ) {
                    throw new RuntimeException(
                        'The destination category no longer belongs '
                            . 'to this event.'
                    );
                }

                // Include inactive speakers in duplicate screening.
                $existingSpeakers = Speaker::where('event_id', $event->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $existingNames = [];
                $latestAdded = null;

                foreach ($existingSpeakers as $existingSpeaker) {
                    if (
                        (int) $existingSpeaker->feature_id
                        === $context['feature_id']
                    ) {
                        $key = $this->wpSpeakerCreateNameKey(
                            $this->wpSpeakerLinkName($existingSpeaker)
                        );

                        $existingNames[$key][] =
                            (int) $existingSpeaker->id;
                    }

                    if ($existingSpeaker->datetime_added) {
                        $added = \Carbon\Carbon::parse(
                            $existingSpeaker->datetime_added
                        );

                        if (
                            $latestAdded === null
                            || $added->gt($latestAdded)
                        ) {
                            $latestAdded = $added;
                        }
                    }
                }

                // Strictly append after existing event speaker timestamps.
                $nextAdded = now()->startOfSecond();

                if (
                    $latestAdded !== null
                    && $latestAdded->gte($nextAdded)
                ) {
                    $nextAdded = $latestAdded->copy()
                        ->addSecond()
                        ->startOfSecond();
                }

                $created = 0;
                $photos = 0;

                foreach ($selected as $wpId => $row) {
                    $payload = $row['payload'];

                    $type = SpeakerType::where('event_id', $event->id)
                        ->whereKey($payload['speaker_type_id'])
                        ->lockForUpdate()
                        ->first();

                    if (!$type) {
                        throw new RuntimeException(
                            'A mapped speaker type is no longer available.'
                        );
                    }

                    $existingLink = DB::table('wordpress_speaker_links')
                        ->where('identity_hash', $row['identity_hash'])
                        ->lockForUpdate()
                        ->first();

                    if ($existingLink) {
                        throw new RuntimeException(
                            'WP #' . $wpId
                                . ' was linked after this preview. '
                                . 'Reload before creating speakers.'
                        );
                    }

                    if (isset($existingNames[$row['name_key']])) {
                        throw new RuntimeException(
                            'Possible existing app match for WP #' . $wpId
                                . ': #'
                                . implode(', #', $existingNames[$row['name_key']])
                                . '. Reload and resolve the match first.'
                        );
                    }

                    // Explicit allowlist: never mass-assign browser rows.
                    $speaker = Speaker::create([
                        'event_id' => (int) $event->id,
                        'feature_id' => $context['feature_id'],
                        'speaker_type_id' =>
                        (int) $payload['speaker_type_id'],

                        'salutation' =>
                        $payload['salutation'] === ''
                            ? null
                            : $payload['salutation'],
                        'first_name' => $payload['first_name'],
                        'middle_name' =>
                        $payload['middle_name'] === ''
                            ? null
                            : $payload['middle_name'],
                        'last_name' => $payload['last_name'],

                        'job_title' => $payload['job_title'],
                        'company_name' => $payload['company_name'],
                        'biography_html_text' =>
                        $payload['biography_html_text'],

                        'is_active' => true,
                        'datetime_added' =>
                        $nextAdded->format('Y-m-d H:i:s'),
                    ]);

                    if (isset($photoIds[$wpId])) {
                        $mediaId = $this->wpSpeakerProfileWritePhoto(
                            $speaker,
                            $row['photo_url']
                        );

                        $speaker->pfp_media_id = $mediaId;
                        $speaker->save();

                        $photos++;
                    }

                    DB::table('wordpress_speaker_links')->insert([
                        'event_id' => (int) $event->id,
                        'speaker_id' => (int) $speaker->id,
                        'source_url' => $context['source_url'],
                        'post_type' => $context['post_type'],
                        'wordpress_post_id' => $wpId,
                        'identity_hash' => $row['identity_hash'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $existingNames[$row['name_key']][] =
                        (int) $speaker->id;

                    $nextAdded->addSecond();
                    $created++;
                }

                return [
                    'speakers' => $created,
                    'photos' => $photos,
                ];
            });

            // Only discard the plan after the transaction commits.

            $this->resetWordPressSpeakerLinking();
            $this->resetWordPressSpeakerUpdates();
            $this->resetWordPressSpeakerCreation();

            $this->wpSpeakerCreateSuccess =
                'Created ' . $result['speakers'] . ' active speaker(s), '
                . $result['speakers'] . ' WordPress link(s), and '
                . $result['photos'] . ' automatically included profile photo(s). '
                . 'New speakers were appended after existing speakers. '
                . 'Existing speaker profiles and ordering were not changed. '
                . 'No session assignments were created. '
                . 'Reload the missing-speaker preview to verify.';
        } catch (Throwable $exception) {
            report($exception);

            $this->wpSpeakerCreateConfirmed = false;

            $this->wpSpeakerCreateError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }


    protected function wpSpeakerCreateCacheKey($token)
    {
        return 'wp-speaker-create:'
            . hash(
                'sha256',
                session()->getId() . '|' . (string) auth()->id()
            )
            . ':' . $token;
    }

    protected function resetWordPressSpeakerCreation()
    {
        $this->wpSpeakerCreateConfirmed = false;
        $this->wpSpeakerCreateSuccess = '';

        $token = (string) $this->wpSpeakerCreateToken;

        if (preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            Cache::forget($this->wpSpeakerCreateCacheKey($token));
        }

        $this->wpSpeakerCreateRows = [];
        $this->wpSpeakerCreateSelections = [];
        $this->wpSpeakerCreateToken = '';
        $this->wpSpeakerCreateLoaded = false;
        $this->wpSpeakerCreateError = '';
        $this->wpSpeakerCreateLinkedCount = 0;
        $this->wpSpeakerCreateExcludedCount = 0;
    }

    protected function wpSpeakerCreateNameKey(string $name)
    {
        $name = $this->wpSpeakerUpdateNormalize($name);

        // Ignore recognized leading titles for duplicate screening.
        $choices = config('app.salutations', []);

        usort($choices, function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });

        do {
            $removed = false;

            foreach ($choices as $choice) {
                $pattern = '/^'
                    . preg_quote($choice, '/')
                    . '(?:\s+|$)/iu';

                if (preg_match($pattern, $name, $match)) {
                    $name = trim(substr($name, strlen($match[0])));
                    $removed = true;
                    break;
                }
            }
        } while ($removed);

        return mb_strtolower(
            preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '', $name) ?? $name,
            'UTF-8'
        );
    }

    public function loadWordPressSpeakerCreation()
    {
        $this->assertDataSyncAdmin();
        $this->resetWordPressSpeakerCreation();

        $this->checkWordPressSpeakerMapping();

        try {
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
            $settingsHash = $this->wpSpeakerUpdateSettingsSnapshot();

            $sourceRows = app(WordPressSpeakerPreview::class)->fetch(
                $context['post_type'],
                $context['year'],
                $context['source_url']
            );

            $existingNames = [];

            foreach (
                Speaker::where('event_id', $context['event_id'])
                    ->where('feature_id', $context['feature_id'])
                    ->get() as $speaker
            ) {
                $key = $this->wpSpeakerCreateNameKey(
                    $this->wpSpeakerLinkName($speaker)
                );

                $existingNames[$key][] = (int) $speaker->id;
            }

            $sourceNameCounts = [];
            $seenIds = [];

            foreach ($sourceRows as $row) {
                $id = (int) $row['wp_id'];

                if ($id < 1 || isset($seenIds[$id])) {
                    throw new RuntimeException(
                        'Invalid or duplicate WordPress speaker ID.'
                    );
                }

                $seenIds[$id] = true;

                if ($row['eligibility'] === 'Eligible') {
                    $key = $this->wpSpeakerCreateNameKey($row['name']);
                    $sourceNameCounts[$key] =
                        ($sourceNameCounts[$key] ?? 0) + 1;
                }
            }

            $types = SpeakerType::where(
                'event_id',
                $context['event_id']
            )->get()->keyBy('id');

            $displayRows = [];
            $planRows = [];
            $linkedCount = 0;
            $excludedCount = 0;

            foreach ($sourceRows as $sourceRow) {
                $wpId = (int) $sourceRow['wp_id'];

                if ($sourceRow['eligibility'] !== 'Eligible') {
                    $excludedCount++;
                    continue;
                }

                $identity = $this->wpSpeakerUpdateHash([
                    $context['event_id'],
                    $context['source_url'],
                    $context['post_type'],
                    $wpId,
                ]);

                if (
                    DB::table('wordpress_speaker_links')
                    ->where('identity_hash', $identity)
                    ->exists()
                ) {
                    $linkedCount++;
                    continue;
                }

                $issues = [];
                $warnings = $sourceRow['warnings'];
                $name = null;

                try {
                    $name = $this->wpSpeakerProfileName($sourceRow);

                    if ($name === null) {
                        $issues[] =
                            'Review this name in the source preview, '
                            . 'then reload the creation preview.';
                    }
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    $issues = array_merge(
                        $issues,
                        $exception->validator->errors()->all()
                    );
                } catch (RuntimeException $exception) {
                    $issues[] = $exception->getMessage();
                }

                $nameKey = $this->wpSpeakerCreateNameKey($sourceRow['name']);

                if ($nameKey === '') {
                    $issues[] = 'The source does not contain a usable name.';
                }

                if (isset($existingNames[$nameKey])) {
                    $issues[] =
                        'Possible existing app match in this category: #'
                        . implode(', #', $existingNames[$nameKey])
                        . '. Link or resolve it before creating.';
                }

                if (($sourceNameCounts[$nameKey] ?? 0) > 1) {
                    $issues[] =
                        'Multiple eligible WordPress records have this '
                        . 'normalized name. Resolve them before creating.';
                }

                $roleKey = sha1(trim((string) $sourceRow['role']));
                $typeId = (int) (
                    $context['role_mappings'][$roleKey] ?? 0
                );

                if (!$typeId || !isset($types[$typeId])) {
                    $issues[] =
                        'Missing or invalid speaker-type mapping. '
                        . 'Reload the source preview and save the mapping.';
                }

                $payload = [
                    'event_id' => $context['event_id'],
                    'feature_id' => $context['feature_id'],
                    'speaker_type_id' => $typeId,
                    'salutation' => $name['salutation'] ?? '',
                    'first_name' => $name['first_name'] ?? '',
                    'middle_name' => $name['middle_name'] ?? '',
                    'last_name' => $name['last_name'] ?? '',
                    'job_title' => (string) $sourceRow['job_title'],
                    'company_name' => (string) $sourceRow['company_name'],
                    'biography_html_text' =>
                    (string) ($sourceRow['biography_html'] ?? ''),
                    'is_active' => true,
                ];

                $validator = Validator::make($payload, [
                    'job_title' => ['nullable', 'string', 'max:255'],
                    'company_name' => ['nullable', 'string', 'max:255'],
                ]);

                if ($validator->fails()) {
                    $issues = array_merge(
                        $issues,
                        $validator->errors()->all()
                    );
                }

                if (strlen($payload['biography_html_text']) > 65535) {
                    $issues[] =
                        'Biography exceeds the current TEXT column limit.';
                }

                $photoUrl = (string) ($sourceRow['image_url'] ?? '');
                $photoAllowed = $photoUrl !== ''
                    && $this->wpSpeakerProfileImageAllowed($photoUrl);

                if (!$photoAllowed) {
                    $warnings[] =
                        'No allowed photo is available. '
                        . 'This speaker will be created without a photo.';
                }

                $canCreate = count($issues) === 0;

                $displayRows[] = [
                    'wp_id' => $wpId,
                    'name' => $name
                        ? $this->wpSpeakerProfileNameText($name)
                        : $sourceRow['name'],
                    'name_parts' => $name,
                    'job_title' => $payload['job_title'],
                    'company_name' => $payload['company_name'],
                    'type_name' => isset($types[$typeId])
                        ? $types[$typeId]->name
                        : 'Not mapped',
                    'photo_url' => $photoUrl,
                    'photo_allowed' => $photoAllowed,
                    'can_create' => $canCreate,
                    'issues' => $issues,
                    'warnings' => array_values(array_unique($warnings)),
                ];

                if ($canCreate) {
                    $this->wpSpeakerCreateSelections[$wpId] = false;

                    $planRows[$wpId] = [
                        'identity_hash' => $identity,
                        'source_hash' => $this->wpSpeakerUpdateHash(
                            $this->wpSpeakerUpdateSourceSnapshot($sourceRow)
                        ),
                        'name_key' => $nameKey,
                        'payload' => $payload,
                        'photo_url' => $photoAllowed ? $photoUrl : null,
                    ];
                }
            }

            if ($planRows) {
                $token = Str::random(48);

                Cache::put(
                    $this->wpSpeakerCreateCacheKey($token),
                    [
                        'context' => $context,
                        'settings_hash' => $settingsHash,
                        'rows' => $planRows,
                    ],
                    now()->addMinutes(15)
                );

                $this->wpSpeakerCreateToken = $token;
            }

            $this->wpSpeakerCreateRows = $displayRows;
            $this->wpSpeakerCreateLinkedCount = $linkedCount;
            $this->wpSpeakerCreateExcludedCount = $excludedCount;
            $this->wpSpeakerCreateLoaded = true;
        } catch (Throwable $exception) {
            report($exception);

            $this->resetWordPressSpeakerCreation();

            $this->wpSpeakerCreateError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }

    public function selectAllWordPressSpeakerCreation($checked = true)
    {
        $this->assertDataSyncAdmin();

        $this->wpSpeakerCreateConfirmed = false;
        $this->wpSpeakerCreateSuccess = '';
        $this->wpSpeakerCreateError = '';

        try {
            if (!is_bool($checked)) {
                throw new RuntimeException('Invalid selection value.');
            }

            if (!$checked) {
                foreach ($this->wpSpeakerCreateSelections as $wpId => $value) {
                    $this->wpSpeakerCreateSelections[$wpId] = false;
                }

                return;
            }

            $token = (string) $this->wpSpeakerCreateToken;

            if (!preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
                throw new RuntimeException(
                    'Load a fresh missing-speaker preview first.'
                );
            }

            $plan = Cache::get(
                $this->wpSpeakerCreateCacheKey($token)
            );

            if (
                !is_array($plan)
                || !isset(
                    $plan['context'],
                    $plan['settings_hash'],
                    $plan['rows']
                )
                || !is_array($plan['rows'])
            ) {
                throw new RuntimeException(
                    'The creation preview expired. Load it again.'
                );
            }

            if (
                $plan['context'] !== $this->wpSpeakerLinkContext()
                || $plan['settings_hash']
                !== $this->wpSpeakerUpdateSettingsSnapshot()
            ) {
                throw new RuntimeException(
                    'The source settings changed. Reload the preview.'
                );
            }

            // Only server-approved candidates are selected.
            $selections = [];

            foreach ($plan['rows'] as $wpId => $row) {
                $selections[(int) $wpId] = true;
            }

            $this->wpSpeakerCreateSelections = $selections;
        } catch (Throwable $exception) {
            report($exception);

            $this->wpSpeakerCreateError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }
}
