<div class="border rounded-xl p-4 mt-4">
    <h3 class="text-xl font-semibold">
        Update linked exhibitors
    </h3>

    <p class="text-sm text-gray-600 mt-2">
        Only linked exhibitors are compared.
        Select the fields you want to update.
        Selecting an empty proposed value clears that field.
        Missing or inaccessible source logos do not remove existing app logos.
    </p>

    <button
        type="button"
        wire:click="loadWordPressExhibitorUpdates"
        wire:loading.attr="disabled"
        class="border rounded p-3 mt-3"
    >
        Load exhibitor update preview
    </button>

    <p
        wire:loading
        wire:target="loadWordPressExhibitorUpdates"
        class="text-sm mt-2"
    >
        Loading WordPress exhibitors and comparing linked records…
    </p>

    <div aria-live="polite">
        @if ($wpExhibitorUpdateError)
            <p class="text-red-600 mt-3">
                {{ $wpExhibitorUpdateError }}
            </p>
        @endif

        @if ($wpExhibitorUpdateSuccess)
            <p class="text-green-700 mt-3">
                {{ $wpExhibitorUpdateSuccess }}
            </p>
        @endif
    </div>

    @if (!empty($wpExhibitorUpdateNotices))
        <div class="mt-3 rounded border border-yellow-200 bg-yellow-50 p-3">
            <p class="text-sm font-semibold">
                Source notices
            </p>

            <ul class="list-disc pl-5 mt-2 space-y-1 text-sm text-yellow-800">
                @foreach ($wpExhibitorUpdateNotices as $notice)
                    <li>{{ $notice }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($wpExhibitorUpdateLoaded)
        @if (count($wpExhibitorUpdateRows) === 0)
            <p class="text-sm text-gray-600 mt-3">
                No eligible changes were found for linked exhibitors.
                Review any notices above.
            </p>
        @else
            <p class="text-sm text-gray-600 mt-3">
                {{ count($wpExhibitorUpdateRows) }} linked exhibitor(s)
                have proposed changes.
                This preview expires after 15 minutes.
            </p>

            <div class="flex flex-wrap gap-2 mt-3">
                <button
                    type="button"
                    wire:click="selectAllWordPressExhibitorUpdates(true)"
                    wire:loading.attr="disabled"
                    class="border rounded px-3 py-2 text-sm"
                >
                    Select all proposed changes
                </button>

                <button
                    type="button"
                    wire:click="selectAllWordPressExhibitorUpdates(false)"
                    wire:loading.attr="disabled"
                    class="border rounded px-3 py-2 text-sm"
                >
                    Clear all selections
                </button>
            </div>

            @foreach ($wpExhibitorUpdateRows as $row)
                <div
                    wire:key="wp-exhibitor-update-{{ $row['wp_id'] }}"
                    class="border rounded-lg p-3 mt-4"
                >
                    <h4 class="font-semibold">
                        WP #{{ $row['wp_id'] }}
                        — {{ $row['name'] }}
                    </h4>

                    <p class="text-sm text-gray-500 mt-1">
                        Linked app exhibitor #{{ $row['exhibitor_id'] }}
                    </p>

                    @if (!empty($row['warnings']))
                        <ul class="list-disc pl-5 mt-2 text-sm text-yellow-800">
                            @foreach ($row['warnings'] as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="flex flex-wrap gap-2 mt-3">
                        <button
                            type="button"
                            wire:click="selectWordPressExhibitorUpdateFields({{ $row['wp_id'] }}, true)"
                            wire:loading.attr="disabled"
                            class="border rounded px-3 py-2 text-sm"
                        >
                            Select this exhibitor's changes
                        </button>

                        <button
                            type="button"
                            wire:click="selectWordPressExhibitorUpdateFields({{ $row['wp_id'] }}, false)"
                            wire:loading.attr="disabled"
                            class="border rounded px-3 py-2 text-sm"
                        >
                            Clear this exhibitor
                        </button>
                    </div>

                    <div class="overflow-x-auto mt-3">
                        <table class="w-full text-sm border-collapse">
                            <thead>
                                <tr>
                                    <th class="border p-2 text-left">
                                        Apply
                                    </th>

                                    <th class="border p-2 text-left">
                                        Field
                                    </th>

                                    <th class="border p-2 text-left">
                                        Current app value
                                    </th>

                                    <th class="border p-2 text-left">
                                        WordPress value
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($row['changes'] as $change)
                                    <tr
                                        wire:key="wp-exhibitor-change-{{ $row['wp_id'] }}-{{ $change['field'] }}"
                                    >
                                        <td class="border p-2 align-top">
                                            <input
                                                type="checkbox"
                                                wire:model="wpExhibitorUpdateSelections.wp_{{ $row['wp_id'] }}.{{ $change['field'] }}"
                                                wire:loading.attr="disabled"
                                                aria-label="Update {{ $change['label'] }} for WP {{ $row['wp_id'] }}"
                                            >
                                        </td>

                                        <td class="border p-2 align-top">
                                            {{ $change['label'] }}
                                        </td>

                                        <td class="border p-2 align-top">
                                            <div class="max-h-56 max-w-md overflow-auto whitespace-pre-wrap break-words">{{ $change['current'] === '' ? '(Empty)' : $change['current'] }}</div>
                                        </td>

                                        <td class="border p-2 align-top">
                                            <div class="max-h-56 max-w-md overflow-auto whitespace-pre-wrap break-words">{{ $change['proposed'] === '' ? '(Empty — clears this field)' : $change['proposed'] }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            <label class="block mt-4 text-sm">
                <input
                    type="checkbox"
                    wire:model="wpExhibitorUpdateConfirmed"
                    wire:loading.attr="disabled"
                >

                I reviewed the selected fields and approve these updates.
            </label>

            <button
                type="button"
                wire:click="applyWordPressExhibitorUpdates"
                wire:loading.attr="disabled"
                @if (!$wpExhibitorUpdateConfirmed) disabled @endif
                class="bg-primaryColor text-white rounded p-3 mt-3 disabled:opacity-50"
            >
                Apply selected exhibitor updates
            </button>

            <p
                wire:loading
                wire:target="applyWordPressExhibitorUpdates"
                class="text-sm mt-2"
            >
                Rechecking WordPress and saving selected exhibitor updates…
            </p>
        @endif
    @endif
</div>