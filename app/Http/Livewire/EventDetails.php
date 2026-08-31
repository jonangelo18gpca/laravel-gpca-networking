<?php

namespace App\Http\Livewire;

use App\Enums\MediaEntityTypes;
use App\Enums\MediaUsageUpdateTypes;
use App\Models\Event as Events;
use App\Models\Media as Medias;
use Carbon\Carbon;
use DateTimeZone;
use Livewire\Component;


class EventDetails extends Component
{
    public $eventData, $eventCategories;

    // EDIT DETAILS
    public $full_name, $short_name, $category, $edition, $location, $event_full_link, $event_short_link, $event_start_date, $event_end_date, $timezone, $timezoneChoices;
    public $editEventDetailsForm;

    // EDIT COLORS
    public $primary_bg_color, $secondary_bg_color, $primary_text_color, $secondary_text_color;
    public $editEventColorsForm;

    // EDIT HTML Texts
    public $description_html_text, $login_html_text, $continue_as_guest_html_text, $forgot_password_html_text;
    public $editEventHTMLTextsForm;

    // EDIT WebView Links
    public $delegate_feedback_survey_link, $app_feedback_survey_link, $about_event_link, $venue_link, $press_releases_link, $slido_link, $shuttle_bus_schedule_link;
    public $editEventWebViewLinksForm;

    // EDIT Floor Plan image Links
    public $floor_plan_3d_image_link, $floor_plan_exhibition_image_link, $interactive_map_link;
    public $editEventFloorPlanLinksForm;

    // EDIT ASSETS
    public $assetType, $editEventAssetForm, $image_media_id, $image_placeholder_text;
    public $chooseImageModal, $mediaFileList = array(), $activeSelectedImage;



    // EDIT SPONSORS BANNER CAROUSEL
    // public $editSponsorsBannerCarouselForm = false;
    // public $carouselImages = [];

    public $sponsors_banner_carousel_text;
    public $editSponsorsBannerCarouselForm = false;

    public $vertical_images_section_text;
    public $editVerticalImagesSectionForm = false;

    protected $listeners = ['editEventDetailsConfirmed' => 'editEventDetails', 'editEventColorsConfirmed' => 'editEventColors', 'editEventHTMLTextsConfirmed' => 'editEventHTMLTexts', 'editEventWebViewLinksConfirmed' => 'editEventWebViewLinks', 'editEventFloorPlanLinksConfirmed' => 'editEventFloorPlanLinks', 'editEventAssetConfirmed' => 'editEventAsset', 'editSponsorsBannerCarouselConfirmed' => 'editSponsorsBannerCarousel','editVerticalImagesSectionConfirmed' => 'editVerticalImagesSection',];

    public function mount($eventData)
    {
        $this->eventCategories = config('app.eventCategories');
        $this->eventData = $eventData;
        $this->mediaFileList = getMediaFileList();
        $this->editEventAssetForm = false;
        $this->editEventDetailsForm = false;
        $this->editEventColorsForm = false;
        $this->editEventHTMLTextsForm = false;
        $this->editSponsorsBannerCarouselForm = false;
        $this->editVerticalImagesSectionForm = false;
    }

    public function render()
    {
        return view('livewire.event.details.event-details');
    }


    // TOGGLE VISIBILITY AND ACCESSIBILITY
    public function toggleVisibilityInTheApp()
    {

        Events::where('id', $this->eventData['eventId'])->update([
            'is_visible_in_the_app' => !$this->eventData['eventDetails']['is_visible_in_the_app'],
        ]);
        $this->eventData['eventDetails']['is_visible_in_the_app'] = !$this->eventData['eventDetails']['is_visible_in_the_app'];
    }

    public function toggleAccessibilityInTheApp()
    {
        Events::where('id', $this->eventData['eventId'])->update([
            'is_accessible_in_the_app' => !$this->eventData['eventDetails']['is_accessible_in_the_app'],
        ]);
        $this->eventData['eventDetails']['is_accessible_in_the_app'] = !$this->eventData['eventDetails']['is_accessible_in_the_app'];
    }


    // EDIT EVENT DETAILS
    public function showEditEventDetails()
    {
        $this->timezoneChoices = DateTimeZone::listIdentifiers();
        $this->full_name = $this->eventData['eventDetails']['full_name'];
        $this->short_name = $this->eventData['eventDetails']['short_name'];
        $this->category = $this->eventData['eventDetails']['category'];
        $this->edition = $this->eventData['eventDetails']['edition'];
        $this->location = $this->eventData['eventDetails']['location'];

        $this->event_full_link = $this->eventData['eventDetails']['event_full_link'];
        $this->event_short_link = $this->eventData['eventDetails']['event_short_link'];

        $this->event_start_date = $this->eventData['eventDetails']['event_start_date'];
        $this->event_end_date = $this->eventData['eventDetails']['event_end_date'];

        $this->timezone = $this->eventData['eventDetails']['timezone'];

        $this->editEventDetailsForm = true;
    }

    public function resetEditEventDetailsFields()
    {
        $this->editEventDetailsForm = false;

        $this->full_name = null;
        $this->short_name = null;
        $this->category = null;
        $this->edition = null;
        $this->location = null;

        $this->event_full_link = null;
        $this->event_short_link = null;

        $this->event_start_date = null;
        $this->event_end_date = null;

        $this->timezone = null;
        $this->timezoneChoices = null;
    }

    public function editEventDetailsConfirmation()
    {
        $this->validate([
            'category' => 'required',
            'full_name' => 'required',
            'short_name' => 'required',
            'location' => 'required',
            'edition' => 'required',
            'event_full_link' => 'required',
            'event_short_link' => 'required',
            'event_start_date' => 'required|date',
            'event_end_date' => 'required|date',
            'timezone' => 'required',
        ]);


        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure?',
            'text' => "",
            'buttonConfirmText' => "Yes, update it!",
            'livewireEmit' => "editEventDetailsConfirmed",
        ]);
    }

    public function editEventDetails()
    {
        $currentYear = strval(Carbon::parse($this->event_start_date)->year);

        Events::where('id', $this->eventData['eventId'])->update([
            'category' => $this->category,
            'full_name' => $this->full_name,
            'short_name' => $this->short_name,
            'location' => $this->location,
            'edition' => $this->edition,
            'event_full_link' => $this->event_full_link,
            'event_short_link' => $this->event_short_link,
            'event_start_date' => $this->event_start_date,
            'event_end_date' => $this->event_end_date,
            'timezone' => $this->timezone,
            'year' => $currentYear,
        ]);

        $this->eventData['category'] = $this->category;
        $this->eventData['eventDetails']['full_name'] = $this->full_name;
        $this->eventData['eventDetails']['short_name'] = $this->short_name;
        $this->eventData['eventDetails']['category'] = $this->category;
        $this->eventData['eventDetails']['location'] = $this->location;
        $this->eventData['eventDetails']['edition'] = $this->edition;
        $this->eventData['eventDetails']['event_full_link'] = $this->event_full_link;
        $this->eventData['eventDetails']['event_short_link'] = $this->event_short_link;
        $this->eventData['eventDetails']['event_short_link'] = $this->event_short_link;
        $this->eventData['eventDetails']['event_start_date'] = $this->event_start_date;
        $this->eventData['eventDetails']['event_end_date'] = $this->event_end_date;
        $this->eventData['eventDetails']['timezone'] = $this->timezone;
        $this->eventData['eventDetails']['finalEventStartDate'] = Carbon::parse($this->event_start_date)->format('d M Y');
        $this->eventData['eventDetails']['finalEventEndDate'] = Carbon::parse($this->event_end_date)->format('d M Y');
        $this->eventData['eventDetails']['year'] = $currentYear;

        if ($this->category == $this->eventData['eventCategory']) {
            $this->dispatchBrowserEvent('swal:success', [
                'type' => 'success',
                'message' => 'Event details updated succesfully!',
                'text' => "",
            ]);

            $this->resetEditEventDetailsFields();
        } else {
            return redirect()->route('admin.event.details.view', ['eventCategory' => $this->category, 'eventId' => $this->eventData['eventId']]);
        }
    }


    // EDIT EVENT COLORS
    public function showEditEventColors()
    {
        $this->primary_bg_color = $this->eventData['eventColors']['primary_bg_color'];
        $this->secondary_bg_color = $this->eventData['eventColors']['secondary_bg_color'];

        $this->primary_text_color = $this->eventData['eventColors']['primary_text_color'];
        $this->secondary_text_color = $this->eventData['eventColors']['secondary_text_color'];

        $this->editEventColorsForm = true;
    }

    public function resetEditEventColorsFields()
    {
        $this->editEventColorsForm = false;
        $this->primary_bg_color = null;
        $this->secondary_bg_color = null;
        $this->primary_text_color = null;
        $this->secondary_text_color = null;
    }

    public function editEventColorsConfirmation()
    {
        $this->validate([
            'primary_bg_color' => 'required',
            'secondary_bg_color' => 'required',
            'primary_text_color' => 'required',
            'secondary_text_color' => 'required',
        ]);


        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure?',
            'text' => "",
            'buttonConfirmText' => "Yes, update it!",
            'livewireEmit' => "editEventColorsConfirmed",
        ]);
    }

    public function editEventColors()
    {
        Events::where('id', $this->eventData['eventId'])->update([
            'primary_bg_color' => $this->primary_bg_color,
            'secondary_bg_color' => $this->secondary_bg_color,
            'primary_text_color' => $this->primary_text_color,
            'secondary_text_color' => $this->secondary_text_color,
        ]);

        $this->eventData['eventColors']['primary_bg_color'] = $this->primary_bg_color;
        $this->eventData['eventColors']['secondary_bg_color'] = $this->secondary_bg_color;
        $this->eventData['eventColors']['primary_text_color'] = $this->primary_text_color;
        $this->eventData['eventColors']['secondary_text_color'] = $this->secondary_text_color;

        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => 'Event colors updated succesfully!',
            'text' => "",
        ]);

        $this->resetEditEventColorsFields();
    }



    // EDIT HTML Texts
    public function showEditEventHTMLTexts()
    {
        $this->description_html_text = $this->eventData['eventHTMLTexts']['description_html_text'];
        $this->login_html_text = $this->eventData['eventHTMLTexts']['login_html_text'];
        $this->continue_as_guest_html_text = $this->eventData['eventHTMLTexts']['continue_as_guest_html_text'];
        $this->forgot_password_html_text = $this->eventData['eventHTMLTexts']['forgot_password_html_text'];
        $this->editEventHTMLTextsForm = true;
    }

    public function resetEditEventHTMLTextsFields()
    {
        $this->editEventHTMLTextsForm = false;
        $this->description_html_text = null;
        $this->login_html_text = null;
        $this->continue_as_guest_html_text = null;
        $this->forgot_password_html_text = null;
    }

    public function editEventHTMLTextsConfirmation()
    {
        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure?',
            'text' => "",
            'buttonConfirmText' => "Yes, update it!",
            'livewireEmit' => "editEventHTMLTextsConfirmed",
        ]);
    }

    public function editEventHTMLTexts()
    {
        Events::where('id', $this->eventData['eventId'])->update([
            'description_html_text' => $this->description_html_text,
            'login_html_text' => $this->login_html_text,
            'continue_as_guest_html_text' => $this->continue_as_guest_html_text,
            'forgot_password_html_text' => $this->forgot_password_html_text,
        ]);

        $this->eventData['eventHTMLTexts']['description_html_text'] = $this->description_html_text;
        $this->eventData['eventHTMLTexts']['login_html_text'] = $this->login_html_text;
        $this->eventData['eventHTMLTexts']['continue_as_guest_html_text'] = $this->continue_as_guest_html_text;
        $this->eventData['eventHTMLTexts']['forgot_password_html_text'] = $this->forgot_password_html_text;

        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => 'Event html texts updated succesfully!',
            'text' => "",
        ]);

        $this->resetEditEventHTMLTextsFields();
    }



    // EDIT WebView Links
    public function showEditEventWebViewLinks()
    {
        $this->delegate_feedback_survey_link = $this->eventData['eventWebViewLinks']['delegate_feedback_survey_link'];
        $this->app_feedback_survey_link = $this->eventData['eventWebViewLinks']['app_feedback_survey_link'];
        $this->about_event_link = $this->eventData['eventWebViewLinks']['about_event_link'];
        $this->venue_link = $this->eventData['eventWebViewLinks']['venue_link'];
        $this->press_releases_link = $this->eventData['eventWebViewLinks']['press_releases_link'];
        $this->slido_link = $this->eventData['eventWebViewLinks']['slido_link'];
        $this->shuttle_bus_schedule_link = $this->eventData['eventWebViewLinks']['shuttle_bus_schedule_link'];
        $this->editEventWebViewLinksForm = true;
    }

    public function resetEditEventWebViewLinksFields()
    {
        $this->editEventWebViewLinksForm = false;
        $this->delegate_feedback_survey_link = null;
        $this->app_feedback_survey_link = null;
        $this->about_event_link = null;
        $this->venue_link = null;
        $this->press_releases_link = null;
        $this->slido_link = null;
    }

    public function editEventWebViewLinksConfirmation()
    {
        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure?',
            'text' => "",
            'buttonConfirmText' => "Yes, update it!",
            'livewireEmit' => "editEventWebViewLinksConfirmed",
        ]);
    }

    public function editEventWebViewLinks()
    {
        Events::where('id', $this->eventData['eventId'])->update([
            'delegate_feedback_survey_link' => $this->delegate_feedback_survey_link,
            'app_feedback_survey_link' => $this->app_feedback_survey_link,
            'about_event_link' => $this->about_event_link,
            'venue_link' => $this->venue_link,
            'press_releases_link' => $this->press_releases_link,
            'slido_link' => $this->slido_link,
            'shuttle_bus_schedule_link' => $this->shuttle_bus_schedule_link,
        ]);

        $this->eventData['eventWebViewLinks']['delegate_feedback_survey_link'] = $this->delegate_feedback_survey_link;
        $this->eventData['eventWebViewLinks']['app_feedback_survey_link'] = $this->app_feedback_survey_link;
        $this->eventData['eventWebViewLinks']['about_event_link'] = $this->about_event_link;
        $this->eventData['eventWebViewLinks']['venue_link'] = $this->venue_link;
        $this->eventData['eventWebViewLinks']['press_releases_link'] = $this->press_releases_link;
        $this->eventData['eventWebViewLinks']['slido_link'] = $this->slido_link;
        $this->eventData['eventWebViewLinks']['shuttle_bus_schedule_link'] = $this->shuttle_bus_schedule_link;

        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => 'Event webview links updated succesfully!',
            'text' => "",
        ]);

        $this->resetEditEventWebViewLinksFields();
    }



    // EDIT Floor Plan Links
    public function showEditEventFloorPlanLinks()
    {
        $this->floor_plan_3d_image_link = $this->eventData['eventFloorPlanLinks']['floor_plan_3d_image_link'];
        $this->floor_plan_exhibition_image_link = $this->eventData['eventFloorPlanLinks']['floor_plan_exhibition_image_link'];
        $this->interactive_map_link = $this->eventData['eventFloorPlanLinks']['interactive_map_link'];
        $this->editEventFloorPlanLinksForm = true;
    }

    public function resetEditEventFloorPlanLinksFields()
    {
        $this->editEventFloorPlanLinksForm = false;
        $this->floor_plan_3d_image_link = null;
        $this->floor_plan_exhibition_image_link = null;
        $this->interactive_map_link = null;
    }




    public function editEventFloorPlanLinksConfirmation()
    {
        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure?',
            'text' => "",
            'buttonConfirmText' => "Yes, update it!",
            'livewireEmit' => "editEventFloorPlanLinksConfirmed",
        ]);
    }

    public function editEventFloorPlanLinks()
    {
        Events::where('id', $this->eventData['eventId'])->update([
            'floor_plan_3d_image_link' => $this->floor_plan_3d_image_link,
            'floor_plan_exhibition_image_link' => $this->floor_plan_exhibition_image_link,
            'interactive_map_link' => $this->interactive_map_link,
        ]);

        $this->eventData['eventFloorPlanLinks']['floor_plan_3d_image_link'] = $this->floor_plan_3d_image_link;
        $this->eventData['eventFloorPlanLinks']['floor_plan_exhibition_image_link'] = $this->floor_plan_exhibition_image_link;
        $this->eventData['eventFloorPlanLinks']['interactive_map_link'] = $this->interactive_map_link;

        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => 'Event floor plan links updated succesfully!',
            'text' => "",
        ]);

        $this->resetEditEventFloorPlanLinksFields();
    }




    // EDIT EVENT ASSET
    public function showEditEventAsset($assetType)
    {
        $this->assetType = $assetType;
        $this->editEventAssetForm = true;
    }

    public function resetEditEventAssetFields()
    {
        $this->editEventAssetForm = false;
        $this->assetType = null;
        $this->image_media_id = null;
        $this->image_placeholder_text = null;
    }

    public function editEventAssetConfirmation()
    {
        $this->validate([
            'image_placeholder_text' => 'required'
        ]);

        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure?',
            'text' => "",
            'buttonConfirmText' => "Yes, update it!",
            'livewireEmit' => "editEventAssetConfirmed",
        ]);
    }

    public function editEventAsset()
    {
        if ($this->assetType == "Event Logo") {
            Events::where('id', $this->eventData['eventId'])->update([
                'event_logo_media_id' => $this->image_media_id,
            ]);

            if ($this->eventData['eventAssets']['event_logo']['media_id'] != null) {
                mediaUsageUpdate(
                    MediaUsageUpdateTypes::REMOVED_THEN_ADD->value,
                    $this->image_media_id,
                    MediaEntityTypes::EVENT_LOGO->value,
                    $this->eventData['eventId'],
                    $this->eventData['eventAssets']['event_logo']['media_usage_id']
                );
            } else {
                mediaUsageUpdate(
                    MediaUsageUpdateTypes::ADD_ONLY->value,
                    $this->image_media_id,
                    MediaEntityTypes::EVENT_LOGO->value,
                    $this->eventData['eventId'],
                    $this->eventData['eventAssets']['event_logo']['media_usage_id']
                );
            }

            $this->eventData['eventAssets']['event_logo'] = [
                'media_id' => $this->image_media_id,
                'media_usage_id' => getMediaUsageId($this->image_media_id, MediaEntityTypes::EVENT_LOGO->value, $this->eventData['eventId']),
                'url' => Medias::where('id', $this->image_media_id)->value('file_url'),
            ];
        } else if ($this->assetType == 'Event Logo inverted') {
            Events::where('id', $this->eventData['eventId'])->update([
                'event_logo_inverted_media_id' => $this->image_media_id,
            ]);

            if ($this->eventData['eventAssets']['event_logo_inverted']['media_id'] != null) {
                mediaUsageUpdate(
                    MediaUsageUpdateTypes::REMOVED_THEN_ADD->value,
                    $this->image_media_id,
                    MediaEntityTypes::EVENT_LOGO_INVERTED->value,
                    $this->eventData['eventId'],
                    $this->eventData['eventAssets']['event_logo_inverted']['media_usage_id']
                );
            } else {
                mediaUsageUpdate(
                    MediaUsageUpdateTypes::ADD_ONLY->value,
                    $this->image_media_id,
                    MediaEntityTypes::EVENT_LOGO_INVERTED->value,
                    $this->eventData['eventId'],
                    $this->eventData['eventAssets']['event_logo_inverted']['media_usage_id']
                );
            }

            $this->eventData['eventAssets']['event_logo_inverted'] = [
                'media_id' => $this->image_media_id,
                'media_usage_id' => getMediaUsageId($this->image_media_id, MediaEntityTypes::EVENT_LOGO_INVERTED->value, $this->eventData['eventId']),
                'url' => Medias::where('id', $this->image_media_id)->value('file_url'),
            ];
        } else if ($this->assetType == 'App Sponsor logo') {
            Events::where('id', $this->eventData['eventId'])->update([
                'app_sponsor_logo_media_id' => $this->image_media_id,
            ]);

            if ($this->eventData['eventAssets']['app_sponsor_logo']['media_id'] != null) {
                mediaUsageUpdate(
                    MediaUsageUpdateTypes::REMOVED_THEN_ADD->value,
                    $this->image_media_id,
                    MediaEntityTypes::EVENT_APP_SPONSOR_LOGO->value,
                    $this->eventData['eventId'],
                    $this->eventData['eventAssets']['app_sponsor_logo']['media_usage_id']
                );
            } else {
                mediaUsageUpdate(
                    MediaUsageUpdateTypes::ADD_ONLY->value,
                    $this->image_media_id,
                    MediaEntityTypes::EVENT_APP_SPONSOR_LOGO->value,
                    $this->eventData['eventId'],
                    $this->eventData['eventAssets']['app_sponsor_logo']['media_usage_id']
                );
            }

            $this->eventData['eventAssets']['app_sponsor_logo'] = [
                'media_id' => $this->image_media_id,
                'media_usage_id' => getMediaUsageId($this->image_media_id, MediaEntityTypes::EVENT_APP_SPONSOR_LOGO->value, $this->eventData['eventId']),
                'url' => Medias::where('id', $this->image_media_id)->value('file_url'),
            ];
        } else if ($this->assetType == 'Event Banner') {
            Events::where('id', $this->eventData['eventId'])->update([
                'event_banner_media_id' => $this->image_media_id,
            ]);

            if ($this->eventData['eventAssets']['event_banner']['media_id'] != null) {
                mediaUsageUpdate(
                    MediaUsageUpdateTypes::REMOVED_THEN_ADD->value,
                    $this->image_media_id,
                    MediaEntityTypes::EVENT_BANNER->value,
                    $this->eventData['eventId'],
                    $this->eventData['eventAssets']['event_banner']['media_usage_id']
                );
            } else {
                mediaUsageUpdate(
                    MediaUsageUpdateTypes::ADD_ONLY->value,
                    $this->image_media_id,
                    MediaEntityTypes::EVENT_BANNER->value,
                    $this->eventData['eventId'],
                    $this->eventData['eventAssets']['event_banner']['media_usage_id']
                );
            }

            $this->eventData['eventAssets']['event_banner'] = [
                'media_id' => $this->image_media_id,
                'media_usage_id' => getMediaUsageId($this->image_media_id, MediaEntityTypes::EVENT_BANNER->value, $this->eventData['eventId']),
                'url' => Medias::where('id', $this->image_media_id)->value('file_url'),
            ];
        } else if ($this->assetType == 'App Sponsor banner') {
            Events::where('id', $this->eventData['eventId'])->update([
                'app_sponsor_banner_media_id' => $this->image_media_id,
            ]);

            if ($this->eventData['eventAssets']['app_sponsor_banner']['media_id'] != null) {
                mediaUsageUpdate(
                    MediaUsageUpdateTypes::REMOVED_THEN_ADD->value,
                    $this->image_media_id,
                    MediaEntityTypes::EVENT_APP_SPONSOR_BANNER->value,
                    $this->eventData['eventId'],
                    $this->eventData['eventAssets']['app_sponsor_banner']['media_usage_id']
                );
            } else {
                mediaUsageUpdate(
                    MediaUsageUpdateTypes::ADD_ONLY->value,
                    $this->image_media_id,
                    MediaEntityTypes::EVENT_APP_SPONSOR_BANNER->value,
                    $this->eventData['eventId'],
                    $this->eventData['eventAssets']['app_sponsor_banner']['media_usage_id']
                );
            }

            $this->eventData['eventAssets']['app_sponsor_banner'] = [
                'media_id' => $this->image_media_id,
                'media_usage_id' => getMediaUsageId($this->image_media_id, MediaEntityTypes::EVENT_APP_SPONSOR_BANNER->value, $this->eventData['eventId']),
                'url' => Medias::where('id', $this->image_media_id)->value('file_url'),
            ];
        } else {
            Events::where('id', $this->eventData['eventId'])->update([
                'event_splash_screen_media_id' => $this->image_media_id,
            ]);

            if ($this->eventData['eventAssets']['event_splash_screen']['media_id'] != null) {
                mediaUsageUpdate(
                    MediaUsageUpdateTypes::REMOVED_THEN_ADD->value,
                    $this->image_media_id,
                    MediaEntityTypes::EVENT_SPLASH_SCREEN->value,
                    $this->eventData['eventId'],
                    $this->eventData['eventAssets']['event_splash_screen']['media_usage_id']
                );
            } else {
                mediaUsageUpdate(
                    MediaUsageUpdateTypes::ADD_ONLY->value,
                    $this->image_media_id,
                    MediaEntityTypes::EVENT_SPLASH_SCREEN->value,
                    $this->eventData['eventId'],
                    $this->eventData['eventAssets']['event_splash_screen']['media_usage_id']
                );
            }

            $this->eventData['eventAssets']['event_splash_screen'] = [
                'media_id' => $this->image_media_id,
                'media_usage_id' => getMediaUsageId($this->image_media_id, MediaEntityTypes::EVENT_SPLASH_SCREEN->value, $this->eventData['eventId']),
                'url' => Medias::where('id', $this->image_media_id)->value('file_url'),
            ];
        }

        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => $this->assetType . ' updated succesfully!',
            'text' => "",
        ]);

        $this->resetEditEventAssetFields();
    }


    // FOR CHOOSING IMAGE MODAL
    public function chooseImage()
    {
        $this->chooseImageModal = true;
    }

    public function showMediaFileDetails($arrayIndex)
    {
        $this->activeSelectedImage = $this->mediaFileList[$arrayIndex];
    }

    public function unshowMediaFileDetails()
    {
        $this->activeSelectedImage = array();
    }

    public function selectChooseImage()
    {
        $this->image_media_id = $this->activeSelectedImage['id'];
        $this->image_placeholder_text = $this->activeSelectedImage['file_name'];
        $this->activeSelectedImage = null;
        $this->chooseImageModal = false;
    }



    public function cancelChooseImage()
    {
        $this->image_media_id = null;
        $this->image_placeholder_text = null;
        $this->activeSelectedImage = null;
        $this->chooseImageModal = false;
    }


    public function deleteEventAsset($deleteAssetType)
    {
        if ($deleteAssetType == "Event Logo") {
            Events::where('id', $this->eventData['eventId'])->update([
                'event_logo_media_id' => null,
            ]);

            mediaUsageUpdate(
                MediaUsageUpdateTypes::REMOVED_ONLY->value,
                $this->eventData['eventAssets']['event_logo']['media_id'],
                MediaEntityTypes::EVENT_LOGO->value,
                $this->eventData['eventId'],
                $this->eventData['eventAssets']['event_logo']['media_usage_id']
            );

            $this->eventData['eventAssets']['event_logo'] = [
                'media_id' => null,
                'media_usage_id' => null,
                'url' => null,
            ];
        } else if ($deleteAssetType == 'Event Logo inverted') {
            Events::where('id', $this->eventData['eventId'])->update([
                'event_logo_inverted_media_id' => null,
            ]);

            mediaUsageUpdate(
                MediaUsageUpdateTypes::REMOVED_ONLY->value,
                $this->eventData['eventAssets']['event_logo_inverted']['media_id'],
                MediaEntityTypes::EVENT_LOGO_INVERTED->value,
                $this->eventData['eventId'],
                $this->eventData['eventAssets']['event_logo_inverted']['media_usage_id']
            );

            $this->eventData['eventAssets']['event_logo_inverted'] = [
                'media_id' => null,
                'media_usage_id' => null,
                'url' => null,
            ];
        } else if ($deleteAssetType == 'App Sponsor logo') {
            Events::where('id', $this->eventData['eventId'])->update([
                'app_sponsor_logo_media_id' => null,
            ]);

            mediaUsageUpdate(
                MediaUsageUpdateTypes::REMOVED_ONLY->value,
                $this->eventData['eventAssets']['app_sponsor_logo']['media_id'],
                MediaEntityTypes::EVENT_APP_SPONSOR_LOGO->value,
                $this->eventData['eventId'],
                $this->eventData['eventAssets']['app_sponsor_logo']['media_usage_id']
            );

            $this->eventData['eventAssets']['app_sponsor_logo'] = [
                'media_id' => null,
                'media_usage_id' => null,
                'url' => null,
            ];
        } else if ($deleteAssetType == 'Event Banner') {
            Events::where('id', $this->eventData['eventId'])->update([
                'event_banner_media_id' => null,
            ]);

            mediaUsageUpdate(
                MediaUsageUpdateTypes::REMOVED_ONLY->value,
                $this->eventData['eventAssets']['event_banner']['media_id'],
                MediaEntityTypes::EVENT_BANNER->value,
                $this->eventData['eventId'],
                $this->eventData['eventAssets']['event_banner']['media_usage_id']
            );

            $this->eventData['eventAssets']['event_banner'] = [
                'media_id' => null,
                'media_usage_id' => null,
                'url' => null,
            ];
        } else if ($deleteAssetType == 'App Sponsor banner') {
            Events::where('id', $this->eventData['eventId'])->update([
                'app_sponsor_banner_media_id' => null,
            ]);

            mediaUsageUpdate(
                MediaUsageUpdateTypes::REMOVED_ONLY->value,
                $this->eventData['eventAssets']['app_sponsor_banner']['media_id'],
                MediaEntityTypes::EVENT_APP_SPONSOR_BANNER->value,
                $this->eventData['eventId'],
                $this->eventData['eventAssets']['app_sponsor_banner']['media_usage_id']
            );

            $this->eventData['eventAssets']['app_sponsor_banner'] = [
                'media_id' => null,
                'media_usage_id' => null,
                'url' => null,
            ];
        } else {
            Events::where('id', $this->eventData['eventId'])->update([
                'event_splash_screen_media_id' => null,
            ]);

            mediaUsageUpdate(
                MediaUsageUpdateTypes::REMOVED_ONLY->value,
                $this->eventData['eventAssets']['event_splash_screen']['media_id'],
                MediaEntityTypes::EVENT_SPLASH_SCREEN->value,
                $this->eventData['eventId'],
                $this->eventData['eventAssets']['event_splash_screen']['media_usage_id']
            );

            $this->eventData['eventAssets']['event_splash_screen'] = [
                'media_id' => null,
                'media_usage_id' => null,
                'url' => null,
            ];
        }
    }


    // EDIT SPONSORS BANNER CAROUSEL
    public function showEditSponsorsBannerCarousel()
    {
        $urls = $this->eventData['eventDetails']['sponsors_banner_carousel'] ?? [];
        $this->sponsors_banner_carousel_text = implode("\n", $urls);
        $this->editSponsorsBannerCarouselForm = true;
    }

    public function resetEditSponsorsBannerCarouselFields()
    {
        $this->editSponsorsBannerCarouselForm = false;
        $this->sponsors_banner_carousel_text = null;
    }

    public function editSponsorsBannerCarouselConfirmation()
    {
        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure?',
            'text' => "",
            'buttonConfirmText' => "Yes, update it!",
            'livewireEmit' => "editSponsorsBannerCarouselConfirmed",
        ]);
    }

    public function editSponsorsBannerCarousel()
    {
        $urls = collect(explode("\n", $this->sponsors_banner_carousel_text))
            ->map(fn($url) => trim($url))
            ->filter()
            ->values()
            ->toArray();

        Events::where('id', $this->eventData['eventId'])->update([
            'sponsors_banner_carousel' => $urls,
        ]);

        $this->eventData['eventDetails']['sponsors_banner_carousel'] = $urls;

        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => 'Sponsors banner carousel updated succesfully!',
            'text' => "",
        ]);

        $this->resetEditSponsorsBannerCarouselFields();
    }


    public function toggleEventFeaturesVisibility()
    {
        Events::where('id', $this->eventData['eventId'])->update([
            'show_event_features' => !$this->eventData['eventDetails']['show_event_features'],
        ]);
        $this->eventData['eventDetails']['show_event_features'] = !$this->eventData['eventDetails']['show_event_features'];
    }

    public function toggleSocialNetworkingsVisibility()
    {
        Events::where('id', $this->eventData['eventId'])->update([
            'show_social_networkings' => !$this->eventData['eventDetails']['show_social_networkings'],
        ]);
        $this->eventData['eventDetails']['show_social_networkings'] = !$this->eventData['eventDetails']['show_social_networkings'];
    }


    public function showEditVerticalImagesSection()
{
    $urls = $this->eventData['eventDetails']['vertical_images_section'] ?? [];
    $this->vertical_images_section_text = implode("\n", $urls);
    $this->editVerticalImagesSectionForm = true;
}

public function resetEditVerticalImagesSectionFields()
{
    $this->editVerticalImagesSectionForm = false;
    $this->vertical_images_section_text = null;
}

public function editVerticalImagesSectionConfirmation()
{
    $this->dispatchBrowserEvent('swal:confirmation', [
        'type' => 'warning',
        'message' => 'Are you sure?',
        'text' => "",
        'buttonConfirmText' => "Yes, update it!",
        'livewireEmit' => "editVerticalImagesSectionConfirmed",
    ]);
}

public function editVerticalImagesSection()
{
    $urls = collect(explode("\n", $this->vertical_images_section_text))
        ->map(fn($url) => trim($url))
        ->filter()
        ->values()
        ->toArray();

    Events::where('id', $this->eventData['eventId'])->update([
        'vertical_images_section' => $urls,
    ]);

    $this->eventData['eventDetails']['vertical_images_section'] = $urls;

    $this->dispatchBrowserEvent('swal:success', [
        'type' => 'success',
        'message' => 'Vertical images section updated succesfully!',
        'text' => "",
    ]);

    $this->resetEditVerticalImagesSectionFields();
}
}
