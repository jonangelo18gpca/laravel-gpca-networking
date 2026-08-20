<?php

namespace App\Http\Livewire;

use App\Enums\MediaEntityTypes;
use App\Enums\MediaUsageUpdateTypes;
use App\Models\Event as Events;
use App\Models\Speaker as Speakers;
use App\Models\SpeakerType as SpeakerTypes;
use App\Models\Feature as Features;
use App\Models\Media as Medias;
use Livewire\Component;

class SpeakerDetails extends Component
{
    public $event, $salutations, $speakerData;

    // EDIT ASSETS
    public $assetType, $editSpeakerAssetForm, $image_media_id, $image_placeholder_text;
    public $chooseImageModal, $mediaFileList = array(), $activeSelectedImage;

    // EDIT DETAILS
    public $feature_id, $speaker_type_id, $salutation, $first_name, $middle_name, $last_name, $company_name, $job_title, $biography_html_text, $country, $email_address, $mobile_number, $website, $facebook, $linkedin, $twitter, $instagram;
    public $categoryChoices = array(), $typeChoices = array();
    public $editSpeakerDetailsForm;

    protected $listeners = ['editSpeakerDetailsConfirmed' => 'editSpeakerDetails', 'editSpeakerAssetConfirmed' => 'editSpeakerAsset'];

    public function mount($eventId, $eventCategory, $speakerData)
    {
        $this->salutations = config('app.salutations');
        $this->event = Events::where('id', $eventId)->where('category', $eventCategory)->first();
        $this->speakerData = $speakerData;
        $this->mediaFileList = getMediaFileList();
        $this->editSpeakerAssetForm = false;
        $this->editSpeakerDetailsForm = false;
    }
    public function render()
    {
        return view('livewire.event.speakers.speaker-details');
    }


    // EDIT SPEAKER DETAILS
    public function showEditSpeakerDetails()
    {
        $speakerTypes = SpeakerTypes::where('event_id', $this->event->id)->get();

        if ($speakerTypes->isNotEmpty()) {
            foreach ($speakerTypes as $speakerType) {
                array_push($this->typeChoices, [
                    'value' => $speakerType->name,
                    'id' => $speakerType->id,
                ]);
            }
        }

        array_push($this->categoryChoices, [
            'value' => $this->event->short_name,
            'id' => 0,
        ]);

        $features = Features::where('event_id', $this->event->id)->get();
        if ($features->isNotEmpty()) {
            foreach ($features as $feature) {
                array_push($this->categoryChoices, [
                    'value' => $feature->short_name,
                    'id' => $feature->id,
                ]);
            }
        }
        $this->feature_id = $this->speakerData['feature_id'];
        $this->speaker_type_id = $this->speakerData['speaker_type_id'];

        $this->salutation = $this->speakerData['salutation'];
        $this->first_name = $this->speakerData['first_name'];
        $this->middle_name = $this->speakerData['middle_name'];
        $this->last_name = $this->speakerData['last_name'];
        $this->company_name = $this->speakerData['company_name'];
        $this->job_title = $this->speakerData['job_title'];
        $this->biography_html_text = $this->speakerData['biography_html_text'];

        $this->country = $this->speakerData['country'];
        $this->email_address = $this->speakerData['email_address'];
        $this->mobile_number = $this->speakerData['mobile_number'];
        $this->website = $this->speakerData['website'];
        $this->facebook = $this->speakerData['facebook'];
        $this->linkedin = $this->speakerData['linkedin'];
        $this->twitter = $this->speakerData['twitter'];
        $this->instagram = $this->speakerData['instagram'];
        $this->editSpeakerDetailsForm = true;
    }

    public function resetEditSpeakerDetailsFields()
    {
        $this->editSpeakerDetailsForm = false;
        $this->feature_id = null;
        $this->speaker_type_id = null;
        $this->salutation = null;
        $this->first_name = null;
        $this->middle_name = null;
        $this->last_name = null;
        $this->company_name = null;
        $this->job_title = null;
        $this->biography_html_text = null;

        $this->country = null;
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

    public function editSpeakerDetailsConfirmation()
    {
        $this->validate([
            'feature_id' => 'required',
            'speaker_type_id' => 'required',
            'first_name' => 'required',
            'last_name' => 'required',
        ]);

        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure?',
            'text' => "",
            'buttonConfirmText' => "Yes, update it!",
            'livewireEmit' => "editSpeakerDetailsConfirmed",
        ]);
    }

    public function editSpeakerDetails()
    {
        Speakers::where('id', $this->speakerData['id'])->update([
            'feature_id' => $this->feature_id,
            'speaker_type_id' => $this->speaker_type_id,
            'salutation' => $this->salutation,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'company_name' => $this->company_name,
            'job_title' => $this->job_title,
            'biography_html_text' => $this->biography_html_text,

            'country' => $this->country == "" ? null : $this->country,
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
            if($typeChoice['id'] == $this->speaker_type_id){
                $selectedType = $typeChoice['value'];
            }
        }
        
        $this->speakerData['categoryName'] = $selectedCategory;
        $this->speakerData['feature_id'] = $this->feature_id;
        $this->speakerData['typeName'] = $selectedType;
        $this->speakerData['speaker_type_id'] = $this->speaker_type_id;

        $this->speakerData['salutation'] = $this->salutation;
        $this->speakerData['first_name'] = $this->first_name;
        $this->speakerData['middle_name'] = $this->middle_name;
        $this->speakerData['last_name'] = $this->last_name;
        $this->speakerData['company_name'] = $this->company_name;
        $this->speakerData['job_title'] = $this->job_title;
        $this->speakerData['biography_html_text'] = $this->biography_html_text;

        $this->speakerData['country'] = $this->country;
        $this->speakerData['email_address'] = $this->email_address;
        $this->speakerData['mobile_number'] = $this->mobile_number;
        $this->speakerData['website'] = $this->website;
        $this->speakerData['facebook'] = $this->facebook;
        $this->speakerData['linkedin'] = $this->linkedin;
        $this->speakerData['twitter'] = $this->twitter;
        $this->speakerData['instagram'] = $this->instagram;

        $this->resetEditSpeakerDetailsFields();

        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => 'Speaker details updated succesfully!',
            'text' => "",
        ]);
    }





    // EDIT SPEAKER ASSET
    public function showEditSpeakerAsset($assetType)
    {
        $this->assetType = $assetType;
        $this->editSpeakerAssetForm = true;
    }

    public function resetEditSpeakerAssetFields()
    {
        $this->editSpeakerAssetForm = false;
        $this->assetType = null;
        $this->image_media_id = null;
        $this->image_placeholder_text = null;
    }

    public function editSpeakerAssetConfirmation()
    {

        // $this->validate([
        //     'image_placeholder_text' => 'required'
        // ]);

        $this->validate([
    'image_placeholder_text' => 'required|url|max:2048',
]);

        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure?',
            'text' => "",
            'buttonConfirmText' => "Yes, update it!",
            'livewireEmit' => "editSpeakerAssetConfirmed",
        ]);
    }

    public function editSpeakerAsset()
    {

        $mediaId = $this->image_media_id;

    if (!$mediaId && !empty($this->image_placeholder_text)) {
        $imageUrl = trim($this->image_placeholder_text);

        $path = parse_url($imageUrl, PHP_URL_PATH);
        $fileName = basename($path) ?: 'speaker-image';
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $media = Medias::create([
            'file_url' => $imageUrl,
            'file_directory' => 'external-url',
            'file_name' => $fileName,
            'file_type' => $extension ?: 'image',
            'file_size' => 0,
            'width' => null,
            'height' => null,
            'date_uploaded' => now(),
        ]);

        $mediaId = $media->id;
    }


        if ($this->assetType == "Speaker PFP") {
            Speakers::where('id', $this->speakerData['id'])->update([
                'pfp_media_id' =>$mediaId,
            ]);

            if ($this->speakerData['pfp']['media_id'] != null) {
                mediaUsageUpdate(
                    MediaUsageUpdateTypes::REMOVED_THEN_ADD->value,
                  $mediaId,
                    MediaEntityTypes::SPEAKER_PFP->value,
                    $this->speakerData['id'],
                    $this->speakerData['pfp']['media_usage_id']
                );
            } else {
                mediaUsageUpdate(
                    MediaUsageUpdateTypes::ADD_ONLY->value,
                    $mediaId,
                    MediaEntityTypes::SPEAKER_PFP->value,
                    $this->speakerData['id'],
                    $this->speakerData['pfp']['media_usage_id']
                );
            }
            
            $this->speakerData['pfp'] = [
                'media_id' => $mediaId,
                'media_usage_id' => getMediaUsageId($mediaId, MediaEntityTypes::SPEAKER_PFP->value, $this->speakerData['id']),
                'url' => Medias::where('id', $mediaId)->value('file_url'),
            ];
        } else {
            Speakers::where('id', $this->speakerData['id'])->update([
                'cover_photo_media_id' => $mediaId,
            ]);

            if ($this->speakerData['cover_photo']['media_id'] != null) {
                mediaUsageUpdate(
                    MediaUsageUpdateTypes::REMOVED_THEN_ADD->value,
                    $mediaId,
                    MediaEntityTypes::SPEAKER_COVER_PHOTO->value,
                    $this->speakerData['id'],
                    $this->speakerData['cover_photo']['media_usage_id']
                );
            } else {
                mediaUsageUpdate(
                    MediaUsageUpdateTypes::ADD_ONLY->value,
                    $mediaId,
                    MediaEntityTypes::SPEAKER_COVER_PHOTO->value,
                    $this->speakerData['id'],
                    $this->speakerData['cover_photo']['media_usage_id']
                );
            }
            
            $this->speakerData['cover_photo'] = [
                'media_id' => $mediaId,
                'media_usage_id' => getMediaUsageId($mediaId, MediaEntityTypes::SPEAKER_COVER_PHOTO->value, $this->speakerData['id']),
                'url' => Medias::where('id', $mediaId)->value('file_url'),
            ];
        }

        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => $this->assetType . ' updated succesfully!',
            'text' => "",
        ]);

        $this->resetEditSpeakerAssetFields();
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
    public function deleteSpeakerAsset($deleteAssetType)
    {
        if ($deleteAssetType == "Speaker PFP") {
            Speakers::where('id', $this->speakerData['id'])->update([
                'pfp_media_id' => null,
            ]);

            mediaUsageUpdate(
                MediaUsageUpdateTypes::REMOVED_ONLY->value,
                $this->speakerData['pfp']['media_id'],
                MediaEntityTypes::SPEAKER_PFP->value,
                $this->speakerData['id'],
                $this->speakerData['pfp']['media_usage_id']
            );

            $this->speakerData['pfp'] = [
                'media_id' => null,
                'media_usage_id' => null,
                'url' => null,
            ];
        } else {
            Speakers::where('id', $this->speakerData['id'])->update([
                'cover_photo_media_id' => null,
            ]);

            mediaUsageUpdate(
                MediaUsageUpdateTypes::REMOVED_ONLY->value,
                $this->speakerData['cover_photo']['media_id'],
                MediaEntityTypes::SPEAKER_COVER_PHOTO->value,
                $this->speakerData['id'],
                $this->speakerData['cover_photo']['media_usage_id']
            );

            $this->speakerData['cover_photo'] = [
                'media_id' => null,
                'media_usage_id' => null,
                'url' => null,
            ];
        }
    }
}
