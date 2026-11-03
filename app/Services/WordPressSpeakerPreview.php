<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WordPressSpeakerPreview
{
    public const SOURCES = [
        'main_plenary_speaker' => 'Main plenary speakers',
        'suspav-speaker' => 'Sustainability Pavilion speakers',
    ];

    public function fetch(
        string $source,
        string $year,
        string $sourceUrl
    ): array {
        if (
            strlen($source) > 100
            || !preg_match('/^[a-z0-9_-]+$/', $source)
        ) {
            throw new RuntimeException('Invalid speaker post-type slug.');
        }

        [$base, $host, $pinned] = $this->resolveSourceWebsite($sourceUrl);

        if (!preg_match('/^\d{4}$/', $year)) {
            throw new RuntimeException('Enter a four-digit year.');
        }

        $rows = [];
        $page = 1;
        $totalPages = 1;

        do {
            // $response = Http::acceptJson()
            //     ->connectTimeout(10)
            //     ->timeout(30)
            //     ->withOptions(['allow_redirects' => false])
            //     ->get(
            //         'https://www.gpcaforum.com/wp-json/wp/v2/' . $source,
            //         [
            //             'status' => 'publish',
            //             'per_page' => 100,
            //             'page' => $page,
            //             '_embed' => 1,
            //         ]
            //     );

            $response = Http::acceptJson()
                ->connectTimeout(10)
                ->timeout(30)
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
                    $base . '/wp-json/wp/v2/' . $source,
                    [
                        'status' => 'publish',
                        'per_page' => 100,
                        'page' => $page,
                        'orderby' => 'id',
                        'order' => 'asc',
                        '_embed' => 1,
                    ]
                );

            if (!$response->successful()) {
                throw new RuntimeException(
                    'WordPress returned HTTP ' . $response->status() . '.'
                );
            }

            $posts = $response->json();

            if (!is_array($posts) || array_values($posts) !== $posts) {
                throw new RuntimeException(
                    'WordPress did not return a speaker collection.'
                );
            }

            $pagesHeader = $response->header('X-WP-TotalPages');

            if ($pagesHeader === null || $pagesHeader === '') {
                throw new RuntimeException(
                    'WordPress pagination header is missing. Preview stopped to avoid an incomplete list.'
                );
            }

            $totalPages = max(1, (int) $pagesHeader);

            if ($totalPages > 20) {
                throw new RuntimeException(
                    'The source exceeds the preview limit of 20 pages.'
                );
            }

            foreach ($posts as $post) {
                if (!is_array($post)) {
                    throw new RuntimeException('Invalid speaker record.');
                }

                if (!isset($post['status']) || !is_string($post['status'])) {
                    throw new RuntimeException(
                        'A speaker record is missing a valid publication status.'
                    );
                }

                if ($post['status'] !== 'publish') {
                    continue;
                }

                $meta = $post['meta'] ?? null;

                if (!is_array($meta) || !array_key_exists('year', $meta)) {
                    throw new RuntimeException(
                        'A speaker record is missing meta.year. Check REST field exposure.'
                    );
                }

                if (!is_scalar($meta['year'])) {
                    throw new RuntimeException('Unexpected speaker year format.');
                }

                if (trim((string) $meta['year']) !== $year) {
                    continue;
                }

                if (($post['type'] ?? '') !== $source) {
                    throw new RuntimeException('Unexpected speaker post type.');
                }

                $rows[] = $this->normalize($post, $meta);
            }

            $page++;
        } while ($page <= $totalPages);

        return $rows;
    }


    private function resolveSourceWebsite(string $sourceUrl): array
    {
        $base = rtrim(trim($sourceUrl), '/');
        $parts = parse_url($base);
        $host = strtolower($parts['host'] ?? '');
        $allowed = config('wordpress_agenda.allowed_hosts', []);

        if (
            !$parts
            || ($parts['scheme'] ?? '') !== 'https'
            || $host === ''
            || !in_array($host, $allowed, true)
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
                'Source URL is not an approved HTTPS WordPress website. '
                    . 'Check General Settings and '
                    . 'config/wordpress_agenda.php.'
            );
        }

        if (
            !extension_loaded('curl')
            || !function_exists('dns_get_record')
        ) {
            throw new RuntimeException(
                'This preview requires PHP cURL and dns_get_record.'
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

    private function normalize(array $post, array $meta): array
    {
        $warnings = [];

        $name = $this->plainText($meta['name'] ?? '');

        if ($name === '') {
            $name = $this->plainText($post['title']['rendered'] ?? '');
            $warnings[] = 'meta.name is empty; using the post title for display.';
        }

        [$jobTitle, $company] = $this->extractDesignationAndCompany(
            $this->stringValue($meta['designation_and_company'] ?? '')
        );

        if ($jobTitle === '') {
            $warnings[] = 'No designation found inside em/i elements.';
        }

        if ($company === '') {
            $warnings[] = 'No company found inside strong/b elements.';
        }

        $exclude = array_key_exists('hide_speaker_name', $meta)
            ? $this->checkboxValue($meta['hide_speaker_name'])
            : null;

        if ($exclude === null) {
            $warnings[] = 'Missing or unrecognized hide_speaker_name value.';
        }

        $bio = $this->stringValue($meta['bio'] ?? '');
        $biographyHtml = app(WordPressSpeakerBiography::class)
            ->sanitize($bio);

        if ($this->plainText($bio) === '') {
            $warnings[] = 'Biography is empty; preserve any existing app biography.';
        }

        $mediaId = (int) ($post['featured_media'] ?? 0);
        $imageUrl = $this->stringValue(
            $post['_embedded']['wp:featuredmedia'][0]['source_url'] ?? ''
        );

        if ($mediaId && $imageUrl === '') {
            $warnings[] = 'Featured media exists but its embedded URL is missing.';
        }

        if ($mediaId === 10494) {
            $warnings[] = 'Shared image from the supplied examples; review before importing.';
        }

        return [
            'wp_id' => (int) ($post['id'] ?? 0),
            'source' => $this->stringValue($post['type'] ?? ''),
            'year' => $this->stringValue($meta['year'] ?? ''),
            'name' => $name,
            'job_title' => $jobTitle,
            'company_name' => $company,
            'role' => $this->plainText($meta['role'] ?? ''),
            'biography_text' => $this->plainText($bio),
            'biography_html' => $biographyHtml,
            'media_id' => $mediaId,
            'image_url' => $imageUrl,
            'eligibility' => $exclude === true
                ? 'Excluded'
                : ($exclude === false ? 'Eligible' : 'Needs review'),
            'warnings' => $warnings,
        ];
    }

    private function extractDesignationAndCompany(string $html): array
    {
        if (trim($html) === '') {
            return ['', ''];
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>',
                LIBXML_NONET
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$loaded) {
            throw new RuntimeException('Unable to parse designation/company HTML.');
        }

        $xpath = new DOMXPath($document);

        $jobTitle = $this->collectText(
            $xpath,
            '//em[not(ancestor::em or ancestor::i)]'
                . ' | //i[not(ancestor::em or ancestor::i)]'
        );

        $company = $this->collectText(
            $xpath,
            '//strong[not(ancestor::strong or ancestor::b)]'
                . ' | //b[not(ancestor::strong or ancestor::b)]'
        );

        return [
            preg_replace('/[\s,;:]+$/u', '', $jobTitle) ?? $jobTitle,
            preg_replace('/^[\s,;:]+|[\s,;:]+$/u', '', $company) ?? $company,
        ];
    }

    private function collectText(DOMXPath $xpath, string $query): string
    {
        $parts = [];

        foreach ($xpath->query($query) as $node) {
            $parts[] = $node->ownerDocument->saveHTML($node);
        }

        return $this->plainText(implode(' ', $parts));
    }

    private function checkboxValue($value): ?bool
    {
        if (is_array($value)) {
            if ($value === []) {
                return false;
            }

            $results = [];

            foreach ($value as $item) {
                $results[] = $this->checkboxValue($item);
            }

            if (in_array(null, $results, true)) {
                return null;
            }

            return in_array(true, $results, true);
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) && in_array($value, [0, 1], true)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['true', '1'], true)) {
                return true;
            }

            if (in_array($normalized, ['false', '0', ''], true)) {
                return false;
            }
        }

        return null;
    }

    private function plainText($value): string
    {
        $html = $this->stringValue($value);

        $html = preg_replace(
            '/<\s*br\s*\/?\s*>|<\/\s*(p|div|li)\s*>/i',
            ' ',
            $html
        ) ?? $html;

        $text = html_entity_decode(
            strip_tags($html),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? $text);
    }

    private function stringValue($value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }


    public function suggestNameParts(string $name): array
    {
        $remaining = $this->plainText($name);
        $salutation = '';

        $choices = array_values(array_filter(
            config('app.salutations', []),
            function ($value) {
                return is_string($value) && trim($value) !== '';
            }
        ));

        usort($choices, function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });

        foreach ($choices as $choice) {
            $pattern = '/^' . preg_quote($choice, '/') . '(?:\s+|$)/iu';

            if (preg_match($pattern, $remaining, $match)) {
                $salutation = $choice;
                $remaining = trim(substr($remaining, strlen($match[0])));
                break;
            }
        }

        $tokens = preg_split(
            '/\s+/u',
            $remaining,
            -1,
            PREG_SPLIT_NO_EMPTY
        ) ?: [];

        $lastName = count($tokens) >= 2
            ? array_pop($tokens)
            : '';

        $middleInitials = [];

        while (count($tokens) > 1) {
            $lastToken = $tokens[count($tokens) - 1];

            if (!preg_match('/^\p{L}\.$/u', $lastToken)) {
                break;
            }

            array_unshift($middleInitials, array_pop($tokens));
        }

        $firstName = implode(' ', $tokens);
        $middleName = implode(' ', $middleInitials);
        $reviewReasons = [];

        if ($firstName === '' || $lastName === '') {
            $reviewReasons[] = 'Cannot identify both a first name and a last name.';
        }

        if (preg_match('/[\p{N}\/|,;()]/u', $remaining)) {
            $reviewReasons[] =
                'Name contains digits or separators that may need manual splitting.';
        }

        $titleOnly = false;

        foreach ($choices as $choice) {
            if (
                $firstName !== ''
                && strcasecmp(trim($firstName), trim($choice)) === 0
            ) {
                $titleOnly = true;
                break;
            }
        }

        if ($titleOnly) {
            $reviewReasons[] =
                'The proposed first name contains only a salutation.';
        }

        if (
            $firstName !== ''
            && preg_match('/^(?:\p{L}\.\s*)+$/u', $firstName)
        ) {
            $reviewReasons[] =
                'The proposed first name contains only initials.';
        }

        return [
            'salutation' => $salutation,
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'review_required' => count($reviewReasons) > 0,
            'review_reasons' => $reviewReasons,
            'reviewed' => false,
        ];
    }
}
