<?php

use App\Models\BlogPost;
use App\Models\Setting;
use App\Support\BlogSection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $title = '';
    public string $slug = '';
    public string $published_at = '';
    public string $excerpt = '';
    public string $author = 'Admin';
    public string $content = '';
    public bool $is_active = true;

    public $imageFile = null;
    public ?string $existingImage = null;
    public bool $removeImage = false;

    public ?string $status = null;

    // Blog texts and display settings (home block, Health Tips page, post page sidebar)
    public bool $home_visible = true;
    public string $home_eyebrow = '';
    public string $home_heading = '';
    public string $home_button_text = '';
    public int $home_count = 8;
    public string $page_title = '';
    public string $page_intro = '';
    public string $recent_heading = '';
    public int $recent_count = 4;

    public function mount(): void
    {
        foreach (BlogSection::content() as $key => $value) {
            $this->$key = $value;
        }
    }

    public function saveSettings(): void
    {
        $data = $this->validate([
            'home_visible' => ['boolean'],
            'home_eyebrow' => ['nullable', 'string', 'max:60'],
            'home_heading' => ['required', 'string', 'max:250'],
            'home_button_text' => ['nullable', 'string', 'max:40'],
            'home_count' => ['required', 'integer', 'min:1', 'max:'.BlogSection::HOME_COUNT_MAX],
            'page_title' => ['required', 'string', 'max:100'],
            'page_intro' => ['nullable', 'string', 'max:400'],
            'recent_heading' => ['required', 'string', 'max:60'],
            'recent_count' => ['required', 'integer', 'min:1', 'max:'.BlogSection::RECENT_COUNT_MAX],
        ], [
            'home_heading.required' => 'The home block heading cannot be empty.',
            'page_title.required' => 'The page title cannot be empty.',
            'recent_heading.required' => 'The sidebar heading cannot be empty.',
        ]);

        Setting::put('blog.home_visible', $data['home_visible'] ? '1' : '0');
        Setting::put('blog.home_eyebrow', $data['home_eyebrow'] ?? '');
        Setting::put('blog.home_heading', $data['home_heading']);
        Setting::put('blog.home_button_text', $data['home_button_text'] ?? '');
        Setting::put('blog.home_count', (string) $data['home_count']);
        Setting::put('blog.page_title', $data['page_title']);
        Setting::put('blog.page_intro', $data['page_intro'] ?? '');
        Setting::put('blog.recent_heading', $data['recent_heading']);
        Setting::put('blog.recent_count', (string) $data['recent_count']);

        $this->status = 'Blog settings saved.';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function posts()
    {
        return BlogPost::query()
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('title', 'like', '%'.$this->search.'%')
                ->orWhere('excerpt', 'like', '%'.$this->search.'%')))
            ->latestFirst()
            ->paginate(10);
    }

    public function create(): void
    {
        $this->resetForm();
        $this->published_at = now()->toDateString();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $post = BlogPost::findOrFail($id);

        $this->resetForm();
        $this->editingId = $post->id;
        $this->title = $post->title;
        $this->slug = $post->slug;
        $this->published_at = $post->published_at->toDateString();
        $this->excerpt = (string) $post->excerpt;
        $this->author = (string) ($post->author ?: 'Admin');
        $this->content = (string) $post->content;
        $this->is_active = $post->is_active;
        $this->existingImage = $post->image;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'title', 'slug', 'published_at', 'excerpt', 'author', 'content', 'is_active', 'imageFile', 'existingImage', 'removeImage']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'published_at' => ['required', 'date'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'author' => ['nullable', 'string', 'max:100'],
            'content' => ['nullable', 'string', 'max:50000'],
            'imageFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_active' => ['boolean'],
        ], [
            'slug.regex' => 'Use only lowercase letters, numbers and hyphens (e.g. my-post-title).',
        ]);

        $post = $this->editingId ? BlogPost::findOrFail($this->editingId) : new BlogPost();

        // Keep the link stable when editing; otherwise build it from the title.
        $slug = $data['slug'] ?: ($post->exists ? $post->slug : BlogPost::uniqueSlug($data['title']));
        if (BlogPost::where('slug', $slug)->when($post->exists, fn ($q) => $q->whereKeyNot($post->id))->exists()) {
            $this->addError('slug', 'Another post already uses this link.');

            return;
        }

        $payload = [
            'title' => $data['title'],
            'slug' => $slug,
            'published_at' => $data['published_at'],
            'excerpt' => $data['excerpt'] ?: null,
            'author' => $data['author'] ?: 'Admin',
            'content' => $data['content'] ?: null,
            'is_active' => $data['is_active'],
        ];

        if ($this->imageFile) {
            $this->deleteUpload($post->image);
            $payload['image'] = 'uploads/'.$this->imageFile->store('blog', 'uploads');
        } elseif ($this->removeImage && $post->image) {
            $this->deleteUpload($post->image);
            $payload['image'] = null;
        }

        $post->fill($payload)->save();

        $this->status = $this->editingId ? 'Post updated.' : 'Post added.';
        $this->closeModal();
        unset($this->posts);
    }

    public function toggle(int $id): void
    {
        $post = BlogPost::findOrFail($id);
        $post->update(['is_active' => ! $post->is_active]);

        $this->status = $post->is_active ? 'Post is now published.' : 'Post hidden from the website.';
        unset($this->posts);
    }

    public function delete(int $id): void
    {
        $post = BlogPost::findOrFail($id);

        $this->deleteUpload($post->image);
        $post->delete();

        $this->status = 'Post deleted.';
        unset($this->posts);
    }

    /** Only files uploaded through the admin are removed; bundled images are never touched. */
    protected function deleteUpload(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/')) {
            Storage::disk('uploads')->delete(substr($path, strlen('uploads/')));
        }
    }

    protected function previewUrl(): ?string
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
        <h2 class="text-lg font-semibold text-gray-900">Blog Posts</h2>
    </x-slot:header>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search posts..."
            class="w-full sm:w-72 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
        <div class="flex items-center gap-3">
            <a href="{{ route('health-tips') }}" target="_blank" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">View blog &nearr;</a>
            <button wire:click="create" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">+ Add Post</button>
        </div>
    </div>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    {{-- Blog texts and display settings --}}
    <details class="group mb-6 rounded-xl border border-gray-200 bg-white" @if ($errors->hasAny(['home_heading', 'home_count', 'home_eyebrow', 'home_button_text', 'page_title', 'page_intro', 'recent_heading', 'recent_count'])) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-4 [&::-webkit-details-marker]:hidden">
            <span>
                <span class="block text-sm font-semibold text-gray-900">Blog page &amp; home block settings</span>
                <span class="block text-xs text-gray-500">Headings and texts for the home page blog block, the Health Tips page and the &ldquo;Recent News&rdquo; panel.</span>
            </span>
            <x-icon name="chevron-down" class="h-5 w-5 text-gray-400 transition group-open:rotate-180" />
        </summary>

        <form wire:submit="saveSettings" class="space-y-6 border-t border-gray-200 p-5">
            {{-- Home block --}}
            <div class="space-y-4">
                <h4 class="text-sm font-semibold text-gray-900">Home page blog block</h4>

                <label class="flex items-start gap-3 rounded-lg border {{ $home_visible ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }} p-3">
                    <input type="checkbox" wire:model.live="home_visible" class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span>
                        <span class="block text-sm font-medium text-gray-900">Show the blog block on the home page</span>
                        <span class="block text-xs text-gray-500">Currently {{ $home_visible ? 'shown' : 'hidden' }}. Press &ldquo;Save settings&rdquo; to apply.</span>
                    </span>
                </label>

                <div class="grid gap-4 sm:grid-cols-[1fr_140px]">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Small label</label>
                        <input wire:model="home_eyebrow" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('home_eyebrow') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Posts shown</label>
                        <input wire:model="home_count" type="number" min="1" max="{{ \App\Support\BlogSection::HOME_COUNT_MAX }}" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('home_count') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Heading *</label>
                    <textarea wire:model="home_heading" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                    @error('home_heading') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:max-w-xs">
                    <label class="block text-sm font-medium text-gray-700">Button text</label>
                    <input wire:model="home_button_text" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <p class="mt-1 text-xs text-gray-400">Opens the Health Tips page. Leave empty to hide the button.</p>
                    @error('home_button_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Health Tips page --}}
            <div class="space-y-4 border-t border-gray-100 pt-5">
                <h4 class="text-sm font-semibold text-gray-900">Health Tips page banner</h4>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Page title *</label>
                    <input wire:model="page_title" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('page_title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Intro text</label>
                    <textarea wire:model="page_intro" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                    @error('page_intro') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Post page sidebar --}}
            <div class="space-y-4 border-t border-gray-100 pt-5">
                <h4 class="text-sm font-semibold text-gray-900">Post page &ldquo;Recent News&rdquo; panel</h4>
                <div class="grid gap-4 sm:grid-cols-[1fr_140px]">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Panel heading *</label>
                        <input wire:model="recent_heading" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('recent_heading') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Posts listed</label>
                        <input wire:model="recent_count" type="number" min="1" max="{{ \App\Support\BlogSection::RECENT_COUNT_MAX }}" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('recent_count') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save settings</button>
            </div>
        </form>
    </details>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium">Post</th>
                    <th class="px-4 py-3 font-medium hidden md:table-cell">Date</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->posts as $post)
                    <tr wire:key="post-{{ $post->id }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if ($post->imageUrl())
                                    <img src="{{ $post->imageUrl() }}" alt="" class="h-12 w-20 shrink-0 rounded-md object-cover border border-gray-200">
                                @else
                                    <div class="h-12 w-20 shrink-0 rounded-md bg-gray-100 border border-gray-200"></div>
                                @endif
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 truncate max-w-md">{{ $post->title }}</p>
                                    <p class="text-xs text-gray-400 truncate">/health-tips/{{ $post->slug }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-600 hidden md:table-cell whitespace-nowrap">{{ $post->published_at->format('M j, Y') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <button wire:click="toggle({{ $post->id }})" role="switch" aria-checked="{{ $post->is_active ? 'true' : 'false' }}"
                                title="{{ $post->is_active ? 'Published - click to hide' : 'Hidden - click to publish' }}"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $post->is_active ? 'bg-indigo-600' : 'bg-gray-300' }}">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ $post->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                            <span class="ml-2 text-xs {{ $post->is_active ? 'text-green-700' : 'text-gray-400' }}">{{ $post->is_active ? 'Published' : 'Hidden' }}</span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                            @if ($post->is_active)
                                <a href="{{ $post->url() }}" target="_blank" class="text-gray-500 hover:text-gray-800 font-medium">View</a>
                            @endif
                            <button wire:click="edit({{ $post->id }})" class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
                            <button wire:click="delete({{ $post->id }})" wire:confirm="Delete this post? This cannot be undone." class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-gray-400">{{ $search !== '' ? 'No posts match your search.' : 'No posts yet.' }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $this->posts->links() }}</div>

    @if ($showModal)
        @php $preview = $this->previewUrl(); @endphp
        <div class="fixed inset-0 z-40 overflow-y-auto bg-black/40 px-4 py-8" wire:key="post-modal">
            <div class="mx-auto w-full max-w-3xl rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit Post' : 'Add Post' }}</h3>
                    <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-600" aria-label="Close">&times;</button>
                </div>

                <form wire:submit="save" class="space-y-5 p-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Title *</label>
                        <input wire:model="title" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Publish date *</label>
                            <input wire:model="published_at" type="date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @error('published_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Author name</label>
                            <input wire:model="author" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @error('author') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Link (slug)</label>
                            <input wire:model="slug" type="text" placeholder="Leave empty to create from the title" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @error('slug') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Short summary</label>
                        <textarea wire:model="excerpt" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                        <p class="mt-1 text-xs text-gray-400">Shown on the blog cards (max 500 characters).</p>
                        @error('excerpt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Full article</label>
                        <textarea wire:model="content" rows="14" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                        <p class="mt-1 text-xs leading-5 text-gray-400">Leave a blank line between paragraphs. Formatting: <code>## Heading</code>, <code>### Sub-heading</code>, lines starting with <code>- </code> make a bullet list, <code>**bold**</code>, and <code>[text](https://link)</code> for links.</p>
                        @error('content') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Banner image</label>
                        <div class="mt-1 flex flex-wrap items-center gap-4">
                            @if ($preview)
                                <img src="{{ $preview }}" alt="" class="h-20 w-36 rounded-md border border-gray-200 object-cover">
                            @endif
                            <div>
                                <input type="file" wire:model="imageFile" accept="image/jpeg,image/png,image/webp"
                                    class="block text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                                <p class="mt-1 text-xs text-gray-400">JPG, PNG or WEBP, up to 5 MB. Wide images (1200&times;628) look best.</p>
                                <div wire:loading wire:target="imageFile" class="mt-1 text-xs text-indigo-600">Uploading&hellip;</div>
                                @if ($existingImage && ! $imageFile)
                                    <label class="mt-1 flex items-center gap-2 text-xs text-gray-600">
                                        <input type="checkbox" wire:model.live="removeImage" class="rounded border-gray-300 text-indigo-600">
                                        Remove current image
                                    </label>
                                @endif
                            </div>
                        </div>
                        @error('imageFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        Published (visible on the website)
                    </label>

                    <div class="flex justify-end gap-2 border-t border-gray-200 pt-4">
                        <button type="button" wire:click="closeModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save,imageFile" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">
                            <span wire:loading.remove wire:target="save">Save post</span>
                            <span wire:loading wire:target="save">Saving&hellip;</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
