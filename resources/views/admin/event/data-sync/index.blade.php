{{-- @extends('admin.event.layouts.master')

@section('content')
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Data Sync</h1>

            <p class="mt-2 text-gray-600">
                Fetch website content, review changes, and link records
                before importing them into this event.
            </p>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <h2 class="text-lg font-semibold">Sync modules</h2>

            <p class="mt-2 text-gray-600">
                This page is being prepared. No fetching or importing
                actions are enabled here yet.
            </p>

            <div class="mt-5 overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="px-3 py-3">Module</th>
                            <th class="px-3 py-3">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ([
                            'Source settings',
                            'Sessions',
                            'Speakers',
                            'Sponsors',
                            'Exhibitors',
                            'Meeting room partners',
                            'Media partners',
                        ] as $module)
                            <tr class="border-b border-gray-100">
                                <td class="px-3 py-3">{{ $module }}</td>
                                <td class="px-3 py-3 text-gray-500">
                                    Not enabled on this page yet
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection --}}


@extends('admin.event.layouts.master')

@section('content')
    @livewire('event-data-sync', [
        'eventCategory' => $eventCategory,
        'eventId' => $eventId,
    ])
@endsection