<?php

namespace App\Http\Livewire;

use App\Enums\MediaEntityTypes;
use App\Enums\MediaUsageUpdateTypes;
use App\Models\Event as Events;
use App\Models\Exhibitor as Exhibitors;
use App\Models\Media as Medias;
use Carbon\Carbon;
use Livewire\Component;

class ExhibitorList extends Component
{
    public $event;
    public $finalListOfExhibitors = array();

    // EDIT DETAILS
    public $name, $website, $stand_number;
    public $chooseImageModal, $mediaFileList = array(), $activeSelectedImage, $image_media_id, $image_placeholder_text;
    public $addExhibitorForm;

    // EDIT DATE TIME
    public $exhibitorId, $exhibitorDateTime, $exhibitorArrayIndex;
    public $inputNameVariableDateTime, $btnUpdateNameMethodDateTime, $btnCancelNameMethodDateTime;
    public $editExhibitorDateTimeForm;

    // DELETE
    public $activeDeleteIndex;

    protected $listeners = ['addExhibitorConfirmed' => 'addExhibitor', 'deleteExhibitorConfirmed' => 'deleteExhibitor'];

    public function mount($eventId, $eventCategory)
    {
        $this->event = Events::where('id', $eventId)->where('category', $eventCategory)->first();

        $exhibitors = Exhibitors::where('event_id', $eventId)->orderBy('datetime_added', 'ASC')->get();

        if ($exhibitors->isNotEmpty()) {
            foreach ($exhibitors as $exhibitor) {
                array_push($this->finalListOfExhibitors, [
                    'id' => $exhibitor->id,
                    'name' => $exhibitor->name,
                    'stand_number' => $exhibitor->stand_number,
                    'website' => $exhibitor->website,
                    'is_active' => $exhibitor->is_active,
                    // 'logo' => Medias::where('id', $exhibitor->logo_media_id)->value('file_url'),
                    'logo' => $exhibitor->logo_media_id
                        ? Medias::where('id', $exhibitor->logo_media_id)->value('file_url')
                        : null,
                    'datetime_added' => Carbon::parse($exhibitor->datetime_added)->format('M j, Y g:i A'),
                ]);
            }
        }

        $this->inputNameVariableDateTime = "exhibitorDateTime";
        $this->btnUpdateNameMethodDateTime = "editExhibitorDateTime";
        $this->btnCancelNameMethodDateTime = "resetEditExhibitorDateTimeFields";

        $this->addExhibitorForm = false;
        $this->editExhibitorDateTimeForm = false;

        $this->mediaFileList = getMediaFileList();
        $this->chooseImageModal = false;
    }

    public function render()
    {
        return view('livewire.event.exhibitors.exhibitor-list');
    }

    public function showAddExhibitor()
    {
        $this->addExhibitorForm = true;
    }

    public function addExhibitorConfirmation()
    {
        $this->validate([
            'name' => 'required',
            'image_media_id' => 'nullable|exists:medias,id',
            'image_placeholder_text' => 'nullable|required_without:image_media_id|url|max:2048',
        ]);

        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure?',
            'text' => "",
            'buttonConfirmText' => "Yes, add it!",
            'livewireEmit' => "addExhibitorConfirmed",
        ]);
    }

    public function resetAddExhibitorFields()
    {
        $this->addExhibitorForm = false;
        $this->name = null;
        $this->website = null;
        $this->stand_number = null;
        $this->image_media_id = null;
        $this->image_placeholder_text = null;
    }

    // public function addExhibitor()
    // {


    //     $newExhibitor = Exhibitors::create([
    //         'event_id' => $this->event->id,
    //         'name' => $this->name,
    //         'website' => $this->website,
    //         'stand_number' => $this->stand_number,
    //         'logo_media_id' => $this->image_media_id ?? null,
    //         'datetime_added' => Carbon::now(),
    //     ]);


    //     if ($this->image_media_id) {
    //         mediaUsageUpdate(
    //             MediaUsageUpdateTypes::ADD_ONLY->value,
    //             $this->image_media_id,
    //             MediaEntityTypes::EXHIBITOR_LOGO->value,
    //             $newExhibitor->id,
    //         );
    //     }
    //     array_push($this->finalListOfExhibitors, [
    //         'id' => $newExhibitor->id,
    //         'name' => $this->name,
    //         'stand_number' => $this->stand_number,
    //         'website' => $this->website,
    //         'is_active' => true,
    //         'logo' => $this->image_media_id ? Medias::where('id', $this->image_media_id)->value('file_url') : null,
    //         'datetime_added' => Carbon::parse(Carbon::now())->format('M j, Y g:i A'),
    //     ]);

    //     $this->resetAddExhibitorFields();

    //     $this->dispatchBrowserEvent('swal:success', [
    //         'type' => 'success',
    //         'message' => 'Exhibitor added successfully!',
    //         'text' => ''
    //     ]);
    // }


    public function addExhibitor()
    {
        $logoMediaId = $this->image_media_id;

        if (!$logoMediaId && !empty($this->image_placeholder_text)) {
            $imageUrl = trim($this->image_placeholder_text);

            $path = parse_url($imageUrl, PHP_URL_PATH);
            $fileName = basename($path) ?: 'exhibitor-logo';
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

        $newExhibitor = Exhibitors::create([
            'event_id' => $this->event->id,
            'name' => $this->name,
            'website' => $this->website,
            'stand_number' => $this->stand_number,
            'logo_media_id' => $logoMediaId,
            'datetime_added' => Carbon::now(),
        ]);

        if ($logoMediaId) {
            mediaUsageUpdate(
                MediaUsageUpdateTypes::ADD_ONLY->value,
                $logoMediaId,
                MediaEntityTypes::EXHIBITOR_LOGO->value,
                $newExhibitor->id,
            );
        }

        array_push($this->finalListOfExhibitors, [
            'id' => $newExhibitor->id,
            'name' => $this->name,
            'stand_number' => $this->stand_number,
            'website' => $this->website,
            'is_active' => true,
            'logo' => $logoMediaId
                ? Medias::where('id', $logoMediaId)->value('file_url')
                : null,
            'datetime_added' => Carbon::now()->format('M j, Y g:i A'),
        ]);

        $this->resetAddExhibitorFields();

        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => 'Exhibitor added successfully!',
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





    public function updateExhibitorStatus($arrayIndex)
    {
        Exhibitors::where('id', $this->finalListOfExhibitors[$arrayIndex]['id'])->update([
            'is_active' => !$this->finalListOfExhibitors[$arrayIndex]['is_active'],
        ]);

        $this->finalListOfExhibitors[$arrayIndex]['is_active'] = !$this->finalListOfExhibitors[$arrayIndex]['is_active'];
    }



    // EDIT DATETIME
    public function showEditExhibitorDateTime($exhibitorId, $exhibitorArrayIndex)
    {
        $exhibitorDateTime = Exhibitors::where('id', $exhibitorId)->value('datetime_added');

        $this->exhibitorId = $exhibitorId;
        $this->exhibitorDateTime = $exhibitorDateTime;
        $this->exhibitorArrayIndex = $exhibitorArrayIndex;
        $this->editExhibitorDateTimeForm = true;
    }

    public function resetEditExhibitorDateTimeFields()
    {
        $this->editExhibitorDateTimeForm = false;
        $this->exhibitorId = null;
        $this->exhibitorDateTime = null;
        $this->exhibitorArrayIndex = null;
    }

    public function editExhibitorDateTime()
    {
        $this->validate([
            'exhibitorDateTime' => 'required',
        ]);

        Exhibitors::where('id', $this->exhibitorId)->update([
            'datetime_added' => $this->exhibitorDateTime,
        ]);

        $this->finalListOfExhibitors[$this->exhibitorArrayIndex]['datetime_added'] = Carbon::parse($this->exhibitorDateTime)->format('M j, Y g:i A');

        $this->resetEditExhibitorDateTimeFields();

        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => 'Exhibitor Datetime updated successfully!',
            'text' => ''
        ]);
    }



    public function deleteExhibitorConfirmation($index)
    {
        $this->activeDeleteIndex = $index;
        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure you want to delete?',
            'text' => "",
            'buttonConfirmText' => "Yes, delete it!",
            'livewireEmit' => "deleteExhibitorConfirmed",
        ]);
    }

    public function deleteExhibitor()
    {
        $exhibitor = Exhibitors::where('id', $this->finalListOfExhibitors[$this->activeDeleteIndex]['id'])->first();

        if ($exhibitor) {
            if ($exhibitor->logo_media_id) {
                mediaUsageUpdate(
                    MediaUsageUpdateTypes::REMOVED_ONLY->value,
                    $exhibitor->logo_media_id,
                    MediaEntityTypes::EXHIBITOR_LOGO->value,
                    $exhibitor->id,
                    getMediaUsageId($exhibitor->logo_media_id, MediaEntityTypes::EXHIBITOR_LOGO->value, $exhibitor->id),
                );
            }

            if ($exhibitor->banner_media_id) {
                mediaUsageUpdate(
                    MediaUsageUpdateTypes::REMOVED_ONLY->value,
                    $exhibitor->banner_media_id,
                    MediaEntityTypes::EXHIBITOR_BANNER->value,
                    $exhibitor->id,
                    getMediaUsageId($exhibitor->banner_media_id, MediaEntityTypes::EXHIBITOR_BANNER->value, $exhibitor->id),
                );
            }

            $exhibitor->delete();

            unset($this->finalListOfExhibitors[$this->activeDeleteIndex]);
            $this->finalListOfExhibitors = array_values($this->finalListOfExhibitors);
        }

        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => 'Exhibitor deleted successfully!',
            'text' => ''
        ]);
    }
}
