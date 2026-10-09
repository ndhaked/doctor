<?php

use App\Models\GalleryGroup;
use App\Models\GalleryImage;
use App\Models\Setting;
use App\Support\GalleryPageSection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    // Page texts
    public string $page_title = '';
    public string $page_intro = '';

    // Groups
    public ?int $selectedGroupId = null;
    public bool $showGroupModal = false;
    public ?int $editingGroupId = null;
    public string $group_title = '';
    public bool $group_active = true;

    // Images
    public array $uploads = [];
    public ?int $editingImageId = null;
    public string $image_caption = '';

    public ?string $status = null;

    public function mount(): void
    {
        $page = GalleryPageSection::content();
        $this->page_title = $page['title'];
        $this->page_intro = $page['intro'];
        $this->selectedGroupId = GalleryGroup::ordered()->value('id');
    }

    #[Computed]
    public function groups()
    {
        return GalleryGroup::ordered()->withCount('images')->get();
    }

    #[Computed]
    public function selectedGroup(): ?GalleryGroup
    {
        return $this->selectedGroupId ? GalleryGroup::with('images')->find($this->selectedGroupId) : null;
    }

    protected function refresh(): void
    {
        unset($this->groups, $this->selectedGroup);
    }

    /* ---------- page texts ---------- */

    public function savePage(): void
    {
        $data = $this->validate([
            'page_title' => ['required', 'string', 'max:100'],
            'page_intro' => ['nullable', 'string', 'max:400'],
        ], ['page_title.required' => 'The page heading cannot be empty.']);

        Setting::put('gallery.title', $data['page_title']);
        Setting::put('gallery.intro', $data['page_intro'] ?? '');

        $this->status = 'Page texts saved.';
    }

    /* ---------- groups ---------- */

    public function selectGroup(int $id): void
    {
        $this->selectedGroupId = GalleryGroup::findOrFail($id)->id;
        $this->reset(['uploads', 'editingImageId']);
        $this->resetValidation();
        $this->refresh();
    }

    public function createGroup(): void
    {
        $this->reset(['editingGroupId', 'group_title']);
        $this->group_active = true;
        $this->resetValidation();
        $this->showGroupModal = true;
    }

    public function editGroup(int $id): void
    {
        $group = GalleryGroup::findOrFail($id);

        $this->resetValidation();
        $this->editingGroupId = $group->id;
        $this->group_title = $group->title;
        $this->group_active = $group->is_active;
        $this->showGroupModal = true;
    }

    public function closeGroupModal(): void
    {
        $this->showGroupModal = false;
        $this->resetValidation();
    }

    public function saveGroup(): void
    {
        $data = $this->validate([
            'group_title' => ['required', 'string', 'max:80'],
            'group_active' => ['boolean'],
        ], ['group_title.required' => 'Please enter a group name.']);

        $group = $this->editingGroupId ? GalleryGroup::findOrFail($this->editingGroupId) : new GalleryGroup();

        $payload = ['title' => $data['group_title'], 'is_active' => $data['group_active']];

        if (! $group->exists) {
            $payload['sort_order'] = (int) GalleryGroup::max('sort_order') + 1;
        }

        $group->fill($payload)->save();

        $this->selectedGroupId = $group->id;
        $this->status = $this->editingGroupId ? 'Group updated.' : 'Group added.';
        $this->showGroupModal = false;
        $this->refresh();
    }

    public function toggleGroup(int $id): void
    {
        $group = GalleryGroup::findOrFail($id);
        $group->update(['is_active' => ! $group->is_active]);

        $this->refresh();
    }

    public function moveGroup(int $id, string $direction): void
    {
        $this->swap(GalleryGroup::ordered()->pluck('id')->all(), $id, $direction, GalleryGroup::class);
        $this->refresh();
    }

    public function deleteGroup(int $id): void
    {
        $group = GalleryGroup::with('images')->findOrFail($id);

        foreach ($group->images as $image) {
            $this->deleteUpload($image->image);
        }

        $group->delete();

        if ($this->selectedGroupId === $id) {
            $this->selectedGroupId = GalleryGroup::ordered()->value('id');
        }

        $this->status = 'Group and its images deleted.';
        $this->refresh();
    }

    /* ---------- images ---------- */

    public function updatedUploads(): void
    {
        $this->validateOnly('uploads.*', [
            'uploads.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ], [
            'uploads.*.image' => 'Each file must be an image.',
            'uploads.*.mimes' => 'Images must be JPG, PNG, WebP or GIF.',
            'uploads.*.max' => 'Each image must be 5 MB or smaller.',
        ]);
    }

    public function addImages(): void
    {
        $group = GalleryGroup::findOrFail($this->selectedGroupId);

        $this->validate([
            'uploads' => ['required', 'array', 'min:1', 'max:30'],
            'uploads.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ], [
            'uploads.required' => 'Choose at least one image to upload.',
            'uploads.min' => 'Choose at least one image to upload.',
            'uploads.max' => 'You can upload up to 30 images at a time.',
            'uploads.*.image' => 'Each file must be an image.',
            'uploads.*.mimes' => 'Images must be JPG, PNG, WebP or GIF.',
            'uploads.*.max' => 'Each image must be 5 MB or smaller.',
        ]);

        $order = (int) $group->images()->max('sort_order');

        foreach ($this->uploads as $file) {
            $group->images()->create([
                'image' => 'uploads/'.$file->store('gallery', 'uploads'),
                'sort_order' => ++$order,
            ]);
        }

        $count = count($this->uploads);
        $this->uploads = [];
        $this->status = $count === 1 ? '1 image added.' : "$count images added.";
        $this->refresh();
    }

    public function editImage(int $id): void
    {
        $image = GalleryImage::findOrFail($id);

        $this->editingImageId = $image->id;
        $this->image_caption = (string) $image->caption;
    }

    public function cancelImageEdit(): void
    {
        $this->reset(['editingImageId', 'image_caption']);
        $this->resetValidation();
    }

    public function saveImage(): void
    {
        $data = $this->validate(['image_caption' => ['nullable', 'string', 'max:150']]);

        GalleryImage::findOrFail($this->editingImageId)->update(['caption' => $data['image_caption'] ?: null]);

        $this->reset(['editingImageId', 'image_caption']);
        $this->status = 'Caption saved.';
        $this->refresh();
    }

    public function toggleImage(int $id): void
    {
        $image = GalleryImage::findOrFail($id);
        $image->update(['is_active' => ! $image->is_active]);

        $this->refresh();
    }

    public function moveImage(int $id, string $direction): void
    {
        $image = GalleryImage::findOrFail($id);
        $this->swap($image->group->images()->pluck('id')->all(), $id, $direction, GalleryImage::class);
        $this->refresh();
    }

    public function deleteImage(int $id): void
    {
        $image = GalleryImage::findOrFail($id);
        $this->deleteUpload($image->image);
        $image->delete();

        $this->status = 'Image deleted.';
        $this->refresh();
    }

    /* ---------- helpers ---------- */

    protected function swap(array $ids, int $id, string $direction, string $model): void
    {
        $pos = array_search($id, $ids, true);
        $other = $direction === 'up' ? $pos - 1 : $pos + 1;

        if ($pos === false || ! isset($ids[$other])) {
            return;
        }

        [$ids[$pos], $ids[$other]] = [$ids[$other], $ids[$pos]];

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
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Image Gallery</h2>
    </x-slot:header>

    <p class="mb-4 text-sm text-gray-500">
        Photos shown on the Image Gallery page, organised into groups. Visitors click a photo to see it large.
        <a href="{{ route('gallery') }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-medium">View website &nearr;</a>
    </p>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    {{-- Page texts --}}
    <form wire:submit="savePage" class="mb-6 max-w-4xl space-y-4 rounded-xl border border-gray-200 bg-white p-5">
        <div>
            <label class="block text-sm font-medium text-gray-700">Page heading</label>
            <input type="text" wire:model="page_title" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            @error('page_title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Intro text</label>
            <textarea wire:model="page_intro" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
            @error('page_intro') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save page texts</button>
    </form>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Groups --}}
        <div class="rounded-xl border border-gray-200 bg-white lg:col-span-1">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                <h3 class="text-sm font-semibold text-gray-900">Groups</h3>
                <button type="button" wire:click="createGroup" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">Add group</button>
            </div>

            <ul class="divide-y divide-gray-100">
                @forelse ($this->groups as $group)
                    <li wire:key="group-{{ $group->id }}" class="px-4 py-3 {{ $selectedGroupId === $group->id ? 'bg-indigo-50' : '' }}">
                        <button type="button" wire:click="selectGroup({{ $group->id }})" class="block w-full text-left">
                            <span class="text-sm font-medium text-gray-900">{{ $group->title }}</span>
                            <span class="ml-1 text-xs text-gray-500">({{ $group->images_count }})</span>
                            @unless ($group->is_active) <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-500">Hidden</span> @endunless
                        </button>
                        <div class="mt-1 flex flex-wrap gap-x-3 text-xs">
                            <button type="button" wire:click="editGroup({{ $group->id }})" class="text-indigo-600 hover:text-indigo-800">Rename</button>
                            <button type="button" wire:click="toggleGroup({{ $group->id }})" class="text-gray-500 hover:text-gray-800">{{ $group->is_active ? 'Hide' : 'Show' }}</button>
                            <button type="button" wire:click="moveGroup({{ $group->id }}, 'up')" class="text-gray-500 hover:text-gray-800" aria-label="Move up">&uarr;</button>
                            <button type="button" wire:click="moveGroup({{ $group->id }}, 'down')" class="text-gray-500 hover:text-gray-800" aria-label="Move down">&darr;</button>
                            <button type="button" wire:click="deleteGroup({{ $group->id }})" wire:confirm="Delete this group and all its images?" class="text-red-600 hover:text-red-800">Delete</button>
                        </div>
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-sm text-gray-400">No groups yet. Add one to start uploading photos.</li>
                @endforelse
            </ul>
        </div>

        {{-- Images of the selected group --}}
        <div class="rounded-xl border border-gray-200 bg-white lg:col-span-2">
            @if ($this->selectedGroup)
                @php $g = $this->selectedGroup; @endphp
                <div class="border-b border-gray-200 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">Images in &ldquo;{{ $g->title }}&rdquo;</h3>
                </div>

                <form wire:submit="addImages" class="space-y-2 border-b border-gray-200 px-4 py-4">
                    <label class="block text-sm font-medium text-gray-700">Add images (you can pick several at once)</label>
                    <input type="file" wire:model="uploads" multiple accept="image/jpeg,image/png,image/webp,image/gif"
                        class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                    <div wire:loading wire:target="uploads" class="text-xs text-indigo-600">Uploading&hellip;</div>
                    @error('uploads') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('uploads.*') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                    @if ($uploads)
                        <div class="flex flex-wrap gap-2 pt-1">
                            @foreach ($uploads as $i => $file)
                                @if (method_exists($file, 'isPreviewable') && $file->isPreviewable())
                                    <img wire:key="up-{{ $i }}" src="{{ $file->temporaryUrl() }}" alt="" class="h-16 w-16 rounded-lg object-cover border border-gray-200">
                                @endif
                            @endforeach
                        </div>
                    @endif

                    <button type="submit" wire:loading.attr="disabled" wire:target="addImages,uploads"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">Add to this group</button>
                </form>

                {{-- Photos + large preview (same viewer idea as the website) --}}
                <div x-data="{
                        open: false, i: 0, items: [],
                        show(n) { this.items = [...$root.querySelectorAll('[data-admin-img]')].map(e => ({ src: e.dataset.src, caption: e.dataset.caption })); this.i = n; this.open = true; },
                        step(d) { this.i = (this.i + d + this.items.length) % this.items.length; }
                    }"
                    x-on:keydown.escape.window="open = false" x-on:keydown.arrow-left.window="open && step(-1)" x-on:keydown.arrow-right.window="open && step(1)">

                    <div class="grid grid-cols-2 gap-4 p-4 md:grid-cols-3 xl:grid-cols-4">
                        @forelse ($g->images as $n => $image)
                            <div wire:key="image-{{ $image->id }}" class="flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white">
                                <button type="button" x-on:click="show({{ $n }})" data-admin-img data-src="{{ $image->url() }}" data-caption="{{ $image->caption ?: $g->title }}"
                                    class="group relative block aspect-[6/7] w-full overflow-hidden bg-gray-100" aria-label="View image">
                                    <img src="{{ $image->url() }}" alt="{{ $image->caption }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105 {{ $image->is_active ? '' : 'opacity-40 grayscale' }}">
                                    @unless ($image->is_active)
                                        <span class="absolute left-2 top-2 rounded bg-gray-900/80 px-1.5 py-0.5 text-[10px] font-medium text-white">Hidden</span>
                                    @endunless
                                </button>

                                <div class="flex flex-1 flex-col gap-2 p-2.5 text-xs">
                                    @if ($editingImageId === $image->id)
                                        <form wire:submit="saveImage" class="space-y-2">
                                            <input type="text" wire:model="image_caption" placeholder="Caption (optional)" class="block w-full rounded border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                                            @error('image_caption') <p class="text-red-600">{{ $message }}</p> @enderror
                                            <div class="flex gap-2">
                                                <button type="submit" class="rounded bg-indigo-600 px-2.5 py-1 font-medium text-white hover:bg-indigo-500">Save</button>
                                                <button type="button" wire:click="cancelImageEdit" class="rounded border border-gray-300 px-2.5 py-1 text-gray-600 hover:bg-gray-50">Cancel</button>
                                            </div>
                                        </form>
                                    @else
                                        <p class="truncate text-gray-700" title="{{ $image->caption }}">{{ $image->caption ?: 'No caption' }}</p>
                                        <div class="mt-auto flex flex-wrap gap-1.5">
                                            <button type="button" x-on:click="show({{ $n }})" class="rounded border border-indigo-200 bg-indigo-50 px-2 py-1 font-medium text-indigo-700 hover:bg-indigo-100">View</button>
                                            <button type="button" wire:click="editImage({{ $image->id }})" class="rounded border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-50">Caption</button>
                                            <button type="button" wire:click="toggleImage({{ $image->id }})" class="rounded border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-50">{{ $image->is_active ? 'Hide' : 'Show' }}</button>
                                            <button type="button" wire:click="moveImage({{ $image->id }}, 'up')" class="rounded border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-50" title="Move earlier" aria-label="Move earlier">&larr;</button>
                                            <button type="button" wire:click="moveImage({{ $image->id }}, 'down')" class="rounded border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-50" title="Move later" aria-label="Move later">&rarr;</button>
                                            <button type="button" wire:click="deleteImage({{ $image->id }})" wire:confirm="Delete this image?" class="rounded border border-red-200 px-2 py-1 text-red-600 hover:bg-red-50">Delete</button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="col-span-full py-6 text-center text-sm text-gray-400">No images in this group yet.</p>
                        @endforelse
                    </div>

                    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4" role="dialog" aria-modal="true" x-on:click.self="open = false">
                        <button type="button" x-on:click="open = false" class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-3xl leading-none text-white hover:bg-white/20" aria-label="Close">&times;</button>
                        <button type="button" x-show="items.length > 1" x-on:click="step(-1)" class="absolute left-3 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-2xl text-white hover:bg-white/20" aria-label="Previous">&#8249;</button>
                        <button type="button" x-show="items.length > 1" x-on:click="step(1)" class="absolute right-3 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-2xl text-white hover:bg-white/20" aria-label="Next">&#8250;</button>
                        <figure class="flex max-h-full max-w-5xl flex-col items-center">
                            <img :src="items[i]?.src" :alt="items[i]?.caption" class="max-h-[80vh] max-w-full object-contain shadow-2xl">
                            <figcaption class="mt-3 text-center text-sm text-white"><span x-text="items[i]?.caption"></span> <span class="ml-2 text-white/60" x-text="(i + 1) + ' / ' + items.length"></span></figcaption>
                        </figure>
                    </div>
                </div>
            @else
                <p class="px-4 py-12 text-center text-sm text-gray-400">Add a group first, then upload its images here.</p>
            @endif
        </div>
    </div>

    {{-- Group modal --}}
    @if ($showGroupModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 px-4" wire:key="group-modal">
            <form wire:submit="saveGroup" class="w-full max-w-md space-y-4 rounded-xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900">{{ $editingGroupId ? 'Rename group' : 'Add group' }}</h3>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Group name</label>
                    <input type="text" wire:model="group_title" autofocus placeholder="e.g. Awards" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('group_title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="group_active" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    Show this group on the website
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeGroupModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save</button>
                </div>
            </form>
        </div>
    @endif
</div>
