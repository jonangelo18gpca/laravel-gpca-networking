<div class="border border-primaryColor rounded-2xl py-5 px-7 mt-5">
    <h2 class="text-headingTextColor text-2xl font-bold">
        Session changes
    </h2>

    @if ($wpUpdateSuccess)
    <div class="mt-4 rounded-lg border border-green-300 bg-green-50 p-4 text-green-800">
        {{ $wpUpdateSuccess }}
    </div>
@endif

<p class="mt-2 text-sm text-gray-600">
    Fetch the selected WordPress agenda and compare it with linked
    app sessions. Unlinked records are left untouched.
    Descriptions are not compared in this version.
</p>

    <button
        type="button"
        wire:click="previewLinkedWordPressUpdates"
        wire:loading.attr="disabled"
        wire:target="previewLinkedWordPressUpdates"
        class="mt-4 bg-primaryColor hover:bg-primaryColorHover text-white rounded-lg px-5 py-2">
        Preview session changes
    </button>

    <p
        wire:loading
        wire:target="previewLinkedWordPressUpdates"
        class="mt-3 text-gray-600">
        Fetching and comparing linked sessions...
    </p>

    @if ($wpUpdatePreviewError)
        <p class="mt-4 text-red-600">
            {{ $wpUpdatePreviewError }}
        </p>
    @endif

    @if ($wpUpdatePreview)
        <div class="mt-4 space-y-1">
            <p>Would update: {{ $wpUpdatePreview['update'] }}</p>
            <p>No changes: {{ $wpUpdatePreview['unchanged'] }}</p>
            <p>Blocked: {{ $wpUpdatePreview['blocked'] }}</p>
            <p>
                Unlinked — left untouched:
                {{ $wpUpdatePreview['unlinked'] }}
            </p>
            <p>Create: 0</p>
        </div>

        <div class="mt-5 space-y-4">
            @forelse ($wpUpdatePreview['rows'] as $row)
                <div
                    class="border rounded-lg p-4"
                    wire:key="wp-update-preview-{{ $row['wordpress_id'] }}">
                    <h3 class="font-semibold">
                        WP #{{ $row['wordpress_id'] }}
                        → App #{{ $row['session_id'] }}
                    </h3>

                    <p class="mt-1">{{ $row['title'] }}</p>
                    <p class="mt-1">{{ $row['status'] }}</p>

                    @foreach ($row['issues'] as $issue)
                        <p class="mt-2 text-red-600">{{ $issue }}</p>
                    @endforeach

                    @if ($row['changes'])
                        <div class="overflow-x-auto mt-3">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b">
                                        <th class="p-2">Field</th>
                                        <th class="p-2">Current app value</th>
                                        <th class="p-2">WordPress value</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach ($row['changes'] as $change)
                                        <tr class="border-b">
                                            <td class="p-2">
                                                {{ $change['field'] }}
                                            </td>
                                            <td class="p-2">
                                                {{ $change['before'] }}
                                            </td>
                                            <td class="p-2">
                                                {{ $change['after'] }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                 @empty
                <p>No saved links found in the fetched source records.</p>
            @endforelse
        </div>

        @if (
            $wpUpdateToken &&
            $wpUpdatePreview['update'] > 0 &&
            $wpUpdatePreview['blocked'] === 0
        )
            <div class="mt-6 border-t pt-5">
                <p class="text-sm text-gray-600">
                    This preview expires after 15 minutes.
                    Applying will recheck WordPress and the current app values.
                </p>

                <label class="mt-4 flex items-start gap-3">
                    <input
                        type="checkbox"
                        wire:model="wpUpdateConfirmed"
                        class="mt-1">

                    <span>
                        I reviewed the changes above and approve updating
                        these linked sessions only.
                        No new sessions will be created.
                    </span>
                </label>

                <button
                    type="button"
                    wire:click="applyLinkedWordPressUpdates"
                    wire:loading.attr="disabled"
                    wire:target="applyLinkedWordPressUpdates,previewLinkedWordPressUpdates"
                    @if (!$wpUpdateConfirmed) disabled @endif
                    class="mt-4 rounded-lg bg-red-600 px-5 py-2 text-white disabled:opacity-50">
                    Apply linked updates
                </button>

                <p
                    wire:loading
                    wire:target="applyLinkedWordPressUpdates"
                    class="mt-3 text-gray-600">
                    Rechecking and applying the reviewed changes.
                    Please wait...
                </p>
            </div>
        @endif
    @endif
</div>