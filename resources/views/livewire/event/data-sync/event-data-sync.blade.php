

<div>
    <div class="mb-6">
        <h1 class="text-headingTextColor text-3xl font-bold">
            Data Sync
        </h1>

        <p class="mt-2 text-gray-600">
            Preview website changes for linked app sessions.
            Unlinked records are left untouched.
        </p>
    </div>

    {{-- Main everyday workflow --}}
    <div class="border border-primaryColor rounded-2xl py-5 px-7">
        <h2 class="text-headingTextColor text-2xl font-bold">
            Sync sessions
        </h2>

        <label for="wp-sync-feature" class="block mt-4 mb-2">
            Agenda feature
        </label>

        <select
            id="wp-sync-feature"
            wire:model="wpPreviewPostType"
            class="border rounded p-2 w-full">
            <option value="">Select a feature</option>

            @foreach ($this->wpPreviewSourceChoices as $choice)
                <option value="{{ $choice['post_type'] }}">
                    {{ $choice['label'] }}
                </option>
            @endforeach
        </select>

        <p class="mt-3 text-sm text-gray-600">
            The preview fetches WordPress data and compares saved
            links automatically. You do not need to load the source
            preview or linking list first.
        </p>
    </div>

    @include('livewire.event.data-sync.linked-update-preview')
    @include('livewire.event.data-sync.create-preview')

    {{-- Initial setup / occasional maintenance --}}
    <details class="mt-6 border border-gray-200 rounded-xl p-5">
        <summary class="cursor-pointer font-semibold">
            Manage existing-session links — initial setup
        </summary>

        <p class="mt-3 text-sm text-gray-600">
            Select an agenda feature above, then load the linking list.
            Use this only to connect existing app sessions that do not
            already have a saved WordPress link.
        </p>

        @include('livewire.event.details.wordpress-agenda-linking')
    </details>

    <details class="mt-6 border border-gray-200 rounded-xl p-5">
        <summary class="cursor-pointer font-semibold">
            Source settings — configuration
        </summary>

        @include('livewire.event.details.wordpress-agenda-settings')
    </details>

    <details class="mt-6 border border-gray-200 rounded-xl p-5">
        <summary class="cursor-pointer font-semibold">
            Inspect WordPress source — optional
        </summary>

        <p class="mt-3 text-sm text-gray-600">
            This is a separate source inspection tool.
            It is not required before previewing linked updates.
        </p>

        @include('livewire.event.details.wordpress-agenda-preview')

    </details>
</div>