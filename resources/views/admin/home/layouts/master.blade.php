<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <title>GPCA Networking - {{ $pageTitle }}</title>

    {{-- FONT AWESOME LINK --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css"
        integrity="sha512-SzlrxWUlpfuzQ+pcUCosxcglQRNAq/DZjVsC0lE40xsADsfeQoEypE+enwcOiGjk/bSuGGKHEyjSoQ1zVisanQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>

    {{-- CSS --}}
    <link rel="stylesheet" href="{{ asset('css/home/master.css') }}">

    {{-- VITE --}}
    @vite('resources/css/app.css')

    {{-- LIVEWIRE --}}
    @livewireStyles()
</head>

<body>
    <div class="bg-primaryColor">
        <div class="container mx-auto py-3 px-5">
            <div class="grid grid-cols-3 items-center">
                <div class="text-white font-semibold text-2xl">
                    Admin Panel
                </div>
                <div class="flex justify-center">
                    <img src="{{ asset('assets/images/gpca-networking-logo-inverted.png') }}" class="max-h-16"
                        alt="logo">
                </div>
                <div class="text-white font-semibold flex items-center gap-10 justify-end">
                    <a href="{{ route('admin.events.view') }}"
                        class="{{ request()->is('admin/event*') ? 'text-dashboardNavItemHoverColor' : 'hover:underline' }}">Manage
                        Events</a>
                    <a href="{{ route('admin.media.view') }}"
                        class="{{ request()->is('admin/media*') ? 'text-dashboardNavItemHoverColor' : 'hover:underline' }}">Media</a>
                    <a href="{{ route('admin.logout') }}" class="hover:underline">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div>
        @yield('content')
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


    <script src="{{ asset('js/home/master.js') }}"></script>
    <script src="{{ asset('js/allswal.js') }}"></script>
        
</body>

</html>
