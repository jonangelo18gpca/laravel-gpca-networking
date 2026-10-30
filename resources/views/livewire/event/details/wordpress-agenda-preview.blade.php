<div class="border border-primaryColor rounded-2xl py-5 px-7 mt-5">
    <h2 class="text-2xl font-bold">WordPress Agenda Preview</h2>
    <p class="mt-2">Reads saved settings only. Save settings above before previewing. No sessions are created, linked
        or updated.</p>
    <div class="mt-4">
        <label class="block font-bold mb-2">
            Select agenda feature
        </label>

        <select wire:model="wpPreviewPostType" wire:change="clearWordPressAgendaPreview" wire:loading.attr="disabled"
            class="border rounded p-2 w-full">
            <option value="">Select a feature</option>

            @foreach ($this->wpPreviewSourceChoices as $choice)
                <option value="{{ $choice['post_type'] }}">
                    {{ $choice['label'] }}
                </option>
            @endforeach
        </select>
    </div>
    <button type="button" wire:click="previewWordPressAgenda" wire:loading.attr="disabled"
        class="bg-primaryColor text-white rounded p-3 mt-3">Fetch agenda preview</button>
    <button type="button" wire:click="clearWordPressAgendaPreview" wire:loading.attr="disabled"
        class="border rounded p-3 mt-3">Clear preview</button>
    <p wire:loading wire:target="previewWordPressAgenda">Fetching WordPress agenda pages. Please wait...</p>
    @if ($wpAgendaPreviewError)
        <p class="text-red-600 mt-3">{{ $wpAgendaPreviewError }}</p>
    @endif
    @if ($wpAgendaPreviewLoaded)
        <p class="mt-3">{{ count($wpAgendaPreviewRows) }} sessions for the configured year.
            {{ $wpAgendaPreviewSkipped }} other-year records skipped.
            {{ $wpAgendaPreviewIssues }} sessions need review.</p>
        <p>This is a source-data preview, not an update/create plan. Existing app sessions have not been matched yet.
        </p>
        @foreach ($wpAgendaPreviewSources as $source)
            <p class="mt-2">{{ $source['category'] }}: {{ $source['count'] }} sessions<br>
                <code>{{ $source['endpoint'] }}</code>
            </p>
        @endforeach
        <div class="overflow-x-auto mt-4">
            <table class="w-full border-collapse" border="1" cellpadding="8">
                <thead>
                    <tr>
                        <th>WP ID / Type</th>
                        <th>Category</th>
                        <th>Date / Day</th>
                        <th>Title / Description</th>
                        <th>Time</th>
                        <th>Validation</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($wpAgendaPreviewRows as $row)
                        <tr>
                            <td>{{ $row['wordpress_id'] }}<br>{{ $row['post_type'] }}</td>
                            <td>{{ $row['category'] }}</td>
                            <td>{{ $row['date'] }}<br>{{ $row['day'] }}<br>WP: {{ $row['wordpress_day'] }}</td>
                            <td>{{ $row['title'] }}<br>
                                @if ($row['description_empty'])
                                    <small>No WordPress description; preserve existing app description when
                                        updating.</small>
                                @else
                                    <small>{{ $row['description_preview'] }}</small>
                                @endif
                            </td>
                            <td>{{ $row['start_time'] }} – {{ $row['end_time'] }}</td>
                            <td>
                                @if (count($row['issues']))
                                    @foreach ($row['issues'] as $issue)
                                        <p class="text-red-600">{{ $issue }}</p>
                                    @endforeach
                                @else
                                    Field validation passed — not linked/synced
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">No matching agenda sessions were returned.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
