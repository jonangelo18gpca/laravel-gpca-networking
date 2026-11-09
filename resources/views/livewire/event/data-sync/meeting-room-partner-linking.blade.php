<div class="border rounded-xl p-4 mt-4">
    <h3 class="text-xl font-semibold">
        Match existing app partners
    </h3>

    <p class="text-sm text-gray-600 mt-2">
        Match only when certain. Saving creates source links only.
        It does not update partner details or logos.
        Already-linked WordPress partners are excluded.
    </p>

    <button
        type="button"
        wire:click="loadWordPressMeetingRoomPartnerLinking"
        wire:loading.attr="disabled"
        class="border rounded p-3 mt-3">
        Load partners for matching
    </button>

    <p
        wire:loading
        wire:target="loadWordPressMeetingRoomPartnerLinking"
        class="text-sm mt-2">
        Loading WordPress partners and available app partners...
    </p>

    {{-- Keep messages outside the token condition. --}}
    <div aria-live="polite">
        @if ($wpMrpLinkError)
            <p class="text-red-600 mt-3">
                {{ $wpMrpLinkError }}
            </p>
        @endif

        @if ($wpMrpLinkSuccess)
            <p class="text-green-700 mt-3">
                {{ $wpMrpLinkSuccess }}
            </p>
        @endif
    </div>

    @if ($wpMrpLinkToken)
        <p class="text-sm mt-3">
            {{ count($wpMrpLinkRows) }} unlinked WordPress partners.
            {{ count($wpMrpLinkOptions) }} available app partners.
            {{ $wpMrpAlreadyLinkedCount }} already linked.
            Matching preview expires after 15 minutes.
        </p>

        @if (count($wpMrpLinkOptions) === 0)
            <p class="text-sm text-gray-600 mt-2">
                No unlinked app partners are available.
                Do not create manual records just to populate this list.
                Missing partners will be handled by the creation workflow.
            </p>
        @else
            <div class="overflow-x-auto mt-3">
                <table class="w-full text-sm" border="1" cellpadding="8">
                    <thead>
                        <tr>
                            <th>WordPress partner</th>
                            <th>Existing app partner</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($wpMrpLinkRows as $row)
                            <tr wire:key="wp-mrp-link-{{ $row['wp_id'] }}">
                                <td class="align-top">
                                    <p>
                                        WP #{{ $row['wp_id'] }}
                                        — {{ $row['name'] }}
                                    </p>

                                    <p class="text-gray-500 mt-1">
                                        {{ $row['location'] ?: 'No location' }}
                                    </p>
                                </td>

                                <td class="align-top">
                                    <select
                                        wire:model="wpMrpLinkSelections.{{ $row['wp_id'] }}"
                                        wire:loading.attr="disabled"
                                        wire:target="saveWordPressMeetingRoomPartnerLinks"
                                        class="border rounded p-2 w-full">

                                        <option value="">
                                            Leave unmatched
                                        </option>

                                        @foreach ($wpMrpLinkOptions as $option)
                                            <option value="{{ $option['id'] }}">
                                                {{ $option['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <label class="block mt-4 text-sm">
                <input
                    type="checkbox"
                    wire:model="wpMrpLinkConfirmed"
                    wire:loading.attr="disabled"
                    wire:target="saveWordPressMeetingRoomPartnerLinks">

                I reviewed the selected matches.
                Save links only; do not change partner details.
            </label>

            <button
                type="button"
                wire:click="saveWordPressMeetingRoomPartnerLinks"
                wire:loading.attr="disabled"
                @if (!$wpMrpLinkConfirmed) disabled @endif
                class="bg-primaryColor text-white rounded p-3 mt-3">
                Save selected links
            </button>

            <p
                wire:loading
                wire:target="saveWordPressMeetingRoomPartnerLinks"
                class="text-sm mt-2">
                Rechecking WordPress and saving links...
            </p>
        @endif
    @endif
</div>