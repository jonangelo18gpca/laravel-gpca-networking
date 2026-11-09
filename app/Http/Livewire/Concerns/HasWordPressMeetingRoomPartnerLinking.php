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

trait HasWordPressMeetingRoomPartnerLinking
{
    public $wpMrpLinkRows = [];
    public $wpMrpLinkOptions = [];
    public $wpMrpLinkSelections = [];
    public $wpMrpLinkToken = '';
    public $wpMrpLinkConfirmed = false;
    public $wpMrpLinkError = '';
    public $wpMrpLinkSuccess = '';
    public $wpMrpAlreadyLinkedCount = 0;

    protected function wpMrpLinkCacheKey(string $token): string
    {
        return 'wp-mrp-link:'
            . hash('sha256', json_encode([
                session()->getId(),
                (string) auth()->id(),
                (int) $this->eventData['eventId'],
            ], JSON_THROW_ON_ERROR))
            . ':' . $token;
    }

    protected function wpMrpSourceContext(Event $event): array
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
            'post_type' => 'meeting-room-partner',
        ];
    }

    protected function wpMrpIdentity(
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

    protected function resetWordPressMeetingRoomPartnerLinking()
    {
        $token = (string) $this->wpMrpLinkToken;

        if (preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            Cache::forget($this->wpMrpLinkCacheKey($token));
        }

        $this->wpMrpLinkRows = [];
        $this->wpMrpLinkOptions = [];
        $this->wpMrpLinkSelections = [];
        $this->wpMrpLinkToken = '';
        $this->wpMrpLinkConfirmed = false;
        $this->wpMrpAlreadyLinkedCount = 0;
    }

    public function updatedWpMrpLinkSelections()
    {
        $this->wpMrpLinkConfirmed = false;
        $this->wpMrpLinkSuccess = '';
    }

    public function loadWordPressMeetingRoomPartnerLinking()
    {
        $this->assertDataSyncAdmin();

        $this->resetWordPressMeetingRoomPartnerLinking();
        $this->wpMrpLinkError = '';
        $this->wpMrpLinkSuccess = '';

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

            $linkedPartnerIds = DB::table(
                'wordpress_meeting_room_partner_links'
            )->pluck('meeting_room_partner_id')->all();

            // Include inactive records when finding existing partners.
            $partners = MeetingRoomPartner::where(
                'event_id',
                $event->id
            )
                ->whereNotIn('id', $linkedPartnerIds)
                ->orderBy('name')
                ->orderBy('id')
                ->get();

            $appPlan = [];

            foreach ($partners as $partner) {
                $this->wpMrpLinkOptions[] = [
                    'id' => (int) $partner->id,
                    'label' => '#' . $partner->id
                        . ' — ' . $partner->name
                        . ' — ' . ($partner->location ?: 'No location'),
                ];

                $appPlan[(int) $partner->id] = [
                    'name' => (string) $partner->name,
                    'location' => (string) $partner->location,
                ];
            }

            $sourcePlan = [];

            foreach ($sourceRows as $row) {
                $wpId = (int) $row['wp_id'];
                $identity = $this->wpMrpIdentity($context, $wpId);

                if (isset($links[$identity])) {
                    $linkedPartner = MeetingRoomPartner::where(
                        'event_id',
                        $event->id
                    )
                        ->whereKey(
                            $links[$identity]->meeting_room_partner_id
                        )
                        ->first();

                    if (!$linkedPartner) {
                        throw new RuntimeException(
                            'WP #' . $wpId
                                . ' has an invalid existing partner link. '
                                . 'Review it before continuing.'
                        );
                    }

                    $this->wpMrpAlreadyLinkedCount++;
                    continue;
                }

                $this->wpMrpLinkRows[] = [
                    'wp_id' => $wpId,
                    'name' => $row['name'],
                    'location' => $row['location'],
                ];

                $this->wpMrpLinkSelections[$wpId] = '';

                $sourcePlan[$wpId] = [
                    'identity_hash' => $identity,
                    'name' => $row['name'],
                    'location' => $row['location'],
                ];
            }

            if (!$sourcePlan) {
                $this->wpMrpLinkSuccess =
                    'No unlinked WordPress partners were found.';
                return;
            }

            $token = Str::random(48);

            $stored = Cache::put(
                $this->wpMrpLinkCacheKey($token),
                [
                    'context' => $context,
                    'sources' => $sourcePlan,
                    'partners' => $appPlan,
                ],
                now()->addMinutes(15)
            );

            if ($stored === false) {
                throw new RuntimeException(
                    'Could not store the matching preview.'
                );
            }

            $this->wpMrpLinkToken = $token;
        } catch (Throwable $exception) {
            report($exception);

            $this->resetWordPressMeetingRoomPartnerLinking();

            $this->wpMrpLinkError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }

    public function saveWordPressMeetingRoomPartnerLinks()
    {
        $this->assertDataSyncAdmin();

        $this->wpMrpLinkError = '';
        $this->wpMrpLinkSuccess = '';

        try {
            if ($this->wpMrpLinkConfirmed !== true) {
                throw new RuntimeException(
                    'Review the selected matches and tick confirmation.'
                );
            }

            $token = (string) $this->wpMrpLinkToken;

            if (!preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
                throw new RuntimeException(
                    'Load a fresh matching preview first.'
                );
            }

            $cacheKey = $this->wpMrpLinkCacheKey($token);
            $plan = Cache::get($cacheKey);

            if (
                !is_array($plan)
                || !isset(
                    $plan['context'],
                    $plan['sources'],
                    $plan['partners']
                )
            ) {
                throw new RuntimeException(
                    'The matching preview expired. Load it again.'
                );
            }

            $event = Event::findOrFail(
                $this->eventData['eventId']
            );

            $context = $this->wpMrpSourceContext($event);

            if ($context !== $plan['context']) {
                throw new RuntimeException(
                    'The source website changed. Reload the preview.'
                );
            }

            if (!is_array($this->wpMrpLinkSelections)) {
                throw new RuntimeException('Invalid matching selections.');
            }

            $selected = [];
            $used = [];

            foreach ($this->wpMrpLinkSelections as $wpId => $rawId) {
                if ($rawId === '' || $rawId === null) {
                    continue;
                }

                if (
                    !ctype_digit((string) $wpId)
                    || !is_scalar($rawId)
                    || !ctype_digit((string) $rawId)
                    || (int) $rawId < 1
                    || !isset($plan['sources'][$wpId])
                    || !isset($plan['partners'][(int) $rawId])
                ) {
                    throw new RuntimeException(
                        'A selection is not in the approved matching preview.'
                    );
                }

                $partnerId = (int) $rawId;

                if (isset($used[$partnerId])) {
                    throw new RuntimeException(
                        'App partner #' . $partnerId
                            . ' was selected more than once.'
                    );
                }

                $used[$partnerId] = true;
                $selected[(int) $wpId] = $partnerId;
            }

            if (!$selected) {
                throw new RuntimeException(
                    'Choose at least one existing app partner.'
                );
            }

            // Recheck WordPress before taking database locks.
            $freshRows = app(
                WordPressMeetingRoomPartnerPreview::class
            )->fetch($event);

            $freshById = [];

            foreach ($freshRows as $row) {
                $freshById[(int) $row['wp_id']] = $row;
            }

            foreach ($selected as $wpId => $partnerId) {
                $fresh = $freshById[$wpId] ?? null;
                $planned = $plan['sources'][$wpId];

                if (
                    !$fresh
                    || $fresh['name'] !== $planned['name']
                    || $fresh['location'] !== $planned['location']
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

                $created = 0;

                foreach ($selected as $wpId => $partnerId) {
                    $partner = MeetingRoomPartner::where(
                        'event_id',
                        $event->id
                    )
                        ->whereKey($partnerId)
                        ->lockForUpdate()
                        ->first();

                    $plannedApp = $plan['partners'][$partnerId];

                    if (
                        !$partner
                        || (string) $partner->name !== $plannedApp['name']
                        || (string) $partner->location
                            !== $plannedApp['location']
                    ) {
                        throw new RuntimeException(
                            'An app partner changed. Reload matching.'
                        );
                    }

                    $identity = $plan['sources'][$wpId]['identity_hash'];

                    $existing = DB::table(
                        'wordpress_meeting_room_partner_links'
                    )
                        ->where('identity_hash', $identity)
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        if (
                            (int) $existing->event_id !== (int) $event->id
                            || (int) $existing->meeting_room_partner_id
                                !== $partnerId
                        ) {
                            throw new RuntimeException(
                                'A source was linked to another app partner.'
                            );
                        }

                        continue;
                    }

                    if (DB::table(
                        'wordpress_meeting_room_partner_links'
                    )
                        ->where('meeting_room_partner_id', $partnerId)
                        ->exists()
                    ) {
                        throw new RuntimeException(
                            'An app partner was already linked. Reload matching.'
                        );
                    }

                    DB::table(
                        'wordpress_meeting_room_partner_links'
                    )->insert([
                        'event_id' => (int) $event->id,
                        'meeting_room_partner_id' => $partnerId,
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

            $this->resetWordPressMeetingRoomPartnerLinking();

            $this->wpMrpLinkSuccess =
                'Saved ' . $created . ' new partner link(s). '
                . 'No partner profiles, logos or ordering were changed.';
        } catch (Throwable $exception) {
            report($exception);

            $this->wpMrpLinkConfirmed = false;

            $this->wpMrpLinkError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }
}