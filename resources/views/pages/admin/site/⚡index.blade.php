<?php

use App\Models\Setting;
use App\Support\SiteSettings;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    // Logo and bar colours
    public string $brand_name = '';
    public string $brand_tagline = '';
    public string $meta_title = '';
    public string $logo = '';
    public $logo_upload = null;
    public bool $remove_logo = false;
    public string $topbar_bg = '#022c22';
    public string $topbar_text = '#ecfdf5';
    public string $header_bg = '#ffffff';

    public string $doctor_name = '';
    public string $doctor_qualifications = '';

    public string $phone = '';
    public string $email = '';
    public string $address = '';

    public string $timing_title = '';
    public string $timing_days = '';
    public string $timing_hours = '';
    public string $timing_note = '';

    public string $facebook = '';
    public string $instagram = '';
    public string $youtube = '';

    public string $footer_text = '';
    public string $footer_links_text = '';
    public string $copyright_name = '';
    public string $credit_name = '';

    public string $map_url = '';

    public string $notify_email = '';

    public ?string $status = null;

    private const FIELDS = [
        'doctor_name', 'doctor_qualifications',
        'phone', 'email', 'address',
        'timing_title', 'timing_days', 'timing_hours', 'timing_note',
        'facebook', 'instagram', 'youtube',
        'brand_tagline', 'meta_title', 'topbar_bg', 'topbar_text', 'header_bg',
        'footer_text', 'copyright_name', 'credit_name', 'map_url', 'notify_email',
    ];

    public function mount(): void
    {
        $site = SiteSettings::content();

        foreach ([...self::FIELDS, 'footer_links_text'] as $field) {
            $this->$field = $site[$field];
        }

        $this->logo = $site['logo'];
        $this->brand_name = $site['brand_name_raw'];

        // edit the text exactly as saved, with its {phone} / {email} placeholders
        $this->footer_text = $site['footer_text_raw'];
    }

    public function save(): void
    {
        $data = $this->validate([
            'doctor_name' => ['required', 'string', 'max:120'],
            'doctor_qualifications' => ['nullable', 'string', 'max:300'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:250'],
            'timing_title' => ['nullable', 'string', 'max:100'],
            'timing_days' => ['nullable', 'string', 'max:60'],
            'timing_hours' => ['nullable', 'string', 'max:60'],
            'timing_note' => ['nullable', 'string', 'max:120'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'youtube' => ['nullable', 'string', 'max:255'],
            'footer_text' => ['nullable', 'string', 'max:600'],
            'footer_links_text' => ['nullable', 'string', 'max:2000'],
            'copyright_name' => ['nullable', 'string', 'max:100'],
            'credit_name' => ['nullable', 'string', 'max:100'],
            'map_url' => ['nullable', 'string', 'max:1000'],
            'notify_email' => ['nullable', 'email', 'max:150'],
            'brand_name' => ['nullable', 'string', 'max:80'],
            'brand_tagline' => ['nullable', 'string', 'max:60'],
            'meta_title' => ['nullable', 'string', 'max:120'],
            'topbar_bg' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'topbar_text' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'header_bg' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg,gif', 'max:2048'],
        ], [
            'topbar_bg.regex' => 'Choose a colour using the colour picker.',
            'topbar_text.regex' => 'Choose a colour using the colour picker.',
            'header_bg.regex' => 'Choose a colour using the colour picker.',
            'logo_upload.image' => 'The logo must be an image (PNG, JPG, WebP, SVG or GIF).',
            'logo_upload.max' => 'The logo must be 2 MB or smaller.',
        ]);

        foreach (self::FIELDS as $field) {
            Setting::put("site.$field", trim($data[$field] ?? ''));
        }
        Setting::put('site.footer_links', trim($data['footer_links_text'] ?? ''));

        Setting::put('site.brand_name', trim($data['brand_name'] ?? ''));

        if ($this->logo_upload) {
            $this->deleteLogoFile();
            $this->logo = 'uploads/'.$this->logo_upload->store('logo', 'uploads');
            Setting::put('site.logo', $this->logo);
        } elseif ($this->remove_logo) {
            $this->deleteLogoFile();
            $this->logo = '';
            Setting::put('site.logo', '');
        }

        $this->reset(['logo_upload', 'remove_logo']);

        $this->status = 'Site settings saved. They are now used on every page.';
    }

    public function resetColors(): void
    {
        $this->topbar_bg = '#022c22';
        $this->topbar_text = '#ecfdf5';
        $this->header_bg = '#ffffff';
    }

    /** Only a logo uploaded through the admin is removed from disk. */
    protected function deleteLogoFile(): void
    {
        if (str_starts_with($this->logo, 'uploads/')) {
            Storage::disk('uploads')->delete(substr($this->logo, strlen('uploads/')));
        }
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Site Settings</h2>
    </x-slot:header>

    <p class="mb-4 text-sm text-gray-500">
        Details shared by many pages. Change them here once and they update everywhere (top bar, footer, contact page, booking section, popup and more).
    </p>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    <form wire:submit="save" class="max-w-4xl space-y-6">
        {{-- Logo and header colours --}}
        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Brand, logo &amp; header colours</h3>
            <p class="-mt-2 text-xs text-gray-400">Used in the header, footer, appointment popup and browser tab titles. Without an uploaded logo, the name below is shown next to the default heart icon. Colours apply to the top bar and the menu bar across the whole width of every page.</p>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Clinic / brand name</label>
                    <input wire:model.live="brand_name" type="text" placeholder="{{ config('app.name') }}" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('brand_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Second line (optional)</label>
                    <input wire:model.live="brand_tagline" type="text" placeholder="Clinic" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('brand_tagline') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Browser title subtitle</label>
                    <input wire:model="meta_title" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('meta_title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Logo image</label>
                <div class="mt-2 flex flex-wrap items-center gap-4">
                    <div class="flex h-16 min-w-32 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 px-3">
                        @if ($logo_upload && $logo_upload->isPreviewable())
                            <img src="{{ $logo_upload->temporaryUrl() }}" alt="New logo preview" class="max-h-12 w-auto">
                        @elseif ($logo !== '' && ! $remove_logo)
                            <img src="{{ asset($logo) }}" alt="Current logo" class="max-h-12 w-auto">
                        @else
                            <span class="text-xs text-gray-400">Default logo</span>
                        @endif
                    </div>
                    <div class="space-y-2">
                        <input wire:model="logo_upload" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/gif"
                            class="block text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                        <div wire:loading wire:target="logo_upload" class="text-xs text-indigo-600">Uploading&hellip;</div>
                        @if ($logo !== '')
                            <label class="flex items-center gap-2 text-xs text-gray-600">
                                <input wire:model.live="remove_logo" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                Remove the uploaded logo and go back to the default
                            </label>
                        @endif
                    </div>
                </div>
                <p class="mt-1 text-xs text-gray-400">A wide PNG, SVG or WebP with a transparent background works best (max 2 MB). Save the settings to apply.</p>
                @error('logo_upload') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                @foreach (['topbar_bg' => 'Top bar background', 'topbar_text' => 'Top bar text & icons', 'header_bg' => 'Menu bar background'] as $field => $label)
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ $label }}</label>
                        <div class="mt-1 flex items-center gap-2">
                            <input wire:model.live="{{ $field }}" type="color" class="h-10 w-14 cursor-pointer rounded border border-gray-300 bg-white p-1">
                            <code class="text-xs text-gray-500">{{ $this->$field }}</code>
                        </div>
                        @error($field) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>

            {{-- Live preview of both bars --}}
            <div class="overflow-hidden rounded-lg border border-gray-200 text-xs">
                <div class="flex items-center justify-between px-4 py-2" style="background-color: {{ $topbar_bg }}; color: {{ $topbar_text }}">
                    <span>Phone : {{ $phone ?: '+91 99999 99999' }} &nbsp; Email : {{ $email ?: 'care@example.com' }}</span>
                    <span class="flex gap-1.5">@foreach (range(1, 3) as $i)<span class="h-5 w-5 rounded-full border border-current"></span>@endforeach</span>
                </div>
                <div class="flex items-center justify-between px-4 py-3 text-gray-700" style="background-color: {{ $header_bg }}">
                    <span class="font-bold">{{ trim($brand_name) !== '' ? $brand_name : config('app.name') }} {{ $brand_tagline }}</span>
                    <span>Home &nbsp; About Doctor &nbsp; Contact Us</span>
                </div>
            </div>
            <button type="button" wire:click="resetColors" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Reset colours to default</button>
        </section>
        {{-- Doctor --}}
        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Doctor</h3>
            <p class="-mt-2 text-xs text-gray-400">Used as the default name and qualifications in the doctor blocks on the home page. Each block can still use its own text.</p>
            <div>
                <label class="block text-sm font-medium text-gray-700">Doctor name *</label>
                <input wire:model="doctor_name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('doctor_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Qualifications</label>
                <textarea wire:model="doctor_qualifications" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                <p class="mt-1 text-xs text-gray-400">Each line is shown on its own line.</p>
                @error('doctor_qualifications') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </section>

        {{-- Contact --}}
        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Contact details</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Phone</label>
                    <input wire:model="phone" type="text" placeholder="+91 99999 99999" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <p class="mt-1 text-xs text-gray-400">The call link is made from this number.</p>
                    @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input wire:model="email" type="email" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Email me new website enquiries at</label>
                <input wire:model="notify_email" type="email" placeholder="Leave empty to read them only in the admin Enquiries inbox" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <p class="mt-1 text-xs text-gray-400">A copy of every message sent from the website forms is emailed here. For real emails, set your mail server in the <code>.env</code> file (MAIL_ settings); until then emails are only written to the log.</p>
                @error('notify_email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Clinic address</label>
                <input wire:model="address" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Google Maps embed link</label>
                <input wire:model="map_url" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <p class="mt-1 text-xs text-gray-400">In Google Maps open your clinic, choose Share &rarr; Embed a map, and paste only the link inside <code>src="..."</code>. Used on the Contact Us page.</p>
                @error('map_url') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </section>

        {{-- Timing --}}
        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Clinic timing</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Title (bold)</label>
                    <input wire:model="timing_title" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('timing_title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Note (bold)</label>
                    <input wire:model="timing_note" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('timing_note') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Days</label>
                    <input wire:model="timing_days" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('timing_days') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Hours</label>
                    <input wire:model="timing_hours" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('timing_hours') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        {{-- Social --}}
        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Social media links</h3>
            <p class="-mt-2 text-xs text-gray-400">Shown as round icons in the top bar and the footer. Leave one empty to hide its icon.</p>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Facebook</label>
                    <input wire:model="facebook" type="text" placeholder="https://facebook.com/..." class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('facebook') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Instagram</label>
                    <input wire:model="instagram" type="text" placeholder="https://instagram.com/..." class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('instagram') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">YouTube</label>
                    <input wire:model="youtube" type="text" placeholder="https://youtube.com/..." class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('youtube') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        {{-- Footer --}}
        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Footer</h3>
            <div>
                <label class="block text-sm font-medium text-gray-700">About text</label>
                <textarea wire:model="footer_text" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                <p class="mt-1 text-xs text-gray-400">Type <code>{phone}</code> or <code>{email}</code> to insert the contact details above, so the text updates when they change.</p>
                @error('footer_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Bottom &ldquo;Quick Links&rdquo; box</label>
                <textarea wire:model="footer_links_text" rows="6" class="mt-1 block w-full rounded-lg border-gray-300 font-mono shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs"></textarea>
                <p class="mt-1 text-xs text-gray-400">One link per line as <code>Label|link</code>, for example <code>Physician in Jaipur|/about-doctor</code>. Use <code>#</code> if there is no page yet. Leave empty to hide the box.</p>
                @error('footer_links_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Copyright name</label>
                    <input wire:model="copyright_name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('copyright_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">&ldquo;Design &amp; Developed by&rdquo; name</label>
                    <input wire:model="credit_name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('credit_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save site settings</button>
        </div>
    </form>
</div>
