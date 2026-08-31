<div>
    <div class="flex gap-3">
        @if ($eventData['eventDetails']['is_visible_in_the_app'])
            <button wire:click.prevent="toggleVisibilityInTheApp"
                class="bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm w-40 h-10">Hide
                in the app</button>
        @else
            <button wire:click.prevent="toggleVisibilityInTheApp"
                class="bg-primaryColor hover:bg-primaryColorHover text-white rounded-lg text-sm w-40 h-10">Show
                in the app</button>
        @endif


        @if ($eventData['eventDetails']['is_accessible_in_the_app'])
            <button wire:click.prevent="toggleAccessibilityInTheApp"
                class="bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm w-40 h-10">Disable
                Access</button>
        @else
            <button wire:click.prevent="toggleAccessibilityInTheApp"
                class="bg-primaryColor hover:bg-primaryColorHover text-white rounded-lg text-sm w-40 h-10">Enable
                access</button>
        @endif
    </div>


    <div class="flex gap-3 mt-3">
    @if ($eventData['eventDetails']['show_event_features'])
        <button wire:click.prevent="toggleEventFeaturesVisibility" class="bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm w-52 h-10">Hide Event Features</button>
    @else
        <button wire:click.prevent="toggleEventFeaturesVisibility" class="bg-primaryColor hover:bg-primaryColorHover text-white rounded-lg text-sm w-52 h-10">Show Event Features</button>
    @endif

    @if ($eventData['eventDetails']['show_social_networkings'])
        <button wire:click.prevent="toggleSocialNetworkingsVisibility" class="bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm w-52 h-10">Hide Social Networking</button>
    @else
        <button wire:click.prevent="toggleSocialNetworkingsVisibility" class="bg-primaryColor hover:bg-primaryColorHover text-white rounded-lg text-sm w-52 h-10">Show Social Networking</button>
    @endif
</div>

    {{-- EVENT DETAILS --}}
    <div class="border border-primaryColor rounded-2xl py-5 px-7 mt-5">
        <div class="flex items-center justify-between">
            <h1 class="text-headingTextColor text-3xl font-bold">Event Details</h1>
            <div>
                <button wire:click="showEditEventDetails"
                    class="bg-yellow-500 hover:bg-yellow-600 text-white font-medium py-2 px-5 rounded-md inline-flex items-center text-sm">
                    <span class="mr-2"><i class="fa-solid fa-file-pen"></i></span>
                    <span>Edit</span>
                </button>
            </div>
        </div>

        <div class="flex gap-3 items-center mt-3">
            <p class="font-bold text-primaryColor">Full Name:</p>
            <p>{{ $eventData['eventDetails']['full_name'] }}</p>
        </div>

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">Short Name:</p>
            <p>{{ $eventData['eventDetails']['short_name'] }}</p>
        </div>

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">Category:</p>
            <p>{{ $eventData['eventDetails']['category'] }}</p>
        </div>

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">Location:</p>
            <p>{{ $eventData['eventDetails']['location'] }}</p>
        </div>

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">Event dates:</p>
            <p>{{ $eventData['eventDetails']['finalEventStartDate'] . ' - ' . $eventData['eventDetails']['finalEventEndDate'] }}
        </div>

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">Timezone:</p>
            <p>{{ $eventData['eventDetails']['timezone'] }}</p>
        </div>

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">Full link:</p>
            <p>{{ $eventData['eventDetails']['event_full_link'] }}</p>
        </div>

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">Short link:</p>
            <p>{{ $eventData['eventDetails']['event_short_link'] }}</p>
        </div>

        <hr class="my-4">

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">Is Visible in the app:</p>
            <p>{{ $eventData['eventDetails']['is_visible_in_the_app'] ? 'Yes' : 'No' }}</p>
        </div>

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">Is Accessible in the app:</p>
            <p>{{ $eventData['eventDetails']['is_accessible_in_the_app'] ? 'Yes' : 'No' }}</p>
        </div>
    </div>


    {{-- EVENT COLORS --}}
    <div class="border border-primaryColor rounded-2xl py-5 px-7 mt-5">
        <div class="flex items-center justify-between">
            <h1 class="text-headingTextColor text-3xl font-bold">Event Colors</h1>
            <div>
                <button wire:click="showEditEventColors"
                    class="bg-yellow-500 hover:bg-yellow-600 text-white font-medium py-2 px-5 rounded-md inline-flex items-center text-sm">
                    <span class="mr-2"><i class="fa-solid fa-file-pen"></i></span>
                    <span>Edit</span>
                </button>
            </div>
        </div>

        <div class="flex gap-3 items-center mt-3">
            <p class="font-bold text-primaryColor">Primary Background Color: </p>
            <p>{{ $eventData['eventColors']['primary_bg_color'] }}</p>
        </div>

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">Secondary Background Color: </p>
            <p>{{ $eventData['eventColors']['secondary_bg_color'] }}</p>
        </div>

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">Primary Text Color: </p>
            <p>{{ $eventData['eventColors']['primary_text_color'] }}</p>
        </div>

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">Secondary Text Color: </p>
            <p>{{ $eventData['eventColors']['secondary_text_color'] }}</p>
        </div>
    </div>

    {{-- EVENT WEBVIEW LINKS --}}
    <div class="border border-primaryColor rounded-2xl py-5 px-7 mt-5">
        <div class="flex items-center justify-between">
            <h1 class="text-headingTextColor text-3xl font-bold">Event WebView Links</h1>
            <div>
                <button wire:click="showEditEventWebViewLinks"
                    class="bg-yellow-500 hover:bg-yellow-600 text-white font-medium py-2 px-5 rounded-md inline-flex items-center text-sm">
                    <span class="mr-2"><i class="fa-solid fa-file-pen"></i></span>
                    <span>Edit</span>
                </button>
            </div>
        </div>

        <div class="flex gap-3 items-center mt-3">
            <p class="font-bold text-primaryColor">Delegate feedback survey link: </p>
            <p>{{ $eventData['eventWebViewLinks']['delegate_feedback_survey_link'] ?? 'N/A' }}</p>
        </div>

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">App feedback survey link: </p>
            <p>{{ $eventData['eventWebViewLinks']['app_feedback_survey_link'] ?? 'N/A' }}</p>
        </div>

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">About event link: </p>
            <p>{{ $eventData['eventWebViewLinks']['about_event_link'] ?? 'N/A' }}</p>
        </div>

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">Venue link: </p>
            <p>{{ $eventData['eventWebViewLinks']['venue_link'] ?? 'N/A' }}</p>
        </div>

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">Press releases link: </p>
            <p>{{ $eventData['eventWebViewLinks']['press_releases_link'] ?? 'N/A' }}</p>
        </div>

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">Slido link: </p>
            <p>{{ $eventData['eventWebViewLinks']['slido_link'] ?? 'N/A' }}</p>
        </div>

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">Shuttle Bus Schedule link: </p>
            <p>{{ $eventData['eventWebViewLinks']['shuttle_bus_schedule_link'] ?? 'N/A' }}</p>
        </div>
    </div>

    {{-- EVENT WEBVIEW LINKS --}}
    <div class="border border-primaryColor rounded-2xl py-5 px-7 mt-5">
        <div class="flex items-center justify-between">
            <h1 class="text-headingTextColor text-3xl font-bold">Event Floor Plan Links</h1>
            <div>
                <button wire:click="showEditEventFloorPlanLinks"
                    class="bg-yellow-500 hover:bg-yellow-600 text-white font-medium py-2 px-5 rounded-md inline-flex items-center text-sm">
                    <span class="mr-2"><i class="fa-solid fa-file-pen"></i></span>
                    <span>Edit</span>
                </button>
            </div>
        </div>

        <div class="flex gap-3 items-center mt-3">
            <p class="font-bold text-primaryColor">Floor plan 3d image link: </p>
            <p>{{ $eventData['eventFloorPlanLinks']['floor_plan_3d_image_link'] ?? 'N/A' }}</p>
        </div>

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">Floor plan exhibition image link: </p>
            <p>{{ $eventData['eventFloorPlanLinks']['floor_plan_exhibition_image_link'] ?? 'N/A' }}</p>
        </div>

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">Interactive map link: </p>
            <p>{{ $eventData['eventFloorPlanLinks']['interactive_map_link'] ?? 'N/A' }}</p>
        </div>
    </div>

    {{-- EVENT HTML TEXTS --}}
    <div class="border border-primaryColor rounded-2xl py-5 px-7 mt-5">
        <div class="flex items-center justify-between">
            <h1 class="text-headingTextColor text-3xl font-bold">Event HTML Texts</h1>
            <div>
                <button wire:click="showEditEventHTMLTexts"
                    class="bg-yellow-500 hover:bg-yellow-600 text-white font-medium py-2 px-5 rounded-md inline-flex items-center text-sm">
                    <span class="mr-2"><i class="fa-solid fa-file-pen"></i></span>
                    <span>Edit</span>
                </button>
            </div>
        </div>

        <div class="flex gap-3 items-center mt-3">
            <p class="font-bold text-primaryColor">Description HTML Text: </p>
            <p>{{ $eventData['eventHTMLTexts']['description_html_text'] ?? 'N/A' }}</p>
        </div>

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">Login HTML Text: </p>
            <p>{{ $eventData['eventHTMLTexts']['login_html_text'] ?? 'N/A' }}</p>
        </div>

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">Continue as guest HTML Text: </p>
            <p>{{ $eventData['eventHTMLTexts']['continue_as_guest_html_text'] ?? 'N/A' }}</p>
        </div>

        <div class="flex gap-3 items-center mt-1">
            <p class="font-bold text-primaryColor">Forgot password HTML Text: </p>
            <p>{{ $eventData['eventHTMLTexts']['forgot_password_html_text'] ?? 'N/A' }}</p>
        </div>
    </div>


    {{-- SPONSORS BANNER CAROUSEL --}}
    <div class="border border-primaryColor rounded-2xl py-5 px-7 mt-5">
        <div class="flex items-center justify-between">
            <h1 class="text-headingTextColor text-3xl font-bold">Sponsors Banner Carousel</h1>
            <div>
                <button wire:click="showEditSponsorsBannerCarousel"
                    class="bg-yellow-500 hover:bg-yellow-600 text-white font-medium py-2 px-5 rounded-md inline-flex items-center text-sm">
                    <span class="mr-2"><i class="fa-solid fa-file-pen"></i></span>
                    <span>Edit</span>
                </button>
            </div>
        </div>

        <div class="mt-3">
            @forelse ($eventData['eventDetails']['sponsors_banner_carousel'] ?? [] as $index => $url)
                <p class="mt-1">{{ $index + 1 }}. {{ $url }}</p>
            @empty
                <p>N/A</p>
            @endforelse
        </div>
    </div>
    


    {{-- VERTICAL IMAGES SECTION --}}
<div class="border border-primaryColor rounded-2xl py-5 px-7 mt-5">
    <div class="flex items-center justify-between">
        <h1 class="text-headingTextColor text-3xl font-bold">Vertical Images Section</h1>
        <div>
            <button wire:click="showEditVerticalImagesSection"
                class="bg-yellow-500 hover:bg-yellow-600 text-white font-medium py-2 px-5 rounded-md inline-flex items-center text-sm">
                <span class="mr-2"><i class="fa-solid fa-file-pen"></i></span>
                <span>Edit</span>
            </button>
        </div>
    </div>

    <div class="mt-3">
        @forelse ($eventData['eventDetails']['vertical_images_section'] ?? [] as $index => $url)
            <p class="mt-1">{{ $index + 1 }}. {{ $url }}</p>
        @empty
            <p>N/A</p>
        @endforelse
    </div>
</div>



    {{-- EVENT ASSETS --}}
    <div class="border border-primaryColor rounded-2xl py-5 px-7 mt-10">
        <h1 class="text-headingTextColor text-3xl font-bold">Event Assets</h1>

        <div class="grid grid-cols-3 gap-x-14 mt-10 items-start">
            <div class="col-span-1">
                <div class="flex items-center flex-col">
                    <div class="relative">
                        <p class="text-center">Event Logo</p>
                        <button wire:click="showEditEventAsset('Event Logo')"
                            class="absolute top-0 -right-6 cursor-pointer hover:text-yellow-600 text-yellow-500">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    </div>
                    @if ($eventData['eventAssets']['event_logo']['url'])
                        <img src="{{ $eventData['eventAssets']['event_logo']['url'] }}" class="mt-3 w-80">
                    @else
                        N/A
                    @endif
                </div>
            </div>

            <div class="col-span-1">
                <div class="flex items-center flex-col">
                    <div class="relative">
                        <p class="text-center">Event Logo inverted</p>
                        <button wire:click="showEditEventAsset('Event Logo inverted')"
                            class="absolute top-0 -right-6 cursor-pointer hover:text-yellow-600 text-yellow-500">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    </div>
                    @if ($eventData['eventAssets']['event_logo_inverted']['url'])
                        <img src="{{ $eventData['eventAssets']['event_logo_inverted']['url'] }}" class="mt-3 w-80">
                        <button
                            wire:click="deleteEventAsset('Event Logo inverted')"class="cursor-pointer hover:bg-red-500 bg-red-400 text-white text-sm py-1 px-5 rounded-md mt-4">
                            Remove image
                        </button>
                    @else
                        N/A
                    @endif
                </div>
            </div>

            <div class="col-span-1">
                <div class="flex items-center flex-col">
                    <div class="relative">
                        <p class="text-center">App Sponsor logo</p>
                        <button wire:click="showEditEventAsset('App Sponsor logo')"
                            class="absolute top-0 -right-6 cursor-pointer hover:text-yellow-600 text-yellow-500">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    </div>
                    @if ($eventData['eventAssets']['app_sponsor_logo']['url'])
                        <img src="{{ $eventData['eventAssets']['app_sponsor_logo']['url'] }}" class="mt-3 w-80">
                        <button
                            wire:click="deleteEventAsset('App Sponsor logo')"class="cursor-pointer hover:bg-red-500 bg-red-400 text-white text-sm py-1 px-5 rounded-md mt-4">
                            Remove image
                        </button>
                    @else
                        N/A
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-x-14 mt-20 items-start">
            <div class="col-span-1">
                <div class="flex items-center flex-col">
                    <div class="relative">
                        <p class="text-center">Event Banner</p>
                        <button wire:click="showEditEventAsset('Event Banner')"
                            class="absolute top-0 -right-6 cursor-pointer hover:text-yellow-600 text-yellow-500">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    </div>
                    @if ($eventData['eventAssets']['event_banner']['url'])
                        <img src="{{ $eventData['eventAssets']['event_banner']['url'] }}" class="mt-3 w-80">
                        <button
                            wire:click="deleteEventAsset('Event Banner')"class="cursor-pointer hover:bg-red-500 bg-red-400 text-white text-sm py-1 px-5 rounded-md mt-4">
                            Remove image
                        </button>
                    @else
                        N/A
                    @endif
                </div>
            </div>

            <div class="col-span-1">
                <div class="flex items-center flex-col">
                    <div class="relative">
                        <p class="text-center">App Sponsor banner</p>
                        <button wire:click="showEditEventAsset('App Sponsor banner')"
                            class="absolute top-0 -right-6 cursor-pointer hover:text-yellow-600 text-yellow-500">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    </div>
                    @if ($eventData['eventAssets']['app_sponsor_banner']['url'])
                        <img src="{{ $eventData['eventAssets']['app_sponsor_banner']['url'] }}" class="mt-3 w-80">
                        <button
                            wire:click="deleteEventAsset('App Sponsor banner')"class="cursor-pointer hover:bg-red-500 bg-red-400 text-white text-sm py-1 px-5 rounded-md mt-4">
                            Remove image
                        </button>
                    @else
                        N/A
                    @endif
                </div>
            </div>

            <div class="col-span-1">
                <div class="flex items-center flex-col">
                    <div class="relative">
                        <p class="text-center">Event splash screen</p>
                        <button wire:click="showEditEventAsset('Event splash screen')"
                            class="absolute top-0 -right-6 cursor-pointer hover:text-yellow-600 text-yellow-500">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    </div>
                    @if ($eventData['eventAssets']['event_splash_screen']['url'])
                        <img src="{{ $eventData['eventAssets']['event_splash_screen']['url'] }}" class="mt-3 w-80">
                        <button
                            wire:click="deleteEventAsset('Event splash screen')"class="cursor-pointer hover:bg-red-500 bg-red-400 text-white text-sm py-1 px-5 rounded-md mt-4">
                            Remove image
                        </button>
                    @else
                        N/A
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($chooseImageModal)
        @include('livewire.common.choose_image_modal')
    @endif

    @if ($editEventHTMLTextsForm)
        @include('livewire.event.details.edit_html_texts_details')
    @endif

    @if ($editEventDetailsForm)
        @include('livewire.event.details.edit_details')
    @endif

    @if ($editEventWebViewLinksForm)
        @include('livewire.event.details.edit_webview_links')
    @endif

    @if ($editEventFloorPlanLinksForm)
        @include('livewire.event.details.edit_floor_plan_links')
    @endif

    @if ($editEventColorsForm)
        @include('livewire.event.details.edit_colors')
    @endif

    @if ($editEventAssetForm)
        @include('livewire.event.details.edit_asset')
    @endif

    @if ($editSponsorsBannerCarouselForm)
        @include('livewire.event.details.edit_sponsors_banner_carousel')
    @endif

    @if ($editVerticalImagesSectionForm)
    @include('livewire.event.details.edit_vertical_images_section')
@endif
</div>
