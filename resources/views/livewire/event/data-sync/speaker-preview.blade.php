<div>
    {{-- Source selection --}}
    <div class="py-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
                <label
                    for="wp-speaker-source"
                    class="block text-sm font-medium text-gray-700 mb-2">
                    Speaker source
                </label>

                <select
                    id="wp-speaker-source"
                    wire:model="wpSpeakerSource"
                    wire:loading.attr="disabled"
                    wire:target="loadWordPressSpeakerPreview"
                    class="border border-gray-300 rounded-lg px-3 py-2 w-full">
                    <option value="">Select a source</option>

                    <option value="main_plenary_speaker">
                        Main plenary speakers
                    </option>

                    <option value="suspav-speaker">
                        Sustainability Pavilion speakers
                    </option>
                </select>

                @error('wpSpeakerSource')
                    <p class="text-sm text-red-600 mt-2">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label
                    for="wp-speaker-year"
                    class="block text-sm font-medium text-gray-700 mb-2">
                    WordPress year
                </label>

                <input
                    id="wp-speaker-year"
                    type="text"
                    inputmode="numeric"
                    maxlength="4"
                    wire:model.lazy="wpSpeakerYear"
                    wire:loading.attr="disabled"
                    wire:target="loadWordPressSpeakerPreview"
                    class="border border-gray-300 rounded-lg px-3 py-2 w-full">

                @error('wpSpeakerYear')
                    <p class="text-sm text-red-600 mt-2">
                        {{ $message }}
                    </p>
                @enderror
            </div>
        </div>

        <div class="mt-3">
            <button
                type="button"
                wire:click="loadWordPressSpeakerPreview"
                wire:loading.attr="disabled"
                class="border border-gray-300 rounded-lg px-4 py-2 text-sm">
                Load speaker source
            </button>
        </div>

        <p
            wire:loading
            wire:target="loadWordPressSpeakerPreview"
            class="mt-2 text-sm text-gray-500">
            Fetching WordPress speakers...
        </p>

        @if ($wpSpeakerError)
            <p class="text-sm text-red-600 mt-2">
                {{ $wpSpeakerError }}
            </p>
        @endif
    </div>

    @if ($wpSpeakerLoaded)
        <p class="text-xs text-gray-500 pb-3">
            {{ count($wpSpeakerRows) }} records loaded for the selected
            source and year.
        </p>

        {{-- Everyday actions --}}
        <div class="border-t border-gray-100">
            <details wire:ignore.self>
                <summary class="cursor-pointer py-4 text-sm font-semibold text-headingTextColor">
                    Update linked speakers
                </summary>

                <div class="pb-4">
                    @include('livewire.event.data-sync.speaker-update-preview')
                </div>
            </details>

            <details
                wire:ignore.self
                class="border-t border-gray-100">

                <summary class="cursor-pointer py-4 text-sm font-semibold text-headingTextColor">
                    Create missing speakers
                </summary>

                <div class="pb-4">
                    @include('livewire.event.data-sync.speaker-create-preview')
                </div>
            </details>
        </div>

        {{-- Setup and maintenance --}}
        <details
            wire:ignore.self
            class="border-t border-gray-100">

            <summary class="cursor-pointer py-4 text-sm font-medium text-gray-500">
                Setup & maintenance
            </summary>

            <div class="border-l border-gray-200 pl-4">
                {{-- Destination and role mappings --}}
                <details wire:ignore.self>
                    <summary class="cursor-pointer py-3 text-sm font-medium text-gray-700">
                        Category & speaker-type mappings
                    </summary>

                    <div class="pb-3">
                        @include('livewire.event.data-sync.speaker-mapping')
                    </div>
                </details>

                {{-- Existing app speaker links --}}
                <details
                    wire:ignore.self
                    class="border-t border-gray-100">

                    <summary class="cursor-pointer py-3 text-sm font-medium text-gray-700">
                        Link existing speakers
                    </summary>

                    <div class="pb-3">
                        @include('livewire.event.data-sync.speaker-linking')
                    </div>
                </details>

                {{-- Source inspection and name review --}}
                <details
                    wire:ignore.self
                    class="border-t border-gray-100">

                    <summary class="cursor-pointer py-3 text-sm font-medium text-gray-700">
                        Source inspection & name review
                        — {{ count($wpSpeakerRows) }} records
                    </summary>

                    <div class="pb-3">
                        <p class="text-sm text-gray-600">
                            Inspect source records and review flagged names.
                            Excluded records remain visible for review.
                            After correcting a name, reload the relevant
                            creation or update preview.
                        </p>

                        @if (!count($wpSpeakerRows))
                            <p class="mt-2 text-sm">
                                No speakers matched this year.
                            </p>
                        @else
                            <div class="overflow-x-auto mt-3">
                                <table
                                    class="w-full text-sm"
                                    border="1"
                                    cellpadding="8">
                                    <thead>
                                        <tr>
                                            <th>WordPress speaker</th>
                                            <th>Proposed app name</th>
                                            <th>Designation / company</th>
                                            <th>Role / eligibility</th>
                                            <th>Review</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @foreach ($wpSpeakerRows as $row)
                                            <tr wire:key="wp-speaker-preview-{{ $row['source'] }}-{{ $row['wp_id'] }}">
                                                <td class="align-top">
                                                    <p class="text-xs text-gray-500">
                                                        WP #{{ $row['wp_id'] }}
                                                    </p>

                                                    <p class="font-medium mt-1">
                                                        {{ $row['name'] }}
                                                    </p>

                                                    <p class="text-xs text-gray-500 mt-1">
                                                        Year: {{ $row['year'] }}
                                                    </p>
                                                </td>

                                                <td class="align-top">
                                                    @if ($row['eligibility'] === 'Eligible')
                                                        @php
                                                            $nameKey =
                                                                $row['source']
                                                                . '-'
                                                                . $row['wp_id'];

                                                            $nameParts =
                                                                $wpSpeakerNames[$nameKey] ?? [];

                                                            $reviewRequired =
                                                                $nameParts['review_required'] ?? false;

                                                            $displayName = implode(
                                                                ' ',
                                                                array_filter(
                                                                    [
                                                                        $nameParts['salutation'] ?? '',
                                                                        $nameParts['first_name'] ?? '',
                                                                        $nameParts['middle_name'] ?? '',
                                                                        $nameParts['last_name'] ?? '',
                                                                    ],
                                                                    function ($part) {
                                                                        return trim((string) $part) !== '';
                                                                    }
                                                                )
                                                            );
                                                        @endphp

                                                        @if ($reviewRequired)
                                                            <div class="mb-3">
                                                                @foreach ($nameParts['review_reasons'] ?? [] as $reason)
                                                                    <p class="text-yellow-700 text-sm mb-2">
                                                                        {{ $reason }}
                                                                    </p>
                                                                @endforeach
                                                            </div>

                                                            <label
                                                                for="wp-name-salutation-{{ $nameKey }}"
                                                                class="block text-sm font-medium text-gray-700 mb-1">
                                                                Salutation
                                                            </label>

                                                            <select
                                                                id="wp-name-salutation-{{ $nameKey }}"
                                                                wire:model.lazy="wpSpeakerNames.{{ $nameKey }}.salutation"
                                                                class="border border-gray-300 rounded-lg px-3 py-2 w-full">
                                                                <option value="">
                                                                    None
                                                                </option>

                                                                @foreach (config('app.salutations', []) as $salutation)
                                                                    <option value="{{ $salutation }}">
                                                                        {{ $salutation }}
                                                                    </option>
                                                                @endforeach
                                                            </select>

                                                            <label
                                                                for="wp-name-first-{{ $nameKey }}"
                                                                class="block text-sm font-medium text-gray-700 mt-3 mb-1">
                                                                First name
                                                            </label>

                                                            <input
                                                                id="wp-name-first-{{ $nameKey }}"
                                                                type="text"
                                                                wire:model.lazy="wpSpeakerNames.{{ $nameKey }}.first_name"
                                                                class="border border-gray-300 rounded-lg px-3 py-2 w-full">

                                                            <label
                                                                for="wp-name-middle-{{ $nameKey }}"
                                                                class="block text-sm font-medium text-gray-700 mt-3 mb-1">
                                                                Middle name
                                                            </label>

                                                            <input
                                                                id="wp-name-middle-{{ $nameKey }}"
                                                                type="text"
                                                                wire:model.lazy="wpSpeakerNames.{{ $nameKey }}.middle_name"
                                                                class="border border-gray-300 rounded-lg px-3 py-2 w-full">

                                                            <label
                                                                for="wp-name-last-{{ $nameKey }}"
                                                                class="block text-sm font-medium text-gray-700 mt-3 mb-1">
                                                                Last name
                                                            </label>

                                                            <input
                                                                id="wp-name-last-{{ $nameKey }}"
                                                                type="text"
                                                                wire:model.lazy="wpSpeakerNames.{{ $nameKey }}.last_name"
                                                                class="border border-gray-300 rounded-lg px-3 py-2 w-full">

                                                            @if (
                                                                trim($nameParts['first_name'] ?? '') === ''
                                                                || trim($nameParts['last_name'] ?? '') === ''
                                                            )
                                                                <p class="text-red-600 text-sm mt-2">
                                                                    First and last name
                                                                    are required before
                                                                    creation.
                                                                </p>
                                                            @endif

                                                            <label class="block text-sm mt-3">
                                                                <input
                                                                    type="checkbox"
                                                                    wire:model="wpSpeakerNames.{{ $nameKey }}.reviewed">

                                                                I checked and corrected
                                                                these name fields.
                                                            </label>
                                                        @else
                                                            <p>
                                                                {{ $displayName ?: $row['name'] }}
                                                            </p>
                                                        @endif
                                                    @else
                                                        <p class="text-sm text-gray-500">
                                                            Name preparation is disabled
                                                            for excluded or
                                                            unrecognized-eligibility
                                                            records.
                                                        </p>
                                                    @endif
                                                </td>

                                                <td class="align-top">
                                                    <p>
                                                        {{ $row['job_title'] ?: 'No designation' }}
                                                    </p>

                                                    <p class="text-gray-500 mt-1">
                                                        {{ $row['company_name'] ?: 'No company' }}
                                                    </p>
                                                </td>

                                                <td class="align-top">
                                                    <p>
                                                        {{ $row['role'] ?: 'No role provided' }}
                                                    </p>

                                                    <p class="text-gray-500 mt-1">
                                                        {{ $row['eligibility'] }}
                                                    </p>

                                                    @if ($row['eligibility'] === 'Excluded')
                                                        <p class="text-xs text-gray-500 mt-2">
                                                            hide_speaker_name is checked.
                                                            Do not import.
                                                        </p>
                                                    @endif
                                                </td>

                                                <td class="align-top">
                                                    @foreach ($row['warnings'] as $warning)
                                                        <p class="text-sm text-yellow-700 mb-2">
                                                            {{ $warning }}
                                                        </p>
                                                    @endforeach

                                                    <details
                                                        wire:ignore.self
                                                        class="mt-2">

                                                        <summary class="cursor-pointer text-sm text-gray-600">
                                                            Biography & image
                                                        </summary>

                                                        <div class="mt-2 space-y-2">
                                                            <p class="whitespace-pre-line text-sm">
                                                                {{ $row['biography_text'] ?: 'No biography provided.' }}
                                                            </p>

                                                            <p class="text-xs text-gray-500">
                                                                WordPress media ID:
                                                                {{ $row['media_id'] ?: 'None' }}
                                                            </p>

                                                            <p class="break-all text-xs text-gray-500">
                                                                {{ $row['image_url'] ?: 'No image URL provided.' }}
                                                            </p>
                                                        </div>
                                                    </details>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </details>
            </div>
        </details>
    @endif
</div>