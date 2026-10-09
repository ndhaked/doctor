<?php

use App\Models\Doctor;
use App\Models\Specialization;
use App\Support\ClinicContext;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    public string $name = '';
    public string $qualification = '';
    public string $specialization = '';
    public string $registration_no = '';
    public string $phone = '';
    public string $email = '';
    public string $clinic_name = '';
    public string $clinic_address = '';
    public string $consultation_fee = '0';
    public int $slot_minutes = 15;
    public array $hours = [];

    public string $signature = '';
    public $signature_upload = null;
    public bool $remove_signature = false;

    public ?string $status = null;

    public function mount(): void
    {
        $doctor = ClinicContext::doctor();

        foreach (['name', 'qualification', 'specialization', 'registration_no', 'phone', 'email', 'clinic_name', 'clinic_address', 'signature'] as $field) {
            $this->$field = (string) $doctor->$field;
        }

        $this->consultation_fee = (string) (float) $doctor->consultation_fee;
        $this->slot_minutes = $doctor->slot_minutes;
        $this->hours = $doctor->hours();
    }

    #[Computed]
    public function specializations()
    {
        return Specialization::active()->ordered()->pluck('name');
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'specialization' => ['nullable', 'string', 'max:100'],
            'registration_no' => ['nullable', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'clinic_name' => ['nullable', 'string', 'max:150'],
            'clinic_address' => ['nullable', 'string', 'max:500'],
            'consultation_fee' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'slot_minutes' => ['required', 'integer', 'min:5', 'max:240'],
            'hours' => ['required', 'array'],
            'hours.*.open' => ['boolean'],
            'hours.*.from' => ['required', 'date_format:H:i'],
            'hours.*.to' => ['required', 'date_format:H:i'],
            'signature_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'name.required' => 'Please enter the doctor name.',
            'consultation_fee.numeric' => 'The fee must be a number.',
            'hours.*.from.date_format' => 'Use a valid time.',
            'hours.*.to.date_format' => 'Use a valid time.',
        ]);

        foreach ($data['hours'] as $day => $row) {
            if (! array_key_exists($day, Doctor::DAYS)) {
                abort(422);
            }

            if (! empty($row['open']) && $row['to'] <= $row['from']) {
                $this->addError("hours.$day.to", 'Closing time must be after opening time.');

                return;
            }
        }

        $doctor = ClinicContext::doctor();

        $payload = [
            'name' => $data['name'],
            'qualification' => $data['qualification'] ?: null,
            'specialization' => $data['specialization'] ?: null,
            'registration_no' => $data['registration_no'] ?: null,
            'phone' => $data['phone'] ?: null,
            'email' => $data['email'] ?: null,
            'clinic_name' => $data['clinic_name'] ?: null,
            'clinic_address' => $data['clinic_address'] ?: null,
            'consultation_fee' => $data['consultation_fee'],
            'slot_minutes' => $data['slot_minutes'],
            'working_hours' => collect($data['hours'])->map(fn ($r) => ['open' => (bool) ($r['open'] ?? false), 'from' => $r['from'], 'to' => $r['to']])->all(),
        ];

        if ($this->signature_upload) {
            $this->deleteSignature();
            $payload['signature'] = $this->signature = 'uploads/'.$this->signature_upload->store('signatures', 'uploads');
        } elseif ($this->remove_signature) {
            $this->deleteSignature();
            $payload['signature'] = null;
            $this->signature = '';
        }

        $doctor->update($payload);
        ClinicContext::current()->forget();

        $this->reset(['signature_upload', 'remove_signature']);
        $this->status = 'Doctor profile saved.';
    }

    protected function deleteSignature(): void
    {
        if (str_starts_with($this->signature, 'uploads/')) {
            Storage::disk('uploads')->delete(substr($this->signature, strlen('uploads/')));
        }
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Doctor Profile</h2>
    </x-slot:header>

    <p class="mb-4 text-sm text-gray-500">The doctor&rsquo;s details used on prescriptions, invoices and the appointment calendar. Any specialization works &mdash; nothing here is tied to one specialty.</p>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    <form wire:submit="save" class="max-w-4xl space-y-6">
        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Doctor</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Doctor name *</label>
                    <input wire:model="name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Qualification</label>
                    <input wire:model="qualification" type="text" placeholder="MBBS, MD" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('qualification') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Specialization</label>
                    <select wire:model="specialization" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">Select&hellip;</option>
                        @foreach ($this->specializations as $item)
                            <option value="{{ $item }}">{{ $item }}</option>
                        @endforeach
                        @if ($specialization !== '' && ! $this->specializations->contains($specialization))
                            <option value="{{ $specialization }}">{{ $specialization }}</option>
                        @endif
                    </select>
                    <p class="mt-1 text-xs text-gray-400">The list is managed in Clinic Settings.</p>
                    @error('specialization') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Registration number</label>
                    <input wire:model="registration_no" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('registration_no') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Phone</label>
                    <input wire:model="phone" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input wire:model="email" type="email" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Clinic</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Clinic name</label>
                    <input wire:model="clinic_name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('clinic_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Consultation fee</label>
                        <input wire:model="consultation_fee" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('consultation_fee') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Slot length (min)</label>
                        <input wire:model="slot_minutes" type="number" min="5" max="240" step="5" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('slot_minutes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Clinic address</label>
                <textarea wire:model="clinic_address" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                @error('clinic_address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </section>

        <section class="space-y-3 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Working hours</h3>
            <p class="-mt-1 text-xs text-gray-400">Appointments can only be booked on open days, inside these hours.</p>
            @foreach (\App\Models\Doctor::DAYS as $key => $label)
                <div wire:key="day-{{ $key }}" class="flex flex-wrap items-center gap-3">
                    <label class="flex w-36 items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model.live="hours.{{ $key }}.open" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        {{ $label }}
                    </label>
                    @if (! empty($hours[$key]['open']))
                        <input type="time" wire:model="hours.{{ $key }}.from" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <span class="text-sm text-gray-400">to</span>
                        <input type="time" wire:model="hours.{{ $key }}.to" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @else
                        <span class="text-sm text-gray-400">Closed</span>
                    @endif
                    @error("hours.$key.to") <p class="w-full text-sm text-red-600">{{ $message }}</p> @enderror
                    @error("hours.$key.from") <p class="w-full text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </section>

        <section class="space-y-3 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Signature</h3>
            <p class="-mt-1 text-xs text-gray-400">Printed at the bottom of prescriptions. Use a clear image with a white or transparent background.</p>
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex h-20 w-44 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 p-2">
                    @if ($signature_upload && $signature_upload->isPreviewable())
                        <img src="{{ $signature_upload->temporaryUrl() }}" alt="New signature" class="max-h-16 w-auto">
                    @elseif ($signature !== '' && ! $remove_signature)
                        <img src="{{ asset($signature) }}" alt="Signature" class="max-h-16 w-auto">
                    @else
                        <span class="text-xs text-gray-400">No signature</span>
                    @endif
                </div>
                <div class="space-y-2">
                    <input wire:model="signature_upload" type="file" accept="image/png,image/jpeg,image/webp"
                        class="block text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                    <div wire:loading wire:target="signature_upload" class="text-xs text-indigo-600">Uploading&hellip;</div>
                    @if ($signature !== '')
                        <label class="flex items-center gap-2 text-xs text-gray-600">
                            <input wire:model.live="remove_signature" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"> Remove the signature
                        </label>
                    @endif
                </div>
            </div>
            @error('signature_upload') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </section>

        <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save profile</button>
    </form>
</div>
