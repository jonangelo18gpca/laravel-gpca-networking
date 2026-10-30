<div class="border border-primaryColor rounded-2xl py-5 px-7 mt-5">
    <h2 class="text-2xl font-bold">Link Existing App Sessions</h2>
    <p class="mt-2">Select a feature in WordPress Agenda Preview first, then load this list. Choose the matching existing app session only where you are certain. Blank means leave unlinked; it does not create or skip a future import automatically.</p>
    <button type="button" wire:click="loadWordPressAgendaLinking" wire:loading.attr="disabled" class="border rounded p-3 mt-3">Load sessions for linking</button>
    <p wire:loading wire:target="loadWordPressAgendaLinking">Fetching selected WordPress agenda...</p>
    @if ($wpLinkError)<p class="text-red-600 mt-3">{{ $wpLinkError }}</p>@endif
    @if ($wpLinkSuccess)<p class="text-green-700 mt-3">{{ $wpLinkSuccess }}</p>@endif
    @if ($wpLinkToken)
        <p class="mt-3">{{ count($wpLinkRows) }} WordPress records; {{ count($wpLinkOptions) }} existing app sessions in this category. List expires after 15 minutes. No automatic matching.</p>
        @if (!count($wpLinkOptions))<p>No existing app sessions for this feature. Nothing needs linking; do not create manual sessions just to supply IDs.</p>@endif
        <div class="overflow-x-auto mt-3">
            <table border="1" cellpadding="8" class="w-full">
                <thead><tr><th>WordPress session</th><th>Existing app session</th><th>Status</th></tr></thead>
                <tbody>
                @foreach ($wpLinkRows as $row)
                    <tr wire:key="wp-link-row-{{ $row['wp_id'] }}">
                        <td>WP #{{ $row['wp_id'] }}<br>{{ $row['title'] }}<br>{{ $row['date'] }} / {{ $row['day'] }} / {{ $row['time'] }}</td>
                        <td>
                            @if (isset($wpLinkExisting[$row['wp_id']]))
                                App #{{ $wpLinkExisting[$row['wp_id']] }} — saved link
                            @else
                                <select wire:model="wpLinkSelections.{{ $row['wp_id'] }}" class="border rounded p-2 w-full">
                                    <option value="">Leave unlinked</option>
                                    @foreach ($wpLinkOptions as $option)
                                        <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </td>
                        <td>@if (isset($wpLinkExisting[$row['wp_id']])) Linked @else Not linked yet @endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <label class="block mt-4"><input type="checkbox" wire:model="wpLinkConfirmed"> I checked the selected WordPress-to-app matches. Save links only; do not update session details.</label>
        <button type="button" wire:click="saveWordPressAgendaLinks" wire:loading.attr="disabled" @if (!$wpLinkConfirmed) disabled @endif class="bg-primaryColor text-white rounded p-3 mt-3">Save selected links only</button>
    @endif
</div>
