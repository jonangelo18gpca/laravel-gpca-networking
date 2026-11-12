<div class="border border-primaryColor rounded-2xl py-5 px-7 mt-5">
    <h2 class="text-headingTextColor text-2xl font-bold">
        WordPress Agenda Settings
    </h2>

    <p class="mt-2">
        Configuration only. Saving does not fetch, create or update
        sessions, speakers or assignments.
    </p>

    @if (session()->has('wordpressAgendaSettingsSaved'))
        <p class="text-green-700 mt-3">
            {{ session('wordpressAgendaSettingsSaved') }}
        </p>
    @endif

    <form wire:submit.prevent="saveWordPressAgendaSettings" class="mt-4">
        @if ($errors->any())
            <div class="border border-red-500 rounded p-3 mb-4 text-red-700">
                <p>Settings were not saved:</p>

                <ul class="list-disc pl-5 mt-2">
                    @foreach ($errors->getMessages() as $field => $messages)
                        @foreach ($messages as $message)
                            <li>{{ $field }}: {{ $message }}</li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        @endif

        <p class="text-sm text-gray-500 mb-4">
            The WordPress website URL is configured under
            WordPress General Settings above.
        </p>

        <label class="block">
            Event year (WordPress year filter)
        </label>

        <input type="text" wire:model.defer="wpAgendaYear" class="border rounded p-2" maxlength="4" required>

        @error('wpAgendaYear')
            <p class="text-red-600">{{ $message }}</p>
        @enderror

        <h3 class="font-bold mt-5">
            Agenda sources
        </h3>

        @foreach ($wpAgendaSources as $index => $source)
            <div class="border rounded p-3 mt-2" wire:key="wp-source-{{ $index }}">
                <label class="block">
                    WordPress agenda post-type slug
                </label>

                <input type="text" wire:model.defer="wpAgendaSources.{{ $index }}.post_type"
                    class="border rounded p-2 w-full" maxlength="100" required>

                @error('wpAgendaSources.' . $index . '.post_type')
                    <p class="text-red-600">{{ $message }}</p>
                @enderror

                <label class="block mt-2">
                    App category / feature
                </label>

                <select wire:model.defer="wpAgendaSources.{{ $index }}.feature_id"
                    class="border rounded p-2 w-full" required>
                    <option value="">Select category</option>

                    <option value="0">
                        {{ $eventData['eventDetails']['short_name'] }}
                        — Main event
                    </option>

                    @foreach ($this->wpFeatureOptions as $feature)
                        <option value="{{ $feature->id }}">
                            {{ $feature->short_name }}
                        </option>
                    @endforeach
                </select>

                @error('wpAgendaSources.' . $index . '.feature_id')
                    <p class="text-red-600">{{ $message }}</p>
                @enderror

                <div class="border-t mt-4 pt-4">
                    <h4 class="font-semibold">
                        Agenda–speaker relationship sync
                    </h4>

                    <label class="block mt-2 text-sm">
                        <input type="checkbox"
                            wire:model.defer="wpAgendaSources.{{ $index }}.speaker_relationship.enabled">

                        Enable relationship sync for this agenda source
                    </label>

                    <p class="text-sm text-gray-500 mt-2">
                        Configure the JetEngine relationship used by this
                        agenda source. These settings do not create assignments.
                    </p>

                    <label class="block mt-3">
                        JetEngine relation ID
                    </label>

                    <input type="number" min="1" max="2147483647"
                        wire:model.defer="wpAgendaSources.{{ $index }}.speaker_relationship.relation_id"
                        class="border rounded p-2 w-full" placeholder="Relationship ID">

                    @error('wpAgendaSources.' . $index . '.speaker_relationship.relation_id')
                        <p class="text-red-600">{{ $message }}</p>
                    @enderror

                    <label class="block mt-3">
                        Related WordPress speaker post-type slug
                    </label>

                    <input type="text"
                        wire:model.defer="wpAgendaSources.{{ $index }}.speaker_relationship.speaker_post_type"
                        class="border rounded p-2 w-full" maxlength="100" placeholder="Exact speaker post-type slug">

                    @error('wpAgendaSources.' . $index . '.speaker_relationship.speaker_post_type')
                        <p class="text-red-600">{{ $message }}</p>
                    @enderror

                    <label class="block mt-3">
                        Relationship role metadata key — optional
                    </label>

                    <input type="text"
                        wire:model.defer="wpAgendaSources.{{ $index }}.speaker_relationship.role_meta_key"
                        class="border rounded p-2 w-full" maxlength="100" placeholder="For example: session_role">

                    <p class="text-sm text-gray-500 mt-1">
                        Leave empty if the relationship has no role metadata.
                        An empty connection value also falls back to the
                        speaker profile role.
                    </p>

                    @error('wpAgendaSources.' . $index . '.speaker_relationship.role_meta_key')
                        <p class="text-red-600">{{ $message }}</p>
                    @enderror

                    <label class="block mt-3">
                        Speaker profile role metadata key
                    </label>

                    <input type="text"
                        wire:model.defer="wpAgendaSources.{{ $index }}.speaker_relationship.speaker_role_meta_key"
                        class="border rounded p-2 w-full" maxlength="100" placeholder="For example: role">

                    <p class="text-sm text-gray-500 mt-1">
                        Used when the relationship role is unavailable or
                        empty. Leave empty to skip profile-role lookup.
                    </p>

                    @error('wpAgendaSources.' . $index . '.speaker_relationship.speaker_role_meta_key')
                        <p class="text-red-600">{{ $message }}</p>
                    @enderror

                    <label class="block mt-3">
                        Final default Speaker Type
                    </label>

                    <input type="text"
                        wire:model.defer="wpAgendaSources.{{ $index }}.speaker_relationship.default_role"
                        class="border rounded p-2 w-full" maxlength="255" required>

                    <p class="text-sm text-gray-500 mt-1">
                        Used only when neither the relationship nor the
                        speaker profile provides a role.
                    </p>

                    @error('wpAgendaSources.' . $index . '.speaker_relationship.default_role')
                        <p class="text-red-600">{{ $message }}</p>
                    @enderror

                    <label class="block mt-3">
                        Optional role label mappings
                    </label>

                    <textarea wire:model.defer="wpAgendaSources.{{ $index }}.speaker_relationship.role_labels_text" rows="4"
                        maxlength="30000" class="border rounded p-2 w-full" placeholder="moderator|Moderator&#10;presenter|Presenter"></textarea>

                    <p class="text-sm text-gray-500 mt-1">
                        One stored-value|App Speaker Type name mapping per line.
                        Matching is case-sensitive. Leave empty to use the
                        role value directly. A mapping applies to either
                        relationship-role or profile-role values.
                    </p>

                    @error('wpAgendaSources.' . $index . '.speaker_relationship.role_labels_text')
                        <p class="text-red-600">{{ $message }}</p>
                    @enderror

                    @error('wpAgendaSources.' . $index . '.speaker_relationship.enabled')
                        <p class="text-red-600">{{ $message }}</p>
                    @enderror

                    @error('wpAgendaSources.' . $index . '.speaker_relationship')
                        <p class="text-red-600">{{ $message }}</p>
                    @enderror

                    <p class="text-sm text-gray-600 mt-3">
                        Role priority: relationship metadata → speaker profile
                        role → final default.
                    </p>
                </div>

                <button type="button" wire:click="removeWpAgendaSource({{ $index }})"
                    class="text-red-600 mt-3">
                    Remove source
                </button>
            </div>
        @endforeach

        @error('wpAgendaSources')
            <p class="text-red-600">{{ $message }}</p>
        @enderror

        <button type="button" wire:click="addWpAgendaSource" class="border rounded p-2 mt-2">
            Add agenda source
        </button>

        <h3 class="font-bold mt-5">
            Day and date mappings
        </h3>

        @foreach ($wpAgendaDays as $index => $mapping)
            <div class="border rounded p-3 mt-2" wire:key="wp-day-{{ $index }}">
                <label class="block">
                    WordPress day label
                </label>

                <input type="text" wire:model.defer="wpAgendaDays.{{ $index }}.wordpress_day"
                    class="border rounded p-2 w-full" required>

                @error('wpAgendaDays.' . $index . '.wordpress_day')
                    <p class="text-red-600">{{ $message }}</p>
                @enderror

                <label class="block mt-2">
                    Existing app session day
                </label>

                <select wire:model.defer="wpAgendaDays.{{ $index }}.session_day"
                    class="border rounded p-2 w-full" required>
                    <option value="">Select day</option>

                    @foreach ($this->wpDayOptions as $day)
                        <option value="{{ $day->session_day }}">
                            {{ $day->session_day }}
                        </option>
                    @endforeach
                </select>

                @error('wpAgendaDays.' . $index . '.session_day')
                    <p class="text-red-600">{{ $message }}</p>
                @enderror

                <label class="block mt-2">
                    Existing app session date
                </label>

                <select wire:model.defer="wpAgendaDays.{{ $index }}.session_date"
                    class="border rounded p-2 w-full" required>
                    <option value="">Select date</option>

                    @foreach ($this->wpDateOptions as $date)
                        @php
                            $dateValue = \Carbon\Carbon::parse($date->session_date)->format('Y-m-d');
                        @endphp

                        <option value="{{ $dateValue }}">
                            {{ \Carbon\Carbon::parse($dateValue)->format('F d, Y') }}
                        </option>
                    @endforeach
                </select>

                @error('wpAgendaDays.' . $index . '.session_date')
                    <p class="text-red-600">{{ $message }}</p>
                @enderror

                <button type="button" wire:click="removeWpAgendaDay({{ $index }})"
                    class="text-red-600 mt-2">
                    Remove mapping
                </button>
            </div>
        @endforeach

        @error('wpAgendaDays')
            <p class="text-red-600">{{ $message }}</p>
        @enderror

        <button type="button" wire:click="addWpAgendaDay" class="border rounded p-2 mt-2">
            Add day mapping
        </button>

        <div class="mt-5">
            <button type="submit" wire:loading.attr="disabled" class="bg-primaryColor text-white rounded p-3">
                Save WordPress agenda settings
            </button>
        </div>
    </form>
</div>
