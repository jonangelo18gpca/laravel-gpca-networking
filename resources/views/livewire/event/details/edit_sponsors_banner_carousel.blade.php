<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl p-7 w-2/3">
        <div class="flex items-center justify-between mb-5">
            <h1 class="text-headingTextColor text-2xl font-bold">Edit Sponsors Banner Carousel</h1>
            <button wire:click="resetEditSponsorsBannerCarouselFields" class="text-gray-500 hover:text-gray-700">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <p class="text-sm text-gray-500 mb-2">Enter one image URL per line.</p>

        <textarea wire:model.defer="sponsors_banner_carousel_text" rows="10"
            class="w-full border border-gray-300 rounded-md p-3 text-sm font-mono"
            placeholder="https://example.com/banner1.jpg&#10;https://example.com/banner2.jpg"></textarea>

        @error('sponsors_banner_carousel_text')
            <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
        @enderror

        <div class="flex gap-3 justify-end mt-6">
            <button wire:click="resetEditSponsorsBannerCarouselFields"
                class="bg-gray-300 hover:bg-gray-400 text-gray-800 text-sm py-2 px-5 rounded-md">
                Cancel
            </button>
            <button wire:click="editSponsorsBannerCarouselConfirmation"
                class="bg-primaryColor hover:bg-primaryColorHover text-white text-sm py-2 px-5 rounded-md">
                Save
            </button>
        </div>
    </div>
</div>