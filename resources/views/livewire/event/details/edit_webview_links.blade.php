<div class="fixed z-10 inset-0 overflow-y-auto">
    <form>
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen"></span>&#8203;
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                role="dialog" aria-modal="true" aria-labelledby="modal-headline">

                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="text-primaryColor italic font-bold text-xl">
                        Edit WebView links
                    </div>

                    <div class="space-y-2 col-span-2 grid grid-cols-2 gap-5 items-start mt-5">
                        <div class="col-span-2 mt-2">
                            <div class="text-primaryColor">
                                Delegate feedback survey link
                            </div>
                            <div class="mt-2">
                                <input placeholder="https://gpca.org.ae/conferences/anc/delegate-feedback/"
                                    type="text" wire:model.lazy="delegate_feedback_survey_link"
                                    class="bg-registrationInputFieldsBGColor w-full py-1 px-3 outline-primaryColor rounded-md border border-gray-200">
                            </div>
                        </div>

                        <div class="col-span-2 mt-2">
                            <div class="text-primaryColor">
                                App feedback survey link
                            </div>
                            <div class="mt-2">
                                <input placeholder="https://gpca.org.ae/conferences/anc/delegate-feedback/"
                                    type="text" wire:model.lazy="app_feedback_survey_link"
                                    class="bg-registrationInputFieldsBGColor w-full py-1 px-3 outline-primaryColor rounded-md border border-gray-200">
                            </div>
                        </div>

                        <div class="col-span-2 mt-2">
                            <div class="text-primaryColor">
                                About event link
                            </div>
                            <div class="mt-2">
                                <input placeholder="https://gpca.org.ae/conferences/anc/delegate-feedback/"
                                    type="text" wire:model.lazy="about_event_link"
                                    class="bg-registrationInputFieldsBGColor w-full py-1 px-3 outline-primaryColor rounded-md border border-gray-200">
                            </div>
                        </div>

                        <div class="col-span-2 mt-2">
                            <div class="text-primaryColor">
                                Venue link
                            </div>
                            <div class="mt-2">
                                <input placeholder="https://gpca.org.ae/conferences/anc/delegate-feedback/"
                                    type="text" wire:model.lazy="venue_link"
                                    class="bg-registrationInputFieldsBGColor w-full py-1 px-3 outline-primaryColor rounded-md border border-gray-200">
                            </div>
                        </div>

                        <div class="col-span-2 mt-2">
                            <div class="text-primaryColor">
                                Press releases link
                            </div>
                            <div class="mt-2">
                                <input placeholder="https://gpca.org.ae/conferences/anc/delegate-feedback/"
                                    type="text" wire:model.lazy="press_releases_link"
                                    class="bg-registrationInputFieldsBGColor w-full py-1 px-3 outline-primaryColor rounded-md border border-gray-200">
                            </div>
                        </div>

                        <div class="col-span-2 mt-2">
                            <div class="text-primaryColor">
                                Slido link
                            </div>
                            <div class="mt-2">
                                <input placeholder="https://app.sli.do/event/uqXFfmDSuepN3oxrUH6zBM" type="text"
                                    wire:model.lazy="slido_link"
                                    class="bg-registrationInputFieldsBGColor w-full py-1 px-3 outline-primaryColor rounded-md border border-gray-200">
                            </div>
                        </div>

                        <div class="col-span-2 mt-2">
                            <div class="text-primaryColor">
                                Shuttle Bus Schedule Link
                            </div>
                            <div class="mt-2">
                                <input placeholder="https://gpca.org.ae/conferences/anc/bus-schedule.pdf" type="text"
                                    wire:model.lazy="shuttle_bus_schedule_link"
                                    class="bg-registrationInputFieldsBGColor w-full py-1 px-3 outline-primaryColor rounded-md border border-gray-200">
                            </div>
                        </div>

                        <div class="col-span-2 mt-2">
                            <div class="text-primaryColor">
                                Agenda Title
                            </div>

                            <div class="mt-2">
                                <input type="text" placeholder="Example: 19th Annual GPCA Forum Agenda (PDF)"
                           wire:model.defer="agenda_title"

                                    class="bg-registrationInputFieldsBGColor w-full py-1 px-3 outline-primaryColor rounded-md border border-gray-200">
                            </div>

                            @error('agenda_title')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-span-2 mt-2">
                            <div class="text-primaryColor">
                                Agenda PDF URL
                            </div>

                            <div class="mt-2">
                                <input type="url" placeholder="https://example.com/agenda.pdf"
                                    wire:model.defer="agenda_url"
                                    class="bg-registrationInputFieldsBGColor w-full py-1 px-3 outline-primaryColor rounded-md border border-gray-200">
                            </div>

                            @error('agenda_url')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>


                    </div>

                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse mt-5">
                        <button type="button"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm"
                            wire:click.prevent="editEventWebViewLinksConfirmation">Update</button>
                        <button type="button"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            wire:click.prevent="resetEditEventWebViewLinksFields">Cancel</button>
                    </div>
                </div>
            </div>
    </form>
</div>
