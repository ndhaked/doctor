<?php

use App\Models\FaqGroup;
use App\Models\FaqItem;
use App\Models\Setting;
use App\Support\FaqSection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    public ?int $selectedGroupId = null;
    public ?string $status = null;

    // Group form
    public bool $showGroupModal = false;
    public ?int $editingGroupId = null;
    public string $groupTitle = '';
    public string $groupPlacement = 'faq_page';
    public bool $groupActive = true;
    public $iconFile = null;
    public ?string $existingIcon = null;
    public bool $removeIcon = false;

    // Question form
    public bool $showItemModal = false;
    public ?int $editingItemId = null;
    public string $question = '';
    public string $answer = '';
    public bool $itemActive = true;

    // Home page FAQ block settings
    public bool $home_visible = true;
    public string $home_eyebrow = '';
    public string $home_heading = '';
    public string $home_background = '';
    public $backgroundFile = null;

    public function mount(): void
    {
        $this->selectedGroupId = FaqGroup::ordered()->value('id');

        $block = FaqSection::content();

        $this->home_visible = $block['home_visible'];
        $this->home_eyebrow = $block['home_eyebrow'];
        $this->home_heading = $block['home_heading'];
        $this->home_background = $block['home_background'];
    }

    /* ---------- home page FAQ block ---------- */

    public function saveHomeBlock(): void
    {
        $data = $this->validate([
            'home_visible' => ['boolean'],
            'home_eyebrow' => ['nullable', 'string', 'max:60'],
            'home_heading' => ['required', 'string', 'max:150'],
            'backgroundFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'home_heading.required' => 'The heading cannot be empty.',
        ]);

        if ($this->backgroundFile) {
            $this->deleteUpload($this->home_background);
            $this->home_background = 'uploads/'.$this->backgroundFile->store('faq', 'uploads');
            $this->backgroundFile = null;
            Setting::put('faq.home_background', $this->home_background);
        }

        Setting::put('faq.home_visible', $data['home_visible'] ? '1' : '0');
        Setting::put('faq.home_eyebrow', $data['home_eyebrow'] ?? '');
        Setting::put('faq.home_heading', $data['home_heading']);

        $this->status = 'Home page FAQ block saved.';
    }

    public function removeBackground(): void
    {
        $this->deleteUpload($this->home_background);
        $this->home_background = '';
        $this->backgroundFile = null;
        Setting::put('faq.home_background', '');

        $this->status = 'Background photo removed.';
    }

    public function restoreBackground(): void
    {
        $this->home_background = FaqSection::defaults()['faq.home_background'];
        $this->backgroundFile = null;
        Setting::put('faq.home_background', $this->home_background);

        $this->status = 'Original background restored.';
    }

    protected function backgroundPreview(): ?string
    {
        if ($this->backgroundFile) {
            try {
                return $this->backgroundFile->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        return $this->home_background !== '' ? asset($this->home_background) : null;
    }

    #[Computed]
    public function groups()
    {
        return FaqGroup::ordered()->withCount('items')->get();
    }

    #[Computed]
    public function selectedGroup(): ?FaqGroup
    {
        return $this->selectedGroupId ? FaqGroup::with('items')->find($this->selectedGroupId) : null;
    }

    public function selectGroup(int $id): void
    {
        $this->selectedGroupId = $id;
        unset($this->selectedGroup);
    }

    /* ---------- groups ---------- */

    public function createGroup(): void
    {
        $this->resetGroupForm();
        $this->showGroupModal = true;
    }

    public function editGroup(int $id): void
    {
        $group = FaqGroup::findOrFail($id);

        $this->resetGroupForm();
        $this->editingGroupId = $group->id;
        $this->groupTitle = $group->title;
        $this->groupPlacement = $group->placement;
        $this->groupActive = $group->is_active;
        $this->existingIcon = $group->icon;
        $this->showGroupModal = true;
    }

    public function closeGroupModal(): void
    {
        $this->showGroupModal = false;
        $this->resetGroupForm();
    }

    protected function resetGroupForm(): void
    {
        $this->reset(['editingGroupId', 'groupTitle', 'groupPlacement', 'groupActive', 'iconFile', 'existingIcon', 'removeIcon']);
        $this->resetValidation();
    }

    public function saveGroup(): void
    {
        $data = $this->validate([
            'groupTitle' => ['required', 'string', 'max:100'],
            'groupPlacement' => ['required', Rule::in(array_keys(FaqGroup::PLACEMENTS))],
            'groupActive' => ['boolean'],
            'iconFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:1024'],
        ], [
            'groupTitle.required' => 'Please enter a group name.',
        ]);

        $group = $this->editingGroupId ? FaqGroup::findOrFail($this->editingGroupId) : new FaqGroup();

        $payload = [
            'title' => $data['groupTitle'],
            'placement' => $data['groupPlacement'],
            'is_active' => $data['groupActive'],
        ];

        if ($this->iconFile) {
            $this->deleteUpload($group->icon);
            $payload['icon'] = 'uploads/'.$this->iconFile->store('faq', 'uploads');
        } elseif ($this->removeIcon && $group->icon) {
            $this->deleteUpload($group->icon);
            $payload['icon'] = null;
        }

        if (! $group->exists) {
            $payload['sort_order'] = (int) FaqGroup::max('sort_order') + 1;
        }

        $group->fill($payload)->save();

        $this->selectedGroupId = $group->id;
        $this->status = $this->editingGroupId ? 'Group updated.' : 'Group added.';
        $this->closeGroupModal();
        unset($this->groups, $this->selectedGroup);
    }

    public function toggleGroup(int $id): void
    {
        $group = FaqGroup::findOrFail($id);
        $group->update(['is_active' => ! $group->is_active]);

        $this->status = $group->is_active ? 'Group is now visible.' : 'Group hidden from the website.';
        unset($this->groups, $this->selectedGroup);
    }

    public function moveGroup(int $id, string $direction): void
    {
        $this->swapOrder(FaqGroup::ordered()->pluck('id')->all(), $id, $direction, FaqGroup::class);
        unset($this->groups);
    }

    public function deleteGroup(int $id): void
    {
        $group = FaqGroup::findOrFail($id);

        $this->deleteUpload($group->icon);
        $group->delete(); // questions are removed by the foreign key cascade

        if ($this->selectedGroupId === $id) {
            $this->selectedGroupId = FaqGroup::ordered()->value('id');
        }

        $this->status = 'Group and its questions deleted.';
        unset($this->groups, $this->selectedGroup);
    }

    /* ---------- questions ---------- */

    public function createItem(): void
    {
        if (! $this->selectedGroupId) {
            return;
        }

        $this->resetItemForm();
        $this->showItemModal = true;
    }

    public function editItem(int $id): void
    {
        $item = FaqItem::findOrFail($id);

        $this->resetItemForm();
        $this->editingItemId = $item->id;
        $this->question = $item->question;
        $this->answer = $item->answer;
        $this->itemActive = $item->is_active;
        $this->showItemModal = true;
    }

    public function closeItemModal(): void
    {
        $this->showItemModal = false;
        $this->resetItemForm();
    }

    protected function resetItemForm(): void
    {
        $this->reset(['editingItemId', 'question', 'answer', 'itemActive']);
        $this->resetValidation();
    }

    public function saveItem(): void
    {
        $data = $this->validate([
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:3000'],
            'itemActive' => ['boolean'],
        ]);

        if ($this->editingItemId) {
            FaqItem::findOrFail($this->editingItemId)->update([
                'question' => $data['question'],
                'answer' => $data['answer'],
                'is_active' => $data['itemActive'],
            ]);
            $this->status = 'Question updated.';
        } else {
            FaqItem::create([
                'faq_group_id' => $this->selectedGroupId,
                'question' => $data['question'],
                'answer' => $data['answer'],
                'is_active' => $data['itemActive'],
                'sort_order' => (int) FaqItem::where('faq_group_id', $this->selectedGroupId)->max('sort_order') + 1,
            ]);
            $this->status = 'Question added.';
        }

        $this->closeItemModal();
        unset($this->groups, $this->selectedGroup);
    }

    public function toggleItem(int $id): void
    {
        $item = FaqItem::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);

        unset($this->selectedGroup);
    }

    public function moveItem(int $id, string $direction): void
    {
        $item = FaqItem::findOrFail($id);
        $ids = FaqItem::where('faq_group_id', $item->faq_group_id)->orderBy('sort_order')->orderBy('id')->pluck('id')->all();

        $this->swapOrder($ids, $id, $direction, FaqItem::class);
        unset($this->selectedGroup);
    }

    public function deleteItem(int $id): void
    {
        FaqItem::findOrFail($id)->delete();

        $this->status = 'Question deleted.';
        unset($this->groups, $this->selectedGroup);
    }

    /* ---------- helpers ---------- */

    /** Swap $id with its neighbour in $ids and rewrite sort_order 1..n. */
    protected function swapOrder(array $ids, int $id, string $direction, string $model): void
    {
        $pos = array_search($id, $ids, true);
        $swap = $direction === 'up' ? $pos - 1 : $pos + 1;

        if ($pos === false || ! isset($ids[$swap])) {
            return;
        }

        [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];

        foreach ($ids as $order => $rowId) {
            $model::whereKey($rowId)->update(['sort_order' => $order + 1]);
        }
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

        return ($this->existingIcon && ! $this->removeIcon) ? asset($this->existingIcon) : null;
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">FAQs</h2>
    </x-slot:header>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <p class="text-sm text-gray-500">
            Groups appear as tabs on the FAQs page, or as the FAQ block on the home page.
            <a href="{{ route('faq') }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-medium">View FAQs page &nearr;</a>
        </p>
    </div>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    {{-- Home page FAQ block settings --}}
    <details class="group mb-6 rounded-xl border border-gray-200 bg-white" @if ($errors->hasAny(['home_heading', 'home_eyebrow', 'backgroundFile'])) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-4 [&::-webkit-details-marker]:hidden">
            <span>
                <span class="block text-sm font-semibold text-gray-900">Home page FAQ block settings</span>
                <span class="block text-xs text-gray-500">Show or hide the block, and change its label, heading and background photo.</span>
            </span>
            <x-icon name="chevron-down" class="h-5 w-5 text-gray-400 transition group-open:rotate-180" />
        </summary>

        @php $bgPreview = $this->backgroundPreview(); @endphp
        <form wire:submit="saveHomeBlock" class="space-y-5 border-t border-gray-200 p-5">
            <label class="flex items-start gap-3 rounded-lg border {{ $home_visible ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }} p-3">
                <input type="checkbox" wire:model.live="home_visible" class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <span>
                    <span class="block text-sm font-medium text-gray-900">Show the FAQ block on the home page</span>
                    <span class="block text-xs text-gray-500">Currently {{ $home_visible ? 'shown' : 'hidden' }}. Press &ldquo;Save block&rdquo; to apply.</span>
                </span>
            </label>

            <div class="grid gap-4 sm:grid-cols-[200px_1fr]">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Small label</label>
                    <input wire:model="home_eyebrow" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('home_eyebrow') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Heading *</label>
                    <input wire:model="home_heading" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('home_heading') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Background photo</label>
                <div class="mt-1 flex flex-wrap items-start gap-4">
                    @if ($bgPreview)
                        <img src="{{ $bgPreview }}" alt="" class="h-20 w-36 rounded-md border border-gray-200 object-cover">
                    @else
                        <div class="flex h-20 w-36 items-center justify-center rounded-md bg-gray-100 text-xs text-gray-400">No photo</div>
                    @endif
                    <div>
                        <input type="file" wire:model="backgroundFile" accept="image/jpeg,image/png,image/webp"
                            class="block text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                        <p class="mt-1 text-xs text-gray-400">JPG, PNG or WEBP, up to 4 MB. A light, soft image works best behind the questions.</p>
                        <div wire:loading wire:target="backgroundFile" class="text-xs text-indigo-600">Uploading&hellip;</div>
                        <div class="mt-1 flex gap-3 text-xs">
                            @if ($home_background !== '')
                                <button type="button" wire:click="removeBackground" wire:confirm="Remove the background photo?" class="font-medium text-red-600 hover:text-red-800">Remove photo</button>
                            @endif
                            <button type="button" wire:click="restoreBackground" class="font-medium text-gray-500 hover:text-gray-800">Use original</button>
                        </div>
                        @error('backgroundFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="saveHomeBlock,backgroundFile" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">Save block</button>
            </div>
        </form>
    </details>
    <div class="grid gap-6 lg:grid-cols-[320px_1fr] items-start">
        {{-- Groups --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                <h3 class="text-sm font-semibold text-gray-900">Groups</h3>
                <button wire:click="createGroup" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">+ Add group</button>
            </div>

            <ul class="divide-y divide-gray-100">
                @forelse ($this->groups as $group)
                    <li wire:key="group-{{ $group->id }}" class="group/row {{ $selectedGroupId === $group->id ? 'bg-indigo-50/60' : '' }}">
                        <div class="flex items-start gap-2 px-3 py-3">
                            <button type="button" wire:click="selectGroup({{ $group->id }})" class="flex min-w-0 flex-1 items-center gap-3 text-left">
                                @if ($group->iconUrl())
                                    <img src="{{ $group->iconUrl() }}" alt="" class="h-8 w-8 shrink-0 object-contain">
                                @else
                                    <span class="h-8 w-8 shrink-0 rounded-md bg-gray-100"></span>
                                @endif
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium {{ $group->is_active ? 'text-gray-900' : 'text-gray-400' }}">{{ $group->title }}</span>
                                    <span class="block text-xs text-gray-400">
                                        {{ $group->items_count }} question(s) &middot; {{ $group->placement === 'home' ? 'Home page' : 'FAQs page' }}
                                    </span>
                                </span>
                            </button>

                            <div class="flex shrink-0 items-center text-gray-400">
                                <button wire:click="moveGroup({{ $group->id }}, 'up')" @disabled($loop->first) title="Move up" class="rounded p-1 hover:bg-gray-100 disabled:opacity-30"><x-icon name="chevron-down" class="h-4 w-4 rotate-180" /></button>
                                <button wire:click="moveGroup({{ $group->id }}, 'down')" @disabled($loop->last) title="Move down" class="rounded p-1 hover:bg-gray-100 disabled:opacity-30"><x-icon name="chevron-down" class="h-4 w-4" /></button>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 px-3 pb-3 pl-14 text-xs">
                            <button wire:click="toggleGroup({{ $group->id }})" role="switch" aria-checked="{{ $group->is_active ? 'true' : 'false' }}"
                                class="relative inline-flex h-5 w-9 items-center rounded-full transition {{ $group->is_active ? 'bg-indigo-600' : 'bg-gray-300' }}" title="{{ $group->is_active ? 'Visible' : 'Hidden' }}">
                                <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition {{ $group->is_active ? 'translate-x-[1.125rem]' : 'translate-x-0.5' }}"></span>
                            </button>
                            <button wire:click="editGroup({{ $group->id }})" class="font-medium text-indigo-600 hover:text-indigo-800">Edit</button>
                            <button wire:click="deleteGroup({{ $group->id }})" wire:confirm="Delete this group and all of its questions?" class="font-medium text-red-600 hover:text-red-800">Delete</button>
                        </div>
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-sm text-gray-400">No groups yet.</li>
                @endforelse
            </ul>
        </div>

        {{-- Questions of the selected group --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            @if ($this->selectedGroup)
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">Questions in &ldquo;{{ $this->selectedGroup->title }}&rdquo;</h3>
                    <button wire:click="createItem" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">+ Add question</button>
                </div>

                <ul class="divide-y divide-gray-100">
                    @forelse ($this->selectedGroup->items as $item)
                        <li wire:key="item-{{ $item->id }}" class="flex items-start gap-3 px-4 py-3">
                            <span class="mt-0.5 w-6 shrink-0 text-center text-xs font-medium text-gray-400">{{ $loop->iteration }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium {{ $item->is_active ? 'text-gray-900' : 'text-gray-400' }}">{{ $item->question }}</p>
                                <p class="mt-0.5 text-xs text-gray-500 line-clamp-2">{{ $item->answer }}</p>
                                <div class="mt-2 flex items-center gap-3 text-xs">
                                    <button wire:click="toggleItem({{ $item->id }})" role="switch" aria-checked="{{ $item->is_active ? 'true' : 'false' }}"
                                        class="relative inline-flex h-5 w-9 items-center rounded-full transition {{ $item->is_active ? 'bg-indigo-600' : 'bg-gray-300' }}" title="{{ $item->is_active ? 'Visible' : 'Hidden' }}">
                                        <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition {{ $item->is_active ? 'translate-x-[1.125rem]' : 'translate-x-0.5' }}"></span>
                                    </button>
                                    <button wire:click="editItem({{ $item->id }})" class="font-medium text-indigo-600 hover:text-indigo-800">Edit</button>
                                    <button wire:click="deleteItem({{ $item->id }})" wire:confirm="Delete this question?" class="font-medium text-red-600 hover:text-red-800">Delete</button>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center text-gray-400">
                                <button wire:click="moveItem({{ $item->id }}, 'up')" @disabled($loop->first) title="Move up" class="rounded p-1 hover:bg-gray-100 disabled:opacity-30"><x-icon name="chevron-down" class="h-4 w-4 rotate-180" /></button>
                                <button wire:click="moveItem({{ $item->id }}, 'down')" @disabled($loop->last) title="Move down" class="rounded p-1 hover:bg-gray-100 disabled:opacity-30"><x-icon name="chevron-down" class="h-4 w-4" /></button>
                            </div>
                        </li>
                    @empty
                        <li class="px-4 py-10 text-center text-sm text-gray-400">No questions in this group yet.</li>
                    @endforelse
                </ul>
            @else
                <p class="px-4 py-14 text-center text-sm text-gray-400">Add a group to start adding questions.</p>
            @endif
        </div>
    </div>

    {{-- Group modal --}}
    @if ($showGroupModal)
        @php $iconPreview = $this->iconPreview(); @endphp
        <div class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-black/40 px-4 py-8" wire:key="group-modal">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">{{ $editingGroupId ? 'Edit Group' : 'Add Group' }}</h3>

                <form wire:submit="saveGroup" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Group name *</label>
                        <input wire:model="groupTitle" type="text" placeholder="e.g. Viral Fever" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('groupTitle') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Shown on</label>
                        <select wire:model="groupPlacement" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @foreach (\App\Models\FaqGroup::PLACEMENTS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-400">Home page groups are listed together in the FAQ block on the home page.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Icon (FAQs page tabs)</label>
                        <div class="mt-1 flex items-center gap-3">
                            @if ($iconPreview)
                                <img src="{{ $iconPreview }}" alt="" class="h-10 w-10 rounded border border-gray-200 object-contain p-1">
                            @endif
                            <div>
                                <input type="file" wire:model="iconFile" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                    class="block text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                                <p class="mt-1 text-xs text-gray-400">Small square image (PNG/WEBP/SVG), up to 1 MB.</p>
                                <div wire:loading wire:target="iconFile" class="text-xs text-indigo-600">Uploading&hellip;</div>
                                @if ($existingIcon && ! $iconFile)
                                    <label class="mt-1 flex items-center gap-2 text-xs text-gray-600">
                                        <input type="checkbox" wire:model.live="removeIcon" class="rounded border-gray-300 text-indigo-600"> Remove current icon
                                    </label>
                                @endif
                            </div>
                        </div>
                        @error('iconFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="groupActive" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"> Visible on the website
                    </label>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeGroupModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveGroup,iconFile" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Question modal --}}
    @if ($showItemModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-black/40 px-4 py-8" wire:key="item-modal">
            <div class="w-full max-w-xl rounded-xl bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">{{ $editingItemId ? 'Edit Question' : 'Add Question' }}</h3>

                <form wire:submit="saveItem" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Question *</label>
                        <input wire:model="question" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('question') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Answer *</label>
                        <textarea wire:model="answer" rows="6" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                        @error('answer') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="itemActive" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"> Visible on the website
                    </label>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeItemModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
