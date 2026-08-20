<?php

namespace App\Http\Livewire;

use App\Enums\MediaEntityTypes;
use App\Enums\MediaUsageUpdateTypes;
use Livewire\Component;
use App\Models\Event as Events;
use App\Models\Sponsor as Sponsors;
use App\Models\SponsorType as SponsorTypes;
use App\Models\Feature as Features;
use App\Models\Media as Medias;
use Livewire\WithFileUploads;

class SponsorDetails extends Component
{
    use WithFileUploads;

    public $event, $sponsorData;

    // EDIT ASSETS
    public $assetType, $editSponsorAssetForm, $image_media_id, $image_placeholder_text;
    public $chooseImageModal, $mediaFileList = array(), $activeSelectedImage;

    public $feature_id, $sponsor_type_id, $name, $profile_html_text, $country, $contact_person_name, $email_address, $mobile_number, $website, $facebook, $linkedin, $twitter, $instagram, $categoryChoices = array(), $typeChoices = array();
    public $editSponsorDetailsForm;

    protected $listeners = ['editSponsorDetailsConfirmed' => 'editSponsorDetails', 'editSponsorAssetConfirmed' => 'editSponsorAsset'];

    public function mount($eventId, $eventCategory, $sponsorData)
    {
        $this->event = Events::where('id', $eventId)->where('category', $eventCategory)->first();
        $this->sponsorData = $sponsorData;
        $this->mediaFileList = getMediaFileList();
        $this->editSponsorAssetForm = false;
        $this->editSponsorDetailsForm = false;
    }

    public function render()
    {
        return view('livewire.event.sponsors.sponsor-details');
    }


    // EDIT SPONSOR DETAILS
    public function showEditSponsorDetails()
    {
        array_push($this->categoryChoices, [
            'value' => $this->event->short_name,
            'id' => 0,
        ]);

        $features = Features::where('event_id', $this->event->id)->get();
        if($features->isNotEmpty()){
            foreach($features as $feature){
                array_push($this->categoryChoices, [
                    'value' => $feature->short_name,
                    'id' => $feature->id,
                ]);
            }
        }
        
        $sponsorTypes = SponsorTypes::where('event_id', $this->event->id)->get();
        if($sponsorTypes->isNotEmpty()){
            foreach($sponsorTypes as $sponsorType){
                array_push($this->typeChoices, [
                    'value' => $sponsorType->name,
                    'id' => $sponsorType->id,
                ]);
            }
        }

        $this->feature_id = $this->sponsorData['feature_id'];
        $this->sponsor_type_id = $this->sponsorData['sponsor_type_id'];

        $this->name = $this->sponsorData['name'];
        $this->profile_html_text = $this->sponsorData['profile_html_text'];
        
        $this->country = $this->sponsorData['country'];
        $this->contact_person_name = $this->sponsorData['contact_person_name'];
        $this->email_address = $this->sponsorData['email_address'];
        $this->mobile_number = $this->sponsorData['mobile_number'];
        $this->website = $this->sponsorData['website'];
        $this->facebook = $this->sponsorData['facebook'];
        $this->linkedin = $this->sponsorData['linkedin'];
        $this->twitter = $this->sponsorData['twitter'];
        $this->instagram = $this->sponsorData['instagram'];
        $this->editSponsorDetailsForm = true;
    }

    public function resetEditSponsorDetailsFields()
    {
        $this->editSponsorDetailsForm = false;
        $this->feature_id = null;
        $this->sponsor_type_id = null;
        $this->name = null;
        $this->profile_html_text = null;

        $this->country = null;
        $this->contact_person_name = null;
        $this->email_address = null;
        $this->mobile_number = null;
        $this->website = null;
        $this->facebook = null;
        $this->linkedin = null;
        $this->twitter = null;
        $this->instagram = null;

        $this->typeChoices = array();
        $this->categoryChoices = array();
    }

    public function editSponsorDetailsConfirmation()
    {
        $this->validate([
            'feature_id' => 'required',
            'sponsor_type_id' => 'required',
            'name' => 'required',
        ]);

        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure?',
            'text' => "",
            'buttonConfirmText' => "Yes, update it!",
            'livewireEmit' => "editSponsorDetailsConfirmed",
        ]);
    }

    public function editSponsorDetails()
    {
        Sponsors::where('id', $this->sponsorData['sponsorId'])->update([
            'feature_id' => $this->feature_id,
            'sponsor_type_id' => $this->sponsor_type_id,
            'name' => $this->name,
            'profile_html_text' => $this->profile_html_text,

            'country' => $this->country == "" ? null : $this->country,
            'contact_person_name' => $this->contact_person_name == "" ? null : $this->contact_person_name,
            'email_address' => $this->email_address == "" ? null : $this->email_address,
            'mobile_number' => $this->mobile_number == "" ? null : $this->mobile_number,
            'website' => $this->website == "" ? null : $this->website,
            'facebook' => $this->facebook == "" ? null : $this->facebook,
            'linkedin' => $this->linkedin == "" ? null : $this->linkedin,
            'twitter' => $this->twitter == "" ? null : $this->twitter,
            'instagram' => $this->instagram == "" ? null : $this->instagram,
        ]);

        
        foreach($this->categoryChoices as $categoryChoice){
            if($categoryChoice['id'] == $this->feature_id){
                $selectedCategory = $categoryChoice['value'];
            }
        }

        foreach($this->typeChoices as $typeChoice){
            if($typeChoice['id'] == $this->sponsor_type_id){
                $selectedType = $typeChoice['value'];
            }
        }

        $this->sponsorData['categoryName'] = $selectedCategory;
        $this->sponsorData['feature_id'] = $this->feature_id;
        $this->sponsorData['typeName'] = $selectedType;
        $this->sponsorData['sponsor_type_id'] = $this->sponsor_type_id;

        $this->sponsorData['name'] = $this->name;
        $this->sponsorData['profile_html_text'] = $this->profile_html_text;

        $this->sponsorData['country'] = $this->country;
        $this->sponsorData['contact_person_name'] = $this->contact_person_name;
        $this->sponsorData['email_address'] = $this->email_address;
        $this->sponsorData['mobile_number'] = $this->mobile_number;
        $this->sponsorData['website'] = $this->website;
        $this->sponsorData['facebook'] = $this->facebook;
        $this->sponsorData['linkedin'] = $this->linkedin;
        $this->sponsorData['twitter'] = $this->twitter;
        $this->sponsorData['instagram'] = $this->instagram;

        $this->resetEditSponsorDetailsFields();

        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => 'Sponsor details updated succesfully!',
            'text' => "",
        ]);
    }




    // EDIT SPONSOR ASSET
    public function showEditSponsorAsset($assetType)
    {
        $this->assetType = $assetType;
        $this->editSponsorAssetForm = true;
    }

    public function resetEditSponsorAssetFields()
    {
        $this->editSponsorAssetForm = false;
        $this->assetType = null;
        $this->image_media_id = null;
        $this->image_placeholder_text = null;
    }

public function editSponsorAssetConfirmation()
{
    $this->validate([
        'image_media_id' => 'nullable|exists:medias,id',
        'image_placeholder_text' => 'nullable|required_without:image_media_id|url|max:2048',
    ]);

    $this->dispatchBrowserEvent('swal:confirmation', [
        'type' => 'warning',
        'message' => 'Are you sure?',
        'text' => '',
        'buttonConfirmText' => 'Yes, update it!',
        'livewireEmit' => 'editSponsorAssetConfirmed',
    ]);
}

    // public function editSponsorAsset()
    // {
    //     if ($this->assetType == "Sponsor logo") {
    //         Sponsors::where('id', $this->sponsorData['sponsorId'])->update([
    //             'logo_media_id' => $this->image_media_id,
    //         ]);

    //         if ($this->sponsorData['logo']['media_id'] != null) {
    //             mediaUsageUpdate(
    //                 MediaUsageUpdateTypes::REMOVED_THEN_ADD->value,
    //                 $this->image_media_id,
    //                 MediaEntityTypes::SPONSOR_LOGO->value,
    //                 $this->sponsorData['sponsorId'],
    //                 $this->sponsorData['logo']['media_usage_id']
    //             );
    //         } else {
    //             mediaUsageUpdate(
    //                 MediaUsageUpdateTypes::ADD_ONLY->value,
    //                 $this->image_media_id,
    //                 MediaEntityTypes::SPONSOR_LOGO->value,
    //                 $this->sponsorData['sponsorId'],
    //                 $this->sponsorData['logo']['media_usage_id']
    //             );
    //         }
            
    //         $this->sponsorData['logo'] = [
    //             'media_id' => $this->image_media_id,
    //             'media_usage_id' => getMediaUsageId($this->image_media_id, MediaEntityTypes::SPONSOR_LOGO->value, $this->sponsorData['sponsorId']),
    //             'url' => Medias::where('id', $this->image_media_id)->value('file_url'),
    //         ];
    //     } else {
    //         Sponsors::where('id', $this->sponsorData['sponsorId'])->update([
    //             'banner_media_id' => $this->image_media_id,
    //         ]);

    //         if ($this->sponsorData['banner']['media_id'] != null) {
    //             mediaUsageUpdate(
    //                 MediaUsageUpdateTypes::REMOVED_THEN_ADD->value,
    //                 $this->image_media_id,
    //                 MediaEntityTypes::SPONSOR_BANNER->value,
    //                 $this->sponsorData['sponsorId'],
    //                 $this->sponsorData['banner']['media_usage_id']
    //             );
    //         } else {
    //             mediaUsageUpdate(
    //                 MediaUsageUpdateTypes::ADD_ONLY->value,
    //                 $this->image_media_id,
    //                 MediaEntityTypes::SPONSOR_BANNER->value,
    //                 $this->sponsorData['sponsorId'],
    //                 $this->sponsorData['banner']['media_usage_id']
    //             );
    //         }
            
    //         $this->sponsorData['banner'] = [
    //             'media_id' => $this->image_media_id,
    //             'media_usage_id' => getMediaUsageId($this->image_media_id, MediaEntityTypes::SPONSOR_BANNER->value, $this->sponsorData['sponsorId']),
    //             'url' => Medias::where('id', $this->image_media_id)->value('file_url'),
    //         ];
    //     }

    //     $this->dispatchBrowserEvent('swal:success', [
    //         'type' => 'success',
    //         'message' => $this->assetType . ' updated succesfully!',
    //         'text' => "",
    //     ]);

    //     $this->resetEditSponsorAssetFields();
    // }


    public function editSponsorAsset()
{
    $mediaId = $this->image_media_id;

    if (!$mediaId && !empty($this->image_placeholder_text)) {
        $imageUrl = trim($this->image_placeholder_text);

        $path = parse_url($imageUrl, PHP_URL_PATH);
        $fileName = basename($path) ?: 'sponsor-image';
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

    if ($this->assetType == "Sponsor logo") {
        Sponsors::where('id', $this->sponsorData['sponsorId'])->update([
            'logo_media_id' => $mediaId,
        ]);

        if ($this->sponsorData['logo']['media_id'] != null) {
            mediaUsageUpdate(
                MediaUsageUpdateTypes::REMOVED_THEN_ADD->value,
                $mediaId,
                MediaEntityTypes::SPONSOR_LOGO->value,
                $this->sponsorData['sponsorId'],
                $this->sponsorData['logo']['media_usage_id']
            );
        } else {
            mediaUsageUpdate(
                MediaUsageUpdateTypes::ADD_ONLY->value,
                $mediaId,
                MediaEntityTypes::SPONSOR_LOGO->value,
                $this->sponsorData['sponsorId']
            );
        }

        $this->sponsorData['logo'] = [
            'media_id' => $mediaId,
            'media_usage_id' => getMediaUsageId(
                $mediaId,
                MediaEntityTypes::SPONSOR_LOGO->value,
                $this->sponsorData['sponsorId']
            ),
            'url' => Medias::where('id', $mediaId)->value('file_url'),
        ];
    } else {
        Sponsors::where('id', $this->sponsorData['sponsorId'])->update([
            'banner_media_id' => $mediaId,
        ]);

        if ($this->sponsorData['banner']['media_id'] != null) {
            mediaUsageUpdate(
                MediaUsageUpdateTypes::REMOVED_THEN_ADD->value,
                $mediaId,
                MediaEntityTypes::SPONSOR_BANNER->value,
                $this->sponsorData['sponsorId'],
                $this->sponsorData['banner']['media_usage_id']
            );
        } else {
            mediaUsageUpdate(
                MediaUsageUpdateTypes::ADD_ONLY->value,
                $mediaId,
                MediaEntityTypes::SPONSOR_BANNER->value,
                $this->sponsorData['sponsorId']
            );
        }

        $this->sponsorData['banner'] = [
            'media_id' => $mediaId,
            'media_usage_id' => getMediaUsageId(
                $mediaId,
                MediaEntityTypes::SPONSOR_BANNER->value,
                $this->sponsorData['sponsorId']
            ),
            'url' => Medias::where('id', $mediaId)->value('file_url'),
        ];
    }

    $this->dispatchBrowserEvent('swal:success', [
        'type' => 'success',
        'message' => $this->assetType . ' updated successfully!',
        'text' => '',
    ]);

    $this->resetEditSponsorAssetFields();
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



    // DELETE ASSET
    public function deleteSponsorAsset($deleteAssetType)
    {
        if ($deleteAssetType == "Sponsor logo") {
            Sponsors::where('id', $this->sponsorData['sponsorId'])->update([
                'logo_media_id' => null,
            ]);

            mediaUsageUpdate(
                MediaUsageUpdateTypes::REMOVED_ONLY->value,
                $this->sponsorData['logo']['media_id'],
                MediaEntityTypes::SPONSOR_LOGO->value,
                $this->sponsorData['sponsorId'],
                $this->sponsorData['logo']['media_usage_id']
            );

            $this->sponsorData['logo'] = [
                'media_id' => null,
                'media_usage_id' => null,
                'url' => null,
            ];
        } else {
            Sponsors::where('id', $this->sponsorData['sponsorId'])->update([
                'banner_media_id' => null,
            ]);

            mediaUsageUpdate(
                MediaUsageUpdateTypes::REMOVED_ONLY->value,
                $this->sponsorData['banner']['media_id'],
                MediaEntityTypes::SPONSOR_BANNER->value,
                $this->sponsorData['sponsorId'],
                $this->sponsorData['banner']['media_usage_id']
            );

            $this->sponsorData['banner'] = [
                'media_id' => null,
                'media_usage_id' => null,
                'url' => null,
            ];
        }
    }


}
