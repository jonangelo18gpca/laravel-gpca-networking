<div class="border border-primaryColor rounded-2xl py-5 px-7 mt-5">
    <h2 class="text-headingTextColor text-2xl font-bold">WordPress Agenda Settings</h2>
    <p class="mt-2">Configuration only. Saving does not fetch, create or update sessions.</p>
    @if (session()->has('wordpressAgendaSettingsSaved'))
        <p class="text-green-700 mt-3">{{ session('wordpressAgendaSettingsSaved') }}</p>
    @endif
    <form wire:submit.prevent="saveWordPressAgendaSettings" class="mt-4">
        <label class="block">WordPress website URL</label>
        <input type="url" wire:model.defer="wpSourceUrl" placeholder="https://www.gpcaforum.com"
            class="border rounded p-2 w-full" required>
        @error('wpSourceUrl') <p class="text-red-600">{{ $message }}</p> @enderror
        <label class="block mt-3">Event year (WordPress year filter)</label>
        <input type="text" wire:model.defer="wpAgendaYear" class="border rounded p-2" maxlength="4" required>
        @error('wpAgendaYear') <p class="text-red-600">{{ $message }}</p> @enderror
        <h3 class="font-bold mt-5">Agenda sources</h3>
        @foreach ($wpAgendaSources as $index => $source)
            <div class="border rounded p-3 mt-2" wire:key="wp-source-{{ $index }}">
                <label class="block">WordPress post-type slug</label>
                <input type="text" wire:model.defer="wpAgendaSources.{{ $index }}.post_type" class="border rounded p-2 w-full" required>
                <label class="block mt-2">App category / feature</label>
                <select wire:model.defer="wpAgendaSources.{{ $index }}.feature_id" class="border rounded p-2 w-full" required>
                    <option value="">Select category</option>
                    <option value="0">{{ $eventData['eventDetails']['short_name'] }} — Main event</option>
                    @foreach ($this->wpFeatureOptions as $feature)
                        <option value="{{ $feature->id }}">{{ $feature->short_name }}</option>
                    @endforeach
                </select>
                @error('wpAgendaSources.'.$index.'.post_type') <p class="text-red-600">{{ $message }}</p> @enderror
                @error('wpAgendaSources.'.$index.'.feature_id') <p class="text-red-600">{{ $message }}</p> @enderror
                <button type="button" wire:click="removeWpAgendaSource({{ $index }})" class="text-red-600 mt-2">Remove source</button>
            </div>
        @endforeach
        @error('wpAgendaSources') <p class="text-red-600">{{ $message }}</p> @enderror
        <button type="button" wire:click="addWpAgendaSource" class="border rounded p-2 mt-2">Add agenda source</button>
        <h3 class="font-bold mt-5">Day and date mappings</h3>
        @foreach ($wpAgendaDays as $index => $mapping)
            <div class="border rounded p-3 mt-2" wire:key="wp-day-{{ $index }}">
                <label class="block">WordPress day label</label>
                <input type="text" wire:model.defer="wpAgendaDays.{{ $index }}.wordpress_day" class="border rounded p-2 w-full" required>
                <label class="block mt-2">Existing app session day</label>
                <select wire:model.defer="wpAgendaDays.{{ $index }}.session_day" class="border rounded p-2 w-full" required>
                    <option value="">Select day</option>
                    @foreach ($this->wpDayOptions as $day)
                        <option value="{{ $day->session_day }}">{{ $day->session_day }}</option>
                    @endforeach
                </select>
                <label class="block mt-2">Existing app session date</label>
                <select wire:model.defer="wpAgendaDays.{{ $index }}.session_date" class="border rounded p-2 w-full" required>
                    <option value="">Select date</option>
                    @foreach ($this->wpDateOptions as $date)
                        @php($dateValue = \Carbon\Carbon::parse($date->session_date)->format('Y-m-d'))
                        <option value="{{ $dateValue }}">{{ \Carbon\Carbon::parse($dateValue)->format('F d, Y') }}</option>
                    @endforeach
                </select>
                @error('wpAgendaDays.'.$index.'.wordpress_day') <p class="text-red-600">{{ $message }}</p> @enderror
                @error('wpAgendaDays.'.$index.'.session_day') <p class="text-red-600">{{ $message }}</p> @enderror
                @error('wpAgendaDays.'.$index.'.session_date') <p class="text-red-600">{{ $message }}</p> @enderror
                <button type="button" wire:click="removeWpAgendaDay({{ $index }})" class="text-red-600 mt-2">Remove mapping</button>
            </div>
        @endforeach
        @error('wpAgendaDays') <p class="text-red-600">{{ $message }}</p> @enderror
        <button type="button" wire:click="addWpAgendaDay" class="border rounded p-2 mt-2">Add day mapping</button>
        <div class="mt-5">
            <button type="submit" wire:loading.attr="disabled" class="bg-primaryColor text-white rounded p-3">Save WordPress agenda settings</button>
        </div>
    </form>
</div>
