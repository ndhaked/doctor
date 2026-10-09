<?php

use App\Models\HeroSlide;
use App\Models\ProfileTreatment;
use App\Models\Setting;
use App\Support\DoctorCardSection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    // Block settings
    public bool $visible = true;
    public string $photo = '';
    public string $poster = '';
    public $photoFile = null;
    public $posterFile = null;
    public string $name = '';
    public string $qualifications = '';
    public string $address = '';
    public bool $show_address = true;
    public bool $show_phone = true;
    public string $experience = '';
    public string $button_text = '';
    public string $button_action = 'appointment';
    public string $button_url = '';

    // Treatment icon form
    public bool $showModal = false;
    public ?int $editingId = null;
    public string $title = '';
    public string $icon = '';
    public bool $is_active = true;
    public $imageFile = null;
    public ?string $existingImage = null;
    public bool $removeImage = false;

    public ?string $status = null;

    public function mount(): void
    {
        $card = DoctorCardSection::content();

        foreach (['visible', 'photo', 'poster', 'name', 'qualifications', 'experience', 'button_text', 'button_action', 'button_url', 'show_address', 'show_phone'] as $key) {
            $this->$key = $card[$key];
        }

        // the editable address is only the custom override (empty = automatic from Site Settings)
        $this->address = $card['address_custom'];
    }

    #[Computed]
    public function treatments()
    {
        return ProfileTreatment::ordered()->get();
    }

    #[Computed]
    public function icons(): array
    {
        return HeroSlide::ICONS;
    }

    /* ---------- block settings ---------- */

    public function saveBlock(): void
    {
        $data = $this->validate([
            'visible' => ['boolean'],
            'photoFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'posterFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'name' => ['required', 'string', 'max:120'],
            'qualifications' => ['nullable', 'string', 'max:300'],
            'address' => ['nullable', 'string', 'max:250'],
            'show_address' => ['boolean'],
            'show_phone' => ['boolean'],
            'experience' => ['nullable', 'string', 'max:100'],
            'button_text' => ['nullable', 'string', 'max:50'],
            'button_action' => ['required', Rule::in(array_keys(DoctorCardSection::ACTIONS))],
            'button_url' => [Rule::requiredIf($this->button_action === 'custom' && $this->button_text !== ''), 'nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'The doctor name cannot be empty.',
        ]);

        foreach (['photo' => 'photoFile', 'poster' => 'posterFile'] as $field => $fileProp) {
            if ($this->$fileProp) {
                $this->deleteUpload($this->$field);
                $this->$field = 'uploads/'.$this->$fileProp->store('doctor-card', 'uploads');
                $this->$fileProp = null;
                Setting::put("card.$field", $this->$field);
            }
        }

        Setting::put('card.visible', $data['visible'] ? '1' : '0');
        Setting::put('card.name', $data['name']);

        foreach (['qualifications', 'address', 'experience', 'button_text'] as $key) {
            Setting::put("card.$key", $data[$key] ?? '');
        }
        Setting::put('card.show_address', $data['show_address'] ? '1' : '0');
        Setting::put('card.show_phone', $data['show_phone'] ? '1' : '0');

        Setting::put('card.button_action', $data['button_action']);
        Setting::put('card.button_url', $data['button_action'] === 'custom' ? ($data['button_url'] ?? '') : '');

        $this->status = 'Doctor card saved.';
    }

    /** Clear a picture slot (photo or poster); admin uploads are deleted, bundled images are kept. */
    public function removeImageSlot(string $field): void
    {
        abort_unless(in_array($field, ['photo', 'poster'], true), 404);

        $this->deleteUpload($this->$field);
        $this->$field = '';
        $this->{$field.'File'} = null;
        Setting::put("card.$field", '');

        $this->status = ucfirst($field).' removed.';
    }

    public function restoreImageSlot(string $field): void
    {
        abort_unless(in_array($field, ['photo', 'poster'], true), 404);

        $this->$field = DoctorCardSection::defaults()["card.$field"];
        $this->{$field.'File'} = null;
        Setting::put("card.$field", $this->$field);

        $this->status = 'Original '.$field.' restored.';
    }

    /* ---------- treatment icons ---------- */

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $item = ProfileTreatment::findOrFail($id);

        $this->resetForm();
        $this->editingId = $item->id;
        $this->title = $item->title;
        $this->icon = (string) $item->icon;
        $this->is_active = $item->is_active;
        $this->existingImage = $item->image;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'title', 'icon', 'is_active', 'imageFile', 'existingImage', 'removeImage']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:60'],
            'icon' => ['nullable', Rule::in(array_keys(HeroSlide::ICONS))],
            'is_active' => ['boolean'],
            'imageFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:1024'],
        ]);

        $item = $this->editingId ? ProfileTreatment::findOrFail($this->editingId) : new ProfileTreatment();

        $payload = [
            'title' => $data['title'],
            'icon' => $data['icon'] ?: null,
            'is_active' => $data['is_active'],
        ];

        if ($this->imageFile) {
            $this->deleteUpload($item->image);
            $payload['image'] = 'uploads/'.$this->imageFile->store('doctor-card', 'uploads');
        } elseif ($this->removeImage && $item->image) {
            $this->deleteUpload($item->image);
            $payload['image'] = null;
        }

        if (! $item->exists) {
            $payload['sort_order'] = (int) ProfileTreatment::max('sort_order') + 1;
        }

        $item->fill($payload)->save();

        $this->status = $this->editingId ? 'Item updated.' : 'Item added.';
        $this->closeModal();
        unset($this->treatments);
    }

    public function toggle(int $id): void
    {
        $item = ProfileTreatment::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);

        unset($this->treatments);
    }

    public function move(int $id, string $direction): void
    {
        $ids = ProfileTreatment::ordered()->pluck('id')->all();
        $pos = array_search($id, $ids, true);
        $swap = $direction === 'up' ? $pos - 1 : $pos + 1;

        if ($pos === false || ! isset($ids[$swap])) {
            return;
        }

        [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];

        foreach ($ids as $order => $itemId) {
            ProfileTreatment::whereKey($itemId)->update(['sort_order' => $order + 1]);
        }

        unset($this->treatments);
    }

    public function delete(int $id): void
    {
        $item = ProfileTreatment::findOrFail($id);

        $this->deleteUpload($item->image);
        $item->delete();

        $this->status = 'Item deleted.';
        unset($this->treatments);
    }

    /** Only files uploaded through the admin are removed; bundled images are never touched. */
    protected function deleteUpload(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/')) {
            Storage::disk('uploads')->delete(substr($path, strlen('uploads/')));
        }
    }

    protected function slotPreview(string $field): ?string
    {
        $file = $this->{$field.'File'};

        if ($file) {
            try {
                return $file->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        return $this->$field !== '' ? asset($this->$field) : null;
    }

    protected function itemPreview(): ?string
    {
        if ($this->imageFile) {
            try {
                return $this->imageFile->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        return ($this->existingImage && ! $this->removeImage) ? asset($this->existingImage) : null;
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Doctor Card</h2>
    </x-slot:header>

    <p class="mb-4 text-sm text-gray-500">
        The block with the round doctor photo, address, buttons, treatment icons and the clinic poster on the home page.
        <a href="{{ route('home') }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-medium">View website &nearr;</a>
    </p>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    @php
        $photoPreview = $this->slotPreview('photo');
        $posterPreview = $this->slotPreview('poster');
    @endphp

    <form wire:submit="saveBlock" class="mb-6 max-w-4xl space-y-6 rounded-xl border border-gray-200 bg-white p-5">
        <label class="flex items-start gap-3 rounded-lg border {{ $visible ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }} p-4">
            <input type="checkbox" wire:model.live="visible" class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
            <span>
                <span class="block text-sm font-medium text-gray-900">Show this block on the home page</span>
                <span class="block text-xs text-gray-500">Currently {{ $visible ? 'shown' : 'hidden' }}. Press &ldquo;Save block&rdquo; to apply.</span>
            </span>
        </label>

        {{-- Pictures --}}
        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Doctor photo (round)</label>
                <div class="mt-1 flex items-start gap-3">
                    @if ($photoPreview)
                        <img src="{{ $photoPreview }}" alt="" class="h-20 w-20 rounded-full border border-gray-200 object-cover">
                    @else
                        <div class="flex h-20 w-20 items-center justify-center rounded-full bg-gray-100 text-xs text-gray-400">None</div>
                    @endif
                    <div class="min-w-0">
                        <input type="file" wire:model="photoFile" accept="image/jpeg,image/png,image/webp"
                            class="block w-full text-xs text-gray-600 file:mr-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-2.5 file:py-1.5 file:text-xs file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                        <div wire:loading wire:target="photoFile" class="text-xs text-indigo-600">Uploading&hellip;</div>
                        <div class="mt-1 flex gap-3 text-xs">
                            @if ($photo !== '')
                                <button type="button" wire:click="removeImageSlot('photo')" wire:confirm="Remove the photo?" class="font-medium text-red-600 hover:text-red-800">Remove</button>
                            @endif
                            <button type="button" wire:click="restoreImageSlot('photo')" class="font-medium text-gray-500 hover:text-gray-800">Use original</button>
                        </div>
                        @error('photoFile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Clinic poster (right side)</label>
                <div class="mt-1 flex items-start gap-3">
                    @if ($posterPreview)
                        <img src="{{ $posterPreview }}" alt="" class="h-24 w-16 rounded border border-gray-200 object-cover">
                    @else
                        <div class="flex h-24 w-16 items-center justify-center rounded bg-gray-100 text-xs text-gray-400">None</div>
                    @endif
                    <div class="min-w-0">
                        <input type="file" wire:model="posterFile" accept="image/jpeg,image/png,image/webp"
                            class="block w-full text-xs text-gray-600 file:mr-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-2.5 file:py-1.5 file:text-xs file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                        <p class="mt-1 text-xs text-gray-400">Tall poster, up to 5 MB. Without one, the green strip and poster are hidden.</p>
                        <div wire:loading wire:target="posterFile" class="text-xs text-indigo-600">Uploading&hellip;</div>
                        <div class="mt-1 flex gap-3 text-xs">
                            @if ($poster !== '')
                                <button type="button" wire:click="removeImageSlot('poster')" wire:confirm="Remove the poster?" class="font-medium text-red-600 hover:text-red-800">Remove</button>
                            @endif
                            <button type="button" wire:click="restoreImageSlot('poster')" class="font-medium text-gray-500 hover:text-gray-800">Use original</button>
                        </div>
                        @error('posterFile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Text --}}
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
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Clinic address (bold line)</label>
                <input wire:model="address" type="text" placeholder="Empty = clinic name + address from Site Settings" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <label class="mt-2 flex items-center gap-2 text-xs text-gray-600">
                    <input type="checkbox" wire:model="show_address" class="rounded border-gray-300 text-indigo-600"> Show the address line
                </label>
                @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Experience line</label>
                <input wire:model="experience" type="text" placeholder="12+ Years of Experience" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('experience') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Buttons --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-gray-700">First button text</label>
                <input wire:model="button_text" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <p class="mt-1 text-xs text-gray-400">Leave empty to hide it.</p>
                @error('button_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">First button action</label>
                <select wire:model.live="button_action" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @foreach (\App\Support\DoctorCardSection::ACTIONS as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('button_action') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Phone button</label>
                <label class="mt-2 flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="show_phone" class="rounded border-gray-300 text-indigo-600"> Show the call button
                </label>
                <p class="mt-1 text-xs text-gray-400">Uses the phone number from <a href="{{ route('admin.site.index') }}" class="text-indigo-600 underline">Site Settings</a>.</p>
            </div>
        </div>
        @if ($button_action === 'custom')
            <div>
                <label class="block text-sm font-medium text-gray-700">First button link</label>
                <input wire:model="button_url" type="text" placeholder="/faq or https://..." class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('button_url') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endif

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="saveBlock,photoFile,posterFile" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">Save block</button>
        </div>
    </form>

    {{-- Treatment icons --}}
    <div class="mb-3 flex max-w-4xl items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900">Treatment icons <span class="font-normal text-gray-400">(the round-icon grid)</span></h3>
        <button wire:click="create" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">+ Add Item</button>
    </div>

    <div class="max-w-4xl overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium w-24">Order</th>
                    <th class="px-4 py-3 font-medium">Item</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->treatments as $item)
                    <tr wire:key="item-{{ $item->id }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1 text-gray-500">
                                <span class="w-5 text-center font-medium text-gray-900">{{ $loop->iteration }}</span>
                                <button wire:click="move({{ $item->id }}, 'up')" @disabled($loop->first) title="Move up" class="rounded p-1 hover:bg-gray-100 disabled:opacity-30"><x-icon name="chevron-down" class="h-4 w-4 rotate-180" /></button>
                                <button wire:click="move({{ $item->id }}, 'down')" @disabled($loop->last) title="Move down" class="rounded p-1 hover:bg-gray-100 disabled:opacity-30"><x-icon name="chevron-down" class="h-4 w-4" /></button>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gray-100">
                                    @if ($item->imageUrl())
                                        <img src="{{ $item->imageUrl() }}" alt="" class="h-7 w-7 object-contain">
                                    @elseif ($item->icon)
                                        <x-icon :name="$item->icon" class="h-6 w-6 text-gray-600" />
                                    @endif
                                </span>
                                <span class="font-medium {{ $item->is_active ? 'text-gray-900' : 'text-gray-400' }}">{{ $item->title }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <button wire:click="toggle({{ $item->id }})" role="switch" aria-checked="{{ $item->is_active ? 'true' : 'false' }}"
                                title="{{ $item->is_active ? 'Visible - click to hide' : 'Hidden - click to show' }}"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $item->is_active ? 'bg-indigo-600' : 'bg-gray-300' }}">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ $item->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                            <span class="ml-2 text-xs {{ $item->is_active ? 'text-green-700' : 'text-gray-400' }}">{{ $item->is_active ? 'Visible' : 'Hidden' }}</span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                            <button wire:click="edit({{ $item->id }})" class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
                            <button wire:click="delete({{ $item->id }})" wire:confirm="Delete this item?" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-gray-400">No items yet. The icon grid is hidden on the website until you add one.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showModal)
        @php $preview = $this->itemPreview(); @endphp
        <div class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-black/40 px-4 py-8" wire:key="item-modal">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit Item' : 'Add Item' }}</h3>

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
                        @if ($existingImage && ! $imageFile)
                            <label class="mt-1 flex items-center gap-2 text-xs text-gray-600">
                                <input type="checkbox" wire:model.live="removeImage" class="rounded border-gray-300 text-indigo-600"> Remove current image
                            </label>
                        @endif
                        @error('imageFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fallback icon</label>
                        <select wire:model.live="icon" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">None</option>
                            @foreach ($this->icons as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-400">Used when there is no image.</p>
                        @error('icon') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="rounded-lg bg-gray-50 p-4">
                        <p class="mb-2 text-xs text-gray-500">Preview</p>
                        <div class="flex items-center gap-3">
                            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100">
                                @if ($preview)
                                    <img src="{{ $preview }}" alt="" class="h-9 w-9 object-contain">
                                @elseif ($icon !== '' && ! ($existingImage && ! $removeImage))
                                    <x-icon :name="$icon" class="h-7 w-7 text-gray-600" />
                                @endif
                            </span>
                            <span class="text-sm font-semibold text-gray-900">{{ $title ?: 'Title' }}</span>
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
