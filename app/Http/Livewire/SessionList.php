<?php

namespace App\Http\Livewire;

use App\Models\Event as Events;
use App\Models\Session as Sessions;
use App\Models\Feature as Features;
use App\Models\SessionDate as SessionDates;
use App\Models\SessionDay as SessionDays;
use App\Models\SessionType as SessionTypes;
use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Facades\DB;

class SessionList extends Component
{
    public $event;
    public $finalListOfSessions = [];

    // Add Sesssion
    public $feature_id, $session_date, $session_day, $session_type, $title, $start_time, $end_time;
    public $categoryChoices = [], $sessionDateChoices = [], $sessionDayChoices = [], $sessionTypeChoices = [];
    public $addSessionForm;

    // Add Session date
    public $add_session_date, $add_session_date_desc, $session_dates = [];
    public $addSessionDateForm;

    // Add Session day
    public $add_session_day, $add_session_day_desc, $session_days = [];
    public $addSessionDayForm;
    public $editingSessionDayId = null;

    // Add Session day
    public $add_session_type, $add_session_type_desc, $session_types = [];
    public $addSessionTypeForm;

    // DELETE
    public $activeDeleteIndex;

    protected $listeners = ['addSessionConfirmed' => 'addSession', 'deleteSessionConfirmed' => 'deleteSession'];

    public function mount($eventId, $eventCategory)
    {
        $this->event = Events::where('id', $eventId)->where('category', $eventCategory)->first();
        $sessions = Sessions::where('event_id', $eventId)->orderBy('session_date', 'ASC')->orderBy('start_time', 'ASC')->get();

        if ($sessions->isNotEmpty()) {
            foreach ($sessions as $session) {

                if ($session->feature_id == 0) {
                    $categoryName = $this->event->short_name;
                } else {
                    $feature = Features::where('event_id', $this->event->id)->where('id', $session->feature_id)->first();
                    if ($feature) {
                        $categoryName = $feature->short_name;
                    } else {
                        $categoryName = "Others";
                    }
                }

                if ($session->end_time == "none") {
                    $timings = $session->start_time . ' - ' . 'onwards';
                } else {
                    $timings = $session->start_time . ' - ' . $session->end_time;
                }

                array_push($this->finalListOfSessions, [
                    'id' => $session->id,
                    'categoryName' => $categoryName,
                    'session_date' => Carbon::parse($session->session_date)->format('F d, Y'),
                    'session_day' => $session->session_day,
                    'title' => $session->title,
                    'timings' => $timings,
                    'is_active' => $session->is_active,
                    'datetime_added' => Carbon::parse($session->datetime_added)->format('M j, Y g:i A'),
                ]);
            }
        }

        $this->addSessionForm = false;
        $this->addSessionDateForm = false;
        $this->addSessionDayForm = false;
        $this->addSessionTypeForm = false;
    }

    public function render()
    {
        return view('livewire.event.sessions.session-list');
    }

    public function showAddSession()
    {
        array_push($this->categoryChoices, [
            'value' => $this->event->short_name,
            'id' => 0,
        ]);

        $features = Features::where('event_id', $this->event->id)->get();
        if ($features->isNotEmpty()) {
            foreach ($features as $feature) {
                array_push($this->categoryChoices, [
                    'value' => $feature->short_name,
                    'id' => $feature->id,
                ]);
            }
        }

        $sessionDates = SessionDates::where('event_id', $this->event->id)->get();
        if ($sessionDates->isNotEmpty()) {
            foreach ($sessionDates as $sessionDate) {
                array_push($this->sessionDateChoices, [
                    'name' => Carbon::parse($sessionDate->session_date)->format('F d, Y'),
                    'value' => $sessionDate->session_date,
                ]);
            }
        }

        $sessionDays = SessionDays::where('event_id', $this->event->id)->get();
        if ($sessionDays->isNotEmpty()) {
            foreach ($sessionDays as $sessionDay) {
                array_push($this->sessionDayChoices, $sessionDay->session_day);
            }
        }

        $sessionTypes = SessionTypes::where('event_id', $this->event->id)->get();
        if ($sessionTypes->isNotEmpty()) {
            foreach ($sessionTypes as $sessionType) {
                array_push($this->sessionTypeChoices, $sessionType->session_type);
            }
        }

        $this->addSessionForm = true;
    }

    public function addSessionConfirmation()
    {
        $this->validate([
            'feature_id' => 'required',
            'session_date' => 'required',
            'session_day' => 'required',
            'title' => 'required',
            'start_time' => 'required',
        ]);

        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure?',
            'text' => "",
            'buttonConfirmText' => "Yes, add it!",
            'livewireEmit' => "addSessionConfirmed",
        ]);
    }

    public function resetAddSessionFields()
    {
        $this->addSessionForm = false;
        $this->feature_id = null;
        $this->session_date = null;
        $this->session_day = null;
        $this->session_type = null;
        $this->title = null;
        $this->start_time = null;
        $this->end_time = null;
        $this->categoryChoices = [];
        $this->sessionDateChoices = [];
        $this->sessionDayChoices = [];
        $this->sessionTypeChoices = [];
    }

    public function addSession()
    {
        if ($this->end_time == "" || $this->end_time == null) {
            $finalEndTime = "none";
            $forArrayTimings = $this->start_time . ' - ' . 'onwards';
        } else {
            $finalEndTime = $this->end_time;
            $forArrayTimings = $this->start_time . ' - ' . $this->end_time;
        }

        if ($this->session_type == "" || $this->session_type == null) {
            $finalSessionType = null;
        } else {
            $finalSessionType = $this->session_type;
        }

        $newSession = Sessions::create([
            'event_id' => $this->event->id,
            'feature_id' => $this->feature_id,
            'session_date' => $this->session_date,
            'session_day' => $this->session_day,
            'session_type' => $finalSessionType,
            'title' => $this->title,
            'start_time' => $this->start_time,
            'end_time' => $finalEndTime,
            'datetime_added' => Carbon::now(),
        ]);


        foreach ($this->categoryChoices as $categoryChoice) {
            if ($categoryChoice['id'] == $this->feature_id) {
                $selectedCategory = $categoryChoice['value'];
            }
        }

        array_push($this->finalListOfSessions, [
            'id' => $newSession->id,
            'categoryName' => $selectedCategory,
            'session_date' => Carbon::parse($this->session_date)->format('F d, Y'),
            'session_day' => $this->session_day,
            'title' => $this->title,
            'timings' => $forArrayTimings,
            'is_active' => true,
            'datetime_added' => Carbon::parse(Carbon::now())->format('M j, Y g:i A'),
        ]);

        $this->resetAddSessionFields();

        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => 'Session added successfully!',
            'text' => ''
        ]);
    }


    public function updateSessionStatus($arrayIndex)
    {
        Sessions::where('id', $this->finalListOfSessions[$arrayIndex]['id'])->update([
            'is_active' => !$this->finalListOfSessions[$arrayIndex]['is_active'],
        ]);

        $this->finalListOfSessions[$arrayIndex]['is_active'] = !$this->finalListOfSessions[$arrayIndex]['is_active'];
    }

    public function deleteSessionConfirmation($index)
    {
        $this->activeDeleteIndex = $index;
        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure you want to delete?',
            'text' => "",
            'buttonConfirmText' => "Yes, delete it!",
            'livewireEmit' => "deleteSessionConfirmed",
        ]);
    }

    public function deleteSession()
    {
        $session = Sessions::where('id', $this->finalListOfSessions[$this->activeDeleteIndex]['id'])->first();
        if ($session) {
            $session->delete();
            unset($this->finalListOfSessions[$this->activeDeleteIndex]);
            $this->finalListOfSessions = array_values($this->finalListOfSessions);
        }

        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => 'Session deleted successfully!',
            'text' => ''
        ]);
    }


    // ADD SESSION DATES
    public function showAddSessionDate()
    {
        $sessionDates = SessionDates::where('event_id', $this->event->id)->get();

        if ($sessionDates->isNotEmpty()) {
            $this->session_dates = $sessionDates->map(function ($session_date) {
                return [
                    'id' => $session_date->id,
                    'session_date' => Carbon::parse($session_date->session_date)->format('F d, Y'),
                    'description' => $session_date->description,
                ];
            });
        }
        $this->addSessionDateForm = true;
    }

    public function resetAddSessionDateFields()
    {
        $this->addSessionDateForm = false;
        $this->add_session_date = null;
        $this->add_session_date_desc = null;
        $this->session_dates = [];
    }

    public function addSessionDate()
    {
        $this->validate([
            'add_session_date' => 'required',
        ]);

        $sessionDate = SessionDates::create([
            'event_id' => $this->event->id,
            'session_date' => $this->add_session_date,
            'description' => $this->add_session_date_desc,
        ]);

        $this->session_dates[] = [
            'id' => $sessionDate->id,
            'session_date' => Carbon::parse($this->add_session_date)->format('F d, Y'),
            'description' => $this->add_session_date_desc,
        ];

        $this->add_session_date = null;
        $this->add_session_date_desc = null;
    }

    public function deleteSessionDate($arrayIndex)
    {
        $sessionDate = SessionDates::find($this->session_dates[$arrayIndex]['id']);
        if ($sessionDate) {
            $sessionDate->delete();
        }
        unset($this->session_dates[$arrayIndex]);
    }



    // ADD SESSION DAYS
    // public function showAddSessionDay()
    // {
    //     $sessionDays = SessionDays::where('event_id', $this->event->id)->get();

    //     if ($sessionDays->isNotEmpty()) {
    //         $this->session_days = $sessionDays->map(function ($session_day) {
    //             return [
    //                 'id' => $session_day->id,
    //                 'session_day' => $session_day->session_day,
    //                 'description' => $session_day->description,
    //             ];
    //         });
    //     }
    //     $this->addSessionDayForm = true;
    // }

    // public function resetAddSessionDayFields()
    // {
    //     $this->addSessionDayForm = false;
    //     $this->add_session_day = null;
    //     $this->add_session_day_desc = null;
    //     $this->session_days = [];
    // }

    // public function addSessionDay()
    // {
    //     $this->validate([
    //         'add_session_day' => 'required',
    //     ]);

    //     $sessionDay = SessionDays::create([
    //         'event_id' => $this->event->id,
    //         'session_day' => $this->add_session_day,
    //         'description' => $this->add_session_day_desc,
    //     ]);

    //     $this->session_days[] = [
    //         'id' => $sessionDay->id,
    //         'session_day' => $this->add_session_day,
    //         'description' => $this->add_session_day_desc,
    //     ];

    //     $this->add_session_day = null;
    //     $this->add_session_day_desc = null;
    // }

    // public function deleteSessionDay($arrayIndex)
    // {
    //     $sessionDay = SessionDays::find($this->session_days[$arrayIndex]['id']);
    //     if ($sessionDay) {
    //         $sessionDay->delete();
    //     }
    //     unset($this->session_days[$arrayIndex]);
    // }

// SESSION DAYS
private function loadSessionDays()
{
    $this->session_days = SessionDays::where('event_id', $this->event->id)
        ->orderBy('id')
        ->get()
        ->map(function ($day) {
            return [
                'id' => $day->id,
                'session_day' => $day->session_day,
                'description' => $day->description,
            ];
        })
        ->toArray();
}

public function showAddSessionDay()
{
    $this->cancelEditSessionDay();
    $this->loadSessionDays();
    $this->addSessionDayForm = true;
}

public function resetAddSessionDayFields()
{
    $this->cancelEditSessionDay();
    $this->addSessionDayForm = false;
    $this->session_days = [];
}

public function cancelEditSessionDay()
{
    $this->editingSessionDayId = null;
    $this->add_session_day = null;
    $this->add_session_day_desc = null;
    $this->resetValidation();
}

private function validateSessionDayFields()
{
    $this->add_session_day = trim((string) $this->add_session_day);

    $this->validate([
        'add_session_day' => 'required|string|max:255',
        'add_session_day_desc' => 'nullable|string|max:255',
    ]);

    $query = SessionDays::where('event_id', $this->event->id)
        ->where('session_day', $this->add_session_day);

    if ($this->editingSessionDayId !== null) {
        $query->where('id', '!=', $this->editingSessionDayId);
    }

    if ($query->exists()) {
        $this->addError(
            'add_session_day',
            'This session day already exists for this event.'
        );

        return false;
    }

    return true;
}

public function addSessionDay()
{
    // Prevent accidentally adding a new day while editing.
    if ($this->editingSessionDayId !== null) {
        return;
    }

    if (!$this->validateSessionDayFields()) {
        return;
    }

    SessionDays::create([
        'event_id' => $this->event->id,
        'session_day' => $this->add_session_day,
        'description' => $this->add_session_day_desc ?: null,
    ]);

    $this->cancelEditSessionDay();
    $this->loadSessionDays();
}

public function editSessionDay($id)
{
    $day = SessionDays::where('event_id', $this->event->id)
        ->where('id', $id)
        ->firstOrFail();

    $this->resetValidation();

    $this->editingSessionDayId = $day->id;
    $this->add_session_day = $day->session_day;
    $this->add_session_day_desc = $day->description;
}

public function updateSessionDay()
{
    if ($this->editingSessionDayId === null) {
        return;
    }

    if (!$this->validateSessionDayFields()) {
        return;
    }

    $newName = $this->add_session_day;

    $oldName = DB::transaction(function () use ($newName) {
        $day = SessionDays::where('event_id', $this->event->id)
            ->where('id', $this->editingSessionDayId)
            ->lockForUpdate()
            ->firstOrFail();

        $oldName = $day->session_day;

        $day->session_day = $newName;
        $day->description = $this->add_session_day_desc ?: null;
        $day->save();

        // Sessions store the day label directly, so rename it there too.
        if ($oldName !== $newName) {
            Sessions::where('event_id', $this->event->id)
                ->where('session_day', $oldName)
                ->update([
                    'session_day' => $newName,
                ]);
        }

        return $oldName;
    });

    // Refresh the session labels already displayed on this page.
    foreach ($this->finalListOfSessions as $index => $session) {
        if ($session['session_day'] === $oldName) {
            $this->finalListOfSessions[$index]['session_day'] = $newName;
        }
    }

    // Refresh choices in case the Add Session form was already loaded.
    $this->sessionDayChoices = SessionDays::where(
        'event_id',
        $this->event->id
    )->orderBy('id')->pluck('session_day')->toArray();

    if ($this->session_day === $oldName) {
        $this->session_day = $newName;
    }

    $this->cancelEditSessionDay();
    $this->loadSessionDays();

    $this->dispatchBrowserEvent('swal:success', [
        'type' => 'success',
        'message' => 'Session day updated successfully!',
        'text' => '',
    ]);
}

public function deleteSessionDay($arrayIndex)
{
    if (!isset($this->session_days[$arrayIndex])) {
        return;
    }

    $id = $this->session_days[$arrayIndex]['id'];

    $day = SessionDays::where('event_id', $this->event->id)
        ->where('id', $id)
        ->firstOrFail();

    // Avoid deleting a day that existing sessions still use.
    $isUsed = Sessions::where('event_id', $this->event->id)
        ->where('session_day', $day->session_day)
        ->exists();

    if ($isUsed) {
        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'warning',
            'message' => 'This day is used by existing sessions.',
            'text' => 'Edit the day instead, or reassign its sessions before deleting.',
        ]);

        return;
    }

    $day->delete();

    if ((string) $this->editingSessionDayId === (string) $id) {
        $this->cancelEditSessionDay();
    }

    $this->loadSessionDays();
}

    // ADD SESSION TYPES
    public function showAddSessionType()
    {
        $sessionTypes = SessionTypes::where('event_id', $this->event->id)->get();

        if ($sessionTypes->isNotEmpty()) {
            $this->session_types = $sessionTypes->map(function ($session_type) {
                return [
                    'id' => $session_type->id,
                    'session_type' => $session_type->session_type,
                    'description' => $session_type->description,
                ];
            });
        }
        $this->addSessionTypeForm = true;
    }

    public function resetAddSessionTypeFields()
    {
        $this->addSessionTypeForm = false;
        $this->add_session_type = null;
        $this->add_session_type_desc = null;
        $this->session_types = [];
    }

    public function addSessionType()
    {
        $this->validate([
            'add_session_type' => 'required',
        ]);

        $sessionType = SessionTypes::create([
            'event_id' => $this->event->id,
            'session_type' => $this->add_session_type,
            'description' => $this->add_session_type_desc,
        ]);

        $this->session_types[] = [
            'id' => $sessionType->id,
            'session_type' => $this->add_session_type,
            'description' => $this->add_session_type_desc,
        ];

        $this->add_session_type = null;
        $this->add_session_type_desc = null;
    }

    public function deleteSessionType($arrayIndex)
    {
        $sessionType = SessionTypes::find($this->session_types[$arrayIndex]['id']);
        if ($sessionType) {
            $sessionType->delete();
        }
        unset($this->session_types[$arrayIndex]);
    }
}
