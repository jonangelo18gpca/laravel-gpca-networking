<?php

namespace App\Http\Livewire;

use App\Enums\MediaEntityTypes;
use App\Enums\MediaUsageUpdateTypes;
use App\Models\Event as Events;
use App\Models\Sponsor as Sponsors;
use App\Models\SponsorType as SponsorTypes;
use App\Models\Feature as Features;
use App\Models\Media as Medias;
use App\Models\Session as Sessions;
use Carbon\Carbon;
use Livewire\Component;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SponsorList extends Component
{
    public $event;
    public $finalListOfSponsors = array();

    // EDIT DETAILS
    public $feature_id, $sponsor_type_id, $name, $website, $categoryChoices = array(), $typeChoices = array();
    public $chooseImageModal, $mediaFileList = array(), $activeSelectedImage, $image_media_id, $image_placeholder_text;
    public $addSponsorForm;

    // EDIT DATE TIME
    public $sponsorId, $sponsorDateTime, $sponsorArrayIndex;
    public $inputNameVariableDateTime, $btnUpdateNameMethodDateTime, $btnCancelNameMethodDateTime;
    public $editSponsorDateTimeForm;

    // DELETE
    public $activeDeleteIndex;

    protected $listeners = ['addSponsorConfirmed' => 'addSponsor', 'deleteSponsorConfirmed' => 'deleteSponsor'];

    // public function mount($eventId, $eventCategory)
    // {
    //     $this->event = Events::where('id', $eventId)->where('category', $eventCategory)->first();

    //     $sponsors = Sponsors::where('event_id', $eventId)->orderBy('datetime_added', 'ASC')->get();

    //     if ($sponsors->isNotEmpty()) {
    //         foreach ($sponsors as $sponsor) {
    //             if ($sponsor->feature_id == 0) {
    //                 $category = $this->event->short_name;
    //             } else {
    //                 $feature = Features::where('event_id', $this->event->id)->where('id', $sponsor->feature_id)->first();
    //                 if ($feature) {
    //                     $category = $feature->short_name;
    //                 } else {
    //                     $category = "Others";
    //                 }
    //             }

    //             $sponsorType = SponsorTypes::where('event_id', $this->event->id)->where('id', $sponsor->sponsor_type_id)->first();
    //             if ($sponsorType) {
    //                 $type = $sponsorType->name;
    //             } else {
    //                 $type = "N/A";
    //             }

    //             array_push($this->finalListOfSponsors, [
    //                 'id' => $sponsor->id,
    //                 // 'logo' => Medias::where('id', $sponsor->logo_media_id)->value('file_url'),
    //                 'logo' => $sponsor->logo_media_id
    //                     ? Medias::where('id', $sponsor->logo_media_id)->value('file_url')
    //                     : null,
    //                 'name' => $sponsor->name,
    //                 'category' => $category,
    //                 'type' => $type,
    //                 'website' => $sponsor->website,
    //                 'is_active' => $sponsor->is_active,
    //                 'datetime_added' => Carbon::parse($sponsor->datetime_added)->format('M j, Y g:i A'),
    //             ]);
    //         }
    //     }

    //     $this->inputNameVariableDateTime = "sponsorDateTime";
    //     $this->btnUpdateNameMethodDateTime = "editSponsorDateTime";
    //     $this->btnCancelNameMethodDateTime = "resetEditSponsorDateTimeFields";

    //     $this->addSponsorForm = false;
    //     $this->editSponsorDateTimeForm = false;

    //     $this->mediaFileList = getMediaFileList();
    //     $this->chooseImageModal = false;

        
    // }

public function mount($eventId, $eventCategory)
{
    $this->event = Events::where('id', $eventId)
        ->where('category', $eventCategory)
        ->first();

    $this->inputNameVariableDateTime = "sponsorDateTime";
    $this->btnUpdateNameMethodDateTime = "editSponsorDateTime";
    $this->btnCancelNameMethodDateTime = "resetEditSponsorDateTimeFields";

    $this->addSponsorForm = false;
    $this->editSponsorDateTimeForm = false;

    $this->mediaFileList = getMediaFileList();
    $this->chooseImageModal = false;

    $this->loadSponsors();
}

    private function loadSponsors()
{
    $this->finalListOfSponsors = [];

    $sponsors = Sponsors::where('event_id', $this->event->id)
        ->orderBy('datetime_added', 'ASC')
        ->orderBy('id', 'ASC')
        ->get();

    foreach ($sponsors as $sponsor) {
        if ($sponsor->feature_id == 0) {
            $category = $this->event->short_name;
        } else {
            $feature = Features::where('event_id', $this->event->id)
                ->where('id', $sponsor->feature_id)
                ->first();

            $category = $feature ? $feature->short_name : 'Others';
        }

        $sponsorType = SponsorTypes::where('event_id', $this->event->id)
            ->where('id', $sponsor->sponsor_type_id)
            ->first();

        $this->finalListOfSponsors[] = [
            'id' => $sponsor->id,
            'logo' => $sponsor->logo_media_id
                ? Medias::where('id', $sponsor->logo_media_id)
                    ->value('file_url')
                : null,
            'name' => $sponsor->name,
            'category' => $category,
            'type' => $sponsorType ? $sponsorType->name : 'N/A',
            'website' => $sponsor->website,
            'is_active' => $sponsor->is_active,
            'datetime_added' => Carbon::parse($sponsor->datetime_added)
                ->format('M j, Y g:i A'),
        ];
    }
}

public function reorderSponsors($items)
{
    abort_unless(
        session('userType') === 'gpcaAdmin',
        403,
        'Only GPCA administrators can reorder sponsors.'
    );

    Validator::make(['items' => $items], [
        'items' => 'required|array|min:1',
        'items.*' => 'required|array',
        'items.*.value' => 'required|integer|min:1|distinct',
        'items.*.order' => 'required|integer|min:1|distinct',
    ])->validate();

    $orderedItems = collect($items)
        ->sortBy('order')
        ->values();

    $positions = $orderedItems
        ->pluck('order')
        ->map(fn ($order) => (int) $order)
        ->all();

    abort_unless(
        $positions === range(1, $orderedItems->count()),
        422,
        'Invalid sponsor positions.'
    );

    $orderedIds = $orderedItems
        ->pluck('value')
        ->map(fn ($id) => (int) $id)
        ->all();

    DB::transaction(function () use ($orderedIds) {
        $sponsors = Sponsors::where('event_id', $this->event->id)
            ->orderBy('datetime_added', 'ASC')
            ->orderBy('id', 'ASC')
            ->lockForUpdate()
            ->get();

        $currentIds = $sponsors
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $submittedIds = collect($orderedIds)
            ->sort()
            ->values()
            ->all();

        abort_unless(
            $currentIds === $submittedIds,
            422,
            'The sponsor list has changed. Refresh the page and try again.'
        );

        $dateSlots = [];
        $previousDate = null;

        foreach ($sponsors as $sponsor) {
            abort_if(
                empty($sponsor->datetime_added),
                422,
                'Each sponsor must have a date before reordering.'
            );

            $date = Carbon::parse($sponsor->datetime_added)
                ->setMicrosecond(0);

            if (
                $previousDate &&
                $date->lessThanOrEqualTo($previousDate)
            ) {
                $date = $previousDate->copy()->addSecond();
            }

            $dateSlots[] = $date->format('Y-m-d H:i:s');
            $previousDate = $date;
        }

        foreach ($orderedIds as $index => $sponsorId) {
            Sponsors::where('event_id', $this->event->id)
                ->where('id', $sponsorId)
                ->update([
                    'datetime_added' => $dateSlots[$index],
                ]);
        }
    });

    $this->resetEditSponsorDateTimeFields();
    $this->activeDeleteIndex = null;
    $this->loadSponsors();

    $this->dispatchBrowserEvent('swal:success', [
        'type' => 'success',
        'message' => 'Sponsor order updated successfully!',
        'text' => '',
    ]);
}



    public function render()
    {
        return view('livewire.event.sponsors.sponsor-list');
    }

    public function showAddSponsorType()
    {
        return redirect()->route('admin.event.sponsor.types.view', ['eventCategory' => $this->event->category, 'eventId' => $this->event->id]);
    }


    public function showAddSponsor()
    {


        $sponsorTypes = SponsorTypes::where('event_id', $this->event->id)->get();

        if ($sponsorTypes->isNotEmpty()) {
            foreach ($sponsorTypes as $sponsorType) {
                array_push($this->typeChoices, [
                    'value' => $sponsorType->name,
                    'id' => $sponsorType->id,
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

        $this->addSponsorForm = true;
    }

    public function addSponsorConfirmation()
    {
        $this->validate([
            'name' => 'required',
            'feature_id' => 'required',
            'sponsor_type_id' => 'required',
            'image_placeholder_text' => 'nullable|url|max:2048',
        ]);

        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure?',
            'text' => "",
            'buttonConfirmText' => "Yes, add it!",
            'livewireEmit' => "addSponsorConfirmed",
        ]);
    }

    public function resetAddSponsorFields()
    {
        $this->addSponsorForm = false;
        $this->feature_id = null;
        $this->sponsor_type_id = null;
        $this->name = null;
        $this->website = null;
        $this->image_media_id = null;
        $this->image_placeholder_text = null;
        $this->categoryChoices = array();
        $this->typeChoices = array();
    }

    public function addSponsor()
    {

        $logoMediaId = $this->image_media_id;

        if (!$logoMediaId && !empty($this->image_placeholder_text)) {
            $imageUrl = trim($this->image_placeholder_text);

            $path = parse_url($imageUrl, PHP_URL_PATH);
            $fileName = basename($path) ?: 'sponsor-logo';
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


        $lastSponsorDate = Sponsors::where('event_id', $this->event->id)
    ->max('datetime_added');

$newSponsorDate = Carbon::now()->setMicrosecond(0);

if (
    $lastSponsorDate &&
    $newSponsorDate->lessThanOrEqualTo(Carbon::parse($lastSponsorDate))
) {
    $newSponsorDate = Carbon::parse($lastSponsorDate)
        ->setMicrosecond(0)
        ->addSecond();
}

        $newSponsor = Sponsors::create([
            'event_id' => $this->event->id,
            'feature_id' => $this->feature_id,
            'sponsor_type_id' => $this->sponsor_type_id,
            'name' => $this->name,
            'website' => $this->website,
            // 'logo_media_id' => $this->image_media_id ?? null,
            'logo_media_id' => $logoMediaId,
            // 'datetime_added' => Carbon::now(),
            'datetime_added' => $newSponsorDate,

        ]);


        foreach ($this->categoryChoices as $categoryChoice) {
            if ($categoryChoice['id'] == $this->feature_id) {
                $selectedCategory = $categoryChoice['value'];
            }
        }

        foreach ($this->typeChoices as $typeChoice) {
            if ($typeChoice['id'] == $this->sponsor_type_id) {
                $selectedType = $typeChoice['value'];
            }
        }

        // if ($this->image_media_id) {
        //     mediaUsageUpdate(
        //         MediaUsageUpdateTypes::ADD_ONLY->value,
        //         $this->image_media_id,
        //         MediaEntityTypes::SPONSOR_LOGO->value,
        //         $newSponsor->id,
        //     );
        // }


        if ($logoMediaId) {
            mediaUsageUpdate(
                MediaUsageUpdateTypes::ADD_ONLY->value,
                $logoMediaId,
                MediaEntityTypes::SPONSOR_LOGO->value,
                $newSponsor->id,
            );
        }
        // array_push($this->finalListOfSponsors, [
        //     'id' => $newSponsor->id,
        //     // 'logo' => $this->image_media_id ? Medias::where('id', $this->image_media_id)->value('file_url') : null,
        //     'logo' => $logoMediaId
        //         ? Medias::where('id', $logoMediaId)->value('file_url')
        //         : null,
        //     'name' => $this->name,
        //     'category' => $selectedCategory,
        //     'type' => $selectedType,
        //     'website' => $this->website,
        //     'is_active' => true,
        //     'datetime_added' => Carbon::parse(Carbon::now())->format('M j, Y g:i A'),
        // ]);

        $this->loadSponsors();
        

        $this->resetAddSponsorFields();

        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => 'Sponsor added successfully!',
            'text' => ''
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




    public function updateSponsorStatus($arrayIndex)
    {
        Sponsors::where('id', $this->finalListOfSponsors[$arrayIndex]['id'])->update([
            'is_active' => !$this->finalListOfSponsors[$arrayIndex]['is_active'],
        ]);

        $this->finalListOfSponsors[$arrayIndex]['is_active'] = !$this->finalListOfSponsors[$arrayIndex]['is_active'];
    }



    // EDIT DATETIME
    public function showEditSponsorDateTime($sponsorId, $sponsorArrayIndex)
    {
        // $sponsorDateTime = Sponsors::where('id', $sponsorId)->value('datetime_added');

        $sponsorDateTime = Sponsors::where('event_id', $this->event->id)
    ->where('id', $sponsorId)
    ->value('datetime_added');


        $this->sponsorId = $sponsorId;
        $this->sponsorDateTime = $sponsorDateTime;
        $this->sponsorArrayIndex = $sponsorArrayIndex;
        $this->editSponsorDateTimeForm = true;
    }

    public function resetEditSponsorDateTimeFields()
    {
        $this->editSponsorDateTimeForm = false;
        $this->sponsorId = null;
        $this->sponsorDateTime = null;
        $this->sponsorArrayIndex = null;
    }

    // public function editSponsorDateTime()
    // {
    //     $this->validate([
    //         'sponsorDateTime' => 'required',
    //     ]);

    //     Sponsors::where('id', $this->sponsorId)->update([
    //         'datetime_added' => $this->sponsorDateTime,
    //     ]);

    //     $this->finalListOfSponsors[$this->sponsorArrayIndex]['datetime_added'] = Carbon::parse($this->sponsorDateTime)->format('M j, Y g:i A');

    //     $this->resetEditSponsorDateTimeFields();

    //     $this->dispatchBrowserEvent('swal:success', [
    //         'type' => 'success',
    //         'message' => 'Sponsor updated successfully!',
    //         'text' => ''
    //     ]);
    // }

public function editSponsorDateTime()
{
    $this->validate([
        'sponsorDateTime' => 'required|date',
    ]);

    Sponsors::where('event_id', $this->event->id)
        ->where('id', $this->sponsorId)
        ->update([
            'datetime_added' => $this->sponsorDateTime,
        ]);

    $this->loadSponsors();
    $this->resetEditSponsorDateTimeFields();

    $this->dispatchBrowserEvent('swal:success', [
        'type' => 'success',
        'message' => 'Sponsor updated successfully!',
        'text' => '',
    ]);
}



    public function deleteSponsorConfirmation($index)
    {
        $this->activeDeleteIndex = $index;
        $this->dispatchBrowserEvent('swal:confirmation', [
            'type' => 'warning',
            'message' => 'Are you sure you want to delete?',
            'text' => "",
            'buttonConfirmText' => "Yes, delete it!",
            'livewireEmit' => "deleteSponsorConfirmed",
        ]);
    }

    public function deleteSponsor()
    {
        $sponsor = Sponsors::where('id', $this->finalListOfSponsors[$this->activeDeleteIndex]['id'])->first();

        if ($sponsor) {
            if ($sponsor->logo_media_id) {
                mediaUsageUpdate(
                    MediaUsageUpdateTypes::REMOVED_ONLY->value,
                    $sponsor->logo_media_id,
                    MediaEntityTypes::SPONSOR_LOGO->value,
                    $sponsor->id,
                    getMediaUsageId($sponsor->logo_media_id, MediaEntityTypes::SPONSOR_LOGO->value, $sponsor->id),
                );
            }

            if ($sponsor->banner_media_id) {
                mediaUsageUpdate(
                    MediaUsageUpdateTypes::REMOVED_ONLY->value,
                    $sponsor->banner_media_id,
                    MediaEntityTypes::SPONSOR_BANNER->value,
                    $sponsor->id,
                    getMediaUsageId($sponsor->banner_media_id, MediaEntityTypes::SPONSOR_BANNER->value, $sponsor->id),
                );
            }

            Sessions::where('event_id', $this->event->id)->where('sponsor_id', $sponsor->id)->update([
                'sponsor_id' => null,
            ]);

            $sponsor->delete();

            unset($this->finalListOfSponsors[$this->activeDeleteIndex]);
            $this->finalListOfSponsors = array_values($this->finalListOfSponsors);
        }

        $this->dispatchBrowserEvent('swal:success', [
            'type' => 'success',
            'message' => 'Sponsor deleted successfully!',
            'text' => ''
        ]);
    }
}
