<?php

namespace App\Http\Livewire\Concerns;

use App\Models\Event;
use App\Models\Feature;
use App\Models\SpeakerType;
use Illuminate\Validation\ValidationException;

trait HasWordPressSpeakerMapping
{
    public $wpSpeakerDestination = '';
    public $wpSpeakerRoleMappings = [];
    public $wpSpeakerMappingMessage = '';

    public function getWpSpeakerCategoryChoicesProperty()
    {
        $eventId = (int) $this->eventData['eventId'];
        $event = Event::findOrFail($eventId);

        $choices = [
            [
                'id' => 0,
                'label' => $event->short_name . ' — Main event',
            ],
        ];

        foreach (
            Feature::where('event_id', $eventId)
                ->orderBy('id')
                ->get() as $feature
        ) {
            $choices[] = [
                'id' => $feature->id,
                'label' => $feature->short_name,
            ];
        }

        return $choices;
    }

    public function getWpSpeakerTypeChoicesProperty()
    {
        return SpeakerType::where(
            'event_id',
            (int) $this->eventData['eventId']
        )
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function getWpSpeakerRoleChoicesProperty()
    {
        $roles = [];

        foreach ($this->wpSpeakerRows as $row) {
            if ($row['eligibility'] !== 'Eligible') {
                continue;
            }

            $role = trim((string) ($row['role'] ?? ''));

            $roles[sha1($role)] = [
                'key' => sha1($role),
                'label' => $role === '' ? '(No WordPress role)' : $role,
            ];
        }

        return array_values($roles);
    }

    protected function resetWordPressSpeakerMapping()
    {
        $this->wpSpeakerDestination = '';
        $this->wpSpeakerRoleMappings = [];
        $this->wpSpeakerMappingMessage = '';

        $this->resetWordPressSpeakerSettingsMessages();
        $this->resetWordPressSpeakerLinking();
        $this->resetWordPressSpeakerUpdates();
        $this->resetWordPressSpeakerCreation();
    }

    public function updatedWpSpeakerDestination()
    {
        $this->wpSpeakerMappingMessage = '';

        $this->resetWordPressSpeakerSettingsMessages();
        $this->resetWordPressSpeakerLinking();
        $this->resetWordPressSpeakerUpdates();
        $this->resetWordPressSpeakerCreation();
    }

    public function updatedWpSpeakerRoleMappings()
    {
        $this->wpSpeakerMappingMessage = '';

        $this->resetWordPressSpeakerSettingsMessages();
        $this->resetWordPressSpeakerLinking();
        $this->resetWordPressSpeakerUpdates();
        $this->resetWordPressSpeakerCreation();
    }

    public function checkWordPressSpeakerMapping()
    {
        $this->assertDataSyncAdmin();
        $this->wpSpeakerMappingMessage = '';
        $this->resetValidation();

        if (!$this->wpSpeakerLoaded) {
            throw ValidationException::withMessages([
                'wpSpeakerDestination' => 'Load a speaker preview first.',
            ]);
        }

        $roles = $this->getWpSpeakerRoleChoicesProperty();

        if (count($roles) === 0) {
            throw ValidationException::withMessages([
                'wpSpeakerDestination' =>
                'There are no eligible speakers to map.',
            ]);
        }

        $this->validate([
            'wpSpeakerDestination' => ['required', 'integer', 'min:0'],
            'wpSpeakerRoleMappings' => ['required', 'array'],
        ]);

        $eventId = (int) $this->eventData['eventId'];
        $destination = (int) $this->wpSpeakerDestination;

        if (
            $destination !== 0
            && !Feature::where('event_id', $eventId)
                ->where('id', $destination)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'wpSpeakerDestination' =>
                'Choose a category belonging to this event.',
            ]);
        }

        $validTypeIds = SpeakerType::where('event_id', $eventId)
            ->pluck('id')
            ->map(function ($id) {
                return (string) $id;
            })
            ->all();

        $errors = [];

        foreach ($roles as $role) {
            $selected = $this->wpSpeakerRoleMappings[$role['key']] ?? '';

            if (
                !is_scalar($selected)
                || !in_array((string) $selected, $validTypeIds, true)
            ) {
                $errors['wpSpeakerRoleMappings.' . $role['key']] =
                    'Choose an app speaker type for ' . $role['label'] . '.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $this->wpSpeakerMappingMessage =
            'Mapping selections are valid for this event. '
            . 'No speakers or links were saved.';
    }
}
