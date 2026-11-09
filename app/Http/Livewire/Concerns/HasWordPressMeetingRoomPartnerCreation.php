<?php

namespace App\Http\Livewire\Concerns;

use App\Models\Event;
use App\Models\MeetingRoomPartner;
use App\Services\WordPressMeetingRoomPartnerPreview;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

use App\Enums\MediaEntityTypes;
use App\Enums\MediaUsageUpdateTypes;
use App\Models\Media;


trait HasWordPressMeetingRoomPartnerCreation
{
    public $wpMrpCreateRows = [];
    public $wpMrpCreateSelected = [];
    public $wpMrpCreateToken = '';
    public $wpMrpCreateConfirmed = false;
    public $wpMrpCreateError = '';
    public $wpMrpCreateSuccess = '';
    public $wpMrpCreateEligibleCount = 0;

    protected function wpMrpCreateCacheKey(string $token): string
    {
        // Reuse the session/user/event binding from the linking trait.
        return $this->wpMrpLinkCacheKey($token) . ':create';
    }

    protected function wpMrpCreateNameKey(string $name): string
    {
        $name = html_entity_decode(
            strip_tags($name),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $name = mb_strtolower(trim($name), 'UTF-8');

        // Conservative duplicate detection:
        // punctuation and spacing differences do not make a new partner.
        return preg_replace('/[^\p{L}\p{N}]+/u', '', $name) ?? '';
    }

    protected function wpMrpCreatePayload(array $row): array
    {
        return [
            'name' => (string) $row['name'],
            'location' => (string) $row['location'],
            'profile_html_text' => (string) $row['profile_html_text'],
            'website' => (string) $row['website'],

            // Only accessible source logos can be imported.
            'logo_url' => ($row['logo_status'] ?? '') === 'available'
                ? trim((string) ($row['logo_url'] ?? ''))
                : '',
        ];
    }


    protected function wpMrpCreateLogoUrlAllowed(
        string $url,
        string $sourceUrl
    ): bool {
        if (
            strlen($url) > 2048
            || filter_var($url, FILTER_VALIDATE_URL) === false
        ) {
            return false;
        }

        $parts = parse_url($url);
        $source = parse_url(rtrim(trim($sourceUrl), '/'));

        if (!is_array($parts) || !is_array($source)) {
            return false;
        }

        $sourceHost = strtolower($source['host'] ?? '');

        // WordPress may be installed in a subdirectory.
        $sourcePath = rtrim($source['path'] ?? '', '/');
        $uploadsPrefix = $sourcePath . '/wp-content/uploads/';

        $path = $parts['path'] ?? '';
        $decodedPath = rawurldecode($path);

        return strtolower($source['scheme'] ?? '') === 'https'
            && $sourceHost !== ''
            && strtolower($parts['scheme'] ?? '') === 'https'
            && strtolower($parts['host'] ?? '') === $sourceHost
            && !isset($parts['user'])
            && !isset($parts['pass'])
            && !isset($parts['fragment'])
            && (
                !isset($parts['port'])
                || (int) $parts['port'] === 443
            )
            && strpos($path, $uploadsPrefix) === 0
            && strlen($path) > strlen($uploadsPrefix)
            && !preg_match('/[\x00-\x1F\x7F\\\\]/', $decodedPath)
            && !preg_match(
                '#(?:^|/)\.{1,2}(?:/|$)#',
                $decodedPath
            )
            && !preg_match('/%2f|%5c/i', $path);
    }

    protected function wpMrpCreateWriteLogo(
        MeetingRoomPartner $partner,
        string $url,
        string $sourceUrl
    ): int {
        if (!$this->wpMrpCreateLogoUrlAllowed($url, $sourceUrl)) {
            throw new RuntimeException(
                'The proposed partner logo URL is not allowed.'
            );
        }

        $path = parse_url($url, PHP_URL_PATH);
        $fileName = basename($path ?: '') ?: 'partner-logo';
        $extension = strtolower(
            pathinfo($fileName, PATHINFO_EXTENSION)
        );

        // Same external-URL approach as speaker photos.
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

        // This helper is for newly created partners only.
        mediaUsageUpdate(
            MediaUsageUpdateTypes::ADD_ONLY->value,
            $media->id,
            MediaEntityTypes::MEETING_ROOM_PARTNER_LOGO->value,
            $partner->id,
            null
        );

        return (int) $media->id;
    }


    protected function wpMrpCreateAppNameIndex(int $eventId): array
    {
        $index = [];

        // Include inactive and already-linked partners.
        $partners = MeetingRoomPartner::where('event_id', $eventId)
            ->orderBy('id')
            ->get(['id', 'name']);

        foreach ($partners as $partner) {
            $key = $this->wpMrpCreateNameKey((string) $partner->name);

            if ($key !== '') {
                $index[$key][] = '#' . $partner->id
                    . ' — ' . $partner->name;
            }
        }

        return $index;
    }

    protected function wpMrpCreateSourceNameCounts(array $rows): array
    {
        $counts = [];

        foreach ($rows as $row) {
            $key = $this->wpMrpCreateNameKey((string) $row['name']);

            if ($key !== '') {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return $counts;
    }

    protected function resetWordPressMeetingRoomPartnerCreation()
    {
        $token = (string) $this->wpMrpCreateToken;

        if (preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            Cache::forget($this->wpMrpCreateCacheKey($token));
        }

        $this->wpMrpCreateRows = [];
        $this->wpMrpCreateSelected = [];
        $this->wpMrpCreateToken = '';
        $this->wpMrpCreateConfirmed = false;
        $this->wpMrpCreateEligibleCount = 0;
    }

    public function updatedWpMrpCreateSelected()
    {
        $this->wpMrpCreateConfirmed = false;
        $this->wpMrpCreateSuccess = '';
    }

    public function loadWordPressMeetingRoomPartnerCreation()
    {
        $this->assertDataSyncAdmin();

        $this->resetWordPressMeetingRoomPartnerCreation();

        $this->wpMrpCreateError = '';
        $this->wpMrpCreateSuccess = '';

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

            $existingLinks = DB::table(
                'wordpress_meeting_room_partner_links'
            )
                ->where('event_id', $event->id)
                ->pluck('identity_hash')
                ->flip()
                ->all();

            $appNames = $this->wpMrpCreateAppNameIndex(
                (int) $event->id
            );

            $sourceNameCounts = $this->wpMrpCreateSourceNameCounts(
                $sourceRows
            );

            $eligible = [];

            foreach ($sourceRows as $row) {
                $wpId = (int) $row['wp_id'];
                $identity = $this->wpMrpIdentity($context, $wpId);

                if (isset($existingLinks[$identity])) {
                    continue;
                }

                $nameKey = $this->wpMrpCreateNameKey($row['name']);
                $reasons = array_values($row['issues'] ?? []);

                if (($row['logo_status'] ?? '') === 'available') {
                    $logoUrl = trim((string) ($row['logo_url'] ?? ''));

                    if (!$this->wpMrpCreateLogoUrlAllowed(
                        $logoUrl,
                        $context['source_url']
                    )) {
                        $reasons[] =
                            'The source logo URL is not an allowed HTTPS URL '
                            . 'under this WordPress website’s uploads directory.';
                    }
                }


                if ($nameKey === '') {
                    $reasons[] =
                        'Name cannot be used for duplicate checking.';
                }

                if (
                    $nameKey !== ''
                    && isset($appNames[$nameKey])
                ) {
                    $reasons[] = 'Possible existing app match: '
                        . implode(', ', $appNames[$nameKey])
                        . '. Review matching instead of creating.';
                }

                if (
                    $nameKey !== ''
                    && ($sourceNameCounts[$nameKey] ?? 0) > 1
                ) {
                    $reasons[] =
                        'Multiple WordPress records have a similar name. '
                        . 'Review them before creating.';
                }

                $reasons = array_values(array_unique($reasons));

                $this->wpMrpCreateRows[] = array_merge($row, [
                    'create_allowed' => count($reasons) === 0,
                    'create_block_reasons' => $reasons,
                ]);

                if (!$reasons) {
                    $eligible[$wpId] = [
                        'identity_hash' => $identity,
                        'payload' => $this->wpMrpCreatePayload($row),
                    ];
                }
            }

            $this->wpMrpCreateEligibleCount = count($eligible);

            if (!$this->wpMrpCreateRows) {
                $this->wpMrpCreateSuccess =
                    'No unlinked WordPress partners were found.';
                return;
            }

            // Blocked rows remain visible, but cannot be submitted.
            if (!$eligible) {
                return;
            }

            $token = Str::random(48);

            $stored = Cache::put(
                $this->wpMrpCreateCacheKey($token),
                [
                    'context' => $context,
                    'eligible' => $eligible,
                ],
                now()->addMinutes(15)
            );

            if ($stored === false) {
                throw new RuntimeException(
                    'Could not store the creation preview.'
                );
            }

            $this->wpMrpCreateToken = $token;
        } catch (Throwable $exception) {
            report($exception);

            $this->resetWordPressMeetingRoomPartnerCreation();

            $this->wpMrpCreateError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }

    public function createSelectedWordPressMeetingRoomPartners()
    {
        $this->assertDataSyncAdmin();

        $this->wpMrpCreateError = '';
        $this->wpMrpCreateSuccess = '';

        try {
            if ($this->wpMrpCreateConfirmed !== true) {
                throw new RuntimeException(
                    'Review the selected partners and tick confirmation.'
                );
            }

            $token = (string) $this->wpMrpCreateToken;

            if (!preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
                throw new RuntimeException(
                    'Load a fresh creation preview first.'
                );
            }

            $cacheKey = $this->wpMrpCreateCacheKey($token);
            $plan = Cache::get($cacheKey);

            if (
                !is_array($plan)
                || !isset($plan['context'], $plan['eligible'])
            ) {
                throw new RuntimeException(
                    'The creation preview expired. Load it again.'
                );
            }

            if (!is_array($this->wpMrpCreateSelected)) {
                throw new RuntimeException('Invalid selections.');
            }

            $selected = [];

            foreach ($this->wpMrpCreateSelected as $rawId) {
                if (
                    !is_scalar($rawId)
                    || !ctype_digit((string) $rawId)
                    || (int) $rawId < 1
                    || !isset($plan['eligible'][(int) $rawId])
                ) {
                    throw new RuntimeException(
                        'A selected record is not eligible in this preview.'
                    );
                }

                $selected[(int) $rawId] = (int) $rawId;
            }

            if (!$selected) {
                throw new RuntimeException(
                    'Select at least one partner to create.'
                );
            }

            sort($selected);

            $event = Event::findOrFail(
                $this->eventData['eventId']
            );

            if (
                $this->wpMrpSourceContext($event)
                !== $plan['context']
            ) {
                throw new RuntimeException(
                    'Source settings changed. Reload the creation preview.'
                );
            }

            // Network requests happen before opening the transaction.
            $freshRows = app(
                WordPressMeetingRoomPartnerPreview::class
            )->fetch($event);

            $freshById = [];

            foreach ($freshRows as $row) {
                $freshById[(int) $row['wp_id']] = $row;
            }

            $freshNameCounts = $this->wpMrpCreateSourceNameCounts(
                $freshRows
            );

            foreach ($selected as $wpId) {
                $fresh = $freshById[$wpId] ?? null;
                $planned = $plan['eligible'][$wpId];

                if (
                    !$fresh
                    || !empty($fresh['issues'])
                    || $this->wpMrpCreatePayload($fresh)
                    !== $planned['payload']
                ) {
                    throw new RuntimeException(
                        'WP #' . $wpId
                            . ' changed, disappeared or has validation issues. '
                            . 'Reload the preview.'
                    );
                }

                $key = $this->wpMrpCreateNameKey($fresh['name']);

                if (
                    $key === ''
                    || ($freshNameCounts[$key] ?? 0) !== 1
                ) {
                    throw new RuntimeException(
                        'WP #' . $wpId
                            . ' now has an ambiguous name. Reload the preview.'
                    );
                }
            }

            $created = DB::transaction(function () use (
                $cacheKey,
                $plan,
                $selected
            ) {
                $context = $plan['context'];

                // Serializes this workflow with the matching workflow.
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

                $appNames = $this->wpMrpCreateAppNameIndex(
                    (int) $event->id
                );

                $created = 0;

                foreach ($selected as $wpId) {
                    $entry = $plan['eligible'][$wpId];
                    $payload = $entry['payload'];

                    $alreadyLinked = DB::table(
                        'wordpress_meeting_room_partner_links'
                    )
                        ->where('identity_hash', $entry['identity_hash'])
                        ->exists();

                    if ($alreadyLinked) {
                        throw new RuntimeException(
                            'WP #' . $wpId
                                . ' was already linked. Reload the preview.'
                        );
                    }

                    $nameKey = $this->wpMrpCreateNameKey(
                        $payload['name']
                    );

                    if (
                        $nameKey === ''
                        || isset($appNames[$nameKey])
                    ) {
                        throw new RuntimeException(
                            'A possible app duplicate now exists for '
                                . $payload['name']
                                . '. Reload and review matching.'
                        );
                    }

                    $partner = MeetingRoomPartner::create([
                        'event_id' => (int) $event->id,
                        'name' => $payload['name'],
                        'location' => $payload['location'] !== ''
                            ? $payload['location']
                            : null,
                        'profile_html_text' =>
                        $payload['profile_html_text'] !== ''
                            ? $payload['profile_html_text']
                            : null,
                        'website' => $payload['website'] !== ''
                            ? $payload['website']
                            : null,

                        // WordPress IDs are not local Media IDs.
                        'logo_media_id' => null,
                        'banner_media_id' => null,

                        'is_active' => true,
                        'datetime_added' => now(),
                    ]);

                    if ($payload['logo_url'] !== '') {
                        $partner->logo_media_id = $this->wpMrpCreateWriteLogo(
                            $partner,
                            $payload['logo_url'],
                            $context['source_url']
                        );

                        $partner->save();
                    }


                    DB::table(
                        'wordpress_meeting_room_partner_links'
                    )->insert([
                        'event_id' => (int) $event->id,
                        'meeting_room_partner_id' => (int) $partner->id,
                        'source_url' => $context['source_url'],
                        'post_type' => $context['post_type'],
                        'wordpress_post_id' => $wpId,
                        'identity_hash' => $entry['identity_hash'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $appNames[$nameKey] = [
                        '#' . $partner->id . ' — ' . $partner->name,
                    ];

                    $created++;
                }

                return $created;
            });

            $this->resetWordPressMeetingRoomPartnerCreation();

            // Any previously loaded matching choices are now stale.
            $this->resetWordPressMeetingRoomPartnerLinking();
            $this->wpMrpLinkError = '';
            $this->wpMrpLinkSuccess = '';

            $this->wpMrpCreateSuccess =
                'Created and linked ' . $created . ' partner(s). '
                . 'Available logos were saved as external-URL media records. '
                . 'Missing or inaccessible logos were left empty. '
                . 'Existing partners were not changed.';
        } catch (Throwable $exception) {
            report($exception);

            $this->wpMrpCreateConfirmed = false;

            $this->wpMrpCreateError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }
}
