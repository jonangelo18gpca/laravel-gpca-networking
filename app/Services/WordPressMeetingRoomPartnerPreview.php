<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WordPressMeetingRoomPartnerPreview
{
    public function fetch(Event $event): array
    {
        [$base, $host, $pinned] = $this->resolveWebsite(
            (string) $event->wordpress_source_url
        );

        $started = microtime(true);
        $rows = [];
        $seen = [];
        $mediaUrls = [];
        $page = 1;
        $pages = 1;

        do {
            $response = $this->request(
                $base . '/wp-json/wp/v2/meeting-room-partner',
                $host,
                $pinned,
                $started,
                [
                    'status' => 'publish',
                    'per_page' => 100,
                    'page' => $page,
                    'orderby' => 'id',
                    'order' => 'asc',
                    '_fields' => 'id,type,status,title,meta',
                ]
            );

            $posts = $response->json();

            if (!is_array($posts) || !array_is_list($posts)) {
                throw new RuntimeException(
                    'WordPress did not return a partner collection.'
                );
            }

            if ($page === 1) {
                $header = $response->header('X-WP-TotalPages');

                if (
                    $header === null
                    || $header === ''
                    || !ctype_digit((string) $header)
                ) {
                    throw new RuntimeException(
                        'Missing or invalid WordPress pagination header.'
                    );
                }

                $pages = max(1, (int) $header);

                if ($pages > 20) {
                    throw new RuntimeException(
                        'Preview supports at most 20 pages.'
                    );
                }
            }

            foreach ($posts as $post) {
                if (
                    !is_array($post)
                    || ($post['type'] ?? '') !== 'meeting-room-partner'
                    || ($post['status'] ?? '') !== 'publish'
                ) {
                    throw new RuntimeException(
                        'Unexpected WordPress partner record.'
                    );
                }

                $wpId = filter_var(
                    $post['id'] ?? null,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );

                if ($wpId === false || isset($seen[$wpId])) {
                    throw new RuntimeException(
                        'Invalid or duplicate WordPress partner ID.'
                    );
                }

                $seen[$wpId] = true;

                if (count($seen) > 2000) {
                    throw new RuntimeException(
                        'Preview limit: 2,000 partner records.'
                    );
                }

                $meta = $post['meta'] ?? null;

                if (!is_array($meta)) {
                    throw new RuntimeException(
                        'Partner metadata is missing for WP #' . $wpId . '.'
                    );
                }

                foreach (
                    [
                        'meeting_room_partners_logo',
                        'subtitle',
                        'meeting_room_partners_description',
                        'meeting_room_partners_website',
                    ] as $field
                ) {
                    if (!array_key_exists($field, $meta)) {
                        throw new RuntimeException(
                            'Missing REST field ' . $field
                                . ' for WP #' . $wpId . '.'
                        );
                    }

                    if (
                        $meta[$field] !== null
                        && !is_scalar($meta[$field])
                    ) {
                        throw new RuntimeException(
                            'Unexpected format for ' . $field
                                . ' on WP #' . $wpId . '.'
                        );
                    }
                }

                $issues = [];
                $warnings = [];

                $name = $this->plainText(
                    (string) ($post['title']['rendered'] ?? '')
                );

                $location = $this->extractLocation(
                    (string) ($meta['subtitle'] ?? '')
                );

                $description = trim((string) (
                    $meta['meeting_room_partners_description'] ?? ''
                ));

                // This source field is textarea content.
                // Escape it and preserve its line breaks as safe HTML.
                $profileHtml = nl2br(htmlspecialchars(
                    $description,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ));

                $website = trim((string) (
                    $meta['meeting_room_partners_website'] ?? ''
                ));

                $websiteParts = parse_url($website);

                if (
                    $website !== ''
                    && (
                        !filter_var($website, FILTER_VALIDATE_URL)
                        || !is_array($websiteParts)
                        || !in_array(
                            strtolower($websiteParts['scheme'] ?? ''),
                            ['http', 'https'],
                            true
                        )
                        || isset($websiteParts['user'])
                        || isset($websiteParts['pass'])
                    )
                ) {
                    $issues[] = 'Invalid company website: ' . $website;
                }

                if ($name === '') {
                    $issues[] = 'Partner name is empty.';
                }

                foreach (
                    [
                        'Name' => $name,
                        'Location' => $location,
                        'Website' => $website,
                    ] as $label => $value
                ) {
                    if (mb_strlen($value) > 255) {
                        $issues[] = $label . ' exceeds 255 characters.';
                    }
                }

                if (strlen($profileHtml) > 65535) {
                    $issues[] = 'Profile exceeds the TEXT column limit.';
                }

                if ($location === '') {
                    $warnings[] = 'Location is empty.';
                }

                if ($description === '') {
                    $warnings[] = 'Description is empty.';
                }

                if ($website === '') {
                    $warnings[] = 'Website is empty.';
                }

                $logoRaw = trim((string) (
                    $meta['meeting_room_partners_logo'] ?? ''
                ));

                $logoId = 0;
                $logoUrl = '';
                $logoStatus = 'missing';
                $logoMessage = '';

                if ($logoRaw !== '' && $logoRaw !== '0') {
                    $validatedLogoId = filter_var(
                        $logoRaw,
                        FILTER_VALIDATE_INT,
                        ['options' => ['min_range' => 1]]
                    );

                    if ($validatedLogoId === false) {
                        $logoStatus = 'invalid';
                        $logoMessage = 'Logo is not a valid attachment ID.';

                        $issues[] = $logoMessage;
                    } else {
                        $logoId = (int) $validatedLogoId;

                        /*
         * Cache successful and denied attachment lookups for this
         * preview so shared attachments are requested only once.
         */
                        if (!array_key_exists($logoId, $mediaUrls)) {
                            try {
                                $mediaResponse = $this->request(
                                    $base . '/wp-json/wp/v2/media/' . $logoId,
                                    $host,
                                    $pinned,
                                    $started,
                                    ['_fields' => 'id,media_type,source_url']
                                );

                                $media = $mediaResponse->json();

                                if (
                                    !is_array($media)
                                    || (int) ($media['id'] ?? 0) !== $logoId
                                    || ($media['media_type'] ?? '') !== 'image'
                                ) {
                                    throw new RuntimeException(
                                        'Invalid logo media response for #'
                                            . $logoId . '.'
                                    );
                                }

                                $url = (string) ($media['source_url'] ?? '');
                                $urlParts = parse_url($url);

                                if (
                                    !filter_var($url, FILTER_VALIDATE_URL)
                                    || !is_array($urlParts)
                                    || strtolower(
                                        $urlParts['scheme'] ?? ''
                                    ) !== 'https'
                                    || strtolower(
                                        $urlParts['host'] ?? ''
                                    ) !== $host
                                    || isset($urlParts['user'])
                                    || isset($urlParts['pass'])
                                    || (
                                        isset($urlParts['port'])
                                        && (int) $urlParts['port'] !== 443
                                    )
                                ) {
                                    throw new RuntimeException(
                                        'Logo URL is missing or outside the '
                                            . 'approved source website.'
                                    );
                                }

                                $mediaUrls[$logoId] = [
                                    'status' => 'available',
                                    'url' => $url,
                                    'message' => '',
                                ];
                            } catch (RuntimeException $exception) {
                                $status = (int) $exception->getCode();

                                if (!in_array($status, [401, 403], true)) {
                                    throw new RuntimeException(
                                        'Partner "' . $name . '"'
                                            . ' (WP #' . $wpId . ')'
                                            . ', logo attachment #' . $logoId . ': '
                                            . $exception->getMessage(),
                                        $status,
                                        $exception
                                    );
                                }

                                $mediaUrls[$logoId] = [
                                    'status' => 'denied',
                                    'url' => '',
                                    'message' =>
                                    'WordPress denied access to logo attachment #'
                                        . $logoId . ' (HTTP ' . $status . ').',
                                ];
                            }
                        }

                        $logoResult = $mediaUrls[$logoId];

                        $logoStatus = $logoResult['status'];
                        $logoUrl = $logoResult['url'];
                        $logoMessage = $logoResult['message'];

                        if ($logoStatus === 'denied') {
                            $warnings[] = $logoMessage
                                . ' Other partner fields remain available for preview. '
                                . 'Preserve any existing app logo.';
                        }
                    }
                } else {
                    $logoMessage = 'No source logo.';
                    $warnings[] = $logoMessage;
                }

                $rows[] = [
                    'wp_id' => (int) $wpId,
                    'name' => $name,
                    'location' => $location,
                    'website' => $website,
                    'profile_text' => $description,
                    'profile_html_text' => $profileHtml,
                    'logo_attachment_id' => $logoId,
                    'logo_url' => $logoUrl,
                    'logo_status' => $logoStatus,
                    'logo_message' => $logoMessage,
                    'issues' => $issues,
                    'warnings' => $warnings,
                ];
            }

            $page++;
        } while ($page <= $pages);

        return $rows;
    }

    private function request(
        string $url,
        string $host,
        string $pinned,
        float $started,
        array $query
    ) {
        $previewBudget = (int) config(
            'wordpress_agenda.meeting_room_partner_preview_seconds',
            90
        );

        $remaining = $previewBudget - (microtime(true) - $started);

        if ($remaining <= 0) {
            throw new RuntimeException(
                'Preview time limit reached. No partners were changed.'
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
            ->get($url, $query);

        if ($response->status() !== 200) {
            $payload = $response->json();

            $code = is_array($payload)
                && is_string($payload['code'] ?? null)
                ? $payload['code']
                : '';

            $message = is_array($payload)
                && is_string($payload['message'] ?? null)
                ? $this->plainText($payload['message'])
                : '';

            \Illuminate\Support\Facades\Log::warning(
                'WordPress meeting room partner preview request failed.',
                [
                    'endpoint' => $url,
                    'status' => $response->status(),
                    'wordpress_code' => $code,
                    'wordpress_message' => mb_substr($message, 0, 300),
                ]
            );

            throw new RuntimeException(
                'WordPress returned HTTP '
                    . $response->status()
                    . ' for '
                    . (parse_url($url, PHP_URL_PATH) ?: 'the requested endpoint')
                    . ($code !== '' ? ' [' . $code . ']' : '')
                    . ($message !== ''
                        ? ': ' . mb_substr($message, 0, 200)
                        : '. Check the Laravel log for details.'),
                $response->status()
            );
        }

        return $response;
    }

    private function resolveWebsite(string $sourceUrl): array
    {
        $base = rtrim(trim($sourceUrl), '/');
        $parts = parse_url($base);
        $host = strtolower($parts['host'] ?? '');

        if (
            !$parts
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
                'Configure an approved HTTPS website in General Settings. '
                    . 'Check config/wordpress_agenda.php.'
            );
        }

        if (
            !extension_loaded('curl')
            || !function_exists('dns_get_record')
        ) {
            throw new RuntimeException(
                'Preview requires PHP cURL and dns_get_record.'
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

    private function extractLocation(string $html): string
    {
        $text = $this->plainText($html);

        return trim(preg_replace(
            '/^meeting\s+rooms?\s*:\s*/iu',
            '',
            $text
        ) ?? $text);
    }

    private function plainText(string $html): string
    {
        $html = preg_replace(
            '/<\s*br\s*\/?\s*>|<\/\s*(?:p|div|li)\s*>/i',
            ' ',
            $html
        ) ?? $html;

        $text = html_entity_decode(
            strip_tags($html),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        return trim(
            preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? $text
        );
    }
}
