<?php

use App\Models\HeroSlide;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $doctor_name = '';
    public string $badge = '';
    public string $title = '';
    public string $credentials = '';
    public string $specialty = '';
    public string $description = '';
    public string $cta_text = '';
    public string $cta_action = 'appointment';
    public string $cta_url = '';
    public bool $is_active = true;

    /** @var array<int, array{icon: string, title: string}> */
    public array $cards = [];

    public $imageFile = null;        // new background image (upload)
    public $doctorImageFile = null;  // new doctor photo (upload)
    public ?string $existingImage = null;
    public ?string $existingDoctorImage = null;
    public bool $removeDoctorImage = false;

    public ?string $status = null;

    #[Computed]
    public function slides()
    {
        return HeroSlide::ordered()->get();
    }

    #[Computed]
    public function icons(): array
    {
        return HeroSlide::ICONS;
    }

    /* ---------- modal open / close ---------- */

    public function create(): void
    {
        $this->resetForm();
        $this->doctor_name = 'Dr. Aditya Sharma';
        $this->cards = [['icon' => 'droplet', 'title' => '']];
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $slide = HeroSlide::findOrFail($id);

        $this->resetForm();
        $this->editingId = $slide->id;
        $this->doctor_name = (string) $slide->doctor_name;
        $this->badge = (string) $slide->badge;
        $this->title = $slide->title;
        $this->credentials = (string) $slide->credentials;
        $this->specialty = (string) $slide->specialty;
        $this->description = (string) $slide->description;
        $this->cta_text = (string) $slide->cta_text;
        $this->cta_action = $slide->cta_action;
        $this->cta_url = (string) $slide->cta_url;
        $this->is_active = $slide->is_active;
        $this->cards = array_values($slide->cards ?? []);
        $this->existingImage = $slide->image;
        $this->existingDoctorImage = $slide->doctor_image;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingId', 'doctor_name', 'badge', 'title', 'credentials', 'specialty', 'description',
            'cta_text', 'cta_action', 'cta_url', 'is_active', 'cards',
            'imageFile', 'doctorImageFile', 'existingImage', 'existingDoctorImage', 'removeDoctorImage',
        ]);
        $this->resetValidation();
    }

    /* ---------- quick cards ---------- */

    public function addCard(): void
    {
        if (count($this->cards) < HeroSlide::MAX_CARDS) {
            $this->cards[] = ['icon' => 'droplet', 'title' => ''];
        }
    }

    public function removeCard(int $index): void
    {
        unset($this->cards[$index]);
        $this->cards = array_values($this->cards);
    }

    /* ---------- save ---------- */

    public function save(): void
    {
        $data = $this->validate([
            'imageFile' => [$this->editingId ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'doctorImageFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'doctor_name' => ['nullable', 'string', 'max:120'],
            'badge' => ['nullable', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:160'],
            'credentials' => ['nullable', 'string', 'max:200'],
            'specialty' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:600'],
            'cards' => ['array', 'max:'.HeroSlide::MAX_CARDS],
            'cards.*.icon' => ['required', Rule::in(array_keys(HeroSlide::ICONS))],
            'cards.*.title' => ['required', 'string', 'max:40'],
            'cta_text' => ['nullable', 'string', 'max:60'],
            'cta_action' => ['required', Rule::in(array_keys(HeroSlide::CTA_ACTIONS))],
            'cta_url' => [Rule::requiredIf($this->cta_action === 'custom' && $this->cta_text !== ''), 'nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ], [
            'imageFile.required' => 'Please choose a background image for the slide.',
            'cards.*.title.required' => 'Each card needs a title (or remove the card).',
        ]);

        $slide = $this->editingId ? HeroSlide::findOrFail($this->editingId) : new HeroSlide();

        $payload = [
            'doctor_name' => $data['doctor_name'] ?: null,
            'badge' => $data['badge'] ?: null,
            'title' => $data['title'],
            'credentials' => $data['credentials'] ?: null,
            'specialty' => $data['specialty'] ?: null,
            'description' => $data['description'] ?: null,
            'cards' => array_values(array_map(fn ($c) => ['icon' => $c['icon'], 'title' => trim($c['title'])], $data['cards'])),
            'cta_text' => $data['cta_text'] ?: null,
            'cta_action' => $data['cta_action'],
            'cta_url' => $data['cta_action'] === 'custom' ? $data['cta_url'] : null,
            'is_active' => $data['is_active'],
        ];

        if ($this->imageFile) {
            $this->deleteUpload($slide->image);
            $payload['image'] = 'uploads/'.$this->imageFile->store('slides', 'uploads');
        }

        if ($this->doctorImageFile) {
            $this->deleteUpload($slide->doctor_image);
            $payload['doctor_image'] = 'uploads/'.$this->doctorImageFile->store('slides', 'uploads');
        } elseif ($this->removeDoctorImage && $slide->doctor_image) {
            $this->deleteUpload($slide->doctor_image);
            $payload['doctor_image'] = null;
        }

        if (! $slide->exists) {
            $payload['sort_order'] = (int) HeroSlide::max('sort_order') + 1;
        }

        $slide->fill($payload)->save();

        $this->status = $this->editingId ? 'Slide updated.' : 'Slide added.';
        $this->closeModal();
        unset($this->slides);
    }

    /* ---------- list actions ---------- */

    public function toggle(int $id): void
    {
        $slide = HeroSlide::findOrFail($id);
        $slide->update(['is_active' => ! $slide->is_active]);

        $this->status = $slide->is_active ? 'Slide is now visible on the website.' : 'Slide hidden from the website.';
        unset($this->slides);
    }

    public function move(int $id, string $direction): void
    {
        $ids = HeroSlide::ordered()->pluck('id')->all();
        $pos = array_search($id, $ids, true);
        $swap = $direction === 'up' ? $pos - 1 : $pos + 1;

        if ($pos === false || ! isset($ids[$swap])) {
            return;
        }

        [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];

        foreach ($ids as $order => $slideId) {
            HeroSlide::whereKey($slideId)->update(['sort_order' => $order + 1]);
        }

        unset($this->slides);
    }

    public function delete(int $id): void
    {
        $slide = HeroSlide::findOrFail($id);

        $this->deleteUpload($slide->image);
        $this->deleteUpload($slide->doctor_image);
        $slide->delete();

        $this->status = 'Slide deleted.';
        unset($this->slides);
    }

    /** Only files the admin uploaded (under uploads/) are removed; bundled images are never touched. */
    protected function deleteUpload(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/')) {
            Storage::disk('uploads')->delete(substr($path, strlen('uploads/')));
        }
    }

    /** Resolve the image shown in the live preview: new upload first, then the saved one. */
    protected function previewUrl($upload, ?string $existing): ?string
    {
        if ($upload) {
            try {
                return $upload->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        return $existing ? asset($existing) : null;
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Hero Slider</h2>
    </x-slot:header>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <p class="text-sm text-gray-500">
            These slides rotate at the top of the home page. Changes go live immediately.
            <a href="{{ route('home') }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-medium">View website &nearr;</a>
        </p>
        <button wire:click="create" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
            + Add Slide
        </button>
    </div>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium w-24">Order</th>
                    <th class="px-4 py-3 font-medium">Slide</th>
                    <th class="px-4 py-3 font-medium hidden md:table-cell">Button</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->slides as $slide)
                    <tr wire:key="slide-{{ $slide->id }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1 text-gray-500">
                                <span class="w-5 text-center font-medium text-gray-900">{{ $loop->iteration }}</span>
                                <button wire:click="move({{ $slide->id }}, 'up')" @disabled($loop->first) title="Move up" class="rounded p-1 hover:bg-gray-100 disabled:opacity-30">
                                    <x-icon name="chevron-down" class="h-4 w-4 rotate-180" />
                                </button>
                                <button wire:click="move({{ $slide->id }}, 'down')" @disabled($loop->last) title="Move down" class="rounded p-1 hover:bg-gray-100 disabled:opacity-30">
                                    <x-icon name="chevron-down" class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ $slide->imageUrl() }}" alt="" class="h-14 w-24 shrink-0 rounded-md object-cover border border-gray-200">
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 truncate">{{ $slide->title }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ $slide->badge ?: 'No badge' }} &middot; {{ count($slide->cards ?? []) }} card(s)</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-600 hidden md:table-cell">
                            @if ($slide->cta_text)
                                <span class="font-medium text-gray-800">{{ $slide->cta_text }}</span>
                                <span class="block text-xs text-gray-400">{{ \App\Models\HeroSlide::CTA_ACTIONS[$slide->cta_action] ?? '' }}</span>
                            @else
                                <span class="text-gray-400">No button</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <button wire:click="toggle({{ $slide->id }})" role="switch" aria-checked="{{ $slide->is_active ? 'true' : 'false' }}"
                                title="{{ $slide->is_active ? 'Visible - click to hide' : 'Hidden - click to show' }}"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $slide->is_active ? 'bg-indigo-600' : 'bg-gray-300' }}">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ $slide->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                            <span class="ml-2 text-xs {{ $slide->is_active ? 'text-green-700' : 'text-gray-400' }}">{{ $slide->is_active ? 'Visible' : 'Hidden' }}</span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                            <button wire:click="edit({{ $slide->id }})" class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
                            <button wire:click="delete({{ $slide->id }})" wire:confirm="Delete this slide? This cannot be undone." class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-gray-400">
                            No slides yet. The slider is hidden on the website until you add one.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showModal)
        @php
            $bgPreview = $this->previewUrl($imageFile, $existingImage);
            $docPreview = $removeDoctorImage ? null : $this->previewUrl($doctorImageFile, $existingDoctorImage);
        @endphp

        <div class="fixed inset-0 z-40 overflow-y-auto bg-black/40 px-4 py-8" wire:key="slide-modal">
            <div class="mx-auto w-full max-w-5xl rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit Slide' : 'Add Slide' }}</h3>
                    <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-600" aria-label="Close">&times;</button>
                </div>

                <form wire:submit="save" class="grid gap-6 p-6 lg:grid-cols-[3fr_2fr]">
                    {{-- Form --}}
                    <div class="space-y-5">
                        {{-- Images --}}
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Background image {{ $editingId ? '' : '*' }}</label>
                                <input type="file" wire:model="imageFile" accept="image/jpeg,image/png,image/webp"
                                    class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                                <p class="mt-1 text-xs text-gray-400">JPG, PNG or WEBP, up to 5 MB. Wide images (about 1920&times;900) work best.</p>
                                <div wire:loading wire:target="imageFile" class="mt-1 text-xs text-indigo-600">Uploading&hellip;</div>
                                @error('imageFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Doctor photo (round)</label>
                                <input type="file" wire:model="doctorImageFile" accept="image/jpeg,image/png,image/webp"
                                    class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                                <p class="mt-1 text-xs text-gray-400">Optional. Square photo, up to 3 MB.</p>
                                <div wire:loading wire:target="doctorImageFile" class="mt-1 text-xs text-indigo-600">Uploading&hellip;</div>
                                @if ($existingDoctorImage && ! $doctorImageFile)
                                    <label class="mt-1 flex items-center gap-2 text-xs text-gray-600">
                                        <input type="checkbox" wire:model.live="removeDoctorImage" class="rounded border-gray-300 text-indigo-600">
                                        Remove current photo
                                    </label>
                                @endif
                                @error('doctorImageFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Doctor name (under photo)</label>
                                <input wire:model.live.debounce.250ms="doctor_name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('doctor_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Badge</label>
                                <input wire:model.live.debounce.250ms="badge" type="text" placeholder="e.g. Diabetes Care" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('badge') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Heading *</label>
                            <input wire:model.live.debounce.250ms="title" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Qualifications / sub-heading</label>
                                <input wire:model.live.debounce.250ms="credentials" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('credentials') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Speciality line (green)</label>
                                <input wire:model.live.debounce.250ms="specialty" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('specialty') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea wire:model.live.debounce.250ms="description" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Quick cards --}}
                        <div>
                            <div class="flex items-center justify-between">
                                <label class="block text-sm font-medium text-gray-700">Quick cards (up to {{ \App\Models\HeroSlide::MAX_CARDS }})</label>
                                @if (count($cards) < \App\Models\HeroSlide::MAX_CARDS)
                                    <button type="button" wire:click="addCard" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">+ Add card</button>
                                @endif
                            </div>
                            <div class="mt-2 space-y-2">
                                @foreach ($cards as $i => $card)
                                    <div class="flex items-start gap-2" wire:key="card-{{ $i }}">
                                        <select wire:model.live="cards.{{ $i }}.icon" class="w-40 shrink-0 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                            @foreach ($this->icons as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <div class="flex-1">
                                            <input wire:model.live.debounce.250ms="cards.{{ $i }}.title" type="text" placeholder="Card title" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                            @error("cards.$i.title") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                        <button type="button" wire:click="removeCard({{ $i }})" class="mt-1.5 text-gray-400 hover:text-red-600" aria-label="Remove card">&times;</button>
                                    </div>
                                @endforeach
                                @if (empty($cards))
                                    <p class="text-xs text-gray-400">No cards - the row under the text will be hidden.</p>
                                @endif
                            </div>
                        </div>

                        {{-- Button --}}
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Button text</label>
                                <input wire:model.live.debounce.250ms="cta_text" type="text" placeholder="Leave empty for no button" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('cta_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Button action</label>
                                <select wire:model.live="cta_action" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    @foreach (\App\Models\HeroSlide::CTA_ACTIONS as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @if ($cta_action === 'custom')
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Button link</label>
                                <input wire:model="cta_url" type="text" placeholder="/faq or https://example.com" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('cta_url') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            Show this slide on the website
                        </label>
                    </div>

                    {{-- Live preview --}}
                    <div class="lg:sticky lg:top-4 self-start">
                        <p class="mb-2 text-sm font-medium text-gray-700">Live preview</p>
                        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-gray-100 aspect-[4/5] sm:aspect-[3/4]">
                            @if ($bgPreview)
                                <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('{{ $bgPreview }}')"></div>
                            @else
                                <div class="absolute inset-0 flex items-center justify-center text-xs text-gray-400">No background image yet</div>
                            @endif
                            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/60 to-white"></div>

                            <div class="absolute inset-0 flex items-center justify-end px-4">
                                <div class="w-3/4 text-center">
                                    <span class="inline-flex h-12 w-12 items-center justify-center overflow-hidden rounded-full bg-white text-emerald-200 ring-2 ring-white shadow">
                                        @if ($docPreview)
                                            <img src="{{ $docPreview }}" alt="" class="h-full w-full object-cover">
                                        @else
                                            <x-icon name="user-doctor" class="h-full w-full p-2" />
                                        @endif
                                    </span>
                                    @if ($doctor_name) <p class="mt-1 text-[10px] font-semibold text-gray-800">{{ $doctor_name }}</p> @endif
                                    @if ($badge)
                                        <span class="mt-1.5 inline-block rounded-full bg-white/80 px-2 py-0.5 text-[9px] font-semibold text-emerald-700 shadow-sm">{{ $badge }}</span>
                                    @endif
                                    <p class="mt-2 text-sm font-bold leading-tight text-emerald-950">{{ $title ?: 'Slide heading' }}</p>
                                    @if ($credentials) <p class="mt-1 text-[9px] text-gray-700">{{ $credentials }}</p> @endif
                                    @if ($specialty) <p class="text-[9px] font-medium text-emerald-700">{{ $specialty }}</p> @endif
                                    @if ($description) <p class="mt-1.5 text-[8px] leading-snug text-gray-600 line-clamp-3">{{ $description }}</p> @endif

                                    @if (! empty($cards))
                                        <div class="mt-2 grid grid-cols-4 gap-1">
                                            @foreach ($cards as $card)
                                                <div class="flex flex-col items-center gap-0.5 rounded-md border border-gray-100 bg-white px-0.5 py-1.5 shadow-sm">
                                                    <x-icon :name="$card['icon'] ?? 'home'" class="h-3.5 w-3.5 text-emerald-600" />
                                                    <span class="text-[7px] font-medium leading-tight text-gray-700">{{ $card['title'] ?? '' }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if ($cta_text)
                                        <span class="mt-2.5 inline-block rounded-full border border-emerald-950 px-3 py-1 text-[9px] font-semibold text-emerald-950">{{ $cta_text }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-400">A simplified preview. The website shows the full-size version.</p>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-gray-200 pt-4 lg:col-span-2">
                        <button type="button" wire:click="closeModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save,imageFile,doctorImageFile" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">
                            <span wire:loading.remove wire:target="save">Save slide</span>
                            <span wire:loading wire:target="save">Saving&hellip;</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
