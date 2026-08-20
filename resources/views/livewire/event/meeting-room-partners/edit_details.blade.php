<div class="fixed z-20 inset-0 overflow-y-auto">
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
                        Edit meeting room partner
                    </div>

                    <div class="mt-5">
                        <div>
                            <div class="text-primaryColor">
                                Name <span class="text-red-500">*</span>
                            </div>
                            <div class="mt-2">
                                <input placeholder="Name" type="text" wire:model.lazy="name"
                                    class="bg-registrationInputFieldsBGColor w-full py-1 px-3 outline-primaryColor rounded-md border border-gray-200">

                                @error('name')
                                    <div class="text-red-500 text-xs italic mt-1">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mt-5">
                        <div>
                            <div class="text-primaryColor">
                                Location
                            </div>
                            <div class="mt-2">
                                <input placeholder="Location" type="text" wire:model.lazy="location"
                                    class="bg-registrationInputFieldsBGColor w-full py-1 px-3 outline-primaryColor rounded-md border border-gray-200">
                            </div>
                        </div>
                    </div>

                    <hr class="mt-7 my-5">

                    <div class="grid grid-cols-2 gap-5">
                        <div class="col-span-1">
                            <div class="text-primaryColor">
                                Country
                            </div>
                            <div>
                                <input placeholder="UAE" type="text" wire:model.lazy="country"
                                    class="bg-registrationInputFieldsBGColor w-full py-1 px-3 outline-primaryColor rounded-md border border-gray-200">
                            </div>
                        </div>

                        <div class="col-span-1">
                            <div class="text-primaryColor">
                                Contact name
                            </div>
                            <div>
                                <input type="text" wire:model.lazy="contact_person_name"
                                    class="bg-registrationInputFieldsBGColor w-full py-1 px-3 outline-primaryColor rounded-md border border-gray-200">
                            </div>
                        </div>

                        <div class="col-span-1">
                            <div class="text-primaryColor">
                                Email address
                            </div>
                            <div>
                                <input placeholder="user@gmail.com" type="email" wire:model.lazy="email_address"
                                    class="bg-registrationInputFieldsBGColor w-full py-1 px-3 outline-primaryColor rounded-md border border-gray-200">
                            </div>
                        </div>

                        <div class="col-span-1">
                            <div class="text-primaryColor">
                                Mobile number
                            </div>
                            <div>
                                <input placeholder="xxxxxxxxxx" type="text" wire:model.lazy="mobile_number"
                                    class="bg-registrationInputFieldsBGColor w-full py-1 px-3 outline-primaryColor rounded-md border border-gray-200">
                            </div>
                        </div>

                        <div class="col-span-1">
                            <div class="text-primaryColor">
                                Website
                            </div>
                            <div>
                                <input placeholder="" type="text" wire:model.lazy="website"
                                    class="bg-registrationInputFieldsBGColor w-full py-1 px-3 outline-primaryColor rounded-md border border-gray-200">
                            </div>
                        </div>

                        <div class="col-span-1">
                            <div class="text-primaryColor">
                                Facebook
                            </div>
                            <div>
                                <input placeholder="" type="text" wire:model.lazy="facebook"
                                    class="bg-registrationInputFieldsBGColor w-full py-1 px-3 outline-primaryColor rounded-md border border-gray-200">
                            </div>
                        </div>

                        <div class="col-span-1">
                            <div class="text-primaryColor">
                                Linkedin
                            </div>
                            <div>
                                <input placeholder="" type="text" wire:model.lazy="linkedin"
                                    class="bg-registrationInputFieldsBGColor w-full py-1 px-3 outline-primaryColor rounded-md border border-gray-200">
                            </div>
                        </div>

                        <div class="col-span-1">
                            <div class="text-primaryColor">
                                Twitter
                            </div>
                            <div>
                                <input placeholder="" type="text" wire:model.lazy="twitter"
                                    class="bg-registrationInputFieldsBGColor w-full py-1 px-3 outline-primaryColor rounded-md border border-gray-200">
                            </div>
                        </div>

                        <div class="col-span-2">
                            <div class="text-primaryColor">
                                Instagram
                            </div>
                            <div>
                                <input placeholder="" type="text" wire:model.lazy="instagram"
                                    class="bg-registrationInputFieldsBGColor w-full py-1 px-3 outline-primaryColor rounded-md border border-gray-200">
                            </div>
                        </div>

                        <div class="col-span-2">
                            <div class="text-primaryColor">
                                Floorplan Link
                            </div>
                            <div>
                                <input placeholder="" type="text" wire:model.lazy="floorplan_link"
                                    class="bg-registrationInputFieldsBGColor w-full py-1 px-3 outline-primaryColor rounded-md border border-gray-200">
                            </div>
                        </div>
                    </div>

                    <hr class="mt-7 my-5">

                    <div>
                        <div>
                            <div class="text-primaryColor">
                                Company profile
                            </div>
                            <div class="mt-2">
                                <textarea wire:model.lazy="profile_html_text" cols="30" rows="10"
                                    class="bg-registrationInputFieldsBGColor w-full py-1 px-3 outline-primaryColor rounded-md border border-gray-200">{{ $profile_html_text }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm"
                        wire:click.prevent="editMeetingRoomPartnerDetailsConfirmation">Update</button>
                    <button type="button"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                        wire:click.prevent="resetEditMeetingRoomPartnerDetailsFields">Cancel</button>
                </div>
            </div>
        </div>
    </form>
</div>
