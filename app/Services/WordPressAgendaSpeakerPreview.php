<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WordPressAgendaSpeakerPreview
{
    public function fetch(Event $event, string $selectedPostType): array
    {
        $started = microtime(true);
        $config = $event->wordpress_agenda_config ?? [];

        $sources = array_values(array_filter(
            $config['sources'] ?? [],
            function ($source) use ($selectedPostType) {
                return ($source['post_type'] ?? '')
                    === $selectedPostType;
            }
        ));

        if (count($sources) !== 1) {
            throw new RuntimeException(
                'Select a valid agenda source from the saved settings.'
            );
        }

        $source = $sources[0];
        $settings = $source['speaker_relationship'] ?? [];

        $enabled = in_array(
            $settings['enabled'] ?? false,
            [true, 1, '1'],
            true
        );

        if (!$enabled) {
            throw new RuntimeException(
                'Relationship sync is not enabled in the saved settings '
                    . 'for agenda source "' . $selectedPostType . '". '
                    . 'Enable it for this exact source and save.'
            );
        }
        $relationId = filter_var(
            $settings['relation_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $speakerPostType = (string) (
            $settings['speaker_post_type'] ?? ''
        );

        if (
            $relationId === false
            || !preg_match('/^[a-z0-9_-]{1,100}$/', $selectedPostType)
            || !preg_match('/^[a-z0-9_-]{1,100}$/', $speakerPostType)
        ) {
            throw new RuntimeException(
                'Save a valid relation ID and speaker post-type slug.'
            );
        }

        /*
         * The public WordPress reader intentionally exposes only:
         * relationship session_role -> profile role -> Speaker.
         *
         * Reject unsupported overrides rather than silently ignoring them.
         */
        $relationshipKey = (string) (
            $settings['role_meta_key'] ?? ''
        );

        $profileKey = (string) (
            $settings['speaker_role_meta_key'] ?? 'role'
        );

        $defaultRole = (string) (
            $settings['default_role'] ?? 'Speaker'
        );

        if (
            !in_array($relationshipKey, ['', 'session_role'], true)
            || $profileKey !== 'role'
            || $defaultRole !== 'Speaker'
        ) {
            throw new RuntimeException(
                'This reader uses relationship key session_role, '
                    . 'speaker profile key role, and default Speaker. '
                    . 'Save those values in the source settings.'
            );
        }

        $roleLabels = $settings['role_labels'] ?? [];

        if (!is_array($roleLabels)) {
            throw new RuntimeException(
                'Invalid saved role label mappings.'
            );
        }

        [$base, $host, $pinned] = $this->resolveWebsite(
            (string) $event->wordpress_source_url
        );

        // Reuse the saved year, feature and day/date mappings.
        $agenda = app(WordPressAgendaPreview::class)->fetch(
            $event,
            $selectedPostType
        );

        $result = [
            'context' => [
                'event_id' => (int) $event->id,
                'source_url' => $base,
                'agenda_post_type' => $selectedPostType,
                'speaker_post_type' => $speakerPostType,
                'relation_id' => (int) $relationId,
                'feature_id' => (int) $source['feature_id'],
                'year' => (string) ($config['year'] ?? ''),
                'config_hash' => hash(
                    'sha256',
                    json_encode(
                        [
                            $event->wordpress_source_url,
                            $config,
                        ],
                        JSON_THROW_ON_ERROR
                    )
                ),
            ],
            'sessions' => [],
            'rows' => [],
            'skipped_year' => (int) (
                $agenda['skipped_year'] ?? 0
            ),
        ];

        $seenSessions = [];

        foreach ($agenda['rows'] as $session) {
            $sessionId = (int) (
                $session['wordpress_id'] ?? 0
            );

            if (
                $sessionId < 1
                || isset($seenSessions[$sessionId])
                || ($session['post_type'] ?? '') !== $selectedPostType
                || (int) $session['feature_id']
                !== (int) $source['feature_id']
            ) {
                throw new RuntimeException(
                    'Invalid or duplicate agenda preview record.'
                );
            }

            $seenSessions[$sessionId] = true;

            $remaining = 90 - (microtime(true) - $started);

            if ($remaining <= 0) {
                throw new RuntimeException(
                    'Relationship preview time limit reached. '
                        . 'No assignments were changed.'
                );
            }

            $response = Http::acceptJson()
                ->connectTimeout(min(5, $remaining))
                ->timeout(min(10, $remaining))
                ->withOptions([
                    'allow_redirects' => false,
                    'verify' => true,
                    'proxy' => '',
                    'curl' => [
                        CURLOPT_RESOLVE => [
                            $host . ':443:' . $pinned,
                        ],
                    ],
                ])
                ->get(
                    $base . '/wp-json/gpca-sync/v1/agenda-speakers',
                    [
                        'relation_id' => (int) $relationId,
                        'session_id' => $sessionId,
                        'agenda_post_type' => $selectedPostType,
                        'speaker_post_type' => $speakerPostType,
                    ]
                );

            if ($response->status() !== 200) {
                throw new RuntimeException(
                    'WordPress returned HTTP '
                        . $response->status()
                        . ' while reading speaker connections for agenda #'
                        . $sessionId . '.'
                );
            }

            $payload = $response->json();

            if (
                !is_array($payload)
                || ($payload['status'] ?? '') !== 'ok'
                || ($payload['relation_id'] ?? null) !== (int) $relationId
                || ($payload['wordpress_session_id'] ?? null)
                !== $sessionId
                || ($payload['agenda_post_type'] ?? '')
                !== $selectedPostType
                || ($payload['speaker_post_type'] ?? '')
                !== $speakerPostType
                || !isset($payload['speakers'])
                || !is_array($payload['speakers'])
                || !array_is_list($payload['speakers'])
            ) {
                throw new RuntimeException(
                    'Unexpected relationship response for agenda #'
                        . $sessionId . '.'
                );
            }

            $sessionIssues = array_values(
                $session['issues'] ?? []
            );

            $result['sessions'][] = [
                'wordpress_session_id' => $sessionId,
                'title' => (string) $session['title'],
                'feature_id' => (int) $session['feature_id'],
                'date' => (string) $session['date'],
                'start_time' => (string) $session['start_time'],
                'speaker_count' => count($payload['speakers']),
                'issues' => $sessionIssues,
            ];

            $seenSpeakers = [];

            foreach ($payload['speakers'] as $speaker) {
                if (!is_array($speaker)) {
                    throw new RuntimeException(
                        'Unexpected speaker connection format.'
                    );
                }

                $speakerId = filter_var(
                    $speaker['wordpress_speaker_id'] ?? null,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );

                if (
                    $speakerId === false
                    || isset($seenSpeakers[$speakerId])
                    || ($speaker['speaker_post_type'] ?? '')
                    !== $speakerPostType
                ) {
                    throw new RuntimeException(
                        'Invalid or duplicate speaker connection '
                            . 'for agenda #' . $sessionId . '.'
                    );
                }

                $speakerId = (int) $speakerId;
                $seenSpeakers[$speakerId] = true;

                $sourceRole = $speaker['session_role'] ?? null;
                $origin = $speaker['role_origin'] ?? null;

                if (
                    !is_string($sourceRole)
                    || !in_array(
                        $origin,
                        ['relationship', 'speaker_profile', 'default'],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'Unexpected role information for speaker #'
                            . $speakerId . '.'
                    );
                }

                $sourceRole = trim($sourceRole);

                $role = $origin !== 'default'
                    && array_key_exists($sourceRole, $roleLabels)
                    ? $roleLabels[$sourceRole]
                    : $sourceRole;

                if (
                    !$this->validRole($sourceRole)
                    || !$this->validRole($role)
                    || (
                        $origin === 'default'
                        && $sourceRole !== 'Speaker'
                    )
                ) {
                    throw new RuntimeException(
                        'Invalid role for speaker #' . $speakerId . '.'
                    );
                }

                if (count($result['rows']) >= 10000) {
                    throw new RuntimeException(
                        'Relationship preview limit: 10,000 assignments.'
                    );
                }

                $result['rows'][] = [
                    'wordpress_session_id' => $sessionId,
                    'agenda_post_type' => $selectedPostType,
                    'feature_id' => (int) $session['feature_id'],
                    'session_title' => (string) $session['title'],
                    'date' => (string) $session['date'],
                    'start_time' => (string) $session['start_time'],
                    'wordpress_speaker_id' => $speakerId,
                    'speaker_post_type' => $speakerPostType,
                    'source_role' => $sourceRole,
                    'session_role' => $role,
                    'role_origin' => $origin,
                    'issues' => $sessionIssues,
                ];
            }
        }

        usort($result['rows'], function ($a, $b) {
            return [
                $a['wordpress_session_id'],
                $a['wordpress_speaker_id'],
            ] <=> [
                $b['wordpress_session_id'],
                $b['wordpress_speaker_id'],
            ];
        });

        return $result;
    }

    private function validRole($role): bool
    {
        return is_string($role)
            && $role !== ''
            && mb_strlen($role, 'UTF-8') <= 255
            && $role === strip_tags($role)
            && !preg_match('/[\x00-\x1F\x7F|]/', $role);
    }

    private function resolveWebsite(string $sourceUrl): array
    {
        $base = rtrim(trim($sourceUrl), '/');
        $parts = parse_url($base);

        $host = is_array($parts)
            ? strtolower($parts['host'] ?? '')
            : '';

        if (
            !is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || $host === ''
            || !in_array(
                $host,
                config('wordpress_agenda.allowed_hosts', []),
                true
            )
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (
                isset($parts['port'])
                && (int) $parts['port'] !== 443
            )
        ) {
            throw new RuntimeException(
                'Configure an approved HTTPS WordPress website '
                    . 'in General Settings.'
            );
        }

        if (
            !extension_loaded('curl')
            || !function_exists('dns_get_record')
        ) {
            throw new RuntimeException(
                'Relationship preview requires PHP cURL '
                    . 'and dns_get_record.'
            );
        }

        $addresses = [];

        foreach (
            (array) dns_get_record($host, DNS_A | DNS_AAAA)
            as $record
        ) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if (!$ip) {
                continue;
            }

            if (!filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            )) {
                throw new RuntimeException(
                    'Source DNS resolves to a non-public address.'
                );
            }

            $addresses[] = $ip;
        }

        if (!$addresses) {
            throw new RuntimeException(
                'Cannot resolve the approved website.'
            );
        }

        $ip = $addresses[0];

        $pinned = strpos($ip, ':') !== false
            ? '[' . $ip . ']'
            : $ip;

        return [$base, $host, $pinned];
    }
}
