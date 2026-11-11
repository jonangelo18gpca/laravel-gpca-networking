<div class="border rounded-xl p-4 mt-4">
    <h3 class="text-xl font-semibold">
        Create missing exhibitors
    </h3>

    <p class="text-sm text-gray-600 mt-2">
        Only unlinked WordPress exhibitors are shown.
        Possible existing app matches and similar WordPress names are blocked.
        Select only exhibitors you are certain are missing from the app.
    </p>

    <p class="text-sm text-gray-600 mt-2">
        Selected exhibitors will be created as active and linked automatically.
        Available logos are stored as external-URL media records.
        Missing or inaccessible logos are left empty.
        Existing exhibitors are not changed.
    </p>

    <button
        type="button"
        wire:click="loadWordPressExhibitorCreation"
        wire:loading.attr="disabled"
        class="border rounded p-3 mt-3"
    >
        Load missing exhibitors
    </button>

    <p
        wire:loading
        wire:target="loadWordPressExhibitorCreation"
        class="text-sm mt-2"
    >
        Loading WordPress exhibitors and checking possible duplicates…
    </p>

    <div aria-live="polite">
        @if ($wpExhibitorCreateError)
            <p class="text-red-600 mt-3">
                {{ $wpExhibitorCreateError }}
            </p>
        @endif

        @if ($wpExhibitorCreateSuccess)
            <p class="text-green-700 mt-3">
                {{ $wpExhibitorCreateSuccess }}
            </p>
        @endif
    </div>

    @if ($wpExhibitorCreateLoaded)
        @if (count($wpExhibitorCreateRows) === 0)
            <p class="text-sm text-gray-600 mt-3">
                No unlinked WordPress exhibitors were found.
            </p>
        @else
            <p class="text-sm mt-3">
                {{ count($wpExhibitorCreateRows) }}
                unlinked WordPress exhibitor(s).
                {{ $wpExhibitorCreateEligibleCount }}
                eligible for creation.
            </p>

            <p class="text-sm text-gray-600 mt-1">
                Eligible does not guarantee that a record is new.
                Duplicate checks ignore case, punctuation and spacing,
                but cannot identify every spelling or naming variation.
                Review the app before creating.
            </p>

            @if ($wpExhibitorCreateToken)
                <p class="text-sm text-gray-600 mt-1">
                    This creation preview expires after 15 minutes.
                </p>
            @endif

            @if ($wpExhibitorCreateToken && $wpExhibitorCreateEligibleCount > 0)
    <div class="flex flex-wrap gap-2 mt-3">
        <button
            type="button"
            wire:click="selectAllWordPressExhibitorCreation(true)"
            wire:loading.attr="disabled"
            class="border rounded px-3 py-2 text-sm"
        >
            Select all eligible exhibitors
        </button>

        <button
            type="button"
            wire:click="selectAllWordPressExhibitorCreation(false)"
            wire:loading.attr="disabled"
            class="border rounded px-3 py-2 text-sm"
        >
            Clear all selections
        </button>
    </div>
@endif


            @foreach ($wpExhibitorCreateRows as $row)
                <div
                    wire:key="wp-exhibitor-create-{{ $row['wp_id'] }}"
                    class="border rounded-lg p-3 mt-4"
                >
                    <div class="flex items-start gap-3">
                        @if ($row['create_allowed'])
                            <input
                                type="checkbox"
                                wire:model="wpExhibitorCreateSelected.wp_{{ $row['wp_id'] }}"
                                wire:loading.attr="disabled"
                                class="mt-1"
                                aria-label="Create {{ $row['name'] }}"
                            >
                        @else
                            <input
                                type="checkbox"
                                disabled
                                class="mt-1"
                                aria-label="Creation blocked for {{ $row['name'] }}"
                            >
                        @endif

                        <div class="min-w-0 flex-1">
                            <h4 class="font-semibold">
                                WP #{{ $row['wp_id'] }}
                                — {{ $row['name'] }}
                            </h4>

                            <p class="text-sm text-gray-600 mt-1">
                                Stand:
                                {{ $row['stand_number'] ?: 'Empty' }}
                            </p>

                            <p class="text-sm text-gray-600 mt-1 break-words">
                                Website:
                                {{ $row['website'] ?: 'Empty' }}
                            </p>

                            <p class="text-sm text-gray-600 mt-1">
                                Logo status:
                                {{ $row['logo_status'] }}
                            </p>

                            @if (!empty($row['logo_message']))
                                <p class="text-sm text-gray-500 mt-1">
                                    {{ $row['logo_message'] }}
                                </p>
                            @endif

                            @if ($row['create_allowed'])
                                <p class="text-sm text-green-700 mt-2">
                                    Eligible for creation — review before selecting.
                                </p>
                            @else
                                <div class="mt-2 text-sm text-red-600">
                                    <p class="font-semibold">
                                        Creation blocked
                                    </p>

                                    <ul class="list-disc pl-5 mt-1 space-y-1">
                                        @foreach ($row['create_block_reasons'] as $reason)
                                            <li>{{ $reason }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if (!empty($row['warnings']))
                                <ul class="list-disc pl-5 mt-2 text-sm text-yellow-800">
                                    @foreach ($row['warnings'] as $warning)
                                        <li>{{ $warning }}</li>
                                    @endforeach
                                </ul>
                            @endif

                            <details wire:ignore.self class="mt-3">
                                <summary class="cursor-pointer text-sm text-gray-600">
                                    Review source description
                                </summary>

                                <div class="mt-2 max-h-56 overflow-auto whitespace-pre-wrap break-words text-sm">{{ $row['profile_text'] === '' ? '(Empty)' : $row['profile_text'] }}</div>
                            </details>
                        </div>
                    </div>
                </div>
            @endforeach

            @if ($wpExhibitorCreateToken && $wpExhibitorCreateEligibleCount > 0)
                <label class="block mt-4 text-sm">
                    <input
                        type="checkbox"
                        wire:model="wpExhibitorCreateConfirmed"
                        wire:loading.attr="disabled"
                    >

                    I checked the selected exhibitors are missing from the app.
                    Create them as active and save their WordPress links.
                </label>

                <button
                    type="button"
                    wire:click="createSelectedWordPressExhibitors"
                    wire:loading.attr="disabled"
                    @if (!$wpExhibitorCreateConfirmed) disabled @endif
                    class="bg-primaryColor text-white rounded p-3 mt-3 disabled:opacity-50"
                >
                    Create selected exhibitors
                </button>

                <p
                    wire:loading
                    wire:target="createSelectedWordPressExhibitors"
                    class="text-sm mt-2"
                >
                    Rechecking WordPress and creating selected exhibitors…
                </p>
            @else
                <p class="text-sm text-gray-600 mt-3">
                    No exhibitors are currently eligible for creation.
                    Review the blocking reasons above.
                    Use matching for records that already exist in the app.
                </p>
            @endif
        @endif
    @endif
</div>