<div class="border rounded-xl p-4 mt-4">
    <h3 class="text-xl font-semibold">
        Match existing app exhibitors
    </h3>

    <p class="text-sm text-gray-600 mt-2">
        Match only when certain.
        Saving creates WordPress-to-app links only.
        It does not update exhibitor details, logos or ordering.
        Already-linked WordPress exhibitors are excluded.
    </p>

    <button type="button" wire:click="loadWordPressExhibitorLinking" wire:loading.attr="disabled"
        class="border rounded p-3 mt-3">
        Load exhibitors for matching
    </button>

    <p wire:loading wire:target="loadWordPressExhibitorLinking" class="text-sm mt-2">
        Loading WordPress exhibitors and available app exhibitors...
    </p>

    <div aria-live="polite">
        @if ($wpExhibitorLinkError)
            <p class="text-red-600 mt-3">
                {{ $wpExhibitorLinkError }}
            </p>
        @endif

        @if ($wpExhibitorLinkSuccess)
            <p class="text-green-700 mt-3">
                {{ $wpExhibitorLinkSuccess }}
            </p>
        @endif
    </div>

    @if ($wpExhibitorLinkToken)
        <p class="text-sm mt-3">
            {{ count($wpExhibitorLinkRows) }} unlinked WordPress exhibitors.
            {{ count($wpExhibitorLinkOptions) }} available app exhibitors.
            {{ $wpExhibitorAlreadyLinkedCount }} already linked.
        </p>

        <p class="text-sm text-gray-600 mt-1">
            Matching preview expires after 15 minutes.
            Logo metadata is not requested for matching.
        </p>

        @if (count($wpExhibitorLinkOptions) === 0)
            <p class="text-sm text-gray-600 mt-3">
                No unlinked app exhibitors are available.
                Do not manually create records just to populate this list.
                Missing exhibitors will be handled by the creation workflow.
            </p>
        @else
            <div class="overflow-x-auto mt-3">
                <table class="w-full text-sm" border="1" cellpadding="8">

                    <thead>
                        <tr>
                            <th>WordPress exhibitor</th>
                            <th>Existing app exhibitor</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($wpExhibitorLinkRows as $row)
                            <tr wire:key="wp-exhibitor-link-{{ $row['wp_id'] }}">
                                <td class="align-top">
                                    <p>
                                        WP #{{ $row['wp_id'] }}
                                        — {{ $row['name'] }}
                                    </p>

                                    <p class="text-gray-500 mt-1">
                                        Stand:
                                        {{ $row['stand_number'] ?: 'Empty' }}
                                    </p>
                                </td>

                                <td class="align-top">
                                    <select wire:model="wpExhibitorLinkSelections.wp_{{ $row['wp_id'] }}"
                                        wire:loading.attr="disabled" class="border rounded p-2 w-full">

                                        <option value="">
                                            Leave unmatched
                                        </option>

                                        @foreach ($wpExhibitorLinkOptions as $option)
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
                <input type="checkbox" wire:model="wpExhibitorLinkConfirmed" wire:loading.attr="disabled">

                I reviewed the selected matches.
                Save links only; do not change exhibitor details.
            </label>

            <button type="button" wire:click="saveWordPressExhibitorLinks" wire:loading.attr="disabled"
                @if (!$wpExhibitorLinkConfirmed) disabled @endif
                class="bg-primaryColor text-white rounded p-3 mt-3 disabled:opacity-50">
                Save selected links
            </button>

            <p wire:loading wire:target="saveWordPressExhibitorLinks" class="text-sm mt-2">
                Rechecking WordPress and saving exhibitor links...
            </p>
        @endif
    @endif
</div>
