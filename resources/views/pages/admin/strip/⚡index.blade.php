<?php

use App\Models\Setting;
use App\Support\InfoStripSection;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts::admin')] class extends Component
{
    public bool $visible = true;

    public string $doctor_name = '';
    public string $doctor_text = '';
    public string $doctor_button_text = '';
    public string $doctor_button_url = '';

    public string $reviews_title = '';

    public string $contact_title = '';
    public string $contact_clinic_label = '';
    public string $contact_link_text = '';
    public string $contact_link_url = '';

    public string $book_title = '';
    public string $book_button_text = '';

    public ?string $status = null;

    private const FIELDS = [
        'doctor_name', 'doctor_text', 'doctor_button_text', 'doctor_button_url',
        'reviews_title',
        'contact_title', 'contact_clinic_label', 'contact_link_text', 'contact_link_url',
        'book_title', 'book_button_text',
    ];

    public function mount(): void
    {
        $strip = InfoStripSection::content();

        $this->visible = $strip['visible'];

        foreach (self::FIELDS as $field) {
            $this->$field = $strip[$field];
        }

        // show the raw values for the two optional links (empty = default target)
        $stored = Setting::many(['strip.contact_link_url' => '']);
        $this->contact_link_url = $stored['strip.contact_link_url'];
    }

    public function save(): void
    {
        $data = $this->validate([
            'visible' => ['boolean'],
            'doctor_name' => ['required', 'string', 'max:120'],
            'doctor_text' => ['nullable', 'string', 'max:300'],
            'doctor_button_text' => ['nullable', 'string', 'max:40'],
            'doctor_button_url' => ['nullable', 'string', 'max:255'],
            'reviews_title' => ['nullable', 'string', 'max:60'],
            'contact_title' => ['nullable', 'string', 'max:60'],
            'contact_clinic_label' => ['nullable', 'string', 'max:40'],
            'contact_link_text' => ['nullable', 'string', 'max:40'],
            'contact_link_url' => ['nullable', 'string', 'max:255'],
            'book_title' => ['nullable', 'string', 'max:60'],
            'book_button_text' => ['nullable', 'string', 'max:40'],
        ], [
            'doctor_name.required' => 'The doctor name cannot be empty.',
        ]);

        Setting::put('strip.visible', $data['visible'] ? '1' : '0');

        foreach (self::FIELDS as $field) {
            Setting::put("strip.$field", trim($data[$field] ?? ''));
        }

        $this->status = 'Info strip saved.';
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Info Strip</h2>
    </x-slot:header>

    <p class="mb-4 text-sm text-gray-500">
        The four columns just under the home page banner. The Google rating, review count and link come from
        <a href="{{ route('admin.reviews.index') }}" class="text-indigo-600 hover:text-indigo-800 font-medium">Reviews</a>,
        and the phone and email from <a href="{{ route('admin.site.index') }}" class="text-indigo-600 hover:text-indigo-800 font-medium">Site Settings</a>.
    </p>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    <form wire:submit="save" class="max-w-4xl space-y-6">
        <label class="flex items-start gap-3 rounded-lg border {{ $visible ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }} p-4">
            <input type="checkbox" wire:model.live="visible" class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
            <span>
                <span class="block text-sm font-medium text-gray-900">Show the info strip on the home page</span>
                <span class="block text-xs text-gray-500">Currently {{ $visible ? 'shown' : 'hidden' }}. Press &ldquo;Save info strip&rdquo; to apply.</span>
            </span>
        </label>

        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Column 1 &ndash; Doctor info</h3>
            <div>
                <label class="block text-sm font-medium text-gray-700">Doctor name *</label>
                <input wire:model="doctor_name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('doctor_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Short text</label>
                <textarea wire:model="doctor_text" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                @error('doctor_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Button text</label>
                    <input wire:model="doctor_button_text" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <p class="mt-1 text-xs text-gray-400">Leave empty to hide the button.</p>
                    @error('doctor_button_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Button link</label>
                    <input wire:model="doctor_button_url" type="text" placeholder="#about" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('doctor_button_url') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Column 2 &ndash; Google reviews</h3>
            <div>
                <label class="block text-sm font-medium text-gray-700">Title</label>
                <input wire:model="reviews_title" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('reviews_title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </section>

        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Column 3 &ndash; Contact</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Title</label>
                    <input wire:model="contact_title" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('contact_title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Clinic row label</label>
                    <input wire:model="contact_clinic_label" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('contact_clinic_label') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Clinic row link text</label>
                    <input wire:model="contact_link_text" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <p class="mt-1 text-xs text-gray-400">Leave empty to hide the clinic row.</p>
                    @error('contact_link_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Clinic row link</label>
                    <input wire:model="contact_link_url" type="text" placeholder="Empty = Contact Us page" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('contact_link_url') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Column 4 &ndash; Quick booking form</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Title</label>
                    <input wire:model="book_title" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('book_title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Button text</label>
                    <input wire:model="book_button_text" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('book_button_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="text-xs text-gray-400">The consent text next to the checkbox is the one set in <a href="{{ route('admin.appointment.index') }}" class="text-indigo-600 underline">Appointment</a>.</p>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save info strip</button>
        </div>
    </form>
</div>
