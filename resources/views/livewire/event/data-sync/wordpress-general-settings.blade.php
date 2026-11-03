<div class="border border-primaryColor rounded-2xl py-5 px-7 mt-5">
    <h2 class="text-headingTextColor text-2xl font-bold">
        WordPress General Settings
    </h2>

    <p class="mt-2 text-sm text-gray-600">
        WordPress website configuration for this event.
        Saving does not fetch or import records.
    </p>

    @if (session()->has('wordpressGeneralSettingsSaved'))
        <p class="text-green-700 mt-3">
            {{ session('wordpressGeneralSettingsSaved') }}
        </p>
    @endif

    <form
        wire:submit.prevent="saveWordPressGeneralSettings"
        class="mt-4">

        <label
            for="wp-general-source-url"
            class="block text-sm font-medium text-gray-700 mb-2">
            WordPress website URL
        </label>

        <input
            id="wp-general-source-url"
            type="url"
            wire:model.defer="wpSourceUrl"
            placeholder="https://your-wordpress-website.com"
            class="border border-gray-300 rounded-lg px-3 py-2 w-full"
            required>

        @error('wpSourceUrl')
            <p class="text-sm text-red-600 mt-2">
                {{ $message }}
            </p>
        @enderror

        <p class="text-xs text-gray-500 mt-2">
            Enter the website base URL, not a wp-json endpoint.
            A WordPress subdirectory is allowed.
        </p>

        <button
            type="submit"
            wire:loading.attr="disabled"
            class="bg-primaryColor text-white rounded-lg px-4 py-2 mt-4">
            Save general settings
        </button>

        <p
            wire:loading
            wire:target="saveWordPressGeneralSettings"
            class="text-sm text-gray-500 mt-2">
            Saving WordPress settings...
        </p>
    </form>
</div>