<?php

use App\Models\AboutExpertise;
use App\Models\AboutTreatment;
use App\Models\Setting;
use App\Support\AboutPageSection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    private const PICTURES = ['banner_photo', 'overview_photo'];

    // Banner
    public string $banner_label = '';
    public string $banner_name = '';
    public string $banner_qualifications = '';
    public string $banner_text = '';
    public string $banner_button_text = '';
    public string $banner_button_url = '';
    public bool $banner_show_phone = true;
    public string $banner_photo = '';
    public $banner_photoFile = null;

    // Overview tab
    public string $overview_title = '';
    public string $overview_subtitle = '';
    public string $overview_text = '';
    public string $expertise_heading = '';
    public string $overview_photo = '';
    public $overview_photoFile = null;

    // Tab lists (one item per line)
    public string $education = '';
    public string $experience = '';
    public string $responsibilities = '';
    public string $research = '';
    public string $awards = '';

    // Call to action band
    public bool $cta_visible = true;
    public string $cta_heading = '';
    public string $cta_text = '';
    public string $cta_button_text = '';
    public bool $cta_show_phone = true;

    // Treatments block
    public bool $treat_visible = true;
    public string $treat_eyebrow = '';
    public string $treat_heading = '';
    public string $treat_intro = '';

    // Card form (expertise / treatment)
    public bool $showModal = false;
    public string $kind = 'expertise';
    public ?int $editingId = null;
    public string $title = '';
    public bool $is_active = true;
    public $imageFile = null;
    public ?string $existingImage = null;

    public ?string $status = null;

    public function mount(): void
    {
        $about = AboutPageSection::content();

        foreach (['banner_label', 'banner_name', 'banner_qualifications', 'banner_button_text', 'banner_show_phone', 'banner_photo',
            'overview_title', 'overview_subtitle', 'overview_text', 'expertise_heading', 'overview_photo',
            'cta_visible', 'cta_heading', 'cta_text', 'cta_button_text', 'cta_show_phone',
            'treat_visible', 'treat_eyebrow', 'treat_heading', 'treat_intro'] as $key) {
            $this->$key = $about[$key];
        }

        // edit the banner text with its {phone} placeholder, and the link as saved (empty = Contact Us page)
        $this->banner_text = $about['banner_text_raw'];
        $this->banner_button_url = Setting::get('about.banner_button_url', '');

        foreach (AboutPageSection::LIST_TABS as $key => $heading) {
            $this->$key = $about['list_text'][$key];
        }
    }

    #[Computed]
    public function expertise()
    {
        return AboutExpertise::ordered()->get();
    }

    #[Computed]
    public function treatments()
    {
        return AboutTreatment::ordered()->get();
    }

    /* ---------- page texts ---------- */

    public function savePage(): void
    {
        $data = $this->validate([
            'banner_photoFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'overview_photoFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'banner_label' => ['nullable', 'string', 'max:60'],
            'banner_name' => ['required', 'string', 'max:120'],
            'banner_qualifications' => ['nullable', 'string', 'max:300'],
            'banner_text' => ['nullable', 'string', 'max:800'],
            'banner_button_text' => ['nullable', 'string', 'max:40'],
            'banner_button_url' => ['nullable', 'string', 'max:255'],
            'banner_show_phone' => ['boolean'],
            'overview_title' => ['required', 'string', 'max:150'],
            'overview_subtitle' => ['nullable', 'string', 'max:150'],
            'overview_text' => ['nullable', 'string', 'max:6000'],
            'expertise_heading' => ['nullable', 'string', 'max:60'],
            'education' => ['nullable', 'string', 'max:4000'],
            'experience' => ['nullable', 'string', 'max:4000'],
            'responsibilities' => ['nullable', 'string', 'max:4000'],
            'research' => ['nullable', 'string', 'max:8000'],
            'awards' => ['nullable', 'string', 'max:4000'],
            'cta_visible' => ['boolean'],
            'cta_heading' => ['required', 'string', 'max:200'],
            'cta_text' => ['nullable', 'string', 'max:400'],
            'cta_button_text' => ['nullable', 'string', 'max:50'],
            'cta_show_phone' => ['boolean'],
            'treat_visible' => ['boolean'],
            'treat_eyebrow' => ['nullable', 'string', 'max:60'],
            'treat_heading' => ['required', 'string', 'max:120'],
            'treat_intro' => ['nullable', 'string', 'max:250'],
        ], [
            'banner_name.required' => 'The doctor name cannot be empty.',
            'overview_title.required' => 'The overview title cannot be empty.',
            'cta_heading.required' => 'The dark band heading cannot be empty.',
            'treat_heading.required' => 'The treatments heading cannot be empty.',
        ]);

        foreach (self::PICTURES as $field) {
            $file = $this->{$field.'File'};

            if ($file) {
                $this->deleteUpload($this->$field);
                $this->$field = 'uploads/'.$file->store('about', 'uploads');
                $this->{$field.'File'} = null;
                Setting::put("about.$field", $this->$field);
            }
        }

        foreach (['banner_show_phone', 'cta_visible', 'cta_show_phone', 'treat_visible'] as $flag) {
            Setting::put("about.$flag", $data[$flag] ? '1' : '0');
        }

        $texts = ['banner_label', 'banner_name', 'banner_qualifications', 'banner_text', 'banner_button_text', 'banner_button_url',
            'overview_title', 'overview_subtitle', 'overview_text', 'expertise_heading',
            'education', 'experience', 'responsibilities', 'research', 'awards',
            'cta_heading', 'cta_text', 'cta_button_text', 'treat_eyebrow', 'treat_heading', 'treat_intro'];

        foreach ($texts as $key) {
            Setting::put("about.$key", trim($data[$key] ?? ''));
        }

        $this->status = 'About page saved.';
    }

    public function removePicture(string $field): void
    {
        abort_unless(in_array($field, self::PICTURES, true), 404);

        $this->deleteUpload($this->$field);
        $this->$field = '';
        $this->{$field.'File'} = null;
        Setting::put("about.$field", '');

        $this->status = 'Picture removed.';
    }

    public function restorePicture(string $field): void
    {
        abort_unless(in_array($field, self::PICTURES, true), 404);

        $this->$field = AboutPageSection::defaults()["about.$field"];
        $this->{$field.'File'} = null;
        Setting::put("about.$field", $this->$field);

        $this->status = 'Original picture restored.';
    }

    /* ---------- cards (expertise + treatments) ---------- */

    protected function modelFor(string $kind): string
    {
        abort_unless(in_array($kind, ['expertise', 'treatment'], true), 404);

        return $kind === 'expertise' ? AboutExpertise::class : AboutTreatment::class;
    }

    public function create(string $kind): void
    {
        $this->modelFor($kind);

        $this->resetForm();
        $this->kind = $kind;
        $this->showModal = true;
    }

    public function edit(string $kind, int $id): void
    {
        $card = $this->modelFor($kind)::findOrFail($id);

        $this->resetForm();
        $this->kind = $kind;
        $this->editingId = $card->id;
        $this->title = $card->title;
        $this->is_active = $card->is_active;
        $this->existingImage = $card->image;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'title', 'is_active', 'imageFile', 'existingImage', 'kind']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $model = $this->modelFor($this->kind);

        $data = $this->validate([
            'title' => ['required', 'string', 'max:80'],
            'is_active' => ['boolean'],
            'imageFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:3072'],
        ]);

        $card = $this->editingId ? $model::findOrFail($this->editingId) : new $model();

        $payload = ['title' => $data['title'], 'is_active' => $data['is_active']];

        if ($this->imageFile) {
            $this->deleteUpload($card->image);
            $payload['image'] = 'uploads/'.$this->imageFile->store('about', 'uploads');
        }

        if (! $card->exists) {
            $payload['sort_order'] = (int) $model::max('sort_order') + 1;
        }

        $card->fill($payload)->save();

        $this->status = $this->editingId ? 'Card updated.' : 'Card added.';
        $this->closeModal();
        unset($this->expertise, $this->treatments);
    }

    public function toggle(string $kind, int $id): void
    {
        $card = $this->modelFor($kind)::findOrFail($id);
        $card->update(['is_active' => ! $card->is_active]);

        unset($this->expertise, $this->treatments);
    }

    public function move(string $kind, int $id, string $direction): void
    {
        $model = $this->modelFor($kind);

        $ids = $model::ordered()->pluck('id')->all();
        $pos = array_search($id, $ids, true);
        $swap = $direction === 'up' ? $pos - 1 : $pos + 1;

        if ($pos === false || ! isset($ids[$swap])) {
            return;
        }

        [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];

        foreach ($ids as $order => $cardId) {
            $model::whereKey($cardId)->update(['sort_order' => $order + 1]);
        }

        unset($this->expertise, $this->treatments);
    }

    public function delete(string $kind, int $id): void
    {
        $card = $this->modelFor($kind)::findOrFail($id);

        $this->deleteUpload($card->image);
        $card->delete();

        $this->status = 'Card deleted.';
        unset($this->expertise, $this->treatments);
    }

    /** Only files uploaded through the admin are removed; bundled images are never touched. */
    protected function deleteUpload(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/')) {
            Storage::disk('uploads')->delete(substr($path, strlen('uploads/')));
        }
    }

    protected function picturePreview(string $field): ?string
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

    protected function cardPreview(): ?string
    {
        if ($this->imageFile) {
            try {
                return $this->imageFile->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        return $this->existingImage ? asset($this->existingImage) : null;
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">About Page</h2>
    </x-slot:header>

    <p class="mb-4 text-sm text-gray-500">
        Everything on the About Doctor page. The phone number comes from
        <a href="{{ route('admin.site.index') }}" class="text-indigo-600 hover:text-indigo-800 font-medium">Site Settings</a>, and the appointment section and reviews are managed in their own pages.
        <a href="{{ route('about') }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-medium">View page &nearr;</a>
    </p>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    @php
        $bannerPreview = $this->picturePreview('banner_photo');
        $overviewPreview = $this->picturePreview('overview_photo');
    @endphp

    <form wire:submit="savePage" class="mb-8 max-w-4xl space-y-6">
        {{-- Banner --}}
        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Banner</h3>
            <div class="grid gap-4 sm:grid-cols-[200px_1fr]">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Small label</label>
                    <input wire:model="banner_label" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('banner_label') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Doctor name *</label>
                    <input wire:model="banner_name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('banner_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Qualifications</label>
                <textarea wire:model="banner_qualifications" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                @error('banner_qualifications') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Text</label>
                <textarea wire:model="banner_text" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                <p class="mt-1 text-xs text-gray-400">Type <code>{phone}</code> or <code>{email}</code> to insert the details from Site Settings.</p>
                @error('banner_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Button text</label>
                    <input wire:model="banner_button_text" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <p class="mt-1 text-xs text-gray-400">Empty hides the button.</p>
                    @error('banner_button_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Button link</label>
                    <input wire:model="banner_button_url" type="text" placeholder="Empty = Contact Us page" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('banner_button_url') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="banner_show_phone" class="rounded border-gray-300 text-indigo-600"> Show the phone button
                    </label>
                </div>
            </div>
            <div class="flex items-start gap-3">
                @if ($bannerPreview)
                    <img src="{{ $bannerPreview }}" alt="" class="h-16 w-16 rounded-full border border-gray-200 object-cover">
                @else
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 text-xs text-gray-400">None</div>
                @endif
                <div>
                    <label class="block text-sm font-medium text-gray-700">Round photo</label>
                    <input type="file" wire:model="banner_photoFile" accept="image/jpeg,image/png,image/webp"
                        class="block text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                    <div wire:loading wire:target="banner_photoFile" class="text-xs text-indigo-600">Uploading&hellip;</div>
                    <div class="mt-1 flex gap-3 text-xs">
                        @if ($banner_photo !== '')<button type="button" wire:click="removePicture('banner_photo')" wire:confirm="Remove the photo?" class="font-medium text-red-600 hover:text-red-800">Remove</button>@endif
                        <button type="button" wire:click="restorePicture('banner_photo')" class="font-medium text-gray-500 hover:text-gray-800">Use original</button>
                    </div>
                    @error('banner_photoFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        {{-- Overview --}}
        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Overview tab</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Title *</label>
                    <input wire:model="overview_title" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('overview_title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Sub-title (bold)</label>
                    <input wire:model="overview_subtitle" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('overview_subtitle') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Text</label>
                <textarea wire:model="overview_text" rows="9" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                <p class="mt-1 text-xs text-gray-400">Leave a blank line between paragraphs. <code>**bold**</code> and <code>[link text](/page)</code> are supported.</p>
                @error('overview_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Expertise cards heading</label>
                    <input wire:model="expertise_heading" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('expertise_heading') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex items-start gap-3">
                @if ($overviewPreview)
                    <img src="{{ $overviewPreview }}" alt="" class="h-20 w-16 rounded-md border border-gray-200 object-cover">
                @else
                    <div class="flex h-20 w-16 items-center justify-center rounded-md bg-gray-100 text-xs text-gray-400">None</div>
                @endif
                <div>
                    <label class="block text-sm font-medium text-gray-700">Portrait photo (right side)</label>
                    <input type="file" wire:model="overview_photoFile" accept="image/jpeg,image/png,image/webp"
                        class="block text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                    <div wire:loading wire:target="overview_photoFile" class="text-xs text-indigo-600">Uploading&hellip;</div>
                    <div class="mt-1 flex gap-3 text-xs">
                        @if ($overview_photo !== '')<button type="button" wire:click="removePicture('overview_photo')" wire:confirm="Remove the photo?" class="font-medium text-red-600 hover:text-red-800">Remove</button>@endif
                        <button type="button" wire:click="restorePicture('overview_photo')" class="font-medium text-gray-500 hover:text-gray-800">Use original</button>
                    </div>
                    @error('overview_photoFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        {{-- Tab lists --}}
        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Other tabs</h3>
            <p class="-mt-2 text-xs text-gray-400">One item per line. A tab with no items is left out of the page. The Expertise tab lists the expertise cards below.</p>
            @foreach (\App\Support\AboutPageSection::LIST_TABS as $key => $heading)
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ $heading }}</label>
                    <textarea wire:model="{{ $key }}" rows="4" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                    @error($key) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </section>

        {{-- Dark band --}}
        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Dark call-to-action band</h3>
            <label class="flex items-start gap-3 rounded-lg border {{ $cta_visible ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }} p-3">
                <input type="checkbox" wire:model.live="cta_visible" class="mt-0.5 rounded border-gray-300 text-indigo-600">
                <span class="text-sm font-medium text-gray-900">Show this band</span>
            </label>
            <div>
                <label class="block text-sm font-medium text-gray-700">Heading *</label>
                <input wire:model="cta_heading" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('cta_heading') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Text</label>
                <textarea wire:model="cta_text" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                @error('cta_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Button text (opens the appointment popup)</label>
                    <input wire:model="cta_button_text" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('cta_button_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="cta_show_phone" class="rounded border-gray-300 text-indigo-600"> Show the phone number
                    </label>
                </div>
            </div>
        </section>

        {{-- Treatments heading --}}
        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">&ldquo;Our Main Treatments&rdquo; block</h3>
            <label class="flex items-start gap-3 rounded-lg border {{ $treat_visible ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }} p-3">
                <input type="checkbox" wire:model.live="treat_visible" class="mt-0.5 rounded border-gray-300 text-indigo-600">
                <span class="text-sm font-medium text-gray-900">Show this block</span>
            </label>
            <div class="grid gap-4 sm:grid-cols-[200px_1fr]">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Small label</label>
                    <input wire:model="treat_eyebrow" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('treat_eyebrow') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Heading *</label>
                    <input wire:model="treat_heading" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('treat_heading') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Intro line</label>
                <input wire:model="treat_intro" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('treat_intro') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </section>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="savePage,banner_photoFile,overview_photoFile" class="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">Save page</button>
        </div>
    </form>

    {{-- Card lists --}}
    @foreach (['expertise' => ['Expertise cards', 'the small icon cards that slide in the Overview tab', $this->expertise], 'treatment' => ['Treatment photos', 'the round photos in "Our Main Treatments"', $this->treatments]] as $listKind => [$listTitle, $listHint, $items])
        <section class="mb-8 max-w-4xl" wire:key="list-{{ $listKind }}">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900">{{ $listTitle }} <span class="font-normal text-gray-400">({{ $listHint }})</span></h3>
                <button wire:click="create('{{ $listKind }}')" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">+ Add</button>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium w-24">Order</th>
                            <th class="px-4 py-3 font-medium">Card</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($items as $card)
                            <tr wire:key="{{ $listKind }}-{{ $card->id }}">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1 text-gray-500">
                                        <span class="w-5 text-center font-medium text-gray-900">{{ $loop->iteration }}</span>
                                        <button wire:click="move('{{ $listKind }}', {{ $card->id }}, 'up')" @disabled($loop->first) title="Move up" class="rounded p-1 hover:bg-gray-100 disabled:opacity-30"><x-icon name="chevron-down" class="h-4 w-4 rotate-180" /></button>
                                        <button wire:click="move('{{ $listKind }}', {{ $card->id }}, 'down')" @disabled($loop->last) title="Move down" class="rounded p-1 hover:bg-gray-100 disabled:opacity-30"><x-icon name="chevron-down" class="h-4 w-4" /></button>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden {{ $listKind === 'treatment' ? 'rounded-full' : 'rounded-md border border-gray-200 bg-white' }}">
                                            @if ($card->imageUrl())
                                                <img src="{{ $card->imageUrl() }}" alt="" class="{{ $listKind === 'treatment' ? 'h-full w-full object-cover' : 'h-7 w-7 object-contain' }}">
                                            @endif
                                        </span>
                                        <span class="font-medium {{ $card->is_active ? 'text-gray-900' : 'text-gray-400' }}">{{ $card->title }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <button wire:click="toggle('{{ $listKind }}', {{ $card->id }})" role="switch" aria-checked="{{ $card->is_active ? 'true' : 'false' }}"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $card->is_active ? 'bg-indigo-600' : 'bg-gray-300' }}">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ $card->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </button>
                                    <span class="ml-2 text-xs {{ $card->is_active ? 'text-green-700' : 'text-gray-400' }}">{{ $card->is_active ? 'Visible' : 'Hidden' }}</span>
                                </td>
                                <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                    <button wire:click="edit('{{ $listKind }}', {{ $card->id }})" class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
                                    <button wire:click="delete('{{ $listKind }}', {{ $card->id }})" wire:confirm="Delete this card?" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Nothing here yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    @if ($showModal)
        @php $preview = $this->cardPreview(); @endphp
        <div class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-black/40 px-4 py-8" wire:key="card-modal">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit' : 'Add' }} {{ $kind === 'expertise' ? 'expertise card' : 'treatment photo' }}</h3>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Title *</label>
                        <input wire:model.live.debounce.250ms="title" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ $kind === 'expertise' ? 'Icon image' : 'Photo' }}</label>
                        <input type="file" wire:model="imageFile" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                            class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                        <p class="mt-1 text-xs text-gray-400">{{ $kind === 'expertise' ? 'Small square image (PNG, WEBP or SVG), up to 3 MB.' : 'Square photo, up to 3 MB. It is shown as a circle.' }}</p>
                        <div wire:loading wire:target="imageFile" class="text-xs text-indigo-600">Uploading&hellip;</div>
                        @error('imageFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4 text-center">
                        <p class="mb-2 text-xs text-gray-500">Preview</p>
                        @if ($kind === 'treatment')
                            <span class="mx-auto block h-20 w-20 overflow-hidden rounded-full bg-gray-200">@if ($preview)<img src="{{ $preview }}" alt="" class="h-full w-full object-cover">@endif</span>
                        @else
                            <span class="mx-auto block h-12 w-12">@if ($preview)<img src="{{ $preview }}" alt="" class="h-full w-full object-contain">@endif</span>
                        @endif
                        <p class="mt-2 text-xs font-semibold text-gray-900">{{ $title ?: 'Title' }}</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-indigo-600"> Visible on the page
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
