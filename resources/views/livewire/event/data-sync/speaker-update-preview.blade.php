<div class="border rounded-xl p-4 mt-4">
    <h3 class="text-xl font-semibold">
        Preview linked speaker updates
    </h3>
<p class="text-sm text-gray-600 mt-2">
    Select the profile changes you want to apply.
    Missing or changed profile photos are selected automatically
    when WordPress provides a valid image URL.
    Matching photo URLs need no update.
    Missing WordPress images do not remove existing app photos.
    Name updates include all four name parts together.
    Categories, speaker types, ordering, cover photos and session
    assignments are not changed.
</p>

    <button type="button" wire:click="loadWordPressSpeakerUpdates" wire:loading.attr="disabled"
        class="border rounded p-3 mt-3">
        Preview linked speaker changes
    </button>

    <p wire:loading wire:target="loadWordPressSpeakerUpdates" class="mt-2">
        Fetching WordPress and comparing linked speakers...
    </p>

    @if ($wpSpeakerUpdateError)
        <p class="text-red-600 mt-3">
            {{ $wpSpeakerUpdateError }}
        </p>
    @endif

    @if ($wpSpeakerUpdateSuccess)
        <p class="text-green-700 mt-3">
            {{ $wpSpeakerUpdateSuccess }}
        </p>
    @endif

    @if ($wpSpeakerUpdateLoaded)
        @php
            $selectedFieldCount = 0;

            foreach ($wpSpeakerUpdateSelections as $selectedFields) {
                if (!is_array($selectedFields)) {
                    continue;
                }

                foreach ($selectedFields as $isSelected) {
                    if ($isSelected === true) {
                        $selectedFieldCount++;
                    }
                }
            }
        @endphp

        <p class="mt-3">
            {{ count($wpSpeakerUpdateRows) }}
            linked speaker records found in this source/year.
        </p>
        

        @if ($wpSpeakerUpdateToken)
    <div class="flex flex-wrap items-center gap-2 mt-3">
        <button
            type="button"
            wire:click="selectAllWordPressSpeakerUpdates(true)"
            wire:loading.attr="disabled"
            class="border rounded px-3 py-2 text-sm">
            Select all proposed changes
        </button>

        <button
            type="button"
            wire:click="selectAllWordPressSpeakerUpdates(false)"
            wire:loading.attr="disabled"
            class="border rounded px-3 py-2 text-sm">
            Clear selection
        </button>
    </div>
@endif

        @forelse ($wpSpeakerUpdateRows as $row)
            <div wire:key="wp-speaker-update-{{ $row['wp_id'] }}-{{ $row['speaker_id'] }}"
                class="border rounded-lg p-3 mt-4">

                <h4 class="font-semibold">
                    WP #{{ $row['wp_id'] }}
                    — {{ $row['source_name'] }}
                </h4>

                <p class="text-sm mt-1">
                    App #{{ $row['speaker_id'] }}
                    — {{ $row['app_name'] }}
                </p>

                <p class="text-sm mt-1">
                    {{ $row['eligibility'] }}.
                    {{ $row['change_count'] }} proposed field change(s).
                </p>

                <div class="overflow-x-auto mt-3">
                    <table class="w-full" border="1" cellpadding="8">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Current app value</th>
                                <th>WordPress value</th>
                                <th>Result</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($row['fields'] as $field)
                                <tr
                                    wire:key="wp-speaker-update-field-{{ $row['wp_id'] }}-{{ $row['speaker_id'] }}-{{ $field['field'] }}">
                                    <td class="align-top">
                                        {{ $field['label'] }}
                                    </td>

                                    <td class="align-top">
                                        <details wire:ignore.self>
                                            <summary class="cursor-pointer">
                                                {{ \Illuminate\Support\Str::limit($field['current'] ?: 'Empty', 100) }}
                                            </summary>

                                            <p class="mt-2 whitespace-pre-line">
                                                {{ $field['current'] ?: 'Empty' }}
                                            </p>
                                        </details>
                                    </td>

                                    <td class="align-top">
                                        <details wire:ignore.self>
                                            <summary class="cursor-pointer">
                                                {{ \Illuminate\Support\Str::limit($field['source'] ?: 'Empty', 100) }}
                                            </summary>

                                            <p class="mt-2 whitespace-pre-line">
                                                {{ $field['source'] ?: 'Empty' }}
                                            </p>
                                        </details>
                                    </td>
                                    <td class="align-top">
                                        <p>{{ $field['status'] }}</p>

                                        @if ($field['status'] === 'Proposed change' && $wpSpeakerUpdateToken)
                                            <label for="wp-update-field-{{ $row['wp_id'] }}-{{ $field['field'] }}"
                                                class="block text-sm mt-2">

                                                <input id="wp-update-field-{{ $row['wp_id'] }}-{{ $field['field'] }}"
                                                    type="checkbox"
                                                    wire:model="wpSpeakerUpdateSelections.{{ $row['wp_id'] }}.{{ $field['field'] }}"
                                                    wire:loading.attr="disabled">

                                                Apply {{ $field['label'] }}
                                            </label>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <p class="mt-3">
                No saved links were found for the selected source/year.
                Save an existing-speaker link first.
            </p>
        @endforelse

        @if ($wpSpeakerUpdateToken)
            <p class="text-sm mt-3">
                Selected profile changes: {{ $selectedFieldCount }}
            </p>

            @if ($selectedFieldCount === 0)
                <p class="text-sm text-yellow-700 mt-2">
                    Tick “Apply” beside at least one field marked
                    “Proposed change” in the comparison above.
                    The confirmation checkbox does not select fields.
                </p>
            @endif

            <label class="block text-sm mt-3">
                <input type="checkbox" wire:model="wpSpeakerUpdateConfirmed" wire:loading.attr="disabled"
                    @if ($selectedFieldCount === 0) disabled @endif>

                I reviewed the {{ $selectedFieldCount }} selected profile change(s).
                Apply only these changes to the linked app speakers.
            </label>

            <button type="button" wire:click="applyWordPressSpeakerUpdates" wire:loading.attr="disabled"
                @if (!$wpSpeakerUpdateConfirmed || $selectedFieldCount === 0) disabled @endif class="bg-primaryColor text-white rounded p-3 mt-3">
                Apply {{ $selectedFieldCount }} selected change(s)
            </button>

            <p wire:loading wire:target="applyWordPressSpeakerUpdates" class="mt-2">
                Rechecking WordPress and applying selected changes...
            </p>
        @endif
    @endif
</div>
