<?php

use App\Models\Setting;
use App\Support\ContactPageSection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    public string $title = '';
    public string $intro = '';

    public string $info_eyebrow = '';
    public string $info_heading = '';
    public string $info_text = '';
    public string $phone_title = '';
    public string $hours_title = '';
    public string $address_title = '';

    public bool $form_visible = true;
    public string $photo = '';
    public $photoFile = null;
    public string $name = '';
    public string $qualifications = '';
    public string $form_heading = '';
    public string $form_text = '';
    public string $form_button = '';

    public bool $map_visible = true;

    public ?string $status = null;

    private const TEXTS = [
        'title', 'intro',
        'info_eyebrow', 'info_heading', 'info_text', 'phone_title', 'hours_title', 'address_title',
        'name', 'qualifications', 'form_heading', 'form_text', 'form_button',
    ];

    public function mount(): void
    {
        $page = ContactPageSection::content();

        foreach ([...self::TEXTS, 'photo', 'form_visible', 'map_visible'] as $key) {
            $this->$key = $page[$key];
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:100'],
            'intro' => ['nullable', 'string', 'max:500'],
            'info_eyebrow' => ['nullable', 'string', 'max:60'],
            'info_heading' => ['required', 'string', 'max:120'],
            'info_text' => ['nullable', 'string', 'max:400'],
            'phone_title' => ['nullable', 'string', 'max:40'],
            'hours_title' => ['nullable', 'string', 'max:40'],
            'address_title' => ['nullable', 'string', 'max:40'],
            'form_visible' => ['boolean'],
            'photoFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'name' => ['nullable', 'string', 'max:120'],
            'qualifications' => ['nullable', 'string', 'max:300'],
            'form_heading' => ['required', 'string', 'max:120'],
            'form_text' => ['nullable', 'string', 'max:400'],
            'form_button' => ['required', 'string', 'max:40'],
            'map_visible' => ['boolean'],
        ], [
            'title.required' => 'The page title cannot be empty.',
            'info_heading.required' => 'The "Get In Touch" heading cannot be empty.',
            'form_heading.required' => 'The form heading cannot be empty.',
            'form_button.required' => 'The form button text cannot be empty.',
        ]);

        if ($this->photoFile) {
            $this->deleteUpload($this->photo);
            $this->photo = 'uploads/'.$this->photoFile->store('contact', 'uploads');
            $this->photoFile = null;
            Setting::put('contactpage.photo', $this->photo);
        }

        foreach (['form_visible', 'map_visible'] as $flag) {
            Setting::put("contactpage.$flag", $data[$flag] ? '1' : '0');
        }

        foreach (self::TEXTS as $key) {
            Setting::put("contactpage.$key", trim($data[$key] ?? ''));
        }

        $this->status = 'Contact page saved.';
    }

    public function removePhoto(): void
    {
        $this->deleteUpload($this->photo);
        $this->photo = '';
        $this->photoFile = null;
        Setting::put('contactpage.photo', '');

        $this->status = 'Photo removed.';
    }

    public function restorePhoto(): void
    {
        $this->photo = ContactPageSection::defaults()['contactpage.photo'];
        $this->photoFile = null;
        Setting::put('contactpage.photo', $this->photo);

        $this->status = 'Original photo restored.';
    }

    /** Only files uploaded through the admin are removed; bundled images are never touched. */
    protected function deleteUpload(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/')) {
            Storage::disk('uploads')->delete(substr($path, strlen('uploads/')));
        }
    }

    protected function photoPreview(): ?string
    {
        if ($this->photoFile) {
            try {
                return $this->photoFile->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        return $this->photo !== '' ? asset($this->photo) : null;
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Contact Page</h2>
    </x-slot:header>

    <p class="mb-4 text-sm text-gray-500">
        Texts of the Contact Us page. The phone, email, address, opening hours and map come from
        <a href="{{ route('admin.site.index') }}" class="text-indigo-600 hover:text-indigo-800 font-medium">Site Settings</a>, and the reviews from
        <a href="{{ route('admin.reviews.index') }}" class="text-indigo-600 hover:text-indigo-800 font-medium">Reviews</a>.
        <a href="{{ route('contact') }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-medium">View page &nearr;</a>
    </p>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    @php $preview = $this->photoPreview(); @endphp

    <form wire:submit="save" class="max-w-4xl space-y-6">
        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Banner</h3>
            <div>
                <label class="block text-sm font-medium text-gray-700">Page title *</label>
                <input wire:model="title" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Intro text</label>
                <textarea wire:model="intro" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                @error('intro') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </section>

        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">&ldquo;Get In Touch&rdquo; cards</h3>
            <div class="grid gap-4 sm:grid-cols-[200px_1fr]">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Small label</label>
                    <input wire:model="info_eyebrow" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('info_eyebrow') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Heading *</label>
                    <input wire:model="info_heading" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('info_heading') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Text</label>
                <textarea wire:model="info_text" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                @error('info_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Phone card title</label>
                    <input wire:model="phone_title" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('phone_title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Hours card title</label>
                    <input wire:model="hours_title" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('hours_title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Address card title</label>
                    <input wire:model="address_title" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('address_title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Query form with the doctor&rsquo;s photo</h3>
            <label class="flex items-start gap-3 rounded-lg border {{ $form_visible ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }} p-3">
                <input type="checkbox" wire:model.live="form_visible" class="mt-0.5 rounded border-gray-300 text-indigo-600">
                <span class="text-sm font-medium text-gray-900">Show this block</span>
            </label>

            <div class="flex items-start gap-3">
                @if ($preview)
                    <img src="{{ $preview }}" alt="" class="h-20 w-16 rounded-md border border-gray-200 object-cover">
                @else
                    <div class="flex h-20 w-16 items-center justify-center rounded-md bg-gray-100 text-xs text-gray-400">None</div>
                @endif
                <div>
                    <label class="block text-sm font-medium text-gray-700">Doctor photo</label>
                    <input type="file" wire:model="photoFile" accept="image/jpeg,image/png,image/webp"
                        class="block text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                    <div wire:loading wire:target="photoFile" class="text-xs text-indigo-600">Uploading&hellip;</div>
                    <div class="mt-1 flex gap-3 text-xs">
                        @if ($photo !== '')<button type="button" wire:click="removePhoto" wire:confirm="Remove the photo?" class="font-medium text-red-600 hover:text-red-800">Remove</button>@endif
                        <button type="button" wire:click="restorePhoto" class="font-medium text-gray-500 hover:text-gray-800">Use original</button>
                    </div>
                    @error('photoFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Name on the photo</label>
                    <input wire:model="name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Qualifications on the photo</label>
                    <textarea wire:model="qualifications" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                    @error('qualifications') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-[1fr_200px]">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Form heading *</label>
                    <input wire:model="form_heading" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('form_heading') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Button text *</label>
                    <input wire:model="form_button" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('form_button') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Form intro text</label>
                <textarea wire:model="form_text" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                <p class="mt-1 text-xs text-gray-400">The consent text next to the checkbox is the one set in <a href="{{ route('admin.appointment.index') }}" class="text-indigo-600 underline">Appointment</a>.</p>
                @error('form_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="mb-3 text-sm font-semibold text-gray-900">Map</h3>
            <label class="flex items-start gap-3 rounded-lg border {{ $map_visible ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }} p-3">
                <input type="checkbox" wire:model.live="map_visible" class="mt-0.5 rounded border-gray-300 text-indigo-600">
                <span class="text-sm text-gray-900"><span class="font-medium">Show the map</span> <span class="text-xs text-gray-500">(its link is set in Site Settings)</span></span>
            </label>
        </section>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="save,photoFile" class="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">Save contact page</button>
        </div>
    </form>
</div>
