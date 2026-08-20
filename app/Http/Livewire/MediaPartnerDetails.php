<?php

namespace App\Http\Livewire;

use App\Enums\MediaEntityTypes;
use App\Enums\MediaUsageUpdateTypes;
use Livewire\Component;
use App\Models\Event as Events;
use App\Models\Media as Medias;
use App\Models\MediaPartner as MediaPartners;

class MediaPartnerDetails extends Component
{
    public $event, $mediaPartnerData;

    public $assetType, $editMediaPartnerAssetForm, $image_media_id, $image_placeholder_text;
    public $chooseImageModal, $mediaFileList = array(), $activeSelectedImage;

    public $name, $profile_html_text, $country, $contact_person_name, $email_address, $mobile_number, $website, $facebook, $linkedin, $twitter, $instagram;
    public $editMediaPartnerDetailsForm;

    protected $listeners = ['editMediaPartnerDetailsConfirmed' => 'editMediaPartnerDetails', 'editMediaPartnerAssetConfirmed' => 'editMediaPartnerAsset'];

    public function mount($eventId, $eventCategory, $mediaPartnerData)
    {
        $this->event = Events::where('id', $eventId)->where('category', $eventCategory)->first();
        $this->mediaPartnerData = $mediaPartnerData;
        $this->mediaFileList = getMediaFileList();
        $this->editMediaPartnerAssetForm = false;
        $this->editMediaPartnerDetailsForm = false;

    }

    public function render()
    {
        return view('livewire.event.media-partners.media-partner-details');
    }


    // EDIT MEDIA PARTNER ASSET
    public function showEditMediaPartnerAsset($assetType)
    {
        $this->assetType = $assetType;
        $this->editMediaPartnerAssetForm = true;
    }

    public function resetEditMediaPartnerAssetFields()
    {
        $this->editMediaPartnerAssetForm = false;
        $this->assetType = null;
        $this->image_media_id = null;
        $this->image_placeholder_text = null;
    }

    // public function editMediaPartnerAssetConfirmation()
    // {
        
    //     $this->validate([
    //         'image_placeholder_text' => 'required'
    //     ]);

    //     $this->dispatchBrowserEvent('swal:confirmation', [
    //         'type' => 'warning',
    //         'message' => 'Are you sure?',
    //         'text' => "",
    //         'buttonConfirmText' => "Yes, update it!",
    //         'livewireEmit' => "editMediaPartnerAssetConfirmed",
    //     ]);
    // }


public function editMediaPartnerAssetConfirmation()
{
    $this->validate([
        'image_media_id' => [
            'nullable',
            'exists:' . (new Medias())->getTable() . ',id',
        ],
        'image_placeholder_text' => [
            'nullable',
            'required_without:image_media_id',
            'url',
            'max:2048',
        ],
    ]);

    $this->dispatchBrowserEvent('swal:confirmation', [
        'type' => 'warning',
        'message' => 'Are you sure?',
        'text' => '',
        'buttonConfirmText' => 'Yes, update it!',
        'livewireEmit' => 'editMediaPartnerAssetConfirmed',
    ]);
}
    // public function editMediaPartnerAsset()
    // {
    //     if ($this->assetType == "Media partner logo") {
    //         MediaPartners::where('id', $this->mediaPartnerData['mediaPartnerId'])->update([
    //             'logo_media_id' => $this->image_media_id,
    //         ]);

    //         if ($this->mediaPartnerData['logo']['media_id'] != null) {
    //             mediaUsageUpdate(
    //                 MediaUsageUpdateTypes::REMOVED_THEN_ADD->value,
    //                 $this->image_media_id,
    //                 MediaEntityTypes::MEDIA_PARTNER_LOGO->value,
    //                 $this->mediaPartnerData['mediaPartnerId'],
    //                 $this->mediaPartnerData['logo']['media_usage_id']
    //             );
    //         } else {
    //             mediaUsageUpdate(
    //                 MediaUsageUpdateTypes::ADD_ONLY->value,
    //                 $this->image_media_id,
    //                 MediaEntityTypes::MEDIA_PARTNER_LOGO->value,
    //                 $this->mediaPartnerData['mediaPartnerId'],
    //                 $this->mediaPartnerData['logo']['media_usage_id']
    //             );
    //         }


    //         $this->mediaPartnerData['logo'] = [
    //             'media_id' => $this->image_media_id,
    //             'media_usage_id' => getMediaUsageId($this->image_media_id, MediaEntityTypes::MEDIA_PARTNER_LOGO->value, $this->mediaPartnerData['mediaPartnerId']),
    //             'url' => Medias::where('id', $this->image_media_id)->value('file_url'),
    //         ];
    //     } else {
    //         MediaPartners::where('id', $this->mediaPartnerData['mediaPartnerId'])->update([
    //             'banner_media_id' => $this->image_media_id,
    //         ]);

    //         if ($this->mediaPartnerData['banner']['media_id'] != null) {
    //             mediaUsageUpdate(
    //                 MediaUsageUpdateTypes::REMOVED_THEN_ADD->value,
    //                 $this->image_media_id,
    //                 MediaEntityTypes::MEDIA_PARTNER_BANNER->value,
    //                 $this->mediaPartnerData['mediaPartnerId'],
    //                 $this->mediaPartnerData['banner']['media_usage_id']
    //             );
    //         } else {
    //             mediaUsageUpdate(
    //                 MediaUsageUpdateTypes::ADD_ONLY->value,
    //                 $this->image_media_id,
    //                 MediaEntityTypes::MEDIA_PARTNER_BANNER->value,
    //                 $this->mediaPartnerData['mediaPartnerId'],
    //                 $this->mediaPartnerData['banner']['media_usage_id']
    //             );
    //         }

    //         $this->mediaPartnerData['banner'] = [
    //             'media_id' => $this->image_media_id,
    //             'media_usage_id' => getMediaUsageId($this->image_media_id, MediaEntityTypes::MEDIA_PARTNER_BANNER->value, $this->mediaPartnerData['mediaPartnerId']),
    //             'url' => Medias::where('id', $this->image_media_id)->value('file_url'),
    //         ];
    //     }

    //     $this->dispatchBrowserEvent('swal:success', [
    //         'type' => 'success',
    //         'message' => $this->assetType . ' updated succesfully!',
    //         'text' => "",
    //     ]);

    //     $this->resetEditMediaPartnerAssetFields();
    // }


    public function editMediaPartnerAsset()
{
    $mediaId = $this->image_media_id;

    if (!$mediaId && !empty($this->image_placeholder_text)) {
        $imageUrl = trim($this->image_placeholder_text);

        $path = parse_url($imageUrl, PHP_URL_PATH);
        $fileName = basename($path) ?: 'media-partner-image';
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

    if ($this->assetType === 'Media partner logo') {
        MediaPartners::where('id', $this->mediaPartnerData['mediaPartnerId'])->update([
            'logo_media_id' => $mediaId,
        ]);

        if (!empty($this->mediaPartnerData['logo']['media_id'])) {
            mediaUsageUpdate(
                MediaUsageUpdateTypes::REMOVED_THEN_ADD->value,
                $mediaId,
                MediaEntityTypes::MEDIA_PARTNER_LOGO->value,
                $this->mediaPartnerData['mediaPartnerId'],
                $this->mediaPartnerData['logo']['media_usage_id']
            );
        } else {
            mediaUsageUpdate(
                MediaUsageUpdateTypes::ADD_ONLY->value,
                $mediaId,
                MediaEntityTypes::MEDIA_PARTNER_LOGO->value,
                $this->mediaPartnerData['mediaPartnerId']
            );
        }

        $this->mediaPartnerData['logo'] = [
            'media_id' => $mediaId,
            'media_usage_id' => getMediaUsageId(
                $mediaId,
                MediaEntityTypes::MEDIA_PARTNER_LOGO->value,
                $this->mediaPartnerData['mediaPartnerId']
            ),
            'url' => Medias::where('id', $mediaId)->value('file_url'),
        ];
    } else {
        MediaPartners::where('id', $this->mediaPartnerData['mediaPartnerId'])->update([
            'banner_media_id' => $mediaId,
        ]);

        if (!empty($this->mediaPartnerData['banner']['media_id'])) {
            mediaUsageUpdate(
                MediaUsageUpdateTypes::REMOVED_THEN_ADD->value,
                $mediaId,
                MediaEntityTypes::MEDIA_PARTNER_BANNER->value,
                $this->mediaPartnerData['mediaPartnerId'],
                $this->mediaPartnerData['banner']['media_usage_id']
            );
        } else {
            mediaUsageUpdate(
                MediaUsageUpdateTypes::ADD_ONLY->value,
                $mediaId,
                MediaEntityTypes::MEDIA_PARTNER_BANNER->value,
                $this->mediaPartnerData['mediaPartnerId']
            );
        }

        $this->mediaPartnerData['banner'] = [
            'media_id' => $mediaId,
            'media_usage_id' => getMediaUsageId(
                $mediaId,
                MediaEntityTypes::MEDIA_PARTNER_BANNER->value,
                $this->mediaPartnerData['mediaPartnerId']
            ),
            'url' => Medias::where('id', $mediaId)->value('file_url'),
        ];
    }

    $this->dispatchBrowserEvent('swal:success', [
        'type' => 'success',
        'message' => $this->assetType . ' updated successfully!',
        'text' => '',
    ]);

    $this->resetEditMediaPartnerAssetFields();
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

    // public function selectChooseImage()
    // {
    //     $this->image_media_id = $this->activeSelectedImage['id'];
    //     $this->image_placeholder_text = $this->activeSelectedImage['file_name'];
    //     $this->activeSelectedImage = null;
    //     $this->chooseImageModal = false;
    // }


    public function selectChooseImage()
{
    $this->image_media_id = $this->activeSelectedImage['id'];
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



    // EDIT MEDIA PARTNER DETAILS
    public function showEditMediaPartnerDetails()
    {
        $this->name = $this->mediaPartnerData['name'];
        $this->profile_html_text = $this->mediaPartnerData['profile_html_text'];
        
        $this->country = $this->mediaPartnerData['country'];
        $this->contact_person_name = $this->mediaPartnerData['contact_person_name'];
        $this->email_address = $this->mediaPartnerData['email_address'];
        $this->mobile_number = $this->mediaPartnerData['mobile_number'];
        $this->website = $this->mediaPartnerData['website'];
        $this->facebook = $this->mediaPartnerData['facebook'];
        $this->linkedin = $this->mediaPartnerData['linkedin'];
        $this->twitter = $this->mediaPartnerData['twitter'];
        $this->instagram = $this->mediaPartnerData['instagram'];

        $this->editMediaPartnerDetailsForm = true;
    }

    public function resetEditMediaPartnerDetailsFields()
    {
        $this->editMediaPartnerDetailsForm = false;
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
    }

    public function editMediaPartnerDetailsConfirmation()
    {
        $this->validate([
            'name' => 'required',
        ]);

        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure?',
            'text' => "",
            'buttonConfirmText' => "Yes, update it!",
            'livewireEmit' => "editMediaPartnerDetailsConfirmed",
        ]);
    }

    public function editMediaPartnerDetails()
    {
        MediaPartners::where('id', $this->mediaPartnerData['mediaPartnerId'])->update([
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

        $this->mediaPartnerData['name'] = $this->name;
        $this->mediaPartnerData['profile_html_text'] = $this->profile_html_text;
        
        $this->mediaPartnerData['country'] = $this->country;
        $this->mediaPartnerData['contact_person_name'] = $this->contact_person_name;
        $this->mediaPartnerData['email_address'] = $this->email_address;
        $this->mediaPartnerData['mobile_number'] = $this->mobile_number;
        $this->mediaPartnerData['website'] = $this->website;
        $this->mediaPartnerData['facebook'] = $this->facebook;
        $this->mediaPartnerData['linkedin'] = $this->linkedin;
        $this->mediaPartnerData['twitter'] = $this->twitter;
        $this->mediaPartnerData['instagram'] = $this->instagram;

        $this->resetEditMediaPartnerDetailsFields();

        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => 'Media partner details updated succesfully!',
            'text' => "",
        ]);
    }



    // DELETE ASSET
    public function deleteMediaPartnerAsset($deleteAssetType)
    {
        if ($deleteAssetType == "Media partner logo") {
            MediaPartners::where('id', $this->mediaPartnerData['mediaPartnerId'])->update([
                'logo_media_id' => null,
            ]);

            mediaUsageUpdate(
                MediaUsageUpdateTypes::REMOVED_ONLY->value,
                $this->mediaPartnerData['logo']['media_id'],
                MediaEntityTypes::MEDIA_PARTNER_LOGO->value,
                $this->mediaPartnerData['mediaPartnerId'],
                $this->mediaPartnerData['logo']['media_usage_id']
            );

            $this->mediaPartnerData['logo'] = [
                'media_id' => null,
                'media_usage_id' => null,
                'url' => null,
            ];
        } else {
            MediaPartners::where('id', $this->mediaPartnerData['mediaPartnerId'])->update([
                'banner_media_id' => null,
            ]);

            mediaUsageUpdate(
                MediaUsageUpdateTypes::REMOVED_ONLY->value,
                $this->mediaPartnerData['banner']['media_id'],
                MediaEntityTypes::MEDIA_PARTNER_BANNER->value,
                $this->mediaPartnerData['mediaPartnerId'],
                $this->mediaPartnerData['banner']['media_usage_id']
            );

            $this->mediaPartnerData['banner'] = [
                'media_id' => null,
                'media_usage_id' => null,
                'url' => null,
            ];
        }
    }
}
