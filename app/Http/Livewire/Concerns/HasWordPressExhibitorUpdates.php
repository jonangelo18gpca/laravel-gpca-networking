<?php

namespace App\Http\Livewire\Concerns;

use App\Enums\MediaEntityTypes;
use App\Enums\MediaUsageUpdateTypes;
use App\Models\Event;
use App\Models\Exhibitor;
use App\Models\Media;
use App\Services\WordPressExhibitorPreview;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * @mixin \App\Http\Livewire\EventDataSync
 */
trait HasWordPressExhibitorUpdates
{
    public $wpExhibitorUpdateRows = [];
    public $wpExhibitorUpdateSelections = [];
    public $wpExhibitorUpdateToken = '';
    public $wpExhibitorUpdateConfirmed = false;
    public $wpExhibitorUpdateLoaded = false;
    public $wpExhibitorUpdateError = '';
    public $wpExhibitorUpdateSuccess = '';
    public $wpExhibitorUpdateNotices = [];

    protected function wpExhibitorUpdateCacheKey(string $token): string
    {
        return $this->wpExhibitorLinkCacheKey($token) . ':updates';
    }

    protected function wpExhibitorUpdateLabels(): array
    {
        return [
            'name' => 'Name',
            'stand_number' => 'Stand number',
            'profile_html_text' => 'Profile HTML',
            'website' => 'Website',
            'logo_url' => 'Logo URL',
        ];
    }

    protected function wpExhibitorUpdateEvent(bool $lock = false): Event
    {
        $query = Event::whereKey($this->eventData['eventId'])
            ->where('category', $this->eventData['eventCategory']);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    protected function wpExhibitorUpdateLogoUrlAllowed(
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

    protected function wpExhibitorUpdateSourceValues(
        array $row,
        string $sourceUrl
    ): array {
        $values = [
            'name' => (string) $row['name'],
            'stand_number' => (string) $row['stand_number'],
            'profile_html_text' => (string) $row['profile_html_text'],
            'website' => (string) $row['website'],
        ];

        // Missing, invalid or denied logos are never proposed as removals.
        if (($row['logo_status'] ?? '') === 'available') {
            $url = trim((string) ($row['logo_url'] ?? ''));

            if ($this->wpExhibitorUpdateLogoUrlAllowed($url, $sourceUrl)) {
                $values['logo_url'] = $url;
            }
        }

        return $values;
    }

    protected function wpExhibitorUpdateSourceIssues(array $row): array
    {
        $issues = array_values($row['issues'] ?? []);

        // Conservative limit matching the supplied exhibitors TEXT column.
        if (strlen((string) ($row['profile_html_text'] ?? '')) > 65535) {
            $issues[] = 'Profile exceeds the 65,535-byte import limit.';
        }

        return array_values(array_unique($issues));
    }

    protected function wpExhibitorUpdateAppSnapshot(
        Exhibitor $exhibitor,
        string $field,
        bool $lockMedia = false
    ): array {
        if (!array_key_exists($field, $this->wpExhibitorUpdateLabels())) {
            throw new RuntimeException('Unexpected exhibitor update field.');
        }

        if ($field !== 'logo_url') {
            return [
                'value' => (string) ($exhibitor->getAttribute($field) ?? ''),
            ];
        }

        $mediaId = $exhibitor->logo_media_id !== null
            ? (int) $exhibitor->logo_media_id
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
                    'App exhibitor #' . $exhibitor->id
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

    protected function wpExhibitorUpdateLinkMatches(
        $link,
        array $context,
        int $wpId,
        int $exhibitorId
    ): bool {
        return $link
            && (int) $link->event_id === $context['event_id']
            && (int) $link->exhibitor_id === $exhibitorId
            && (string) $link->source_url === $context['source_url']
            && (string) $link->post_type === $context['post_type']
            && (int) $link->wordpress_post_id === $wpId
            && (string) $link->identity_hash
                === $this->wpExhibitorIdentity($context, $wpId);
    }

    protected function wpExhibitorUpdateGetPlan(): array
    {
        $token = (string) $this->wpExhibitorUpdateToken;

        if (!preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            throw new RuntimeException(
                'Load a fresh exhibitor update preview first.'
            );
        }

        $plan = Cache::get(
            $this->wpExhibitorUpdateCacheKey($token)
        );

        if (
            !is_array($plan)
            || !isset($plan['context'], $plan['rows'])
            || !is_array($plan['context'])
            || !is_array($plan['rows'])
        ) {
            throw new RuntimeException(
                'The update preview expired. Load it again.'
            );
        }

        return $plan;
    }

    protected function resetWordPressExhibitorUpdates()
    {
        $token = (string) $this->wpExhibitorUpdateToken;

        if (preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            Cache::forget(
                $this->wpExhibitorUpdateCacheKey($token)
            );
        }

        $this->wpExhibitorUpdateRows = [];
        $this->wpExhibitorUpdateSelections = [];
        $this->wpExhibitorUpdateToken = '';
        $this->wpExhibitorUpdateConfirmed = false;
        $this->wpExhibitorUpdateLoaded = false;
        $this->wpExhibitorUpdateNotices = [];
    }

    public function updatedWpExhibitorUpdateSelections()
    {
        $this->wpExhibitorUpdateConfirmed = false;
        $this->wpExhibitorUpdateSuccess = '';
        $this->wpExhibitorUpdateError = '';
    }

    public function loadWordPressExhibitorUpdates()
    {
        $this->assertDataSyncAdmin();

        $this->resetWordPressExhibitorUpdates();

        $this->wpExhibitorUpdateError = '';
        $this->wpExhibitorUpdateSuccess = '';

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

            $event = $this->wpExhibitorUpdateEvent();
            $context = $this->wpExhibitorSourceContext($event);

            $sourceRows = app(
                WordPressExhibitorPreview::class
            )->fetch($event);

            $links = DB::table('wordpress_exhibitor_links')
                ->where('event_id', $event->id)
                ->get()
                ->keyBy('identity_hash');

            $exhibitors = Exhibitor::where('event_id', $event->id)
                ->get()
                ->keyBy('id');

            $planRows = [];
            $seen = [];
            $labels = $this->wpExhibitorUpdateLabels();

            foreach ($sourceRows as $row) {
                $wpId = (int) $row['wp_id'];

                if ($wpId < 1 || isset($seen[$wpId])) {
                    throw new RuntimeException(
                        'Invalid or duplicate WordPress exhibitor ID.'
                    );
                }

                $seen[$wpId] = true;

                $identity = $this->wpExhibitorIdentity($context, $wpId);
                $link = $links->get($identity);

                if (!$link) {
                    continue;
                }

                $exhibitorId = (int) $link->exhibitor_id;

                if (!$this->wpExhibitorUpdateLinkMatches(
                    $link,
                    $context,
                    $wpId,
                    $exhibitorId
                )) {
                    throw new RuntimeException(
                        'WP #' . $wpId
                        . ' has an invalid existing exhibitor link.'
                    );
                }

                $exhibitor = $exhibitors->get($exhibitorId);

                if (!$exhibitor) {
                    $this->wpExhibitorUpdateNotices[] =
                        'WP #' . $wpId
                        . ': linked app exhibitor is missing from this event.';

                    continue;
                }

                $issues = $this->wpExhibitorUpdateSourceIssues($row);

                if ($issues) {
                    $this->wpExhibitorUpdateNotices[] =
                        'WP #' . $wpId . ' — ' . $row['name']
                        . ': updates blocked. ' . implode(' ', $issues);

                    continue;
                }

                $warnings = array_values($row['warnings'] ?? []);

                if (
                    ($row['logo_status'] ?? '') === 'available'
                    && !$this->wpExhibitorUpdateLogoUrlAllowed(
                        trim((string) ($row['logo_url'] ?? '')),
                        $context['source_url']
                    )
                ) {
                    $warnings[] =
                        'Source logo URL is not allowed. '
                        . 'The existing app logo will be preserved.';
                }

                $values = $this->wpExhibitorUpdateSourceValues(
                    $row,
                    $context['source_url']
                );

                $changes = [];
                $fields = [];

                foreach ($values as $field => $proposed) {
                    $current = $this->wpExhibitorUpdateAppSnapshot(
                        $exhibitor,
                        $field
                    );

                    if ($current['value'] === $proposed) {
                        continue;
                    }

                    $changes[] = [
                        'field' => $field,
                        'label' => $labels[$field],
                        'current' => $current['value'],
                        'proposed' => $proposed,
                    ];

                    $fields[$field] = [
                        'app_snapshot' => $current,
                        'proposed' => $proposed,
                    ];

                    $this->wpExhibitorUpdateSelections[
                        'wp_' . $wpId
                    ][$field] = false;
                }

                if (!$changes) {
                    if ($warnings) {
                        $this->wpExhibitorUpdateNotices[] =
                            'WP #' . $wpId . ' — ' . $row['name']
                            . ': ' . implode(' ', $warnings);
                    }

                    continue;
                }

                $this->wpExhibitorUpdateRows[] = [
                    'wp_id' => $wpId,
                    'exhibitor_id' => $exhibitorId,
                    'name' => (string) $row['name'],
                    'changes' => $changes,
                    'warnings' => array_values(array_unique($warnings)),
                ];

                $planRows[$wpId] = [
                    'identity_hash' => $identity,
                    'exhibitor_id' => $exhibitorId,
                    'fields' => $fields,
                ];
            }

            if ($planRows) {
                $token = Str::random(48);

                $stored = Cache::put(
                    $this->wpExhibitorUpdateCacheKey($token),
                    [
                        'context' => $context,
                        'rows' => $planRows,
                    ],
                    now()->addMinutes(15)
                );

                if ($stored === false) {
                    throw new RuntimeException(
                        'Could not store the exhibitor update preview.'
                    );
                }

                $this->wpExhibitorUpdateToken = $token;
            }

            $this->wpExhibitorUpdateLoaded = true;
        } catch (Throwable $exception) {
            report($exception);

            $this->resetWordPressExhibitorUpdates();

            $this->wpExhibitorUpdateError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }

    protected function wpExhibitorUpdateWriteLogo(
        Exhibitor $exhibitor,
        string $url,
        string $sourceUrl
    ): int {
        if (!$this->wpExhibitorUpdateLogoUrlAllowed($url, $sourceUrl)) {
            throw new RuntimeException(
                'The proposed exhibitor logo URL is not allowed.'
            );
        }

        $entityType = MediaEntityTypes::EXHIBITOR_LOGO->value;

        $usageId = $exhibitor->logo_media_id
            ? getMediaUsageId(
                $exhibitor->logo_media_id,
                $entityType,
                $exhibitor->id
            )
            : null;

        $path = parse_url($url, PHP_URL_PATH);
        $fileName = basename($path ?: '') ?: 'exhibitor-logo';
        $extension = strtolower(
            pathinfo($fileName, PATHINFO_EXTENSION)
        );

        // Create a new Media record; do not rewrite shared media.
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
            $exhibitor->id,
            $usageId
        );

        return (int) $media->id;
    }

    public function applyWordPressExhibitorUpdates()
    {
        $this->assertDataSyncAdmin();

        $this->wpExhibitorUpdateError = '';
        $this->wpExhibitorUpdateSuccess = '';

        try {
            set_time_limit(120);

            if ($this->wpExhibitorUpdateConfirmed !== true) {
                throw new RuntimeException(
                    'Review the selected fields and tick confirmation.'
                );
            }

            $plan = $this->wpExhibitorUpdateGetPlan();

            $cacheKey = $this->wpExhibitorUpdateCacheKey(
                (string) $this->wpExhibitorUpdateToken
            );

            if (!is_array($this->wpExhibitorUpdateSelections)) {
                throw new RuntimeException('Invalid update selections.');
            }

            $selected = [];
            $labels = $this->wpExhibitorUpdateLabels();

            foreach (
                $this->wpExhibitorUpdateSelections
                as $selectionKey => $fields
            ) {
                if (
                    !is_string($selectionKey)
                    || !preg_match(
                        '/^wp_([1-9][0-9]*)$/',
                        $selectionKey,
                        $matches
                    )
                    || !is_array($fields)
                ) {
                    throw new RuntimeException(
                        'Invalid exhibitor update selection format.'
                    );
                }

                $wpId = (int) $matches[1];

                foreach ($fields as $field => $checked) {
                    if ($checked === false) {
                        continue;
                    }

                    if (
                        $checked !== true
                        || !array_key_exists($field, $labels)
                        || !isset($plan['rows'][$wpId]['fields'][$field])
                    ) {
                        throw new RuntimeException(
                            'A selected field is not in the approved preview.'
                        );
                    }

                    $selected[$wpId][$field] =
                        $plan['rows'][$wpId]['fields'][$field];
                }
            }

            if (!$selected) {
                throw new RuntimeException(
                    'Select at least one changed field.'
                );
            }

            ksort($selected);

            $event = $this->wpExhibitorUpdateEvent();

            if (
                $this->wpExhibitorSourceContext($event)
                !== $plan['context']
            ) {
                throw new RuntimeException(
                    'Source settings changed. Reload the update preview.'
                );
            }

            // Complete network requests before taking database locks.
            $freshById = [];

            foreach (
                app(WordPressExhibitorPreview::class)->fetch($event)
                as $row
            ) {
                $wpId = (int) $row['wp_id'];

                if ($wpId < 1 || isset($freshById[$wpId])) {
                    throw new RuntimeException(
                        'Invalid or duplicate WordPress exhibitor ID.'
                    );
                }

                $freshById[$wpId] = $row;
            }

            foreach ($selected as $wpId => $fields) {
                $fresh = $freshById[$wpId] ?? null;

                if (
                    !$fresh
                    || $this->wpExhibitorUpdateSourceIssues($fresh)
                ) {
                    throw new RuntimeException(
                        'WP #' . $wpId
                        . ' disappeared or has validation issues. '
                        . 'Reload the update preview.'
                    );
                }

                $freshValues = $this->wpExhibitorUpdateSourceValues(
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
                $event = $this->wpExhibitorUpdateEvent(true);

                if (
                    $this->wpExhibitorSourceContext($event) !== $context
                    || Cache::get($cacheKey) !== $plan
                ) {
                    throw new RuntimeException(
                        'The preview expired or source settings changed.'
                    );
                }

                $exhibitorCount = 0;
                $fieldCount = 0;
                $logoCount = 0;
                $usedExhibitors = [];

                foreach ($selected as $wpId => $fields) {
                    $plannedRow = $plan['rows'][$wpId];

                    $identity = $this->wpExhibitorIdentity(
                        $context,
                        $wpId
                    );

                    if ($identity !== $plannedRow['identity_hash']) {
                        throw new RuntimeException(
                            'Source identity changed. Reload the preview.'
                        );
                    }

                    $link = DB::table('wordpress_exhibitor_links')
                        ->where('event_id', $event->id)
                        ->where('identity_hash', $identity)
                        ->lockForUpdate()
                        ->first();

                    $exhibitorId = (int) $plannedRow['exhibitor_id'];

                    if (!$this->wpExhibitorUpdateLinkMatches(
                        $link,
                        $context,
                        $wpId,
                        $exhibitorId
                    )) {
                        throw new RuntimeException(
                            'An exhibitor link changed. Reload the preview.'
                        );
                    }

                    if (isset($usedExhibitors[$exhibitorId])) {
                        throw new RuntimeException(
                            'Multiple source records refer to one app exhibitor.'
                        );
                    }

                    $usedExhibitors[$exhibitorId] = true;

                    $exhibitor = Exhibitor::where('event_id', $event->id)
                        ->whereKey($exhibitorId)
                        ->lockForUpdate()
                        ->first();

                    if (!$exhibitor) {
                        throw new RuntimeException(
                            'A linked app exhibitor no longer exists.'
                        );
                    }

                    foreach ($fields as $field => $entry) {
                        $current = $this->wpExhibitorUpdateAppSnapshot(
                            $exhibitor,
                            $field,
                            true
                        );

                        if ($current !== $entry['app_snapshot']) {
                            throw new RuntimeException(
                                'A selected app field changed for exhibitor #'
                                . $exhibitorId
                                . '. Reload the update preview.'
                            );
                        }
                    }

                    foreach ($fields as $field => $entry) {
                        $value = $entry['proposed'];

                        if ($field === 'logo_url') {
                            $exhibitor->logo_media_id =
                                $this->wpExhibitorUpdateWriteLogo(
                                    $exhibitor,
                                    $value,
                                    $context['source_url']
                                );

                            $logoCount++;
                        } else {
                            $exhibitor->setAttribute(
                                $field,
                                $field !== 'name' && $value === ''
                                    ? null
                                    : $value
                            );
                        }

                        $fieldCount++;
                    }

                    $exhibitor->save();
                    $exhibitorCount++;
                }

                return [
                    'exhibitors' => $exhibitorCount,
                    'fields' => $fieldCount,
                    'logos' => $logoCount,
                ];
            });

    $this->resetWordPressExhibitorUpdates();
$this->resetWordPressExhibitorLinking();
$this->resetWordPressExhibitorCreation();

            $this->wpExhibitorLinkError = '';
            $this->wpExhibitorLinkSuccess = '';

            $this->wpExhibitorUpdateSuccess =
                'Updated ' . $result['fields'] . ' selected field(s) '
                . 'across ' . $result['exhibitors']
                . ' exhibitor(s), including '
                . $result['logos'] . ' logo(s). '
                . 'Unchecked fields, active status, banners, contact details '
                . 'and ordering were not changed. Reload the preview to verify.';
        } catch (Throwable $exception) {
            report($exception);

            $this->wpExhibitorUpdateConfirmed = false;

            $this->wpExhibitorUpdateError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }

    public function selectWordPressExhibitorUpdateFields(
        $wpId,
        $checked = true
    ) {
        $this->assertDataSyncAdmin();

        $this->wpExhibitorUpdateConfirmed = false;
        $this->wpExhibitorUpdateSuccess = '';
        $this->wpExhibitorUpdateError = '';

        try {
            if (
                (!is_int($wpId) && !is_string($wpId))
                || !ctype_digit((string) $wpId)
                || (int) $wpId < 1
                || !is_bool($checked)
            ) {
                throw new RuntimeException(
                    'Invalid exhibitor selection.'
                );
            }

            $wpId = (int) $wpId;
            $plan = $this->wpExhibitorUpdateGetPlan();
            $event = $this->wpExhibitorUpdateEvent();

            if (
                $this->wpExhibitorSourceContext($event)
                !== $plan['context']
            ) {
                throw new RuntimeException(
                    'Source settings changed. Reload the preview.'
                );
            }

            if (
                !isset($plan['rows'][$wpId]['fields'])
                || !is_array($plan['rows'][$wpId]['fields'])
            ) {
                throw new RuntimeException(
                    'This exhibitor is not in the approved update preview.'
                );
            }

            $selections = [];

            foreach ($plan['rows'][$wpId]['fields'] as $field => $entry) {
                $selections[$field] = $checked;
            }

            $this->wpExhibitorUpdateSelections['wp_' . $wpId] =
                $selections;
        } catch (Throwable $exception) {
            report($exception);

            $this->wpExhibitorUpdateError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }

    public function selectAllWordPressExhibitorUpdates($checked = true)
    {
        $this->assertDataSyncAdmin();

        $this->wpExhibitorUpdateConfirmed = false;
        $this->wpExhibitorUpdateSuccess = '';
        $this->wpExhibitorUpdateError = '';

        try {
            if (!is_bool($checked)) {
                throw new RuntimeException('Invalid selection value.');
            }

            $plan = $this->wpExhibitorUpdateGetPlan();
            $event = $this->wpExhibitorUpdateEvent();

            if (
                $this->wpExhibitorSourceContext($event)
                !== $plan['context']
            ) {
                throw new RuntimeException(
                    'Source settings changed. Reload the preview.'
                );
            }

            $selections = [];

            foreach ($plan['rows'] as $wpId => $row) {
                foreach ($row['fields'] as $field => $entry) {
                    $selections['wp_' . $wpId][$field] = $checked;
                }
            }

            $this->wpExhibitorUpdateSelections = $selections;
        } catch (Throwable $exception) {
            report($exception);

            $this->wpExhibitorUpdateError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }
}