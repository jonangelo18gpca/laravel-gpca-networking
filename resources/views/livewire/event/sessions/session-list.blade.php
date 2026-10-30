<div>
    <h1 class="text-headingTextColor text-3xl font-bold">Session</h1>

    <!-- Floating Add Session Button -->
    <div class="fixed bottom-0 left-6 mb-5 z-50">
        <button type="button" wire:click.prevent="showAddSession"
            class="w-14 h-14 rounded-full bg-primaryColor hover:bg-primaryColorHover text-white shadow-lg flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primaryColor">
            <i class="fa-solid fa-plus text-xl"></i>
        </button>
    </div>

    <div class="flex gap-5 mt-5">
        <button type="button" wire:click.prevent="showAddSession" wire:key="showAddSession"
            class="bg-primaryColor hover:bg-primaryColorHover text-white rounded-lg text-sm w-32 h-10">Add
            session</button>
        <button type="button" wire:click.prevent="showAddSessionDate" wire:key="showAddSessionDate"
            class="bg-primaryColor hover:bg-primaryColorHover text-white rounded-lg text-sm w-32 h-10">Add
            session date</button>
        <button type="button" wire:click.prevent="showAddSessionDay" wire:key="showAddSessionDay"
            class="bg-primaryColor hover:bg-primaryColorHover text-white rounded-lg text-sm w-32 h-10">Add
            session day</button>
        <button type="button" wire:click.prevent="showAddSessionType" wire:key="showAddSessionType"
            class="bg-primaryColor hover:bg-primaryColorHover text-white rounded-lg text-sm w-32 h-10">Add
            session type</button>
    </div>

    @if (count($finalListOfSessions) == 0)
        <div class="bg-red-400 text-white text-center py-3 mt-5 rounded-md">
            There are no sessions yet.
        </div>
    @else
        <div class="shadow-lg my-5 bg-white rounded-md">
            <div
                class="grid grid-cols-12 pt-2 pb-2 mt-3 text-center items-center gap-10 text-sm text-white bg-primaryColor rounded-tl-md rounded-tr-md">
                <div class="col-span-1">No.</div>
                <div class="col-span-2">Category</div>
                <div class="col-span-2">Date</div>
                <div class="col-span-1">Day</div>
                <div class="col-span-2">Title</div>
                <div class="col-span-2">Timings</div>
                <div class="col-span-1">Status</div>
                <div class="col-span-1">Action</div>
            </div>
            @foreach ($finalListOfSessions as $index => $finalListOfSession)
                <div
                    class="grid grid-cols-12 gap-10 pt-2 pb-2 mb-1 text-center items-center text-sm {{ $index % 2 == 0 ? 'bg-registrationInputFieldsBGColor' : 'bg-registrationCardBGColor' }}">
                    {{-- <div class="col-span-1">{{ $index + 1 }}</div> --}}
                    <div class="col-span-1">
                        <div>{{ $index + 1 }}</div>

                        <div class="text-xs text-gray-500">
                            App ID: {{ $finalListOfSession['id'] }}
                        </div>
                    </div>
                    <div class="col-span-2">{{ $finalListOfSession['categoryName'] }}</div>
                    <div class="col-span-2">{{ $finalListOfSession['session_date'] }}</div>
                    <div class="col-span-1">{{ $finalListOfSession['session_day'] }}</div>
                    <div class="col-span-2">{{ $finalListOfSession['title'] }}</div>
                    <div class="col-span-2">{{ $finalListOfSession['timings'] }}</div>
                    <div class="col-span-1">
                        @if ($finalListOfSession['is_active'])
                            <button wire:click="updateSessionStatus({{ $index }})"
                                class="text-gray-700 bg-green-300 hover:bg-green-500 hover:text-white py-1 px-2 text-sm rounded-md">Active</button>
                        @else
                            <button wire:click="updateSessionStatus({{ $index }})"
                                class="text-gray-700 bg-red-300 hover:bg-red-500 hover:text-white py-1 px-2 text-sm rounded-md">Inactive</button>
                        @endif
                    </div>
                    <div class="col-span-1 flex gap-3 items-center justify-center">
                        <a href="{{ route('admin.event.session.view', ['eventCategory' => $event->category, 'eventId' => $event->id, 'sessionId' => $finalListOfSession['id']]) }}"
                            class="cursor-pointer hover:text-gray-600 text-gray-500">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                        <button wire:click="deleteSessionConfirmation({{ $index }})"
                            class="cursor-pointer hover:text-red-600 text-red-500 text-sm ">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($addSessionForm)
        @include('livewire.event.sessions.add_session')
    @endif

    @if ($addSessionDateForm)
        @include('livewire.event.sessions.add_session_date')
    @endif

    @if ($addSessionDayForm)
        @include('livewire.event.sessions.add_session_day')
    @endif

    @if ($addSessionTypeForm)
        @include('livewire.event.sessions.add_session_type')
    @endif
</div>
