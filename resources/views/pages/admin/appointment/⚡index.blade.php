<?php

use App\Models\BookingSlide;
use App\Models\Setting;
use App\Support\AppointmentSection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    // Section
    public bool $visible = true;
    public string $eyebrow = '';
    public string $heading = '';
    public string $intro = '';
    public string $clinic_name = '';
    public string $form_title = '';
    public string $submit_text = '';
    public string $consent = '';

    // Popup
    public string $popup_title = '';
    public string $popup_subtitle = '';
    public string $popup_submit = '';
    public string $popup_side_title = '';
    public string $popup_side_text = '';
    public string $popup_logo = '';
    public $popupLogoFile = null;

    // New slide
    public $slideFile = null;
    public string $slideAlt = '';

    public ?string $status = null;

    public function mount(): void
    {
        $a = AppointmentSection::content();

        foreach (['visible', 'eyebrow', 'heading', 'intro', 'clinic_name', 'form_title', 'submit_text', 'consent', 'popup_title', 'popup_subtitle', 'popup_submit', 'popup_side_title', 'popup_logo'] as $key) {
            $this->$key = $a[$key];
        }

        // edit the popup text exactly as saved, with its {phone} / {email} placeholders
        $this->popup_side_text = $a['popup_side_text_raw'];
    }

    #[Computed]
    public function slides()
    {
        return BookingSlide::ordered()->get();
    }

    /* ---------- section + popup texts ---------- */

    public function saveTexts(): void
    {
        $data = $this->validate([
            'visible' => ['boolean'],
            'eyebrow' => ['nullable', 'string', 'max:60'],
            'heading' => ['required', 'string', 'max:200'],
            'intro' => ['nullable', 'string', 'max:400'],
            'clinic_name' => ['nullable', 'string', 'max:120'],
            'form_title' => ['required', 'string', 'max:80'],
            'submit_text' => ['required', 'string', 'max:40'],
            'consent' => ['nullable', 'string', 'max:300'],
            'popup_title' => ['required', 'string', 'max:80'],
            'popup_subtitle' => ['nullable', 'string', 'max:150'],
            'popup_submit' => ['required', 'string', 'max:40'],
            'popup_side_title' => ['nullable', 'string', 'max:80'],
            'popup_side_text' => ['nullable', 'string', 'max:600'],
            'popupLogoFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ], [
            'heading.required' => 'The section heading cannot be empty.',
            'form_title.required' => 'The form title cannot be empty.',
            'submit_text.required' => 'The submit button text cannot be empty.',
            'popup_title.required' => 'The popup title cannot be empty.',
            'popup_submit.required' => 'The popup button text cannot be empty.',
        ]);

        if ($this->popupLogoFile) {
            $this->deleteUpload($this->popup_logo);
            $this->popup_logo = 'uploads/'.$this->popupLogoFile->store('appointment', 'uploads');
            $this->popupLogoFile = null;
            Setting::put('appt.popup_logo', $this->popup_logo);
        }

        Setting::put('appt.visible', $data['visible'] ? '1' : '0');

        foreach (['eyebrow', 'heading', 'intro', 'clinic_name', 'form_title', 'submit_text', 'consent', 'popup_title', 'popup_subtitle', 'popup_submit', 'popup_side_title', 'popup_side_text'] as $key) {
            Setting::put("appt.$key", $data[$key] ?? '');
        }

        $this->status = 'Appointment texts saved.';
    }

    public function removePopupLogo(): void
    {
        $this->deleteUpload($this->popup_logo);
        $this->popup_logo = '';
        $this->popupLogoFile = null;
        Setting::put('appt.popup_logo', '');

        $this->status = 'Popup logo removed.';
    }

    public function restorePopupLogo(): void
    {
        $this->popup_logo = AppointmentSection::defaults()['appt.popup_logo'];
        $this->popupLogoFile = null;
        Setting::put('appt.popup_logo', $this->popup_logo);

        $this->status = 'Original logo restored.';
    }

    /* ---------- slides ---------- */

    public function addSlide(): void
    {
        $this->validate([
            'slideFile' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'slideAlt' => ['nullable', 'string', 'max:150'],
        ], [
            'slideFile.required' => 'Please choose a picture to add.',
        ]);

        BookingSlide::create([
            'image' => 'uploads/'.$this->slideFile->store('appointment', 'uploads'),
            'alt' => $this->slideAlt ?: null,
            'sort_order' => (int) BookingSlide::max('sort_order') + 1,
        ]);

        $this->reset(['slideFile', 'slideAlt']);
        $this->status = 'Picture added.';
        unset($this->slides);
    }

    public function toggleSlide(int $id): void
    {
        $slide = BookingSlide::findOrFail($id);
        $slide->update(['is_active' => ! $slide->is_active]);

        unset($this->slides);
    }

    public function moveSlide(int $id, string $direction): void
    {
        $ids = BookingSlide::ordered()->pluck('id')->all();
        $pos = array_search($id, $ids, true);
        $swap = $direction === 'up' ? $pos - 1 : $pos + 1;

        if ($pos === false || ! isset($ids[$swap])) {
            return;
        }

        [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];

        foreach ($ids as $order => $slideId) {
            BookingSlide::whereKey($slideId)->update(['sort_order' => $order + 1]);
        }

        unset($this->slides);
    }

    public function deleteSlide(int $id): void
    {
        $slide = BookingSlide::findOrFail($id);

        $this->deleteUpload($slide->image);
        $slide->delete();

        $this->status = 'Picture deleted.';
        unset($this->slides);
    }

    /** Only files uploaded through the admin are removed; bundled images are never touched. */
    protected function deleteUpload(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/')) {
            Storage::disk('uploads')->delete(substr($path, strlen('uploads/')));
        }
    }

    protected function logoPreview(): ?string
    {
        if ($this->popupLogoFile) {
            try {
                return $this->popupLogoFile->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        return $this->popup_logo !== '' ? asset($this->popup_logo) : null;
    }

    protected function slidePreview(): ?string
    {
        if ($this->slideFile) {
            try {
                return $this->slideFile->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Appointment</h2>
    </x-slot:header>

    <p class="mb-4 text-sm text-gray-500">
        The &ldquo;Book your appointment&rdquo; section (shown on the home, About Doctor and FAQs pages) and the appointment popup. Phone, email and timings come from
        <a href="{{ route('admin.site.index') }}" class="text-indigo-600 hover:text-indigo-800 font-medium">Site Settings</a>.
    </p>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    @php
        $logoPreview = $this->logoPreview();
        $slidePreview = $this->slidePreview();
    @endphp

    <form wire:submit="saveTexts" class="mb-8 max-w-4xl space-y-6">
        {{-- Section --}}
        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">&ldquo;Book your appointment&rdquo; section</h3>

            <label class="flex items-start gap-3 rounded-lg border {{ $visible ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }} p-4">
                <input type="checkbox" wire:model.live="visible" class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <span>
                    <span class="block text-sm font-medium text-gray-900">Show this section on the website</span>
                    <span class="block text-xs text-gray-500">Currently {{ $visible ? 'shown' : 'hidden' }}. The popup opened by &ldquo;Get Appointment&rdquo; buttons keeps working either way. Press &ldquo;Save texts&rdquo; to apply.</span>
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
                    <label class="block text-sm font-medium text-gray-700">Clinic name (left card)</label>
                    <input wire:model="clinic_name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('clinic_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Form title *</label>
                    <input wire:model="form_title" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('form_title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Form button *</label>
                    <input wire:model="submit_text" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('submit_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Consent checkbox text (also used in the popup)</label>
                <textarea wire:model="consent" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                @error('consent') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </section>

        {{-- Popup --}}
        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Appointment popup</h3>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Title *</label>
                    <input wire:model="popup_title" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('popup_title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Sub-title</label>
                    <input wire:model="popup_subtitle" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('popup_subtitle') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Button text *</label>
                    <input wire:model="popup_submit" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('popup_submit') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Green panel heading</label>
                    <input wire:model="popup_side_title" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('popup_side_title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Green panel text</label>
                <textarea wire:model="popup_side_text" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                <p class="mt-1 text-xs text-gray-400">Type <code>{phone}</code> or <code>{email}</code> to insert the details from Site Settings.</p>
                @error('popup_side_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Logo in the green panel</label>
                <div class="mt-1 flex items-start gap-4">
                    @if ($logoPreview)
                        <span class="rounded-md border border-gray-200 bg-white p-2"><img src="{{ $logoPreview }}" alt="" class="h-12 w-auto"></span>
                    @else
                        <div class="flex h-16 w-28 items-center justify-center rounded-md bg-gray-100 px-2 text-center text-xs text-gray-400">Uses the site logo</div>
                    @endif
                    <div>
                        <input type="file" wire:model="popupLogoFile" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                            class="block text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                        <div wire:loading wire:target="popupLogoFile" class="text-xs text-indigo-600">Uploading&hellip;</div>
                        <div class="mt-1 flex gap-3 text-xs">
                            @if ($popup_logo !== '')
                                <button type="button" wire:click="removePopupLogo" wire:confirm="Remove the logo?" class="font-medium text-red-600 hover:text-red-800">Remove logo</button>
                            @endif
                            <button type="button" wire:click="restorePopupLogo" class="font-medium text-gray-500 hover:text-gray-800">Use the site logo</button>
                        </div>
                        @error('popupLogoFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </section>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="saveTexts,popupLogoFile" class="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">Save texts</button>
        </div>
    </form>

    {{-- Slides --}}
    <section class="max-w-4xl">
        <h3 class="mb-1 text-sm font-semibold text-gray-900">Pictures in the section slider</h3>
        <p class="mb-3 text-xs text-gray-500">The poster and photos that rotate in the middle of the section. Tall pictures (about 2:3) fit best; other shapes are cropped to fit.</p>

        <form wire:submit="addSlide" class="mb-4 flex flex-wrap items-end gap-4 rounded-xl border border-gray-200 bg-white p-4">
            <div class="flex items-center gap-3">
                @if ($slidePreview)
                    <img src="{{ $slidePreview }}" alt="" class="h-16 w-11 rounded object-cover border border-gray-200">
                @endif
                <div>
                    <label class="block text-xs font-medium text-gray-700">New picture</label>
                    <input type="file" wire:model="slideFile" accept="image/jpeg,image/png,image/webp"
                        class="mt-1 block text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                    <div wire:loading wire:target="slideFile" class="text-xs text-indigo-600">Uploading&hellip;</div>
                </div>
            </div>
            <div class="min-w-48 flex-1">
                <label class="block text-xs font-medium text-gray-700">Description (for screen readers)</label>
                <input wire:model="slideAlt" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>
            <button type="submit" wire:loading.attr="disabled" wire:target="addSlide,slideFile" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">+ Add picture</button>
            @error('slideFile') <p class="w-full text-sm text-red-600">{{ $message }}</p> @enderror
            @error('slideAlt') <p class="w-full text-sm text-red-600">{{ $message }}</p> @enderror
        </form>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium w-24">Order</th>
                        <th class="px-4 py-3 font-medium">Picture</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->slides as $slide)
                        <tr wire:key="slide-{{ $slide->id }}">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1 text-gray-500">
                                    <span class="w-5 text-center font-medium text-gray-900">{{ $loop->iteration }}</span>
                                    <button wire:click="moveSlide({{ $slide->id }}, 'up')" @disabled($loop->first) title="Move up" class="rounded p-1 hover:bg-gray-100 disabled:opacity-30"><x-icon name="chevron-down" class="h-4 w-4 rotate-180" /></button>
                                    <button wire:click="moveSlide({{ $slide->id }}, 'down')" @disabled($loop->last) title="Move down" class="rounded p-1 hover:bg-gray-100 disabled:opacity-30"><x-icon name="chevron-down" class="h-4 w-4" /></button>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $slide->imageUrl() }}" alt="" class="h-16 w-11 rounded border border-gray-200 object-cover">
                                    <span class="text-xs text-gray-500">{{ $slide->alt }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <button wire:click="toggleSlide({{ $slide->id }})" role="switch" aria-checked="{{ $slide->is_active ? 'true' : 'false' }}"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $slide->is_active ? 'bg-indigo-600' : 'bg-gray-300' }}">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ $slide->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                </button>
                                <span class="ml-2 text-xs {{ $slide->is_active ? 'text-green-700' : 'text-gray-400' }}">{{ $slide->is_active ? 'Visible' : 'Hidden' }}</span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button wire:click="deleteSlide({{ $slide->id }})" wire:confirm="Delete this picture?" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-400">No pictures. The middle column of the section is left empty.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
