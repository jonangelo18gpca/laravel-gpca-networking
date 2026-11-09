<div class="border rounded-xl p-4 mt-4">
    <h3 class="text-xl font-semibold">
        Update existing partners
    </h3>

    <p class="text-sm text-gray-600 mt-2">
        Shows only linked partners with changed fields.
        Select individual changes to apply.
        All checkboxes start unchecked.
    </p>

    <p class="text-sm text-gray-600 mt-2">
        Missing or inaccessible source logos never clear an existing logo.
        Active status, banners, contact details and ordering are not updated.
        Source-empty text fields can clear app values only when selected.
    </p>

    <button
        type="button"
        wire:click="loadWordPressMeetingRoomPartnerUpdates"
        wire:loading.attr="disabled"
        class="border rounded p-3 mt-3">
        Load update preview
    </button>

    <p
        wire:loading
        wire:target="loadWordPressMeetingRoomPartnerUpdates"
        class="text-sm mt-2">
        Loading WordPress and comparing linked partners...
    </p>

    <div aria-live="polite">
        @if ($wpMrpUpdateError)
            <p class="text-red-600 mt-3">
                {{ $wpMrpUpdateError }}
            </p>
        @endif

        @if ($wpMrpUpdateSuccess)
            <p class="text-green-700 mt-3">
                {{ $wpMrpUpdateSuccess }}
            </p>
        @endif
    </div>

    @if (count($wpMrpUpdateNotices))
        <details class="border rounded p-3 mt-3">
            <summary class="cursor-pointer text-sm">
                Source warnings / blocked records
                ({{ count($wpMrpUpdateNotices) }})
            </summary>

            @foreach ($wpMrpUpdateNotices as $notice)
                <p class="text-sm text-gray-600 mt-2">
                    {{ $notice }}
                </p>
            @endforeach
        </details>
    @endif

    @if ($wpMrpUpdateLoaded && !count($wpMrpUpdateRows))
        <p class="text-sm mt-3">
            No actionable differences were found for linked partners.
            Review any warnings above.
        </p>
    @endif

    @if (count($wpMrpUpdateRows))
        <p class="text-sm mt-3">
            {{ count($wpMrpUpdateRows) }} linked partner(s) with changes.
            Preview expires after 15 minutes.
        </p>

        <div class="border rounded-lg bg-gray-50 p-3 mt-3">
    <p class="text-sm font-medium">
        All companies
    </p>

    <div class="flex flex-wrap items-center gap-2 mt-2">
        <button
            type="button"
            wire:click="selectAllWordPressMeetingRoomPartnerUpdates(true)"
            wire:loading.attr="disabled"
            class="border rounded px-3 py-2 text-sm">
            Select all changes
        </button>

        <button
            type="button"
            wire:click="selectAllWordPressMeetingRoomPartnerUpdates(false)"
            wire:loading.attr="disabled"
            class="border rounded px-3 py-2 text-sm">
            Clear all selections
        </button>
    </div>

    <p class="text-sm text-gray-600 mt-2">
        Applies to every company in this update preview.
        Includes proposed logo changes and empty text values
        that would clear existing app values.
        Review before confirming.
    </p>
</div>


        @foreach ($wpMrpUpdateRows as $row)
            <div
                wire:key="wp-mrp-update-{{ $row['wp_id'] }}"
                class="border rounded-lg p-3 mt-4">

                <h4 class="font-semibold">
                    {{ $row['name'] }}
                </h4>

                <p class="text-sm text-gray-500 mt-1">
                    WP #{{ $row['wp_id'] }}
                    → App #{{ $row['partner_id'] }}
                </p>

                <div class="flex flex-wrap items-center gap-2 mt-3">
    <button
        type="button"
        wire:click="selectWordPressMeetingRoomPartnerUpdateFields({{ $row['wp_id'] }}, true)"
        wire:loading.attr="disabled"
        class="border rounded px-3 py-2 text-sm">
        Select all for this company
    </button>

    <button
        type="button"
        wire:click="selectWordPressMeetingRoomPartnerUpdateFields({{ $row['wp_id'] }}, false)"
        wire:loading.attr="disabled"
        class="border rounded px-3 py-2 text-sm">
        Clear company selection
    </button>
</div>



                @foreach ($row['warnings'] as $warning)
                    <p class="text-sm text-gray-600 mt-2">
                        {{ $warning }}
                    </p>
                @endforeach

                <div class="overflow-x-auto mt-3">
                    <table
                        class="w-full text-sm"
                        border="1"
                        cellpadding="8">

                        <thead>
                            <tr>
                                <th>Apply</th>
                                <th>Field</th>
                                <th>Current app value</th>
                                <th>Proposed WordPress value</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($row['changes'] as $change)
                                <tr wire:key="wp-mrp-field-{{ $row['wp_id'] }}-{{ $change['field'] }}">
                                    <td class="align-top">
                                        <input
                                            type="checkbox"
                                            wire:model="wpMrpUpdateSelections.{{ $row['wp_id'] }}.{{ $change['field'] }}"
                                            wire:loading.attr="disabled"
                                            wire:target="applyWordPressMeetingRoomPartnerUpdates"
                                            aria-label="Apply {{ $change['label'] }} change for {{ $row['name'] }}">
                                    </td>

                                    <td class="align-top">
                                        {{ $change['label'] }}
                                    </td>

                                    <td class="align-top">
                                        @if ($change['field'] === 'profile_html_text')
                                            <details>
                                                <summary class="cursor-pointer">
                                                    View current HTML as text
                                                </summary>

                                                <pre class="whitespace-pre-wrap break-all mt-2">{{ $change['current'] !== '' ? $change['current'] : '(empty)' }}</pre>
                                            </details>
                                        @else
                                            <div class="whitespace-pre-wrap break-all">{{ $change['current'] !== '' ? $change['current'] : '(empty)' }}</div>
                                        @endif
                                    </td>

                                    <td class="align-top">
                                        @if ($change['field'] === 'profile_html_text')
                                            <details>
                                                <summary class="cursor-pointer">
                                                    View proposed HTML as text
                                                </summary>

                                                <pre class="whitespace-pre-wrap break-all mt-2">{{ $change['proposed'] !== '' ? $change['proposed'] : '(empty — clears app value if selected)' }}</pre>
                                            </details>
                                        @elseif ($change['field'] === 'logo_url')
                                            <a
                                                href="{{ $change['proposed'] }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="underline break-all">
                                                {{ $change['proposed'] }}
                                            </a>
                                        @else
                                            <div class="whitespace-pre-wrap break-all">{{ $change['proposed'] !== '' ? $change['proposed'] : '(empty — clears app value if selected)' }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endif

    @if ($wpMrpUpdateToken)
        <label class="block text-sm mt-4">
            <input
                type="checkbox"
                wire:model="wpMrpUpdateConfirmed"
                wire:loading.attr="disabled"
                wire:target="applyWordPressMeetingRoomPartnerUpdates">

            I reviewed the selected field changes.
            Apply only checked changes.
            Selected empty text values may clear existing values.
            Selected logos will be saved as external-URL media records.
        </label>

        <button
            type="button"
            wire:click="applyWordPressMeetingRoomPartnerUpdates"
            wire:loading.attr="disabled"
            @if (!$wpMrpUpdateConfirmed) disabled @endif
            class="bg-primaryColor text-white rounded p-3 mt-3">
            Apply selected changes
        </button>

        <p
            wire:loading
            wire:target="applyWordPressMeetingRoomPartnerUpdates"
            class="text-sm mt-2">
            Rechecking source and app values, then applying selected changes...
        </p>
    @endif
</div>