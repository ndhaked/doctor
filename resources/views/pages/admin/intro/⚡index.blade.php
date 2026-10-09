<?php

use App\Models\Setting;
use App\Support\IntroSection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    public bool $visible = true;
    public string $heading = '';
    public string $content = '';
    public string $image = '';
    public $imageFile = null;

    public ?string $status = null;

    public function mount(): void
    {
        $intro = IntroSection::content();

        $this->visible = $intro['visible'];
        $this->heading = $intro['heading'];
        $this->content = $intro['content'];
        $this->image = $intro['image'];
    }

    public function save(): void
    {
        $data = $this->validate([
            'visible' => ['boolean'],
            'heading' => ['required', 'string', 'max:200'],
            'content' => ['nullable', 'string', 'max:5000'],
            'imageFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'heading.required' => 'The heading cannot be empty.',
        ]);

        if ($this->imageFile) {
            $this->deleteUpload($this->image);
            $this->image = 'uploads/'.$this->imageFile->store('intro', 'uploads');
            $this->imageFile = null;
            Setting::put('intro.image', $this->image);
        }

        Setting::put('intro.visible', $data['visible'] ? '1' : '0');
        Setting::put('intro.heading', $data['heading']);
        Setting::put('intro.content', $data['content'] ?? '');

        $this->status = 'Block saved.';
    }

    /** Hide the photo (the text then uses the full width). */
    public function removeImage(): void
    {
        $this->deleteUpload($this->image);
        $this->image = '';
        $this->imageFile = null;
        Setting::put('intro.image', '');

        $this->status = 'Photo removed.';
    }

    public function restoreImage(): void
    {
        $this->image = IntroSection::defaults()['intro.image'];
        $this->imageFile = null;
        Setting::put('intro.image', $this->image);

        $this->status = 'Original photo restored.';
    }

    /** Only files uploaded through the admin are removed; bundled images are never touched. */
    protected function deleteUpload(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/')) {
            Storage::disk('uploads')->delete(substr($path, strlen('uploads/')));
        }
    }

    protected function imagePreview(): ?string
    {
        if ($this->imageFile) {
            try {
                return $this->imageFile->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        return $this->image !== '' ? asset($this->image) : null;
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Appointment Intro</h2>
    </x-slot:header>

    <p class="mb-4 text-sm text-gray-500">
        The &ldquo;Book an appointment with Dr&hellip;&rdquo; photo-and-text block on the home page.
        <a href="{{ route('home') }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-medium">View website &nearr;</a>
    </p>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    @php $preview = $this->imagePreview(); @endphp

    <form wire:submit="save" class="max-w-4xl space-y-6 rounded-xl border border-gray-200 bg-white p-5">
        {{-- Show / hide --}}
        <label class="flex items-start gap-3 rounded-lg border {{ $visible ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }} p-4">
            <input type="checkbox" wire:model.live="visible" class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
            <span>
                <span class="block text-sm font-medium text-gray-900">Show this block on the home page</span>
                <span class="block text-xs text-gray-500">Currently {{ $visible ? 'shown' : 'hidden' }}. Press &ldquo;Save block&rdquo; to apply.</span>
            </span>
        </label>

        <div>
            <label class="block text-sm font-medium text-gray-700">Heading *</label>
            <input wire:model="heading" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            @error('heading') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Text</label>
            <textarea wire:model="content" rows="10" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
            <p class="mt-1 text-xs text-gray-400">Leave a blank line between paragraphs.</p>
            @error('content') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Photo</label>
            <div class="mt-1 flex flex-wrap items-start gap-4">
                @if ($preview)
                    <img src="{{ $preview }}" alt="" class="h-28 w-40 rounded-md border border-gray-200 object-cover">
                @else
                    <div class="flex h-28 w-40 items-center justify-center rounded-md bg-gray-100 text-xs text-gray-400">No photo</div>
                @endif
                <div>
                    <input type="file" wire:model="imageFile" accept="image/jpeg,image/png,image/webp"
                        class="block text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                    <p class="mt-1 text-xs text-gray-400">JPG, PNG or WEBP, up to 4 MB. A new photo is saved with the block.</p>
                    <div wire:loading wire:target="imageFile" class="text-xs text-indigo-600">Uploading&hellip;</div>
                    <div class="mt-1 flex gap-3 text-xs">
                        @if ($image !== '')
                            <button type="button" wire:click="removeImage" wire:confirm="Remove the photo? The text will use the full width." class="font-medium text-red-600 hover:text-red-800">Remove photo</button>
                        @endif
                        <button type="button" wire:click="restoreImage" class="font-medium text-gray-500 hover:text-gray-800">Use original</button>
                    </div>
                    @error('imageFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="save,imageFile" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">Save block</button>
        </div>
    </form>
</div>
