<?php

use App\Models\ClinicTreatment;
use App\Models\Setting;
use App\Support\ClinicSection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    // Block texts
    public string $eyebrow = '';
    public string $heading = '';
    public string $description = '';
    public string $stat_number = '';
    public string $stat_label = '';
    public string $button_text = '';
    public string $button_url = '';

    /** Photo slot => saved path (relative to /public) */
    public array $photos = [];
    /** Photo slot => pending upload */
    public array $photoFiles = [];

    // Treatment card form
    public bool $showModal = false;
    public ?int $editingId = null;
    public string $title = '';
    public bool $is_active = true;
    public $imageFile = null;
    public ?string $existingImage = null;

    public ?string $status = null;

    public function mount(): void
    {
        $content = ClinicSection::content();

        $this->eyebrow = $content['eyebrow'];
        $this->heading = $content['heading'];
        $this->description = $content['description'];
        $this->stat_number = $content['stat_number'];
        $this->stat_label = $content['stat_label'];
        $this->button_text = $content['button_text'];
        $this->button_url = $content['button_url'];

        for ($n = 1; $n <= ClinicSection::PHOTO_COUNT; $n++) {
            $this->photos[$n] = $content['photos'][$n] ?? '';
        }
    }

    #[Computed]
    public function treatments()
    {
        return ClinicTreatment::ordered()->get();
    }

    /* ---------- block content ---------- */

    public function saveContent(): void
    {
        $data = $this->validate([
            'eyebrow' => ['nullable', 'string', 'max:120'],
            'heading' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1500'],
            'stat_number' => ['nullable', 'string', 'max:30'],
            'stat_label' => ['nullable', 'string', 'max:60'],
            'button_text' => ['nullable', 'string', 'max:50'],
            'button_url' => ['nullable', 'string', 'max:255'],
            'photoFiles.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'heading.required' => 'The heading cannot be empty.',
        ]);

        Setting::put('clinic.eyebrow', $data['eyebrow'] ?? '');
        Setting::put('clinic.heading', $data['heading']);
        Setting::put('clinic.description', $data['description'] ?? '');
        Setting::put('clinic.stat_number', $data['stat_number'] ?? '');
        Setting::put('clinic.stat_label', $data['stat_label'] ?? '');
        Setting::put('clinic.button_text', $data['button_text'] ?? '');
        Setting::put('clinic.button_url', $data['button_url'] ?? '');

        foreach ($this->photoFiles as $slot => $file) {
            if (! $file) {
                continue;
            }

            $this->deleteUpload($this->photos[$slot] ?? null);
            $this->photos[$slot] = 'uploads/'.$file->store('clinic', 'uploads');
            Setting::put("clinic.photo_$slot", $this->photos[$slot]);
        }

        $this->photoFiles = [];
        $this->status = 'Clinic block saved.';
    }

    /** Hide one photo slot (the file is deleted only if it was an admin upload). */
    public function removePhoto(int $slot): void
    {
        $this->deleteUpload($this->photos[$slot] ?? null);
        $this->photos[$slot] = '';
        unset($this->photoFiles[$slot]);
        Setting::put("clinic.photo_$slot", '');

        $this->status = 'Photo removed.';
    }

    /** Bring a removed photo slot back to the original picture. */
    public function restorePhoto(int $slot): void
    {
        $default = ClinicSection::defaults()["clinic.photo_$slot"];

        $this->photos[$slot] = $default;
        unset($this->photoFiles[$slot]);
        Setting::put("clinic.photo_$slot", $default);

        $this->status = 'Original photo restored.';
    }

    /* ---------- treatment cards ---------- */

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $card = ClinicTreatment::findOrFail($id);

        $this->resetForm();
        $this->editingId = $card->id;
        $this->title = $card->title;
        $this->is_active = $card->is_active;
        $this->existingImage = $card->image;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'title', 'is_active', 'imageFile', 'existingImage']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:60'],
            'is_active' => ['boolean'],
            'imageFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:1024'],
        ]);

        $card = $this->editingId ? ClinicTreatment::findOrFail($this->editingId) : new ClinicTreatment();

        $payload = ['title' => $data['title'], 'is_active' => $data['is_active']];

        if ($this->imageFile) {
            $this->deleteUpload($card->image);
            $payload['image'] = 'uploads/'.$this->imageFile->store('clinic', 'uploads');
        }

        if (! $card->exists) {
            $payload['sort_order'] = (int) ClinicTreatment::max('sort_order') + 1;
        }

        $card->fill($payload)->save();

        $this->status = $this->editingId ? 'Card updated.' : 'Card added.';
        $this->closeModal();
        unset($this->treatments);
    }

    public function toggle(int $id): void
    {
        $card = ClinicTreatment::findOrFail($id);
        $card->update(['is_active' => ! $card->is_active]);

        unset($this->treatments);
    }

    public function move(int $id, string $direction): void
    {
        $ids = ClinicTreatment::ordered()->pluck('id')->all();
        $pos = array_search($id, $ids, true);
        $swap = $direction === 'up' ? $pos - 1 : $pos + 1;

        if ($pos === false || ! isset($ids[$swap])) {
            return;
        }

        [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];

        foreach ($ids as $order => $cardId) {
            ClinicTreatment::whereKey($cardId)->update(['sort_order' => $order + 1]);
        }

        unset($this->treatments);
    }

    public function delete(int $id): void
    {
        $card = ClinicTreatment::findOrFail($id);

        $this->deleteUpload($card->image);
        $card->delete();

        $this->status = 'Card deleted.';
        unset($this->treatments);
    }

    /** Only files uploaded through the admin are removed; bundled images are never touched. */
    protected function deleteUpload(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/')) {
            Storage::disk('uploads')->delete(substr($path, strlen('uploads/')));
        }
    }

    protected function photoPreview(int $slot): ?string
    {
        $file = $this->photoFiles[$slot] ?? null;

        if ($file) {
            try {
                return $file->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        return ($this->photos[$slot] ?? '') !== '' ? asset($this->photos[$slot]) : null;
    }

    protected function cardPreview(): ?string
    {
        if ($this->imageFile) {
            try {
                return $this->imageFile->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        return $this->existingImage ? asset($this->existingImage) : null;
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">About the Clinic</h2>
    </x-slot:header>

    <p class="mb-4 text-sm text-gray-500">
        The &ldquo;About the Best Physician Clinic&rdquo; block on the home page (photos, counter, text and treatment cards).
        <a href="{{ route('home') }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-medium">View website &nearr;</a>
    </p>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    <form wire:submit="saveContent" class="mb-6 space-y-6 rounded-xl border border-gray-200 bg-white p-5">
        {{-- Photos --}}
        <div>
            <h3 class="mb-3 text-sm font-semibold text-gray-900">Photos (2 &times; 2 grid)</h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (range(1, \App\Support\ClinicSection::PHOTO_COUNT) as $slot)
                    @php $preview = $this->photoPreview($slot); @endphp
                    <div wire:key="photo-{{ $slot }}" class="rounded-lg border border-gray-200 p-3">
                        @if ($preview)
                            <img src="{{ $preview }}" alt="" class="aspect-[4/3] w-full rounded object-cover">
                        @else
                            <div class="flex aspect-[4/3] w-full items-center justify-center rounded bg-gray-100 text-xs text-gray-400">Empty</div>
                        @endif
                        <input type="file" wire:model="photoFiles.{{ $slot }}" accept="image/jpeg,image/png,image/webp"
                            class="mt-2 block w-full text-xs text-gray-600 file:mr-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-2.5 file:py-1.5 file:text-xs file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                        <div wire:loading wire:target="photoFiles.{{ $slot }}" class="text-xs text-indigo-600">Uploading&hellip;</div>
                        @error("photoFiles.$slot") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        <div class="mt-1 flex gap-3 text-xs">
                            @if (($photos[$slot] ?? '') !== '')
                                <button type="button" wire:click="removePhoto({{ $slot }})" wire:confirm="Remove this photo from the block?" class="font-medium text-red-600 hover:text-red-800">Remove</button>
                            @endif
                            <button type="button" wire:click="restorePhoto({{ $slot }})" class="font-medium text-gray-500 hover:text-gray-800">Use original</button>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-gray-400">JPG, PNG or WEBP up to 4 MB. Photos are shown in a 4:3 frame. New photos are saved when you press &ldquo;Save block&rdquo;.</p>
        </div>

        {{-- Counter --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Counter number</label>
                <input wire:model="stat_number" type="text" placeholder="10,000+" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('stat_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Counter label</label>
                <input wire:model="stat_label" type="text" placeholder="Satisfied clients" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('stat_label') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <p class="-mt-3 text-xs text-gray-400">Leave both empty to hide the round counter badge.</p>

        {{-- Text --}}
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Small label</label>
                <input wire:model="eyebrow" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('eyebrow') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Heading *</label>
                <input wire:model="heading" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('heading') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Description</label>
                <textarea wire:model="description" rows="5" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Button --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Button text</label>
                <input wire:model="button_text" type="text" placeholder="Contact Us" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <p class="mt-1 text-xs text-gray-400">Leave empty to hide the button.</p>
                @error('button_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Button link</label>
                <input wire:model="button_url" type="text" placeholder="Empty = Contact Us page" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('button_url') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="saveContent,photoFiles" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">Save block</button>
        </div>
    </form>

    {{-- Treatment cards --}}
    <div class="mb-3 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900">Treatment cards</h3>
        <button wire:click="create" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">+ Add Card</button>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium w-24">Order</th>
                    <th class="px-4 py-3 font-medium">Card</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->treatments as $card)
                    <tr wire:key="card-{{ $card->id }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1 text-gray-500">
                                <span class="w-5 text-center font-medium text-gray-900">{{ $loop->iteration }}</span>
                                <button wire:click="move({{ $card->id }}, 'up')" @disabled($loop->first) title="Move up" class="rounded p-1 hover:bg-gray-100 disabled:opacity-30"><x-icon name="chevron-down" class="h-4 w-4 rotate-180" /></button>
                                <button wire:click="move({{ $card->id }}, 'down')" @disabled($loop->last) title="Move down" class="rounded p-1 hover:bg-gray-100 disabled:opacity-30"><x-icon name="chevron-down" class="h-4 w-4" /></button>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md border border-gray-200 bg-white">
                                    @if ($card->imageUrl())
                                        <img src="{{ $card->imageUrl() }}" alt="" class="h-8 w-8 object-contain">
                                    @endif
                                </span>
                                <span class="font-medium {{ $card->is_active ? 'text-gray-900' : 'text-gray-400' }}">{{ $card->title }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <button wire:click="toggle({{ $card->id }})" role="switch" aria-checked="{{ $card->is_active ? 'true' : 'false' }}"
                                title="{{ $card->is_active ? 'Visible - click to hide' : 'Hidden - click to show' }}"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $card->is_active ? 'bg-indigo-600' : 'bg-gray-300' }}">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ $card->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                            <span class="ml-2 text-xs {{ $card->is_active ? 'text-green-700' : 'text-gray-400' }}">{{ $card->is_active ? 'Visible' : 'Hidden' }}</span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                            <button wire:click="edit({{ $card->id }})" class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
                            <button wire:click="delete({{ $card->id }})" wire:confirm="Delete this card?" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-gray-400">No cards yet. The card grid is hidden on the website until you add one.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showModal)
        @php $preview = $this->cardPreview(); @endphp
        <div class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-black/40 px-4 py-8" wire:key="card-modal">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit Card' : 'Add Card' }}</h3>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Title *</label>
                        <input wire:model.live.debounce.250ms="title" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Icon image</label>
                        <input type="file" wire:model="imageFile" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                            class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                        <p class="mt-1 text-xs text-gray-400">Small square image (PNG, WEBP or SVG), up to 1 MB.</p>
                        <div wire:loading wire:target="imageFile" class="text-xs text-indigo-600">Uploading&hellip;</div>
                        @error('imageFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="rounded-lg bg-gray-50 p-4">
                        <p class="mb-2 text-xs text-gray-500">Preview</p>
                        <div class="mx-auto flex w-28 flex-col items-center gap-2 rounded-md bg-white px-2 py-4 text-center shadow-[0_0_8px_rgba(0,0,0,0.08)]">
                            @if ($preview)
                                <img src="{{ $preview }}" alt="" class="h-10 w-10 object-contain">
                            @else
                                <span class="h-10 w-10"></span>
                            @endif
                            <span class="text-xs font-semibold text-gray-900">{{ $title ?: 'Title' }}</span>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"> Visible on the website
                    </label>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save,imageFile" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
