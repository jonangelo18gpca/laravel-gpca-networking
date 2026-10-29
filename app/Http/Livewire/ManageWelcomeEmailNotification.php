<?php

namespace App\Http\Livewire;

use App\Mail\NewAttendee;
use App\Models\Attendee as Attendees;
use App\Models\Event as Events;
use App\Models\WelcomeEmailNotifActivity;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class ManageWelcomeEmailNotification extends Component
{
    public $event;
    public $finalListOfAttendees = array();
    public $selectedAttendees = [];
    public $sendableAttendees = 0;
    public $activeSelectedIndex = null;


    public $filterWelcomeEmailTotalSent = 0;
    public $select50AttendeesMessage = null;


    protected $listeners = ['sendWelcomeEmailNotificationConfirmed' => 'sendWelcomeEmailNotification', 'sendWelcomeEmailNotificationBulkConfirmed' => 'sendWelcomeEmailNotificationBulk'];

    public function mount($eventId, $eventCategory)
    {
        $this->event = Events::where('id', $eventId)->where('category', $eventCategory)->first();


        $attendees = Attendees::with(['passwordResets', 'logins', 'welcomeEmailNotifications'])->where('event_id', $eventId)->get();
        if ($attendees->isNotEmpty()) {
            foreach ($attendees as $attendee) {
                $is_password_resetted = true;
                $is_logged_in_already = true;
                $totatWelcomeEmailNotificationSent = 0;
                $lasttWelcomeEmailNotificationSent = "N/A";

                if ($attendee->passwordResets->isEmpty()) {
                    $is_password_resetted = false;
                    $this->sendableAttendees++;
                }

                if ($attendee->logins->isEmpty()) {
                    $is_logged_in_already = false;
                }

                if ($attendee->welcomeEmailNotifications->isNotEmpty()) {
                    foreach ($attendee->welcomeEmailNotifications as $welcomeEmailNotification) {
                        $totatWelcomeEmailNotificationSent++;
                        $lasttWelcomeEmailNotificationSent = $welcomeEmailNotification->sent_datetime;
                    }
                }

                array_push($this->finalListOfAttendees, [
                    'id' => $attendee->id,
                    'badge_number' => $attendee->badge_number,
                    'username' => $attendee->username,
                    'name' => $attendee->first_name . ' ' . $attendee->last_name,
                    'first_name' => $attendee->first_name,
                    'last_name' => $attendee->last_name,
                    'job_title' => $attendee->job_title,
                    'email_address' => $attendee->email_address,
                    'company_name' => $attendee->company_name,
                    'is_password_resetted' => $is_password_resetted,
                    'is_logged_in_already' => $is_logged_in_already,
                    'totatWelcomeEmailNotificationSent' => $totatWelcomeEmailNotificationSent,
                    'lasttWelcomeEmailNotificationSent' => $lasttWelcomeEmailNotificationSent,
                    'joined_date_time' => $attendee->joined_date_time,
                ]);
            }
        }
    }
    public function render()
    {
        return view('livewire.event.attendees.manage-welcome-email-notification');
    }

    public function selectAllAttendee()
    {
        $this->selectedAttendees = [];
        foreach ($this->finalListOfAttendees as $index =>  $attendee) {
            if (!$attendee['is_password_resetted']) {
                $this->selectedAttendees[] = $index;
            }
        }
    }

    public function unselectAllAttendee()
    {
        $this->selectedAttendees = [];
    }

    public function sendWelcomeEmailConfirmation($index)
    {
        $this->activeSelectedIndex = $index;
        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure?',
            'text' => "",
            'buttonConfirmText' => "Yes, send it!",
            'livewireEmit' => "sendWelcomeEmailNotificationConfirmed",
        ]);
    }

    public function sendWelcomeEmailNotification()
    {
        $currentAttendee = $this->finalListOfAttendees[$this->activeSelectedIndex];
        $eventFormattedDate = Carbon::parse($this->event->event_start_date)->format('d') . '-' . Carbon::parse($this->event->event_end_date)->format('d M Y');

        $details = [
            'subject' => 'Welcome to ' . $this->event->full_name . ' - Your Access Details for GPCA Networking',
            'eventCategory' => $this->event->category,
            'eventYear' => $this->event->year,

            'name' => $currentAttendee['first_name'] . ' ' . $currentAttendee['last_name'],
            'eventName' => $this->event->full_name,
            'eventDate' => $eventFormattedDate,
            'eventLocation' => $this->event->location,
            'username' => $currentAttendee['username'],
            'email_address' => $currentAttendee['email_address'],
        ];

        $is_sent_successfully = false;
        $error = '';
        try {
            Mail::to($currentAttendee['email_address'])->send(new NewAttendee($details));
            $is_sent_successfully = true;
        } catch (\Throwable $th) {
            $error = $th->getMessage();
            $is_sent_successfully = false;
        }


        if ($is_sent_successfully) {
            WelcomeEmailNotifActivity::create([
                'event_id' => $this->event->id,
                'attendee_id' => $currentAttendee['id'],
                'sent_datetime' => Carbon::now(),
            ]);

            $this->finalListOfAttendees[$this->activeSelectedIndex]['totatWelcomeEmailNotificationSent'] += 1;
            $this->finalListOfAttendees[$this->activeSelectedIndex]['lasttWelcomeEmailNotificationSent'] = Carbon::now();

            $this->dispatchBrowserEvent('swal:success', [
                'type' => 'success',
                'message' => 'Welcome email sent successfully!',
                'text' => ''
            ]);
        } else {
            $this->dispatchBrowserEvent('swal:success', [
                'type' => 'error',
                'message' => 'An error occured while sending the email!',
                'text' => $error,
            ]);
        }

        $this->activeSelectedIndex = null;
    }

    public function sendWelcomeEmailConfirmationBulkConfirmation()
    {
        $text = "This will send to " . count($this->selectedAttendees) . ' attendees!';
        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure?',
            'text' => $text,
            'buttonConfirmText' => "Yes, send it!",
            'livewireEmit' => "sendWelcomeEmailNotificationBulkConfirmed",
        ]);
    }

    public function sendWelcomeEmailNotificationBulk()
    {
        $eventFormattedDate = Carbon::parse($this->event->event_start_date)->format('d') . '-' . Carbon::parse($this->event->event_end_date)->format('d M Y');
        $error = 0;

        foreach ($this->selectedAttendees as $selectedAttendeeIndex) {
            $currentAttendee = $this->finalListOfAttendees[$selectedAttendeeIndex];
            $details = [
                'subject' => 'Welcome to ' . $this->event->full_name . ' - Your Access Details for GPCA Networking',
                'eventCategory' => $this->event->category,
                'eventYear' => $this->event->year,

                'name' => $currentAttendee['first_name'] . ' ' . $currentAttendee['last_name'],
                'eventName' => $this->event->full_name,
                'eventDate' => $eventFormattedDate,
                'eventLocation' => $this->event->location,
                'username' => $currentAttendee['username'],
                'email_address' => $currentAttendee['email_address'],
            ];

            $is_sent_successfully = false;
            try {
                Mail::to($currentAttendee['email_address'])->send(new NewAttendee($details));
                $is_sent_successfully = true;
            } catch (\Throwable $th) {
                $error++;
                $is_sent_successfully = false;
            }


            if ($is_sent_successfully) {
                WelcomeEmailNotifActivity::create([
                    'event_id' => $this->event->id,
                    'attendee_id' => $currentAttendee['id'],
                    'sent_datetime' => Carbon::now(),
                ]);

                $this->finalListOfAttendees[$selectedAttendeeIndex]['totatWelcomeEmailNotificationSent'] += 1;
                $this->finalListOfAttendees[$selectedAttendeeIndex]['lasttWelcomeEmailNotificationSent'] = Carbon::now();
            }
        }

        if ($error == count($this->selectedAttendees)) {
            $this->dispatchBrowserEvent('swal:success', [
                'type' => 'error',
                'message' => 'Welcome email did not send to all selected attendees!',
                'text' => "Please check their email address!",
            ]);
        } else if ($error == 0) {
            $this->dispatchBrowserEvent('swal:success', [
                'type' => 'success',
                'message' => 'Welcome email sent successfully!',
                'text' => ''
            ]);
        } else {
            $text = $error . ' attendees will not receive welcome email due to incorrect email address!';
            $this->dispatchBrowserEvent('swal:success', [
                'type' => 'success',
                'message' => 'Welcome email sent successfully!',
                'text' => $text
            ]);
        }

        // $this->selectedAttendees = [];
    }

    public function select50AttendeesBySentCount()
{
    $this->select50AttendeesMessage = null;

    $this->validate([
        'filterWelcomeEmailTotalSent' => 'required|integer|min:0',
    ], [
        'filterWelcomeEmailTotalSent.required' =>
            'Enter the total sent count.',
        'filterWelcomeEmailTotalSent.integer' =>
            'Total sent must be a whole number.',
        'filterWelcomeEmailTotalSent.min' =>
            'Total sent cannot be negative.',
    ]);

    $targetSentCount = (int) $this->filterWelcomeEmailTotalSent;

    $selected = [];

    foreach ($this->finalListOfAttendees as $index => $attendee) {
        // Only attendees who have never logged in.
        if ($attendee['is_logged_in_already']) {
            continue;
        }

        // Preserve the existing checkbox/send eligibility rule.
        if ($attendee['is_password_resetted']) {
            continue;
        }

        // Match the exact total sent count.
        if (
            (int) $attendee['totatWelcomeEmailNotificationSent']
            !== $targetSentCount
        ) {
            continue;
        }

        // Keep indexes because the existing bulk sender uses indexes.
        $selected[] = (string) $index;

        if (count($selected) >= 50) {
            break;
        }
    }

    // Replace the current selection, rather than adding to it.
    $this->selectedAttendees = $selected;

    $count = count($selected);

    $this->select50AttendeesMessage = $count > 0
        ? "{$count} attendees selected: not logged in, password not changed, total sent = {$targetSentCount}."
        : "No eligible attendees found with total sent = {$targetSentCount}.";
}
}
