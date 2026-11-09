<div class="border rounded-xl p-4 mt-4">
    <h3 class="text-xl font-semibold">
        Meeting room partners — WordPress preview
    </h3>

    <p class="text-sm text-gray-600 mt-2">
        Uses the website saved in WordPress General Settings.
        Published meeting-room-partner records are previewed.
        No year filter is applied because this source has no year field.
        No app records are created or updated.
    </p>

    <button type="button" wire:click="loadWordPressMeetingRoomPartnerPreview" wire:loading.attr="disabled"
        wire:target="loadWordPressMeetingRoomPartnerPreview" class="border rounded p-3 mt-3">
        Load meeting room partner preview
    </button>

    <p wire:loading wire:target="loadWordPressMeetingRoomPartnerPreview" class="text-sm text-gray-500 mt-2">
        Fetching partners and resolving logos...
    </p>

    @if ($wpMrpError)
        <p class="text-red-600 mt-3">
            {{ $wpMrpError }}
        </p>
    @endif

    @if ($wpMrpLoaded)
        <p class="text-sm mt-3">
            {{ count($wpMrpRows) }} published partner records loaded.
        </p>

        @if (count($wpMrpRows) === 0)
            <p class="text-sm mt-2">
                No published meeting room partners were returned.
            </p>
        @else
            <div class="overflow-x-auto mt-3">
                <table class="w-full text-sm" border="1" cellpadding="8">
                    <thead>
                        <tr>
                            <th>WordPress partner</th>
                            <th>App location</th>
                            <th>Website</th>
                            <th>Logo Link</th>
                            <th>Profile / review</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($wpMrpRows as $row)
                            <tr wire:key="wp-mrp-preview-{{ $row['wp_id'] }}">
                                <td class="align-top">
                                    <p>WP #{{ $row['wp_id'] }}</p>
                                    <p class="font-medium mt-1">
                                        {{ $row['name'] }}
                                    </p>
                                </td>

                                <td class="align-top">
                                    {{ $row['location'] ?: 'No location' }}
                                </td>

                                <td class="align-top break-all">
                                    {{ $row['website'] ?: 'No website' }}
                                </td>
                                <td class="align-top">
                                    @if (($row['logo_status'] ?? '') === 'available')
                                        <a href="{{ $row['logo_url'] }}" target="_blank" rel="noopener noreferrer"
                                            class="text-blue-700 hover:underline break-all">
                                            {{ $row['logo_url'] }}
                                        </a>

                                        <p class="text-xs text-gray-500 mt-2">
                                            WP media #{{ $row['logo_attachment_id'] }}
                                        </p>
                                    @elseif (($row['logo_status'] ?? '') === 'denied')
                                        <p class="text-yellow-700">
                                            Logo unavailable — WordPress denied access.
                                        </p>

                                        <p class="text-xs text-gray-500 mt-2">
                                            WP media #{{ $row['logo_attachment_id'] }}
                                        </p>

                                        <p class="text-xs text-gray-500 mt-2">
                                            Preserve the existing app logo.
                                        </p>
                                    @elseif (($row['logo_status'] ?? '') === 'invalid')
                                        <p class="text-red-600">
                                            Invalid source logo reference.
                                        </p>
                                    @else
                                        <p class="text-gray-500">
                                            No source logo.
                                        </p>
                                    @endif
                                </td>

                                <td class="align-top">
                                    @foreach ($row['issues'] as $issue)
                                        <p class="text-red-600 mb-2">
                                            {{ $issue }}
                                        </p>
                                    @endforeach

                                    @foreach ($row['warnings'] as $warning)
                                        <p class="text-yellow-700 mb-2">
                                            {{ $warning }}
                                        </p>
                                    @endforeach

                                    <details>
                                        <summary class="cursor-pointer">
                                            View company profile
                                        </summary>

                                        <p class="whitespace-pre-line mt-2">
                                            {{ $row['profile_text'] }}
                                        </p>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
