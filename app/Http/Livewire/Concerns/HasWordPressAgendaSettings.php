<?php

namespace App\Http\Livewire\Concerns;

use App\Models\Event;
use App\Models\Feature;
use App\Models\SessionDate;
use App\Models\SessionDay;
use Illuminate\Validation\ValidationException;

trait HasWordPressAgendaSettings
{
    public $wpSourceUrl = '';
    public $wpAgendaYear = '';
    public $wpAgendaSources = [];
    public $wpAgendaDays = [];

    protected function loadWordPressAgendaSettings()
    {
        $event = Event::findOrFail($this->eventData['eventId']);
        $config = $event->wordpress_agenda_config ?? [];
        $this->wpSourceUrl = $event->wordpress_source_url ?? '';
        $this->wpAgendaYear = (string) ($config['year'] ?? $event->year ?? '');
        $this->wpAgendaSources = $config['sources'] ?? [
            ['post_type' => 'main-plenary-agenda', 'feature_id' => '0'],
            ['post_type' => 'suspav-agenda', 'feature_id' => ''],
            ['post_type' => 'knowledge-agenda', 'feature_id' => ''],
        ];
        $this->wpAgendaDays = $config['days'] ?? [
            ['wordpress_day' => 'Pre-conference', 'session_day' => '', 'session_date' => ''],
            ['wordpress_day' => 'Day 1', 'session_day' => '', 'session_date' => ''],
            ['wordpress_day' => 'Day 2', 'session_day' => '', 'session_date' => ''],
        ];
    }

    public function getWpFeatureOptionsProperty()
    {
        return Feature::where('event_id', $this->eventData['eventId'])->orderBy('id')->get();
    }

    public function getWpDateOptionsProperty()
    {
        return SessionDate::where('event_id', $this->eventData['eventId'])
            ->orderBy('session_date')->get();
    }

    public function getWpDayOptionsProperty()
    {
        return SessionDay::where('event_id', $this->eventData['eventId'])->orderBy('id')->get();
    }

    public function addWpAgendaSource()
    {
        if (count($this->wpAgendaSources) < 30) {
            $this->wpAgendaSources[] = ['post_type' => '', 'feature_id' => ''];
        }
    }

    public function removeWpAgendaSource($index)
    {
        unset($this->wpAgendaSources[(int) $index]);
        $this->wpAgendaSources = array_values($this->wpAgendaSources);
    }

    public function addWpAgendaDay()
    {
        if (count($this->wpAgendaDays) < 30) {
            $this->wpAgendaDays[] = ['wordpress_day' => '', 'session_day' => '', 'session_date' => ''];
        }
    }

    public function removeWpAgendaDay($index)
    {
        unset($this->wpAgendaDays[(int) $index]);
        $this->wpAgendaDays = array_values($this->wpAgendaDays);
    }

    public function saveWordPressAgendaSettings()
    {
        // This action belongs behind the existing event-admin authorization.
        $event = Event::findOrFail($this->eventData['eventId']);
        $this->wpSourceUrl = rtrim(trim((string) $this->wpSourceUrl), '/');
        foreach ($this->wpAgendaSources as &$source) {
            $source['post_type'] = trim((string) ($source['post_type'] ?? ''));
        }
        unset($source);
        foreach ($this->wpAgendaDays as &$day) {
            $day['wordpress_day'] = trim((string) ($day['wordpress_day'] ?? ''));
        }
        unset($day);

        $this->validate([
            'wpSourceUrl' => 'required|url|max:2048',
            'wpAgendaYear' => 'required|digits:4',
            'wpAgendaSources' => 'required|array|min:1|max:30',
            'wpAgendaSources.*.post_type' => 'required|string|max:100|regex:/^[a-z0-9_-]+$/|distinct',
            'wpAgendaSources.*.feature_id' => 'required|integer|min:0',
            'wpAgendaDays' => 'required|array|min:1|max:30',
            'wpAgendaDays.*.wordpress_day' => 'required|string|max:255|distinct',
            'wpAgendaDays.*.session_day' => 'required|string|max:255',
            'wpAgendaDays.*.session_date' => 'required|date_format:Y-m-d',
        ]);

        $parts = parse_url($this->wpSourceUrl);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) ||
            isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) ||
            isset($parts['fragment']) || (isset($parts['port']) && $parts['port'] !== 443)) {
            throw ValidationException::withMessages(['wpSourceUrl' => 'Use an HTTPS WordPress base URL without credentials, query, fragment or nonstandard port. A subdirectory path is allowed.']);
        }

        $featureIds = Feature::where('event_id', $event->id)->pluck('id')->map(function ($id) { return (int) $id; })->all();
        $dateValues = SessionDate::where('event_id', $event->id)->get()->map(function ($row) {
            return \Carbon\Carbon::parse($row->session_date)->format('Y-m-d');
        })->all();
        $dayValues = SessionDay::where('event_id', $event->id)->pluck('session_day')->all();

        foreach ($this->wpAgendaSources as $i => $source) {
            $id = (int) $source['feature_id'];
            if ($id !== 0 && !in_array($id, $featureIds, true)) {
                throw ValidationException::withMessages(["wpAgendaSources.$i.feature_id" => 'Choose a feature belonging to this event.']);
            }
        }
        $seen = [];
        foreach ($this->wpAgendaDays as $i => $day) {
            $normalized = strtolower($day['wordpress_day']);
            if (isset($seen[$normalized])) {
                throw ValidationException::withMessages(["wpAgendaDays.$i.wordpress_day" => 'Duplicate WordPress day label.']);
            }
            $seen[$normalized] = true;
            if (!in_array($day['session_day'], $dayValues, true) || !in_array($day['session_date'], $dateValues, true)) {
                throw ValidationException::withMessages(["wpAgendaDays.$i.session_date" => 'Choose a date and day already configured for this event.']);
            }
            if (substr($day['session_date'], 0, 4) !== (string) $this->wpAgendaYear) {
                throw ValidationException::withMessages(["wpAgendaDays.$i.session_date" => 'The date must match the selected event year.']);
            }
        }

        $event->wordpress_source_url = $this->wpSourceUrl;
        $event->wordpress_agenda_config = [
            'version' => 1,
            'year' => (string) $this->wpAgendaYear,
            'sources' => array_map(function ($row) {
                return ['post_type' => $row['post_type'], 'feature_id' => (int) $row['feature_id']];
            }, $this->wpAgendaSources),
            'days' => array_map(function ($row) {
                return [
                    'wordpress_day' => $row['wordpress_day'],
                    'session_day' => $row['session_day'],
                    'session_date' => $row['session_date'],
                ];
            }, $this->wpAgendaDays),
        ];
        $event->save();
        session()->flash('wordpressAgendaSettingsSaved', 'WordPress agenda settings saved. No sessions were changed.');
    }
}
