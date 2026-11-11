<?php

namespace App\Http\Livewire\Concerns;

use App\Models\Event;
use App\Models\Exhibitor;
use App\Services\WordPressExhibitorPreview;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Requires HasWordPressExhibitorLinking
 * and HasWordPressExhibitorUpdates on the host component.
 *
 * @mixin \App\Http\Livewire\EventDataSync
 */
trait HasWordPressExhibitorCreation
{
    public $wpExhibitorCreateRows = [];
    public $wpExhibitorCreateSelected = [];
    public $wpExhibitorCreateToken = '';
    public $wpExhibitorCreateConfirmed = false;
    public $wpExhibitorCreateLoaded = false;
    public $wpExhibitorCreateError = '';
    public $wpExhibitorCreateSuccess = '';
    public $wpExhibitorCreateEligibleCount = 0;

    protected function wpExhibitorCreateCacheKey(string $token): string
    {
        return $this->wpExhibitorLinkCacheKey($token) . ':create';
    }

    protected function wpExhibitorCreateNameKey(string $name): string
    {
        $name = html_entity_decode(
            strip_tags($name),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $name = mb_strtolower(trim($name), 'UTF-8');

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $name) ?? '';
    }

    protected function wpExhibitorCreateEvent(bool $lock = false): Event
    {
        $query = Event::whereKey($this->eventData['eventId'])
            ->where('category', $this->eventData['eventCategory']);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    protected function wpExhibitorCreatePayload(array $row): array
    {
        return [
            'name' => (string) $row['name'],
            'stand_number' => (string) $row['stand_number'],
            'profile_html_text' => (string) $row['profile_html_text'],
            'website' => (string) $row['website'],
            'logo_url' => ($row['logo_status'] ?? '') === 'available'
                ? trim((string) ($row['logo_url'] ?? ''))
                : '',
        ];
    }

    protected function wpExhibitorCreateAppNameIndex(int $eventId): array
    {
        $index = [];

        // Include inactive and already-linked exhibitors.
        $exhibitors = Exhibitor::where('event_id', $eventId)
            ->orderBy('id')
            ->get(['id', 'name']);

        foreach ($exhibitors as $exhibitor) {
            $key = $this->wpExhibitorCreateNameKey(
                (string) $exhibitor->name
            );

            if ($key !== '') {
                $index[$key][] = '#' . $exhibitor->id
                    . ' — ' . $exhibitor->name;
            }
        }

        return $index;
    }

    protected function wpExhibitorCreateSourceNameCounts(
        array $rows
    ): array {
        $counts = [];

        foreach ($rows as $row) {
            $key = $this->wpExhibitorCreateNameKey(
                (string) $row['name']
            );

            if ($key !== '') {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return $counts;
    }

    protected function wpExhibitorCreateSourceMap(array $rows): array
    {
        $map = [];

        foreach ($rows as $row) {
            $wpId = (int) ($row['wp_id'] ?? 0);

            if ($wpId < 1 || isset($map[$wpId])) {
                throw new RuntimeException(
                    'Invalid or duplicate WordPress exhibitor ID.'
                );
            }

            $map[$wpId] = $row;
        }

        return $map;
    }

    protected function wpExhibitorCreateSourceIssues(
        array $row,
        string $sourceUrl
    ): array {
        $issues = array_values($row['issues'] ?? []);

        // Match the supplied exhibitors TEXT column.
        if (strlen((string) $row['profile_html_text']) > 65535) {
            $issues[] = 'Profile exceeds the 65,535-byte import limit.';
        }

        if (($row['logo_status'] ?? '') === 'available') {
            $url = trim((string) ($row['logo_url'] ?? ''));

            if (!$this->wpExhibitorUpdateLogoUrlAllowed(
                $url,
                $sourceUrl
            )) {
                $issues[] =
                    'The logo URL is not an allowed HTTPS URL '
                    . 'under this WordPress website’s uploads directory.';
            }
        }

        return array_values(array_unique($issues));
    }

    protected function resetWordPressExhibitorCreation()
    {
        $token = (string) $this->wpExhibitorCreateToken;

        if (preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            Cache::forget(
                $this->wpExhibitorCreateCacheKey($token)
            );
        }

        $this->wpExhibitorCreateRows = [];
        $this->wpExhibitorCreateSelected = [];
        $this->wpExhibitorCreateToken = '';
        $this->wpExhibitorCreateConfirmed = false;
        $this->wpExhibitorCreateLoaded = false;
        $this->wpExhibitorCreateEligibleCount = 0;
    }

    public function updatedWpExhibitorCreateSelected()
    {
        $this->wpExhibitorCreateConfirmed = false;
        $this->wpExhibitorCreateError = '';
        $this->wpExhibitorCreateSuccess = '';
    }

    public function loadWordPressExhibitorCreation()
    {
        $this->assertDataSyncAdmin();

        $this->resetWordPressExhibitorCreation();

        $this->wpExhibitorCreateError = '';
        $this->wpExhibitorCreateSuccess = '';

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

            $event = $this->wpExhibitorCreateEvent();
            $context = $this->wpExhibitorSourceContext($event);

            $sourceRows = app(
                WordPressExhibitorPreview::class
            )->fetch($event);

            $sourceMap = $this->wpExhibitorCreateSourceMap(
                $sourceRows
            );

            $existingLinks = DB::table('wordpress_exhibitor_links')
                ->where('event_id', $event->id)
                ->pluck('identity_hash')
                ->flip()
                ->all();

            $appNames = $this->wpExhibitorCreateAppNameIndex(
                (int) $event->id
            );

            $sourceNameCounts =
                $this->wpExhibitorCreateSourceNameCounts($sourceRows);

            $eligible = [];

            foreach ($sourceMap as $wpId => $row) {
                $identity = $this->wpExhibitorIdentity(
                    $context,
                    $wpId
                );

                if (isset($existingLinks[$identity])) {
                    continue;
                }

                $nameKey = $this->wpExhibitorCreateNameKey(
                    (string) $row['name']
                );

                $reasons = $this->wpExhibitorCreateSourceIssues(
                    $row,
                    $context['source_url']
                );

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
                        . 'Review the source records before creating.';
                }

                $reasons = array_values(array_unique($reasons));
                $allowed = count($reasons) === 0;

                $this->wpExhibitorCreateRows[] = array_merge(
                    $row,
                    [
                        'create_allowed' => $allowed,
                        'create_block_reasons' => $reasons,
                    ]
                );

                if ($allowed) {
                    $eligible[$wpId] = [
                        'identity_hash' => $identity,
                        'payload' => $this->wpExhibitorCreatePayload($row),
                    ];

                    $this->wpExhibitorCreateSelected[
                        'wp_' . $wpId
                    ] = false;
                }
            }

            $this->wpExhibitorCreateEligibleCount = count($eligible);

            if ($eligible) {
                $token = Str::random(48);

                $stored = Cache::put(
                    $this->wpExhibitorCreateCacheKey($token),
                    [
                        'context' => $context,
                        'eligible' => $eligible,
                    ],
                    now()->addMinutes(15)
                );

                if ($stored === false) {
                    throw new RuntimeException(
                        'Could not store the exhibitor creation preview.'
                    );
                }

                $this->wpExhibitorCreateToken = $token;
            }

            $this->wpExhibitorCreateLoaded = true;
        } catch (Throwable $exception) {
            report($exception);

            $this->resetWordPressExhibitorCreation();

            $this->wpExhibitorCreateError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }

    public function createSelectedWordPressExhibitors()
    {
        $this->assertDataSyncAdmin();

        $this->wpExhibitorCreateError = '';
        $this->wpExhibitorCreateSuccess = '';

        try {
            set_time_limit(120);

            if ($this->wpExhibitorCreateConfirmed !== true) {
                throw new RuntimeException(
                    'Review the selected exhibitors and tick confirmation.'
                );
            }

            $token = (string) $this->wpExhibitorCreateToken;

            if (!preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
                throw new RuntimeException(
                    'Load a fresh exhibitor creation preview first.'
                );
            }

            $cacheKey = $this->wpExhibitorCreateCacheKey($token);
            $plan = Cache::get($cacheKey);

            if (
                !is_array($plan)
                || !isset($plan['context'], $plan['eligible'])
                || !is_array($plan['context'])
                || !is_array($plan['eligible'])
            ) {
                throw new RuntimeException(
                    'The creation preview expired. Load it again.'
                );
            }

            if (!is_array($this->wpExhibitorCreateSelected)) {
                throw new RuntimeException('Invalid selections.');
            }

            $selected = [];

            foreach (
                $this->wpExhibitorCreateSelected
                as $selectionKey => $checked
            ) {
                if (
                    !is_string($selectionKey)
                    || !preg_match(
                        '/^wp_([1-9][0-9]*)$/',
                        $selectionKey,
                        $matches
                    )
                    || !is_bool($checked)
                ) {
                    throw new RuntimeException(
                        'Invalid exhibitor creation selection.'
                    );
                }

                if (!$checked) {
                    continue;
                }

                $wpId = (int) $matches[1];

                if (!isset($plan['eligible'][$wpId])) {
                    throw new RuntimeException(
                        'A selected exhibitor is not eligible in this preview.'
                    );
                }

                $selected[$wpId] = $wpId;
            }

            if (!$selected) {
                throw new RuntimeException(
                    'Select at least one exhibitor to create.'
                );
            }

            sort($selected);

            $event = $this->wpExhibitorCreateEvent();

            if (
                $this->wpExhibitorSourceContext($event)
                !== $plan['context']
            ) {
                throw new RuntimeException(
                    'Source settings changed. Reload the creation preview.'
                );
            }

            // Finish network requests before opening the transaction.
            $freshRows = app(
                WordPressExhibitorPreview::class
            )->fetch($event);

            $freshById = $this->wpExhibitorCreateSourceMap(
                $freshRows
            );

            $freshNameCounts =
                $this->wpExhibitorCreateSourceNameCounts($freshRows);

            foreach ($selected as $wpId) {
                $fresh = $freshById[$wpId] ?? null;
                $planned = $plan['eligible'][$wpId];

                if (
                    !$fresh
                    || $this->wpExhibitorCreateSourceIssues(
                        $fresh,
                        $plan['context']['source_url']
                    )
                    || $this->wpExhibitorCreatePayload($fresh)
                        !== $planned['payload']
                ) {
                    throw new RuntimeException(
                        'WP #' . $wpId
                        . ' changed, disappeared or has validation issues. '
                        . 'Reload the creation preview.'
                    );
                }

                $key = $this->wpExhibitorCreateNameKey(
                    (string) $fresh['name']
                );

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

            $result = DB::transaction(function () use (
                $cacheKey,
                $plan,
                $selected
            ) {
                $context = $plan['context'];

                // Use the same event lock as linking and updates.
                $event = $this->wpExhibitorCreateEvent(true);

                if (
                    $this->wpExhibitorSourceContext($event) !== $context
                    || Cache::get($cacheKey) !== $plan
                ) {
                    throw new RuntimeException(
                        'The preview expired or source settings changed.'
                    );
                }

                $appNames = $this->wpExhibitorCreateAppNameIndex(
                    (int) $event->id
                );

                $created = 0;
                $logos = 0;

                foreach ($selected as $wpId) {
                    $entry = $plan['eligible'][$wpId];
                    $payload = $entry['payload'];

                    $identity = $this->wpExhibitorIdentity(
                        $context,
                        $wpId
                    );

                    if ($identity !== $entry['identity_hash']) {
                        throw new RuntimeException(
                            'The planned source identity is invalid.'
                        );
                    }

                    $alreadyLinked = DB::table(
                        'wordpress_exhibitor_links'
                    )
                        ->where('identity_hash', $identity)
                        ->exists();

                    if ($alreadyLinked) {
                        throw new RuntimeException(
                            'WP #' . $wpId
                            . ' was already linked. Reload the preview.'
                        );
                    }

                    $nameKey = $this->wpExhibitorCreateNameKey(
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

                    $exhibitor = Exhibitor::create([
                        'event_id' => (int) $event->id,
                        'name' => $payload['name'],
                        'stand_number' =>
                            $payload['stand_number'] !== ''
                                ? $payload['stand_number']
                                : null,
                        'profile_html_text' =>
                            $payload['profile_html_text'] !== ''
                                ? $payload['profile_html_text']
                                : null,
                        'website' => $payload['website'] !== ''
                            ? $payload['website']
                            : null,
                        'logo_media_id' => null,
                        'banner_media_id' => null,
                        'is_active' => true,
                        'datetime_added' => now(),
                    ]);

                    if ($payload['logo_url'] !== '') {
                        // Reuse the existing validated external-URL writer.
                        // A new exhibitor has no prior logo usage to remove.
                        $exhibitor->logo_media_id =
                            $this->wpExhibitorUpdateWriteLogo(
                                $exhibitor,
                                $payload['logo_url'],
                                $context['source_url']
                            );

                        $exhibitor->save();
                        $logos++;
                    }

                    DB::table('wordpress_exhibitor_links')->insert([
                        'event_id' => (int) $event->id,
                        'exhibitor_id' => (int) $exhibitor->id,
                        'source_url' => $context['source_url'],
                        'post_type' => $context['post_type'],
                        'wordpress_post_id' => $wpId,
                        'identity_hash' => $identity,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $appNames[$nameKey] = [
                        '#' . $exhibitor->id . ' — ' . $exhibitor->name,
                    ];

                    $created++;
                }

                return [
                    'created' => $created,
                    'logos' => $logos,
                ];
            });

            $this->resetWordPressExhibitorCreation();
            $this->resetWordPressExhibitorLinking();
            $this->resetWordPressExhibitorUpdates();

            $this->wpExhibitorLinkError = '';
            $this->wpExhibitorLinkSuccess = '';
            $this->wpExhibitorUpdateError = '';
            $this->wpExhibitorUpdateSuccess = '';

            $this->wpExhibitorPreviewRows = [];
            $this->wpExhibitorPreviewLoaded = false;
            $this->wpExhibitorPreviewError = '';

            $this->wpExhibitorCreateSuccess =
                'Created and linked ' . $result['created']
                . ' exhibitor(s), including '
                . $result['logos'] . ' external-URL logo(s). '
                . 'Missing or inaccessible logos were left empty. '
                . 'Existing exhibitors were not changed. '
                . 'New exhibitors were created as active.';
        } catch (Throwable $exception) {
            report($exception);

            $this->wpExhibitorCreateConfirmed = false;

            $this->wpExhibitorCreateError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }


    public function selectAllWordPressExhibitorCreation($checked = true)
{
    $this->assertDataSyncAdmin();

    $this->wpExhibitorCreateConfirmed = false;
    $this->wpExhibitorCreateError = '';
    $this->wpExhibitorCreateSuccess = '';

    try {
        if (!is_bool($checked)) {
            throw new RuntimeException('Invalid selection value.');
        }

        $token = (string) $this->wpExhibitorCreateToken;

        if (!preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            throw new RuntimeException(
                'Load a fresh exhibitor creation preview first.'
            );
        }

        $plan = Cache::get(
            $this->wpExhibitorCreateCacheKey($token)
        );

        if (
            !is_array($plan)
            || !isset($plan['context'], $plan['eligible'])
            || !is_array($plan['context'])
            || !is_array($plan['eligible'])
        ) {
            throw new RuntimeException(
                'The creation preview expired. Load it again.'
            );
        }

        $event = $this->wpExhibitorCreateEvent();

        if (
            $this->wpExhibitorSourceContext($event)
            !== $plan['context']
        ) {
            throw new RuntimeException(
                'Source settings changed. Reload the creation preview.'
            );
        }

        $selections = [];

        // Select only records approved in the server-side preview.
        foreach ($plan['eligible'] as $wpId => $entry) {
            $selections['wp_' . $wpId] = $checked;
        }

        $this->wpExhibitorCreateSelected = $selections;
    } catch (Throwable $exception) {
        report($exception);

        $this->wpExhibitorCreateError = mb_substr(
            $exception->getMessage(),
            0,
            600
        );
    }
}

}