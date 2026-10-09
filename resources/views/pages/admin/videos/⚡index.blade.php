<?php

use App\Models\GalleryVideo;
use App\Models\Setting;
use App\Support\VideoPageSection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component
{
    // Page texts
    public string $page_title = '';
    public string $page_intro = '';

    // Video form
    public bool $showModal = false;
    public ?int $editingId = null;
    public string $title = '';
    public string $description = '';
    public string $youtube_url = '';
    public bool $is_active = true;

    public ?string $status = null;

    public function mount(): void
    {
        $page = VideoPageSection::content();
        $this->page_title = $page['title'];
        $this->page_intro = $page['intro'];
    }

    #[Computed]
    public function videos()
    {
        return GalleryVideo::ordered()->get();
    }

    #[Computed]
    public function previewId(): ?string
    {
        return GalleryVideo::extractYoutubeId($this->youtube_url);
    }

    /* ---------- page texts ---------- */

    public function savePage(): void
    {
        $data = $this->validate([
            'page_title' => ['required', 'string', 'max:100'],
            'page_intro' => ['nullable', 'string', 'max:400'],
        ], ['page_title.required' => 'The page heading cannot be empty.']);

        Setting::put('videos.title', $data['page_title']);
        Setting::put('videos.intro', $data['page_intro'] ?? '');

        $this->status = 'Page texts saved.';
    }

    /* ---------- videos ---------- */

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $video = GalleryVideo::findOrFail($id);

        $this->resetForm();
        $this->editingId = $video->id;
        $this->title = $video->title;
        $this->description = (string) $video->description;
        $this->youtube_url = $video->youtube_url;
        $this->is_active = $video->is_active;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'title', 'description', 'youtube_url']);
        $this->is_active = true;
        $this->resetValidation();
        unset($this->previewId);
    }

    public function save(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'youtube_url' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (! GalleryVideo::extractYoutubeId($value)) {
                    $fail('Please paste a valid YouTube video link, for example https://www.youtube.com/watch?v=xxxxxxxxxxx or https://youtu.be/xxxxxxxxxxx.');
                }
            }],
            'is_active' => ['boolean'],
        ], [
            'title.required' => 'Please enter a video title.',
            'youtube_url.required' => 'Please paste the YouTube video link.',
        ]);

        $video = $this->editingId ? GalleryVideo::findOrFail($this->editingId) : new GalleryVideo();

        $payload = [
            'title' => $data['title'],
            'description' => $data['description'] ?: null,
            'youtube_url' => trim($data['youtube_url']),
            'youtube_id' => GalleryVideo::extractYoutubeId($data['youtube_url']),
            'is_active' => $data['is_active'],
        ];

        if (! $video->exists) {
            $payload['sort_order'] = (int) GalleryVideo::max('sort_order') + 1;
        }

        $video->fill($payload)->save();

        $this->status = $this->editingId ? 'Video updated.' : 'Video added.';
        $this->closeModal();
        unset($this->videos);
    }

    public function toggle(int $id): void
    {
        $video = GalleryVideo::findOrFail($id);
        $video->update(['is_active' => ! $video->is_active]);

        unset($this->videos);
    }

    public function move(int $id, string $direction): void
    {
        $ids = GalleryVideo::ordered()->pluck('id')->all();
        $pos = array_search($id, $ids, true);
        $other = $direction === 'up' ? $pos - 1 : $pos + 1;

        if ($pos === false || ! isset($ids[$other])) {
            return;
        }

        [$ids[$pos], $ids[$other]] = [$ids[$other], $ids[$pos]];

        foreach ($ids as $order => $videoId) {
            GalleryVideo::whereKey($videoId)->update(['sort_order' => $order + 1]);
        }

        unset($this->videos);
    }

    public function delete(int $id): void
    {
        GalleryVideo::findOrFail($id)->delete();

        $this->status = 'Video deleted.';
        unset($this->videos);
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Video Gallery</h2>
    </x-slot:header>

    <p class="mb-4 text-sm text-gray-500">
        Videos shown on the Video Gallery page. Only YouTube links are used; the thumbnail is taken from YouTube and the video plays only when a visitor clicks it.
        <a href="{{ route('video-gallery') }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-medium">View website &nearr;</a>
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

    <div class="mb-4 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900">Videos ({{ $this->videos->count() }})</h3>
        <button type="button" wire:click="create" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Add video</button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse ($this->videos as $video)
            <div wire:key="video-{{ $video->id }}" class="flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white">
                <div class="relative aspect-video bg-gray-900">
                    <img src="{{ $video->thumbnailUrl() }}" alt="{{ $video->title }}" loading="lazy" class="h-full w-full object-cover {{ $video->is_active ? '' : 'opacity-40 grayscale' }}">
                    <span class="absolute left-1/2 top-1/2 flex h-10 w-10 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-white bg-black/50">
                        <svg viewBox="0 0 24 24" class="ml-0.5 h-4 w-4 fill-white"><path d="M8 5v14l11-7z"/></svg>
                    </span>
                    @unless ($video->is_active)
                        <span class="absolute left-2 top-2 rounded bg-gray-900/80 px-1.5 py-0.5 text-[10px] font-medium text-white">Hidden</span>
                    @endunless
                </div>
                <div class="flex flex-1 flex-col gap-2 p-3 text-xs">
                    <p class="font-semibold text-gray-900 text-sm leading-snug">{{ $video->title }}</p>
                    @if ($video->description) <p class="line-clamp-2 text-gray-500">{{ $video->description }}</p> @endif
                    <div class="mt-auto flex flex-wrap gap-1.5 pt-1">
                        <a href="{{ $video->watchUrl() }}" target="_blank" rel="noopener" class="rounded border border-indigo-200 bg-indigo-50 px-2 py-1 font-medium text-indigo-700 hover:bg-indigo-100">Watch</a>
                        <button type="button" wire:click="edit({{ $video->id }})" class="rounded border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-50">Edit</button>
                        <button type="button" wire:click="toggle({{ $video->id }})" class="rounded border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-50">{{ $video->is_active ? 'Hide' : 'Show' }}</button>
                        <button type="button" wire:click="move({{ $video->id }}, 'up')" class="rounded border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-50" title="Move earlier" aria-label="Move earlier">&larr;</button>
                        <button type="button" wire:click="move({{ $video->id }}, 'down')" class="rounded border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-50" title="Move later" aria-label="Move later">&rarr;</button>
                        <button type="button" wire:click="delete({{ $video->id }})" wire:confirm="Delete this video?" class="rounded border border-red-200 px-2 py-1 text-red-600 hover:bg-red-50">Delete</button>
                    </div>
                </div>
            </div>
        @empty
            <p class="col-span-full rounded-xl border border-gray-200 bg-white py-12 text-center text-sm text-gray-400">No videos yet. Click &ldquo;Add video&rdquo; and paste a YouTube link.</p>
        @endforelse
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-black/40 px-4 py-8" wire:key="video-modal">
            <form wire:submit="save" class="w-full max-w-lg space-y-4 rounded-xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit video' : 'Add video' }}</h3>

                <div>
                    <label class="block text-sm font-medium text-gray-700">YouTube link</label>
                    <input type="url" wire:model.live.debounce.500ms="youtube_url" placeholder="https://www.youtube.com/watch?v=..." class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <p class="mt-1 text-xs text-gray-500">Paste a normal, youtu.be, Shorts or embed link.</p>
                    @error('youtube_url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                    @if ($this->previewId)
                        <img src="https://img.youtube.com/vi/{{ $this->previewId }}/hqdefault.jpg" alt="Thumbnail preview" class="mt-3 aspect-video w-48 rounded-lg border border-gray-200 object-cover">
                    @endif
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Title</label>
                    <input type="text" wire:model="title" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Short description (optional)</label>
                    <textarea wire:model="description" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                    @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    Show this video on the website
                </label>

                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save</button>
                </div>
            </form>
        </div>
    @endif
</div>
