<div class="border rounded-xl p-4 mt-4">
    <h3 class="text-xl font-semibold">
        Speaker changes
    </h3>

    <p class="text-sm text-gray-600 mt-2">
        Only linked speakers with proposed changes are listed.
        Only changed fields are shown inside each speaker box.
        All changes, including photos, start unchecked.
    </p>

    <p class="text-sm text-gray-600 mt-2">
        Missing WordPress values preserve existing app values.
        Name updates include all four name parts together.
        Categories, speaker types, ordering, cover photos and
        session assignments are not changed.
    </p>

    <button
        type="button"
        wire:click="loadWordPressSpeakerUpdates"
        wire:loading.attr="disabled"
        class="border rounded p-3 mt-3">
        Preview linked speaker changes
    </button>

    <p
        wire:loading
        wire:target="loadWordPressSpeakerUpdates"
        class="mt-2">
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
            $selectedSpeakerCount = 0;

            foreach ($wpSpeakerUpdateSelections as $selectedFields) {
                if (!is_array($selectedFields)) {
                    continue;
                }

                $speakerFieldCount = count(array_filter(
                    $selectedFields,
                    function ($checked) {
                        return $checked === true;
                    }
                ));

                $selectedFieldCount += $speakerFieldCount;

                if ($speakerFieldCount > 0) {
                    $selectedSpeakerCount++;
                }
            }
        @endphp

        <div class="mt-3 text-sm space-y-1">
            <p>
                Speakers with changes:
                {{ count($wpSpeakerUpdateRows) }}
            </p>

            <p>
                Selected:
                {{ $selectedFieldCount }} field/group change(s)
                across {{ $selectedSpeakerCount }} speaker(s).
            </p>
        </div>

        @if (count($wpSpeakerUpdateNotices))
            <details wire:ignore.self class="border rounded p-3 mt-3">
                <summary class="cursor-pointer text-sm">
                    Review / preservation notices
                    ({{ count($wpSpeakerUpdateNotices) }} speaker records)
                </summary>

                @foreach ($wpSpeakerUpdateNotices as $notice)
                    <div class="mt-3 text-sm">
                        <p class="font-medium">
                            WP #{{ $notice['wp_id'] }}
                            → App #{{ $notice['speaker_id'] }}
                            — {{ $notice['name'] }}
                        </p>

                        @foreach ($notice['messages'] as $message)
                            <p class="text-gray-600 mt-1">
                                {{ $message }}
                            </p>
                        @endforeach
                    </div>
                @endforeach
            </details>
        @endif

        @if ($wpSpeakerUpdateToken && count($wpSpeakerUpdateRows))
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
                    Clear all selections
                </button>
            </div>

            <p class="text-sm text-gray-600 mt-2">
                These buttons affect every speaker in this preview.
                Selecting does not save changes.
                Preview expires after 15 minutes.
            </p>
        @endif

        @forelse ($wpSpeakerUpdateRows as $row)
            <div
                wire:key="wp-speaker-update-{{ $row['wp_id'] }}-{{ $row['speaker_id'] }}"
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
                    {{ $row['change_count'] }}
                    proposed field/group change(s).
                </p>

                <div class="flex flex-wrap items-center gap-2 mt-3">
                    <button
                        type="button"
                        wire:click="selectWordPressSpeakerUpdateFields({{ $row['wp_id'] }}, true)"
                        wire:loading.attr="disabled"
                        class="border rounded px-3 py-2 text-sm">
                        Select all for this speaker
                    </button>

                    <button
                        type="button"
                        wire:click="selectWordPressSpeakerUpdateFields({{ $row['wp_id'] }}, false)"
                        wire:loading.attr="disabled"
                        class="border rounded px-3 py-2 text-sm">
                        Clear speaker selection
                    </button>
                </div>

                <div class="overflow-x-auto mt-3">
                    <table class="w-full" border="1" cellpadding="8">
                        <thead>
                            <tr>
                                <th>Apply</th>
                                <th>Changed field</th>
                                <th>Current app value</th>
                                <th>WordPress value</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($row['fields'] as $field)
                                <tr wire:key="wp-speaker-update-field-{{ $row['wp_id'] }}-{{ $row['speaker_id'] }}-{{ $field['field'] }}">
                                    <td class="align-top">
                                        <input
                                            type="checkbox"
                                            wire:model="wpSpeakerUpdateSelections.{{ $row['wp_id'] }}.{{ $field['field'] }}"
                                            wire:loading.attr="disabled"
                                            aria-label="Apply {{ $field['label'] }} for {{ $row['source_name'] }}">
                                    </td>

                                    <td class="align-top">
                                        {{ $field['label'] }}
                                    </td>

                                    <td class="align-top">
                                        <details wire:ignore.self>
                                            <summary class="cursor-pointer break-all">
                                                {{ \Illuminate\Support\Str::limit($field['current'] !== '' ? $field['current'] : 'Empty', 100) }}
                                            </summary>

                                            <p class="mt-2 whitespace-pre-line break-all">{{ $field['current'] !== '' ? $field['current'] : 'Empty' }}</p>
                                        </details>
                                    </td>

                                    <td class="align-top">
                                        <details wire:ignore.self>
                                            <summary class="cursor-pointer break-all">
                                                {{ \Illuminate\Support\Str::limit($field['source'] !== '' ? $field['source'] : 'Empty', 100) }}
                                            </summary>

                                            <p class="mt-2 whitespace-pre-line break-all">{{ $field['source'] !== '' ? $field['source'] : 'Empty' }}</p>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <p class="mt-3 text-gray-600">
                No actionable changes were found for linked speakers.
                Unchanged speakers are hidden.
                Review any notices above.
            </p>
        @endforelse

        @if ($wpSpeakerUpdateToken && count($wpSpeakerUpdateRows))
            <div class="border-t mt-4 pt-4">
                <p class="text-sm">
                    Selected:
                    {{ $selectedFieldCount }} field/group change(s)
                    across {{ $selectedSpeakerCount }} speaker(s).
                </p>

                @if ($selectedFieldCount === 0)
                    <p class="text-sm text-yellow-700 mt-2">
                        Select individual fields, all changes for one speaker,
                        or all proposed changes.
                        Confirmation does not select fields.
                    </p>
                @endif

                <label class="block text-sm mt-3">
                    <input
                        type="checkbox"
                        wire:model="wpSpeakerUpdateConfirmed"
                        wire:loading.attr="disabled"
                        @if ($selectedFieldCount === 0) disabled @endif>

                    I reviewed the
                    {{ $selectedFieldCount }} selected field/group change(s)
                    across {{ $selectedSpeakerCount }} speaker(s).
                    Apply only these changes.
                </label>

                <button
                    type="button"
                    wire:click="applyWordPressSpeakerUpdates"
                    wire:loading.attr="disabled"
                    @if (
                        !$wpSpeakerUpdateConfirmed
                        || $selectedFieldCount === 0
                    ) disabled @endif
                    class="bg-primaryColor text-white rounded p-3 mt-3 disabled:opacity-50">
                    Apply {{ $selectedFieldCount }} selected change(s)
                </button>

                <p
                    wire:loading
                    wire:target="applyWordPressSpeakerUpdates"
                    class="mt-2">
                    Rechecking WordPress and applying selected changes...
                </p>
            </div>
        @endif
    @endif
</div>