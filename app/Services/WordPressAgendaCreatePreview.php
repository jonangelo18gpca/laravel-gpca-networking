<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Session;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WordPressAgendaCreatePreview
{
    public function build(Event $event, $postType)
    {
        $source = app(WordPressAgendaPreview::class)
            ->fetch($event, $postType);

        $base = rtrim((string) $event->wordpress_source_url, '/');

        $sessions = Session::where('event_id', $event->id)->get();

        $result = [
            'rows' => [],
            'ready' => 0,
            'review' => 0,
            'linked' => 0,
        ];

        $seen = [];
        $signatures = [];

        foreach ($source['rows'] as $row) {
            $signature = json_encode([
                (int) $row['feature_id'],
                $this->titleKey($row['title']),
                $row['date'],
                $row['start_time'],
            ]);

            $signatures[$signature] =
                ($signatures[$signature] ?? 0) + 1;
        }

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

            if ($link) {
                $valid = $sessions->contains(function ($session) use (
                    $link,
                    $row
                ) {
                    return (int) $session->id === (int) $link->session_id
                        && (int) $session->feature_id ===
                        (int) $row['feature_id'];
                });

                if (!$valid) {
                    throw new RuntimeException(
                        'A saved link points to a missing or recategorized session. Repair it first.'
                    );
                }

                $result['linked']++;
                continue;
            }

            $issues = $row['issues'];
            $candidates = [];

            foreach ($sessions as $session) {
                if (
                    (int) $session->feature_id !==
                    (int) $row['feature_id']
                ) {
                    continue;
                }

                $sameTitle = $this->titleKey($session->title) ===
                    $this->titleKey($row['title']);

                $sameSlot =
                    Carbon::parse($session->session_date)
                    ->format('Y-m-d') === $row['date']
                    && substr((string) $session->start_time, 0, 5) ===
                    $row['start_time'];

                if ($sameTitle || $sameSlot) {
                    $candidates[] = (int) $session->id;
                }
            }

            if ($candidates) {
                $issues[] =
                    'Possible existing app session(s): #' .
                    implode(', #', $candidates) .
                    '. Review and link if they are the same session.';
            }

            $signature = json_encode([
                (int) $row['feature_id'],
                $this->titleKey($row['title']),
                $row['date'],
                $row['start_time'],
            ]);

            if ($signatures[$signature] > 1) {
                $issues[] =
                    'Multiple WordPress posts share this title, date and start time. Review before creating.';
            }

            $status = $issues ? 'Needs review' : 'Ready to create';

            if ($issues) {
                $result['review']++;
            } else {
                $result['ready']++;
            }

            $result['rows'][] = [
                'wordpress_id' => $wpId,
                'feature_id' => (int) $row['feature_id'],
                'title' => $row['title'],
                'category' => $row['category'],
                'date' => $row['date'],
                'day' => $row['day'],
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
                'status' => $status,
                'issues' => $issues,
            ];
        }

        return $result;
    }

    private function titleKey($value)
    {
        return mb_strtolower(
            preg_replace('/\s+/u', ' ', trim((string) $value))
        );
    }
}
