<?php

namespace App\Http\Controllers;

use App\Enums\MediaEntityTypes;
use App\Models\Attendee;
use App\Models\AttendeeDeviceToken;
use App\Models\AttendeeNotification;
use App\Models\Event;
use App\Models\Exhibitor;
use App\Models\Feature;
use App\Models\Media;
use App\Models\MediaPartner;
use App\Models\MeetingRoomPartner;
use App\Models\Session;
use App\Models\SessionSpeaker;
use App\Models\SingleConversation;
use App\Models\SingleConversationMessage;
use App\Models\Speaker;
use App\Models\Sponsor;
use App\Models\SponsorType;
use App\Traits\HttpResponses;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EventController extends Controller
{
    use HttpResponses;
    // =========================================================
    //                       RENDER VIEWS
    // =========================================================

    public function mainDashboardView()
    {
        return view('admin.home.dashboard', [
            "pageTitle" => "Dashboard"
        ]);
    }

    public function eventsView()
    {
        $finalEvents = [];
        $events = Event::with('eventLogo')->orderBy('event_start_date', 'desc')->get();

        if ($events->isNotEmpty()) {
            foreach ($events as $event) {
                $eventFormattedDate =  Carbon::parse($event->event_start_date)->format('d M Y') . ' - ' . Carbon::parse($event->event_end_date)->format('d M Y');
                array_push($finalEvents, [
                    'eventId' => $event->id,
                    'eventLogo' => $event->eventLogo->file_url,
                    'eventName' => $event->full_name,
                    'eventCategory' => $event->category,
                    'eventLocation' => $event->location,
                    'eventDate' => $eventFormattedDate,
                ]);
            }
        }

        return view('admin.home.events', [
            "pageTitle" => "Events",
            "finalEvents" => $finalEvents,
        ]);
    }

    public function addEventView()
    {
        return view('admin.home.add.add_event', [
            "pageTitle" => "Add event",
        ]);
    }

    public function eventDashboardView($eventCategory, $eventId)
    {
        $eventName = Event::where('id', $eventId)->where('category', $eventCategory)->value('full_name');
        $finalData = $this->eventDashboardData($eventId);
        return view('admin.event.dashboard.dashboard', [
            "pageTitle" => "Dashboard",
            "eventName" => $eventName,
            "eventCategory" => $eventCategory,
            "eventId" => $eventId,
            "finalData" => $finalData,
        ]);
    }

    public function eventDetailsView($eventCategory, $eventId)
    {
        $event = Event::with(['eventLogo', 'eventLogoInverted', 'appSponsorLogo', 'eventSplashScreen', 'eventBanner', 'appSponsorBanner'])->where('id', $eventId)->first();

        $finalEventStartDate = Carbon::parse($event->event_start_date)->format('d M Y');
        $finalEventEndDate = Carbon::parse($event->event_end_date)->format('d M Y');

        return view('admin.event.details.details', [
            "pageTitle" => "Event details",
            "eventName" => $event->full_name,
            "eventCategory" => $eventCategory,
            "eventId" => $eventId,
            "eventData" => [
                "eventCategory" => $eventCategory,
                "eventId" => $eventId,
                "eventDetails" => [
                    'full_name' => $event->full_name,
                    'short_name' => $event->short_name,
                    'category' => $event->category,
                    'location' => $event->location,
                    'edition' => $event->edition,

                    'event_full_link' => $event->event_full_link,
                    'event_short_link' => $event->event_short_link,

                    'event_start_date' => $event->event_start_date,
                    'event_end_date' => $event->event_end_date,

                    'timezone' => $event->timezone,

                    'finalEventStartDate' => $finalEventStartDate,
                    'finalEventEndDate' => $finalEventEndDate,

                    'is_visible_in_the_app' => $event->is_visible_in_the_app,
                    'is_accessible_in_the_app' => $event->is_accessible_in_the_app,

                    'year' => $event->year,
                ],
                "eventColors" => [
                    'primary_bg_color' => $event->primary_bg_color,
                    'secondary_bg_color' => $event->secondary_bg_color,
                    'primary_text_color' => $event->primary_text_color,
                    'secondary_text_color' => $event->secondary_text_color,
                ],
                "eventHTMLTexts" => [
                    'description_html_text' => $event->description_html_text,
                    'login_html_text' => $event->login_html_text,
                    'continue_as_guest_html_text' => $event->continue_as_guest_html_text,
                    'forgot_password_html_text' => $event->forgot_password_html_text,
                ],
                "eventWebViewLinks" => [
                    'delegate_feedback_survey_link' => $event->delegate_feedback_survey_link,
                    'app_feedback_survey_link' => $event->app_feedback_survey_link,
                    'about_event_link' => $event->about_event_link,
                    'venue_link' => $event->venue_link,
                    'press_releases_link' => $event->press_releases_link,
                    'slido_link' => $event->slido_link,
                    'shuttle_bus_schedule_link' => $event->shuttle_bus_schedule_link,
                ],
                "eventFloorPlanLinks" => [
                    'floor_plan_3d_image_link' => $event->floor_plan_3d_image_link,
                    'floor_plan_exhibition_image_link' => $event->floor_plan_exhibition_image_link,
                    'interactive_map_link' => $event->interactive_map_link,
                ],
                "eventAssets" => [
                    'event_logo' => [
                        'media_id' => $event->event_logo_media_id,
                        'media_usage_id' => getMediaUsageId($event->event_logo_media_id, MediaEntityTypes::EVENT_LOGO->value, $event->id),
                        'url' => $event->eventLogo->file_url ?? null,
                    ],
                    'event_logo_inverted' => [
                        'media_id' => $event->event_logo_inverted_media_id,
                        'media_usage_id' => getMediaUsageId($event->event_logo_inverted_media_id, MediaEntityTypes::EVENT_LOGO_INVERTED->value, $event->id),
                        'url' => $event->eventLogoInverted->file_url ?? null,
                    ],
                    'app_sponsor_logo' => [
                        'media_id' => $event->app_sponsor_logo_media_id,
                        'media_usage_id' => getMediaUsageId($event->app_sponsor_logo_media_id, MediaEntityTypes::EVENT_APP_SPONSOR_LOGO->value, $event->id),
                        'url' => $event->appSponsorLogo->file_url ?? null,
                    ],

                    'event_splash_screen' => [
                        'media_id' => $event->event_splash_screen_media_id,
                        'media_usage_id' => getMediaUsageId($event->event_splash_screen_media_id, MediaEntityTypes::EVENT_SPLASH_SCREEN->value, $event->id),
                        'url' => $event->eventSplashScreen->file_url ?? null,
                    ],
                    'event_banner' => [
                        'media_id' => $event->event_banner_media_id,
                        'media_usage_id' => getMediaUsageId($event->event_banner_media_id, MediaEntityTypes::EVENT_BANNER->value, $event->id),
                        'url' => $event->eventBanner->file_url ?? null,
                    ],
                    'app_sponsor_banner' => [
                        'media_id' => $event->app_sponsor_banner_media_id,
                        'media_usage_id' => getMediaUsageId($event->app_sponsor_banner_media_id, MediaEntityTypes::EVENT_APP_SPONSOR_BANNER->value, $event->id),
                        'url' => $event->appSponsorBanner->file_url ?? null,
                    ],
                ],
            ],
        ]);
    }

    public function eventDashboardData($eventId)
    {
        $totalAttendees = 0;
        $totalSpeakers = 0;
        $totalSessions = 0;
        $totalSponsors = 0;
        $totalExhibitors = 0;
        $totalMrps = 0;
        $totalMps = 0;
        $totalConversations = 0;
        $totalChats = 0;
        $totalLogins = 0;


        $totalAttendees = Attendee::where('event_id', $eventId)->count();
        $totalSpeakers = Speaker::where('event_id', $eventId)->count();
        $totalSessions = Session::where('event_id', $eventId)->count();
        $totalSponsors = Sponsor::where('event_id', $eventId)->count();
        $totalExhibitors = Exhibitor::where('event_id', $eventId)->count();
        $totalMrps = MeetingRoomPartner::where('event_id', $eventId)->count();
        $totalMps = MediaPartner::where('event_id', $eventId)->count();
        $totalLogins = AttendeeDeviceToken::where('event_id', $eventId)->distinct('attendee_id')->count('attendee_id');

        $event = Event::where('id', $eventId)->first();

        if ($event->category == "SCC2") {
            $chatStats = getFirestoreChatStats($eventId);
            $finalTotalConversations = $chatStats['totalConversations'];
            $finalTotalChats = $chatStats['totalChats'];
        } else {
            $singleConversations = SingleConversation::where('event_id', $eventId)->get();

            if ($singleConversations->isNotEmpty()) {
                foreach ($singleConversations as $singleConversation) {
                    $totalConversations++;
                    $totalChats = $totalChats + SingleConversationMessage::where('single_conversation_id', $singleConversation->id)->count();
                }
            }

            $finalTotalConversations = $totalConversations;
            $finalTotalChats = $totalChats;
        }

        $finalData = [
            'totalAttendees' => $totalAttendees,
            'totalSpeakers' => $totalSpeakers,
            'totalSessions' => $totalSessions,
            'totalSponsors' => $totalSponsors,
            'totalExhibitors' => $totalExhibitors,
            'totalMrps' => $totalMrps,
            'totalMps' => $totalMps,
            'totalConversations' => $finalTotalConversations,
            'totalChats' => $finalTotalChats,
            'totalLogins' => $totalLogins,
        ];

        return $finalData;
    }







    // =========================================================
    //                       API FUNCTIONS
    // =========================================================
    public function apiEventsList()
    {
        try {
            $events = Event::with(['eventLogo', 'eventSplashScreen'])->where('is_visible_in_the_app', true)->get();

            if ($events->isEmpty()) {
                return $this->error(null, "There's no events yet.", 404);
            }

            $data = array();

            foreach ($events as $event) {
                $start = Carbon::parse($event->event_start_date);
                $end   = Carbon::parse($event->event_end_date);

                if ($start->isSameDay($end)) {
                    $eventDateText = $start->format('d F Y');
                } elseif ($start->isSameMonth($end) && $start->isSameYear($end)) {
                    $eventDateText = $start->format('d') . '-' . $end->format('d F Y');
                } elseif ($start->isSameYear($end)) {
                    $eventDateText = $start->format('d F') . ' - ' . $end->format('d F Y');
                } else {
                    $eventDateText = $start->format('d F Y') . ' - ' . $end->format('d F Y');
                }


                if ($event->category == "AF") {
                    // $eventSplashScreenSecond = "https://www.gpcaforum.com/wp-content/uploads/2025/12/AF-side-table-screen_2_temp.png";
                    $eventSplashScreenSecond = "https://www.gpcaforum.com/wp-content/uploads/2025/10/AF-splash-screen_2.jpg";
                } else {
                    $eventSplashScreenSecond = null;
                }

                array_push($data, [
                    'id' => $event->id,
                    'category' => $event->category,

                    'short_name' => $event->short_name,
                    'edition' => $event->edition,
                    'location' => $event->location,
                    'event_date' => $eventDateText,

                    'event_logo' => $event->eventLogo->file_url ?? null,
                    'event_splash_screen' => $event->eventSplashScreen->file_url ?? null,
                    'event_splash_screen_secondary' => $eventSplashScreenSecond,

                    'login_html_text' => $event->login_html_text,
                    'forgot_password_html_text' => $event->forgot_password_html_text,

                    'primary_bg_color' => $event->primary_bg_color,
                    'secondary_bg_color' => $event->secondary_bg_color,
                    'primary_text_color' => $event->primary_text_color,
                    'secondary_text_color' => $event->secondary_text_color,

                    'is_accessible_in_the_app' => $event->is_accessible_in_the_app,
                ]);
            }

            return $this->success($data, "Events List", 200);
        } catch (\Exception $e) {
            return $this->error($e, "An error occurred while getting the list of events", 500);
        }
    }

    public function apiEventHomepage($apiCode, $eventCategory, $eventId, $attendeeId)
    {
Log::info('AUTH USER API Event Homepage', ['user' => auth()->user()]);

        try {
            $event = Event::with(['eventLogoInverted', 'eventBanner', 'appSponsorLogo'])->where('id', $eventId)->where('category', $eventCategory)->first();
            // $attendee = Attendee::with('pfp')->where('id', $attendeeId)->where('event_id', $eventId)->first();
            $attendee = auth()->user();
            $attendeeNotificationsCount = AttendeeNotification::with('notification')->where('event_id', $eventId)->where('attendee_id', $attendee->id)->where('is_seen', false)->count();



            $data = [
                // 'event_logo_inverted' => $event->eventLogoInverted->file_url ?? null,
                'event_logo_inverted' => optional($event->eventLogoInverted)->file_url,
                // 'event_banner' => $event->eventBanner->file_url ?? null,
                'event_banner' => optional($event->eventBanner)->file_url,
                'app_sponsor_logo' => $event->appSponsorLogo->file_url ?? null,
                'attendee_details' => [
                    // 'pfp' => $attendee->pfp->file_url ?? asset('assets/images/feature-image-placeholder.jpg'),
                    'pfp' => optional($attendee->pfp)->file_url ?? asset('assets/images/feature-image-placeholder.jpg'),
                    'salutation' => $attendee->salutation,
                    'first_name' => $attendee->first_name,
                    'middle_name' => $attendee->middle_name,
                    'last_name' => $attendee->last_name,
                    'email_address' => $attendee->email_address,
                ],
                'speakers' => $this->apiGetSpeakersList($eventCategory, $eventId),
                'programs' => $this->apiGetProgramsList($eventCategory, $eventId),
                'sponsors' => $this->apiGetSponsorsList($eventCategory, $eventId),
                'exhibitors' => $this->apiGetExhibitorsList($eventCategory, $eventId),
                'meeting_room_partners' => $this->apiGetMrpsList($eventCategory, $eventId),
                'media_partners' => $this->apiGetMpsList($eventCategory, $eventId),

                'notification_count' => $attendeeNotificationsCount,

                'webview' => [
                    'delegate_feedback_survey_link' => $event->delegate_feedback_survey_link,
                    'app_feedback_survey_link' => $event->app_feedback_survey_link,
                    'about_event_link' => $event->about_event_link,
                    'venue_link' => $event->venue_link,
                    'press_releases_link' => $event->press_releases_link,
                ],

                'floor_plan' => [
                    'floor_plan_3d_image_link' => $event->floor_plan_3d_image_link,
                    'floor_plan_exhibition_image_link' => $event->floor_plan_exhibition_image_link,
                ],
                // 'notifications' => $this->apiGetNotifications($eventCategory, $eventId, $attendeeId),
                'notifications' => $this->apiGetNotifications($eventCategory, $eventId, $attendee->id),
            ];
            return $this->success($data, "Event Homepage details", 200);
        } catch (\Exception $e) {
            return $this->error($e, "An error occurred while getting the event details", 500);
        }
    }

    public function apiEventHomepagev2($apiCode, $eventCategory, $eventId, $attendeeId)
    {


        try {
            $event = Event::with(['eventBanner'])->where('id', $eventId)->where('category', $eventCategory)->first();
            if (!$event) {
                Log::error('EVENT NOT FOUND', compact('eventId', 'eventCategory'));
                return $this->error(null, "Event not found", 404);
            }
            // $attendee = Attendee::with('pfp')->where('id', $attendeeId)->where('event_id', $eventId)->first();
            $attendee = auth()->user();
            $attendeeNotificationsCount = AttendeeNotification::with('notification')->where('event_id', $eventId)->where('attendee_id', $attendee->id)->where('is_seen', false)->count();

            if ($eventCategory == "PC") {
                $sponsorsBannerCarousel = [
                    "https://gpca.org.ae/conferences/scc/wp-content/uploads/2025/05/8.jpg",
                    "https://gpca.org.ae/conferences/scc/wp-content/uploads/2025/05/9.jpg",
                    "https://gpca.org.ae/conferences/scc/wp-content/uploads/2025/05/10.jpg"
                ];
            } else if ($eventCategory == "SCC") {
                $sponsorsBannerCarousel = [
                    "http://gpca.org.ae/conferences/anc/wp-content/uploads/2025/08/exhibitor1.jpg",
                    "https://gpca.org.ae/conferences/scc/wp-content/uploads/2025/05/2.jpg",
                    "https://gpca.org.ae/conferences/scc/wp-content/uploads/2025/05/3.jpg",
                    "https://gpca.org.ae/conferences/scc/wp-content/uploads/2025/05/4.jpg",
                    "https://gpca.org.ae/conferences/scc/wp-content/uploads/2025/05/5.jpg",
                    "https://gpca.org.ae/conferences/scc/wp-content/uploads/2025/05/6.jpg",
                    "https://gpca.org.ae/conferences/scc/wp-content/uploads/2025/05/7.jpg",
                    "https://gpca.org.ae/conferences/scc/wp-content/uploads/2025/05/8.jpg",
                    "https://gpca.org.ae/conferences/scc/wp-content/uploads/2025/05/9.jpg",
                    "https://gpca.org.ae/conferences/scc/wp-content/uploads/2025/05/10.jpg",
                ];
            } else if ($eventCategory == "ANC") {
                $sponsorsBannerCarousel = [
                    "http://gpca.org.ae/conferences/anc/wp-content/uploads/2025/08/sponsor1.jpg",
                    "http://gpca.org.ae/conferences/anc/wp-content/uploads/2025/08/sponsor2.jpg",
                    "http://gpca.org.ae/conferences/anc/wp-content/uploads/2025/08/sponsor3.jpg",
                    "http://gpca.org.ae/conferences/anc/wp-content/uploads/2025/08/sponsor4.jpg",
                    "http://gpca.org.ae/conferences/anc/wp-content/uploads/2025/08/sponsor5.jpg",
                    "http://gpca.org.ae/conferences/anc/wp-content/uploads/2025/08/exhibitor1.jpg",
                    "http://gpca.org.ae/conferences/anc/wp-content/uploads/2025/09/exhibitor2.jpg",
                    "http://gpca.org.ae/conferences/anc/wp-content/uploads/2025/09/exhibitor3.jpg",
                    "http://gpca.org.ae/conferences/anc/wp-content/uploads/2025/09/exhibitor4.jpg",
                    "http://gpca.org.ae/conferences/anc/wp-content/uploads/2025/09/mrp1.jpg",
                    "http://gpca.org.ae/conferences/anc/wp-content/uploads/2025/09/event1.jpg",
                    "http://gpca.org.ae/conferences/anc/wp-content/uploads/2025/08/event2.jpg",
                ];
            } else if ($eventCategory == "RCC") {
                $sponsorsBannerCarousel = [
                    "http://gpca.org.ae/conferences/rcc/wp-content/uploads/2025/10/sponsor1.jpg",
                    "http://gpca.org.ae/conferences/rcc/wp-content/uploads/2025/10/event1.jpg",
                ];
            } else if ($eventCategory == "RIC") {
                $sponsorsBannerCarousel = [
                    "http://gpca.org.ae/conferences/rcc/wp-content/uploads/2025/10/sponsor1.jpg",
                    "http://gpca.org.ae/conferences/rcc/wp-content/uploads/2025/10/event1.jpg",
                ];
            } else {
                $sponsorsBannerCarousel = [
                    "https://www.gpcaforum.com/wp-content/uploads/2025/12/slide1.jpg",
                    "https://www.gpcaforum.com/wp-content/uploads/2025/10/slide2.jpg",
                    "https://www.gpcaforum.com/wp-content/uploads/2025/10/slide3.jpg",
                    "https://www.gpcaforum.com/wp-content/uploads/2025/10/slide4.jpg",
                    "https://www.gpcaforum.com/wp-content/uploads/2025/10/slide5.jpg",
                    "https://www.gpcaforum.com/wp-content/uploads/2025/10/slide6.jpg",
                    "https://www.gpcaforum.com/wp-content/uploads/2025/10/slide7.jpg",
                    "https://www.gpcaforum.com/wp-content/uploads/2025/11/slide8.jpg",
                    "https://www.gpcaforum.com/wp-content/uploads/2025/10/slide9.jpg",
                    "https://gpcachem.org/wp-content/uploads/2025/11/slide10.jpg",
                ];
            }

            $eventFeatures = [
                [
                    "image" => "https://www.gpcaforum.com/wp-content/uploads/2025/10/sus-pav.png",
                    "title" => "GPCA Sustainability Pavilion",
                    "link" => "https://www.gpcaforum.com/gpca-sustainability-pavilion/"
                ],
                [
                    "image" => "https://www.gpcaforum.com/wp-content/uploads/2025/10/youth-forum.png",
                    "title" => "GPCA Youth Forum",
                    "link" => "https://www.gpcaforum.com/gpca-youth-forum/"
                ],
                [
                    "image" => "https://www.gpcaforum.com/wp-content/uploads/2025/10/sol-x.png",
                    "title" => "Solutions Xchange",
                    "link" => "https://www.gpcaforum.com/solutions-xchange/"
                ],
                [
                    "image" => "https://www.gpcaforum.com/wp-content/uploads/2025/10/nexus.png",
                    "title" => "GPCA Startup Nexus",
                    "link" => "https://www.gpcaforum.com/gpca-startup-nexus/"
                ],
                [
                    "image" => "https://www.gpcaforum.com/wp-content/uploads/2025/10/vip-maj.png",
                    "title" => "VIP Majlis",
                    "link" => "https://www.gpcaforum.com/vip-majlis/"
                ],
                [
                    "image" => "https://www.gpcaforum.com/wp-content/uploads/2025/10/legacy.png",
                    "title" => "Legacy Awards",
                    "link" => "https://gpcachem.org/gpca-legacy-initiative-awards/"
                ],
                // [
                //     "image" => "https://www.gpcaforum.com/wp-content/uploads/2025/10/cult-maj.png",
                //     "title" => "Cultural Majlis",
                //     "link" => "https://www.gpcaforum.com/cultural-majlis/"
                // ],
            ];

            $socialNetworkings = [
                [
                    "image" => "https://www.gpcaforum.com/wp-content/uploads/2025/10/networking-1.jpg",
                    "title" => "Networking",
                    "link" => "https://www.gpcaforum.com/networking/"
                ],
                [
                    "image" => "https://www.gpcaforum.com/wp-content/uploads/2025/10/welcome_dinner-1.jpg",
                    "title" => "Welcome Dinner",
                    "link" => "https://www.gpcaforum.com/networking/"
                ],
                [
                    "image" => "https://www.gpcaforum.com/wp-content/uploads/2025/11/gala_dinner-v2.jpg",
                    "title" => "Gala Dinner",
                    "link" => "https://www.gpcaforum.com/networking/"
                ],
            ];

            $data = [
                // 'event_banner' => $event->eventBanner->file_url ?? null,
                'event_banner' => optional($event->eventBanner)->file_url,
                'sponsors_banner_carousel' => $sponsorsBannerCarousel,
                'attendee_details' => [
                    // 'pfp' => $attendee->pfp->file_url ?? asset('assets/images/feature-image-placeholder.jpg'),
                    'pfp' => optional($attendee->pfp)->file_url ?? asset('assets/images/feature-image-placeholder.jpg'),
                    'salutation' => $attendee->salutation,
                    'first_name' => $attendee->first_name,
                    'middle_name' => $attendee->middle_name,
                    'last_name' => $attendee->last_name,
                    'job_title' => $attendee->job_title,
                ],
                'speakers' => $this->apiGetSpeakersListv2($eventId),
                'programs' => $this->apiGetProgramsListv2($eventId),
                'notification_count' => $attendeeNotificationsCount,

                'webview' => [
                    'delegate_feedback_survey_link' => $event->delegate_feedback_survey_link,
                    'app_feedback_survey_link' => $event->app_feedback_survey_link,
                    'about_event_link' => $event->about_event_link,
                    'venue_link' => $event->venue_link,
                    'press_releases_link' => $event->press_releases_link,
                    'slido_link' => $event->slido_link,
                    'shuttle_bus_schedule_link' => $event->shuttle_bus_schedule_link,
                ],

                'floor_plan' => [
                    'floor_plan_3d_image_link' => $event->floor_plan_3d_image_link,
                    'floor_plan_exhibition_image_link' => $event->floor_plan_exhibition_image_link,
                    'interactive_map_link' => $event->interactive_map_link,
                ],

                'event_features' => $eventFeatures,
                'social_networkings' => $socialNetworkings,
            ];
            return $this->success($data, "Event Homepage details", 200);
        } catch (\Exception $e) {
            return $this->error($e, "An error occurred while getting the event details", 500);
        }
    }


    public function apiGetSpeakersListv2($eventId)
    {
        $speakers = Speaker::with(['pfp'])->where('event_id', $eventId)->where('is_active', true)->orderBy('datetime_added', 'ASC')->limit(20)->get();
        $data = array();

        foreach ($speakers as $speaker) {
            if ($speaker->feature_id == 0) {
                array_push($data, [
                    'id' => $speaker->id,
                    'full_name'  => trim(implode(' ', array_filter([
                        $speaker->salutation,
                        $speaker->first_name,
                        $speaker->middle_name,
                        $speaker->last_name
                    ]))),
                    'company_name' => $speaker->company_name,
                    'job_title' => $speaker->job_title,
                    'pfp' => $speaker->pfp->file_url ?? null,
                ]);
            }
        }
        return $data;
    }

    public function apiGetProgramsListv2($eventId)
    {
        $sessions = Session::where('event_id', $eventId)->where('is_active', true)->orderBy('session_date', 'ASC')->orderBy('start_time', 'ASC')->get();

        if ($sessions->isEmpty()) {
            return [];
        }

        $data = array();

        $grouped = $sessions->groupBy('session_date');

        foreach ($grouped as $date => $sessionsForDate) {
            $sessionsTemp = [];
            $sessionDay = $sessionsForDate->first()->session_day;

            foreach ($sessionsForDate->take(3) as $session) {
                $sessionsTemp[] = [
                    'start_time' => $session->start_time,
                    'title' => $session->title,
                ];
            }

            $finalSessionDate = Carbon::parse($date)->format('D d F Y');

            $data[] = [
                'session_day' => $sessionDay,
                'session_date' => $finalSessionDate,
                'sessions' => $sessionsTemp,
            ];
        }


        return $data;
    }

    public function apiGetSpeakersList($eventCategory, $eventId)
    {
        $speakers = Speaker::with(['pfp', 'speakerType'])->where('event_id', $eventId)->where('is_active', true)->orderBy('datetime_added', 'ASC')->get();
        $features = Feature::where('event_id', $eventId)->where('is_active', true)->orderBy('datetime_added', 'ASC')->get();
        $event = Event::where('id', $eventId)->where('category', $eventCategory)->first();

        if ($speakers->isEmpty()) {
            return [];
        }

        $data = array();
        $mainConferenceSpeakers = array();

        foreach ($speakers as $speaker) {
            if ($speaker->feature_id == 0) {
                array_push($mainConferenceSpeakers, [
                    'id' => $speaker->id,
                    'salutation' => $speaker->salutation,
                    'first_name' => $speaker->first_name,
                    'middle_name' => $speaker->middle_name,
                    'last_name' => $speaker->last_name,
                    'company_name' => $speaker->company_name,
                    'job_title' => $speaker->job_title,
                    'speaker_type_name' => $speaker->speakerType->name,
                    'pfp' => $speaker->pfp->file_url ?? null,
                ]);
            }
        }

        if (count($mainConferenceSpeakers) > 0) {
            array_push($data, [
                'speakerCategoryName' => "Main Conference",
                'speakerCategoryTextColor' => $event->primary_text_color,
                'speakerCategoryBackgroundColor' => $event->primary_bg_color,
                'speakers' => $mainConferenceSpeakers,
            ]);
        }

        foreach ($features as $feature) {
            $categorizedSpeakers = array();

            foreach ($speakers as $speaker) {
                if ($speaker->feature_id == $feature->id) {
                    array_push($categorizedSpeakers, [
                        'id' => $speaker->id,
                        'salutation' => $speaker->salutation,
                        'first_name' => $speaker->first_name,
                        'middle_name' => $speaker->middle_name,
                        'last_name' => $speaker->last_name,
                        'company_name' => $speaker->company_name,
                        'job_title' => $speaker->job_title,
                        'speaker_type_name' => $speaker->speakerType->name,
                        'pfp' => $speaker->pfp->file_url ?? null,
                    ]);
                }
            }

            if (count($categorizedSpeakers) > 0) {
                array_push($data, [
                    'speakerCategoryName' => $feature->short_name,
                    'speakerCategoryTextColor' => $feature->primary_text_color,
                    'speakerCategoryBackgroundColor' => $feature->primary_bg_color,
                    'speakers' => $categorizedSpeakers,
                ]);
            }
        }
        return $data;
    }

    public function apiGetProgramsList($eventCategory, $eventId)
    {
        $sessions = Session::with(['feature', 'sponsor.logo'])->where('event_id', $eventId)->where('is_active', true)->orderBy('session_date', 'ASC')->orderBy('datetime_added', 'ASC')->get();
        $features = Feature::where('event_id', $eventId)->where('is_active', true)->orderBy('datetime_added', 'ASC')->get();
        $event = Event::where('id', $eventId)->where('category', $eventCategory)->first();

        if ($sessions->isEmpty()) {
            return [];
        }

        $data = array();
        $categorizedSessionsByDate = array();
        $storeDatesCategoryTemp = [];

        // GET THE DATES FIRST
        $storeDatesCategoryTemp = [];
        foreach ($sessions as $session) {
            if ($session->feature_id == 0) {
                $date = $session->session_date;
                if (!isset($storeDatesCategoryTemp[$date])) {
                    $storeDatesCategoryTemp[$date] = true;
                }
            }
        }
        $uniqueDates = array_keys($storeDatesCategoryTemp);

        foreach ($uniqueDates as $uniqueDate) {
            $sessionsTemp = array();
            foreach ($sessions as $session) {
                if ($session->feature_id == 0) {
                    if ($session->session_date == $uniqueDate) {
                        $getSpeakersHeadshot = [];

                        $sessionSpeakersTemp = SessionSpeaker::where('event_id', $eventId)->where('session_id', $session->id)->get();

                        if ($sessionSpeakersTemp->isNotEmpty()) {
                            foreach ($sessionSpeakersTemp as $sessionSpeakerTemp) {
                                $speaker = Speaker::with('pfp')->where('event_id', $eventId)->where('id', $sessionSpeakerTemp->speaker_id)->first();
                                $getSpeakersHeadshot[] = $speaker->pfp->file_url ?? null;
                            }
                        }

                        if ($session->end_time == "none") {
                            $sessionEndTime = "onwards";
                        } else {
                            $sessionEndTime = $session->end_time;
                        }

                        array_push($sessionsTemp, [
                            'session_id' => $session->id,
                            'start_time' => $session->start_time,
                            'end_time' => $sessionEndTime,
                            'title' => $session->title,
                            'speakers_mini_headshot' => $getSpeakersHeadshot,
                            'sponsor_mini_logo' => $session->sponsor->logo->file_url ?? null,
                        ]);
                    }
                }
            }

            usort($sessionsTemp, function ($a, $b) {
                return strtotime($a['start_time']) - strtotime($b['start_time']);
            });

            $dateTemp = Carbon::parse($uniqueDate);
            $formattedDate = $dateTemp->format('D d M');
            array_push($categorizedSessionsByDate, [
                'sessions_date' => $formattedDate,
                'sessions' => $sessionsTemp,
            ]);
        }

        $startDate = Carbon::parse($uniqueDates[0]);
        $endDate = Carbon::parse(end($uniqueDates));

        if ($startDate == $endDate) {
            $formattedDate = $startDate->format('d F Y');
        } else {
            if ($startDate->format('F') === $endDate->format('F')) {
                $formattedDate = $startDate->format('d') . '-' . $endDate->format('d F Y');
            } else {
                $formattedDate = $startDate->format('d F') . '-' . $endDate->format('d F Y');
            }
        }

        array_push($data, [
            'program_name' => $event->short_name,
            'program_banner' => Media::where('id', $event->event_banner_media_id)->value('file_url'),
            'program_date' => $formattedDate,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'session_dates' => $categorizedSessionsByDate,
        ]);



        if ($features->isNotEmpty()) {
            foreach ($features as $feature) {
                $categorizedSessionsByDate = array();
                $storeDatesCategoryTemp = [];


                // GET THE DATES FIRST
                $storeDatesCategoryTemp = [];
                foreach ($sessions as $session) {
                    if ($session->feature_id == $feature->id) {
                        $date = $session->session_date;
                        if (!isset($storeDatesCategoryTemp[$date])) {
                            $storeDatesCategoryTemp[$date] = true;
                        }
                    }
                }
                $uniqueDates = array_keys($storeDatesCategoryTemp);


                foreach ($uniqueDates as $uniqueDate) {
                    $sessionsTemp = [];
                    foreach ($sessions as $session) {
                        if ($session->feature_id == $feature->id) {
                            if ($session->session_date == $uniqueDate) {
                                $getSpeakersHeadshot = [];

                                $sessionSpeakersTemp = SessionSpeaker::where('event_id', $eventId)->where('session_id', $session->id)->get();

                                if ($sessionSpeakersTemp->isNotEmpty()) {
                                    foreach ($sessionSpeakersTemp as $sessionSpeakerTemp) {
                                        $speaker = Speaker::with('pfp')->where('event_id', $eventId)->where('id', $sessionSpeakerTemp->speaker_id)->first();
                                        $getSpeakersHeadshot[] = $speaker->pfp->file_url ?? null;
                                    }
                                }

                                if ($session->end_time == "none") {
                                    $sessionEndTime = "onwards";
                                } else {
                                    $sessionEndTime = $session->end_time;
                                }

                                array_push($sessionsTemp, [
                                    'session_id' => $session->id,
                                    'start_time' => $session->start_time,
                                    'end_time' => $sessionEndTime,
                                    'title' => $session->title,
                                    'speakers_mini_headshot' => $getSpeakersHeadshot,
                                    'sponsor_mini_logo' => $session->sponsor->logo->file_url ?? null,
                                ]);
                            }
                        }
                    }

                    usort($sessionsTemp, function ($a, $b) {
                        return strtotime($a['start_time']) - strtotime($b['start_time']);
                    });

                    $dateTemp = Carbon::parse($uniqueDate);
                    $formattedDate = $dateTemp->format('D d M');
                    array_push($categorizedSessionsByDate, [
                        'sessions_date' => $formattedDate,
                        'sessions' => $sessionsTemp,
                    ]);
                }

                $startDate = Carbon::parse($uniqueDates[0]);
                $endDate = Carbon::parse(end($uniqueDates));

                if ($startDate == $endDate) {
                    $formattedDate = $startDate->format('d F Y');
                } else {
                    if ($startDate->format('F') === $endDate->format('F')) {
                        $formattedDate = $startDate->format('d') . '-' . $endDate->format('d F Y');
                    } else {
                        $formattedDate = $startDate->format('d F') . '-' . $endDate->format('d F Y');
                    }
                }

                if (count($categorizedSessionsByDate) > 0) {
                    array_push($data, [
                        'program_name' => $feature->short_name,
                        'program_banner' => Media::where('id', $feature->banner_media_id)->value('file_url'),
                        'program_date' => $formattedDate,
                        'startDate' => $startDate,
                        'endDate' => $endDate,
                        'session_dates' => $categorizedSessionsByDate,
                    ]);
                }
            }
        }

        usort($data, function ($a, $b) {
            return strtotime($a['startDate']) - strtotime($b['startDate']);
        });

        return $data;
    }

    public function apiGetSponsorsList($eventCategory, $eventId)
    {
        $sponsors = Sponsor::with('logo')->where('event_id', $eventId)->where('is_active', true)->orderBy('datetime_added', 'ASC')->get();
        $sponsorTypes = SponsorType::where('event_id', $eventId)->orderBy('datetime_added', 'ASC')->get();

        if ($sponsors->isEmpty()) {
            return [];
        }

        $data = array();

        foreach ($sponsorTypes as $sponsorType) {
            $categorizedSponsors = array();
            foreach ($sponsors as $sponsor) {
                if ($sponsorType->id == $sponsor->sponsor_type_id) {
                    array_push($categorizedSponsors, [
                        'id' => $sponsor->id,
                        'name' => $sponsor->name,
                        'website' => $sponsor->website,
                        'logo' => $sponsor->logo->file_url ?? null,
                    ]);
                }
            }

            if (count($categorizedSponsors) > 0) {
                array_push($data, [
                    'sponsorTypeName' => $sponsorType->name,
                    'sponsorTypeTextColor' => $sponsorType->text_color,
                    'sponsorTypeBackgroundColor' => $sponsorType->background_color,
                    'sponsors' => $categorizedSponsors,
                ]);
            }
        }

        return $data;
    }

    public function apiGetExhibitorsList($eventCategory, $eventId)
    {
        $exhibitors = Exhibitor::with('logo')->where('event_id', $eventId)->where('is_active', true)->orderBy('datetime_added', 'ASC')->get();

        if ($exhibitors->isEmpty()) {
            return [];
        }

        $data = array();
        foreach ($exhibitors as $exhibitor) {
            array_push($data, [
                'id' => $exhibitor->id,
                'name' => $exhibitor->name,
                'stand_number' => $exhibitor->stand_number,
                'logo' => $exhibitor->logo->file_url ?? null,
            ]);
        }
        return $data;
    }

    public function apiGetMrpsList($eventCategory, $eventId)
    {
        $meetingRoomPartners = MeetingRoomPartner::with('logo')->where('event_id', $eventId)->where('is_active', true)->orderBy('datetime_added', 'ASC')->get();

        if ($meetingRoomPartners->isEmpty()) {
            return [];
        }

        $data = array();
        foreach ($meetingRoomPartners as $meetingRoomPartner) {
            array_push($data, [
                'id' => $meetingRoomPartner->id,
                'name' => $meetingRoomPartner->name,
                'location' => $meetingRoomPartner->location,
                'website' => $meetingRoomPartner->website,
                'logo' => $meetingRoomPartner->logo->file_url ?? null,
            ]);
        }
        return $data;
    }

    public function apiGetMpsList($eventCategory, $eventId)
    {
        $mediaPartners = MediaPartner::with('logo')->where('event_id', $eventId)->where('is_active', true)->orderBy('datetime_added', 'ASC')->get();

        if ($mediaPartners->isEmpty()) {
            return [];
        }

        $data = array();
        foreach ($mediaPartners as $mediaPartner) {
            array_push($data, [
                'id' => $mediaPartner->id,
                'name' => $mediaPartner->name,
                'website' => $mediaPartner->website,
                'logo' => $mediaPartner->logo->file_url ?? null,
            ]);
        }
        return $data;
    }

    public function apiGetNotifications($eventCategory, $eventId, $attendeeId)
    {
        // $attendeeNotifications = AttendeeNotification::with('notification')->where('event_id', $eventId)->where('attendee_id', $attendeeId)->orderBy('sent_datetime', 'DESC')->get();
Log::info('AUTH USER API GetNotif', ['user' => auth()->user()]);

            $attendee = auth()->user();

    if (!$attendee) {
        return [];
    }

    $attendeeNotifications = AttendeeNotification::with('notification')
        ->where('event_id', $eventId)
        ->where('attendee_id', $attendee->id)
        ->orderBy('sent_datetime', 'DESC')
        ->get();


        if ($attendeeNotifications->isEmpty()) {
            return [];
        }

        $data = array();
        foreach ($attendeeNotifications as $attendeeNotification) {
            if ($attendeeNotification->notification_id != null) {
                array_push($data, [
                    'id' => $attendeeNotification->id,
                    'type' => $attendeeNotification->notification->type,
                    'title' => $attendeeNotification->notification->title,
                    'subtitle' => $attendeeNotification->notification->subtitle,
                    'message' => $attendeeNotification->notification->message,
                    'sent_datetime' => Carbon::parse($attendeeNotification->sent_datetime)->format('M j, Y g:i A'),
                    'is_seen' => $attendeeNotification->is_seen ? true : false,
                    'seen_datetime' => $attendeeNotification->seen_datetime,
                ]);
            } else {
                array_push($data, [
                    'id' => $attendeeNotification->id,
                    'type' => $attendeeNotification->type,
                    'title' => $attendeeNotification->title,
                    'subtitle' => $attendeeNotification->subtitle,
                    'message' => $attendeeNotification->message,
                    'sent_datetime' => Carbon::parse($attendeeNotification->sent_datetime)->format('M j, Y g:i A'),
                    'is_seen' => $attendeeNotification->is_seen ? true : false,
                    'seen_datetime' => $attendeeNotification->seen_datetime,
                ]);
            }
        }

        return $data;
    }
}
