@extends('admin.home.layouts.master')

@section('content')
    <div class="container mx-auto my-10">
        @if (session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded fixed top-4 left-1/2 transform -translate-x-1/2 w-96"
                role="alert">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="block sm:inline">{{ session('success') }}</span>
                    </div>
                </div>
            </div>
        @endif

        <a href="{{ route('admin.add.event.view') }}"
            class="bg-primaryColor hover:bg-primaryColorHover text-white font-medium py-2 px-5 rounded-md inline-flex items-center text-sm">
            <span class="mr-2"><i class="fas fa-plus"></i></span>
            <span>Add Event</span>
        </a>

        {{-- @if (count($finalEvents) > 0)
            <div class="mt-10 grid grid-cols-2 gap-5">
                @foreach ($finalEvents as $event)
                    <a
                        href="{{ route('admin.event.details.view', ['eventCategory' => $event['eventCategory'], 'eventId' => $event['eventId']]) }}">
                        <div
                            class="bg-gray-100 px-4 py-4 rounded-lg hover:scale-110 hover:cursor-pointer hover:shadow-md duration-100">
                            <div class="flex items-center gap-4">
                                <img src="{{ $event['eventLogo'] }}" alt="" class="h-16">
                                <p class="font-bold text-3xl">{{ $event['eventName'] }}</p>
                                <p
                                    class="text-primaryColor rounded-full border border-primaryColor px-4 font-bold text-sm">
                                    {{ $event['eventCategory'] }}</p>
                            </div>

                            <div class="flex gap-3 items-center mt-5 text-primaryColor">
                                <i class="fa-solid fa-location-dot"></i>
                                <p>{{ $event['eventLocation'] }}</p>
                            </div>

                            <div class="flex gap-3 items-center mt-2 text-primaryColor">
                                <i class="fa-solid fa-calendar-days"></i>
                                <p>{{ $event['eventDate'] }}</p>
                            </div>
                        </div>

                    </a>
                @endforeach
            </div>
        @endif --}}

        @if (count($finalEvents) > 0)
    <div class="mt-10 grid grid-cols-2 gap-5">
        @foreach ($finalEvents as $event)
            <a
                href="{{ route('admin.event.dashboard.view', [
                    'eventCategory' => $event['eventCategory'],
                    'eventId' => $event['eventId'],
                ]) }}">

                <div
                    class="bg-gray-100 px-4 py-4 rounded-lg hover:scale-110 hover:cursor-pointer hover:shadow-md duration-100">

                    <div class="flex items-center gap-4">
                        <img src="{{ $event['eventLogo'] }}" alt="" class="h-16">

                        <p class="font-bold text-3xl">
                            {{ $event['eventName'] }}
                        </p>

                        <p class="text-primaryColor rounded-full border border-primaryColor px-4 font-bold text-sm">
                            {{ $event['eventCategory'] }}
                        </p>
                    </div>

                    <div class="flex gap-3 items-center mt-5 text-primaryColor">
                        <i class="fa-solid fa-location-dot"></i>
                        <p>{{ $event['eventLocation'] }}</p>
                    </div>

                    <div class="flex gap-3 items-center mt-2 text-primaryColor">
                        <i class="fa-solid fa-calendar-days"></i>
                        <p>{{ $event['eventDate'] }}</p>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
@endif
    </div>

    @if (count($finalEvents) < 1)
        <div class="bg-red-400 text-white text-center py-3 mt-5 rounded-md container mx-auto">
            There are no events yet.
        </div>
    @endif
@endsection
