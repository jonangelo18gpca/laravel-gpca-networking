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
        Compare the selected WordPress agenda with linked app sessions.
        Only sessions with changes are listed.
        Select the sessions you want to update.
    </p>

    <p class="mt-2 text-sm text-gray-600">
        Selecting a session applies all changes listed inside its box.
        Unchecked sessions and unlinked records remain untouched.
        Descriptions are not compared or updated in this version.
    </p>

    <button
        type="button"
        wire:click="previewLinkedWordPressUpdates"
        wire:loading.attr="disabled"
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
        @php
            $wpSessionUpdateSelectedCount = count(array_filter(
                $wpUpdateSelections,
                function ($checked) {
                    return $checked === true;
                }
            ));

            $wpSessionUpdateLabels = [
                'title' => 'Title',
                'session_date' => 'Date',
                'session_day' => 'Day',
                'start_time' => 'Start time',
                'end_time' => 'End time',
            ];
        @endphp

        <div class="mt-4 space-y-1">
            <p>
                Sessions with changes:
                {{ count($wpUpdatePreview['rows']) }}
            </p>

            <p>
                Selected for update:
                {{ $wpSessionUpdateSelectedCount }}
            </p>

            <p>
                Unchanged — hidden:
                {{ $wpUpdatePreview['unchanged'] }}
            </p>

            <p>
                Blocked:
                {{ $wpUpdatePreview['blocked'] }}
            </p>

            <p>
                Unlinked — left untouched:
                {{ $wpUpdatePreview['unlinked'] }}
            </p>
        </div>

        @if ($wpUpdatePreview['blocked'] > 0)
            <div class="mt-4 rounded-lg border border-red-300 bg-red-50 p-4 text-red-700">
                Updates are blocked because
                {{ $wpUpdatePreview['blocked'] }}
                linked record(s) need attention.
                Resolve those records and generate a fresh preview.
                Selecting sessions does not bypass this safety check.
            </div>
        @endif

        {{-- Global selection controls. --}}
        @if ($wpUpdateToken && count($wpUpdatePreview['rows']))
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    wire:click="selectAllWordPressSessionUpdates(true)"
                    wire:loading.attr="disabled"
                    class="border rounded-lg px-4 py-2">
                    Select all sessions
                </button>

                <button
                    type="button"
                    wire:click="selectAllWordPressSessionUpdates(false)"
                    wire:loading.attr="disabled"
                    class="border rounded-lg px-4 py-2">
                    Clear selections
                </button>
            </div>

            <p class="mt-2 text-sm text-gray-600">
                All session checkboxes start unchecked.
                Selection does not save any changes.
            </p>
        @endif

        <div class="mt-5 space-y-4">
            @forelse ($wpUpdatePreview['rows'] as $row)
                <div
                    class="border rounded-lg p-4"
                    wire:key="wp-update-preview-{{ $row['wordpress_id'] }}">

                    {{-- Individual session selection. --}}
                    <label class="mb-3 flex items-start gap-3">
                        <input
                            type="checkbox"
                            wire:model="wpUpdateSelections.session_{{ $row['session_id'] }}"
                            wire:loading.attr="disabled"
                            class="mt-1">

                        <span class="font-semibold">
                            Select this session for update
                        </span>
                    </label>

                    <h3 class="font-semibold">
                        WP #{{ $row['wordpress_id'] }}
                        → App #{{ $row['session_id'] }}
                    </h3>

                    <p class="mt-1">
                        {{ $row['title'] }}
                    </p>

                    @foreach ($row['issues'] as $issue)
                        <p class="mt-2 text-red-600">
                            {{ $issue }}
                        </p>
                    @endforeach

                    <div class="overflow-x-auto mt-3">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="p-2">Changed field</th>
                                    <th class="p-2">Current app value</th>
                                    <th class="p-2">WordPress value</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($row['changes'] as $change)
                                    <tr class="border-b">
                                        <td class="p-2 align-top">
                                            {{ $wpSessionUpdateLabels[$change['field']] ?? $change['field'] }}
                                        </td>

                                        <td class="p-2 align-top whitespace-pre-wrap break-words">{{ $change['before'] === null || $change['before'] === '' ? '(empty)' : $change['before'] }}</td>

                                        <td class="p-2 align-top whitespace-pre-wrap break-words">{{ $change['after'] === null || $change['after'] === '' ? '(empty)' : $change['after'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <p class="text-gray-600">
                    No linked sessions with actionable changes were found.
                    Unchanged sessions are hidden.
                </p>
            @endforelse
        </div>

        @if (
            $wpUpdateToken &&
            count($wpUpdatePreview['rows']) > 0 &&
            $wpUpdatePreview['blocked'] === 0
        )
            <div class="mt-6 border-t pt-5">
                <p class="text-sm text-gray-600">
                    This preview expires after 15 minutes.
                    Applying will recheck WordPress and current app values.
                    Only selected sessions will be updated.
                </p>

                <label class="mt-4 flex items-start gap-3">
                    <input
                        type="checkbox"
                        wire:model="wpUpdateConfirmed"
                        wire:loading.attr="disabled"
                        class="mt-1">

                    <span>
                        I reviewed the listed changes and approve updating
                        {{ $wpSessionUpdateSelectedCount }}
                        selected session(s).
                        All listed changes for each selected session
                        will be applied.
                        No new sessions will be created.
                    </span>
                </label>

                <button
                    type="button"
                    wire:click="applyLinkedWordPressUpdates"
                    wire:loading.attr="disabled"
                    @if (
                        !$wpUpdateConfirmed ||
                        $wpSessionUpdateSelectedCount < 1
                    ) disabled @endif
                    class="mt-4 rounded-lg bg-red-600 px-5 py-2 text-white disabled:opacity-50">
                    Update {{ $wpSessionUpdateSelectedCount }} selected session(s)
                </button>

                <p
                    wire:loading
                    wire:target="applyLinkedWordPressUpdates"
                    class="mt-3 text-gray-600">
                    Rechecking and updating the selected sessions.
                    Please wait...
                </p>
            </div>
        @endif
    @endif
</div>