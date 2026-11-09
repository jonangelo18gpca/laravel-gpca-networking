<?php

namespace App\Http\Livewire\Concerns;

use App\Enums\MediaEntityTypes;
use App\Enums\MediaUsageUpdateTypes;
use App\Models\Event;
use App\Models\Media;
use App\Models\MeetingRoomPartner;
use App\Services\WordPressMeetingRoomPartnerPreview;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

trait HasWordPressMeetingRoomPartnerUpdates
{
    public $wpMrpUpdateRows = [];
    public $wpMrpUpdateSelections = [];
    public $wpMrpUpdateToken = '';
    public $wpMrpUpdateConfirmed = false;
    public $wpMrpUpdateLoaded = false;
    public $wpMrpUpdateError = '';
    public $wpMrpUpdateSuccess = '';
    public $wpMrpUpdateNotices = [];

    protected function wpMrpUpdateCacheKey(string $token): string
    {
        return $this->wpMrpLinkCacheKey($token) . ':updates';
    }

    protected function wpMrpUpdateLabels(): array
    {
        return [
            'name' => 'Name',
            'location' => 'Location',
            'profile_html_text' => 'Profile HTML',
            'website' => 'Website',
            'logo_url' => 'Logo URL',
        ];
    }

    protected function wpMrpUpdateSourceValues(
        array $row,
        string $sourceUrl
    ): array {
        $values = [
            'name' => (string) $row['name'],
            'location' => (string) $row['location'],
            'profile_html_text' => (string) $row['profile_html_text'],
            'website' => (string) $row['website'],
        ];

        // Missing or denied source logos are not proposed as removals.
        if (($row['logo_status'] ?? '') === 'available') {
            $url = trim((string) ($row['logo_url'] ?? ''));

            if ($this->wpMrpCreateLogoUrlAllowed($url, $sourceUrl)) {
                $values['logo_url'] = $url;
            }
        }

        return $values;
    }

    protected function wpMrpUpdateAppSnapshot(
        MeetingRoomPartner $partner,
        string $field,
        bool $lockMedia = false
    ): array {
        if ($field !== 'logo_url') {
            return [
                'value' => (string) ($partner->getAttribute($field) ?? ''),
            ];
        }

        $mediaId = $partner->logo_media_id !== null
            ? (int) $partner->logo_media_id
            : null;

        $url = '';

        if ($mediaId !== null) {
            $query = Media::whereKey($mediaId);

            if ($lockMedia) {
                $query->lockForUpdate();
            }

            $media = $query->first();

            if (!$media) {
                throw new RuntimeException(
                    'App partner #' . $partner->id
                        . ' refers to a missing logo Media record.'
                );
            }

            $url = (string) $media->file_url;
        }

        return [
            'value' => $url,
            'media_id' => $mediaId,
        ];
    }

    protected function resetWordPressMeetingRoomPartnerUpdates()
    {
        $token = (string) $this->wpMrpUpdateToken;

        if (preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            Cache::forget($this->wpMrpUpdateCacheKey($token));
        }

        $this->wpMrpUpdateRows = [];
        $this->wpMrpUpdateSelections = [];
        $this->wpMrpUpdateToken = '';
        $this->wpMrpUpdateConfirmed = false;
        $this->wpMrpUpdateLoaded = false;
        $this->wpMrpUpdateNotices = [];
    }

    public function updatedWpMrpUpdateSelections()
    {
        $this->wpMrpUpdateConfirmed = false;
        $this->wpMrpUpdateSuccess = '';
    }

    public function loadWordPressMeetingRoomPartnerUpdates()
    {
        $this->assertDataSyncAdmin();

        $this->resetWordPressMeetingRoomPartnerUpdates();

        $this->wpMrpUpdateError = '';
        $this->wpMrpUpdateSuccess = '';

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

            $event = Event::findOrFail(
                $this->eventData['eventId']
            );

            $context = $this->wpMrpSourceContext($event);

            $sourceRows = app(
                WordPressMeetingRoomPartnerPreview::class
            )->fetch($event);

            $links = DB::table(
                'wordpress_meeting_room_partner_links'
            )
                ->where('event_id', $event->id)
                ->get()
                ->keyBy('identity_hash');

            $partners = MeetingRoomPartner::where(
                'event_id',
                $event->id
            )->get()->keyBy('id');

            $planRows = [];
            $labels = $this->wpMrpUpdateLabels();

            foreach ($sourceRows as $row) {
                $wpId = (int) $row['wp_id'];
                $identity = $this->wpMrpIdentity($context, $wpId);
                $link = $links->get($identity);

                if (!$link) {
                    continue;
                }

                $partner = $partners->get(
                    (int) $link->meeting_room_partner_id
                );

                if (!$partner) {
                    $this->wpMrpUpdateNotices[] =
                        'WP #' . $wpId
                        . ': linked app partner is missing from this event.';
                    continue;
                }

                // Conservative: source validation issues block this record.
                if (!empty($row['issues'])) {
                    $this->wpMrpUpdateNotices[] =
                        'WP #' . $wpId . ' — ' . $row['name']
                        . ': updates blocked. '
                        . implode(' ', $row['issues']);
                    continue;
                }

                $warnings = array_values($row['warnings'] ?? []);

                if (
                    ($row['logo_status'] ?? '') === 'available'
                    && !$this->wpMrpCreateLogoUrlAllowed(
                        trim((string) ($row['logo_url'] ?? '')),
                        $context['source_url']
                    )
                ) {
                    $warnings[] =
                        'Source logo URL is not allowed. '
                        . 'The existing app logo will be preserved.';
                }

                $sourceValues = $this->wpMrpUpdateSourceValues(
                    $row,
                    $context['source_url']
                );

                $changes = [];
                $plannedFields = [];

                foreach ($sourceValues as $field => $proposed) {
                    $current = $this->wpMrpUpdateAppSnapshot(
                        $partner,
                        $field
                    );

                    // Treat app null and source empty string as equivalent.
                    if ($current['value'] === $proposed) {
                        continue;
                    }

                    $changes[] = [
                        'field' => $field,
                        'label' => $labels[$field],
                        'current' => $current['value'],
                        'proposed' => $proposed,
                    ];

                    $plannedFields[$field] = [
                        'app_snapshot' => $current,
                        'proposed' => $proposed,
                    ];

                    $this->wpMrpUpdateSelections[$wpId][$field] = false;
                }

                if (!$changes) {
                    // Keep unchanged partners out of the changes table.
                    // Still surface source warnings separately.
                    if ($warnings) {
                        $this->wpMrpUpdateNotices[] =
                            'WP #' . $wpId . ' — ' . $row['name']
                            . ': ' . implode(' ', $warnings);
                    }

                    continue;
                }

                $this->wpMrpUpdateRows[] = [
                    'wp_id' => $wpId,
                    'partner_id' => (int) $partner->id,
                    'name' => $row['name'],
                    'changes' => $changes,
                    'warnings' => array_values(array_unique($warnings)),
                ];

                $planRows[$wpId] = [
                    'identity_hash' => $identity,
                    'partner_id' => (int) $partner->id,
                    'fields' => $plannedFields,
                ];
            }

            if ($planRows) {
                $token = Str::random(48);

                $stored = Cache::put(
                    $this->wpMrpUpdateCacheKey($token),
                    [
                        'context' => $context,
                        'rows' => $planRows,
                    ],
                    now()->addMinutes(15)
                );

                if ($stored === false) {
                    throw new RuntimeException(
                        'Could not store the update preview.'
                    );
                }

                $this->wpMrpUpdateToken = $token;
            }

            $this->wpMrpUpdateLoaded = true;
        } catch (Throwable $exception) {
            report($exception);

            $this->resetWordPressMeetingRoomPartnerUpdates();

            $this->wpMrpUpdateError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }

    protected function wpMrpUpdateWriteLogo(
        MeetingRoomPartner $partner,
        string $url,
        string $sourceUrl
    ): int {
        if (!$this->wpMrpCreateLogoUrlAllowed($url, $sourceUrl)) {
            throw new RuntimeException(
                'The proposed partner logo URL is not allowed.'
            );
        }

        $entityType =
            MediaEntityTypes::MEETING_ROOM_PARTNER_LOGO->value;

        $usageId = $partner->logo_media_id
            ? getMediaUsageId(
                $partner->logo_media_id,
                $entityType,
                $partner->id
            )
            : null;

        $path = parse_url($url, PHP_URL_PATH);
        $fileName = basename($path ?: '') ?: 'partner-logo';
        $extension = strtolower(
            pathinfo($fileName, PATHINFO_EXTENSION)
        );

        // Do not rewrite or delete a potentially shared Media record.
        $media = Media::create([
            'file_url' => $url,
            'file_directory' => 'external-url',
            'file_name' => $fileName,
            'file_type' => $extension ?: 'image',
            'file_size' => 0,
            'width' => null,
            'height' => null,
            'date_uploaded' => now(),
        ]);

        mediaUsageUpdate(
            $usageId
                ? MediaUsageUpdateTypes::REMOVED_THEN_ADD->value
                : MediaUsageUpdateTypes::ADD_ONLY->value,
            $media->id,
            $entityType,
            $partner->id,
            $usageId
        );

        return (int) $media->id;
    }

    public function applyWordPressMeetingRoomPartnerUpdates()
    {
        $this->assertDataSyncAdmin();

        $this->wpMrpUpdateError = '';
        $this->wpMrpUpdateSuccess = '';

        try {
            if ($this->wpMrpUpdateConfirmed !== true) {
                throw new RuntimeException(
                    'Review the selected fields and tick confirmation.'
                );
            }

            $token = (string) $this->wpMrpUpdateToken;

            if (!preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
                throw new RuntimeException(
                    'Load a fresh update preview first.'
                );
            }

            $cacheKey = $this->wpMrpUpdateCacheKey($token);
            $plan = Cache::get($cacheKey);

            if (
                !is_array($plan)
                || !isset($plan['context'], $plan['rows'])
            ) {
                throw new RuntimeException(
                    'The update preview expired. Load it again.'
                );
            }

            if (!is_array($this->wpMrpUpdateSelections)) {
                throw new RuntimeException('Invalid update selections.');
            }

            $selected = [];

            foreach ($this->wpMrpUpdateSelections as $wpId => $fields) {
                if (
                    !ctype_digit((string) $wpId)
                    || !is_array($fields)
                ) {
                    throw new RuntimeException(
                        'Invalid update selection format.'
                    );
                }

                foreach ($fields as $field => $checked) {
                    if ($checked === false) {
                        continue;
                    }

                    if (
                        $checked !== true
                        || !isset($plan['rows'][$wpId]['fields'][$field])
                        || !array_key_exists(
                            $field,
                            $this->wpMrpUpdateLabels()
                        )
                    ) {
                        throw new RuntimeException(
                            'A selected field is not in the approved preview.'
                        );
                    }

                    $selected[(int) $wpId][$field] =
                        $plan['rows'][$wpId]['fields'][$field];
                }
            }

            if (!$selected) {
                throw new RuntimeException(
                    'Select at least one changed field.'
                );
            }

            ksort($selected);

            $event = Event::findOrFail(
                $this->eventData['eventId']
            );

            if (
                $this->wpMrpSourceContext($event)
                !== $plan['context']
            ) {
                throw new RuntimeException(
                    'Source settings changed. Reload the update preview.'
                );
            }

            // Refetch before opening the database transaction.
            $freshById = [];

            foreach (
                app(WordPressMeetingRoomPartnerPreview::class)
                    ->fetch($event) as $row
            ) {
                $freshById[(int) $row['wp_id']] = $row;
            }

            foreach ($selected as $wpId => $fields) {
                $fresh = $freshById[$wpId] ?? null;

                if (!$fresh || !empty($fresh['issues'])) {
                    throw new RuntimeException(
                        'WP #' . $wpId
                            . ' disappeared or has validation issues. '
                            . 'Reload the update preview.'
                    );
                }

                $freshValues = $this->wpMrpUpdateSourceValues(
                    $fresh,
                    $plan['context']['source_url']
                );

                foreach ($fields as $field => $entry) {
                    if (
                        !array_key_exists($field, $freshValues)
                        || $freshValues[$field] !== $entry['proposed']
                    ) {
                        throw new RuntimeException(
                            'A selected source field changed for WP #'
                                . $wpId . '. Reload the update preview.'
                        );
                    }
                }
            }

            $result = DB::transaction(function () use (
                $cacheKey,
                $plan,
                $selected
            ) {
                $context = $plan['context'];

                $event = Event::whereKey($context['event_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $this->wpMrpSourceContext($event) !== $context
                    || Cache::get($cacheKey) !== $plan
                ) {
                    throw new RuntimeException(
                        'The preview expired or source settings changed.'
                    );
                }

                $partnerCount = 0;
                $fieldCount = 0;
                $logoCount = 0;
                $usedPartners = [];

                foreach ($selected as $wpId => $fields) {
                    $plannedRow = $plan['rows'][$wpId];

                    $identity = $this->wpMrpIdentity(
                        $context,
                        $wpId
                    );

                    if ($identity !== $plannedRow['identity_hash']) {
                        throw new RuntimeException(
                            'Source identity changed. Reload the preview.'
                        );
                    }

                    $link = DB::table(
                        'wordpress_meeting_room_partner_links'
                    )
                        ->where('event_id', $event->id)
                        ->where('identity_hash', $identity)
                        ->lockForUpdate()
                        ->first();

                    if (
                        !$link
                        || (int) $link->meeting_room_partner_id
                            !== $plannedRow['partner_id']
                    ) {
                        throw new RuntimeException(
                            'A partner link changed. Reload the preview.'
                        );
                    }

                    $partnerId = (int) $link->meeting_room_partner_id;

                    if (isset($usedPartners[$partnerId])) {
                        throw new RuntimeException(
                            'Multiple source records refer to one app partner.'
                        );
                    }

                    $usedPartners[$partnerId] = true;

                    $partner = MeetingRoomPartner::where(
                        'event_id',
                        $event->id
                    )
                        ->whereKey($partnerId)
                        ->lockForUpdate()
                        ->first();

                    if (!$partner) {
                        throw new RuntimeException(
                            'A linked app partner no longer exists.'
                        );
                    }

                    // Check every selected app field before modifying it.
                    foreach ($fields as $field => $entry) {
                        $current = $this->wpMrpUpdateAppSnapshot(
                            $partner,
                            $field,
                            true
                        );

                        if ($current !== $entry['app_snapshot']) {
                            throw new RuntimeException(
                                'A selected app field changed for partner #'
                                    . $partner->id
                                    . '. Reload the update preview.'
                            );
                        }
                    }

                    foreach ($fields as $field => $entry) {
                        $value = $entry['proposed'];

                        if ($field === 'logo_url') {
                            $partner->logo_media_id =
                                $this->wpMrpUpdateWriteLogo(
                                    $partner,
                                    $value,
                                    $context['source_url']
                                );

                            $logoCount++;
                        } else {
                            $partner->setAttribute(
                                $field,
                                $field !== 'name' && $value === ''
                                    ? null
                                    : $value
                            );
                        }

                        $fieldCount++;
                    }

                    $partner->save();
                    $partnerCount++;
                }

                return [
                    'partners' => $partnerCount,
                    'fields' => $fieldCount,
                    'logos' => $logoCount,
                ];
            });

            // Discard the plan only after a successful commit.
            $this->resetWordPressMeetingRoomPartnerUpdates();

            // Names and other fields may affect other loaded previews.
            $this->resetWordPressMeetingRoomPartnerCreation();
            $this->resetWordPressMeetingRoomPartnerLinking();

            $this->wpMrpUpdateSuccess =
                'Updated ' . $result['fields'] . ' selected field(s) '
                . 'across ' . $result['partners'] . ' partner(s), including '
                . $result['logos'] . ' logo(s). '
                . 'Unchecked fields, active status, banners and ordering '
                . 'were not changed. Reload the preview to verify.';
        } catch (Throwable $exception) {
            report($exception);

            $this->wpMrpUpdateConfirmed = false;

            $this->wpMrpUpdateError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }


    public function selectWordPressMeetingRoomPartnerUpdateFields(
    $wpId,
    $checked = true
) {
    $this->assertDataSyncAdmin();

    $this->wpMrpUpdateConfirmed = false;
    $this->wpMrpUpdateSuccess = '';
    $this->wpMrpUpdateError = '';

    try {
        if (
            !is_scalar($wpId)
            || !ctype_digit((string) $wpId)
            || (int) $wpId < 1
            || !is_bool($checked)
        ) {
            throw new RuntimeException(
                'Invalid company selection.'
            );
        }

        $wpId = (int) $wpId;
        $token = (string) $this->wpMrpUpdateToken;

        if (!preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            throw new RuntimeException(
                'Load a fresh update preview first.'
            );
        }

        $plan = Cache::get(
            $this->wpMrpUpdateCacheKey($token)
        );

        if (
            !is_array($plan)
            || !isset($plan['context'], $plan['rows'])
            || !is_array($plan['rows'])
        ) {
            throw new RuntimeException(
                'The update preview expired. Load it again.'
            );
        }

        if (
            !isset($plan['rows'][$wpId]['fields'])
            || !is_array($plan['rows'][$wpId]['fields'])
        ) {
            throw new RuntimeException(
                'This company is not in the approved update preview.'
            );
        }

        $event = Event::findOrFail(
            $this->eventData['eventId']
        );

        if (
            $this->wpMrpSourceContext($event)
            !== $plan['context']
        ) {
            throw new RuntimeException(
                'Source settings changed. Reload the update preview.'
            );
        }

        $companySelections = [];

        // Use only fields approved in the server-side preview.
        foreach ($plan['rows'][$wpId]['fields'] as $field => $entry) {
            $companySelections[$field] = $checked;
        }

        // Replace this company's selections only.
        $this->wpMrpUpdateSelections[$wpId] = $companySelections;
    } catch (Throwable $exception) {
        report($exception);

        $this->wpMrpUpdateError = mb_substr(
            $exception->getMessage(),
            0,
            600
        );
    }
}


public function selectAllWordPressMeetingRoomPartnerUpdates(
    $checked = true
) {
    $this->assertDataSyncAdmin();

    $this->wpMrpUpdateConfirmed = false;
    $this->wpMrpUpdateSuccess = '';
    $this->wpMrpUpdateError = '';

    try {
        if (!is_bool($checked)) {
            throw new RuntimeException('Invalid selection value.');
        }

        $token = (string) $this->wpMrpUpdateToken;

        if (!preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            throw new RuntimeException(
                'Load a fresh update preview first.'
            );
        }

        $plan = Cache::get(
            $this->wpMrpUpdateCacheKey($token)
        );

        if (
            !is_array($plan)
            || !isset($plan['context'], $plan['rows'])
            || !is_array($plan['rows'])
        ) {
            throw new RuntimeException(
                'The update preview expired. Load it again.'
            );
        }

        $event = Event::findOrFail(
            $this->eventData['eventId']
        );

        if (
            $this->wpMrpSourceContext($event)
            !== $plan['context']
        ) {
            throw new RuntimeException(
                'Source settings changed. Reload the update preview.'
            );
        }

        $selections = [];

        // Select or clear only server-approved proposed changes.
        foreach ($plan['rows'] as $wpId => $row) {
            foreach ($row['fields'] as $field => $entry) {
                $selections[(int) $wpId][$field] = $checked;
            }
        }

        $this->wpMrpUpdateSelections = $selections;
    } catch (Throwable $exception) {
        report($exception);

        $this->wpMrpUpdateError = mb_substr(
            $exception->getMessage(),
            0,
            600
        );
    }
}


}