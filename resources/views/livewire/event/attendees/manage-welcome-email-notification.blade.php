<div>
    <h1 class="text-headingTextColor text-3xl font-bold">Manage welcome email notification</h1>

    <!-- Floating Add Session Button -->
    <div class="fixed bottom-0 left-6 mb-5 z-50">
        @if (count($selectedAttendees) > 0)
            <button type="button" wire:click.prevent="sendWelcomeEmailConfirmationBulkConfirmation"
                wire:key="sendWelcomeEmailConfirmationBulkConfirmation2"
                class="bg-primaryColor hover:bg-primaryColorHover text-white rounded text-sm w-60 h-9">Send to selected
                attendees ({{ count($selectedAttendees) }})</button>
        @else
            <button type="button" class="bg-gray-400 cursor-not-allowed text-white rounded text-sm w-60 h-9"
                disabled>Send to selected attendees</button>
        @endif
    </div>

    <div class="flex gap-3 mt-5 items-center">
        <a href="{{ route('admin.event.attendees.view', ['eventCategory' => $event->category, 'eventId' => $event->id]) }}"
            class="bg-red-500 hover:bg-red-400 text-white font-medium py-2 px-5 rounded inline-flex items-center text-sm">
            <span class="mr-2"><i class="fa-sharp fa-solid fa-arrow-left"></i></span>
            <span>List of attendees</span>
        </a>

        @if (count($selectedAttendees) > 0)
            <button type="button" wire:click.prevent="sendWelcomeEmailConfirmationBulkConfirmation"
                wire:key="sendWelcomeEmailConfirmationBulkConfirmation1"
                class="bg-primaryColor hover:bg-primaryColorHover text-white rounded text-sm w-60 h-9">Send to selected
                attendees</button>
        @else
            <button type="button" class="bg-gray-400 cursor-not-allowed text-white rounded text-sm w-60 h-9"
                disabled>Send to selected attendees</button>
        @endif
    </div>

    @if (count($finalListOfAttendees) == 0)
        <div class="bg-red-400 text-white text-center py-3 mt-5 rounded-md">
            There are no attendees yet.
        </div>
    @else
        <p class="mt-5">Total attendees: {{ count($finalListOfAttendees) }}</p>
        <p>Applicable attendees to send: {{ $sendableAttendees }}</p>
        <p>Not applicable attendees to send: {{ count($finalListOfAttendees) - $sendableAttendees }}</p>


        {{-- SELECT UP TO 50 ELIGIBLE ATTENDEES --}}
        <div
            style="
        margin-top: 24px;
        padding: 18px 20px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #ffffff;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    ">
            <div
                style="
            margin-bottom: 6px;
            color: #0f172a;
            font-size: 15px;
            font-weight: 600;
        ">
                Select a batch of 50 attendees
            </div>

            <p
                style="
            margin: 0 0 16px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.7;
        ">
                Select attendees who have not logged in or changed their password,
                with the exact total sent count below.
            </p>

            <div
                style="
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 12px;
        ">
                <div>
                    <label for="welcome-email-total-sent-filter"
                        style="
                    display: block;
                    margin-bottom: 6px;
                    color: #334155;
                    font-size: 12px;
                    font-weight: 600;
                ">
                        Total sent equals
                    </label>

                    <input id="welcome-email-total-sent-filter" type="number" min="0" step="1"
                        wire:model.defer="filterWelcomeEmailTotalSent"
                        style="
                    width: 150px;
                    padding: 10px 12px;
                    border: 1px solid #cbd5e1;
                    border-radius: 8px;
                    background: #f8fafc;
                    color: #0f172a;
                    font-size: 14px;
                ">
                </div>

                <button type="button" wire:click="select50AttendeesBySentCount" wire:loading.attr="disabled"
                    wire:target="select50AttendeesBySentCount"
                    style="
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 11px 18px;
                border: 0;
                border-radius: 8px;
                background: #0f172a;
                color: #ffffff;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
            ">
                    <span wire:loading.remove wire:target="select50AttendeesBySentCount">
                        <i class="fa-solid fa-users" aria-hidden="true"></i>

                        Select up to 50
                    </span>

                    <span wire:loading wire:target="select50AttendeesBySentCount">
                        Selecting...
                    </span>
                </button>
            </div>

            @error('filterWelcomeEmailTotalSent')
                <div role="alert"
                    style="
                margin-top: 10px;
                color: #b91c1c;
                font-size: 12px;
            ">
                    {{ $message }}
                </div>
            @enderror

            @if ($select50AttendeesMessage)
                <div role="status"
                    style="
                margin-top: 14px;
                padding: 10px 12px;
                border: 1px solid #bfdbfe;
                border-radius: 8px;
                background: #eff6ff;
                color: #1e40af;
                font-size: 12px;
                line-height: 1.7;
            ">
                    {{ $select50AttendeesMessage }}
                </div>
            @endif
        </div>

        <div class="flex gap-5 mt-10">


            @if ($sendableAttendees == count($selectedAttendees))
                <button type="button" wire:click.prevent="unselectAllAttendee" wire:key="unselectAllAttendee"
                    class="bg-primaryColor hover:bg-primaryColorHover text-white rounded text-sm p-5 py-1">Unselect
                    all</button>
            @else
                <button type="button" wire:click.prevent="selectAllAttendee" wire:key="selectAllAttendee"
                    class="bg-primaryColor hover:bg-primaryColorHover text-white rounded text-sm p-5 py-1">Select
                    all</button>
            @endif
            <p>Selected attendees: {{ count($selectedAttendees) }}</p>
        </div>

        <div class="shadow-lg my-5 bg-white rounded-md">
            <div
                class="grid grid-cols-10 pt-2 pb-2 mt-3 text-center items-center gap-10 text-sm text-white bg-primaryColor rounded-tl-md rounded-tr-md">
                <div class="col-span-1">Badge number</div>
                <div class="col-span-1">Name</div>
                <div class="col-span-1">Job title</div>
                <div class="col-span-1">Email address</div>
                <div class="col-span-1">Company</div>
                <div class="col-span-1">Logged In</div>
                <div class="col-span-1">Password Changed</div>
                <div class="col-span-1">Total sent</div>
                <div class="col-span-1">Last sent</div>
                <div class="col-span-1">Action</div>
            </div>
            @foreach ($finalListOfAttendees as $index => $finalListOfAttendee)
                <div
                    class="grid grid-cols-10 gap-10 pt-2 pb-2 mb-1 text-center items-center text-sm {{ $index % 2 == 0 ? 'bg-registrationInputFieldsBGColor' : 'bg-registrationCardBGColor' }}">
                    @if ($finalListOfAttendee['is_password_resetted'])
                        <div class="col-span-1">{{ $finalListOfAttendee['badge_number'] }}</div>
                    @else
                        <div class="col-span-1 flex items-center gap-2">
                            <input type="checkbox" wire:model.lazy="selectedAttendees" value="{{ $index }}"
                                id="{{ $index }}">
                            <label for="{{ $index }}">{{ $finalListOfAttendee['badge_number'] }}</label>
                        </div>
                    @endif

                    <div class="col-span-1 break-words">{{ $finalListOfAttendee['name'] }}</div>
                    <div class="col-span-1 break-words">{{ $finalListOfAttendee['job_title'] }}</div>
                    <div class="col-span-1 break-words">{{ $finalListOfAttendee['email_address'] }}</div>
                    <div class="col-span-1 break-words">{{ $finalListOfAttendee['company_name'] }}</div>
                    <div class="col-span-1">
                        @if ($finalListOfAttendee['is_logged_in_already'])
                            <span class="bg-green-300 py-1 px-10">Yes</span>
                        @else
                            <span class="bg-red-300 py-1 px-10">No</span>
                        @endif
                    </div>
                    <div class="col-span-1">
                        @if ($finalListOfAttendee['is_password_resetted'])
                            <span class="bg-green-300 py-1 px-10">Yes</span>
                        @else
                            <span class="bg-red-300 py-1 px-10">No</span>
                        @endif
                    </div>
                    <div class="col-span-1">{{ $finalListOfAttendee['totatWelcomeEmailNotificationSent'] }}</div>
                    <div class="col-span-1">{{ $finalListOfAttendee['lasttWelcomeEmailNotificationSent'] }}</div>
                    <div class="col-span-1">
                        @if ($finalListOfAttendee['is_password_resetted'])
                            <button class="cursor-not-allowed bg-gray-400 py-1 px-6 rounded-md" disabled>Send
                                email</button>
                        @else
                            <button
                                class="cursor-pointer bg-primaryColor hover:bg-primaryColorHover text-white py-1 px-6 rounded-md"
                                wire:click.prevent="{{ "sendWelcomeEmailConfirmation($index)" }}">Send
                                email</button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
