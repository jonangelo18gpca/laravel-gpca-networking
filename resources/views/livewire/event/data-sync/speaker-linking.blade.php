<div class="border rounded-xl p-4 mt-4">
    <h3 class="text-xl font-semibold">
        Match existing app speakers
    </h3>

    <p class="text-sm text-gray-600 mt-2">
        Choose matches only when certain. Only speakers from the
        selected app category are listed. Blank means leave unlinked.
        Saving creates links only; it does not update speaker profiles
        or session assignments.
    </p>
    <button type="button" wire:click="loadWordPressSpeakerLinking" wire:loading.attr="disabled"
        class="border rounded p-3 mt-3">
        Load existing speakers for matching
    </button>

    <p wire:loading wire:target="loadWordPressSpeakerLinking" class="mt-2">
        Loading eligible WordPress speakers and app choices...
    </p>

    @if ($wpSpeakerLinkError)
        <p class="text-red-600 mt-3">
            {{ $wpSpeakerLinkError }}
        </p>
    @endif

    @if ($wpSpeakerLinkSuccess)
        <p class="text-green-700 mt-3">
            {{ $wpSpeakerLinkSuccess }}
        </p>
    @endif

    @if ($wpSpeakerLinkToken)
        <p class="text-sm mt-3">
            {{ count($wpSpeakerLinkRows) }} eligible WordPress speakers.
            {{ count($wpSpeakerLinkOptions) }} unlinked app speakers
            available in this category.
            Temporary plan expires after 15 minutes.
        </p>

        @if (!count($wpSpeakerLinkOptions))
            <p class="text-sm mt-2">
                No unlinked app speakers are available in this category.
                Leave unmatched rows blank. Do not create manual records
                just to populate these choices.
            </p>
        @endif

        <div class="overflow-x-auto mt-3">
            <table class="w-full" border="1" cellpadding="8">
                <thead>
                    <tr>
                        <th>WordPress speaker</th>
                        <th>Existing app speaker</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($wpSpeakerLinkRows as $row)
                        <tr wire:key="wp-speaker-link-{{ $wpSpeakerSource }}-{{ $row['wp_id'] }}">
                            <td class="align-top">
                                <p>
                                    WP #{{ $row['wp_id'] }}
                                    — {{ $row['name'] }}
                                </p>

                                <p class="text-sm mt-1">
                                    {{ $row['job_title'] }}
                                </p>

                                <p class="text-sm">
                                    {{ $row['company_name'] }}
                                </p>

                                <p class="text-sm mt-1">
                                    {{ $row['role'] }}
                                </p>
                            </td>

                            <td class="align-top">
                                @if (isset($wpSpeakerLinkExisting[$row['wp_id']]))
                                    <p>
                                        App
                                        #{{ $wpSpeakerLinkExisting[$row['wp_id']]['id'] }}
                                        —
                                        {{ $wpSpeakerLinkExisting[$row['wp_id']]['name'] }}
                                    </p>

                                    <p class="text-sm text-green-700 mt-1">
                                        Saved link
                                    </p>
                                @else
                                    <select wire:model="wpSpeakerLinkSelections.{{ $row['wp_id'] }}"
                                        class="border rounded p-2 w-full">
                                        <option value="">Leave unlinked</option>

                                        @foreach ($wpSpeakerLinkOptions as $option)
                                            <option value="{{ $option['id'] }}">
                                                {{ $option['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <label class="block mt-4 text-sm">
            <input type="checkbox" wire:model="wpSpeakerLinkConfirmed">

            I checked the selected WordPress-to-app matches.
            Save links only; do not update speaker details.
        </label>

        <button type="button" wire:click="saveWordPressSpeakerLinks" wire:loading.attr="disabled"
            @if (!$wpSpeakerLinkConfirmed) disabled @endif class="bg-primaryColor text-white rounded p-3 mt-3">
            Save selected links only
        </button>

        <p wire:loading wire:target="saveWordPressSpeakerLinks" class="mt-2">
            Rechecking WordPress and saving selected links...
        </p>
    @endif
</div>
