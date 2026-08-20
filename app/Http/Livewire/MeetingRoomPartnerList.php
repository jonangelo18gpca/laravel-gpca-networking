<?php

namespace App\Http\Livewire;

use App\Enums\MediaEntityTypes;
use App\Enums\MediaUsageUpdateTypes;
use App\Models\Event as Events;
use App\Models\Media as Medias;
use App\Models\MeetingRoomPartner as MeetingRoomPartners;
use Carbon\Carbon;
use Livewire\Component;

class MeetingRoomPartnerList extends Component
{
    public $event;
    public $finalListOfMeetingRoomPartners = array();

    // ADD MRP
    public $name, $website, $location;
    public $chooseImageModal, $mediaFileList = array(), $activeSelectedImage, $image_media_id, $image_placeholder_text;
    public $addMeetingRoomPartnerForm;

    // EDIT DATE TIME
    public $meetingRoomPartnerId, $meetingRoomPartnerDateTime, $meetingRoomPartnerArrayIndex;
    public $inputNameVariableDateTime, $btnUpdateNameMethodDateTime, $btnCancelNameMethodDateTime;
    public $editMeetingRoomPartnerDateTimeForm;

    // DELETE
    public $activeDeleteIndex;

    protected $listeners = ['addMeetingRoomPartnerConfirmed' => 'addMeetingRoomPartner', 'deleteMeetingRoomPartnerConfirmed' => 'deleteMeetingRoomPartner'];

    public function mount($eventId, $eventCategory)
    {
        $this->event = Events::where('id', $eventId)->where('category', $eventCategory)->first();

        $meetingRoomPartners = MeetingRoomPartners::where('event_id', $eventId)->orderBy('datetime_added', 'ASC')->get();

        if ($meetingRoomPartners->isNotEmpty()) {
            foreach ($meetingRoomPartners as $meetingRoomPartner) {
                array_push($this->finalListOfMeetingRoomPartners, [
                    'id' => $meetingRoomPartner->id,
                    'name' => $meetingRoomPartner->name,
                    'location' => $meetingRoomPartner->location,
                    'website' => $meetingRoomPartner->website,
                    'is_active' => $meetingRoomPartner->is_active,
                    'logo' => Medias::where('id', $meetingRoomPartner->logo_media_id)->value('file_url'),
                    'datetime_added' => Carbon::parse($meetingRoomPartner->datetime_added)->format('M j, Y g:i A'),
                ]);
            }
        }

        $this->inputNameVariableDateTime = "meetingRoomPartnerDateTime";
        $this->btnUpdateNameMethodDateTime = "editMeetingRoomPartnerDateTime";
        $this->btnCancelNameMethodDateTime = "resetEditMeetingRoomPartnerDateTimeFields";

        $this->addMeetingRoomPartnerForm = false;
        $this->editMeetingRoomPartnerDateTimeForm = false;
        
        $this->mediaFileList = getMediaFileList();
        $this->chooseImageModal = false;
    }

    public function render()
    {
        return view('livewire.event.meeting-room-partners.meeting-room-partner-list');
    }
    
    public function showAddMeetingRoomPartner()
    {
        $this->addMeetingRoomPartnerForm = true;
    }

    // public function addMeetingRoomPartnerConfirmation()
    // {
    //     $this->validate([
    //         'name' => 'required',
    //     ]);

    //     $this->dispatchBrowserEvent('swal:confirmation', [
    //         'type' => 'warning',
    //         'message' => 'Are you sure?',
    //         'text' => "",
    //         'buttonConfirmText' => "Yes, add it!",
    //         'livewireEmit' => "addMeetingRoomPartnerConfirmed",
    //     ]);
    // }


    public function addMeetingRoomPartnerConfirmation()
{
    $this->validate([
        'name' => 'required',
        'image_media_id' => 'nullable|exists:medias,id',
        'image_placeholder_text' => 'nullable|required_without:image_media_id|url|max:2048',
    ]);

    $this->dispatchBrowserEvent('swal:confirmation', [
        'type' => 'warning',
        'message' => 'Are you sure?',
        'text' => '',
        'buttonConfirmText' => 'Yes, add it!',
        'livewireEmit' => 'addMeetingRoomPartnerConfirmed',
    ]);
}


    public function resetAddMeetingRoomPartnerFields()
    {
        $this->addMeetingRoomPartnerForm = false;
        $this->name = null;
        $this->website = null;
        $this->location = null;
        $this->image_media_id = null;
        $this->image_placeholder_text = null;
    }

    // public function addMeetingRoomPartner(){
    //     $newMeetingRoomPartner = MeetingRoomPartners::create([
    //         'event_id' => $this->event->id,
    //         'name' => $this->name,
    //         'website' => $this->website,
    //         'location' => $this->location,
    //         'logo_media_id' => $this->image_media_id ?? null,
    //         'datetime_added' => Carbon::now(),
    //     ]);

    //     if($this->image_media_id){
    //         mediaUsageUpdate(
    //             MediaUsageUpdateTypes::ADD_ONLY->value,
    //             $this->image_media_id,
    //             MediaEntityTypes::MEETING_ROOM_PARTNER_LOGO->value,
    //             $newMeetingRoomPartner->id,
    //         );
    //     }
        
    //     array_push($this->finalListOfMeetingRoomPartners, [
    //         'id' => $newMeetingRoomPartner->id,
    //         'name' => $this->name,
    //         'location' => $this->location,
    //         'website' => $this->website,
    //         'is_active' => true,
    //         'logo' => $this->image_media_id ? Medias::where('id', $this->image_media_id)->value('file_url') : null,
    //         'datetime_added' => Carbon::parse(Carbon::now())->format('M j, Y g:i A'),
    //     ]);

    //     $this->resetAddMeetingRoomPartnerFields();

    //     $this->dispatchBrowserEvent('swal:success', [
    //         'type' => 'success',
    //         'message' => 'Meeting room partner added successfully!',
    //         'text' => ''
    //     ]);
    // }


    public function addMeetingRoomPartner()
{
    $logoMediaId = $this->image_media_id;

    if (!$logoMediaId && !empty($this->image_placeholder_text)) {
        $imageUrl = trim($this->image_placeholder_text);

        $path = parse_url($imageUrl, PHP_URL_PATH);
        $fileName = basename($path) ?: 'meeting-room-partner-logo';
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $media = Medias::create([
            'file_url' => $imageUrl,
            'file_directory' => 'external-url',
            'file_name' => $fileName,
            'file_type' => $extension ?: 'image',
            'file_size' => 0,
            'width' => 0,
            'height' => 0,
            'date_uploaded' => Carbon::now(),
        ]);

        $logoMediaId = $media->id;
    }

    $newMeetingRoomPartner = MeetingRoomPartners::create([
        'event_id' => $this->event->id,
        'name' => $this->name,
        'website' => $this->website,
        'location' => $this->location,
        'logo_media_id' => $logoMediaId,
        'datetime_added' => Carbon::now(),
    ]);

    if ($logoMediaId) {
        mediaUsageUpdate(
            MediaUsageUpdateTypes::ADD_ONLY->value,
            $logoMediaId,
            MediaEntityTypes::MEETING_ROOM_PARTNER_LOGO->value,
            $newMeetingRoomPartner->id,
        );
    }

    array_push($this->finalListOfMeetingRoomPartners, [
        'id' => $newMeetingRoomPartner->id,
        'name' => $this->name,
        'location' => $this->location,
        'website' => $this->website,
        'is_active' => true,
        'logo' => $logoMediaId
            ? Medias::where('id', $logoMediaId)->value('file_url')
            : null,
        'datetime_added' => Carbon::now()->format('M j, Y g:i A'),
    ]);

    $this->resetAddMeetingRoomPartnerFields();

    $this->dispatchBrowserEvent('swal:success', [
        'type' => 'success',
        'message' => 'Meeting room partner added successfully!',
        'text' => '',
    ]);
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
        // $this->image_placeholder_text = $this->activeSelectedImage['file_name'];
        $this->image_placeholder_text = $this->activeSelectedImage['file_url'];
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


    
    public function updateMeetingRoomPartnerStatus($arrayIndex){
        MeetingRoomPartners::where('id', $this->finalListOfMeetingRoomPartners[$arrayIndex]['id'])->update([
            'is_active' => !$this->finalListOfMeetingRoomPartners[$arrayIndex]['is_active'],
        ]);

        $this->finalListOfMeetingRoomPartners[$arrayIndex]['is_active'] = !$this->finalListOfMeetingRoomPartners[$arrayIndex]['is_active'];
    }



    // EDIT DATETIME
    public function showEditMeetingRoomPartnerDateTime($meetingRoomPartnerId, $meetingRoomPartnerArrayIndex)
    {
        $meetingRoomPartnerDateTime = MeetingRoomPartners::where('id', $meetingRoomPartnerId)->value('datetime_added');

        $this->meetingRoomPartnerId = $meetingRoomPartnerId;
        $this->meetingRoomPartnerDateTime = $meetingRoomPartnerDateTime;
        $this->meetingRoomPartnerArrayIndex = $meetingRoomPartnerArrayIndex;
        $this->editMeetingRoomPartnerDateTimeForm = true;
    }

    public function resetEditMeetingRoomPartnerDateTimeFields()
    {
        $this->editMeetingRoomPartnerDateTimeForm = false;
        $this->meetingRoomPartnerId = null;
        $this->meetingRoomPartnerDateTime = null;
        $this->meetingRoomPartnerArrayIndex = null;
    }
    
    public function editMeetingRoomPartnerDateTime()
    {
        $this->validate([
            'meetingRoomPartnerDateTime' => 'required',
        ]);

        MeetingRoomPartners::where('id', $this->meetingRoomPartnerId)->update([
            'datetime_added' => $this->meetingRoomPartnerDateTime,
        ]);

        $this->finalListOfMeetingRoomPartners[$this->meetingRoomPartnerArrayIndex]['datetime_added'] = Carbon::parse($this->meetingRoomPartnerDateTime)->format('M j, Y g:i A');

        $this->resetEditMeetingRoomPartnerDateTimeFields();

        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => 'Media Partner Datetime updated successfully!',
            'text' => ''
        ]);
    }


    public function deleteMeetingRoomPartnerConfirmation($index)
    {
        $this->activeDeleteIndex = $index;
        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure you want to delete?',
            'text' => "",
            'buttonConfirmText' => "Yes, delete it!",
            'livewireEmit' => "deleteMeetingRoomPartnerConfirmed",
        ]);
    }

    public function deleteMeetingRoomPartner()
    {
        $meetingRoomPartner = MeetingRoomPartners::where('id', $this->finalListOfMeetingRoomPartners[$this->activeDeleteIndex]['id'])->first();

        if($meetingRoomPartner){
            if($meetingRoomPartner->logo_media_id){
                mediaUsageUpdate(
                    MediaUsageUpdateTypes::REMOVED_ONLY->value,
                    $meetingRoomPartner->logo_media_id,
                    MediaEntityTypes::MEETING_ROOM_PARTNER_LOGO->value,
                    $meetingRoomPartner->id,
                    getMediaUsageId($meetingRoomPartner->logo_media_id, MediaEntityTypes::MEETING_ROOM_PARTNER_LOGO->value, $meetingRoomPartner->id),
                );
            }

            if($meetingRoomPartner->banner_media_id){
                mediaUsageUpdate(
                    MediaUsageUpdateTypes::REMOVED_ONLY->value,
                    $meetingRoomPartner->banner_media_id,
                    MediaEntityTypes::MEETING_ROOM_PARTNER_BANNER->value,
                    $meetingRoomPartner->id,
                    getMediaUsageId($meetingRoomPartner->banner_media_id, MediaEntityTypes::MEETING_ROOM_PARTNER_BANNER->value, $meetingRoomPartner->id),
                );
            }

            $meetingRoomPartner->delete();

            unset($this->finalListOfMeetingRoomPartners[$this->activeDeleteIndex]);
            $this->finalListOfMeetingRoomPartners = array_values($this->finalListOfMeetingRoomPartners);
        }
        
        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => 'Meeting Room Partner deleted successfully!',
            'text' => ''
        ]);
    }
}
