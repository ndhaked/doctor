<?php

use App\Models\Setting;
use App\Models\Symptom;
use Illuminate\Support\Facades\Storage;
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
    public bool $is_active = true;
    public $iconFile = null;
    public ?string $existingIcon = null;

    public ?string $status = null;

    public function mount(): void
    {
        $text = Symptom::sectionText();

        $this->eyebrow = $text['eyebrow'];
        $this->heading = $text['heading'];
        $this->intro = $text['intro'];
    }

    #[Computed]
    public function symptoms()
    {
        return Symptom::ordered()->get();
    }

    /* ---------- section text ---------- */

    public function saveText(): void
    {
        $data = $this->validate([
            'eyebrow' => ['nullable', 'string', 'max:60'],
            'heading' => ['required', 'string', 'max:200'],
            'intro' => ['nullable', 'string', 'max:300'],
        ], [
            'heading.required' => 'The section heading cannot be empty.',
        ]);

        Setting::put('symptoms.eyebrow', $data['eyebrow'] ?? '');
        Setting::put('symptoms.heading', $data['heading']);
        Setting::put('symptoms.intro', $data['intro'] ?? '');

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
        $symptom = Symptom::findOrFail($id);

        $this->resetForm();
        $this->editingId = $symptom->id;
        $this->title = $symptom->title;
        $this->description = (string) $symptom->description;
        $this->is_active = $symptom->is_active;
        $this->existingIcon = $symptom->icon;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'title', 'description', 'is_active', 'iconFile', 'existingIcon']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:400'],
            'is_active' => ['boolean'],
            'iconFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:1024'],
        ]);

        $symptom = $this->editingId ? Symptom::findOrFail($this->editingId) : new Symptom();

        $payload = [
            'title' => $data['title'],
            'description' => $data['description'] ?: null,
            'is_active' => $data['is_active'],
        ];

        if ($this->iconFile) {
            $this->deleteUpload($symptom->icon);
            $payload['icon'] = 'uploads/'.$this->iconFile->store('symptoms', 'uploads');
        }

        if (! $symptom->exists) {
            $payload['sort_order'] = (int) Symptom::max('sort_order') + 1;
        }

        $symptom->fill($payload)->save();

        $this->status = $this->editingId ? 'Symptom updated.' : 'Symptom added.';
        $this->closeModal();
        unset($this->symptoms);
    }

    public function toggle(int $id): void
    {
        $symptom = Symptom::findOrFail($id);
        $symptom->update(['is_active' => ! $symptom->is_active]);

        $this->status = $symptom->is_active ? 'Symptom is now visible.' : 'Symptom hidden from the website.';
        unset($this->symptoms);
    }

    public function move(int $id, string $direction): void
    {
        $ids = Symptom::ordered()->pluck('id')->all();
        $pos = array_search($id, $ids, true);
        $swap = $direction === 'up' ? $pos - 1 : $pos + 1;

        if ($pos === false || ! isset($ids[$swap])) {
            return;
        }

        [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];

        foreach ($ids as $order => $symptomId) {
            Symptom::whereKey($symptomId)->update(['sort_order' => $order + 1]);
        }

        unset($this->symptoms);
    }

    public function delete(int $id): void
    {
        $symptom = Symptom::findOrFail($id);

        $this->deleteUpload($symptom->icon);
        $symptom->delete();

        $this->status = 'Symptom deleted.';
        unset($this->symptoms);
    }

    /** Only files uploaded through the admin are removed; bundled images are never touched. */
    protected function deleteUpload(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/')) {
            Storage::disk('uploads')->delete(substr($path, strlen('uploads/')));
        }
    }

    protected function iconPreview(): ?string
    {
        if ($this->iconFile) {
            try {
                return $this->iconFile->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        return $this->existingIcon ? asset($this->existingIcon) : null;
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Symptoms Section</h2>
    </x-slot:header>

    <p class="mb-4 text-sm text-gray-500">
        The &ldquo;Symptoms That You Should Visit&hellip;&rdquo; block on the home page. Changes go live immediately.
        <a href="{{ route('home') }}#symptoms" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-medium">View website &nearr;</a>
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
                <label class="block text-sm font-medium text-gray-700">Intro line</label>
                <input wire:model="intro" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('intro') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="mt-4 flex justify-end">
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save text</button>
        </div>
    </form>

    {{-- Cards --}}
    <div class="mb-3 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900">Symptom cards</h3>
        <button wire:click="create" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">+ Add Symptom</button>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium w-24">Order</th>
                    <th class="px-4 py-3 font-medium">Symptom</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->symptoms as $symptom)
                    <tr wire:key="symptom-{{ $symptom->id }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1 text-gray-500">
                                <span class="w-5 text-center font-medium text-gray-900">{{ $loop->iteration }}</span>
                                <button wire:click="move({{ $symptom->id }}, 'up')" @disabled($loop->first) title="Move up" class="rounded p-1 hover:bg-gray-100 disabled:opacity-30"><x-icon name="chevron-down" class="h-4 w-4 rotate-180" /></button>
                                <button wire:click="move({{ $symptom->id }}, 'down')" @disabled($loop->last) title="Move down" class="rounded p-1 hover:bg-gray-100 disabled:opacity-30"><x-icon name="chevron-down" class="h-4 w-4" /></button>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-gray-100">
                                    @if ($symptom->iconUrl())
                                        <img src="{{ $symptom->iconUrl() }}" alt="" class="h-8 w-8 object-contain">
                                    @endif
                                </span>
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900">{{ $symptom->title }}</p>
                                    <p class="max-w-md truncate text-xs text-gray-500">{{ $symptom->description }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <button wire:click="toggle({{ $symptom->id }})" role="switch" aria-checked="{{ $symptom->is_active ? 'true' : 'false' }}"
                                title="{{ $symptom->is_active ? 'Visible - click to hide' : 'Hidden - click to show' }}"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $symptom->is_active ? 'bg-indigo-600' : 'bg-gray-300' }}">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ $symptom->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                            <span class="ml-2 text-xs {{ $symptom->is_active ? 'text-green-700' : 'text-gray-400' }}">{{ $symptom->is_active ? 'Visible' : 'Hidden' }}</span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                            <button wire:click="edit({{ $symptom->id }})" class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
                            <button wire:click="delete({{ $symptom->id }})" wire:confirm="Delete this symptom card?" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-gray-400">No symptom cards yet. The section is hidden on the website until you add one.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showModal)
        @php $preview = $this->iconPreview(); @endphp
        <div class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-black/40 px-4 py-8" wire:key="symptom-modal">
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit Symptom' : 'Add Symptom' }}</h3>

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

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Icon</label>
                        <input type="file" wire:model="iconFile" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                            class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                        <p class="mt-1 text-xs text-gray-400">Small square image (PNG, WEBP or SVG), up to 1 MB.</p>
                        <div wire:loading wire:target="iconFile" class="text-xs text-indigo-600">Uploading&hellip;</div>
                        @error('iconFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Live preview of the card --}}
                    <div class="rounded-lg bg-sky-50 p-4">
                        <p class="mb-2 text-xs text-gray-500">Preview</p>
                        <div class="mx-auto max-w-[220px] rounded-md bg-white px-4 py-4 text-center">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100">
                                @if ($preview)
                                    <img src="{{ $preview }}" alt="" class="h-9 w-9 object-contain">
                                @endif
                            </div>
                            <p class="mt-3 text-xs font-bold text-emerald-950">{{ $title ?: 'Title' }}</p>
                            <p class="mt-1.5 text-[11px] leading-5 text-gray-700">{{ $description }}</p>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"> Visible on the website
                    </label>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save,iconFile" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
