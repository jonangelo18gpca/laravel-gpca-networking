<?php

namespace App\Http\Livewire;

use App\Http\Livewire\Concerns\HasWordPressAgendaSettings;
use App\Http\Livewire\Concerns\HasWordPressAgendaPreview;
use App\Http\Livewire\Concerns\HasWordPressAgendaLinking;
use App\Http\Livewire\Concerns\HasWordPressLinkedUpdates;
use App\Http\Livewire\Concerns\HasWordPressSessionCreation;
use App\Models\Event;
use Livewire\Component;

class EventDataSync extends Component
{
    use HasWordPressAgendaSettings;
    use HasWordPressAgendaPreview;
    use HasWordPressAgendaLinking;
    use HasWordPressLinkedUpdates;
    use HasWordPressSessionCreation;

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