<div class="border rounded-xl p-4 mt-4">
    <h3 class="text-xl font-semibold">
        App destination and speaker types
    </h3>


<p class="text-sm text-gray-600 mt-2">
    Settings are saved separately for each event, WordPress source and year.
    Saving settings does not change existing speakers or their links.
    After editing a mapping, save it before continuing.
</p>

    <label for="wp-speaker-destination" class="block mt-4 mb-2">
        App category
    </label>

    <select id="wp-speaker-destination" wire:model="wpSpeakerDestination" class="border rounded p-2 w-full">
        <option value="">Select an app category</option>

        @foreach ($this->wpSpeakerCategoryChoices as $category)
            <option value="{{ $category['id'] }}">
                {{ $category['label'] }}
            </option>
        @endforeach
    </select>

    @error('wpSpeakerDestination')
        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
    @enderror

    {{-- @forelse ($this->wpSpeakerRoleChoices as $role) --}}

    @forelse ($this->getWpSpeakerRoleChoicesProperty() as $role)
        <div wire:key="wp-speaker-role-map-{{ $role['key'] }}" class="mt-4">
            <label for="wp-speaker-role-{{ $role['key'] }}" class="block mb-2">
                WordPress “{{ $role['label'] }}” → App speaker type
            </label>

            <select id="wp-speaker-role-{{ $role['key'] }}" wire:model="wpSpeakerRoleMappings.{{ $role['key'] }}"
                class="border rounded p-2 w-full">
                <option value="">Select an app speaker type</option>

                @foreach ($this->wpSpeakerTypeChoices as $speakerType)
                    <option value="{{ $speakerType->id }}">
                        {{ $speakerType->name }}
                    </option>
                @endforeach
            </select>

            @error('wpSpeakerRoleMappings.' . $role['key'])
                <p class="text-red-600 text-sm mt-1">
                    {{ $message }}
                </p>
            @enderror
        </div>
    @empty
        <p class="mt-3 text-sm">
            No eligible speaker roles are available to map.
        </p>
    @endforelse

    @error('wpSpeakerRoleMappings')
        <p class="text-red-600 text-sm mt-2">{{ $message }}</p>
    @enderror

    <button type="button" wire:click="checkWordPressSpeakerMapping" wire:loading.attr="disabled"
        class="border rounded p-3 mt-4">
        Check mapping
    </button>

    <button
    type="button"
    wire:click="saveWordPressSpeakerSettings"
    wire:loading.attr="disabled"
    class="bg-primaryColor text-white rounded p-3 mt-4">
    Save speaker source settings
</button>

@if ($wpSpeakerSettingsMessage)
    <p class="text-green-700 mt-3">
        {{ $wpSpeakerSettingsMessage }}
    </p>
@endif

@if ($wpSpeakerSettingsError)
    <p class="text-red-600 mt-3">
        {{ $wpSpeakerSettingsError }}
    </p>
@endif


    @if ($wpSpeakerMappingMessage)
        <p class="text-green-700 mt-3">
            {{ $wpSpeakerMappingMessage }}
        </p>
    @endif
</div>
