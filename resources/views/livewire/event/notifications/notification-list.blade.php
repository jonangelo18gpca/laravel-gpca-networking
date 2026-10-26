<div>
    <h1 class="text-headingTextColor text-3xl font-bold">Notifications</h1>
    

    
    <!-- Floating Add Session Button -->
    <div class="fixed bottom-0 left-6 mb-5 z-50">
        <button type="button" wire:click.prevent="showAddNotification"
            class="w-14 h-14 rounded-full bg-primaryColor hover:bg-primaryColorHover text-white shadow-lg flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primaryColor">
            <i class="fa-solid fa-plus text-xl"></i>
        </button>
    </div>

    <div class="flex justify-between mt-5">
        <button type="button" wire:click.prevent="showAddNotification" wire:key="showAddNotification"
            class="bg-primaryColor hover:bg-primaryColorHover text-white rounded-lg text-sm w-36 h-10">Add
            notification</button>
    </div>

    @if (count($finalListOfNotifications) == 0)
        <div class="bg-red-400 text-white text-center py-3 mt-5 rounded-md">
            There are no notifications yet.
        </div>
    @else
        <p class="mt-5">Total notifications: {{ count($finalListOfNotifications) }}</p>

        <div class="shadow-lg my-5 bg-white rounded-md">
            <div
                class="grid grid-cols-12 pt-2 pb-2 mt-3 text-center items-center gap-10 text-sm text-white bg-primaryColor rounded-tl-md rounded-tr-md">
                <div class="col-span-1">No.</div>
                <div class="col-span-1">Date</div>
                <div class="col-span-1">Time</div>
                <div class="col-span-1">Type</div>
                <div class="col-span-2">Title</div>
                <div class="col-span-1">Subtitle</div>
                <div class="col-span-3">Message</div>
                <div class="col-span-1">Sent</div>
                <div class="col-span-1">Action</div>
            </div>
            @foreach ($finalListOfNotifications as $index => $finalListOfNotification)
                <div
                    class="grid grid-cols-12 gap-10 pt-2 pb-2 mb-1 text-center items-center text-sm {{ $index % 2 == 0 ? 'bg-registrationInputFieldsBGColor' : 'bg-registrationCardBGColor' }}">
                    <div class="col-span-1">{{ $index + 1 }}</div>
                    <div class="col-span-1">{{ $finalListOfNotification['send_date'] }}</div>
                    <div class="col-span-1">{{ $finalListOfNotification['send_time'] }}</div>
                    <div class="col-span-1">{{ $finalListOfNotification['type'] }}</div>
                    <div class="col-span-2">{{ $finalListOfNotification['title'] }}</div>
                    <div class="col-span-1">{{ $finalListOfNotification['subtitle'] ?? 'N/A' }}</div>
                    <div class="col-span-3 text-xs">{{ $finalListOfNotification['message'] ?? 'N/A' }}</div>
                    <div class="col-span-1">
                        @if ($finalListOfNotification['is_sent'])
                            <div
                                class="text-gray-700 bg-green-300 py-1 px-1 text-sm rounded-md">
                                Yes
                            </div>
                        @else
                            <div
                                class="text-gray-700 bg-red-300 py-1 px-1 text-sm rounded-md">
                                No
                            </div>
                        @endif
                    </div>

                    <div class="col-span-1 flex gap-3 items-center justify-center">
                        <button wire:click="showEditNotification({{ $index }})"
                            class="cursor-pointer hover:text-yellow-600 text-yellow-500 text-sm ">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button wire:click="deleteNotificationConfirmation({{ $index }})"
                            class="cursor-pointer hover:text-red-600 text-red-500 text-sm ">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($addNotificationForm)
        @include('livewire.event.notifications.add_notification')
    @endif

    @if ($editNotificationForm)
        @include('livewire.event.notifications.edit_notification')
    @endif


    <section
    aria-label="Bulk notification import"
    style="
        margin-top: 24px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        background: #ffffff;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
    "
>
    {{-- HEADER --}}
    <div
        style="
            padding: 22px 24px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        "
    >
        <h2
            style="
                display: flex;
                align-items: center;
                gap: 10px;
                margin: 0;
                color: #0f172a;
                font-size: 18px;
                font-weight: 700;
            "
        >
            <i
                class="fa-solid fa-file-import"
                aria-hidden="true"
                style="color: #64748b;"
            ></i>

            Bulk import notifications
        </h2>

        <p
            style="
                margin: 8px 0 0;
                color: #64748b;
                font-size: 13px;
                line-height: 1.7;
            "
        >
            Download the template, fill in your schedule, then upload
            the completed CSV.
        </p>
    </div>

    {{-- RESPONSIVE DOWNLOAD / UPLOAD SECTIONS --}}
    <div
        style="
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            padding: 24px;
        "
    >
        {{-- DOWNLOAD --}}
        <div
            style="
                flex: 1 1 280px;
                min-width: 0;
            "
        >
            <div
                style="
                    display: inline-flex;
                    align-items: center;
                    gap: 7px;
                    margin-bottom: 12px;
                    padding: 5px 10px;
                    border-radius: 20px;
                    background: #eff6ff;
                    color: #2563eb;
                    font-size: 11px;
                    font-weight: 700;
                    letter-spacing: 0.04em;
                    text-transform: uppercase;
                "
            >
                <i class="fa-solid fa-file-csv" aria-hidden="true"></i>
                Step 01 · Prepare
            </div>

            <h3
                style="
                    margin: 0;
                    color: #0f172a;
                    font-size: 16px;
                    font-weight: 600;
                "
            >
                Download your template
            </h3>

            <p
                style="
                    margin: 8px 0 0;
                    color: #64748b;
                    font-size: 13px;
                    line-height: 1.7;
                "
            >
                Use the provided CSV template with these columns:
            </p>

            <div
                style="
                    display: flex;
                    flex-wrap: wrap;
                    gap: 6px;
                    margin-top: 12px;
                "
            >
                @foreach (['Date', 'Time', 'Type', 'Title', 'Subtitle', 'Message'] as $column)
                    <span
                        style="
                            padding: 4px 9px;
                            border: 1px solid #e2e8f0;
                            border-radius: 6px;
                            background: #f8fafc;
                            color: #475569;
                            font-size: 11px;
                            font-weight: 500;
                        "
                    >
                        {{ $column }}
                    </span>
                @endforeach
            </div>

            <button
                type="button"
                wire:click="downloadNotificationTemplate"
                wire:loading.attr="disabled"
                wire:target="downloadNotificationTemplate"
                style="
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 9px;
                    margin-top: 20px;
                    padding: 12px 18px;
                    border: 1px solid #cbd5e1;
                    border-radius: 10px;
                    background: #ffffff;
                    color: #334155;
                    font-size: 13px;
                    font-weight: 600;
                    cursor: pointer;
                    box-shadow: 0 2px 4px rgba(15, 23, 42, 0.04);
                "
            >
                <i class="fa-solid fa-download" aria-hidden="true"></i>

                <span
                    wire:loading.remove
                    wire:target="downloadNotificationTemplate"
                >
                    Download template
                </span>

                <span
                    wire:loading
                    wire:target="downloadNotificationTemplate"
                >
                    Preparing...
                </span>
            </button>

            <div
                style="
                    margin-top: 16px;
                    color: #64748b;
                    font-size: 12px;
                    line-height: 1.9;
                "
            >
                <div>Date: YYYY-MM-DD or DD/MM/YYYY</div>
                <div>Time: 14:30, 1430, or 2:30 PM</div>
                <div>Save your completed file as CSV UTF-8.</div>
            </div>
        </div>

        {{-- UPLOAD --}}
        <form
            wire:submit.prevent="importNotifications"
            style="
                flex: 1.2 1 320px;
                min-width: 0;
                margin: 0;
            "
        >
            <div
                style="
                    display: inline-flex;
                    align-items: center;
                    gap: 7px;
                    margin-bottom: 12px;
                    padding: 5px 10px;
                    border-radius: 20px;
                    background: #f0fdf4;
                    color: #15803d;
                    font-size: 11px;
                    font-weight: 700;
                    letter-spacing: 0.04em;
                    text-transform: uppercase;
                "
            >
                <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                Step 02 · Upload
            </div>

            <h3
                style="
                    margin: 0;
                    color: #0f172a;
                    font-size: 16px;
                    font-weight: 600;
                "
            >
                Import your completed template
            </h3>

            <p
                style="
                    margin: 8px 0 0;
                    color: #64748b;
                    font-size: 13px;
                    line-height: 1.7;
                "
            >
                Select your completed CSV to add notifications to this event.
            </p>

            {{-- STYLED FILE PICKER --}}
            <div
                style="
                    position: relative;
                    margin-top: 16px;
                    padding: 22px 16px;
                    overflow: hidden;
                    border: 2px dashed #cbd5e1;
                    border-radius: 12px;
                    background: #f8fafc;
                    text-align: center;
                "
            >
                <div
                    style="
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        width: 46px;
                        height: 46px;
                        margin-bottom: 10px;
                        border-radius: 12px;
                        background: #e2e8f0;
                        color: #475569;
                        font-size: 20px;
                    "
                >
                    <i class="fa-solid fa-file-arrow-up" aria-hidden="true"></i>
                </div>

                <label
                    for="notification-import-file"
                    style="
                        display: block;
                        margin: 0;
                        color: #334155;
                        font-size: 13px;
                        font-weight: 600;
                    "
                >
                    Click to choose your CSV file
                </label>

                <p
                    style="
                        margin: 6px 0 0;
                        color: #94a3b8;
                        font-size: 12px;
                        line-height: 1.6;
                    "
                >
                    CSV only · Maximum 2 MB · Up to 500 notifications
                </p>

                <input
                    id="notification-import-file"
                    type="file"
                    accept=".csv,text/csv"
                    wire:model="notificationImportFile"
                    style="
                        position: absolute;
                        inset: 0;
                        width: 100%;
                        height: 100%;
                        opacity: 0;
                        cursor: pointer;
                    "
                >
            </div>

            {{-- UPLOAD PROGRESS --}}
            <div
                wire:loading
                wire:target="notificationImportFile"
                role="status"
                style="
                    margin-top: 10px;
                    color: #2563eb;
                    font-size: 12px;
                "
            >
                <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                Uploading file...
            </div>

            {{-- SELECTED FILE --}}
            @if ($notificationImportFile)
                <div
                    wire:loading.remove
                    wire:target="notificationImportFile"
                    style="
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        margin-top: 12px;
                        padding: 10px 12px;
                        border: 1px solid #bbf7d0;
                        border-radius: 8px;
                        background: #f0fdf4;
                        color: #166534;
                        font-size: 12px;
                        overflow-wrap: anywhere;
                    "
                >
                    <i class="fa-solid fa-file-circle-check" aria-hidden="true"></i>

                    {{ $notificationImportFile->getClientOriginalName() }}
                </div>
            @endif

            {{-- VALIDATION ERROR --}}
            @error('notificationImportFile')
                <div
                    role="alert"
                    style="
                        margin-top: 12px;
                        padding: 10px 12px;
                        border: 1px solid #fecaca;
                        border-radius: 8px;
                        background: #fef2f2;
                        color: #b91c1c;
                        font-size: 12px;
                        line-height: 1.7;
                    "
                >
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    {{ $message }}
                </div>
            @enderror

            {{-- IMPORT BUTTON --}}
            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="notificationImportFile,importNotifications"
                @if (!$notificationImportFile) disabled @endif
                style="
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    width: 100%;
                    margin-top: 16px;
                    padding: 13px 18px;
                    border: 0;
                    border-radius: 10px;
                    background: {{ $notificationImportFile ? '#0f172a' : '#cbd5e1' }};
                    color: #ffffff;
                    font-size: 13px;
                    font-weight: 600;
                    cursor: {{ $notificationImportFile ? 'pointer' : 'not-allowed' }};
                "
            >
                <span
                    wire:loading.remove
                    wire:target="importNotifications"
                >
                    <i class="fa-solid fa-upload" aria-hidden="true"></i>
                    Import notifications
                </span>

                <span
                    wire:loading
                    wire:target="importNotifications"
                >
                    <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                    Importing notifications...
                </span>
            </button>

            <p
                style="
                    margin: 10px 0 0;
                    color: #94a3b8;
                    font-size: 11px;
                    line-height: 1.7;
                "
            >
                Uploading the same rows again creates duplicate notifications.
            </p>
        </form>
    </div>

    {{-- SUCCESS MESSAGE --}}
    @if ($notificationImportResult)
        <div
            role="status"
            style="
                display: flex;
                align-items: center;
                gap: 9px;
                margin: 0 24px 20px;
                padding: 13px 16px;
                border: 1px solid #bbf7d0;
                border-radius: 10px;
                background: #f0fdf4;
                color: #166534;
                font-size: 13px;
                line-height: 1.6;
            "
        >
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            {{ $notificationImportResult }}
        </div>
    @endif

    {{-- FOOTER --}}
    <div
        style="
            padding: 15px 24px;
            border-top: 1px solid #e2e8f0;
            background: #fafbfc;
            color: #64748b;
            font-size: 12px;
            line-height: 1.8;
            overflow-wrap: anywhere;
        "
    >
        <div>
            <i class="fa-regular fa-clock" aria-hidden="true"></i>
            Schedule timezone: {{ config('app.timezone') }}.
            Future schedules only. Military time displays as AM/PM.
        </div>

        <details style="margin-top: 8px;">
            <summary
                style="
                    color: #475569;
                    font-weight: 600;
                    cursor: pointer;
                "
            >
                View allowed notification types
            </summary>

            <div
                style="
                    display: flex;
                    flex-wrap: wrap;
                    gap: 6px;
                    margin-top: 10px;
                "
            >
                @foreach (config('app.notificationTypes', []) as $notificationType)
                    <span
                        style="
                            padding: 3px 8px;
                            border: 1px solid #e2e8f0;
                            border-radius: 5px;
                            background: #ffffff;
                            color: #475569;
                            font-size: 11px;
                        "
                    >
                        {{ $notificationType }}
                    </span>
                @endforeach
            </div>
        </details>
    </div>
</section>
</div>
