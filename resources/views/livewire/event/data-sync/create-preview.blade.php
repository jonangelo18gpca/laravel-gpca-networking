<div class="border border-primaryColor rounded-2xl py-5 px-7 mt-5">
    <h2 class="text-headingTextColor text-2xl font-bold">
        New sessions
    </h2>

    @if ($wpCreateSuccess)
        <div class="mt-4 rounded-lg border border-green-300 bg-green-50 p-4 text-green-800">
            {{ $wpCreateSuccess }}
        </div>
    @endif

    <p class="mt-2 text-sm text-gray-600">
        Review unlinked WordPress records for the selected feature.
        Possible duplicates need review.
        This preview does not create sessions.
    </p>

    <button type="button" wire:click="previewWordPressCreates" wire:loading.attr="disabled"
        wire:target="previewWordPressCreates,createReviewedWordPressSessions"
        class="mt-4 bg-primaryColor hover:bg-primaryColorHover text-white rounded-lg px-5 py-2">
        Preview new sessions
    </button>

    <p wire:loading wire:target="previewWordPressCreates" class="mt-3 text-gray-600">
        Fetching and checking unlinked records...
    </p>

    @if ($wpCreatePreviewError)
        <p class="mt-4 text-red-600">
            {{ $wpCreatePreviewError }}
        </p>
    @endif

    @if ($wpCreatePreview)
        @php
            $wpCreateSelectedCount = count(
                array_filter($wpCreateSelections, function ($checked) {
                    return $checked === true;
                }),
            );
        @endphp

        <div class="mt-4 space-y-1">
            <p>Ready to create: {{ $wpCreatePreview['ready'] }}</p>
            <p>Needs review: {{ $wpCreatePreview['review'] }}</p>
            <p>Already linked: {{ $wpCreatePreview['linked'] }}</p>
            <p>Selected for creation: {{ $wpCreateSelectedCount }}</p>
        </div>

        {{-- Global selection buttons: above the session cards. --}}
        @if ($wpCreateToken && $wpCreatePreview['ready'] > 0)
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <button type="button" wire:click="selectAllWordPressSessionCreation(true)" wire:loading.attr="disabled"
                    class="border rounded-lg px-4 py-2">
                    Select all eligible sessions
                </button>

                <button type="button" wire:click="selectAllWordPressSessionCreation(false)"
                    wire:loading.attr="disabled" class="border rounded-lg px-4 py-2">
                    Clear selections
                </button>
            </div>

            <p class="mt-2 text-sm text-gray-600">
                Only selected sessions will be created.
                Creation remains blocked while any preview record needs review.
            </p>
        @endif

        <div class="mt-5 space-y-4">
            @forelse ($wpCreatePreview['rows'] as $row)
                <div class="border rounded-lg p-4" wire:key="wp-create-preview-{{ $row['wordpress_id'] }}">

                    {{-- Individual selection: inside each eligible card. --}}
                    @if ($row['status'] === 'Ready to create' && empty($row['issues']))
                        <label class="mb-3 flex items-center gap-2">
                            <input type="checkbox" wire:model="wpCreateSelections.{{ $row['wordpress_id'] }}"
                                wire:loading.attr="disabled">

                            <span class="text-sm">
                                Select this session for creation
                            </span>
                        </label>
                    @endif

                    <h3 class="font-semibold">
                        WP #{{ $row['wordpress_id'] }}
                        — {{ $row['title'] }}
                    </h3>

                    <p class="mt-2">
                        {{ $row['category'] }} /
                        {{ $row['date'] }} /
                        {{ $row['day'] }} /
                        {{ $row['start_time'] }}–{{ $row['end_time'] }}
                    </p>

                    <p class="mt-2">{{ $row['status'] }}</p>

                    @foreach ($row['issues'] as $issue)
                        <p class="mt-2 text-red-600">
                            {{ $issue }}
                        </p>
                    @endforeach
                </div>
            @empty
                <p>No unlinked records returned for this feature.</p>
            @endforelse
        </div>

        @if ($wpCreateToken && $wpCreatePreview['ready'] > 0 && $wpCreatePreview['review'] === 0)
            <div class="mt-6 border-t pt-5">
                <p class="text-sm text-gray-600">
                    This plan expires after 15 minutes.
                    New sessions will be inactive.
                    Descriptions, session types, locations and sponsors
                    will be left empty.
                </p>

                <label class="mt-4 flex items-start gap-3">
                    <input type="checkbox" wire:model="wpCreateConfirmed" wire:loading.attr="disabled" class="mt-1">

                    <span>
                        I reviewed the selected records and approve creating
                        {{ $wpCreateSelectedCount }} inactive session(s)
                        and saving their WordPress links.
                    </span>
                </label>

                <button type="button" wire:click="createReviewedWordPressSessions" wire:loading.attr="disabled"
                    @if (!$wpCreateConfirmed || $wpCreateSelectedCount < 1) disabled @endif
                    class="mt-4 rounded-lg bg-red-600 px-5 py-2 text-white disabled:opacity-50">
                    Create {{ $wpCreateSelectedCount }} selected inactive session(s)
                </button>

                <p wire:loading wire:target="createReviewedWordPressSessions" class="mt-3 text-gray-600">
                    Rechecking and creating the selected sessions.
                    Please wait...
                </p>
            </div>
        @endif
    @endif
</div>
