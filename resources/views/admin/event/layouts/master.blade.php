<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <title>{{ $pageTitle }} - {{ $eventName }}</title>

    {{-- FONT AWESOME LINK --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css"
        integrity="sha512-SzlrxWUlpfuzQ+pcUCosxcglQRNAq/DZjVsC0lE40xsADsfeQoEypE+enwcOiGjk/bSuGGKHEyjSoQ1zVisanQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>

    {{-- CSS --}}
    <link rel="stylesheet" href="{{ asset('css/event/master.css') }}">

    {{-- VITE --}}
    @vite('resources/css/app.css')

    {{-- LIVEWIRE --}}
    @livewireStyles()
</head>

<body>
    <div class="main-grid-full duration-300" id="main-container">
        <div class="bg-primaryColor h-full min-h-screen p-5 pt-8 duration-300 sidebar-full" id="sidebar">
            <div class="flex justify-center">
                <img src="{{ asset('assets/images/gpca-networking-logo-inverted.png') }}" class="duration-300 sidebar-full-image" alt="logo" id="sidebar-image">
            </div>
            <div class="mt-10 text-white flex flex-col gap-3" id="main-navigation">
                <a href="{{ route('admin.event.dashboard.view', ['eventCategory' => $eventCategory, 'eventId' => $eventId]) }}" class="{{ request()->is('admin/event/*/*/dashboard*') ? 'bg-sideBarBGColorHover' : 'hover:bg-sideBarBGColorHover' }} flex items-center gap-5 p-2 rounded-md duration-500">
                    <i class="fa-solid fa-chart-pie w-5 text-center"></i>
                    <p>Dashboard</p>
                </a>
                <a href="{{ route('admin.event.details.view', ['eventCategory' => $eventCategory, 'eventId' => $eventId]) }}" class="{{ request()->is('admin/event/*/*/details*') ? 'bg-sideBarBGColorHover' : 'hover:bg-sideBarBGColorHover' }} flex items-center gap-5 p-2 rounded-md duration-500">
                    <i class="fa-solid fa-calendar-days  w-5 text-center"></i>
                    <p>Event</p>
                </a>
                <a href="{{ route('admin.event.attendees.view', ['eventCategory' => $eventCategory, 'eventId' => $eventId]) }}" class="{{ request()->is('admin/event/*/*/attendee*') ? 'bg-sideBarBGColorHover' : 'hover:bg-sideBarBGColorHover' }} flex items-center gap-5 p-2 rounded-md duration-500">
                    <i class="fa-solid fa-people-group w-5 text-center"></i>
                    <p>Attendees</p>
                </a>
                <a href="{{ route('admin.event.speakers.view', ['eventCategory' => $eventCategory, 'eventId' => $eventId]) }}" class="{{ request()->is('admin/event/*/*/speaker*') ? 'bg-sideBarBGColorHover' : 'hover:bg-sideBarBGColorHover' }} flex items-center gap-5 p-2 rounded-md duration-500">
                    <i class="fa-solid fa-microphone w-5 text-center"></i>
                    <p>Speakers</p>
                </a>
                <a href="{{ route('admin.event.sessions.view', ['eventCategory' => $eventCategory, 'eventId' => $eventId]) }}" class="{{ request()->is('admin/event/*/*/session*') ? 'bg-sideBarBGColorHover' : 'hover:bg-sideBarBGColorHover' }} flex items-center gap-5 p-2 rounded-md duration-500">
                    <i class="fa-regular fa-newspaper w-5 text-center"></i>
                    <p>Sessions</p>
                </a>
                <a href="{{ route('admin.event.sponsors.view', ['eventCategory' => $eventCategory, 'eventId' => $eventId]) }}" class="{{ request()->is('admin/event/*/*/sponsor*') ? 'bg-sideBarBGColorHover' : 'hover:bg-sideBarBGColorHover' }} flex items-center gap-5 p-2 rounded-md duration-500">
                    <i class="fa-solid fa-handshake w-5 text-center"></i>
                    <p>Sponsors</p>
                </a>
                <a href="{{ route('admin.event.exhibitors.view', ['eventCategory' => $eventCategory, 'eventId' => $eventId]) }}" class="{{ request()->is('admin/event/*/*/exhibitor*') ? 'bg-sideBarBGColorHover' : 'hover:bg-sideBarBGColorHover' }} flex items-center gap-5 p-2 rounded-md duration-500">
                    <i class="fa-solid fa-building-user w-5 text-center"></i>
                    <p>Exhibitors</p>
                </a>
                <a href="{{ route('admin.event.meeting-room-partners.view', ['eventCategory' => $eventCategory, 'eventId' => $eventId]) }}" class="{{ request()->is('admin/event/*/*/meeting-room-partner*') ? 'bg-sideBarBGColorHover' : 'hover:bg-sideBarBGColorHover' }} flex items-center gap-5 p-2 rounded-md duration-500">
                    <i class="fa-solid fa-users-between-lines w-5 text-center"></i>
                    <p>Meeting room partners</p>
                </a>
                <a href="{{ route('admin.event.media-partners.view', ['eventCategory' => $eventCategory, 'eventId' => $eventId]) }}" class="{{ request()->is('admin/event/*/*/media-partner*') ? 'bg-sideBarBGColorHover' : 'hover:bg-sideBarBGColorHover' }} flex items-center gap-5 p-2 rounded-md duration-500">
                    <i class="fa-solid fa-photo-film w-5 text-center"></i>
                    <p>Media partners</p>
                </a>
                <a href="{{ route('admin.event.notifications.view', ['eventCategory' => $eventCategory, 'eventId' => $eventId]) }}" class="{{ request()->is('admin/event/*/*/notification*') ? 'bg-sideBarBGColorHover' : 'hover:bg-sideBarBGColorHover' }} flex items-center gap-5 p-2 rounded-md duration-500">
                    <i class="fa fa-bell w-5 text-center"></i>
                    <p>Notifications</p>
                </a>
                <a href="{{ route('admin.event.features.view', ['eventCategory' => $eventCategory, 'eventId' => $eventId]) }}" class="{{ request()->is('admin/event/*/*/feature*') ? 'bg-sideBarBGColorHover' : 'hover:bg-sideBarBGColorHover' }} flex items-center gap-5 p-2 rounded-md duration-500">
                    <i class="fa-solid fa-list-check w-5 text-center"></i>
                    <p>Features</p>
                </a>
            </div>
        </div>
        <div>
            <div class="bg-headerBGColor text-white flex items-center justify-between py-5 px-7">
                <div class="flex items-center gap-5">
                    <i class="fa-solid fa-bars cursor-pointer mt-1" onclick="menuButtonClicked()"></i>
                    <p>Admin Panel - {{ $eventName }}</p>
                </div>
                <div class="flex gap-5">
                    <a href="{{ route('admin.events.view') }}" class="hover:underline">Manage events</a>
                    <a href="{{ route('admin.media.view') }}" class="hover:underline">Media library</a>
                    <a href="{{ route('admin.logout') }}" class="hover:underline">Logout</a>
                </div>
            </div>
            <div class="p-7">
                @yield('content')
            </div>
        </div>
    </div>

    @livewireScripts()
        


    

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.7/Sortable.min.js"></script>

<script>
    Livewire.directive('sortable', (el, directive, component) => {
        if (directive.modifiers.length > 0) {
            return;
        }

        if (Sortable.get(el)) {
            return;
        }

        Sortable.create(el, {
            animation: 150,
            direction: 'vertical',
            draggable: '[wire\\:sortable\\.item]',
            handle: '[wire\\:sortable\\.handle]',
            dataIdAttr: 'wire:sortable.item',

            onEnd: async function (event) {
                if (event.oldIndex === event.newIndex) {
                    return;
                }

                const sortable = Sortable.get(el);

                const items = sortable.toArray().map((id, index) => ({
                    order: index + 1,
                    value: Number(id)
                }));

                sortable.option('disabled', true);

                try {
                    await Livewire.find(component.id).call(
                        directive.value,
                        items
                    );
                } catch (error) {
                    console.error('Speaker reorder failed:', error);
                    alert('Could not save speaker order. Refresh the page before trying again.');
                } finally {
                    if (el.isConnected && Sortable.get(el)) {
                        Sortable.get(el).option('disabled', false);
                    }
                }
            }
        });
    });

    Livewire.hook('element.removed', (el) => {
        const sortable = Sortable.get(el);

        if (sortable) {
            sortable.destroy();
        }
    });
</script>

    <script src="{{ asset('js/event/master.js') }}"></script>
    <script src="{{ asset('js/allswal.js') }}"></script>


</body>

</html>
