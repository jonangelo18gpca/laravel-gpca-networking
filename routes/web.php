<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AttendeesController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ExhibitorController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MediaPartnerController;
use App\Http\Controllers\MeetingResponseController;
use App\Http\Controllers\MeetingRoomPartnerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SpeakerController;
use App\Http\Controllers\SponsorController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::prefix('admin')->group(function () {
    Route::middleware(['isAdmin'])->group(function () {
        Route::get('/logout', [AdminController::class, 'logout'])->name('admin.logout');
        Route::get('/dashboard', [EventController::class, 'mainDashboardView'])->name('admin.main-dashboard.view');
        Route::get('/event', [EventController::class, 'eventsView'])->name('admin.events.view');
        Route::get('/event/add', [EventController::class, 'addEventView'])->name('admin.add.event.view');
        Route::get('/media', [MediaController::class, 'mediaView'])->name('admin.media.view');

        Route::group(['middleware' => 'check.event.exists'], function () {
            Route::prefix('event/{eventCategory}/{eventId}')->group(function () {
                Route::get('/dashboard', [EventController::class, 'eventDashboardView'])->name('admin.event.dashboard.view');
                Route::get('/details', [EventController::class, 'eventDetailsView'])->name('admin.event.details.view');
                Route::get('/data-sync', [EventController::class, 'eventDataSyncView'])
    ->name('admin.event.data-sync.view');

                Route::prefix('attendee')->group(function () {
                    Route::get('/', [AttendeesController::class, 'eventAttendeesView'])->name('admin.event.attendees.view');
                    Route::get('/export', [AttendeesController::class, 'eventAttendeesExport'])->name('admin.event.attendees.export');
                    Route::get('/manage-welcome-email-notification', [AttendeesController::class, 'eventManageWelcomeEmailNotificationView'])->name('admin.event.manage.welcome.email.notif.view');
                    Route::get('/{attendeeId}', [AttendeesController::class, 'eventAttendeeView'])->name('admin.event.attendee.view');
                    Route::get('/add/from-api', [AttendeesController::class, 'eventAddAttendeeFromApiView'])->name('admin.event.add.attendee.from.api.view');
                });

                Route::prefix('/speaker')->group(function () {
                    Route::get('/', [SpeakerController::class, 'eventSpeakersView'])->name('admin.event.speakers.view');
                    Route::get('/type', [SpeakerController::class, 'eventSpeakerTypesView'])->name('admin.event.speaker.types.view');
                    Route::get('/{speakerId}', [SpeakerController::class, 'eventSpeakerView'])->name('admin.event.speaker.view');
                });

                Route::prefix('/session')->group(function () {
                    Route::get('/', [SessionController::class, 'eventSessionsView'])->name('admin.event.sessions.view');
                    Route::get('/{sessionId}', [SessionController::class, 'eventSessionView'])->name('admin.event.session.view');
                    Route::get('/{sessionId}/speaker/type', [SessionController::class, 'eventSessionSpeakerTypesView'])->name('admin.event.session.speaker.types.view');
                });

                Route::prefix('/sponsor')->group(function () {
                    Route::get('/', [SponsorController::class, 'eventSponsorsView'])->name('admin.event.sponsors.view');
                    Route::get('/type', [SponsorController::class, 'eventSponsorTypesView'])->name('admin.event.sponsor.types.view');
                    Route::get('/{sponsorId}', [SponsorController::class, 'eventSponsorView'])->name('admin.event.sponsor.view');
                });

                Route::prefix('/exhibitor')->group(function () {
                    Route::get('/', [ExhibitorController::class, 'eventExhibitorsView'])->name('admin.event.exhibitors.view');
                    Route::get('/{exhibitorId}', [ExhibitorController::class, 'eventExhibitorView'])->name('admin.event.exhibitor.view');
                });

                Route::prefix('/meeting-room-partner')->group(function () {
                    Route::get('/', [MeetingRoomPartnerController::class, 'eventMeetingRoomPartnersView'])->name('admin.event.meeting-room-partners.view');
                    Route::get('/{meetingRoomPartnerId}', [MeetingRoomPartnerController::class, 'eventMeetingRoomPartnerView'])->name('admin.event.meeting-room-partner.view');
                });

                Route::prefix('/media-partner')->group(function () {
                    Route::get('/', [MediaPartnerController::class, 'eventMediaPartnersView'])->name('admin.event.media-partners.view');
                    Route::get('/{mediaPartnerId}', [MediaPartnerController::class, 'eventMediaPartnerView'])->name('admin.event.media-partner.view');
                });

                Route::prefix('/notification')->group(function () {
                    Route::get('/', [NotificationController::class, 'eventNotificationsView'])->name('admin.event.notifications.view');
                });

                Route::prefix('/feature')->group(function () {
                    Route::get('/', [FeatureController::class, 'eventFeaturesView'])->name('admin.event.features.view');
                    Route::get('/{featureId}', [FeatureController::class, 'eventFeatureView'])->name('admin.event.feature.view');
                });
            });
        });
    });

    Route::get('/login', [AdminController::class, 'loginView'])->name('admin.login.view');
    Route::post('/login', [AdminController::class, 'login'])->name('admin.login.post');
});

Route::get('/test-push-notification', [NotificationController::class, 'testPushNotification'])->name('test-notif');

Route::group(['middleware' => 'check.event.exists'], function () {
    Route::prefix('event/{eventCategory}/{eventId}')->group(function () {
        Route::get('/meeting/{meetingId}/respond/{token}', [MeetingResponseController::class, 'meetingRespondView'])->middleware('throttle:10,1')->name('meeting.respond.view');
        Route::post('/meeting/{meetingId}/respond', [MeetingResponseController::class, 'meetingRespond'])->middleware('throttle:10,1')->name('meeting.respond.submit');
    });
});

Route::get('/clear-cache', function () {
    if (request('key') !== env('CACHE_CLEAR_KEY')) {
        return "Unauthorized";
    }

    Artisan::call('cache:clear');
    return "Cache cleared successfully";
});

