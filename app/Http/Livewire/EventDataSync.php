<?php

namespace App\Http\Livewire;

use App\Http\Livewire\Concerns\HasWordPressAgendaSettings;
use App\Http\Livewire\Concerns\HasWordPressAgendaPreview;
use App\Http\Livewire\Concerns\HasWordPressAgendaLinking;
use App\Http\Livewire\Concerns\HasWordPressLinkedUpdates;
use App\Http\Livewire\Concerns\HasWordPressSessionCreation;
use App\Http\Livewire\Concerns\HasWordPressSpeakerPreview;
use App\Http\Livewire\Concerns\HasWordPressSpeakerMapping;
use App\Http\Livewire\Concerns\HasWordPressSpeakerLinking;
use App\Http\Livewire\Concerns\HasWordPressSpeakerUpdates;
use App\Http\Livewire\Concerns\HasWordPressSpeakerSettings;
use App\Http\Livewire\Concerns\HasWordPressSpeakerProfileFields;
use App\Http\Livewire\Concerns\HasWordPressSpeakerCreation;
use App\Http\Livewire\Concerns\HasWordPressMeetingRoomPartnerPreview;
use App\Http\Livewire\Concerns\HasWordPressMeetingRoomPartnerLinking;
use App\Http\Livewire\Concerns\HasWordPressMeetingRoomPartnerCreation;
use App\Http\Livewire\Concerns\HasWordPressMeetingRoomPartnerUpdates;
use App\Http\Livewire\Concerns\HasWordPressExhibitorPreview;
use App\Http\Livewire\Concerns\HasWordPressExhibitorLinking;
use App\Http\Livewire\Concerns\HasWordPressExhibitorUpdates;
use App\Http\Livewire\Concerns\HasWordPressExhibitorCreation;


use App\Models\Event;
use Livewire\Component;

class EventDataSync extends Component
{
    use HasWordPressAgendaSettings;
    use HasWordPressAgendaPreview;
    use HasWordPressAgendaLinking;
    use HasWordPressLinkedUpdates;
    use HasWordPressSessionCreation;
    use HasWordPressSpeakerPreview;
    use HasWordPressSpeakerMapping;
    use HasWordPressSpeakerLinking;
    use HasWordPressSpeakerUpdates;
    use HasWordPressSpeakerSettings;
    use HasWordPressSpeakerProfileFields;
    use HasWordPressSpeakerCreation;
    use HasWordPressMeetingRoomPartnerPreview;
    use HasWordPressMeetingRoomPartnerLinking;
    use HasWordPressMeetingRoomPartnerCreation;
    use HasWordPressMeetingRoomPartnerUpdates;
    use HasWordPressExhibitorPreview;
    use HasWordPressExhibitorLinking;
    use HasWordPressExhibitorUpdates;
    use HasWordPressExhibitorCreation;

    public $eventData = [];

    protected function assertDataSyncAdmin()
    {
        abort_unless(
            session()->get('userType') === 'gpcaAdmin',
            403,
            'Only administrators can manage Data Sync.'
        );
    }

    public function hydrate()
    {
        $this->assertDataSyncAdmin();
    }

    public function mount($eventCategory, $eventId)
    {
        $this->assertDataSyncAdmin();

        $event = Event::where('id', $eventId)
            ->where('category', $eventCategory)
            ->firstOrFail();

        $this->eventData = [
            'eventId' => $event->id,
            'eventCategory' => $event->category,
            'eventDetails' => [
                'short_name' => $event->short_name,
            ],
        ];

        $this->loadWordPressAgendaSettings();
    }

    public function render()
    {
        return view('livewire.event.data-sync.event-data-sync');
    }
}
