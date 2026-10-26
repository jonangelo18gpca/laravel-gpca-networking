<?php

namespace App\Http\Livewire;

use App\Models\Event as Events;
use App\Models\Notification as Notifications;
use Carbon\Carbon;
use Livewire\Component;

use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;


class NotificationList extends Component
{
    public $event;
    public $notificationTypeChoices = array();
    public $finalListOfNotifications = array();

    // ADD MRP
    public $type, $title, $subtitle, $message, $send_datetime;
    public $addNotificationForm;

    public $activeEditIndex, $editNotificationForm;

    // DELETE
    public $activeDeleteIndex;

    protected $listeners = ['addNotificationConfirmed' => 'addNotification', 'editNotificationConfirmed' => 'editNotification', 'deleteNotificationConfirmed' => 'deleteNotification'];

    public function mount($eventId, $eventCategory)
    {
        $this->event = Events::where('id', $eventId)->where('category', $eventCategory)->first();

        $notifications = Notifications::where('event_id', $eventId)->orderBy('send_datetime', 'ASC')->get();

        if ($notifications->isNotEmpty()) {
            foreach ($notifications as $notification) {
                array_push($this->finalListOfNotifications, [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'subtitle' => $notification->subtitle,
                    'message' => $notification->message,
                    'is_sent' => $notification->is_sent,
                    'send_date' => Carbon::parse($notification->send_datetime)->format('M j, Y'),
                    'send_time' => Carbon::parse($notification->send_datetime)->format('g:i A'),
                    'send_datetime' => $notification->send_datetime,
                ]);
            }
        }

        $this->notificationTypeChoices = config('app.notificationTypes');

        $this->addNotificationForm = false;
        $this->editNotificationForm = false;
    }
    public function render()
    {
        return view('livewire.event.notifications.notification-list');
    }

    public function showAddNotification()
    {
        $this->addNotificationForm = true;
    }

    public function addNotificationConfirmation()
    {
        $this->validate([
            'type' => 'required',
            'title' => 'required',
            'send_datetime' => 'required',
        ]);

        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure?',
            'text' => "",
            'buttonConfirmText' => "Yes, add it!",
            'livewireEmit' => "addNotificationConfirmed",
        ]);
    }

    public function resetAddNotificationFields()
    {
        $this->addNotificationForm = false;
        $this->type = null;
        $this->title = null;
        $this->subtitle = null;
        $this->message = null;
        $this->send_datetime = null;
    }

    public function addNotification()
    {
        $newNotification = Notifications::create([
            'event_id' => $this->event->id,
            'type' => $this->type,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'message' => $this->message,
            'send_datetime' => $this->send_datetime,
        ]);

        array_push($this->finalListOfNotifications, [
            'id' => $newNotification->id,
            'type' => $this->type,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'message' => $this->message,
            'is_sent' => false,
            'send_date' => Carbon::parse($this->send_datetime)->format('M j, Y'),
            'send_time' => Carbon::parse($this->send_datetime)->format('g:i A'),
            'send_datetime' => $this->send_datetime,
        ]);

        $this->resetAddNotificationFields();

        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => 'Notification added successfully!',
            'text' => ''
        ]);
    }


    // EDIT
    public function showEditNotification($index)
    {
        $this->activeEditIndex = $index;
        $this->type = $this->finalListOfNotifications[$this->activeEditIndex]['type'];
        $this->title = $this->finalListOfNotifications[$this->activeEditIndex]['title'];
        $this->subtitle = $this->finalListOfNotifications[$this->activeEditIndex]['subtitle'];
        $this->message = $this->finalListOfNotifications[$this->activeEditIndex]['message'];
        $this->send_datetime = $this->finalListOfNotifications[$this->activeEditIndex]['send_datetime'];
        $this->editNotificationForm = true;
    }

    public function editNotificationConfirmation()
    {
        $this->validate([
            'type' => 'required',
            'title' => 'required',
            'send_datetime' => 'required',
        ]);

        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure?',
            'text' => "",
            'buttonConfirmText' => "Yes, update it!",
            'livewireEmit' => "editNotificationConfirmed",
        ]);
    }

    public function editNotification()
    {
        Notifications::where('id', $this->finalListOfNotifications[$this->activeEditIndex]['id'])->update([
            'type' => $this->type,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'message' => $this->message,
            'send_datetime' => $this->send_datetime,
        ]);

        $this->finalListOfNotifications[$this->activeEditIndex]['type'] = $this->type;
        $this->finalListOfNotifications[$this->activeEditIndex]['title'] = $this->title;
        $this->finalListOfNotifications[$this->activeEditIndex]['subtitle'] = $this->subtitle;
        $this->finalListOfNotifications[$this->activeEditIndex]['message'] = $this->message;
        $this->finalListOfNotifications[$this->activeEditIndex]['send_date'] = Carbon::parse($this->send_datetime)->format('M j, Y');
        $this->finalListOfNotifications[$this->activeEditIndex]['send_time'] = Carbon::parse($this->send_datetime)->format('g:i A');
        $this->finalListOfNotifications[$this->activeEditIndex]['send_datetime'] = $this->send_datetime;

        $this->resetEditNotificationFields();

        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => 'Notification updated successfully!',
            'text' => ''
        ]);
    }


    public function resetEditNotificationFields()
    {
        $this->editNotificationForm = false;
        $this->type = null;
        $this->title = null;
        $this->subtitle = null;
        $this->message = null;
        $this->send_datetime = null;
    }


    // DELETE
    public function deleteNotificationConfirmation($index)
    {
        $this->activeDeleteIndex = $index;
        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure you want to delete?',
            'text' => "",
            'buttonConfirmText' => "Yes, delete it!",
            'livewireEmit' => "deleteNotificationConfirmed",
        ]);
    }

    public function deleteNotification()
    {
        $notification = Notifications::where('id', $this->finalListOfNotifications[$this->activeDeleteIndex]['id'])->first();

        if ($notification) {
            $notification->delete();

            unset($this->finalListOfNotifications[$this->activeDeleteIndex]);
            $this->finalListOfNotifications = array_values($this->finalListOfNotifications);
        }

        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => 'Notification deleted successfully!',
            'text' => ''
        ]);
    }


    use WithFileUploads;

    public $notificationImportFile;
    public $notificationImportResult;

    public function updatedNotificationImportFile()
    {
        $this->resetValidation('notificationImportFile');
        $this->notificationImportResult = null;
    }

    public function downloadNotificationTemplate()
    {
        return response()->streamDownload(function () {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM for Excel.
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'Date',
                'Time',
                'Type',
                'Title',
                'Subtitle',
                'Message',
            ]);

            fclose($file);
        }, 'notification-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function parseImportSchedule($date, $time)
    {
        $timezone = new \DateTimeZone(config('app.timezone', 'UTC'));

        $parsedDate = false;

        foreach (['!Y-m-d', '!d/m/Y'] as $format) {
            $candidate = \DateTimeImmutable::createFromFormat(
                $format,
                trim($date),
                $timezone
            );

            $errors = \DateTimeImmutable::getLastErrors();

            if (
                $candidate &&
                (
                    $errors === false ||
                    (
                        $errors['warning_count'] === 0 &&
                        $errors['error_count'] === 0
                    )
                )
            ) {
                $parsedDate = $candidate;
                break;
            }
        }

        if (!$parsedDate) {
            throw new \InvalidArgumentException(
                'Date must use YYYY-MM-DD or DD/MM/YYYY.'
            );
        }

        $time = strtoupper(
            preg_replace('/\s+/', ' ', trim($time))
        );

        $time = preg_replace('/\s*(AM|PM)$/', ' $1', $time);

        $hour = 0;
        $minute = 0;
        $second = 0;

        // Example: 2:30 PM or 02:30:00 PM.
        if (
            preg_match(
                '/^(\d{1,2}):(\d{2})(?::(\d{2}))? (AM|PM)$/',
                $time,
                $matches
            )
        ) {
            $hour = (int) $matches[1];
            $minute = (int) $matches[2];

            $second = isset($matches[3]) && $matches[3] !== ''
                ? (int) $matches[3]
                : 0;

            if ($hour < 1 || $hour > 12) {
                throw new \InvalidArgumentException(
                    'AM/PM hours must be between 1 and 12.'
                );
            }

            $hour = ($hour % 12) + (
                $matches[4] === 'PM' ? 12 : 0
            );
        }

        // Example: 14:30 or 14:30:00.
        elseif (
            preg_match(
                '/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/',
                $time,
                $matches
            )
        ) {
            $hour = (int) $matches[1];
            $minute = (int) $matches[2];

            $second = isset($matches[3])
                ? (int) $matches[3]
                : 0;
        }

        // Example: 1430.
        elseif (
            preg_match('/^(\d{2})(\d{2})$/', $time, $matches)
        ) {
            $hour = (int) $matches[1];
            $minute = (int) $matches[2];
        } else {
            throw new \InvalidArgumentException(
                'Time must look like 14:30, 1430, or 2:30 PM.'
            );
        }

        if ($hour > 23 || $minute > 59 || $second > 59) {
            throw new \InvalidArgumentException(
                'Invalid time. Hours must be 00–23 and minutes/seconds 00–59.'
            );
        }

        return $parsedDate
            ->setTime($hour, $minute, $second)
            ->format('Y-m-d H:i:s');
    }

    public function importNotifications()
    {
        $this->notificationImportResult = null;
        $this->resetValidation('notificationImportFile');

        $this->validate([
            'notificationImportFile' => 'required|file|max:2048',
        ]);

        $originalName = trim(
            $this->notificationImportFile->getClientOriginalName()
        );

        $extension = strtolower(
            pathinfo($originalName, PATHINFO_EXTENSION)
        );

        if ($extension !== 'csv') {
            $this->addError(
                'notificationImportFile',
                'Please upload a CSV file. Selected file: ' . $originalName
            );

            return;
        }

        $handle = fopen(
            $this->notificationImportFile->getRealPath(),
            'r'
        );

        if ($handle === false) {
            $this->addError(
                'notificationImportFile',
                'Unable to open the uploaded file.'
            );

            return;
        }

        $rows = [];
        $recordNumber = 1;

        try {
            $headers = fgetcsv($handle);

            if ($headers === false) {
                throw ValidationException::withMessages([
                    'notificationImportFile' => 'The CSV file is empty.',
                ]);
            }

            $headers = array_map(function ($value) {
                return strtolower(
                    trim(
                        preg_replace(
                            '/^\xEF\xBB\xBF/',
                            '',
                            (string) $value
                        )
                    )
                );
            }, $headers);

            if ($headers !== [
                'date',
                'time',
                'type',
                'title',
                'subtitle',
                'message',
            ]) {
                throw ValidationException::withMessages([
                    'notificationImportFile' =>
                    'Columns must be Date, Time, Type, Title, Subtitle, Message, in that order.',
                ]);
            }

            while (($values = fgetcsv($handle)) !== false) {
                $recordNumber++;

                $values = array_map(function ($value) {
                    return trim((string) $value);
                }, $values);

                // Ignore blank records.
                if (
                    count(
                        array_filter($values, function ($value) {
                            return $value !== '';
                        })
                    ) === 0
                ) {
                    continue;
                }

                if (count($values) !== 6) {
                    throw ValidationException::withMessages([
                        'notificationImportFile' =>
                        "CSV record {$recordNumber}: expected exactly 6 columns.",
                    ]);
                }

                if (count($rows) >= 500) {
                    throw ValidationException::withMessages([
                        'notificationImportFile' =>
                        'Maximum 500 notifications per upload.',
                    ]);
                }

                $row = array_combine($headers, $values);

                $row['type'] = strtoupper($row['type']);

                $validator = Validator::make($row, [
                    'date' => 'required|string',
                    'time' => 'required|string',
                    'type' => [
                        'required',
                        Rule::in(config('app.notificationTypes', [])),
                    ],
                    'title' => 'required|string|max:255',
                    'subtitle' => 'nullable|string|max:255',
                    'message' => 'nullable|string',
                ]);

                if ($validator->fails()) {
                    throw ValidationException::withMessages([
                        'notificationImportFile' =>
                        "CSV record {$recordNumber}: " .
                            implode(' ', $validator->errors()->all()),
                    ]);
                }

                try {
                    $schedule = $this->parseImportSchedule(
                        $row['date'],
                        $row['time']
                    );
                } catch (\InvalidArgumentException $e) {
                    throw ValidationException::withMessages([
                        'notificationImportFile' =>
                        "CSV record {$recordNumber}: " .
                            $e->getMessage(),
                    ]);
                }

                if (
                    Carbon::parse(
                        $schedule,
                        config('app.timezone')
                    )->lessThanOrEqualTo(Carbon::now())
                ) {
                    throw ValidationException::withMessages([
                        'notificationImportFile' =>
                        "CSV record {$recordNumber}: schedule must be in the future (" .
                            config('app.timezone') . ').',
                    ]);
                }

                $rows[] = [
                    'event_id' => $this->event->id,
                    'type' => $row['type'],
                    'title' => $row['title'],
                    'subtitle' => $row['subtitle'] !== ''
                        ? $row['subtitle']
                        : null,
                    'message' => $row['message'] !== ''
                        ? $row['message']
                        : null,
                    'send_datetime' => $schedule,
                    'is_sent' => false,
                ];
            }
        } finally {
            fclose($handle);
        }

        if (empty($rows)) {
            $this->addError(
                'notificationImportFile',
                'Add at least one notification below the headings.'
            );

            return;
        }

        try {
            DB::transaction(function () use ($rows) {
                foreach ($rows as $row) {
                    Notifications::create($row);
                }
            });
        } catch (\Throwable $e) {
            report($e);

            $this->addError(
                'notificationImportFile',
                'Import failed. No notifications saved. Check the Laravel log.'
            );

            return;
        }

        $this->finalListOfNotifications = Notifications::where(
            'event_id',
            $this->event->id
        )
            ->orderBy('send_datetime', 'ASC')
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'subtitle' => $notification->subtitle,
                    'message' => $notification->message,
                    'is_sent' => $notification->is_sent,
                    'send_date' => Carbon::parse(
                        $notification->send_datetime
                    )->format('M j, Y'),
                    'send_time' => Carbon::parse(
                        $notification->send_datetime
                    )->format('g:i A'),
                    'send_datetime' => $notification->send_datetime,
                ];
            })
            ->toArray();

        $this->reset('notificationImportFile');
        $this->resetValidation('notificationImportFile');

        $this->notificationImportResult =
            count($rows) . ' notifications imported successfully.';
    }
}
