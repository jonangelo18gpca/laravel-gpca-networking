<div>
    <h1 class="text-headingTextColor text-3xl font-bold">Speakers Management</h1>

    <!-- Floating Add Session Button -->
    <div class="fixed bottom-0 left-6 mb-5 z-50">
        <button type="button" wire:click.prevent="showAddSpeaker"
            class="w-14 h-14 rounded-full bg-primaryColor hover:bg-primaryColorHover text-white shadow-lg flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primaryColor">
            <i class="fa-solid fa-plus text-xl"></i>
        </button>
    </div>

    <div class="flex gap-5 mt-5">
        <button type="button" wire:click.prevent="showAddSpeaker" wire:key="showAddSpeaker"
            class="bg-primaryColor hover:bg-primaryColorHover text-white rounded-lg text-sm w-32 h-10">Add
            speaker</button>

        <button type="button" wire:click.prevent="showAddSpeakerType" wire:key="showAddSpeakerType"
            class="bg-primaryColor hover:bg-primaryColorHover text-white rounded-lg text-sm w-36 h-10">Add
            speaker type</button>
    </div>

    @if (count($finalListOfSpeakers) == 0)
        <div class="bg-red-400 text-white text-center py-3 mt-5 rounded-md">
            There are no speakers yet.
        </div>
    @else
        <p class="mt-5">Total speakers: {{ count($finalListOfSpeakers) }}</p>
        <div class="shadow-lg my-5 bg-white rounded-md">
            <div
                class="grid grid-cols-12 pt-2 pb-2 mt-3 text-center items-center gap-10 text-sm text-white bg-primaryColor rounded-tl-md rounded-tr-md">
                <div class="col-span-1">No.</div>
                <div class="col-span-1">Headshot</div>
                <div class="col-span-2">Name</div>
                <div class="col-span-1">Company</div>
                <div class="col-span-1">Job title</div>
                <div class="col-span-1">Category</div>
                <div class="col-span-1">Type</div>
                <div class="col-span-2">Date time added</div>
            <div class="col-span-1">Status</div>
                <div class="col-span-1">Action</div>
            </div>
            <div @if (session('userType') === 'gpcaAdmin') wire:sortable="reorderSpeakers" @endif>
                @foreach ($finalListOfSpeakers as $index => $finalListOfSpeaker)
                    {{-- <div
                        class="grid grid-cols-12 gap-10 pt-2 pb-2 mb-1 text-center items-center text-sm {{ $index % 2 == 0 ? 'bg-registrationInputFieldsBGColor' : 'bg-registrationCardBGColor' }}"> --}}
<div
    wire:key="speaker-row-{{ $finalListOfSpeaker['id'] }}"
    wire:sortable.item="{{ $finalListOfSpeaker['id'] }}"
    class="grid grid-cols-12 gap-10 pt-2 pb-2 mb-1 text-center items-center text-sm {{ $index % 2 == 0 ? 'bg-registrationInputFieldsBGColor' : 'bg-registrationCardBGColor' }}">
                        {{-- <div class="col-span-1">{{ $index + 1 }}</div> --}}
                      <div class="col-span-1 flex items-center justify-center gap-2">
    @if (session('userType') === 'gpcaAdmin')
       <span
    wire:sortable.handle
    title="Drag to reorder speaker"
    style="cursor: grab; display: inline-block; padding: 8px; user-select: none;">
    <i
        class="fa-solid fa-grip-vertical"
        style="pointer-events: none;">
    </i>
</span>
    @endif

    <span>{{ $index + 1 }}</span>
</div>
                      
                        <div class="col-span-1 flex justify-center">
                            @if ($finalListOfSpeaker['pfp'])
                                <img src="{{ $finalListOfSpeaker['pfp'] }}" class="w-16">
                            @else
                                N/A
                            @endif
                        </div>
                        <div class="col-span-2">{{ $finalListOfSpeaker['name'] }}</div>
                        <div class="col-span-1">{{ $finalListOfSpeaker['company_name'] }}</div>
                        <div class="col-span-1">{{ $finalListOfSpeaker['job_title'] }}</div>
                        <div class="col-span-1">{{ $finalListOfSpeaker['category'] }}</div>
                        <div class="col-span-1">{{ $finalListOfSpeaker['type'] }}</div>
                        <div wire:click="showEditSpeakerDateTime({{ $finalListOfSpeaker['id'] }}, {{ $index }})"
                            class="text-blue-700 hover:underline col-span-2 cursor-pointer">
                            {{ $finalListOfSpeaker['datetime_added'] }}</div>
                        <div class="col-span-1">
                            @if ($finalListOfSpeaker['is_active'])
                                <button wire:click="updateSpeakerStatus({{ $index }})"
                                    class="text-gray-700 bg-green-300 hover:bg-green-500 hover:text-white py-1 px-2 text-sm rounded-md">Active</button>
                            @else
                                <button wire:click="updateSpeakerStatus({{ $index }})"
                                    class="text-gray-700 bg-red-300 hover:bg-red-500 hover:text-white py-1 px-2 text-sm rounded-md">Inactive</button>
                            @endif
                        </div>
                        <div class="col-span-1 flex gap-3 items-center justify-center">
                            <a href="{{ route('admin.event.speaker.view', ['eventCategory' => $event->category, 'eventId' => $event->id, 'speakerId' => $finalListOfSpeaker['id']]) }}"
                                class="cursor-pointer hover:text-gray-600 text-gray-500">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <button wire:click="deleteSpeakerConfirmation({{ $index }})"
                                class="cursor-pointer hover:text-red-600 text-red-500 text-sm ">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($chooseImageModal)
        @include('livewire.common.choose_image_modal')
    @endif

    @if ($editSpeakerDateTimeForm)
        @include('livewire.common.edit_datetime_form')
    @endif

    @if ($addSpeakerForm)
        @include('livewire.event.speakers.add_speaker')
    @endif
</div>
