<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Session;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WordPressAgendaUpdatePreview
{
    public function build(Event $event, $postType)
    {
        $source = app(WordPressAgendaPreview::class)
            ->fetch($event, $postType);

        $base = rtrim((string) $event->wordpress_source_url, '/');

        $result = [
            'rows' => [],
            'update' => 0,
            'unchanged' => 0,
            'blocked' => 0,
            'unlinked' => 0,
        ];

        $seen = [];

        foreach ($source['rows'] as $row) {
            $wpId = (int) $row['wordpress_id'];

            if ($wpId < 1 || isset($seen[$wpId])) {
                throw new RuntimeException(
                    'Invalid or duplicate WordPress ID. Preview stopped.'
                );
            }

            $seen[$wpId] = true;

            $identity = hash('sha256', json_encode([
                (int) $event->id,
                $base,
                (string) $postType,
                $wpId,
            ]));

            $link = DB::table('wordpress_agenda_links')
                ->where('identity_hash', $identity)
                ->first();

            if (!$link) {
                $result['unlinked']++;
                continue;
            }

            $session = Session::where('event_id', $event->id)
                ->where('feature_id', (int) $row['feature_id'])
                ->whereKey($link->session_id)
                ->first();

            $entry = [
                'wordpress_id' => $wpId,
                'session_id' => (int) $link->session_id,
                'title' => $row['title'],
                'status' => '',
                'issues' => $row['issues'],
                'changes' => [],
            ];

            if (!$session) {
                $entry['issues'][] =
                    'Saved link points to a missing or recategorized session.';
            }

            if ($entry['issues']) {
                $entry['status'] = 'Blocked';
                $result['blocked']++;
                $result['rows'][] = $entry;
                continue;
            }

            $proposed = [
                'title' => (string) $row['title'],
                'session_date' => (string) $row['date'],
                'session_day' => (string) $row['day'],
                'start_time' => (string) $row['start_time'],
                'end_time' => $row['end_time'] === 'onwards'
                    ? 'none'
                    : (string) $row['end_time'],
            ];

            foreach ($proposed as $field => $after) {
                $before = $this->normalize(
                    $field,
                    $session->getAttribute($field)
                );

                $normalizedAfter = $this->normalize($field, $after);

                if ($before !== $normalizedAfter) {
                    $entry['changes'][] = [
                        'field' => $field,
                        'before' => $before,
                        'after' => $normalizedAfter,
                    ];
                }
            }

            if ($entry['changes']) {
                $entry['status'] = 'Would update';
                $result['update']++;
            } else {
                $entry['status'] = 'No changes';
                $result['unchanged']++;
            }

            $result['rows'][] = $entry;
        }

        return $result;
    }

    private function normalize($field, $value)
    {
        $value = trim((string) $value);

        if ($field === 'session_date' && $value !== '') {
            return Carbon::parse($value)->format('Y-m-d');
        }

        if ($field === 'end_time' &&
            in_array(strtolower($value), ['', 'none', 'onwards'], true)) {
            return 'none';
        }

        if (in_array($field, ['start_time', 'end_time'], true) &&
            preg_match('/^(\d{2}:\d{2})(?::00)?$/', $value, $matches)) {
            return $matches[1];
        }

        return $value;
    }
}