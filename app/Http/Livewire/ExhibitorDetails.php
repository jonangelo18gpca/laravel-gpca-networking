<?php

namespace App\Http\Livewire;

use App\Enums\MediaEntityTypes;
use App\Enums\MediaUsageUpdateTypes;
use Livewire\Component;
use App\Models\Event as Events;
use App\Models\Exhibitor as Exhibitors;
use App\Models\Media as Medias;

class ExhibitorDetails extends Component
{
    public $event, $exhibitorData;

    public $assetType, $editExhibitorAssetForm, $image_media_id, $image_placeholder_text;
    public $chooseImageModal, $mediaFileList = array(), $activeSelectedImage;

    public $name, $profile_html_text, $stand_number, $country, $contact_person_name, $email_address, $mobile_number, $website, $facebook, $linkedin, $twitter, $instagram, $floorplan_link;
    public $editExhibitorDetailsForm;

    protected $listeners = ['editExhibitorDetailsConfirmed' => 'editExhibitorDetails', 'editExhibitorAssetConfirmed' => 'editExhibitorAsset'];

    public function mount($eventId, $eventCategory, $exhibitorData)
    {
        $this->event = Events::where('id', $eventId)->where('category', $eventCategory)->first();
        $this->exhibitorData = $exhibitorData;
        $this->mediaFileList = getMediaFileList();
        $this->editExhibitorAssetForm = false;
        $this->editExhibitorDetailsForm = false;
    }

    public function render()
    {
        return view('livewire.event.exhibitors.exhibitor-details');
    }



    // EDIT EXHIBITOR ASSET
    public function showEditExhibitorAsset($assetType)
    {
        $this->assetType = $assetType;
        $this->editExhibitorAssetForm = true;
    }

    public function resetEditExhibitorAssetFields()
    {
        $this->editExhibitorAssetForm = false;
        $this->assetType = null;
        $this->image_media_id = null;
        $this->image_placeholder_text = null;
    }

    public function editExhibitorAssetConfirmation()
    {
        // $this->validate([
        //     'image_placeholder_text' => 'required'
        // ]);


        $this->validate([
    'image_media_id' => 'nullable|exists:medias,id',
    'image_placeholder_text' => 'nullable|required_without:image_media_id|url|max:2048',
]);


        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure?',
            'text' => "",
            'buttonConfirmText' => "Yes, update it!",
            'livewireEmit' => "editExhibitorAssetConfirmed",
        ]);
    }

    // public function editExhibitorAsset()
    // {
    //     if ($this->assetType == "Exhibitor logo") {
    //         Exhibitors::where('id', $this->exhibitorData['exhibitorId'])->update([
    //             'logo_media_id' => $this->image_media_id,
    //         ]);

    //         if ($this->exhibitorData['logo']['media_id'] != null) {
    //             mediaUsageUpdate(
    //                 MediaUsageUpdateTypes::REMOVED_THEN_ADD->value,
    //                 $this->image_media_id,
    //                 MediaEntityTypes::EXHIBITOR_LOGO->value,
    //                 $this->exhibitorData['exhibitorId'],
    //                 $this->exhibitorData['logo']['media_usage_id']
    //             );
    //         } else {
    //             mediaUsageUpdate(
    //                 MediaUsageUpdateTypes::ADD_ONLY->value,
    //                 $this->image_media_id,
    //                 MediaEntityTypes::EXHIBITOR_LOGO->value,
    //                 $this->exhibitorData['exhibitorId'],
    //                 $this->exhibitorData['logo']['media_usage_id']
    //             );
    //         }

    //         $this->exhibitorData['logo'] = [
    //             'media_id' => $this->image_media_id,
    //             'media_usage_id' => getMediaUsageId($this->image_media_id, MediaEntityTypes::EXHIBITOR_LOGO->value, $this->exhibitorData['exhibitorId']),
    //             'url' => Medias::where('id', $this->image_media_id)->value('file_url'),
    //         ];
    //     } else {
    //         Exhibitors::where('id', $this->exhibitorData['exhibitorId'])->update([
    //             'banner_media_id' => $this->image_media_id,
    //         ]);

    //         if ($this->exhibitorData['banner']['media_id'] != null) {
    //             mediaUsageUpdate(
    //                 MediaUsageUpdateTypes::REMOVED_THEN_ADD->value,
    //                 $this->image_media_id,
    //                 MediaEntityTypes::EXHIBITOR_BANNER->value,
    //                 $this->exhibitorData['exhibitorId'],
    //                 $this->exhibitorData['banner']['media_usage_id']
    //             );
    //         } else {
    //             mediaUsageUpdate(
    //                 MediaUsageUpdateTypes::ADD_ONLY->value,
    //                 $this->image_media_id,
    //                 MediaEntityTypes::EXHIBITOR_BANNER->value,
    //                 $this->exhibitorData['exhibitorId'],
    //                 $this->exhibitorData['banner']['media_usage_id']
    //             );
    //         }

    //         $this->exhibitorData['banner'] = [
    //             'media_id' => $this->image_media_id,
    //             'media_usage_id' => getMediaUsageId($this->image_media_id, MediaEntityTypes::EXHIBITOR_BANNER->value, $this->exhibitorData['exhibitorId']),
    //             'url' => Medias::where('id', $this->image_media_id)->value('file_url'),
    //         ];
    //     }

    //     $this->dispatchBrowserEvent('swal:success', [
    //         'type' => 'success',
    //         'message' => $this->assetType . ' updated succesfully!',
    //         'text' => "",
    //     ]);

    //     $this->resetEditExhibitorAssetFields();
    // }

public function editExhibitorAsset()
{
    $mediaId = $this->image_media_id;

    if (!$mediaId && !empty($this->image_placeholder_text)) {
        $imageUrl = trim($this->image_placeholder_text);

        $path = parse_url($imageUrl, PHP_URL_PATH);
        $fileName = basename($path) ?: 'exhibitor-image';
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $media = Medias::create([
            'file_url' => $imageUrl,
            'file_directory' => 'external-url',
            'file_name' => $fileName,
            'file_type' => $extension ?: 'image',
            'file_size' => 0,
            'width' => 0,
            'height' => 0,
            'date_uploaded' => now(),
        ]);

        $mediaId = $media->id;
    }

    if (!$mediaId) {
        $this->addError(
            'image_placeholder_text',
            'Please choose an image or provide a valid image URL.'
        );

        return;
    }

    if ($this->assetType === 'Exhibitor logo') {
        Exhibitors::where('id', $this->exhibitorData['exhibitorId'])->update([
            'logo_media_id' => $mediaId,
        ]);

        if (!empty($this->exhibitorData['logo']['media_id'])) {
            mediaUsageUpdate(
                MediaUsageUpdateTypes::REMOVED_THEN_ADD->value,
                $mediaId,
                MediaEntityTypes::EXHIBITOR_LOGO->value,
                $this->exhibitorData['exhibitorId'],
                $this->exhibitorData['logo']['media_usage_id']
            );
        } else {
            mediaUsageUpdate(
                MediaUsageUpdateTypes::ADD_ONLY->value,
                $mediaId,
                MediaEntityTypes::EXHIBITOR_LOGO->value,
                $this->exhibitorData['exhibitorId']
            );
        }

        $this->exhibitorData['logo'] = [
            'media_id' => $mediaId,
            'media_usage_id' => getMediaUsageId(
                $mediaId,
                MediaEntityTypes::EXHIBITOR_LOGO->value,
                $this->exhibitorData['exhibitorId']
            ),
            'url' => Medias::where('id', $mediaId)->value('file_url'),
        ];
    } else {
        Exhibitors::where('id', $this->exhibitorData['exhibitorId'])->update([
            'banner_media_id' => $mediaId,
        ]);

        if (!empty($this->exhibitorData['banner']['media_id'])) {
            mediaUsageUpdate(
                MediaUsageUpdateTypes::REMOVED_THEN_ADD->value,
                $mediaId,
                MediaEntityTypes::EXHIBITOR_BANNER->value,
                $this->exhibitorData['exhibitorId'],
                $this->exhibitorData['banner']['media_usage_id']
            );
        } else {
            mediaUsageUpdate(
                MediaUsageUpdateTypes::ADD_ONLY->value,
                $mediaId,
                MediaEntityTypes::EXHIBITOR_BANNER->value,
                $this->exhibitorData['exhibitorId']
            );
        }

        $this->exhibitorData['banner'] = [
            'media_id' => $mediaId,
            'media_usage_id' => getMediaUsageId(
                $mediaId,
                MediaEntityTypes::EXHIBITOR_BANNER->value,
                $this->exhibitorData['exhibitorId']
            ),
            'url' => Medias::where('id', $mediaId)->value('file_url'),
        ];
    }

    $this->dispatchBrowserEvent('swal:success', [
        'type' => 'success',
        'message' => $this->assetType . ' updated successfully!',
        'text' => '',
    ]);

    $this->resetEditExhibitorAssetFields();
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



    // EDIT EXHIBITOR DETAILS
    public function showEditExhibitorDetails()
    {
        $this->name = $this->exhibitorData['name'];
        $this->profile_html_text = $this->exhibitorData['profile_html_text'];
        $this->stand_number = $this->exhibitorData['stand_number'];

        $this->country = $this->exhibitorData['country'];
        $this->contact_person_name = $this->exhibitorData['contact_person_name'];
        $this->email_address = $this->exhibitorData['email_address'];
        $this->mobile_number = $this->exhibitorData['mobile_number'];
        $this->website = $this->exhibitorData['website'];
        $this->facebook = $this->exhibitorData['facebook'];
        $this->linkedin = $this->exhibitorData['linkedin'];
        $this->twitter = $this->exhibitorData['twitter'];
        $this->instagram = $this->exhibitorData['instagram'];
        $this->floorplan_link = $this->exhibitorData['floorplan_link'];

        $this->editExhibitorDetailsForm = true;
    }

    public function resetEditExhibitorDetailsFields()
    {
        $this->editExhibitorDetailsForm = false;
        $this->name = null;
        $this->profile_html_text = null;
        $this->stand_number = null;

        $this->country = null;
        $this->contact_person_name = null;
        $this->email_address = null;
        $this->mobile_number = null;
        $this->website = null;
        $this->facebook = null;
        $this->linkedin = null;
        $this->twitter = null;
        $this->instagram = null;
        $this->floorplan_link = null;
    }

    public function editExhibitorDetailsConfirmation()
    {
        $this->validate([
            'name' => 'required',
        ]);

        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure?',
            'text' => "",
            'buttonConfirmText' => "Yes, update it!",
            'livewireEmit' => "editExhibitorDetailsConfirmed",
        ]);
    }

    public function editExhibitorDetails()
    {
        Exhibitors::where('id', $this->exhibitorData['exhibitorId'])->update([
            'name' => $this->name,
            'profile_html_text' => $this->profile_html_text,
            'stand_number' => $this->stand_number,

            'country' => $this->country == "" ? null : $this->country,
            'contact_person_name' => $this->contact_person_name == "" ? null : $this->contact_person_name,
            'email_address' => $this->email_address == "" ? null : $this->email_address,
            'mobile_number' => $this->mobile_number == "" ? null : $this->mobile_number,
            'website' => $this->website == "" ? null : $this->website,
            'facebook' => $this->facebook == "" ? null : $this->facebook,
            'linkedin' => $this->linkedin == "" ? null : $this->linkedin,
            'twitter' => $this->twitter == "" ? null : $this->twitter,
            'instagram' => $this->instagram == "" ? null : $this->instagram,
            'floorplan_link' => $this->floorplan_link == "" ? null : $this->floorplan_link,
        ]);

        $this->exhibitorData['name'] = $this->name;
        $this->exhibitorData['profile_html_text'] = $this->profile_html_text;
        $this->exhibitorData['stand_number'] = $this->stand_number;

        $this->exhibitorData['country'] = $this->country;
        $this->exhibitorData['contact_person_name'] = $this->contact_person_name;
        $this->exhibitorData['email_address'] = $this->email_address;
        $this->exhibitorData['mobile_number'] = $this->mobile_number;
        $this->exhibitorData['website'] = $this->website;
        $this->exhibitorData['facebook'] = $this->facebook;
        $this->exhibitorData['linkedin'] = $this->linkedin;
        $this->exhibitorData['twitter'] = $this->twitter;
        $this->exhibitorData['instagram'] = $this->instagram;
        $this->exhibitorData['floorplan_link'] = $this->floorplan_link;

        $this->resetEditExhibitorDetailsFields();

        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => 'Exhibitor details updated succesfully!',
            'text' => "",
        ]);
    }



    // DELETE ASSET
    public function deleteExhibitorAsset($deleteAssetType)
    {
        if ($deleteAssetType == "Exhibitor logo") {
            Exhibitors::where('id', $this->exhibitorData['exhibitorId'])->update([
                'logo_media_id' => null,
            ]);

            mediaUsageUpdate(
                MediaUsageUpdateTypes::REMOVED_ONLY->value,
                $this->exhibitorData['logo']['media_id'],
                MediaEntityTypes::EXHIBITOR_LOGO->value,
                $this->exhibitorData['exhibitorId'],
                $this->exhibitorData['logo']['media_usage_id']
            );

            $this->exhibitorData['logo'] = [
                'media_id' => null,
                'media_usage_id' => null,
                'url' => null,
            ];
        } else {
            Exhibitors::where('id', $this->exhibitorData['exhibitorId'])->update([
                'banner_media_id' => null,
            ]);

            mediaUsageUpdate(
                MediaUsageUpdateTypes::REMOVED_ONLY->value,
                $this->exhibitorData['banner']['media_id'],
                MediaEntityTypes::EXHIBITOR_BANNER->value,
                $this->exhibitorData['exhibitorId'],
                $this->exhibitorData['banner']['media_usage_id']
            );

            $this->exhibitorData['banner'] = [
                'media_id' => null,
                'media_usage_id' => null,
                'url' => null,
            ];
        }
    }
}
