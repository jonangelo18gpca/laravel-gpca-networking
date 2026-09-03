<?php

namespace App\Http\Controllers;

use App\Enums\MeetingReceiverType;
use App\Enums\MeetingStatus;
use App\Enums\NotificationTypes;
use App\Mail\AttendeeMeetingAcceptedMail;
use App\Mail\AttendeeMeetingCancelledMail;
use App\Mail\AttendeeMeetingDeclinedMail;
use App\Mail\AttendeeMeetingRequestMail;
use App\Mail\AttendeeMeetingRescheduledMail;
use App\Models\Attendee;
use App\Models\AttendeeMeeting;
use App\Models\AttendeeNotification;
use App\Models\Event;
use App\Models\Exhibitor;
use App\Models\MeetingRoomPartner;
use App\Models\Sponsor;
use App\Traits\HttpResponses;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use PDO;
use Illuminate\Support\Str;
use App\Enums\MeetingRespondTokenStatus;

class MeetingController extends Controller
{
    use HttpResponses;

    private function hasOverlapForParticipant(
        int $eventId,
        string $dateISO,
        string $startHHmm,
        string $endHHmm,
        ?int $excludeMeetingId,
        string $participantType,
        int $participantId
    ): bool {
        $q = AttendeeMeeting::query()
            ->where('event_id', $eventId)
            ->whereDate('meeting_date', $dateISO)
            ->where('meeting_status', MeetingStatus::ACCEPTED->value)
            ->when($excludeMeetingId, fn($qq) => $qq->where('id', '!=', $excludeMeetingId));

        if ($participantType === MeetingReceiverType::ATTENDEE->value) {
            $q->where(function ($w) use ($participantId) {
                $w->where('attendee_id', $participantId)
                    ->orWhere(function ($ww) use ($participantId) {
                        $ww->where('receiver_id', $participantId)
                            ->where('receiver_type', MeetingReceiverType::ATTENDEE->value);
                    });
            });
        } else {
            $q->where('receiver_id', $participantId)
                ->where('receiver_type', $participantType);
        }

        return $q
            ->whereRaw('TIME(?) < meeting_end_time', [$startHHmm])
            ->whereRaw('TIME(?) > meeting_start_time', [$endHHmm])
            ->exists();
    }


    public function apiAttendeeMeetingMetadata($apiCode, $eventCategory, $eventId, $attendeeId, $receiverId, $receiverType, $excludeMeetingId = null)
    {
        try {
            // if ($eventCategory !== 'AF') {
            //     return $this->error([
            //         'locations' => [],
            //         'dates'     => [],
            //     ], 'No date and locations available at the moment', 404);
            // }

            $rawDates = [
                '2026-09-16',
                '2026-09-17',

            ];

            $blockingStatuses = [MeetingStatus::ACCEPTED->value];
            $dateAndTime = [];

            $excludeMeetingId = is_numeric($excludeMeetingId) ? (int) $excludeMeetingId : null;

            foreach ($rawDates as $isoDate) {
                $friendlyDate = Carbon::parse($isoDate)->format('F d, Y');

                // -------------------------------
                // SELF BUSY (requester attendee)
                // busy if the requester is either the creator OR the receiver as an attendee
                // -------------------------------
                $selfBusy = AttendeeMeeting::query()
                    ->where('event_id', $eventId)
                    ->when($excludeMeetingId, fn($q) => $q->where('id', '!=', $excludeMeetingId))
                    ->whereDate('meeting_date', $isoDate)
                    ->whereIn('meeting_status', $blockingStatuses)
                    ->where(function ($q) use ($attendeeId) {
                        $q->where('attendee_id', $attendeeId)
                            ->orWhere(function ($qq) use ($attendeeId) {
                                $qq->where('receiver_id', $attendeeId)
                                    ->where('receiver_type', MeetingReceiverType::ATTENDEE->value);
                            });
                    })
                    ->get(['meeting_start_time', 'meeting_end_time']);

                // -------------------------------
                // OTHER BUSY (receiver)
                // If receiver is an attendee -> they can appear as attendee_id or receiver_id('attendee')
                // If receiver is exhibitor/sponsor/mrp -> they NEVER appear as attendee_id; only as receiver_id with their receiver_type
                // -------------------------------
                $otherBusyQuery = AttendeeMeeting::query()
                    ->where('event_id', $eventId)
                    ->when($excludeMeetingId, fn($q) => $q->where('id', '!=', $excludeMeetingId))
                    ->whereDate('meeting_date', $isoDate)
                    ->whereIn('meeting_status', $blockingStatuses);

                if ($receiverType === MeetingReceiverType::ATTENDEE->value) {
                    $otherBusyQuery->where(function ($q) use ($receiverId) {
                        $q->where('attendee_id', $receiverId)
                            ->orWhere(function ($qq) use ($receiverId) {
                                $qq->where('receiver_id', $receiverId)
                                    ->where('receiver_type', MeetingReceiverType::ATTENDEE->value);
                            });
                    });
                } else {
                    $otherBusyQuery->where('receiver_id', $receiverId)
                        ->where('receiver_type', $receiverType);
                }
                $otherBusy = $otherBusyQuery->get(['meeting_start_time', 'meeting_end_time']);

                $timings = [];

                foreach ($otherBusy as $m) {
                    $timings[] = [
                        'start' => Carbon::parse($m->meeting_start_time)->format('H:i'),
                        'end' => Carbon::parse($m->meeting_end_time)->format('H:i'),
                        'not_available' => 'receiver',
                    ];
                }

                foreach ($selfBusy as $m) {
                    $timings[] = [
                        'start' => Carbon::parse($m->meeting_start_time)->format('H:i'),
                        'end' => Carbon::parse($m->meeting_end_time)->format('H:i'),
                        'not_available' => 'requester',
                    ];
                }

                $dateAndTime[] = [
                    'date_time' => [
                        'date' => $friendlyDate,
                        'busy_intervals' => [
                            'timings' => $timings,
                        ],
                    ],
                ];
            }

            Log::info($dateAndTime);

            $locations = [
                "Company Stand",
                "Company Meeting room ",
                "GPCA Stand",
                "Registration Area",
                // "Plenary Area",
                // "Alberta Networking Lounge",
            ];

            return $this->success([
                "locations" => $locations,
                "date_and_time" => $dateAndTime,
            ], "Meeting metadata retrieved successfully", 200);
        } catch (Exception $e) {
            Log::info($e);

            return $this->error(null, "Error getting meeting metada", 500);
        }
    }

    public function apiAttendeeMeetings($apiCode, $eventCategory, $eventId, $attendeeId)
    {
        try {
            $upcomingMeetings = [];
            $pendingMeetings = [];
            $declinedCancelledMeetings = [];
            $pastMeetings = [];
            $incomingRequests = [];

            $attendeeMeetings = AttendeeMeeting::where('event_id', $eventId)
                ->where(function ($query) use ($attendeeId) {
                    $query->where('attendee_id', $attendeeId)
                        ->orWhere(function ($q) use ($attendeeId) {
                            $q->where('receiver_id', $attendeeId)
                                ->where('receiver_type', MeetingReceiverType::ATTENDEE->value);
                        });
                })
                ->with(['requester'])
                ->get();


            if ($attendeeMeetings->isEmpty()) {
                return $this->error(null, "No meetings found for the attendee.", 404);
            }

            foreach ($attendeeMeetings as $meeting) {
                $start = Carbon::createFromFormat('Y-m-d H:i:s', $meeting->meeting_date . ' ' . $meeting->meeting_start_time);
                $end   = Carbon::createFromFormat('Y-m-d H:i:s', $meeting->meeting_date . ' ' . $meeting->meeting_end_time);

                $direction = $meeting->attendee_id == $attendeeId ? 'outgoing' : 'incoming';

                if ($meeting->receiver_type !== MeetingReceiverType::ATTENDEE->value) {
                    if ($meeting->receiver_type === MeetingReceiverType::EXHIBITOR->value) {
                        $ex = Exhibitor::select('contact_person_name', 'name')->find($meeting->receiver_id);
                        $companyName = $ex?->name ?? '';
                        $fullName    = $ex?->contact_person_name ?? '';
                    } elseif ($meeting->receiver_type === MeetingReceiverType::SPONSOR->value) {
                        $sp = Sponsor::select('contact_person_name', 'name')->find($meeting->receiver_id);
                        $companyName = $sp?->name ?? '';
                        $fullName    = $sp?->contact_person_name ?? '';
                    } else { // MEETING_ROOM_PARTNER
                        $mrp = MeetingRoomPartner::select('contact_person_name', 'name')->find($meeting->receiver_id);
                        $companyName = $mrp?->name ?? '';
                        $fullName    = $mrp?->contact_person_name ?? '';
                    }
                } else {
                    if ($direction == "incoming") {
                        $fullName = trim(implode(' ', array_filter([
                            $meeting->requester->salutation ?? null,
                            $meeting->requester->first_name ?? null,
                            $meeting->requester->middle_name ?? null,
                            $meeting->requester->last_name ?? null,
                        ])));
                        $companyName = $meeting->requester->company_name ?? '';
                    } else {
                        $atnd = Attendee::select('salutation', 'first_name', 'middle_name', 'last_name', 'company_name')->find($meeting->receiver_id);
                        $fullName = trim(implode(' ', array_filter([
                            $atnd->salutation ?? null,
                            $atnd->first_name ?? null,
                            $atnd->middle_name ?? null,
                            $atnd->last_name ?? null,
                        ])));
                        $companyName = $atnd->company_name ?? '';
                    }
                }

                $item = [
                    'id'         => $meeting->id,
                    'title'      => $meeting->meeting_title,
                    'status'     => $meeting->meeting_status,
                    'date'       => Carbon::parse($meeting->meeting_date)->format('F d, Y'),
                    'start_time' => Carbon::parse($meeting->meeting_start_time)->format('g:i A'),
                    'end_time'   => Carbon::parse($meeting->meeting_end_time)->format('g:i A'),
                    'location'   => $meeting->meeting_location,
                    'direction'  => $direction,
                    'opposite'   => [
                        'full_name' => $fullName,
                        'company'   => $companyName,
                    ],
                ];

                // 1) Incoming requests (pending + future)
                if (
                    $direction === 'incoming'
                    && $meeting->meeting_status === MeetingStatus::PENDING->value
                    && $start->isFuture()
                ) {
                    $incomingRequests[] = $item;
                    continue;
                }

                // 2) Expired → always Past (even if end time is in future because token expired early)
                if ($meeting->meeting_status === MeetingStatus::EXPIRED->value) {
                    $pastMeetings[] = $item;
                    continue;
                }

                // 3) Upcoming (accepted + future)
                if ($meeting->meeting_status === MeetingStatus::ACCEPTED->value && $start->isFuture()) {
                    $upcomingMeetings[] = $item;
                    continue;
                }

                // 4) Pending (future)
                if ($meeting->meeting_status === MeetingStatus::PENDING->value && $start->isFuture()) {
                    $pendingMeetings[] = $item;
                    continue;
                }

                // 5) Declined/Cancelled tab
                if (in_array($meeting->meeting_status, [
                    MeetingStatus::DECLINED->value,
                    MeetingStatus::CANCELLED->value,
                ])) {
                    $declinedCancelledMeetings[] = $item;
                    continue;
                }

                // 6) Past: accepted & ended (use END time)
                if ($meeting->meeting_status === MeetingStatus::ACCEPTED->value && $end->isPast()) {
                    $pastMeetings[] = $item;
                    continue;
                }
            }

            return $this->success([
                'upcoming' => $upcomingMeetings,
                'pending' => $pendingMeetings,
                'declined_cancelled' => $declinedCancelledMeetings,
                'past' => $pastMeetings,
                'incoming' => $incomingRequests,
            ], "Meetings grouped by status retrieved successfully", 200);
        } catch (\Exception $e) {
            return $this->error($e, "An error occurred while grouping the attendee meetings", 500);
        }
    }

    public function apiAttendeeMeetingDetails($apiCode, $eventCategory, $eventId, $attendeeId, $meetingId)
    {
        try {
            $meeting = AttendeeMeeting::with('requester')->where('id', $meetingId)->where('event_id', $eventId)->first();

            if (!$meeting) {
                return $this->error(null, "Meeting doesn't exist", 404);
            }

            $direction = $meeting->attendee_id == $attendeeId ? 'outgoing' : 'incoming';

            if ($direction === "incoming") {
                //INCOMING
                $fullName = trim(implode(' ', array_filter([
                    $meeting->requester->salutation,
                    $meeting->requester->first_name,
                    $meeting->requester->middle_name,
                    $meeting->requester->last_name
                ])));
                $companyName = $meeting->requester->company_name;
                $photo = $meeting->requester->pfp->file_url ?? "https://upload.wikimedia.org/wikipedia/commons/8/89/Portrait_Placeholder.png";
                $jobTitle = $meeting->requester->job_title;
                $oppositeId = $meeting->requester->id;
            } else {
                //OUTGOING
                if ($meeting->receiver_type === MeetingReceiverType::EXHIBITOR->value) {
                    $exhibitor = Exhibitor::with('logo')->select('contact_person_name', 'name', 'logo_media_id')->where('id', $meeting->receiver_id)->first();
                    $companyName = $exhibitor->name;
                    $fullName = $exhibitor->contact_person_name;
                    $photo = $exhibitor->logo->file_url ?? "https://upload.wikimedia.org/wikipedia/commons/8/89/Portrait_Placeholder.png";
                    $jobTitle = MeetingReceiverType::EXHIBITOR->value;
                } else if ($meeting->receiver_type === MeetingReceiverType::SPONSOR->value) {
                    $sponsor = Sponsor::with('logo')->select('contact_person_name', 'name', 'logo_media_id')->where('id', $meeting->receiver_id)->first();
                    $companyName = $sponsor->name;
                    $fullName = $sponsor->contact_person_name;
                    $photo = $sponsor->logo->file_url ?? "https://upload.wikimedia.org/wikipedia/commons/8/89/Portrait_Placeholder.png";
                    $jobTitle = MeetingReceiverType::SPONSOR->value;
                } else if ($meeting->receiver_type === MeetingReceiverType::MEETING_ROOM_PARTNER->value) {
                    $partner = MeetingRoomPartner::with('logo')->select('contact_person_name', 'name', 'logo_media_id')->where('id', $meeting->receiver_id)->first();
                    $companyName = $partner->name;
                    $fullName = $partner->contact_person_name;
                    $photo = $partner->logo->file_url ?? "https://upload.wikimedia.org/wikipedia/commons/8/89/Portrait_Placeholder.png";
                    $jobTitle = MeetingReceiverType::MEETING_ROOM_PARTNER->value;
                } else {
                    //ATTENDEE
                    $attendee = Attendee::with('pfp')->where('id', $meeting->receiver_id)->first();
                    $fullName = trim(implode(' ', array_filter([
                        $attendee->salutation,
                        $attendee->first_name,
                        $attendee->middle_name,
                        $attendee->last_name
                    ])));
                    $companyName = $attendee->company_name;
                    $photo = $attendee->pfp?->file_url ?? "https://upload.wikimedia.org/wikipedia/commons/8/89/Portrait_Placeholder.png";
                    $jobTitle = $attendee->job_title;
                }
                $oppositeId = $meeting->receiver_id;
            }

            $parentMeetingDetails = [];

            if ($meeting->parent_meeting_id != null) {
                $parentMeeting = AttendeeMeeting::where('id', $meeting->parent_meeting_id)->first();
                if ($parentMeeting) {
                    $parentMeetingDetails = [
                        'id' => $parentMeeting->id,
                        'meeting_title' => $parentMeeting->meeting_title,
                        'meeting_date' => Carbon::parse($parentMeeting->meeting_date)->format('F d, Y'),
                        'meeting_start_time' => Carbon::parse($parentMeeting->meeting_start_time)->format('g:i A'),
                        'meeting_end_time' => Carbon::parse($parentMeeting->meeting_end_time)->format('g:i A'),
                        'meeting_location' => $parentMeeting->meeting_location,
                    ];
                }
            }

            $meeting = [
                'id' => $meeting->id,

                'meeting_status' => $meeting->meeting_status,
                'meeting_title' => $meeting->meeting_title,
                'meeting_date' => Carbon::parse($meeting->meeting_date)->format('F d, Y'),
                'meeting_start_time' => Carbon::parse($meeting->meeting_start_time)->format('g:i A'),
                'meeting_end_time' => Carbon::parse($meeting->meeting_end_time)->format('g:i A'),

                'meeting_location' => $meeting->meeting_location,
                'meeting_notes' => $meeting->meeting_notes,

                'direction' => $direction,
                'opposite' => [
                    'id' => $oppositeId,
                    'type' => $meeting->receiver_type,
                    'full_name'  => $fullName,
                    'companyName' => $companyName,
                    'photo' => $photo,
                    'jobTitle' => $jobTitle,
                ],

                'accepted_datetime' => $meeting->accepted_datetime ?? null,
                'accepted_reason' => $meeting->accepted_reason ?? null,

                'declined_datetime' => $meeting->declined_datetime ?? null,
                'declined_reason' => $meeting->declined_reason ?? null,

                'cancelled_datetime' => $meeting->cancelled_datetime ?? null,
                'cancelled_reason' => $meeting->cancelled_reason ?? null,

                'is_reschedule' => (bool) $meeting->is_reschedule,
                'parent_meeting' => $parentMeetingDetails,
            ];
            Log::info($meeting);
            return $this->success($meeting, "Meeting retrieved successfully", 200);
        } catch (\Exception $e) {
            return $this->error($e, "An error occurred while getting the meeting details /n $e", 500);
        }
    }


    public function apiAttendeeAddMeeting(Request $request, $apiCode, $eventCategory, $eventId, $attendeeId)
    {
        $validator = Validator::make($request->all(), [
            'attendee_id' => 'required|exists:attendees,id',
            'receiver_id' => 'required',
            'receiver_type' => 'required',
            'meeting_title' => 'required|string|max:255',
            'meeting_date' => 'required',
            'meeting_start_time' => 'required',
            'meeting_end_time' => 'required',
            'meeting_location' => 'required',
            'meeting_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorValidation($validator->errors());
        }

        $event = Event::where('id', $eventId)->first();
        $requester = Attendee::with('deviceTokens')->where('id', $request->attendee_id)->first();
        if ($request->receiver_type ===  MeetingReceiverType::ATTENDEE->value) {
            $receiver = Attendee::with('deviceTokens')->where('id', $request->receiver_id)->first();
            $receiverName = $receiver->first_name;
        } else if ($request->receiver_type === MeetingReceiverType::EXHIBITOR->value) {
            $receiver = Exhibitor::where('id', $request->receiver_id)->first();
            $receiverName = $receiver->contact_person_name;
        } else if ($request->receiver_type === MeetingReceiverType::SPONSOR->value) {
            $receiver = Sponsor::where('id', $request->receiver_id)->first();
            $receiverName = $receiver->contact_person_name;
        } else if ($request->receiver_type === MeetingReceiverType::MEETING_ROOM_PARTNER->value) {
            $receiver = MeetingRoomPartner::where('id', $request->receiver_id)->first();
            $receiverName = $receiver->contact_person_name;
        } else {
            return $this->error(null, "Invalid receiver type", 400);
        }

        $isNonAttendee = $request->receiver_type !== MeetingReceiverType::ATTENDEE->value;
        $rawToken = $isNonAttendee ? Str::random(64) : null;
        $tokenExpiresAt = $isNonAttendee ? now()->addDays(7) : null;

        try {
            $meetingExist = AttendeeMeeting::where('event_id', $eventId)
                ->where('attendee_id', $request->attendee_id)
                ->where('receiver_id', $request->receiver_id)
                ->where('receiver_type', $request->receiver_type)
                ->whereDate('meeting_date', Carbon::parse($request->meeting_date)->format('Y-m-d'))
                ->where(function ($query) use ($request) {
                    $query->where('meeting_status', MeetingStatus::PENDING->value)
                        ->orWhere('meeting_status', MeetingStatus::ACCEPTED->value);
                })
                ->where(function ($query) use ($request) {
                    $query->where(function ($q) use ($request) {
                        $q->whereTime('meeting_start_time', '<', Carbon::parse($request->meeting_end_time))
                            ->whereTime('meeting_end_time', '>', Carbon::parse($request->meeting_start_time));
                    });
                })
                ->exists();

            if (!$meetingExist) {
                $meeting = AttendeeMeeting::create([
                    'event_id' => $eventId,
                    'attendee_id' => $request->attendee_id,
                    'receiver_id' => $request->receiver_id,
                    'receiver_type' => $request->receiver_type,
                    'meeting_status' => MeetingStatus::PENDING->value,
                    'meeting_title' => $request->meeting_title,
                    'meeting_date' => Carbon::parse($request->meeting_date)->format('Y-m-d'),
                    'meeting_start_time' => Carbon::parse($request->meeting_start_time)->format('H:i:s'),
                    'meeting_end_time' => Carbon::parse($request->meeting_end_time)->format('H:i:s'),
                    'meeting_location' => $request->meeting_location,
                    'meeting_notes' => $request->meeting_notes,

                    'respond_token' => $rawToken,
                    'respond_token_expires_at' => $tokenExpiresAt,
                    'respond_token_status' => $isNonAttendee ? MeetingRespondTokenStatus::ACTIVE->value : null,
                ]);

                // PUSH NOTIFICATION TO RECEIVER AND REQUESTER
                if ($request->receiver_type ===  MeetingReceiverType::ATTENDEE->value) {
                    AttendeeNotification::create([
                        'event_id' => $event->id,
                        'attendee_id' => $request->receiver_id,
                        'notification_id' => null,

                        'type' => NotificationTypes::ATTENDEE_MEETINGS->value,
                        'title' => "New Meeting Request",
                        'subtitle' => "You have a new meeting request from {$requester->first_name}.",
                        'message' => "You have a new meeting request from {$requester->first_name}.",
                        'sent_datetime' => Carbon::now(),
                        'is_seen' => false,
                    ]);

                    if ($receiver->deviceTokens->isNotEmpty()) {
                        foreach ($receiver->deviceTokens as $attendeeDeviceToken) {
                            $data = [
                                'event_id' => (string) $event->id,
                                'notification_type' => NotificationTypes::ATTENDEE_MEETINGS->value,
                                'entity_id' => null,
                            ];
                            sendPushNotificationv2($attendeeDeviceToken->device_token, "New Meeting Request", "You have a new meeting request from {$requester->first_name}.", $data);
                        }
                    }
                }

                AttendeeNotification::create([
                    'event_id' => $event->id,
                    'attendee_id' => $request->attendee_id,
                    'notification_id' => null,

                    'type' => NotificationTypes::ATTENDEE_MEETINGS->value,
                    'title' => "Meeting Request Sent",
                    'subtitle' => "You have sent a meeting request to {$receiverName}.",
                    'message' => "You have sent a meeting request to {$receiverName}.",
                    'sent_datetime' => Carbon::now(),
                    'is_seen' => false,
                ]);

                if ($requester->deviceTokens->isNotEmpty()) {
                    foreach ($requester->deviceTokens as $attendeeDeviceToken) {
                        $data = [
                            'event_id' => (string) $event->id,
                            'notification_type' => NotificationTypes::ATTENDEE_MEETINGS->value,
                            'entity_id' => null,
                        ];
                        sendPushNotificationv2($attendeeDeviceToken->device_token, "Meeting Request Sent", "You have sent a meeting request to {$receiverName}.", $data);
                    }
                }

                $meetingRespondLink = null;
                if ($isNonAttendee && $meeting) {
                    $meetingRespondLink = route('meeting.respond.view', [
                        'eventCategory' => $event->category,
                        'eventId'       => $event->id,
                        'meetingId'     => $meeting->id,
                        'token'         => $rawToken,
                    ]);
                }

                $details = [
                    'requesterName' => $requester->first_name,
                    'receiverName' => $receiverName,
                    'receiverType' => $request->receiver_type,

                    'eventName' => $event->full_name,
                    'eventCategory' => $event->category,
                    'eventYear' => $event->year,
                    'eventLink' => $event->event_full_link,

                    'meetingTitle' => $request->meeting_title,
                    'meetingDate' => Carbon::parse($request->meeting_date)->format('F d, Y'),
                    'meetingStartTime' => Carbon::parse($request->meeting_start_time)->format('g:i A'),
                    'meetingEndTime' => Carbon::parse($request->meeting_end_time)->format('g:i A'),
                    'meetingLocation' => $request->meeting_location,
                    'meetingNotes' => $request->meeting_notes,

                    'isAttendee' => $request->receiver_type === MeetingReceiverType::ATTENDEE->value,
                    'meetingRespondLink' => $meetingRespondLink,
                ];

                // EMAIL NOTIFICATION TO RECEIVER AND REQUESTER
                try {
                    Mail::to($receiver->email_address)->send(new AttendeeMeetingRequestMail($details, true));
                } catch (\Throwable $e) {
                    Log::error("Failed to send email to receiver: " . $e->getMessage());
                }

                try {
                    Mail::to($requester->email_address)->send(new AttendeeMeetingRequestMail($details, false));
                } catch (\Throwable $e) {
                    Log::error("Failed to send email to requester: " . $e->getMessage());
                }


                return $this->success(null, "Meeting added successfully", 200);
            }
            return $this->error(null, "Meeting already exists", 409);
        } catch (\Exception $e) {
            return $this->error($e, "An error occurred while requesting a meeting", 500);
        }
    }


    public function apiAttendeeAcceptMeeting(Request $request, $apiCode, $eventCategory, $eventId, $attendeeId)
    {
        $validator = Validator::make($request->all(), [
            'meeting_id' => 'required|exists:attendee_meetings,id',
            'accepted_reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->errorValidation($validator->errors());
        }

        try {
            $meeting = AttendeeMeeting::with('requester')->where('id', $request->meeting_id)->where('event_id', $eventId)->first();

            if (!$meeting) {
                return $this->error(null, "Meeting doesn't exist", 404);
            }

            if ($meeting->meeting_status !== MeetingStatus::PENDING->value) {
                return $this->error(null, "Meeting is not in pending status", 400);
            }

            if ($meeting->receiver_type === MeetingReceiverType::ATTENDEE->value && $meeting->receiver_id != $attendeeId) {
                return $this->error(null, "You are not authorized to accept this meeting", 403);
            }

            $dateISO  = Carbon::parse($meeting->meeting_date)->format('Y-m-d');
            $startHH  = Carbon::parse($meeting->meeting_start_time)->format('H:i');
            $endHH    = Carbon::parse($meeting->meeting_end_time)->format('H:i');

            $reqHasConflict = $this->hasOverlapForParticipant(
                $eventId,
                $dateISO,
                $startHH,
                $endHH,
                $meeting->id,
                MeetingReceiverType::ATTENDEE->value,
                $meeting->attendee_id
            );

            $recHasConflict = $this->hasOverlapForParticipant(
                $eventId,
                $dateISO,
                $startHH,
                $endHH,
                $meeting->id,
                $meeting->receiver_type,
                $meeting->receiver_id
            );

            if ($reqHasConflict || $recHasConflict) {
                $who = $reqHasConflict && $recHasConflict
                    ? 'Both the requester and the receiver'
                    : ($reqHasConflict ? 'The requester' : 'The receiver');

                return $this->error(null, "$who already has a confirmed meeting that overlaps this time.", 409);
            }

            $meeting->meeting_status = MeetingStatus::ACCEPTED->value;
            $meeting->accepted_datetime = Carbon::now();
            $meeting->accepted_reason = $request->accepted_reason ?? null;
            $meeting->save();

            $event = Event::where('id', $eventId)->first();

            if ($meeting->receiver_type ===  MeetingReceiverType::ATTENDEE->value) {
                $receiver = Attendee::with('deviceTokens')->where('id', $meeting->receiver_id)->first();
                $receiverName = $receiver->first_name;
            } else if ($meeting->receiver_type === MeetingReceiverType::EXHIBITOR->value) {
                $receiver = Exhibitor::where('id', $meeting->receiver_id)->first();
                $receiverName = $receiver->contact_person_name;
            } else if ($meeting->receiver_type === MeetingReceiverType::SPONSOR->value) {
                $receiver = Sponsor::where('id', $meeting->receiver_id)->first();
                $receiverName = $receiver->contact_person_name;
            } else if ($meeting->receiver_type === MeetingReceiverType::MEETING_ROOM_PARTNER->value) {
                $receiver = MeetingRoomPartner::where('id', $meeting->receiver_id)->first();
                $receiverName = $receiver->contact_person_name;
            } else {
                return $this->error(null, "Invalid receiver type", 400);
            }

            // PUSH NOTIFICATION TO RECEIVER AND REQUESTER
            if ($meeting->receiver_type === MeetingReceiverType::ATTENDEE->value) {
                AttendeeNotification::create([
                    'event_id' => $event->id,
                    'attendee_id' => $meeting->receiver_id,
                    'notification_id' => null,

                    'type' => NotificationTypes::ATTENDEE_MEETING_DETAILS->value,
                    'title' => "You accepted a meeting request",
                    'subtitle' => "You accepted a meeting request from {$meeting->requester->first_name}.",
                    'message' => "You accepted a meeting request from {$meeting->requester->first_name}.",
                    'sent_datetime' => Carbon::now(),
                    'is_seen' => false,
                ]);

                if ($receiver->deviceTokens->isNotEmpty()) {
                    foreach ($receiver->deviceTokens as $attendeeDeviceToken) {
                        $data = [
                            'event_id' => (string) $event->id,
                            'notification_type' => NotificationTypes::ATTENDEE_MEETING_DETAILS->value,
                            'entity_id' => $meeting->id,
                        ];
                        sendPushNotificationv2($attendeeDeviceToken->device_token, "You Accepted Meeting Request", "You have accepted a meeting request from {$meeting->requester->first_name}.", $data);
                    }
                }
            }

            AttendeeNotification::create([
                'event_id' => $event->id,
                'attendee_id' => $meeting->attendee_id,
                'notification_id' => null,

                'type' => NotificationTypes::ATTENDEE_MEETING_DETAILS->value,
                'title' => "Meeting Request Accepted",
                'subtitle' => "Your meeting request has been accepted by {$receiverName}.",
                'message' => "Your meeting request has been accepted by {$receiverName}.",
                'sent_datetime' => Carbon::now(),
                'is_seen' => false,
            ]);

            if ($meeting->requester->deviceTokens->isNotEmpty()) {
                foreach ($meeting->requester->deviceTokens as $attendeeDeviceToken) {
                    $data = [
                        'event_id' => (string) $event->id,
                        'notification_type' => NotificationTypes::ATTENDEE_MEETING_DETAILS->value,
                        'entity_id' => $meeting->id,
                    ];
                    sendPushNotificationv2($attendeeDeviceToken->device_token, "Meeting Request Accepted", "Your meeting request has been accepted by {$receiverName}.", $data);
                }
            }

            $details = [
                'requesterName' => $meeting->requester->first_name,
                'receiverName' => $receiverName,
                'receiverType' => $meeting->receiver_type,

                'eventName' => $event->full_name,
                'eventCategory' => $event->category,
                'eventYear' => $event->year,
                'eventLink' => $event->event_full_link,

                'meetingTitle' => $meeting->meeting_title,
                'meetingDate' => Carbon::parse($meeting->meeting_date)->format('F d, Y'),
                'meetingStartTime' => Carbon::parse($meeting->meeting_start_time)->format('g:i A'),
                'meetingEndTime' => Carbon::parse($meeting->meeting_end_time)->format('g:i A'),
                'meetingLocation' => $meeting->meeting_location,
                'meetingNotes' => $meeting->meeting_notes,

                'isAttendee' => $meeting->receiver_type === MeetingReceiverType::ATTENDEE->value,
                'meetingRespondLink' => null,
            ];

            // EMAIL NOTIFICATION TO RECEIVER AND REQUESTER
            Mail::to($receiver->email_address)->send(new AttendeeMeetingAcceptedMail($details, true));
            Mail::to($meeting->requester->email_address)->send(new AttendeeMeetingAcceptedMail($details, false));

            return $this->success(null, "Meeting accepted successfully", 200);
        } catch (\Exception $e) {
            return $this->error($e, "An error occurred while accepting a meeting", 500);
        }
    }

    public function apiAttendeeDeclineMeeting(Request $request, $apiCode, $eventCategory, $eventId, $attendeeId)
    {
        $validator = Validator::make($request->all(), [
            'meeting_id' => 'required|exists:attendee_meetings,id',
            'declined_reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->errorValidation($validator->errors());
        }

        try {
            $meeting = AttendeeMeeting::with('requester')->where('id', $request->meeting_id)->where('event_id', $eventId)->first();

            if (!$meeting) {
                return $this->error(null, "Meeting doesn't exist", 404);
            }

            if ($meeting->meeting_status !== MeetingStatus::PENDING->value) {
                return $this->error(null, "Meeting is not in pending status", 400);
            }

            if ($meeting->receiver_type === MeetingReceiverType::ATTENDEE->value && $meeting->receiver_id != $attendeeId) {
                return $this->error(null, "You are not authorized to decline this meeting", 403);
            }

            $meeting->meeting_status = MeetingStatus::DECLINED->value;
            $meeting->declined_datetime = Carbon::now();
            $meeting->declined_reason = $request->declined_reason ?? null;
            $meeting->save();

            $event = Event::where('id', $eventId)->first();

            if ($meeting->receiver_type === MeetingReceiverType::ATTENDEE->value) {
                $receiver = Attendee::with('deviceTokens')->where('id', $meeting->receiver_id)->first();
                $receiverName = $receiver->first_name;
            } else if ($meeting->receiver_type === MeetingReceiverType::EXHIBITOR->value) {
                $receiver = Exhibitor::where('id', $meeting->receiver_id)->first();
                $receiverName = $receiver->contact_person_name;
            } else if ($meeting->receiver_type === MeetingReceiverType::SPONSOR->value) {
                $receiver = Sponsor::where('id', $meeting->receiver_id)->first();
                $receiverName = $receiver->contact_person_name;
            } else if ($meeting->receiver_type === MeetingReceiverType::MEETING_ROOM_PARTNER->value) {
                $receiver = MeetingRoomPartner::where('id', $meeting->receiver_id)->first();
                $receiverName = $receiver->contact_person_name;
            } else {
                return $this->error(null, "Invalid receiver type", 400);
            }

            // PUSH NOTIFICATION TO RECEIVER AND REQUESTER
            if ($meeting->receiver_type === MeetingReceiverType::ATTENDEE->value) {
                AttendeeNotification::create([
                    'event_id' => $event->id,
                    'attendee_id' => $meeting->receiver_id,
                    'notification_id' => null,

                    'type' => NotificationTypes::ATTENDEE_MEETING_DETAILS->value,
                    'title' => "You declined a meeting request",
                    'subtitle' => "You declined a meeting request from {$meeting->requester->first_name}.",
                    'message' => "You declined a meeting request from {$meeting->requester->first_name}.",
                    'sent_datetime' => Carbon::now(),
                    'is_seen' => false,
                ]);

                if ($receiver->deviceTokens->isNotEmpty()) {
                    foreach ($receiver->deviceTokens as $attendeeDeviceToken) {
                        $data = [
                            'event_id' => (string) $event->id,
                            'notification_type' => NotificationTypes::ATTENDEE_MEETING_DETAILS->value,
                            'entity_id' => $meeting->id,
                        ];
                        sendPushNotificationv2($attendeeDeviceToken->device_token, "You Declined Meeting Request", "You have declined a meeting request from {$meeting->requester->first_name}.", $data);
                    }
                }
            }

            AttendeeNotification::create([
                'event_id' => $event->id,
                'attendee_id' => $meeting->attendee_id,
                'notification_id' => null,
                'type' => NotificationTypes::ATTENDEE_MEETINGS->value,
                'title' => "Meeting Request Declined",
                'subtitle' => "{$receiverName} declined your meeting request.",
                'message' => "{$receiverName} declined your meeting request.",
                'sent_datetime' => Carbon::now(),
                'is_seen' => false,
            ]);

            if ($meeting->requester->deviceTokens->isNotEmpty()) {
                foreach ($meeting->requester->deviceTokens as $attendeeDeviceToken) {
                    $data = [
                        'event_id' => (string) $event->id,
                        'notification_type' => NotificationTypes::ATTENDEE_MEETINGS->value,
                        'entity_id' => $meeting->id,
                    ];
                    sendPushNotificationv2($attendeeDeviceToken->device_token, "Meeting Request Declined", "{$receiverName} declined your meeting request.", $data);
                }
            }

            $details = [
                'requesterName' => $meeting->requester->first_name,
                'receiverName' => $receiverName,
                'receiverType' => $meeting->receiver_type,

                'eventName' => $event->full_name,
                'eventCategory' => $event->category,
                'eventYear' => $event->year,
                'eventLink' => $event->event_full_link,

                'meetingTitle' => $meeting->meeting_title,
                'meetingDate' => Carbon::parse($meeting->meeting_date)->format('F d, Y'),
                'meetingStartTime' => Carbon::parse($meeting->meeting_start_time)->format('g:i A'),
                'meetingEndTime' => Carbon::parse($meeting->meeting_end_time)->format('g:i A'),
                'meetingLocation' => $meeting->meeting_location,
                'meetingNotes' => $meeting->meeting_notes,

                'declinedReason' => $request->declined_reason ?? null,
                'isAttendee' => $meeting->receiver_type === MeetingReceiverType::ATTENDEE->value,
                'meetingRespondLink' => null,
            ];

            Mail::to($receiver->email_address)->send(new AttendeeMeetingDeclinedMail($details, true));
            Mail::to($meeting->requester->email_address)->send(new AttendeeMeetingDeclinedMail($details, false));

            return $this->success(null, "Meeting declined successfully", 200);
        } catch (\Exception $e) {
            return $this->error($e, "An error occurred while declining the meeting", 500);
        }
    }

    public function apiAttendeeCancelMeeting(Request $request, $apiCode, $eventCategory, $eventId, $attendeeId)
    {
        $validator = Validator::make($request->all(), [
            'meeting_id' => 'required|exists:attendee_meetings,id',
            'cancelled_reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->errorValidation($validator->errors());
        }

        try {
            $meeting = AttendeeMeeting::with('requester')->where('id', $request->meeting_id)->where('event_id', $eventId)->first();

            if (!$meeting) {
                return $this->error(null, "Meeting doesn't exist", 404);
            }

            if ($meeting->attendee_id != $attendeeId) {
                return $this->error(null, "You are not authorized to cancel this meeting", 403);
            }

            $meeting->meeting_status = MeetingStatus::CANCELLED->value;
            $meeting->cancelled_datetime = Carbon::now();
            $meeting->cancelled_reason = $request->cancelled_reason ?? null;
            $meeting->save();

            $event = Event::where('id', $eventId)->first();

            if ($meeting->receiver_type === MeetingReceiverType::ATTENDEE->value) {
                $receiver = Attendee::with('deviceTokens')->where('id', $meeting->receiver_id)->first();
                $receiverName = $receiver->first_name;
            } else if ($meeting->receiver_type === MeetingReceiverType::EXHIBITOR->value) {
                $receiver = Exhibitor::where('id', $meeting->receiver_id)->first();
                $receiverName = $receiver->contact_person_name;
            } else if ($meeting->receiver_type === MeetingReceiverType::SPONSOR->value) {
                $receiver = Sponsor::where('id', $meeting->receiver_id)->first();
                $receiverName = $receiver->contact_person_name;
            } else if ($meeting->receiver_type === MeetingReceiverType::MEETING_ROOM_PARTNER->value) {
                $receiver = MeetingRoomPartner::where('id', $meeting->receiver_id)->first();
                $receiverName = $receiver->contact_person_name;
            } else {
                return $this->error(null, "Invalid receiver type", 400);
            }

            // PUSH NOTIFICATION TO RECEIVER (if attendee)
            if ($meeting->receiver_type === MeetingReceiverType::ATTENDEE->value) {
                AttendeeNotification::create([
                    'event_id' => $event->id,
                    'attendee_id' => $meeting->receiver_id,
                    'notification_id' => null,

                    'type' => NotificationTypes::ATTENDEE_MEETING_DETAILS->value,
                    'title' => "Meeting Cancelled",
                    'subtitle' => "The meeting with {$meeting->requester->first_name} has been cancelled.",
                    'message' => "The meeting with {$meeting->requester->first_name} has been cancelled.",
                    'sent_datetime' => Carbon::now(),
                    'is_seen' => false,
                ]);

                if ($receiver->deviceTokens->isNotEmpty()) {
                    foreach ($receiver->deviceTokens as $attendeeDeviceToken) {
                        $data = [
                            'event_id' => (string) $event->id,
                            'notification_type' => NotificationTypes::ATTENDEE_MEETING_DETAILS->value,
                            'entity_id' => $meeting->id,
                        ];
                        sendPushNotificationv2($attendeeDeviceToken->device_token, "Meeting Cancelled", "The meeting with {$meeting->requester->first_name} has been cancelled.", $data);
                    }
                }
            }

            // NOTIFICATION FOR REQUESTER
            AttendeeNotification::create([
                'event_id' => $event->id,
                'attendee_id' => $meeting->attendee_id,
                'notification_id' => null,
                'type' => NotificationTypes::ATTENDEE_MEETING_DETAILS->value,
                'title' => "You cancelled the meeting",
                'subtitle' => "You cancelled the meeting with {$receiverName}.",
                'message' => "You cancelled the meeting with {$receiverName}.",
                'sent_datetime' => Carbon::now(),
                'is_seen' => false,
            ]);

            if ($meeting->requester->deviceTokens->isNotEmpty()) {
                foreach ($meeting->requester->deviceTokens as $attendeeDeviceToken) {
                    $data = [
                        'event_id' => (string) $event->id,
                        'notification_type' => NotificationTypes::ATTENDEE_MEETING_DETAILS->value,
                        'entity_id' => $meeting->id,
                    ];
                    sendPushNotificationv2($attendeeDeviceToken->device_token, "Meeting Cancelled", "You have cancelled the meeting with {$receiverName}.", $data);
                }
            }

            // EMAIL NOTIFICATION TO BOTH SIDES
            $details = [
                'requesterName' => $meeting->requester->first_name,
                'receiverName' => $receiverName,
                'receiverType' => $meeting->receiver_type,

                'eventName' => $event->full_name,
                'eventCategory' => $event->category,
                'eventYear' => $event->year,
                'eventLink' => $event->event_full_link,

                'meetingTitle' => $meeting->meeting_title,
                'meetingDate' => Carbon::parse($meeting->meeting_date)->format('F d, Y'),
                'meetingStartTime' => Carbon::parse($meeting->meeting_start_time)->format('g:i A'),
                'meetingEndTime' => Carbon::parse($meeting->meeting_end_time)->format('g:i A'),
                'meetingLocation' => $meeting->meeting_location,
                'meetingNotes' => $meeting->meeting_notes,

                'cancelledReason' => $request->cancelled_reason ?? null,
                'isAttendee' => $meeting->receiver_type === MeetingReceiverType::ATTENDEE->value,
                'meetingRespondLink' => null,
            ];

            Mail::to($receiver->email_address)->send(new AttendeeMeetingCancelledMail($details, true));
            Mail::to($meeting->requester->email_address)->send(new AttendeeMeetingCancelledMail($details, false));

            return $this->success(null, "Meeting cancelled successfully", 200);
        } catch (\Exception $e) {
            return $this->error($e, "An error occurred while cancelling the meeting", 500);
        }
    }

    public function apiAttendeeRescheduleMeeting(Request $request, $apiCode, $eventCategory, $eventId, $attendeeId)
    {
        $validator = Validator::make($request->all(), [
            'meeting_id' => 'required|exists:attendee_meetings,id',
            'meeting_title' => 'required|string|max:255',
            'meeting_date' => 'required',
            'meeting_start_time' => 'required',
            'meeting_end_time' => 'required',
            'meeting_location' => 'required|string',
            'meeting_notes' => 'required|string',
            'reschedule_reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorValidation($validator->errors());
        }

        try {
            $originalMeeting = AttendeeMeeting::with('requester')
                ->where('id', $request->meeting_id)
                ->where('event_id', $eventId)
                ->first();

            if (!$originalMeeting) {
                return $this->error(null, "Meeting doesn't exist", 404);
            }

            if ($originalMeeting->attendee_id != $attendeeId) {
                return $this->error(null, "You are not authorized to reschedule this meeting", 403);
            }

            if (!in_array($originalMeeting->meeting_status, [MeetingStatus::PENDING->value, MeetingStatus::ACCEPTED->value])) {
                return $this->error(null, "Only pending or accepted meetings can be rescheduled", 400);
            }

            // Cancel the old meeting
            $originalMeeting->meeting_status = MeetingStatus::CANCELLED->value;
            $originalMeeting->cancelled_reason = $request->reschedule_reason ?? 'Rescheduled to a new date/time.';
            $originalMeeting->cancelled_datetime = Carbon::now();
            $originalMeeting->is_reschedule = true;

            if ($originalMeeting->receiver_type !== MeetingReceiverType::ATTENDEE->value) {
                $originalMeeting->respond_token_status = MeetingRespondTokenStatus::EXPIRED->value;
                $originalMeeting->respond_token_expires_at = now();
            }

            $originalMeeting->save();

            $receiverType = $originalMeeting->receiver_type;

            $isNonAttendee = $receiverType !== MeetingReceiverType::ATTENDEE->value;
            $rawToken       = $isNonAttendee ? Str::random(64) : null;
            $tokenExpiresAt = $isNonAttendee ? now()->addDays(7) : null;

            // Create the new meeting
            $newMeeting = AttendeeMeeting::create([
                'event_id' => $eventId,
                'attendee_id' => $originalMeeting->attendee_id,
                'receiver_id' => $originalMeeting->receiver_id,
                'receiver_type' => $originalMeeting->receiver_type,
                'meeting_status' => MeetingStatus::PENDING->value,
                'meeting_title' => $request->meeting_title,
                'meeting_date' => Carbon::parse($request->meeting_date)->format('Y-m-d'),
                'meeting_start_time' => Carbon::parse($request->meeting_start_time)->format('H:i:s'),
                'meeting_end_time' => Carbon::parse($request->meeting_end_time)->format('H:i:s'),
                'meeting_location' => $request->meeting_location,
                'meeting_notes' => $request->meeting_notes,
                'parent_meeting_id' => $originalMeeting->id,

                'respond_token' => $rawToken,
                'respond_token_expires_at' => $tokenExpiresAt,
                'respond_token_status' => $isNonAttendee ? MeetingRespondTokenStatus::ACTIVE->value : null,
            ]);

            $event = Event::where('id', $eventId)->first();
            $requester = $originalMeeting->requester;

            $receiverType = $originalMeeting->receiver_type;
            $receiver = null;
            $receiverName = '';

            if ($receiverType === MeetingReceiverType::ATTENDEE->value) {
                $receiver = Attendee::with('deviceTokens')->where('id', $originalMeeting->receiver_id)->first();
                $receiverName = $receiver->first_name;
            } else if ($receiverType === MeetingReceiverType::EXHIBITOR->value) {
                $receiver = Exhibitor::where('id', $originalMeeting->receiver_id)->first();
                $receiverName = $receiver->contact_person_name;
            } else if ($receiverType === MeetingReceiverType::SPONSOR->value) {
                $receiver = Sponsor::where('id', $originalMeeting->receiver_id)->first();
                $receiverName = $receiver->contact_person_name;
            } else if ($receiverType === MeetingReceiverType::MEETING_ROOM_PARTNER->value) {
                $receiver = MeetingRoomPartner::where('id', $originalMeeting->receiver_id)->first();
                $receiverName = $receiver->contact_person_name;
            } else {
                return $this->error(null, "Invalid receiver type", 400);
            }

            // Push notification to receiver
            if ($receiverType === MeetingReceiverType::ATTENDEE->value) {
                AttendeeNotification::create([
                    'event_id' => $event->id,
                    'attendee_id' => $receiver->id,
                    'notification_id' => null,
                    'type' => NotificationTypes::ATTENDEE_MEETING_DETAILS->value,
                    'title' => "Meeting Rescheduled",
                    'subtitle' => "{$requester->first_name} has rescheduled your meeting.",
                    'message' => "{$requester->first_name} has rescheduled your meeting.",
                    'sent_datetime' => Carbon::now(),
                    'is_seen' => false,
                ]);

                foreach ($receiver->deviceTokens as $token) {
                    sendPushNotificationv2(
                        $token->device_token,
                        "Meeting Rescheduled",
                        "{$requester->first_name} has rescheduled your meeting.",
                        [
                            'event_id' => (string) $event->id,
                            'notification_type' => NotificationTypes::ATTENDEE_MEETING_DETAILS->value,
                            'entity_id' => $newMeeting->id,
                        ]
                    );
                }
            }

            // Push notification to requester
            AttendeeNotification::create([
                'event_id' => $event->id,
                'attendee_id' => $requester->id,
                'notification_id' => null,
                'type' => NotificationTypes::ATTENDEE_MEETING_DETAILS->value,
                'title' => "You Rescheduled a Meeting",
                'subtitle' => "You rescheduled a meeting with {$receiverName}.",
                'message' => "You rescheduled a meeting with {$receiverName}.",
                'sent_datetime' => Carbon::now(),
                'is_seen' => false,
            ]);

            foreach ($requester->deviceTokens as $token) {
                sendPushNotificationv2(
                    $token->device_token,
                    "Meeting Rescheduled",
                    "You rescheduled a meeting with {$receiverName}.",
                    [
                        'event_id' => (string) $event->id,
                        'notification_type' => NotificationTypes::ATTENDEE_MEETING_DETAILS->value,
                        'entity_id' => $newMeeting->id,
                    ]
                );
            }

            $meetingRespondLink = null;
            if ($isNonAttendee) {
                $meetingRespondLink = route('meeting.respond.view', [
                    'eventCategory' => $event->category,
                    'eventId'       => $event->id,
                    'meetingId'     => $newMeeting->id,
                    'token'         => $rawToken,
                ]);
            }

            // Email to both sides
            $details = [
                'requesterName' => $requester->first_name,
                'receiverName' => $receiverName,
                'receiverType' => $receiverType,

                'eventName' => $event->full_name,
                'eventCategory' => $event->category,
                'eventYear' => $event->year,
                'eventLink' => $event->event_full_link,

                'meetingTitle' => $newMeeting->meeting_title,
                'meetingDate' => Carbon::parse($newMeeting->meeting_date)->format('F d, Y'),
                'meetingStartTime' => Carbon::parse($newMeeting->meeting_start_time)->format('g:i A'),
                'meetingEndTime' => Carbon::parse($newMeeting->meeting_end_time)->format('g:i A'),
                'meetingLocation' => $newMeeting->meeting_location,
                'meetingNotes' => $newMeeting->meeting_notes,

                'cancelledReason' => $originalMeeting->cancelled_reason,
                'isAttendee' => $receiverType === MeetingReceiverType::ATTENDEE->value,
                'meetingRespondLink' => $meetingRespondLink,
            ];

            Mail::to($receiver->email_address)->send(new AttendeeMeetingRescheduledMail($details, true));
            Mail::to($requester->email_address)->send(new AttendeeMeetingRescheduledMail($details, false));

            return $this->success(['meeting_id' => $newMeeting->id], "Meeting rescheduled successfully", 200);
        } catch (\Exception $e) {
            Log::info($e);
            return $this->error($e, "An error occurred while rescheduling the meeting", 500);
        }
    }
}
