<?php

use App\Models\Setting;
use App\Models\WhyChooseItem;
use App\Support\WhyChooseSection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component
{
    // Block settings
    public bool $visible = true;
    public string $heading = '';
    public string $subheading = '';
    public string $content = '';

    // Accordion row form
    public bool $showModal = false;
    public ?int $editingId = null;
    public string $title = '';
    public string $body = '';
    public bool $is_active = true;

    public ?string $status = null;

    public function mount(): void
    {
        $section = WhyChooseSection::content();

        $this->visible = $section['visible'];
        $this->heading = $section['heading'];
        $this->subheading = $section['subheading'];
        $this->content = $section['content'];
    }

    #[Computed]
    public function items()
    {
        return WhyChooseItem::ordered()->get();
    }

    /* ---------- block settings ---------- */

    public function saveBlock(): void
    {
        $data = $this->validate([
            'visible' => ['boolean'],
            'heading' => ['required', 'string', 'max:150'],
            'subheading' => ['nullable', 'string', 'max:250'],
            'content' => ['nullable', 'string', 'max:3000'],
        ], [
            'heading.required' => 'The heading cannot be empty.',
        ]);

        Setting::put('whychoose.visible', $data['visible'] ? '1' : '0');
        Setting::put('whychoose.heading', $data['heading']);
        Setting::put('whychoose.subheading', $data['subheading'] ?? '');
        Setting::put('whychoose.content', $data['content'] ?? '');

        $this->status = 'Block saved.';
    }

    /* ---------- accordion rows ---------- */

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $item = WhyChooseItem::findOrFail($id);

        $this->resetForm();
        $this->editingId = $item->id;
        $this->title = $item->title;
        $this->body = (string) $item->content;
        $this->is_active = $item->is_active;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'title', 'body', 'is_active']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ]);

        $item = $this->editingId ? WhyChooseItem::findOrFail($this->editingId) : new WhyChooseItem();

        $payload = ['title' => $data['title'], 'content' => $data['body'] ?: null, 'is_active' => $data['is_active']];

        if (! $item->exists) {
            $payload['sort_order'] = (int) WhyChooseItem::max('sort_order') + 1;
        }

        $item->fill($payload)->save();

        $this->status = $this->editingId ? 'Row updated.' : 'Row added.';
        $this->closeModal();
        unset($this->items);
    }

    public function toggle(int $id): void
    {
        $item = WhyChooseItem::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);

        unset($this->items);
    }

    public function move(int $id, string $direction): void
    {
        $ids = WhyChooseItem::ordered()->pluck('id')->all();
        $pos = array_search($id, $ids, true);
        $swap = $direction === 'up' ? $pos - 1 : $pos + 1;

        if ($pos === false || ! isset($ids[$swap])) {
            return;
        }

        [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];

        foreach ($ids as $order => $itemId) {
            WhyChooseItem::whereKey($itemId)->update(['sort_order' => $order + 1]);
        }

        unset($this->items);
    }

    public function delete(int $id): void
    {
        WhyChooseItem::findOrFail($id)->delete();

        $this->status = 'Row deleted.';
        unset($this->items);
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Why Choose</h2>
    </x-slot:header>

    <p class="mb-4 text-sm text-gray-500">
        The &ldquo;Why Choose?&rdquo; block with the expanding rows on the home page.
        <a href="{{ route('home') }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-medium">View website &nearr;</a>
    </p>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    <form wire:submit="saveBlock" class="mb-6 max-w-4xl space-y-5 rounded-xl border border-gray-200 bg-white p-5">
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
            <label class="block text-sm font-medium text-gray-700">Sub-heading</label>
            <input wire:model="subheading" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            @error('subheading') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Text</label>
            <textarea wire:model="content" rows="7" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
            @error('content') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save block</button>
        </div>
    </form>

    <div class="mb-3 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900">Expanding rows <span class="font-normal text-gray-400">(the first visible row starts open)</span></h3>
        <button wire:click="create" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">+ Add Row</button>
    </div>

    <div class="max-w-4xl overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium w-24">Order</th>
                    <th class="px-4 py-3 font-medium">Row</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->items as $item)
                    <tr wire:key="item-{{ $item->id }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1 text-gray-500">
                                <span class="w-5 text-center font-medium text-gray-900">{{ $loop->iteration }}</span>
                                <button wire:click="move({{ $item->id }}, 'up')" @disabled($loop->first) title="Move up" class="rounded p-1 hover:bg-gray-100 disabled:opacity-30"><x-icon name="chevron-down" class="h-4 w-4 rotate-180" /></button>
                                <button wire:click="move({{ $item->id }}, 'down')" @disabled($loop->last) title="Move down" class="rounded p-1 hover:bg-gray-100 disabled:opacity-30"><x-icon name="chevron-down" class="h-4 w-4" /></button>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium {{ $item->is_active ? 'text-gray-900' : 'text-gray-400' }}">{{ $item->title }}</p>
                            <p class="max-w-md truncate text-xs text-gray-500">{{ $item->content }}</p>
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
                            <button wire:click="delete({{ $item->id }})" wire:confirm="Delete this row?" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-gray-400">No rows yet. The right-hand column is hidden on the website until you add one.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-black/40 px-4 py-8" wire:key="item-modal">
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit Row' : 'Add Row' }}</h3>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Title *</label>
                        <input wire:model="title" type="text" placeholder="e.g. Expertise" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Text shown when the row opens</label>
                        <textarea wire:model="body" rows="6" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                        @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"> Visible on the website
                    </label>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
