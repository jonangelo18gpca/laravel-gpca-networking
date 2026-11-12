
<div>
    <h1 class="text-headingTextColor text-3xl font-bold">
        Meeting room partners
    </h1>

    <!-- Floating Add Meeting Room Partner Button -->
    <div class="fixed bottom-0 left-6 mb-5 z-50">
        <button
            type="button"
            wire:click.prevent="showAddMeetingRoomPartner"
            class="w-14 h-14 rounded-full bg-primaryColor hover:bg-primaryColorHover text-white shadow-lg flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primaryColor">
            <i class="fa-solid fa-plus text-xl"></i>
        </button>
    </div>

    <div class="flex justify-between mt-5">
        <button
            type="button"
            wire:click.prevent="showAddMeetingRoomPartner"
            wire:key="showAddMeetingRoomPartner"
            class="bg-primaryColor hover:bg-primaryColorHover text-white rounded-lg text-sm w-52 h-10">
            Add meeting room partner
        </button>
    </div>

    @if (count($finalListOfMeetingRoomPartners) == 0)
        <div class="bg-red-400 text-white text-center py-3 mt-5 rounded-md">
            There are no meeting room partners yet.
        </div>
    @else
        <p class="mt-5">
            Total meeting room partners:
            {{ count($finalListOfMeetingRoomPartners) }}
        </p>

        @if (session('userType') === 'gpcaAdmin')
            <p class="mt-2 text-sm text-gray-500">
                Drag the grip icon beside a partner’s number to change its order.
            </p>
        @endif

        <div class="shadow-lg my-5 bg-white rounded-md">
            {{-- Header stays outside the sortable container --}}
            <div
                class="grid grid-cols-11 pt-2 pb-2 mt-3 text-center items-center gap-10 text-sm text-white bg-primaryColor rounded-tl-md rounded-tr-md">
                <div class="col-span-1">No.</div>
                <div class="col-span-2">Logo</div>
                <div class="col-span-2">Company Name</div>
                <div class="col-span-1">Website</div>
                <div class="col-span-1">Location</div>
                <div class="col-span-2">Date time added</div>
                <div class="col-span-1">Status</div>
                <div class="col-span-1">Action</div>
            </div>

            <div
                @if (session('userType') === 'gpcaAdmin')
                    wire:sortable="reorderMeetingRoomPartners"
                @endif
            >
                @foreach ($finalListOfMeetingRoomPartners as $index => $finalListOfMeetingRoomPartner)
                    <div
                        wire:key="meeting-room-partner-row-{{ $finalListOfMeetingRoomPartner['id'] }}"
                        wire:sortable.item="{{ $finalListOfMeetingRoomPartner['id'] }}"
                        class="grid grid-cols-11 gap-10 pt-2 pb-2 mb-1 text-center items-center text-sm {{ $index % 2 == 0 ? 'bg-registrationInputFieldsBGColor' : 'bg-registrationCardBGColor' }}">

                        <div class="col-span-1 flex items-center justify-center gap-2">
                            @if (session('userType') === 'gpcaAdmin')
                                <span
                                    wire:sortable.handle
                                    title="Drag to reorder meeting room partner"
                                    style="cursor: grab; display: inline-block; padding: 8px; user-select: none;">
                                    <i
                                        class="fa-solid fa-grip-vertical"
                                        style="pointer-events: none;">
                                    </i>
                                </span>
                            @endif

                            <span>{{ $index + 1 }}</span>
                        </div>

                        <div class="col-span-2">
                            @if ($finalListOfMeetingRoomPartner['logo'] == null)
                                N/A
                            @else
                                <img
                                    src="{{ $finalListOfMeetingRoomPartner['logo'] }}"
                                    alt=""
                                    class="mx-auto w-14">
                            @endif
                        </div>

                        <div class="col-span-2">
                            {{ $finalListOfMeetingRoomPartner['name'] }}
                        </div>

                        <div class="col-span-1">
                            {{ $finalListOfMeetingRoomPartner['website'] ?? 'N/A' }}
                        </div>

                        <div class="col-span-1">
                            {{ $finalListOfMeetingRoomPartner['location'] ?? 'N/A' }}
                        </div>

                        <div
                            wire:click="showEditMeetingRoomPartnerDateTime({{ $finalListOfMeetingRoomPartner['id'] }}, {{ $index }})"
                            class="text-blue-700 hover:underline col-span-2 cursor-pointer">
                            {{ $finalListOfMeetingRoomPartner['datetime_added'] }}
                        </div>

                        <div class="col-span-1">
                            @if ($finalListOfMeetingRoomPartner['is_active'])
                                <button
                                    type="button"
                                    wire:click="updateMeetingRoomPartnerStatus({{ $index }})"
                                    class="text-gray-700 bg-green-300 hover:bg-green-500 hover:text-white py-1 px-2 text-sm rounded-md">
                                    Active
                                </button>
                            @else
                                <button
                                    type="button"
                                    wire:click="updateMeetingRoomPartnerStatus({{ $index }})"
                                    class="text-gray-700 bg-red-300 hover:bg-red-500 hover:text-white py-1 px-2 text-sm rounded-md">
                                    Inactive
                                </button>
                            @endif
                        </div>

                        <div class="col-span-1 flex gap-3 items-center justify-center">
                            <a
                                href="{{ route('admin.event.meeting-room-partner.view', [
                                    'eventCategory' => $event->category,
                                    'eventId' => $event->id,
                                    'meetingRoomPartnerId' => $finalListOfMeetingRoomPartner['id'],
                                ]) }}"
                                class="cursor-pointer hover:text-gray-600 text-gray-500">
                                <i class="fa-solid fa-eye"></i>
                            </a>

                            <button
                                type="button"
                                wire:click="deleteMeetingRoomPartnerConfirmation({{ $index }})"
                                class="cursor-pointer hover:text-red-600 text-red-500 text-sm">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    

    @if (session('userType') === 'gpcaAdmin')
    <div class="border rounded-xl p-4 mb-5">
        <div class="flex flex-wrap items-center gap-3">
            <button
                type="button"
                wire:click="previewPartnersAlphabetically"
                wire:loading.attr="disabled"
                class="border rounded px-4 py-2"
            >
                Sort by company A–Z
            </button>

            <p class="text-sm text-gray-600">
                Preview alphabetical order before saving.
                Manual drag-and-drop ordering remains available afterward.
            </p>
        </div>

        @error('partnerAlphabeticalSort')
            <p class="text-red-600 mt-3" role="alert">
                {{ $message }}
            </p>
        @enderror

        @if ($showPartnerAlphabeticalPreview)
            <div class="border-t mt-4 pt-4">
                <h3 class="font-semibold">
                    Proposed company order
                </h3>

                <p class="text-sm text-gray-600 mt-2">
                    Includes active and inactive meeting room partners
                    for this event. Company names are compared without
                    case differences. Empty names appear last.
                </p>

                <div class="max-h-80 overflow-y-auto mt-3">
                    <ol class="list-decimal pl-6 space-y-2">
                        @foreach ($partnerAlphabeticalPreview as $partner)
                            <li wire:key="partner-az-preview-{{ $partner['id'] }}">
                                {{ trim($partner['name']) !== '' ? $partner['name'] : '(No company name)' }}

                                @if (!$partner['is_active'])
                                    <span class="text-sm text-gray-500">
                                        — inactive
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>

                <p class="text-sm text-gray-600 mt-4">
                    Saving replaces the current manual order by updating
                    the existing ordering dates. No partner profiles,
                    logos or WordPress mappings will be deleted.
                </p>

                <label class="block text-sm mt-3">
                    <input
                        type="checkbox"
                        wire:model="partnerAlphabeticalConfirmed"
                        wire:loading.attr="disabled"
                    >

                    I reviewed this order. Save company A–Z as the
                    meeting room partner order.
                </label>

                <div class="flex flex-wrap gap-3 mt-3">
                    <button
                        type="button"
                        wire:click="applyPartnersAlphabetically"
                        wire:loading.attr="disabled"
                        @if (!$partnerAlphabeticalConfirmed) disabled @endif
                        class="bg-primaryColor text-white rounded px-4 py-2 disabled:opacity-50"
                    >
                        Save alphabetical order
                    </button>

                    <button
                        type="button"
                        wire:click="cancelPartnerAlphabeticalSort"
                        wire:loading.attr="disabled"
                        class="border rounded px-4 py-2"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        @endif

        <p
            wire:loading
            wire:target="previewPartnersAlphabetically,applyPartnersAlphabetically"
            class="text-sm text-gray-600 mt-3"
        >
            Processing meeting room partner order…
        </p>
    </div>
@endif

    @if ($chooseImageModal)
        @include('livewire.common.choose_image_modal')
    @endif

    @if ($editMeetingRoomPartnerDateTimeForm)
        @include('livewire.common.edit_datetime_form')
    @endif

    @if ($addMeetingRoomPartnerForm)
        @include('livewire.event.meeting-room-partners.add_mrp')
    @endif
</div>