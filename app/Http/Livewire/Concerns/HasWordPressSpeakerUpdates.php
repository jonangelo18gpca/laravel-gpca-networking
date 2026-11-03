<?php

namespace App\Http\Livewire\Concerns;

use App\Models\Event;
use App\Models\Speaker;
use App\Services\WordPressSpeakerPreview;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

trait HasWordPressSpeakerUpdates
{
    public $wpSpeakerUpdateRows = [];
    public $wpSpeakerUpdateLoaded = false;
    public $wpSpeakerUpdateError = '';
    public $wpSpeakerUpdateSuccess = '';

    public $wpSpeakerUpdateToken = '';
    public $wpSpeakerUpdateSelections = [];
    public $wpSpeakerUpdateConfirmed = false;

    protected function wpSpeakerUpdateCacheKey($token)
    {
        return 'wp-speaker-update:'
            . hash(
                'sha256',
                session()->getId() . '|' . (string) auth()->id()
            )
            . ':' . $token;
    }

    protected function resetWordPressSpeakerUpdates()
    {
        $token = (string) $this->wpSpeakerUpdateToken;

        if (preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            Cache::forget($this->wpSpeakerUpdateCacheKey($token));
        }

        $this->wpSpeakerUpdateRows = [];
        $this->wpSpeakerUpdateLoaded = false;
        $this->wpSpeakerUpdateError = '';
        $this->wpSpeakerUpdateSuccess = '';
        $this->wpSpeakerUpdateToken = '';
        $this->wpSpeakerUpdateSelections = [];
        $this->wpSpeakerUpdateConfirmed = false;
    }

    public function updatedWpSpeakerUpdateSelections()
    {
        $this->wpSpeakerUpdateConfirmed = false;
        $this->wpSpeakerUpdateError = '';
        $this->wpSpeakerUpdateSuccess = '';
    }
    protected function wpSpeakerUpdateHash($value)
    {
        return hash('sha256', json_encode(
            $value,
            JSON_THROW_ON_ERROR
        ));
    }

    protected function wpSpeakerUpdateSettingsSnapshot()
    {
        $identity = $this->wpSpeakerSettingsIdentity();

        $settings = DB::table('wordpress_speaker_settings')
            ->where('event_id', $identity['event_id'])
            ->where('identity_hash', $identity['identity_hash'])
            ->first();

        if (!$settings) {
            throw new RuntimeException(
                'Save the speaker source settings before previewing updates.'
            );
        }

        $context = $this->wpSpeakerLinkContext();
        $savedRoles = json_decode(
            $settings->role_mappings,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($savedRoles)) {
            throw new RuntimeException('Invalid saved role mappings.');
        }

        if ((int) $settings->feature_id !== $context['feature_id']) {
            throw new RuntimeException(
                'Save the changed destination settings before continuing.'
            );
        }

        foreach ($this->getWpSpeakerRoleChoicesProperty() as $role) {
            $current = $context['role_mappings'][$role['key']] ?? '';
            $saved = $savedRoles[$role['key']] ?? '';

            if ((string) $current !== (string) $saved) {
                throw new RuntimeException(
                    'Save the changed role mappings before continuing.'
                );
            }
        }

        return $this->wpSpeakerUpdateHash((array) $settings);
    }

    protected function wpSpeakerUpdateSourceSnapshot(array $row)
    {
        return [
            'wp_id' => (int) $row['wp_id'],
            'source' => $row['source'],
            'year' => $row['year'],
            'name' => $row['name'],
            'role' => $row['role'],
            'eligibility' => $row['eligibility'],
            'job_title' => $row['job_title'],
            'company_name' => $row['company_name'],
            'biography_html' => $row['biography_html'] ?? '',
            'media_id' => (int) ($row['media_id'] ?? 0),
            'image_url' => (string) ($row['image_url'] ?? ''),

        ];
    }

    public function loadWordPressSpeakerUpdates()
    {
        $this->assertDataSyncAdmin();
        $this->resetWordPressSpeakerUpdates();

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
                $context['year']
            );

            $displayRows = [];
            $planRows = [];
            $seen = [];

            foreach ($sourceRows as $sourceRow) {
                $wpId = (int) $sourceRow['wp_id'];

                if ($wpId < 1 || isset($seen[$wpId])) {
                    throw new RuntimeException(
                        'Invalid or duplicate WordPress speaker ID.'
                    );
                }

                $seen[$wpId] = true;

                $identity = $this->wpSpeakerUpdateHash([
                    $context['event_id'],
                    $context['source_url'],
                    $context['post_type'],
                    $wpId,
                ]);

                $link = DB::table('wordpress_speaker_links')
                    ->where('event_id', $context['event_id'])
                    ->where('identity_hash', $identity)
                    ->first();

                if (!$link) {
                    continue;
                }

                $speaker = Speaker::where(
                    'event_id',
                    $context['event_id']
                )
                    ->where('feature_id', $context['feature_id'])
                    ->whereKey($link->speaker_id)
                    ->first();

                if (!$speaker) {
                    throw new RuntimeException(
                        'A saved link points to a deleted or recategorized '
                            . 'speaker. Repair it before proceeding.'
                    );
                }

                $incoming = [
                    'job_title' => (string) $sourceRow['job_title'],
                    'company_name' => (string) $sourceRow['company_name'],
                    'biography_html_text' =>
                    (string) ($sourceRow['biography_html'] ?? ''),
                ];

                $labels = [
                    'job_title' => 'Designation',
                    'company_name' => 'Company',
                    'biography_html_text' => 'Biography',
                ];

                $fields = [];
                $proposed = [];

                foreach ($labels as $field => $label) {
                    $currentRaw = (string) $speaker->{$field};
                    $sourceRaw = $incoming[$field];

                    $currentText = $field === 'biography_html_text'
                        ? $this->wpSpeakerUpdatePlainText($currentRaw)
                        : $this->wpSpeakerUpdateNormalize($currentRaw);

                    $sourceText = $field === 'biography_html_text'
                        ? $this->wpSpeakerUpdatePlainText($sourceRaw)
                        : $this->wpSpeakerUpdateNormalize($sourceRaw);

                    if ($sourceRow['eligibility'] !== 'Eligible') {
                        $status = 'Skipped: source is not eligible';
                    } elseif ($sourceText === '') {
                        $status = 'Preserve app value: WordPress is empty';
                    } elseif ($currentText === $sourceText) {
                        $status = 'No text change';
                    } else {
                        $status = 'Proposed change';
                        $proposed[$field] = $sourceRaw;
                        $this->wpSpeakerUpdateSelections[$wpId][$field] = false;
                    }

                    $fields[] = [
                        'field' => $field,
                        'label' => $label,
                        'current' => $currentText,
                        'source' => $sourceText,
                        'status' => $status,
                    ];
                }


                // Name is one grouped change, including intentional clearing
                // of an old salutation or middle name.
                $name = null;
                $nameStatus = 'Skipped: source is not eligible';
                $nameChanged = false;

                if ($sourceRow['eligibility'] === 'Eligible') {
                    $name = $this->wpSpeakerProfileName($sourceRow);

                    if ($name === null) {
                        $nameStatus =
                            'Review the flagged name in the source preview, '
                            . 'then reload this comparison.';
                    } else {
                        foreach ($name as $column => $value) {
                            if (
                                $this->wpSpeakerUpdateNormalize((string) $speaker->{$column})
                                !== $this->wpSpeakerUpdateNormalize($value)
                            ) {
                                $nameChanged = true;
                                break;
                            }
                        }

                        $nameStatus = $nameChanged
                            ? 'Proposed change'
                            : 'No name change';

                        if ($nameChanged) {
                            $proposed['name'] = $name;
                            $this->wpSpeakerUpdateSelections[$wpId]['name'] = false;
                        }
                    }
                }

                $fields[] = [
                    'field' => 'name',
                    'label' => 'Name — all four parts',
                    'current' => $this->wpSpeakerLinkName($speaker),
                    'source' => $name
                        ? 'Salutation: ' . ($name['salutation'] ?: 'None')
                        . "\nFirst: " . $name['first_name']
                        . "\nMiddle: " . ($name['middle_name'] ?: 'None')
                        . "\nLast: " . $name['last_name']
                        : $sourceRow['name'],
                    'status' => $nameStatus,
                ];

                // Include missing or changed photos based on their WordPress URL.
                $currentPhoto = $this->wpSpeakerProfilePhotoUrl($speaker);
                $sourcePhoto = (string) ($sourceRow['image_url'] ?? '');

                $photoStatus = 'Skipped: source is not eligible';

                if ($sourceRow['eligibility'] === 'Eligible') {
                    if ($sourcePhoto === '') {
                        $photoStatus =
                            'Preserve app photo: WordPress image is missing';
                    } elseif (!$this->wpSpeakerProfileImageAllowed($sourcePhoto)) {
                        $photoStatus =
                            'Blocked: unexpected or invalid image URL';
                    } elseif ($currentPhoto === $sourcePhoto) {
                        $photoStatus = 'No URL change';
                    } else {
                        $photoStatus = 'Proposed change';

                        $proposed['profile_photo'] = $sourcePhoto;

                        // Photo changes start selected, including photo-only updates.
                        $this->wpSpeakerUpdateSelections[$wpId]['profile_photo'] = true;
                    }
                }

                $fields[] = [
                    'field' => 'profile_photo',
                    'label' => 'Profile photo URL',
                    'current' => $currentPhoto,
                    'source' => $sourcePhoto,
                    'status' => $photoStatus,
                ];


                $displayRows[] = [
                    'wp_id' => $wpId,
                    'speaker_id' => (int) $speaker->id,
                    'source_name' => $sourceRow['name'],
                    'app_name' => $this->wpSpeakerLinkName($speaker),
                    'eligibility' => $sourceRow['eligibility'],
                    'change_count' => count($proposed),
                    'fields' => $fields,
                ];

                if ($proposed) {
                    $planRows[$wpId] = [
                        'speaker_id' => (int) $speaker->id,
                        'identity_hash' => $identity,
                        'link_hash' => $this->wpSpeakerUpdateHash(
                            (array) $link
                        ),
                        'app_hash' => $this->wpSpeakerUpdateHash(
                            $speaker->getRawOriginal()
                        ),
                        'source_hash' => $this->wpSpeakerUpdateHash(
                            $this->wpSpeakerUpdateSourceSnapshot($sourceRow)
                        ),
                        'values' => $proposed,
                        'photo_hash' => $this->wpSpeakerProfilePhotoHash($speaker),
                    ];
                }
            }

            if ($planRows) {
                $token = Str::random(48);

                Cache::put(
                    $this->wpSpeakerUpdateCacheKey($token),
                    [
                        'context' => $context,
                        'settings_hash' => $settingsHash,
                        'rows' => $planRows,
                    ],
                    now()->addMinutes(15)
                );

                $this->wpSpeakerUpdateToken = $token;
            }

            $this->wpSpeakerUpdateRows = $displayRows;
            $this->wpSpeakerUpdateLoaded = true;
        } catch (Throwable $exception) {
            report($exception);
            $this->resetWordPressSpeakerUpdates();

            $this->wpSpeakerUpdateError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }

    public function applyWordPressSpeakerUpdates()
    {
        $this->assertDataSyncAdmin();
        $this->wpSpeakerUpdateError = '';
        $this->wpSpeakerUpdateSuccess = '';

        try {
            if ($this->wpSpeakerUpdateConfirmed !== true) {
                throw new RuntimeException(
                    'Review the selected changes and tick confirmation.'
                );
            }

            $token = (string) $this->wpSpeakerUpdateToken;

            if (!preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
                throw new RuntimeException('Load a fresh update preview.');
            }

            $cacheKey = $this->wpSpeakerUpdateCacheKey($token);
            $plan = Cache::get($cacheKey);

            if (!$plan) {
                throw new RuntimeException(
                    'The update preview expired. Load it again.'
                );
            }

            if ($plan['context'] !== $this->wpSpeakerLinkContext()) {
                throw new RuntimeException(
                    'Source or mappings changed. Load a fresh preview.'
                );
            }

            if (!is_array($this->wpSpeakerUpdateSelections)) {
                throw new RuntimeException('Invalid field selections.');
            }

            $selected = [];
            $allowed = [
                'job_title',
                'company_name',
                'biography_html_text',
                'name',
                'profile_photo',
            ];

            foreach ($this->wpSpeakerUpdateSelections as $wpId => $fields) {
                if (!is_array($fields)) {
                    throw new RuntimeException('Invalid field selections.');
                }

                foreach ($fields as $field => $checked) {
                    if ($checked === false) {
                        continue;
                    }

                    if (
                        $checked !== true
                        || !in_array($field, $allowed, true)
                        || !isset($plan['rows'][$wpId]['values'][$field])
                    ) {
                        throw new RuntimeException(
                            'A selected field is not in the approved preview.'
                        );
                    }

                    $selected[$wpId][$field] =
                        $plan['rows'][$wpId]['values'][$field];
                }
            }

            if (!$selected) {
                throw new RuntimeException(
                    'Select at least one proposed field change.'
                );
            }

            foreach ($selected as $values) {

                if (isset($values['name'])) {
                    Validator::make(['name' => $values['name']], [
                        'name' => [
                            'required',
                            'array:salutation,first_name,middle_name,last_name',
                        ],
                        'name.salutation' => [
                            'nullable',
                            'string',
                            'max:255',
                            \Illuminate\Validation\Rule::in(
                                config('app.salutations', [])
                            ),
                        ],
                        'name.first_name' => ['required', 'string', 'max:255'],
                        'name.middle_name' => ['nullable', 'string', 'max:255'],
                        'name.last_name' => ['required', 'string', 'max:255'],
                    ])->validate();
                }

                if (
                    isset($values['profile_photo'])
                    && (
                        !is_string($values['profile_photo'])
                        || !$this->wpSpeakerProfileImageAllowed($values['profile_photo'])
                    )
                ) {
                    throw new RuntimeException('Invalid proposed profile-photo URL.');
                }

                Validator::make($values, [
                    'job_title' => ['sometimes', 'required', 'string', 'max:255'],
                    'company_name' => ['sometimes', 'required', 'string', 'max:255'],
                    'biography_html_text' => ['sometimes', 'required', 'string'],
                ])->validate();

                if (
                    isset($values['biography_html_text'])
                    && strlen($values['biography_html_text']) > 65535
                ) {
                    throw new RuntimeException(
                        'Biography exceeds the current TEXT column limit.'
                    );
                }
            }

            $context = $plan['context'];
            $freshById = [];

            foreach (
                app(WordPressSpeakerPreview::class)->fetch(
                    $context['post_type'],
                    $context['year']
                ) as $row
            ) {
                $id = (int) $row['wp_id'];

                if ($id < 1 || isset($freshById[$id])) {
                    throw new RuntimeException(
                        'Invalid or duplicate WordPress ID.'
                    );
                }

                $freshById[$id] = $row;
            }

            foreach ($selected as $wpId => $values) {
                $fresh = $freshById[$wpId] ?? null;

                if (
                    !$fresh
                    || $fresh['eligibility'] !== 'Eligible'
                    || $this->wpSpeakerUpdateHash(
                        $this->wpSpeakerUpdateSourceSnapshot($fresh)
                    ) !== $plan['rows'][$wpId]['source_hash']
                ) {
                    throw new RuntimeException(
                        'WordPress speaker #' . $wpId
                            . ' changed or is no longer eligible. Reload the preview.'
                    );
                }
            }

            $result = DB::transaction(function () use (
                $cacheKey,
                $plan,
                $context,
                $selected
            ) {
                Event::whereKey($context['event_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (Cache::get($cacheKey) !== $plan) {
                    throw new RuntimeException(
                        'The preview expired or was invalidated.'
                    );
                }

                if (
                    $this->wpSpeakerUpdateSettingsSnapshot()
                    !== $plan['settings_hash']
                ) {
                    throw new RuntimeException(
                        'Saved source settings changed. Reload the preview.'
                    );
                }

                $fieldCount = 0;

                foreach ($selected as $wpId => $values) {
                    $row = $plan['rows'][$wpId];

                    $link = DB::table('wordpress_speaker_links')
                        ->where('event_id', $context['event_id'])
                        ->where('identity_hash', $row['identity_hash'])
                        ->lockForUpdate()
                        ->first();

                    if (
                        !$link
                        || $this->wpSpeakerUpdateHash((array) $link)
                        !== $row['link_hash']
                    ) {
                        throw new RuntimeException(
                            'A saved speaker link changed. Reload the preview.'
                        );
                    }

                    $speaker = Speaker::where(
                        'event_id',
                        $context['event_id']
                    )
                        ->where('feature_id', $context['feature_id'])
                        ->whereKey($row['speaker_id'])
                        ->lockForUpdate()
                        ->first();

                    if (
                        !$speaker
                        || $this->wpSpeakerUpdateHash(
                            $speaker->getRawOriginal()
                        ) !== $row['app_hash']
                    ) {
                        throw new RuntimeException(
                            'An app speaker changed after preview. '
                                . 'Reload before applying updates.'
                        );
                    }

                    // Only server-planned, allowlisted values reach this update.
                    $updates = [];

                    foreach (
                        ['job_title', 'company_name', 'biography_html_text']
                        as $column
                    ) {
                        if (array_key_exists($column, $values)) {
                            $updates[$column] = $values[$column];
                        }
                    }

                    if (isset($values['name'])) {
                        foreach (
                            ['salutation', 'first_name', 'middle_name', 'last_name']
                            as $column
                        ) {
                            $value = $values['name'][$column];

                            $updates[$column] = (
                                in_array($column, ['salutation', 'middle_name'], true)
                                && $value === ''
                            ) ? null : $value;
                        }
                    }

                    if (isset($values['profile_photo'])) {
                        if (
                            $this->wpSpeakerProfilePhotoHash($speaker)
                            !== $row['photo_hash']
                        ) {
                            throw new RuntimeException(
                                'The app profile-photo URL changed after preview. '
                                    . 'Reload the comparison.'
                            );
                        }

                        $updates['pfp_media_id'] = $this->wpSpeakerProfileWritePhoto(
                            $speaker,
                            $values['profile_photo']
                        );
                    }

                    $updates['updated_at'] = now();

                    DB::table('speakers')
                        ->where('event_id', $context['event_id'])
                        ->where('id', $row['speaker_id'])
                        ->update($updates);

                    // Count selected groups: Name counts as one, Photo counts as one.
                    $fieldCount += count($values);
                }

                return [
                    'speakers' => count($selected),
                    'fields' => $fieldCount,
                ];
            });

            $this->resetWordPressSpeakerUpdates();

            $this->wpSpeakerUpdateSuccess =
                'Updated ' . $result['fields'] . ' selected field/group(s) across '
                . $result['speakers'] . ' speaker(s). '
                . 'Only selected profile fields were changed. '
                . 'Categories, speaker types, ordering, cover photos and session '
                . 'assignments were not changed. Reload to verify.';
        } catch (Throwable $exception) {
            report($exception);
            $this->wpSpeakerUpdateConfirmed = false;

            $this->wpSpeakerUpdateError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }

    protected function wpSpeakerUpdatePlainText(string $html)
    {
        $html = preg_replace(
            '/<\s*br\s*\/?\s*>|<\/\s*(p|div|li)\s*>/i',
            ' ',
            $html
        ) ?? $html;

        return $this->wpSpeakerUpdateNormalize(
            html_entity_decode(
                strip_tags($html),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            )
        );
    }

    public function selectAllWordPressSpeakerUpdates($checked = true)
    {
        $this->assertDataSyncAdmin();

        $this->wpSpeakerUpdateConfirmed = false;
        $this->wpSpeakerUpdateError = '';
        $this->wpSpeakerUpdateSuccess = '';

        try {
            if (!is_bool($checked)) {
                throw new RuntimeException('Invalid selection value.');
            }

            if (!$checked) {
                $this->wpSpeakerUpdateSelections = [];

                return;
            }

            $token = (string) $this->wpSpeakerUpdateToken;

            if (!preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
                throw new RuntimeException(
                    'Load a fresh linked-speaker update preview first.'
                );
            }

            $plan = Cache::get(
                $this->wpSpeakerUpdateCacheKey($token)
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
                    'The update preview expired. Load it again.'
                );
            }

            if (
                $plan['context'] !== $this->wpSpeakerLinkContext()
                || $plan['settings_hash']
                !== $this->wpSpeakerUpdateSettingsSnapshot()
            ) {
                throw new RuntimeException(
                    'The source settings changed. Reload the update preview.'
                );
            }

            $allowed = [
                'job_title',
                'company_name',
                'biography_html_text',
                'name',
                'profile_photo',
            ];

            $selections = [];

            // Read proposed fields from the server-side plan,
            // not the public display rows.
            foreach ($plan['rows'] as $wpId => $row) {
                foreach ($row['values'] as $field => $value) {
                    if (!in_array($field, $allowed, true)) {
                        throw new RuntimeException(
                            'The update plan contains an unexpected field. '
                                . 'Reload the preview.'
                        );
                    }

                    $selections[$wpId][$field] = true;
                }
            }

            $this->wpSpeakerUpdateSelections = $selections;
        } catch (Throwable $exception) {
            report($exception);

            $this->wpSpeakerUpdateError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }

    protected function wpSpeakerUpdateNormalize(string $text)
    {
        return trim(
            preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? $text
        );
    }
}
