<div class="border rounded-xl p-4 mt-4">
    <h3 class="text-xl font-semibold">
        Create missing speakers — preview
    </h3>

    <p class="text-sm text-gray-600 mt-2">
        Create missing speakers in the selected app category.
        Available, allowed profile photos are included automatically.
        Previewing does not create records.
    </p>

    <button type="button" wire:click="loadWordPressSpeakerCreation" wire:loading.attr="disabled"
        class="border rounded p-3 mt-3">
        Preview missing speakers
    </button>

    <p wire:loading wire:target="loadWordPressSpeakerCreation" class="mt-2">
        Checking source records, saved links and existing app names...
    </p>

    @if ($wpSpeakerCreateError)
        <p class="text-red-600 mt-3">
            {{ $wpSpeakerCreateError }}
        </p>
    @endif

    @if ($wpSpeakerCreateSuccess)
        <p class="text-green-700 mt-3">
            {{ $wpSpeakerCreateSuccess }}
        </p>
    @endif



    @if ($wpSpeakerCreateLoaded)

        @php
            $wpSpeakerCreateSelectedCount = count(
                array_filter($wpSpeakerCreateSelections, function ($checked) {
                    return $checked === true;
                }),
            );
        @endphp


        <p class="mt-3 text-sm">
            {{ count($wpSpeakerCreateRows) }} unlinked eligible records;
            {{ $wpSpeakerCreateLinkedCount }} already linked;
            {{ $wpSpeakerCreateExcludedCount }} excluded or
            unrecognized-eligibility records.
        </p>

        @if ($wpSpeakerCreateToken)
            <p class="text-sm text-gray-600 mt-2">
                This creation preview expires after 15 minutes.
                Reload it if the source, settings or candidate records change.
            </p>
        @endif


        @if ($wpSpeakerCreateToken)
            <div class="flex flex-wrap items-center gap-2 mt-3">
                <button type="button" wire:click="selectAllWordPressSpeakerCreation(true)" wire:loading.attr="disabled"
                    class="border rounded px-3 py-2 text-sm">
                    Select all eligible
                </button>

                <button type="button" wire:click="selectAllWordPressSpeakerCreation(false)"
                    wire:loading.attr="disabled" class="border rounded px-3 py-2 text-sm">
                    Clear selection
                </button>

                <span class="text-sm text-gray-600">
                    {{ $wpSpeakerCreateSelectedCount }} selected
                </span>
            </div>
        @endif

        <div class="overflow-x-auto mt-3">
            <table class="w-full" border="1" cellpadding="8">
                <thead>
                    <tr>
                        <th>Select</th>
                        <th>Proposed speaker</th>
                        <th>Profile</th>
                        <th>Photo</th>
                        <th>Checks</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($wpSpeakerCreateRows as $row)
                        <tr wire:key="wp-speaker-create-{{ $wpSpeakerSource }}-{{ $row['wp_id'] }}">
                            <td class="align-top">
                                @if ($row['can_create'])
                                    <label>
                                        <input type="checkbox"
                                            wire:model="wpSpeakerCreateSelections.{{ $row['wp_id'] }}"
                                            wire:loading.attr="disabled">
                                        Select
                                    </label>
                                @else
                                    Blocked
                                @endif
                            </td>

                            <td class="align-top">
                                <p>
                                    WP #{{ $row['wp_id'] }}
                                    — {{ $row['name'] }}
                                </p>

                                @if ($row['name_parts'])
                                    <details wire:ignore.self class="mt-2">
                                        <summary class="cursor-pointer text-sm">
                                            Name split
                                        </summary>

                                        <p class="text-sm mt-2">
                                            Salutation:
                                            {{ $row['name_parts']['salutation'] ?: 'None' }}
                                        </p>
                                        <p class="text-sm">
                                            First:
                                            {{ $row['name_parts']['first_name'] }}
                                        </p>
                                        <p class="text-sm">
                                            Middle:
                                            {{ $row['name_parts']['middle_name'] ?: 'None' }}
                                        </p>
                                        <p class="text-sm">
                                            Last:
                                            {{ $row['name_parts']['last_name'] }}
                                        </p>
                                    </details>
                                @endif
                            </td>

                            <td class="align-top">
                                <p>{{ $row['job_title'] ?: 'No designation' }}</p>
                                <p>{{ $row['company_name'] ?: 'No company' }}</p>
                                <p class="text-sm mt-2">
                                    App type: {{ $row['type_name'] }}
                                </p>
                            </td>

                            <td class="align-top">
                                @if ($row['photo_allowed'])
                                    <p class="text-sm text-green-700">
                                        Included automatically
                                    </p>

                                    <details wire:ignore.self class="mt-1">
                                        <summary class="cursor-pointer text-xs text-gray-500">
                                            View URL
                                        </summary>

                                        <p class="text-xs break-all mt-1">
                                            {{ $row['photo_url'] }}
                                        </p>
                                    </details>
                                @else
                                    <p class="text-sm text-gray-500">
                                        No allowed photo
                                    </p>
                                @endif
                            </td>

                            <td class="align-top">
                                @if ($row['can_create'])
                                    <p class="text-green-700 text-sm">
                                        Passed current checks.
                                        Verify this person is not already
                                        present under a different name.
                                    </p>
                                @endif

                                @foreach ($row['issues'] as $issue)
                                    <p class="text-red-600 text-sm mt-2">
                                        {{ $issue }}
                                    </p>
                                @endforeach

                                @foreach ($row['warnings'] as $warning)
                                    <p class="text-yellow-700 text-sm mt-2">
                                        {{ $warning }}
                                    </p>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                No unlinked eligible speakers were found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>


    @if ($wpSpeakerCreateToken)
    <div class="border-t mt-4 pt-4">
        <label class="block text-sm">
            <input
                type="checkbox"
                wire:model="wpSpeakerCreateConfirmed"
                wire:loading.attr="disabled">

            I reviewed the selected speakers and approve creating
            {{ $wpSpeakerCreateSelectedCount }} active speaker profile(s)
            and their WordPress links, including available,
            allowed profile photos.
        </label>

        <p class="text-sm text-gray-600 mt-2">
            New speakers will be appended after existing speakers.
            Existing profiles and session assignments will not be changed.
        </p>

        <button
            type="button"
            wire:click="createSelectedWordPressSpeakers"
            wire:loading.attr="disabled"
            @if (
                !$wpSpeakerCreateConfirmed
                || $wpSpeakerCreateSelectedCount < 1
            ) disabled @endif
            class="border rounded p-3 mt-3 disabled:opacity-50">
            Create {{ $wpSpeakerCreateSelectedCount }} selected speaker(s)
        </button>

        <p
            wire:loading
            wire:target="createSelectedWordPressSpeakers"
            class="text-sm mt-2">
            Rechecking WordPress and creating selected speakers...
        </p>
    </div>
@endif

    @endif
</div>
