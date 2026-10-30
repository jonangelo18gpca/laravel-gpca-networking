<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Feature;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WordPressAgendaPreview
{
    public function fetch(Event $event, $selectedPostType)
    {
        $config = $event->wordpress_agenda_config ?? [];
        $base = rtrim((string) $event->wordpress_source_url, '/');
        $parts = parse_url($base);
        $host = strtolower($parts['host'] ?? '');
        $allowed = config('wordpress_agenda.allowed_hosts', []);
        if (
            !$parts || ($parts['scheme'] ?? '') !== 'https' || !in_array($host, $allowed, true) ||
            isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) ||
            (isset($parts['port']) && $parts['port'] !== 443)
        ) {
            throw new RuntimeException('Source URL is not an approved HTTPS WordPress website. Check config/wordpress_agenda.php.');
        }
        if (!extension_loaded('curl') || !function_exists('dns_get_record')) {
            throw new RuntimeException('This preview requires PHP cURL and dns_get_record.');
        }
        $addresses = [];
        foreach ((array) dns_get_record($host, DNS_A | DNS_AAAA) as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (!$ip) continue;
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Source DNS resolves to a non-public address.');
            }
            $addresses[] = $ip;
        }
        if (!$addresses) throw new RuntimeException('Cannot resolve the approved website.');
        $ip = $addresses[0];
        $pinned = strpos($ip, ':') !== false ? '[' . $ip . ']' : $ip;
        $year = (string) ($config['year'] ?? '');
        $sources = $config['sources'] ?? [];
        // Fetch only the selected source from this event's saved mappings.
        $sources = array_values(array_filter(
            $sources,
            function ($source) use ($selectedPostType) {
                return ($source['post_type'] ?? '') === $selectedPostType;
            }
        ));

        if (count($sources) !== 1) {
            throw new RuntimeException(
                'Select a valid agenda feature from the saved settings.'
            );
        }
        if (!preg_match('/^\d{4}$/', $year) || !$sources || count($sources) > 30) {
            throw new RuntimeException('Save valid agenda source and year settings first.');
        }
        $days = [];
        foreach (($config['days'] ?? []) as $day) {
            $key = strtolower(trim((string) ($day['wordpress_day'] ?? '')));
            if ($key === '' || isset($days[$key])) throw new RuntimeException('Invalid or duplicate day mapping.');
            $days[$key] = $day;
        }
        $features = Feature::where('event_id', $event->id)->pluck('short_name', 'id')->all();
        $result = ['rows' => [], 'sources' => [], 'skipped_year' => 0, 'issues' => 0];
        $started = microtime(true);
        $fetched = 0;
        foreach ($sources as $source) {
            $type = (string) ($source['post_type'] ?? '');
            $featureId = (int) ($source['feature_id'] ?? -1);
            if (!preg_match('/^[a-z0-9_-]+$/', $type) || ($featureId !== 0 && !isset($features[$featureId]))) {
                throw new RuntimeException('An agenda source has an invalid post type or destination feature.');
            }
            $category = $featureId === 0 ? $event->short_name : $features[$featureId];
            $endpoint = $base . '/wp-json/wp/v2/' . $type;
            $page = 1;
            $pages = 1;
            $included = 0;
            do {
                if (microtime(true) - $started > 45) throw new RuntimeException('Preview time limit reached. No sessions were changed.');
                $response = Http::acceptJson()->timeout(10)->withOptions([
                    'connect_timeout' => 5,
                    'allow_redirects' => false,
                    'verify' => true,
                    'proxy' => '',
                    'curl' => [CURLOPT_RESOLVE => [$host . ':443:' . $pinned]],
                ])->get($endpoint, [
                    'per_page' => 100,
                    'page' => $page,
                    'status' => 'publish',
                    'orderby' => 'id',
                    'order' => 'asc',
                    '_fields' => 'id,type,title,agenda_fields',
                ]);
                if ($response->status() !== 200) {
                    throw new RuntimeException('WordPress returned HTTP ' . $response->status() . ' for ' . $type . '. Check the source URL and API access. Redirects are not followed.');
                }
                $posts = $response->json();
                if (!is_array($posts) || ($posts && array_keys($posts) !== range(0, count($posts) - 1))) {
                    throw new RuntimeException('WordPress did not return a post list for ' . $type . '.');
                }
                if ($page === 1) {
                    $header = $response->header('X-WP-TotalPages');
                    if ($header === null || $header === '' || !ctype_digit((string) $header)) {
                        throw new RuntimeException('Missing pagination header for ' . $type . '. Preview stopped to avoid incomplete results.');
                    }
                    $pages = max(1, (int) $header);
                    if ($pages > 20) throw new RuntimeException('This test preview supports at most 20 pages per source.');
                }
                foreach ($posts as $post) {
                    if (++$fetched > 2000) throw new RuntimeException('Preview limit: 2,000 fetched posts across sources. No partial preview shown.');
                    if (!isset($post['agenda_fields']) || !is_array($post['agenda_fields'])) {
                        throw new RuntimeException('agenda_fields is missing from ' . $type . '. Check the WordPress snippet.');
                    }
                    $meta = $post['agenda_fields'];
                    $issues = [];
                    $wpYear = trim((string) ($meta['year'] ?? ''));
                    if ($wpYear !== '' && $wpYear !== $year) {
                        $result['skipped_year']++;
                        continue;
                    }
                    if ($wpYear === '') $issues[] = 'Missing WordPress year';
                    $label = trim((string) ($meta['day'] ?? ''));
                    $day = $days[strtolower($label)] ?? null;
                    if (!$day) $issues[] = 'Unmapped day: ' . $label;
                    $date = $day['session_date'] ?? '';
                    $title = trim(strip_tags(html_entity_decode((string) ($meta['session_title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                    if ($title === '') $title = trim(strip_tags(html_entity_decode((string) ($post['title']['rendered'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                    if ($title === '') $issues[] = 'Missing title';
                    $start = trim((string) ($meta['start_time'] ?? ''));
                    $end = trim((string) ($meta['end_time'] ?? ''));
                    // WordPress may explicitly use an open-ended time.
                    if (in_array(strtolower($end), ['onwards', 'none'], true)) {
                        $end = '';
                    }
                    $timePattern = '/^(?:[01]\d|2[0-3]):[0-5]\d$/';
                    if (!preg_match($timePattern, $start)) $issues[] = 'Invalid start time: ' . $start;
                    if ($end !== '' && !preg_match($timePattern, $end)) $issues[] = 'Invalid end time: ' . $end;
                    if ($end !== '' && preg_match($timePattern, $start) && preg_match($timePattern, $end) && $end <= $start) $issues[] = 'End time is not after start time; review overnight sessions';
                    if ($date !== '' && substr($date, 0, 4) !== $year) $issues[] = 'Mapped date year mismatch';
                    $description = (string) ($meta['session_description'] ?? '');
                    $result['rows'][] = [
                        'wordpress_id' => (int) ($post['id'] ?? 0),
                        'post_type' => $type,
                        'category' => $category,
                        'feature_id' => $featureId,
                        'date' => $date,
                        'day' => $day['session_day'] ?? '',
                        'wordpress_day' => $label,
                        'title' => $title,
                        'start_time' => $start,
                        'end_time' => $end === '' ? 'onwards' : $end,
                        'description_preview' => mb_substr(trim(strip_tags($description)), 0, 240),
                        'description_empty' => trim(strip_tags($description)) === '',
                        'issues' => $issues,
                    ];
                    if ($issues) $result['issues']++;
                    $included++;
                }
                $page++;
            } while ($page <= $pages);
            $result['sources'][] = ['endpoint' => $endpoint, 'category' => $category, 'count' => $included];
        }
        usort($result['rows'], function ($a, $b) {
            return strcmp($a['date'] . ' ' . $a['start_time'], $b['date'] . ' ' . $b['start_time']);
        });
        return $result;
    }
}
