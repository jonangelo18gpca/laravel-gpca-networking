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
 * @mixin \App\Http\Livewire\EventDataSync
 */
trait HasWordPressExhibitorLinking
{
    public $wpExhibitorLinkRows = [];
    public $wpExhibitorLinkOptions = [];
    public $wpExhibitorLinkSelections = [];

    public $wpExhibitorLinkToken = '';
    public $wpExhibitorLinkConfirmed = false;

    public $wpExhibitorLinkError = '';
    public $wpExhibitorLinkSuccess = '';

    public $wpExhibitorAlreadyLinkedCount = 0;

    protected function wpExhibitorLinkCacheKey(string $token): string
    {
        $owner = hash('sha256', json_encode([
            session()->getId(),
            (string) auth()->id(),
            (int) $this->eventData['eventId'],
            (string) $this->eventData['eventCategory'],
        ], JSON_THROW_ON_ERROR));

        return 'wp-exhibitor-link:' . $owner . ':' . $token;
    }

    protected function wpExhibitorSourceContext(Event $event): array
    {
        $url = rtrim(
            trim((string) $event->wordpress_source_url),
            '/'
        );

        if ($url === '') {
            throw new RuntimeException(
                'Save the website in WordPress General Settings first.'
            );
        }

        return [
            'event_id' => (int) $event->id,
            'source_url' => $url,
            'post_type' => 'exhibitor',
        ];
    }

    protected function wpExhibitorIdentity(
        array $context,
        int $wpId
    ): string {
        return hash('sha256', json_encode([
            $context['event_id'],
            $context['source_url'],
            $context['post_type'],
            $wpId,
        ], JSON_THROW_ON_ERROR));
    }

    protected function resetWordPressExhibitorLinking()
    {
        $token = (string) $this->wpExhibitorLinkToken;

        if (preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            Cache::forget(
                $this->wpExhibitorLinkCacheKey($token)
            );
        }

        $this->wpExhibitorLinkRows = [];
        $this->wpExhibitorLinkOptions = [];
        $this->wpExhibitorLinkSelections = [];

        $this->wpExhibitorLinkToken = '';
        $this->wpExhibitorLinkConfirmed = false;

        $this->wpExhibitorAlreadyLinkedCount = 0;
    }

    public function updatedWpExhibitorLinkSelections()
    {
        $this->wpExhibitorLinkConfirmed = false;
        $this->wpExhibitorLinkSuccess = '';
        $this->wpExhibitorLinkError = '';
    }

    public function loadWordPressExhibitorLinking()
    {
        $this->assertDataSyncAdmin();

        $this->resetWordPressExhibitorLinking();

        $this->wpExhibitorLinkError = '';
        $this->wpExhibitorLinkSuccess = '';

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

            $event = Event::whereKey(
                $this->eventData['eventId']
            )
                ->where(
                    'category',
                    $this->eventData['eventCategory']
                )
                ->firstOrFail();

            $context = $this->wpExhibitorSourceContext($event);

            // Matching needs IDs, names and stands, not logo metadata.
            $sourceRows = app(
                WordPressExhibitorPreview::class
            )->fetch($event, false);

            $links = DB::table('wordpress_exhibitor_links')
                ->where('event_id', $event->id)
                ->get()
                ->keyBy('identity_hash');

            // Include inactive records when matching existing exhibitors.
            $allExhibitors = Exhibitor::where(
                'event_id',
                $event->id
            )
                ->orderBy('name')
                ->orderBy('id')
                ->get();

            $exhibitorsById = $allExhibitors->keyBy('id');

            // An app exhibitor may have only one source link.
            $linkedExhibitorIds = DB::table(
                'wordpress_exhibitor_links'
            )
                ->pluck('exhibitor_id')
                ->map(function ($id) {
                    return (int) $id;
                })
                ->all();

            $linkedExhibitorSet = array_fill_keys(
                $linkedExhibitorIds,
                true
            );

            $appPlan = [];

            foreach ($allExhibitors as $exhibitor) {
                $exhibitorId = (int) $exhibitor->id;

                if (isset($linkedExhibitorSet[$exhibitorId])) {
                    continue;
                }

                $this->wpExhibitorLinkOptions[] = [
                    'id' => $exhibitorId,
                    'label' => '#' . $exhibitorId
                        . ' — ' . $exhibitor->name
                        . ' — Stand: '
                        . ($exhibitor->stand_number ?: 'Empty'),
                ];

                $appPlan[$exhibitorId] = [
                    'name' => (string) $exhibitor->name,
                    'stand_number' => (string) $exhibitor->stand_number,
                ];
            }

            $sourcePlan = [];
            $seen = [];

            foreach ($sourceRows as $row) {
                $wpId = (int) $row['wp_id'];

                if ($wpId < 1 || isset($seen[$wpId])) {
                    throw new RuntimeException(
                        'Invalid or duplicate WordPress exhibitor ID.'
                    );
                }

                $seen[$wpId] = true;

                $identity = $this->wpExhibitorIdentity(
                    $context,
                    $wpId
                );

                $existingLink = $links->get($identity);

                if ($existingLink) {
                    $linkedExhibitor = $exhibitorsById->get(
                        (int) $existingLink->exhibitor_id
                    );

                    if (
                        !$linkedExhibitor
                        || (string) $existingLink->source_url
                            !== $context['source_url']
                        || (string) $existingLink->post_type
                            !== $context['post_type']
                        || (int) $existingLink->wordpress_post_id !== $wpId
                    ) {
                        throw new RuntimeException(
                            'WP #' . $wpId
                                . ' has an invalid existing exhibitor link. '
                                . 'Review it before continuing.'
                        );
                    }

                    $this->wpExhibitorAlreadyLinkedCount++;
                    continue;
                }

                $this->wpExhibitorLinkRows[] = [
                    'wp_id' => $wpId,
                    'name' => (string) $row['name'],
                    'stand_number' => (string) $row['stand_number'],
                ];

                // Prefixed keys avoid numeric-only selection keys.
                $selectionKey = 'wp_' . $wpId;

                $this->wpExhibitorLinkSelections[$selectionKey] = '';

                $sourcePlan[$wpId] = [
                    'identity_hash' => $identity,
                    'name' => (string) $row['name'],
                    'stand_number' => (string) $row['stand_number'],
                ];
            }

            if (!$sourcePlan) {
                $this->wpExhibitorLinkSuccess =
                    'No unlinked WordPress exhibitors were found. '
                    . $this->wpExhibitorAlreadyLinkedCount
                    . ' source record(s) are already linked.';

                return;
            }

            $token = Str::random(48);

            $stored = Cache::put(
                $this->wpExhibitorLinkCacheKey($token),
                [
                    'context' => $context,
                    'sources' => $sourcePlan,
                    'exhibitors' => $appPlan,
                ],
                now()->addMinutes(15)
            );

            if ($stored === false) {
                throw new RuntimeException(
                    'Could not store the exhibitor matching preview.'
                );
            }

            $this->wpExhibitorLinkToken = $token;
        } catch (Throwable $exception) {
            report($exception);

            $this->resetWordPressExhibitorLinking();

            $this->wpExhibitorLinkError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }

    public function saveWordPressExhibitorLinks()
    {
        $this->assertDataSyncAdmin();

        $this->wpExhibitorLinkError = '';
        $this->wpExhibitorLinkSuccess = '';

        try {
            if ($this->wpExhibitorLinkConfirmed !== true) {
                throw new RuntimeException(
                    'Review the selected matches and tick confirmation.'
                );
            }

            $token = (string) $this->wpExhibitorLinkToken;

            if (!preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
                throw new RuntimeException(
                    'Load a fresh exhibitor matching preview first.'
                );
            }

            $cacheKey = $this->wpExhibitorLinkCacheKey($token);
            $plan = Cache::get($cacheKey);

            if (
                !is_array($plan)
                || !isset(
                    $plan['context'],
                    $plan['sources'],
                    $plan['exhibitors']
                )
                || !is_array($plan['context'])
                || !is_array($plan['sources'])
                || !is_array($plan['exhibitors'])
            ) {
                throw new RuntimeException(
                    'The matching preview expired. Load it again.'
                );
            }

            $event = Event::whereKey(
                $this->eventData['eventId']
            )
                ->where(
                    'category',
                    $this->eventData['eventCategory']
                )
                ->firstOrFail();

            $context = $this->wpExhibitorSourceContext($event);

            if ($context !== $plan['context']) {
                throw new RuntimeException(
                    'The source website changed. Reload matching.'
                );
            }

            if (!is_array($this->wpExhibitorLinkSelections)) {
                throw new RuntimeException(
                    'Invalid exhibitor matching selections.'
                );
            }

            $selected = [];
            $usedExhibitors = [];

            foreach (
                $this->wpExhibitorLinkSelections
                as $selectionKey => $rawId
            ) {
                if ($rawId === '' || $rawId === null) {
                    continue;
                }

                if (
                    !is_string($selectionKey)
                    || !preg_match(
                        '/^wp_([1-9][0-9]*)$/',
                        $selectionKey,
                        $matches
                    )
                    || (!is_int($rawId) && !is_string($rawId))
                    || !ctype_digit((string) $rawId)
                    || (int) $rawId < 1
                ) {
                    throw new RuntimeException(
                        'Invalid exhibitor matching selection.'
                    );
                }

                $wpId = (int) $matches[1];
                $exhibitorId = (int) $rawId;

                if (
                    !isset($plan['sources'][$wpId])
                    || !isset($plan['exhibitors'][$exhibitorId])
                ) {
                    throw new RuntimeException(
                        'A selection is not in the approved matching preview.'
                    );
                }

                if (isset($usedExhibitors[$exhibitorId])) {
                    throw new RuntimeException(
                        'App exhibitor #' . $exhibitorId
                            . ' was selected more than once.'
                    );
                }

                $usedExhibitors[$exhibitorId] = true;
                $selected[$wpId] = $exhibitorId;
            }

            if (!$selected) {
                throw new RuntimeException(
                    'Choose at least one existing app exhibitor.'
                );
            }

            // Recheck WordPress before taking database locks.
            $freshRows = app(
                WordPressExhibitorPreview::class
            )->fetch($event, false);

            $freshById = [];

            foreach ($freshRows as $row) {
                $wpId = (int) $row['wp_id'];

                if ($wpId < 1 || isset($freshById[$wpId])) {
                    throw new RuntimeException(
                        'Invalid or duplicate WordPress exhibitor ID.'
                    );
                }

                $freshById[$wpId] = $row;
            }

            foreach ($selected as $wpId => $exhibitorId) {
                $fresh = $freshById[$wpId] ?? null;
                $planned = $plan['sources'][$wpId];

                if (
                    !$fresh
                    || (string) $fresh['name'] !== $planned['name']
                    || (string) $fresh['stand_number']
                        !== $planned['stand_number']
                ) {
                    throw new RuntimeException(
                        'WP #' . $wpId
                            . ' changed or disappeared. Reload matching.'
                    );
                }
            }

            ksort($selected);

            $created = DB::transaction(function () use (
                $context,
                $plan,
                $cacheKey,
                $selected
            ) {
                $event = Event::whereKey($context['event_id'])
                    ->where(
                        'category',
                        $this->eventData['eventCategory']
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $this->wpExhibitorSourceContext($event) !== $context
                    || Cache::get($cacheKey) !== $plan
                ) {
                    throw new RuntimeException(
                        'The preview expired or source settings changed.'
                    );
                }

                $created = 0;

                foreach ($selected as $wpId => $exhibitorId) {
                    $exhibitor = Exhibitor::where(
                        'event_id',
                        $event->id
                    )
                        ->whereKey($exhibitorId)
                        ->lockForUpdate()
                        ->first();

                    $plannedApp = $plan['exhibitors'][$exhibitorId];

                    if (
                        !$exhibitor
                        || (string) $exhibitor->name
                            !== $plannedApp['name']
                        || (string) $exhibitor->stand_number
                            !== $plannedApp['stand_number']
                    ) {
                        throw new RuntimeException(
                            'App exhibitor #' . $exhibitorId
                                . ' changed or disappeared. Reload matching.'
                        );
                    }

                    $identity = $this->wpExhibitorIdentity(
                        $context,
                        $wpId
                    );

                    if (
                        $identity
                        !== $plan['sources'][$wpId]['identity_hash']
                    ) {
                        throw new RuntimeException(
                            'The planned source identity is invalid.'
                        );
                    }

                    $existingLink = DB::table(
                        'wordpress_exhibitor_links'
                    )
                        ->where('identity_hash', $identity)
                        ->lockForUpdate()
                        ->first();

                    if ($existingLink) {
                        if (
                            (int) $existingLink->event_id
                                !== (int) $event->id
                            || (int) $existingLink->exhibitor_id
                                !== $exhibitorId
                            || (string) $existingLink->source_url
                                !== $context['source_url']
                            || (string) $existingLink->post_type
                                !== $context['post_type']
                            || (int) $existingLink->wordpress_post_id
                                !== $wpId
                        ) {
                            throw new RuntimeException(
                                'WP #' . $wpId
                                    . ' already has a different link. '
                                    . 'Reload matching.'
                            );
                        }

                        // The exact same match already exists.
                        continue;
                    }

                    $appAlreadyLinked = DB::table(
                        'wordpress_exhibitor_links'
                    )
                        ->where('exhibitor_id', $exhibitorId)
                        ->exists();

                    if ($appAlreadyLinked) {
                        throw new RuntimeException(
                            'App exhibitor #' . $exhibitorId
                                . ' is already linked. Reload matching.'
                        );
                    }

                    DB::table('wordpress_exhibitor_links')->insert([
                        'event_id' => (int) $event->id,
                        'exhibitor_id' => $exhibitorId,
                        'source_url' => $context['source_url'],
                        'post_type' => $context['post_type'],
                        'wordpress_post_id' => $wpId,
                        'identity_hash' => $identity,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $created++;
                }

                return $created;
            });

            $this->resetWordPressExhibitorLinking();

            $this->wpExhibitorLinkSuccess =
                'Saved ' . $created . ' new exhibitor link(s). '
                . 'No exhibitor profiles, logos or ordering were changed.';
        } catch (Throwable $exception) {
            report($exception);

            $this->wpExhibitorLinkConfirmed = false;

            $this->wpExhibitorLinkError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }
}