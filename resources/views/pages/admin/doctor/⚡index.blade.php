<?php

use App\Models\Setting;
use App\Support\DoctorSection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    public bool $visible = true;
    public string $photo = '';
    public $photoFile = null;
    public string $badge_number = '';
    public string $badge_label = '';
    public string $eyebrow = '';
    public string $name = '';
    public string $qualifications = '';
    public string $description = '';
    public string $diseases_intro = '';
    public string $diseases = '';
    public string $button_text = '';
    public string $button_url = '';

    public ?string $status = null;

    public function mount(): void
    {
        $doctor = DoctorSection::content();

        foreach (['visible', 'photo', 'badge_number', 'badge_label', 'eyebrow', 'name', 'qualifications', 'description', 'diseases_intro', 'diseases', 'button_text', 'button_url'] as $key) {
            $this->$key = $doctor[$key];
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'visible' => ['boolean'],
            'photoFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'badge_number' => ['nullable', 'string', 'max:20'],
            'badge_label' => ['nullable', 'string', 'max:60'],
            'eyebrow' => ['nullable', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'qualifications' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:4000'],
            'diseases_intro' => ['nullable', 'string', 'max:250'],
            'diseases' => ['nullable', 'string', 'max:1500'],
            'button_text' => ['nullable', 'string', 'max:50'],
            'button_url' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'The doctor name cannot be empty.',
        ]);

        if ($this->photoFile) {
            $this->deleteUpload($this->photo);
            $this->photo = 'uploads/'.$this->photoFile->store('doctor', 'uploads');
            $this->photoFile = null;
            Setting::put('doctor.photo', $this->photo);
        }

        Setting::put('doctor.visible', $data['visible'] ? '1' : '0');

        foreach (['badge_number', 'badge_label', 'eyebrow', 'qualifications', 'description', 'diseases_intro', 'diseases', 'button_text', 'button_url'] as $key) {
            Setting::put("doctor.$key", $data[$key] ?? '');
        }
        Setting::put('doctor.name', $data['name']);

        $this->status = 'Doctor block saved.';
    }

    /** Hide the photo (the text then uses the full width). */
    public function removePhoto(): void
    {
        $this->deleteUpload($this->photo);
        $this->photo = '';
        $this->photoFile = null;
        Setting::put('doctor.photo', '');

        $this->status = 'Photo removed.';
    }

    public function restorePhoto(): void
    {
        $this->photo = DoctorSection::defaults()['doctor.photo'];
        $this->photoFile = null;
        Setting::put('doctor.photo', $this->photo);

        $this->status = 'Original photo restored.';
    }

    /** Only files uploaded through the admin are removed; bundled images are never touched. */
    protected function deleteUpload(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/')) {
            Storage::disk('uploads')->delete(substr($path, strlen('uploads/')));
        }
    }

    protected function photoPreview(): ?string
    {
        if ($this->photoFile) {
            try {
                return $this->photoFile->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        return $this->photo !== '' ? asset($this->photo) : null;
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Doctor Profile</h2>
    </x-slot:header>

    <p class="mb-4 text-sm text-gray-500">
        The &ldquo;Meet our expert general physician&rdquo; block on the home page (the green panel with the doctor&rsquo;s photo).
        <a href="{{ route('home') }}#about" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-medium">View website &nearr;</a>
    </p>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    @php $preview = $this->photoPreview(); @endphp

    <form wire:submit="save" class="max-w-4xl space-y-6 rounded-xl border border-gray-200 bg-white p-5">
        <label class="flex items-start gap-3 rounded-lg border {{ $visible ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }} p-4">
            <input type="checkbox" wire:model.live="visible" class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
            <span>
                <span class="block text-sm font-medium text-gray-900">Show this block on the home page</span>
                <span class="block text-xs text-gray-500">Currently {{ $visible ? 'shown' : 'hidden' }}. Press &ldquo;Save block&rdquo; to apply.</span>
            </span>
        </label>

        {{-- Photo + badge --}}
        <div>
            <label class="block text-sm font-medium text-gray-700">Photo</label>
            <div class="mt-1 flex flex-wrap items-start gap-4">
                @if ($preview)
                    <img src="{{ $preview }}" alt="" class="h-32 w-24 rounded-md border border-gray-200 object-cover">
                @else
                    <div class="flex h-32 w-24 items-center justify-center rounded-md bg-gray-100 text-xs text-gray-400">No photo</div>
                @endif
                <div>
                    <input type="file" wire:model="photoFile" accept="image/jpeg,image/png,image/webp"
                        class="block text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                    <p class="mt-1 text-xs text-gray-400">Portrait photo (about 4:5), JPG, PNG or WEBP up to 4 MB. With no photo the text uses the full width.</p>
                    <div wire:loading wire:target="photoFile" class="text-xs text-indigo-600">Uploading&hellip;</div>
                    <div class="mt-1 flex gap-3 text-xs">
                        @if ($photo !== '')
                            <button type="button" wire:click="removePhoto" wire:confirm="Remove the photo?" class="font-medium text-red-600 hover:text-red-800">Remove photo</button>
                        @endif
                        <button type="button" wire:click="restorePhoto" class="font-medium text-gray-500 hover:text-gray-800">Use original</button>
                    </div>
                    @error('photoFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Badge number</label>
                <input wire:model="badge_number" type="text" placeholder="12+" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('badge_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Badge text</label>
                <textarea wire:model="badge_label" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                <p class="mt-1 text-xs text-gray-400">e.g. &ldquo;Years / Of Experience&rdquo; on two lines. Clear both fields to hide the badge.</p>
                @error('badge_label') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Text --}}
        <div>
            <label class="block text-sm font-medium text-gray-700">Small label</label>
            <input wire:model="eyebrow" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            @error('eyebrow') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Doctor name *</label>
            <input wire:model="name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Qualifications</label>
            <textarea wire:model="qualifications" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
            <p class="mt-1 text-xs text-gray-400">Each line is shown on its own line.</p>
            @error('qualifications') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Description</label>
            <textarea wire:model="description" rows="8" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
            <p class="mt-1 text-xs text-gray-400">Leave a blank line between paragraphs.</p>
            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Disease list --}}
        <div>
            <label class="block text-sm font-medium text-gray-700">Line above the list</label>
            <input wire:model="diseases_intro" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            @error('diseases_intro') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">List items</label>
            <textarea wire:model="diseases" rows="6" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
            <p class="mt-1 text-xs text-gray-400">One item per line (shown with a tick). Leave empty to hide the list.</p>
            @error('diseases') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Button --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Button text</label>
                <input wire:model="button_text" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <p class="mt-1 text-xs text-gray-400">Leave empty to hide the button.</p>
                @error('button_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Button link</label>
                <input wire:model="button_url" type="text" placeholder="Empty = About Doctor page" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('button_url') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="save,photoFile" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">Save block</button>
        </div>
    </form>
</div>
