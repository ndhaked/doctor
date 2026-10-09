<?php

use App\Models\HeroSlide;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    // Section heading texts
    public string $eyebrow = '';
    public string $heading = '';
    public string $intro = '';

    // Card form
    public bool $showModal = false;
    public ?int $editingId = null;
    public string $title = '';
    public string $description = '';
    public string $icon = 'droplet';
    public string $link_url = '';
    public bool $is_active = true;
    public $imageFile = null;
    public ?string $existingImage = null;
    public bool $removeImage = false;

    public ?string $status = null;

    public function mount(): void
    {
        $text = Service::sectionText();

        $this->eyebrow = $text['eyebrow'];
        $this->heading = $text['heading'];
        $this->intro = $text['intro'];
    }

    #[Computed]
    public function services()
    {
        return Service::ordered()->get();
    }

    #[Computed]
    public function icons(): array
    {
        return HeroSlide::ICONS;
    }

    /* ---------- section text ---------- */

    public function saveText(): void
    {
        $data = $this->validate([
            'eyebrow' => ['nullable', 'string', 'max:60'],
            'heading' => ['required', 'string', 'max:200'],
            'intro' => ['nullable', 'string', 'max:500'],
        ], [
            'heading.required' => 'The section heading cannot be empty.',
        ]);

        Setting::put('services.eyebrow', $data['eyebrow'] ?? '');
        Setting::put('services.heading', $data['heading']);
        Setting::put('services.intro', $data['intro'] ?? '');

        $this->status = 'Section text saved.';
    }

    /* ---------- cards ---------- */

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $service = Service::findOrFail($id);

        $this->resetForm();
        $this->editingId = $service->id;
        $this->title = $service->title;
        $this->description = (string) $service->description;
        $this->icon = $service->icon;
        $this->link_url = (string) $service->link_url;
        $this->is_active = $service->is_active;
        $this->existingImage = $service->image;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'title', 'description', 'icon', 'link_url', 'is_active', 'imageFile', 'existingImage', 'removeImage']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:600'],
            'icon' => ['required', Rule::in(array_keys(HeroSlide::ICONS))],
            'link_url' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'imageFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ]);

        $service = $this->editingId ? Service::findOrFail($this->editingId) : new Service();

        $payload = [
            'title' => $data['title'],
            'description' => $data['description'] ?: null,
            'icon' => $data['icon'],
            'link_url' => $data['link_url'] ?: null,
            'is_active' => $data['is_active'],
        ];

        if ($this->imageFile) {
            $this->deleteUpload($service->image);
            $payload['image'] = 'uploads/'.$this->imageFile->store('services', 'uploads');
        } elseif ($this->removeImage && $service->image) {
            $this->deleteUpload($service->image);
            $payload['image'] = null;
        }

        if (! $service->exists) {
            $payload['sort_order'] = (int) Service::max('sort_order') + 1;
        }

        $service->fill($payload)->save();

        $this->status = $this->editingId ? 'Service updated.' : 'Service added.';
        $this->closeModal();
        unset($this->services);
    }

    public function toggle(int $id): void
    {
        $service = Service::findOrFail($id);
        $service->update(['is_active' => ! $service->is_active]);

        $this->status = $service->is_active ? 'Service is now visible.' : 'Service hidden from the website.';
        unset($this->services);
    }

    public function move(int $id, string $direction): void
    {
        $ids = Service::ordered()->pluck('id')->all();
        $pos = array_search($id, $ids, true);
        $swap = $direction === 'up' ? $pos - 1 : $pos + 1;

        if ($pos === false || ! isset($ids[$swap])) {
            return;
        }

        [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];

        foreach ($ids as $order => $serviceId) {
            Service::whereKey($serviceId)->update(['sort_order' => $order + 1]);
        }

        unset($this->services);
    }

    public function delete(int $id): void
    {
        $service = Service::findOrFail($id);

        $this->deleteUpload($service->image);
        $service->delete();

        $this->status = 'Service deleted.';
        unset($this->services);
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

        return ($this->existingImage && ! $this->removeImage) ? asset($this->existingImage) : null;
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Services</h2>
    </x-slot:header>

    <p class="mb-4 text-sm text-gray-500">
        The &ldquo;Our Main Expertise Services&rdquo; cards on the home page. The same list fills the &ldquo;Expertise Services&rdquo; menu in the header.
        <a href="{{ route('home') }}#services" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-medium">View website &nearr;</a>
    </p>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    {{-- Section text --}}
    <form wire:submit="saveText" class="mb-6 rounded-xl border border-gray-200 bg-white p-5">
        <h3 class="mb-4 text-sm font-semibold text-gray-900">Section heading</h3>
        <div class="grid gap-4 sm:grid-cols-[200px_1fr]">
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
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Intro text</label>
                <textarea wire:model="intro" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                <p class="mt-1 text-xs text-gray-400">A new line in the box becomes a new line on the website.</p>
                @error('intro') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="mt-4 flex justify-end">
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save text</button>
        </div>
    </form>

    {{-- Cards --}}
    <div class="mb-3 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900">Service cards</h3>
        <button wire:click="create" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">+ Add Service</button>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium w-24">Order</th>
                    <th class="px-4 py-3 font-medium">Service</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->services as $service)
                    <tr wire:key="service-{{ $service->id }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1 text-gray-500">
                                <span class="w-5 text-center font-medium text-gray-900">{{ $loop->iteration }}</span>
                                <button wire:click="move({{ $service->id }}, 'up')" @disabled($loop->first) title="Move up" class="rounded p-1 hover:bg-gray-100 disabled:opacity-30"><x-icon name="chevron-down" class="h-4 w-4 rotate-180" /></button>
                                <button wire:click="move({{ $service->id }}, 'down')" @disabled($loop->last) title="Move down" class="rounded p-1 hover:bg-gray-100 disabled:opacity-30"><x-icon name="chevron-down" class="h-4 w-4" /></button>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if ($service->imageUrl())
                                    <img src="{{ $service->imageUrl() }}" alt="" class="h-12 w-20 shrink-0 rounded-md border border-gray-200 object-cover">
                                @else
                                    <span class="flex h-12 w-20 shrink-0 items-center justify-center rounded-md bg-emerald-50 text-emerald-300">
                                        <x-icon :name="$service->icon" class="h-6 w-6" />
                                    </span>
                                @endif
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900">{{ $service->title }}</p>
                                    <p class="max-w-md truncate text-xs text-gray-500">{{ $service->description }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <button wire:click="toggle({{ $service->id }})" role="switch" aria-checked="{{ $service->is_active ? 'true' : 'false' }}"
                                title="{{ $service->is_active ? 'Visible - click to hide' : 'Hidden - click to show' }}"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $service->is_active ? 'bg-indigo-600' : 'bg-gray-300' }}">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ $service->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                            <span class="ml-2 text-xs {{ $service->is_active ? 'text-green-700' : 'text-gray-400' }}">{{ $service->is_active ? 'Visible' : 'Hidden' }}</span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                            <button wire:click="edit({{ $service->id }})" class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
                            <button wire:click="delete({{ $service->id }})" wire:confirm="Delete this service?" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-gray-400">No services yet. The section is hidden on the website until you add one.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showModal)
        @php $preview = $this->imagePreview(); @endphp
        <div class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-black/40 px-4 py-8" wire:key="service-modal">
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit Service' : 'Add Service' }}</h3>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Title *</label>
                        <input wire:model.live.debounce.250ms="title" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea wire:model.live.debounce.250ms="description" rows="4" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                        @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Icon</label>
                            <select wire:model.live="icon" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @foreach ($this->icons as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-400">Used in the header menu, and on the card when there is no photo.</p>
                            @error('icon') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">&ldquo;View Service&rdquo; link</label>
                            <input wire:model="link_url" type="text" placeholder="/faq or https://..." class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @error('link_url') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Card photo</label>
                        <input type="file" wire:model="imageFile" accept="image/jpeg,image/png,image/webp"
                            class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                        <p class="mt-1 text-xs text-gray-400">Optional. JPG, PNG or WEBP, up to 3 MB. Wide images (about 330&times;185) look best.</p>
                        <div wire:loading wire:target="imageFile" class="text-xs text-indigo-600">Uploading&hellip;</div>
                        @if ($existingImage && ! $imageFile)
                            <label class="mt-1 flex items-center gap-2 text-xs text-gray-600">
                                <input type="checkbox" wire:model.live="removeImage" class="rounded border-gray-300 text-indigo-600"> Remove current photo
                            </label>
                        @endif
                        @error('imageFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Live preview of the card --}}
                    <div class="rounded-lg bg-gray-50 p-4">
                        <p class="mb-2 text-xs text-gray-500">Preview</p>
                        <div class="mx-auto max-w-[240px] rounded-lg bg-white p-3 shadow-sm">
                            @if ($preview)
                                <img src="{{ $preview }}" alt="" class="h-24 w-full rounded object-cover">
                            @else
                                <div class="flex h-24 w-full items-center justify-center rounded bg-emerald-50 text-emerald-300">
                                    <x-icon :name="$icon" class="h-8 w-8" />
                                </div>
                            @endif
                            <p class="mt-2 text-sm font-bold text-gray-900">{{ $title ?: 'Title' }}</p>
                            <p class="mt-1 text-[11px] leading-5 text-gray-500 line-clamp-3">{{ $description }}</p>
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
