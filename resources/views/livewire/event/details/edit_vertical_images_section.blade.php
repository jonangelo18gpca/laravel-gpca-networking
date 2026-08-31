<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl p-7 w-2/3">
        <div class="flex items-center justify-between mb-5">
            <h1 class="text-headingTextColor text-2xl font-bold">Edit Vertical Images Section</h1>
            <button wire:click="resetEditVerticalImagesSectionFields" class="text-gray-500 hover:text-gray-700">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <p class="text-sm text-gray-500 mb-2">Enter one image URL per line. Images will display top to bottom, in this order.</p>

        <textarea wire:model.defer="vertical_images_section_text" rows="10"
            class="w-full border border-gray-300 rounded-md p-3 text-sm font-mono"
            placeholder="https://example.com/image1.jpg&#10;https://example.com/image2.jpg"></textarea>

        @error('vertical_images_section_text')
            <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
        @enderror

        <div class="flex gap-3 justify-end mt-6">
            <button wire:click="resetEditVerticalImagesSectionFields"
                class="bg-gray-300 hover:bg-gray-400 text-gray-800 text-sm py-2 px-5 rounded-md">
                Cancel
            </button>
            <button wire:click="editVerticalImagesSectionConfirmation"
                class="bg-primaryColor hover:bg-primaryColorHover text-white text-sm py-2 px-5 rounded-md">
                Save
            </button>
        </div>
    </div>
</div>