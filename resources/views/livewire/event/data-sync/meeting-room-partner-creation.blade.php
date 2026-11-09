<div class="border rounded-xl p-4 mt-4">
    <h3 class="text-xl font-semibold">
        Create missing partners
    </h3>

    <p class="text-sm text-gray-600 mt-2">
        Only unlinked WordPress partners are shown.
        Possible duplicates and invalid records cannot be selected.
        New partners will be active.
    </p>

    <p class="text-sm text-gray-600 mt-2">
        This step imports name, location, profile and website only.
        Logos and banners are not imported.
        Existing app partners are not changed.
    </p>

    <button type="button" wire:click="loadWordPressMeetingRoomPartnerCreation" wire:loading.attr="disabled"
        class="border rounded p-3 mt-3">
        Load creation preview
    </button>

    <p wire:loading wire:target="loadWordPressMeetingRoomPartnerCreation" class="text-sm mt-2">
        Loading WordPress partners and checking possible duplicates...
    </p>

    {{-- Keep messages visible after the preview is cleared. --}}
    <div aria-live="polite">
        @if ($wpMrpCreateError)
            <p class="text-red-600 mt-3">
                {{ $wpMrpCreateError }}
            </p>
        @endif

        @if ($wpMrpCreateSuccess)
            <p class="text-green-700 mt-3">
                {{ $wpMrpCreateSuccess }}
            </p>
        @endif
    </div>

    @if (count($wpMrpCreateRows))
        <p class="text-sm mt-3">
            {{ count($wpMrpCreateRows) }} unlinked WordPress partners.
            {{ $wpMrpCreateEligibleCount }} eligible for creation.
        </p>

        <p class="text-sm text-gray-600 mt-1">
            Eligible previews expire after 15 minutes.
            All selection boxes start unchecked.
        </p>

        <div class="overflow-x-auto mt-3">
            <table class="w-full text-sm" border="1" cellpadding="8">
                <thead>
                    <tr>
                        <th>Select</th>
                        <th>Partner</th>
                        <th>Fields to create</th>
                        <th>Source logo</th>
                        <th>Review</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($wpMrpCreateRows as $row)
                        <tr wire:key="wp-mrp-create-{{ $row['wp_id'] }}">
                            <td class="align-top">
                                @if ($row['create_allowed'])
                                    <input type="checkbox" value="{{ $row['wp_id'] }}" wire:model="wpMrpCreateSelected"
                                        wire:loading.attr="disabled"
                                        wire:target="createSelectedWordPressMeetingRoomPartners"
                                        aria-label="Create {{ $row['name'] }}">
                                @else
                                    <span class="text-gray-500">
                                        Blocked
                                    </span>
                                @endif
                            </td>

                            <td class="align-top">
                                <p>WP #{{ $row['wp_id'] }}</p>
                                <p class="font-medium mt-1">
                                    {{ $row['name'] }}
                                </p>
                            </td>

                            <td class="align-top">
                                <p>
                                    Location:
                                    {{ $row['location'] ?: 'Empty' }}
                                </p>

                                <p class="mt-2 break-all">
                                    Website:
                                    {{ $row['website'] ?: 'Empty' }}
                                </p>

                                <details class="mt-2">
                                    <summary class="cursor-pointer">
                                        Profile text
                                    </summary>

                                    {{-- Never render source content as raw HTML. --}}
                                    <div class="whitespace-pre-wrap mt-2">{{ $row['profile_text'] ?: 'Empty' }}</div>
                                </details>
                            </td>

                            <td class="align-top">
                                @if ($row['logo_status'] === 'available' && $row['logo_url'])
                                    <a href="{{ $row['logo_url'] }}" target="_blank" rel="noopener noreferrer"
                                        class="underline break-all">
                                        {{ $row['logo_url'] }}
                                    </a>
                                @else
                                    <p>
                                        {{ $row['logo_message'] ?: 'No accessible source logo.' }}
                                    </p>
                                @endif

                                <p class="text-gray-500 mt-2">
                                    @if ($row['logo_status'] === 'available' && $row['logo_url'])
                                        This URL will be saved as the app logo if this partner is created.
                                    @else
                                        This partner will be created without a logo.
                                    @endif
                                </p>
                            </td>

                            <td class="align-top">
                                @foreach ($row['create_block_reasons'] as $reason)
                                    <p class="text-red-600 mb-2">
                                        {{ $reason }}
                                    </p>
                                @endforeach

                                @foreach ($row['warnings'] as $warning)
                                    <p class="text-gray-600 mb-2">
                                        {{ $warning }}
                                    </p>
                                @endforeach

                                @if ($row['create_allowed'])
                                    <p class="text-green-700">
                                        Eligible for creation.
                                        Review before selecting.
                                    </p>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($wpMrpCreateToken && $wpMrpCreateEligibleCount)
        <p class="text-sm mt-4">
            Selected: {{ count($wpMrpCreateSelected) }}
        </p>

        <label class="block mt-3 text-sm">
            <input type="checkbox" wire:model="wpMrpCreateConfirmed" wire:loading.attr="disabled"
                wire:target="createSelectedWordPressMeetingRoomPartners">

            I reviewed the selected partners.
            Create them as active records without logos or banners,
            and save their WordPress links.
        </label>

        <button type="button" wire:click="createSelectedWordPressMeetingRoomPartners" wire:loading.attr="disabled"
            @if (!$wpMrpCreateConfirmed || count($wpMrpCreateSelected) === 0) disabled @endif class="bg-primaryColor text-white rounded p-3 mt-3">
            Create selected partners
        </button>

        <p wire:loading wire:target="createSelectedWordPressMeetingRoomPartners" class="text-sm mt-2">
            Rechecking WordPress and creating selected partners...
        </p>
    @endif
</div>
