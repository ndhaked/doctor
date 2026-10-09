<?php

use App\Models\Review;
use App\Models\Setting;
use App\Support\ReviewsSection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component
{
    // Section settings
    public bool $visible = true;
    public string $eyebrow = '';
    public string $heading = '';
    public string $intro = '';
    public string $google_label = '';
    public string $rating = '';
    public string $count_text = '';
    public string $button_text = '';
    public string $button_url = '';

    // Review form
    public bool $showModal = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $text = '';
    public int $stars = 5;
    public bool $is_active = true;

    public ?string $status = null;

    public function mount(): void
    {
        $section = ReviewsSection::content();

        foreach (['visible', 'eyebrow', 'heading', 'intro', 'google_label', 'rating', 'count_text', 'button_text'] as $key) {
            $this->$key = $section[$key];
        }

        // keep the raw link (the page falls back to "#" when it is empty)
        $this->button_url = $section['button_url'] === '#' ? '' : $section['button_url'];
    }

    #[Computed]
    public function reviews()
    {
        return Review::ordered()->get();
    }

    /* ---------- section settings ---------- */

    public function saveSection(): void
    {
        $data = $this->validate([
            'visible' => ['boolean'],
            'eyebrow' => ['nullable', 'string', 'max:60'],
            'heading' => ['required', 'string', 'max:150'],
            'intro' => ['nullable', 'string', 'max:300'],
            'google_label' => ['nullable', 'string', 'max:60'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'count_text' => ['nullable', 'string', 'max:40'],
            'button_text' => ['nullable', 'string', 'max:50'],
            'button_url' => ['nullable', 'string', 'max:255'],
        ], [
            'heading.required' => 'The heading cannot be empty.',
            'rating.numeric' => 'The rating must be a number between 0 and 5, for example 4.8.',
            'rating.max' => 'The rating cannot be more than 5.',
        ]);

        Setting::put('reviews.visible', $data['visible'] ? '1' : '0');

        foreach (['eyebrow', 'intro', 'google_label', 'count_text', 'button_text', 'button_url'] as $key) {
            Setting::put("reviews.$key", $data[$key] ?? '');
        }

        Setting::put('reviews.heading', $data['heading']);
        Setting::put('reviews.rating', (string) ($data['rating'] ?? ''));

        $this->status = 'Reviews section saved.';
    }

    /* ---------- reviews ---------- */

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $review = Review::findOrFail($id);

        $this->resetForm();
        $this->editingId = $review->id;
        $this->name = $review->name;
        $this->text = $review->text;
        $this->stars = $review->rating;
        $this->is_active = $review->is_active;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'text', 'stars', 'is_active']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'text' => ['required', 'string', 'max:1500'],
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
            'is_active' => ['boolean'],
        ], [
            'name.required' => 'Please enter the patient name.',
            'text.required' => 'Please enter the review text.',
        ]);

        $review = $this->editingId ? Review::findOrFail($this->editingId) : new Review();

        $payload = ['name' => $data['name'], 'text' => $data['text'], 'rating' => $data['stars'], 'is_active' => $data['is_active']];

        if (! $review->exists) {
            $payload['sort_order'] = (int) Review::max('sort_order') + 1;
        }

        $review->fill($payload)->save();

        $this->status = $this->editingId ? 'Review updated.' : 'Review added.';
        $this->closeModal();
        unset($this->reviews);
    }

    public function toggle(int $id): void
    {
        $review = Review::findOrFail($id);
        $review->update(['is_active' => ! $review->is_active]);

        unset($this->reviews);
    }

    public function move(int $id, string $direction): void
    {
        $ids = Review::ordered()->pluck('id')->all();
        $pos = array_search($id, $ids, true);
        $swap = $direction === 'up' ? $pos - 1 : $pos + 1;

        if ($pos === false || ! isset($ids[$swap])) {
            return;
        }

        [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];

        foreach ($ids as $order => $reviewId) {
            Review::whereKey($reviewId)->update(['sort_order' => $order + 1]);
        }

        unset($this->reviews);
    }

    public function delete(int $id): void
    {
        Review::findOrFail($id)->delete();

        $this->status = 'Review deleted.';
        unset($this->reviews);
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Reviews</h2>
    </x-slot:header>

    <p class="mb-4 text-sm text-gray-500">
        The sliding &ldquo;What Our Patients Say&rdquo; section (shown on the home, About Doctor and Contact Us pages).
        <a href="{{ route('home') }}#testimonials" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-medium">View website &nearr;</a>
    </p>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    <form wire:submit="saveSection" class="mb-6 max-w-4xl space-y-5 rounded-xl border border-gray-200 bg-white p-5">
        <label class="flex items-start gap-3 rounded-lg border {{ $visible ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }} p-4">
            <input type="checkbox" wire:model.live="visible" class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
            <span>
                <span class="block text-sm font-medium text-gray-900">Show this section on the website</span>
                <span class="block text-xs text-gray-500">Currently {{ $visible ? 'shown' : 'hidden' }}. It is hidden on every page when switched off. Press &ldquo;Save section&rdquo; to apply.</span>
            </span>
        </label>

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
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Intro text</label>
            <textarea wire:model="intro" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
            @error('intro') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-gray-700">Google reviews label</label>
                <input wire:model="google_label" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <p class="mt-1 text-xs text-gray-400">Leave the label, rating and count empty to hide that rating line.</p>
                @error('google_label') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Overall rating (0&ndash;5)</label>
                <input wire:model="rating" type="text" placeholder="4.8" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('rating') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Review count text</label>
                <input wire:model="count_text" type="text" placeholder="900+ Reviews" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('count_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Button text</label>
                <input wire:model="button_text" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <p class="mt-1 text-xs text-gray-400">The Google button. Leave empty to hide it.</p>
                @error('button_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Button link</label>
                <input wire:model="button_url" type="text" placeholder="Your Google reviews link" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('button_url') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save section</button>
        </div>
    </form>

    <div class="mb-3 flex max-w-4xl items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900">Patient reviews</h3>
        <button wire:click="create" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">+ Add Review</button>
    </div>

    <div class="max-w-4xl overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium w-24">Order</th>
                    <th class="px-4 py-3 font-medium">Review</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->reviews as $review)
                    <tr wire:key="review-{{ $review->id }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1 text-gray-500">
                                <span class="w-5 text-center font-medium text-gray-900">{{ $loop->iteration }}</span>
                                <button wire:click="move({{ $review->id }}, 'up')" @disabled($loop->first) title="Move up" class="rounded p-1 hover:bg-gray-100 disabled:opacity-30"><x-icon name="chevron-down" class="h-4 w-4 rotate-180" /></button>
                                <button wire:click="move({{ $review->id }}, 'down')" @disabled($loop->last) title="Move down" class="rounded p-1 hover:bg-gray-100 disabled:opacity-30"><x-icon name="chevron-down" class="h-4 w-4" /></button>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium {{ $review->is_active ? 'text-gray-900' : 'text-gray-400' }}">{{ $review->name }}
                                <span class="ml-1 text-xs text-amber-500">{{ str_repeat('★', $review->rating) }}</span>
                            </p>
                            <p class="max-w-lg truncate text-xs text-gray-500">{{ $review->text }}</p>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <button wire:click="toggle({{ $review->id }})" role="switch" aria-checked="{{ $review->is_active ? 'true' : 'false' }}"
                                title="{{ $review->is_active ? 'Visible - click to hide' : 'Hidden - click to show' }}"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $review->is_active ? 'bg-indigo-600' : 'bg-gray-300' }}">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ $review->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                            <span class="ml-2 text-xs {{ $review->is_active ? 'text-green-700' : 'text-gray-400' }}">{{ $review->is_active ? 'Visible' : 'Hidden' }}</span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                            <button wire:click="edit({{ $review->id }})" class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
                            <button wire:click="delete({{ $review->id }})" wire:confirm="Delete this review?" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-gray-400">No reviews yet. The sliding cards are hidden on the website until you add one.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-black/40 px-4 py-8" wire:key="review-modal">
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit Review' : 'Add Review' }}</h3>

                <form wire:submit="save" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-[1fr_130px]">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Patient name *</label>
                            <input wire:model="name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Stars</label>
                            <select wire:model="stars" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @foreach ([5, 4, 3, 2, 1] as $n)
                                    <option value="{{ $n }}">{{ $n }} {{ $n === 1 ? 'star' : 'stars' }}</option>
                                @endforeach
                            </select>
                            @error('stars') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Review *</label>
                        <textarea wire:model="text" rows="6" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                        @error('text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
