<?php

namespace App\Http\Controllers;

use App\Models\AttendeeFavoriteSession;
use App\Models\Event;
use App\Models\Feature;
use App\Models\Media;
use App\Models\Session;
use App\Models\SessionSpeaker;
use App\Models\SessionSpeakerType;
use App\Models\Speaker;
use App\Models\Sponsor;
use App\Traits\HttpResponses;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SessionController extends Controller
{
    use HttpResponses;

    public function eventSessionsView($eventCategory, $eventId)
    {
        $eventName = Event::where('id', $eventId)->where('category', $eventCategory)->value('full_name');

        return view('admin.event.sessions.sessions', [
            "pageTitle" => "Session",
            "eventName" => $eventName,
            "eventCategory" => $eventCategory,
            "eventId" => $eventId,
        ]);
    }

    public function eventSessionView($eventCategory, $eventId, $sessionId)
    {
        $session = Session::with(['event', 'feature'])->where('id', $sessionId)->first();

        if ($session) {
            if ($session->feature_id == 0) {
                $category = $session->event->short_name;
            } else {
                if ($session->feature) {
                    $category = $session->feature->short_name;
                } else {
                    $category = "Others";
                }
            }

            if ($session->end_time == "none") {
                $finalEndTime = 'onwards';
            } else {
                $finalEndTime = $session->end_time;
            }


            // FORE SESSION SPEAKERS
            $sessionSpeakerGroup = [];
            $finalSessionSpeakerGroup = [];

            $sessionSpeakerTypes = SessionSpeakerType::where('event_id', $eventId)->where('session_id', $sessionId)->orderBy('datetime_added')->get();

            if ($sessionSpeakerTypes->isNotEmpty()) {
                foreach ($sessionSpeakerTypes as $sessionSpeakerType) {
                    array_push($sessionSpeakerGroup, [
                        'sessionSpeakerTypeId' => $sessionSpeakerType->id,
                        'sessionSpeakerTypeName' => $sessionSpeakerType->name,
                        'speakers' => array(),
                    ]);
                }
            }

            array_push($sessionSpeakerGroup, [
                'sessionSpeakerTypeId' => 0,
                'sessionSpeakerTypeName' => null,
                'speakers' => array(),
            ]);

            $sessionSpeakers = SessionSpeaker::with('speaker.pfp')->where('event_id', $eventId)->where('session_id', $sessionId)->get();
            if ($sessionSpeakers->isNotEmpty()) {
                foreach ($sessionSpeakers as $sessionSpeaker) {

                    foreach ($sessionSpeakerGroup as $sessionSpeakerGroupIndex => $group) {
                        if ($group['sessionSpeakerTypeId'] == $sessionSpeaker['session_speaker_type_id']) {

                            $speakerName = $sessionSpeaker->speaker->salutation . ' ' . $sessionSpeaker->speaker->first_name . ' ' . $sessionSpeaker->speaker->middle_name . ' ' . $sessionSpeaker->speaker->last_name;

                            $speakers = [
                                'sessionSpeakerId' => $sessionSpeaker->id,
                                'speakerId' => $sessionSpeaker->speaker->id,
                                'speakerName' => $speakerName,
                                'speakerPFP' => $sessionSpeaker->speaker->pfp->file_url ?? null,
                            ];

                            array_push($sessionSpeakerGroup[$sessionSpeakerGroupIndex]['speakers'], $speakers);
                        }
                    }
                }

                foreach ($sessionSpeakerGroup as $group) {
                    if ($group['speakers'] != array()) {
                        array_push($finalSessionSpeakerGroup, $group);
                    }
                }
            } else {
                $finalSessionSpeakerGroup = [];
            }

            if ($session->sponsor_id) {
                $sponsor = Sponsor::with(['sponsorType', 'logo'])->where('id', $session->sponsor_id)->where('event_id', $eventId)->where('is_active', true)->first();
                if ($sponsor) {
                    $sponsorTypeName = $sponsor->sponsorType->name ?? null;
                    $sponsorName = $sponsor->name . ' - ' . $sponsorTypeName;
                    $sessionSponsorLogo = $sponsor->logo->file_url ?? null;
                } else {
                    $sponsorName = null;
                    $sessionSponsorLogo = null;
                }
            } else {
                $sponsorName = null;
                $sessionSponsorLogo = null;
            }

            $sessionData = [
                "sessionId" => $session->id,
                "sessionCategoryName" => $category,
                "sessionFeatureId" => $session->feature_id,

                "sessionDate" => $session->session_date,
                "sessionDateName" => Carbon::parse($session->session_date)->format('F d, Y'),

                "sessionDay" => $session->session_day,
                "sessionType" => $session->session_type,
                "sessionTitle" => $session->title,
                "sessionDescription" => $session->description_html_text,
                "sessionStartTime" => $session->start_time,
                "sessionEndTime" => $finalEndTime,
                "sessionLocation" => $session->location,

                "sessionSponsorLogo" => [
                    'sponsor_id' => $session->sponsor_id,
                    'name' => $sponsorName,
                    'url' => $sessionSponsorLogo,
                ],

                "finalSessionSpeakerGroup" => $finalSessionSpeakerGroup,

                "sessionStatus" => $session->active,
                "sessionDateTimeAdded" => Carbon::parse($session->datetime_added)->format('M j, Y g:i A'),
            ];

            return view('admin.event.sessions.session', [
                "pageTitle" => "Session",
                "eventName" => $session->event->full_name,
                "eventCategory" => $eventCategory,
                "eventId" => $eventId,
                "sessionData" => $sessionData,
            ]);
        } else {
            abort(404, 'Data not found');
        }
    }

    public function eventSessionSpeakerTypesView($eventCategory, $eventId, $sessionId)
    {
        $event = Event::where('id', $eventId)->where('category', $eventCategory)->first();
        $session = Session::where('id', $sessionId)->first();

        if ($session) {
            return view('admin.event.sessions.session_speaker_types', [
                "pageTitle" => "Session speaker types",
                "eventName" => $event->full_name,
                "eventCategory" => $eventCategory,
                "eventId" => $eventId,
                "sessionId" => $sessionId,
            ]);
        } else {
            abort(404, 'Data not found');
        }
    }




    // =========================================================
    //                       API FUNCTIONS
    // =========================================================
    public function apiEventSessions($apiCode, $eventCategory, $eventId, $attendeeId)
    {
        try {
            $sessions = Session::with(['feature', 'sponsor.logo'])->where('event_id', $eventId)->where('is_active', true)->orderBy('session_date', 'ASC')->orderBy('start_time', 'ASC')->get();

            // if ($sessions->isEmpty()) {
            //     return $this->error(null, "No sessions available at the moment.", 404);
            // }

            if ($sessions->isEmpty()) {
    return $this->success([], "No sessions available", 200);
}

            $data = array();

            // GET THE DATES FIRST
            $storeDatesCategoryTemp = [];
            foreach ($sessions as $session) {
                $date = $session->session_date;
                if (!isset($storeDatesCategoryTemp[$date])) {
                    $storeDatesCategoryTemp[$date] = true;
                }
            }
            $uniqueDates = array_keys($storeDatesCategoryTemp);

            if ($eventCategory == "SCC") {
                $pdf = [
                    [
                        'title' => 'GPCA Gulf SQAS Workshop Agenda (PDF)',
                        'url' => 'https://gpca.org.ae/conferences/scc/wp-content/uploads/2025/05/GPCA-Gulf-SQAS-WS-Agenda-clean.pdf'
                    ],
                    [
                        'title' => '16th GPCA Supply Chain Conference Agenda (PDF)',
                        'url' => 'https://gpca.org.ae/conferences/scc/wp-content/uploads/2025/05/16th-GPCA-Supply-Chain-Conference-Agenda_26May.pdf'
                    ]
                ];
            } else if ($eventCategory == "ANC") {
                $pdf = [
                    [
                        'title' => '15th GPCA Agri-Nutrients Conference Agenda (PDF)',
                        'url' => 'https://gpca.org.ae/conferences/anc/wp-content/uploads/2025/09/15th-GPCA-Agri-Nutrients-Conference-Agenda_17Sep.pdf'
                    ],
                ];
            } else if ($eventCategory == "RCC") {
                $pdf = [
                    [
                        'title' => '6th GPCA Responsible Care Conference Agenda (PDF)',
                        'url' => 'https://gpca.org.ae/conferences/rcc/wp-content/uploads/2025/10/6th-GPCA-Responsible-Care-Conference_Agenda_3Oct.pdf'
                    ],
                ];
            } else if ($eventCategory == "AF") {
                $pdf = [
                    [
                        'title' => '19th Annual GPCA Forum Agenda (PDF)',
                        'url' => 'https://www.gpcaforum.com/wp-content/uploads/2025/12/19th-Annual-GPCA-Forum-Agenda_7Dec.pdf'
                    ],
                    [
                        'title' => '4th GPCA Youth Forum Agenda (PDF)',
                        'url' => 'https://www.gpcaforum.com/wp-content/uploads/2025/12/4th-GPCA-Youth-Forum-Agenda-Dec-8th-PDF.pdf'
                    ],
                    [
                        'title' => 'GPCA Solutions XChange Agenda (PDF)',
                        'url' => 'https://www.gpcaforum.com/wp-content/uploads/2025/12/GPCA-Solutions-XChange-Agenda_06Dec.pdf'
                    ],
                    [
                        'title' => 'GPCA Startup Nexus Agenda (PDF)',
                        'url' => 'https://www.gpcaforum.com/wp-content/uploads/2025/12/GPCA-Startup-Nexus-Agenda_7Dec.pdf'
                    ],
                    [
                        'title' => 'GPCA Sustainability Pavilion Agenda (PDF)',
                        'url' => 'https://www.gpcaforum.com/wp-content/uploads/2025/12/GPCA-Sustainability-Pavilion-Agenda_7Dec.pdf'
                    ],
                ];
            } else if ($eventCategory == "RIC") {
                $pdf = [
                    [
                        'title' => '19th Annual GPCA Forum Agenda (PDF)',
                        'url' => 'http://gpcachem.org/wp-content/uploads/2026/03/8th-GPCA-RI-Conference-Agenda_25March.pdf'
                    ],

                ];
            } else {
                $pdf = [];
            }

            foreach ($uniqueDates as $uniqueDate) {
                $sessionsTemp = array();
                foreach ($sessions as $session) {
                    if ($session->session_date == $uniqueDate) {
                        $getSpeakersHeadshot = [];

                        $sessionSpeakersTemp = SessionSpeaker::where('event_id', $eventId)->where('session_id', $session->id)->get();

                        if ($sessionSpeakersTemp->isNotEmpty()) {
                            foreach ($sessionSpeakersTemp as $sessionSpeakerTemp) {
                                $speaker = Speaker::with('pfp')->where('event_id', $eventId)->where('id', $sessionSpeakerTemp->speaker_id)->where('is_active', true)->first();
                                if ($speaker) {
                                    $getSpeakersHeadshot[] = $speaker->pfp->file_url ?? null;
                                }
                            }
                        }

                        if ($session->end_time == "none") {
                            $sessionEndTime = "onwards";
                        } else {
                            $sessionEndTime = $session->end_time;
                        }

                        $finalCategory = "";
                        if ($session->feature_id == 0) {
                            $finalCategory = $session->event->short_name;
                        } else {
                            $finalCategory = $session->feature->short_name;
                        }

                        array_push($sessionsTemp, [
                            'session_id' => $session->id,
                            'start_time' => $session->start_time,
                            'end_time' => $sessionEndTime,
                            'title' => $session->title,
                            'category' => $finalCategory,
                            'location' => $session->location,
                            'speakers_mini_headshot' => $getSpeakersHeadshot,
                            'sponsor_mini_logo' => $session->sponsor->logo->file_url ?? null,
                        ]);
                    }
                }

                usort($sessionsTemp, function ($a, $b) {
                    return strtotime($a['start_time']) - strtotime($b['start_time']);
                });

                $dateTemp = Carbon::parse($uniqueDate);
                $formattedDate = $dateTemp->format('D d M');

                $slidoLink = Event::where('id', $eventId)->value('slido_link');



                array_push($data, [
                    'sessions_date' => $formattedDate,
                    'slido_link' => $slidoLink ?? "",
                    'sessions' => $sessionsTemp,
                    'pdfs' => $pdf,
                ]);
            }
            return $this->success($data, "Sessions list", 200);
        } catch (\Exception $e) {
            return $this->error($e, "An error occurred while getting the session list, $e", 500);
        }
    }
    // public function apiEventSessions($apiCode, $eventCategory, $eventId, $attendeeId)
    // {
    //     try {
    //         $sessions = Session::with(['feature', 'sponsor.logo'])->where('event_id', $eventId)->where('is_active', true)->orderBy('session_date', 'ASC')->orderBy('datetime_added', 'ASC')->get();
    //         $features = Feature::where('event_id', $eventId)->where('is_active', true)->orderBy('datetime_added', 'ASC')->get();
    //         $event = Event::where('id', $eventId)->where('category', $eventCategory)->first();

    //         if ($sessions->isEmpty()) {
    //             return null;
    //         }

    //         $data = array();
    //         $categorizedSessionsByDate = array();
    //         $storeDatesCategoryTemp = [];

    //         // GET THE DATES FIRST
    //         $storeDatesCategoryTemp = [];
    //         foreach ($sessions as $session) {
    //             if ($session->feature_id == 0) {
    //                 $date = $session->session_date;
    //                 if (!isset($storeDatesCategoryTemp[$date])) {
    //                     $storeDatesCategoryTemp[$date] = true;
    //                 }
    //             }
    //         }
    //         $uniqueDates = array_keys($storeDatesCategoryTemp);

    //         foreach ($uniqueDates as $uniqueDate) {
    //             $sessionsTemp = array();
    //             foreach ($sessions as $session) {
    //                 if ($session->feature_id == 0) {
    //                     if ($session->session_date == $uniqueDate) {
    //                         $getSpeakersHeadshot = [];

    //                         $sessionSpeakersTemp = SessionSpeaker::where('event_id', $eventId)->where('session_id', $session->id)->get();

    //                         if ($sessionSpeakersTemp->isNotEmpty()) {
    //                             foreach ($sessionSpeakersTemp as $sessionSpeakerTemp) {
    //                                 $speaker = Speaker::with('pfp')->where('event_id', $eventId)->where('id', $sessionSpeakerTemp->speaker_id)->first();
    //                                 $getSpeakersHeadshot[] = $speaker->pfp->file_url ?? null;
    //                             }
    //                         }

    //                         if ($session->end_time == "none") {
    //                             $sessionEndTime = "onwards";
    //                         } else {
    //                             $sessionEndTime = $session->end_time;
    //                         }

    //                         array_push($sessionsTemp, [
    //                             'session_id' => $session->id,
    //                             'start_time' => $session->start_time,
    //                             'end_time' => $sessionEndTime,
    //                             'title' => $session->title,
    //                             'speakers_mini_headshot' => $getSpeakersHeadshot,
    //                             'sponsor_mini_logo' => $session->sponsor->logo->file_url ?? null,
    //                         ]);
    //                     }
    //                 }
    //             }

    //             usort($sessionsTemp, function ($a, $b) {
    //                 return strtotime($a['start_time']) - strtotime($b['start_time']);
    //             });

    //             $dateTemp = Carbon::parse($uniqueDate);
    //             $formattedDate = $dateTemp->format('D d M');
    //             array_push($categorizedSessionsByDate, [
    //                 'sessions_date' => $formattedDate,
    //                 'sessions' => $sessionsTemp,
    //             ]);
    //         }

    //         $startDate = Carbon::parse($uniqueDates[0]);
    //         $endDate = Carbon::parse(end($uniqueDates));

    //         if ($startDate == $endDate) {
    //             $formattedDate = $startDate->format('d F Y');
    //         } else {
    //             if ($startDate->format('F') === $endDate->format('F')) {
    //                 $formattedDate = $startDate->format('d') . '-' . $endDate->format('d F Y');
    //             } else {
    //                 $formattedDate = $startDate->format('d F') . '-' . $endDate->format('d F Y');
    //             }
    //         }

    //         array_push($data, [
    //             'program_name' => $event->short_name,
    //             'program_banner' => Media::where('id', $event->event_banner_media_id)->value('file_url'),
    //             'program_date' => $formattedDate,
    //             'startDate' => $startDate,
    //             'endDate' => $endDate,
    //             'session_dates' => $categorizedSessionsByDate,
    //         ]);



    //         if ($features->isNotEmpty()) {
    //             foreach ($features as $feature) {
    //                 $categorizedSessionsByDate = array();
    //                 $storeDatesCategoryTemp = [];


    //                 // GET THE DATES FIRST
    //                 $storeDatesCategoryTemp = [];
    //                 foreach ($sessions as $session) {
    //                     if ($session->feature_id == $feature->id) {
    //                         $date = $session->session_date;
    //                         if (!isset($storeDatesCategoryTemp[$date])) {
    //                             $storeDatesCategoryTemp[$date] = true;
    //                         }
    //                     }
    //                 }
    //                 $uniqueDates = array_keys($storeDatesCategoryTemp);


    //                 foreach ($uniqueDates as $uniqueDate) {
    //                     $sessionsTemp = [];
    //                     foreach ($sessions as $session) {
    //                         if ($session->feature_id == $feature->id) {
    //                             if ($session->session_date == $uniqueDate) {
    //                                 $getSpeakersHeadshot = [];

    //                                 $sessionSpeakersTemp = SessionSpeaker::where('event_id', $eventId)->where('session_id', $session->id)->get();

    //                                 if ($sessionSpeakersTemp->isNotEmpty()) {
    //                                     foreach ($sessionSpeakersTemp as $sessionSpeakerTemp) {
    //                                         $speaker = Speaker::with('pfp')->where('event_id', $eventId)->where('id', $sessionSpeakerTemp->speaker_id)->first();
    //                                         $getSpeakersHeadshot[] = $speaker->pfp->file_url ?? null;
    //                                     }
    //                                 }

    //                                 if ($session->end_time == "none") {
    //                                     $sessionEndTime = "onwards";
    //                                 } else {
    //                                     $sessionEndTime = $session->end_time;
    //                                 }

    //                                 array_push($sessionsTemp, [
    //                                     'session_id' => $session->id,
    //                                     'start_time' => $session->start_time,
    //                                     'end_time' => $sessionEndTime,
    //                                     'title' => $session->title,
    //                                     'speakers_mini_headshot' => $getSpeakersHeadshot,
    //                                     'sponsor_mini_logo' => $session->sponsor->logo->file_url ?? null,
    //                                 ]);
    //                             }
    //                         }
    //                     }

    //                     usort($sessionsTemp, function ($a, $b) {
    //                         return strtotime($a['start_time']) - strtotime($b['start_time']);
    //                     });

    //                     $dateTemp = Carbon::parse($uniqueDate);
    //                     $formattedDate = $dateTemp->format('D d M');
    //                     array_push($categorizedSessionsByDate, [
    //                         'sessions_date' => $formattedDate,
    //                         'sessions' => $sessionsTemp,
    //                     ]);
    //                 }

    //                 $startDate = Carbon::parse($uniqueDates[0]);
    //                 $endDate = Carbon::parse(end($uniqueDates));

    //                 if ($startDate == $endDate) {
    //                     $formattedDate = $startDate->format('d F Y');
    //                 } else {
    //                     if ($startDate->format('F') === $endDate->format('F')) {
    //                         $formattedDate = $startDate->format('d') . '-' . $endDate->format('d F Y');
    //                     } else {
    //                         $formattedDate = $startDate->format('d F') . '-' . $endDate->format('d F Y');
    //                     }
    //                 }

    //                 if (count($categorizedSessionsByDate) > 0) {
    //                     array_push($data, [
    //                         'program_name' => $feature->short_name,
    //                         'program_banner' => Media::where('id', $feature->banner_media_id)->value('file_url'),
    //                         'program_date' => $formattedDate,
    //                         'startDate' => $startDate,
    //                         'endDate' => $endDate,
    //                         'session_dates' => $categorizedSessionsByDate,
    //                     ]);
    //                 }
    //             }
    //         }

    //         usort($data, function ($a, $b) {
    //             return strtotime($a['startDate']) - strtotime($b['startDate']);
    //         });

    //         return $this->success($data, "Sessions list", 200);
    //     } catch (\Exception $e) {
    //         return $this->error($e, "An error occurred while getting the session list", 500);
    //     }
    // }

    public function apiEventSessionDetail($apiCode, $eventCategory, $eventId, $attendeeId, $sessionId)
    {
        try {
            $session = Session::with(['feature', 'sponsor.logo'])->where('id', $sessionId)->where('event_id', $eventId)->where('is_active', true)->first();
            $defaultBgColor = Event::where('id', $eventId)->value('primary_bg_color');

            if (!$session) {
                return $this->error(null, "Session doesn't exist", 404);
            }

            $sessionSpeakerTypes = SessionSpeakerType::where('event_id', $eventId)->where('session_id', $sessionId)->orderBy('datetime_added', 'ASC')->get();

            $sessionSpeakerCategorized = array();
            if ($sessionSpeakerTypes->isNotEmpty()) {
                foreach ($sessionSpeakerTypes as $sessionSpeakerType) {
                    $sessionSpeakers = SessionSpeaker::where('event_id', $eventId)->where('session_id', $sessionId)->where('session_speaker_type_id', $sessionSpeakerType->id)->get();

                    if ($sessionSpeakers->isNotEmpty()) {
                        $categorizedSpeakers = array();

                        foreach ($sessionSpeakers as $sessionSpeaker) {
                            $speaker = Speaker::where('id', $sessionSpeaker->speaker_id)->where('event_id', $eventId)->where('is_active', true)->first();

                            if ($speaker) {
                                array_push($categorizedSpeakers, [
                                    'speaker_id' => $speaker->id,
                                    'full_name'  => trim(implode(' ', array_filter([
                                        $speaker->salutation,
                                        $speaker->first_name,
                                        $speaker->middle_name,
                                        $speaker->last_name
                                    ]))),

                                    //to be removed
                                    'salutation' => $speaker->salutation,
                                    'first_name' => $speaker->first_name,
                                    'middle_name' => $speaker->middle_name,
                                    'last_name' => $speaker->last_name,

                                    'company_name' => $speaker->company_name,
                                    'job_title' => $speaker->job_title,
                                    'pfp' => $speaker->pfp->file_url ?? null,
                                ]);
                            }
                        }

                        if (count($categorizedSpeakers) > 0) {
                            array_push($sessionSpeakerCategorized, [
                                'speaker_type_name' => $sessionSpeakerType->name,
                                'text_color' => $sessionSpeakerType->text_color ?? "#ffffff",
                                'background_color' => $sessionSpeakerType->background_color ?? $defaultBgColor,
                                'speakers' => $categorizedSpeakers,
                            ]);
                        }
                    }
                }
            }

            if ($session->end_time == "none") {
                $sessionEndTime = "onwards";
            } else {
                $sessionEndTime = $session->end_time;
            }

            $finalSessionDate = Carbon::parse($session->session_date)->format('F d, Y') . ' | ' . Carbon::parse($session->session_date)->format('l') . ' | ' . $session->session_day;
            $finalCategory = "";
            if ($session->feature_id == 0) {
                $finalCategory = $session->event->short_name;
            } else {
                $finalCategory = $session->feature->short_name;
            }

            $data = [
                'session_id' => $session->id,
                'title' => $session->title,
                'description_html_text' => $session->description_html_text,
                'session_time_merged' => $session->start_time . ' - ' . $sessionEndTime,
                'location' => $session->location,
                'session_date_merged' => $finalSessionDate,

                'session_date' => $session->session_date,
                'start_time' => $session->start_time,
                'end_time' => $session->end_time,
                'session_week_day' => Carbon::parse($session->session_date)->format('l'),
                'session_day' => $session->session_day,

                'session_category' => $finalCategory,

                'session_type' => $session->session_type,
                'sponsored_by' => $session->sponsor->logo->file_url ?? null,
                'is_favorite' => AttendeeFavoriteSession::where('event_id', $eventId)->where('attendee_id', $attendeeId)->where('session_id', $sessionId)->exists(),
                'favorite_count' => AttendeeFavoriteSession::where('event_id', $eventId)->where('session_id', $sessionId)->count(),
                'sessionSpeakerCategorized' => $sessionSpeakerCategorized,
            ];

            return $this->success($data, "Session details", 200);
        } catch (\Exception $e) {
            return $this->error($e, "An error occurred while getting the session details", 500);
        }
    }


    public function apiEventSessionMarkAsFavorite(Request $request, $apiCode, $eventCategory, $eventId, $attendeeId)
    {
        $validator = Validator::make($request->all(), [
            'attendeeId' => 'required|exists:attendees,id',
            'sessionId' => 'required|exists:sessions,id',
            'isFavorite' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorValidation($validator->errors());
        }

        try {
            $favorite = AttendeeFavoriteSession::where('event_id', $eventId)->where('attendee_id', $request->attendeeId)->where('session_id', $request->sessionId)->first();

            if ($request->isFavorite) {
                if (!$favorite) {
                    AttendeeFavoriteSession::create([
                        'event_id' => $eventId,
                        'attendee_id' => $request->attendeeId,
                        'session_id' => $request->sessionId,
                    ]);
                }
            } else {
                if ($favorite) {
                    $favorite->delete();
                }
            }
            return $this->success(null, "Session favorite status updated successfully", 200);
        } catch (\Exception $e) {
            return $this->error($e, "An error occurred while updating the favorite status", 500);
        }
    }
}
