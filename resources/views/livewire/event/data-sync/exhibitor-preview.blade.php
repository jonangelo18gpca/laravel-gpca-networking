<div class="border rounded-xl p-4 mt-4">
    <h3 class="text-xl font-semibold">
        WordPress exhibitor source preview
    </h3>

    <p class="text-sm text-gray-600 mt-2">
        Reads the website saved in WordPress General Settings.
        Previewing does not create, link or update exhibitors.
        Logo URLs are displayed as text; images are not downloaded.
    </p>

    <button
        type="button"
        wire:click="loadWordPressExhibitorPreview"
        wire:loading.attr="disabled"
        class="border rounded p-3 mt-3">
        Load exhibitor preview
    </button>

    <p
        wire:loading
        wire:target="loadWordPressExhibitorPreview"
        class="text-sm mt-2">
        Fetching exhibitors and logo metadata...
    </p>

    @if ($wpExhibitorPreviewError)
        <p class="text-red-600 mt-3">
            {{ $wpExhibitorPreviewError }}
        </p>
    @endif

    @if ($wpExhibitorPreviewLoaded)
        <p class="text-sm mt-3">
            {{ count($wpExhibitorPreviewRows) }}
            WordPress exhibitor record(s) fetched.
        </p>

        @forelse ($wpExhibitorPreviewRows as $row)
            <div
                class="border rounded-lg p-3 mt-3"
                wire:key="wp-exhibitor-source-{{ $row['wp_id'] }}">

                <h4 class="font-semibold">
                    WP #{{ $row['wp_id'] }} — {{ $row['name'] }}
                </h4>

                <p class="text-sm mt-2">
                    Stand: {{ $row['stand_number'] ?: 'Empty' }}
                </p>

                <p class="text-sm mt-2 break-all">
                    Website: {{ $row['website'] ?: 'Empty' }}
                </p>

                <details wire:ignore.self class="mt-2">
                    <summary class="cursor-pointer text-sm">
                        Profile text
                    </summary>

                    <div class="text-sm whitespace-pre-wrap mt-2">{{ $row['profile_text'] ?: 'Empty' }}</div>
                </details>

                <p class="text-sm mt-2">
                    Logo status: {{ $row['logo_status'] }}
                </p>

                @if ($row['logo_url'])
                    <p class="text-sm mt-1 break-all">
                        {{ $row['logo_url'] }}
                    </p>
                @elseif ($row['logo_message'])
                    <p class="text-sm text-gray-600 mt-1">
                        {{ $row['logo_message'] }}
                    </p>
                @endif

                @foreach ($row['issues'] as $issue)
                    <p class="text-sm text-red-600 mt-2">
                        {{ $issue }}
                    </p>
                @endforeach

                @foreach ($row['warnings'] as $warning)
                    <p class="text-sm text-gray-600 mt-2">
                        {{ $warning }}
                    </p>
                @endforeach
            </div>
        @empty
            <p class="text-sm mt-3">
                No exhibitors were returned.
            </p>
        @endforelse
    @endif
</div>