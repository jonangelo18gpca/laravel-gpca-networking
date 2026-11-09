<div>
    {{-- Page heading --}}
    <div class="mb-6">
        <h1 class="text-headingTextColor text-3xl font-bold">
            Data Sync
        </h1>

        <p class="mt-2 text-sm text-gray-500">
            Preview WordPress changes before applying them to the app.
        </p>
    </div>

    <div class="space-y-4">
        {{-- Shared WordPress website settings --}}
        @include('livewire.event.data-sync.wordpress-general-settings')
        <br>
        {{-- Speakers --}}
        <details wire:ignore.self class="border border-gray-200 rounded-xl bg-white">

            <summary class="cursor-pointer px-5 py-4 text-lg font-semibold text-headingTextColor">
                Speakers
                <span class="ml-2 text-xs font-normal text-gray-500">
                    Import & update profiles
                </span>
            </summary>

            <div class="border-t border-gray-100 px-5 pb-5">
                @include('livewire.event.data-sync.speaker-preview')
            </div>
        </details>

        {{-- Sessions --}}
        <details wire:ignore.self class="border border-gray-200 rounded-xl bg-white">

            <summary class="cursor-pointer px-5 py-4 text-lg font-semibold text-headingTextColor">
                Sessions
                <span class="ml-2 text-xs font-normal text-gray-500">
                    Import & update agenda
                </span>
            </summary>

            <div class="border-t border-gray-100 px-5 pb-5">
                {{-- Source selection --}}
                <div class="py-4">
                    <label for="wp-sync-feature" class="block text-sm font-medium text-gray-700 mb-2">
                        Agenda feature
                    </label>

                    <select id="wp-sync-feature" wire:model="wpPreviewPostType"
                        class="border border-gray-300 rounded-lg px-3 py-2 w-full">
                        <option value="">
                            Select a feature
                        </option>

                        @foreach ($this->wpPreviewSourceChoices as $choice)
                            <option value="{{ $choice['post_type'] }}">
                                {{ $choice['label'] }}
                            </option>
                        @endforeach
                    </select>

                    @error('wpPreviewPostType')
                        <p class="text-sm text-red-600 mt-2">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- Everyday actions --}}
                <div class="border-t border-gray-100">
                    <details wire:ignore.self>
                        <summary class="cursor-pointer py-4 text-sm font-semibold text-headingTextColor">
                            Update linked sessions
                        </summary>

                        <div class="pb-4">
                            @include('livewire.event.data-sync.linked-update-preview')
                        </div>
                    </details>

                    <details wire:ignore.self class="border-t border-gray-100">

                        <summary class="cursor-pointer py-4 text-sm font-semibold text-headingTextColor">
                            Create missing sessions
                        </summary>

                        <div class="pb-4">
                            @include('livewire.event.data-sync.create-preview')
                        </div>
                    </details>
                </div>

                {{-- Occasional setup --}}
                <details wire:ignore.self class="border-t border-gray-100">

                    <summary class="cursor-pointer py-4 text-sm font-medium text-gray-500">
                        Setup & maintenance
                    </summary>

                    <div class="border-l border-gray-200 pl-4">
                        <details wire:ignore.self>
                            <summary class="cursor-pointer py-3 text-sm font-medium text-gray-700">
                                Link existing sessions
                            </summary>

                            <div class="pb-3">
                                @include('livewire.event.details.wordpress-agenda-linking')
                            </div>
                        </details>

                        <details wire:ignore.self class="border-t border-gray-100">

                            <summary class="cursor-pointer py-3 text-sm font-medium text-gray-700">
                                Agenda settings
                            </summary>

                            <div class="pb-3">
                                @include('livewire.event.details.wordpress-agenda-settings')
                            </div>
                        </details>

                        <details wire:ignore.self class="border-t border-gray-100">

                            <summary class="cursor-pointer py-3 text-sm font-medium text-gray-700">
                                Inspect WordPress source
                            </summary>

                            <div class="pb-3">
                                @include('livewire.event.details.wordpress-agenda-preview')
                            </div>
                        </details>
                    </div>
                </details>
            </div>
        </details>

        {{-- Meeting room partners --}}
        <details wire:ignore.self class="border border-gray-200 rounded-xl bg-white">

            <summary class="cursor-pointer px-5 py-4 text-lg font-semibold text-headingTextColor">
                Meeting room partners
                <span class="ml-2 text-xs font-normal text-gray-500">
                    Preview WordPress partners
                </span>
            </summary>

            <div class="border-t border-gray-100 px-5 pb-5">

                <details wire:ignore.self class="border-t border-gray-100">

                    <summary class="cursor-pointer py-4 text-sm font-medium text-gray-600">
                        Update existing partners
                    </summary>

                    @include('livewire.event.data-sync.meeting-room-partner-updates')
                </details>


                <details wire:ignore.self class="border-t border-gray-100">

                    <summary class="cursor-pointer py-4 text-sm font-medium text-gray-600">
                        Create missing partners
                    </summary>

                    @include('livewire.event.data-sync.meeting-room-partner-creation')
                </details>


                <details wire:ignore.self>
                    <summary class="cursor-pointer py-4 text-sm font-medium text-gray-600">
                        Inspect WordPress source
                    </summary>

                    @include('livewire.event.data-sync.meeting-room-partner-preview')
                </details>

                <details wire:ignore.self class="border-t border-gray-100">

                    <summary class="cursor-pointer py-4 text-sm font-medium text-gray-600">
                        Setup — match existing app partners
                    </summary>

                    @include('livewire.event.data-sync.meeting-room-partner-linking')
                </details>
            </div>
        </details>
    </div>
</div>
