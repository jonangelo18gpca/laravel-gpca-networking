<div class="border rounded-xl p-4 mt-4">
    <h3 class="text-xl font-semibold">
        Sync agenda speakers
    </h3>

    <p class="text-sm text-gray-600 mt-2">
        Uses the agenda feature selected above.
        Checks missing app assignments and existing app assignments
        absent from the current WordPress connections.
    </p>

    <p class="text-sm text-gray-600 mt-2">
        Additions use relationship role → speaker profile role → Speaker.
        Removals affect only the selected agenda–speaker assignment.
        Profiles, WordPress mappings and existing Speaker Types remain.
    </p>

    <button
        type="button"
        wire:click="loadWordPressAgendaSpeakerSync"
        wire:loading.attr="disabled"
        class="border rounded p-3 mt-3"
    >
        Load speaker assignment preview
    </button>

    <p
        wire:loading
        wire:target="loadWordPressAgendaSpeakerSync"
        class="text-sm mt-2"
    >
        Reading WordPress connections and checking app assignments…
    </p>

    <div aria-live="polite">
        @if ($wpAgendaSpeakerError)
            <p class="text-red-600 mt-3">
                {{ $wpAgendaSpeakerError }}
            </p>
        @endif

        @if ($wpAgendaSpeakerSuccess)
            <p class="text-green-700 mt-3">
                {{ $wpAgendaSpeakerSuccess }}
            </p>
        @endif
    </div>

    @if ($wpAgendaSpeakerLoaded)
        @php
            $previewRows = collect($wpAgendaSpeakerRows);

            $addCount = $previewRows->where('status', 'add')->count();
            $removeCount = $previewRows->where('status', 'remove')->count();
            $existingCount = $previewRows->where('status', 'existing')->count();
            $blockedCount = $previewRows->where('status', 'blocked')->count();

            $selectedAdds = 0;
            $selectedRemovals = 0;

            foreach ($wpAgendaSpeakerRows as $previewRow) {
                if (
                    ($wpAgendaSpeakerSelections[$previewRow['key']] ?? false)
                    !== true
                ) {
                    continue;
                }

                if ($previewRow['status'] === 'add') {
                    $selectedAdds++;
                }

                if ($previewRow['status'] === 'remove') {
                    $selectedRemovals++;
                }
            }

            $groups = [
                'add' => 'Assignments to add',
                'remove' => 'Assignments to remove — review carefully',
                'existing' => 'Already assigned',
                'blocked' => 'Review required',
            ];
        @endphp

        <p class="text-sm mt-3">
            {{ $addCount }} addition candidate(s).
            {{ $removeCount }} removal candidate(s).
            {{ $existingCount }} already assigned.
            {{ $blockedCount }} require review.
        </p>

        <p class="text-sm text-gray-500 mt-2">
            Removal checks include only successfully checked, linked
            agendas without agenda validation issues.
            Agendas missing entirely from the WordPress response
            are not cleaned up.
        </p>

        @if ($wpAgendaSpeakerToken)
            <p class="text-sm text-gray-500 mt-2">
                Preview expires after 15 minutes.
                Nothing is selected automatically.
            </p>

            <div class="flex flex-wrap gap-2 mt-3">
                <button
                    type="button"
                    wire:click="selectAllWordPressAgendaSpeakerSync(true)"
                    wire:loading.attr="disabled"
                    class="border rounded px-3 py-2 text-sm"
                >
                    Select additions only
                </button>

                <button
                    type="button"
                    wire:click="selectAllWordPressAgendaSpeakerSync(false)"
                    wire:loading.attr="disabled"
                    class="border rounded px-3 py-2 text-sm"
                >
                    Clear all selections
                </button>
            </div>
        @endif

        @if (empty($wpAgendaSpeakerRows))
            <p class="text-sm text-gray-600 mt-3">
                No assignment rows were found for this source and year.
            </p>
        @endif

        @foreach ($groups as $status => $heading)
            @php
                $groupRows = $previewRows->where('status', $status);
            @endphp

            @if ($groupRows->isNotEmpty())
                <h4 class="font-semibold mt-5">
                    {{ $heading }} ({{ $groupRows->count() }})
                </h4>

                <div class="overflow-x-auto mt-2">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr>
                                <th class="border p-2 text-left">Select</th>
                                <th class="border p-2 text-left">Agenda</th>
                                <th class="border p-2 text-left">Speaker</th>
                                <th class="border p-2 text-left">Speaker Type</th>
                                <th class="border p-2 text-left">Result</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($groupRows as $row)
                                <tr wire:key="wp-agenda-speaker-{{ $row['key'] }}">
                                    <td class="border p-2 align-top">
                                        @if (in_array($row['status'], ['add', 'remove'], true))
                                            <input
                                                type="checkbox"
                                                wire:model="wpAgendaSpeakerSelections.{{ $row['key'] }}"
                                                wire:loading.attr="disabled"
                                                aria-label="{{ $row['status'] === 'remove' ? 'Remove' : 'Add' }} app speaker {{ $row['speaker_id'] }} for app agenda {{ $row['session_id'] }}"
                                            >
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>

                                    <td class="border p-2 align-top">
                                        <p>{{ $row['session_title'] }}</p>

                                        <p class="text-gray-500 mt-1">
                                            WP #{{ $row['wordpress_session_id'] }}
                                        </p>

                                        @if ($row['session_id'])
                                            <p class="text-gray-500">
                                                App #{{ $row['session_id'] }}
                                            </p>
                                        @endif
                                    </td>

                                    <td class="border p-2 align-top">
                                        <p>{{ $row['speaker_name'] }}</p>

                                        @if ($row['wordpress_speaker_id'])
                                            <p class="text-gray-500 mt-1">
                                                WP #{{ $row['wordpress_speaker_id'] }}
                                            </p>
                                        @endif

                                        @if ($row['speaker_id'])
                                            <p class="text-gray-500">
                                                App #{{ $row['speaker_id'] }}
                                            </p>
                                        @endif

                                        @if (isset($row['assignment_id']))
                                            <p class="text-gray-500">
                                                Assignment #{{ $row['assignment_id'] }}
                                            </p>
                                        @endif
                                    </td>

                                    <td class="border p-2 align-top">
                                        <p>{{ $row['role'] }}</p>

                                        <p class="text-gray-500 mt-1">
                                            @if ($row['role_origin'] === 'relationship')
                                                Relationship metadata
                                            @elseif ($row['role_origin'] === 'speaker_profile')
                                                Speaker profile
                                            @elseif ($row['role_origin'] === 'app_assignment')
                                                Current app assignment
                                            @else
                                                Default
                                            @endif
                                        </p>

                                        @if ($row['status'] === 'add' && $row['will_create_type'])
                                            <p class="text-gray-500 mt-1">
                                                Will create this type in the agenda.
                                            </p>
                                        @endif

                                        @if ($row['status'] !== 'remove' && $row['source_role'] !== $row['role'])
                                            <p class="text-gray-500 mt-1">
                                                Mapped from: {{ $row['source_role'] }}
                                            </p>
                                        @endif
                                    </td>

                                    <td class="border p-2 align-top">
                                        <p class="{{ in_array($row['status'], ['remove', 'blocked'], true) ? 'text-red-700' : ($row['status'] === 'existing' ? 'text-green-700' : 'text-gray-700') }}">
                                            {{ $row['message'] }}
                                        </p>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endforeach

        @if ($wpAgendaSpeakerToken)
            <p class="text-sm mt-4">
                Selected:
                {{ $selectedAdds }} addition(s),
                {{ $selectedRemovals }} removal(s).
            </p>

            <label class="block mt-3 text-sm">
                <input
                    type="checkbox"
                    wire:model="wpAgendaSpeakerConfirmed"
                    wire:loading.attr="disabled"
                >

                I reviewed the selected additions and removals.
                Removals affect agenda–speaker assignments only.
                Keep speaker profiles, WordPress mappings and existing
                Speaker Types.
            </label>

            <button
                type="button"
                wire:click="applyWordPressAgendaSpeakerSync"
                wire:loading.attr="disabled"
                @if (!$wpAgendaSpeakerConfirmed) disabled @endif
                class="bg-primaryColor text-white rounded p-3 mt-3 disabled:opacity-50"
            >
                Apply selected changes
            </button>

            <p
                wire:loading
                wire:target="applyWordPressAgendaSpeakerSync"
                class="text-sm mt-2"
            >
                Rechecking WordPress connections and applying selected changes…
            </p>
        @endif
    @endif
</div>