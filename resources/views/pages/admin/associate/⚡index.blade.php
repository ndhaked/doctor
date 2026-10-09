<?php

use App\Models\Setting;
use App\Support\AssociateSection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    private const PICTURES = ['photo', 'badge_image', 'hospital_image'];

    public bool $visible = true;

    public string $photo = '';
    public string $badge_image = '';
    public string $hospital_image = '';
    public $photoFile = null;
    public $badge_imageFile = null;
    public $hospital_imageFile = null;

    public string $name = '';
    public string $qualifications = '';
    public string $tagline = '';
    public string $button_text = '';
    public string $button_url = '';

    public string $badge_text = '';

    public string $hospital_link = '';
    public string $hospital_name = '';
    public string $hospital_role = '';
    public string $hospital_text = '';

    public ?string $status = null;

    public function mount(): void
    {
        $assoc = AssociateSection::content();

        $this->visible = $assoc['visible'];

        foreach ([...self::PICTURES, 'name', 'qualifications', 'tagline', 'button_text', 'badge_text', 'hospital_name', 'hospital_role', 'hospital_text'] as $key) {
            $this->$key = $assoc[$key];
        }

        // the two links: show only what was saved (empty = the default page)
        $stored = Setting::many(['assoc.button_url' => '', 'assoc.hospital_link' => '']);
        $this->button_url = $stored['assoc.button_url'];
        $this->hospital_link = $stored['assoc.hospital_link'];
    }

    public function save(): void
    {
        $data = $this->validate([
            'visible' => ['boolean'],
            'photoFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'badge_imageFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:1024'],
            'hospital_imageFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'name' => ['required', 'string', 'max:120'],
            'qualifications' => ['nullable', 'string', 'max:300'],
            'tagline' => ['nullable', 'string', 'max:100'],
            'button_text' => ['nullable', 'string', 'max:40'],
            'button_url' => ['nullable', 'string', 'max:255'],
            'badge_text' => ['nullable', 'string', 'max:60'],
            'hospital_link' => ['nullable', 'string', 'max:255'],
            'hospital_name' => ['nullable', 'string', 'max:120'],
            'hospital_role' => ['nullable', 'string', 'max:120'],
            'hospital_text' => ['nullable', 'string', 'max:600'],
        ], [
            'name.required' => 'The doctor name cannot be empty.',
        ]);

        foreach (self::PICTURES as $field) {
            $file = $this->{$field.'File'};

            if ($file) {
                $this->deleteUpload($this->$field);
                $this->$field = 'uploads/'.$file->store('associate', 'uploads');
                $this->{$field.'File'} = null;
                Setting::put("assoc.$field", $this->$field);
            }
        }

        Setting::put('assoc.visible', $data['visible'] ? '1' : '0');

        foreach (['name', 'qualifications', 'tagline', 'button_text', 'button_url', 'badge_text', 'hospital_link', 'hospital_name', 'hospital_role', 'hospital_text'] as $key) {
            Setting::put("assoc.$key", trim($data[$key] ?? ''));
        }

        $this->status = 'Associate band saved.';
    }

    public function removePicture(string $field): void
    {
        abort_unless(in_array($field, self::PICTURES, true), 404);

        $this->deleteUpload($this->$field);
        $this->$field = '';
        $this->{$field.'File'} = null;
        Setting::put("assoc.$field", '');

        $this->status = 'Picture removed.';
    }

    public function restorePicture(string $field): void
    {
        abort_unless(in_array($field, self::PICTURES, true), 404);

        $this->$field = AssociateSection::defaults()["assoc.$field"];
        $this->{$field.'File'} = null;
        Setting::put("assoc.$field", $this->$field);

        $this->status = 'Original picture restored.';
    }

    /** Only files uploaded through the admin are removed; bundled images are never touched. */
    protected function deleteUpload(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/')) {
            Storage::disk('uploads')->delete(substr($path, strlen('uploads/')));
        }
    }

    protected function preview(string $field): ?string
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
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Associate Band</h2>
    </x-slot:header>

    <p class="mb-4 text-sm text-gray-500">
        The dark green band on the home page: the doctor on the left, the &ldquo;Associate With&rdquo; badge in the middle and the hospital on the right.
        <a href="{{ route('home') }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-medium">View website &nearr;</a>
    </p>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    <form wire:submit="save" class="max-w-4xl space-y-6">
        <label class="flex items-start gap-3 rounded-lg border {{ $visible ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }} p-4">
            <input type="checkbox" wire:model.live="visible" class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
            <span>
                <span class="block text-sm font-medium text-gray-900">Show this band on the home page</span>
                <span class="block text-xs text-gray-500">Currently {{ $visible ? 'shown' : 'hidden' }}. Press &ldquo;Save band&rdquo; to apply.</span>
            </span>
        </label>

        {{-- Doctor --}}
        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Left &ndash; Doctor</h3>

            @php $p = $this->preview('photo'); @endphp
            <div class="flex items-start gap-3">
                @if ($p)
                    <img src="{{ $p }}" alt="" class="h-16 w-16 rounded-full border border-gray-200 object-cover">
                @else
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 text-xs text-gray-400">None</div>
                @endif
                <div>
                    <input type="file" wire:model="photoFile" accept="image/jpeg,image/png,image/webp"
                        class="block text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                    <div wire:loading wire:target="photoFile" class="text-xs text-indigo-600">Uploading&hellip;</div>
                    <div class="mt-1 flex gap-3 text-xs">
                        @if ($photo !== '')<button type="button" wire:click="removePicture('photo')" wire:confirm="Remove the photo?" class="font-medium text-red-600 hover:text-red-800">Remove</button>@endif
                        <button type="button" wire:click="restorePicture('photo')" class="font-medium text-gray-500 hover:text-gray-800">Use original</button>
                    </div>
                    @error('photoFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Doctor name *</label>
                    <input wire:model="name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Orange tagline</label>
                    <input wire:model="tagline" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('tagline') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Qualifications</label>
                <textarea wire:model="qualifications" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                <p class="mt-1 text-xs text-gray-400">Each line is shown on its own line.</p>
                @error('qualifications') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Button text</label>
                    <input wire:model="button_text" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <p class="mt-1 text-xs text-gray-400">Leave empty to hide the button.</p>
                    @error('button_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Button link</label>
                    <input wire:model="button_url" type="text" placeholder="Empty = About Doctor page" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('button_url') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        {{-- Badge --}}
        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Middle &ndash; Round badge</h3>
            @php $b = $this->preview('badge_image'); @endphp
            <div class="flex items-start gap-3">
                @if ($b)
                    <span class="flex h-16 w-16 items-center justify-center rounded-full border border-gray-200 bg-white"><img src="{{ $b }}" alt="" class="h-10 w-10 object-contain"></span>
                @else
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 text-xs text-gray-400">None</div>
                @endif
                <div>
                    <input type="file" wire:model="badge_imageFile" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                        class="block text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                    <div wire:loading wire:target="badge_imageFile" class="text-xs text-indigo-600">Uploading&hellip;</div>
                    <div class="mt-1 flex gap-3 text-xs">
                        @if ($badge_image !== '')<button type="button" wire:click="removePicture('badge_image')" wire:confirm="Remove the badge icon?" class="font-medium text-red-600 hover:text-red-800">Remove</button>@endif
                        <button type="button" wire:click="restorePicture('badge_image')" class="font-medium text-gray-500 hover:text-gray-800">Use original</button>
                    </div>
                    @error('badge_imageFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="sm:max-w-xs">
                <label class="block text-sm font-medium text-gray-700">Badge text</label>
                <input wire:model="badge_text" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <p class="mt-1 text-xs text-gray-400">With no icon and no text, the badge is hidden.</p>
                @error('badge_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </section>

        {{-- Hospital --}}
        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Right &ndash; Hospital</h3>
            @php $h = $this->preview('hospital_image'); @endphp
            <div class="flex items-start gap-3">
                @if ($h)
                    <img src="{{ $h }}" alt="" class="h-16 w-24 rounded-md border border-gray-200 object-cover">
                @else
                    <div class="flex h-16 w-24 items-center justify-center rounded-md bg-gray-100 text-xs text-gray-400">None</div>
                @endif
                <div>
                    <input type="file" wire:model="hospital_imageFile" accept="image/jpeg,image/png,image/webp"
                        class="block text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                    <div wire:loading wire:target="hospital_imageFile" class="text-xs text-indigo-600">Uploading&hellip;</div>
                    <div class="mt-1 flex gap-3 text-xs">
                        @if ($hospital_image !== '')<button type="button" wire:click="removePicture('hospital_image')" wire:confirm="Remove the hospital photo?" class="font-medium text-red-600 hover:text-red-800">Remove</button>@endif
                        <button type="button" wire:click="restorePicture('hospital_image')" class="font-medium text-gray-500 hover:text-gray-800">Use original</button>
                    </div>
                    @error('hospital_imageFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Hospital name</label>
                    <input wire:model="hospital_name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('hospital_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Orange role line</label>
                    <input wire:model="hospital_role" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('hospital_role') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Description</label>
                <textarea wire:model="hospital_text" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                @error('hospital_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:max-w-md">
                <label class="block text-sm font-medium text-gray-700">Photo link</label>
                <input wire:model="hospital_link" type="text" placeholder="Empty = Contact Us page" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <p class="mt-1 text-xs text-gray-400">Where the hospital photo leads when clicked (for example its Google Maps page).</p>
                @error('hospital_link') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </section>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="save,photoFile,badge_imageFile,hospital_imageFile" class="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">Save band</button>
        </div>
    </form>
</div>
