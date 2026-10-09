<?php

use App\Models\Patient;
use App\Support\ClinicContext;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    // Form
    public bool $showModal = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $gender = '';
    public string $dob = '';
    public string $phone = '';
    public string $email = '';
    public string $blood_group = '';
    public string $address = '';
    public string $emergency_contact = '';
    public string $allergies = '';
    public string $medical_history = '';
    public string $notes = '';

    public ?string $status = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function patients()
    {
        return Patient::forDoctor(ClinicContext::id())
            ->search($this->search)
            ->withCount(['appointments', 'consultations'])
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $patient = Patient::forDoctor(ClinicContext::id())->findOrFail($id);

        $this->resetForm();
        $this->editingId = $patient->id;

        foreach (['name', 'gender', 'phone', 'email', 'blood_group', 'address', 'emergency_contact', 'allergies', 'medical_history', 'notes'] as $field) {
            $this->$field = (string) $patient->$field;
        }

        $this->dob = $patient->dob?->format('Y-m-d') ?? '';
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'gender', 'dob', 'phone', 'email', 'blood_group', 'address', 'emergency_contact', 'allergies', 'medical_history', 'notes']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'gender' => ['nullable', 'in:'.implode(',', array_keys(Patient::GENDERS))],
            'dob' => ['nullable', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9][0-9\s\-().]{5,28}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'blood_group' => ['nullable', 'in:'.implode(',', Patient::BLOOD_GROUPS)],
            'address' => ['nullable', 'string', 'max:500'],
            'emergency_contact' => ['nullable', 'string', 'max:150'],
            'allergies' => ['nullable', 'string', 'max:1000'],
            'medical_history' => ['nullable', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.required' => 'Please enter the patient name.',
            'phone.required' => 'Please enter a phone number.',
            'phone.regex' => 'Please enter a valid phone number.',
            'dob.before_or_equal' => 'The date of birth cannot be in the future.',
        ]);

        $payload = array_map(fn ($v) => $v === '' ? null : $v, $data);
        $payload['name'] = $data['name'];
        $payload['phone'] = $data['phone'];

        if ($this->editingId) {
            Patient::forDoctor(ClinicContext::id())->findOrFail($this->editingId)->update($payload);
            $this->status = 'Patient updated.';
        } else {
            $patient = Patient::register($payload + ['doctor_id' => ClinicContext::id()]);
            $this->status = "Patient registered with number {$patient->uhid}.";
        }

        $this->closeModal();
        unset($this->patients);
    }

    public function delete(int $id): void
    {
        Patient::forDoctor(ClinicContext::id())->findOrFail($id)->delete();

        $this->status = 'Patient removed. Their records are kept and can be restored by an administrator.';
        unset($this->patients);
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Patients</h2>
    </x-slot:header>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search name, phone, number or email..."
            class="w-full sm:w-80 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
        <button type="button" wire:click="create" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Register patient</button>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Patient</th>
                    <th class="px-4 py-3 font-medium hidden sm:table-cell">Phone</th>
                    <th class="px-4 py-3 font-medium hidden md:table-cell">Age / Gender</th>
                    <th class="px-4 py-3 font-medium hidden lg:table-cell">Visits</th>
                    <th class="px-4 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->patients as $patient)
                    <tr wire:key="patient-{{ $patient->id }}">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.clinic.patients.show', $patient) }}" wire:navigate class="font-medium text-gray-900 hover:text-indigo-700">{{ $patient->name }}</a>
                            <p class="text-xs text-gray-400">{{ $patient->uhid }}@if ($patient->allergies) <span class="ml-1 rounded bg-red-50 px-1.5 py-0.5 text-red-600">Allergy</span>@endif</p>
                        </td>
                        <td class="px-4 py-3 text-gray-600 hidden sm:table-cell">{{ $patient->phone }}</td>
                        <td class="px-4 py-3 text-gray-600 hidden md:table-cell">{{ $patient->age() !== null ? $patient->age().' yrs' : '—' }}{{ $patient->gender ? ' / '.\App\Models\Patient::GENDERS[$patient->gender] : '' }}</td>
                        <td class="px-4 py-3 text-gray-600 hidden lg:table-cell">{{ $patient->appointments_count }} appointments &middot; {{ $patient->consultations_count }} consultations</td>
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                            <a href="{{ route('admin.clinic.patients.show', $patient) }}" wire:navigate class="font-medium text-indigo-600 hover:text-indigo-800">Open</a>
                            <button type="button" wire:click="edit({{ $patient->id }})" class="font-medium text-gray-500 hover:text-gray-800">Edit</button>
                            <button type="button" wire:click="delete({{ $patient->id }})" wire:confirm="Remove this patient? Their records are kept." class="font-medium text-red-600 hover:text-red-800">Remove</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-gray-400">{{ $search !== '' ? 'No patients match your search.' : 'No patients yet. Click "Register patient" to add the first one.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $this->patients->links() }}</div>

    @if ($showModal)
        <div class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-black/40 px-4 py-8" wire:key="patient-modal">
            <form wire:submit="save" class="w-full max-w-2xl space-y-4 rounded-xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit patient' : 'Register patient' }}</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Full name *</label>
                        <input wire:model="name" type="text" autofocus class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Phone *</label>
                        <input wire:model="phone" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input wire:model="email" type="email" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Gender</label>
                        <select wire:model="gender" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">Select&hellip;</option>
                            @foreach (\App\Models\Patient::GENDERS as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                        </select>
                        @error('gender') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Date of birth</label>
                        <input wire:model="dob" type="date" max="{{ now()->format('Y-m-d') }}" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('dob') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Blood group</label>
                        <select wire:model="blood_group" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">Unknown</option>
                            @foreach (\App\Models\Patient::BLOOD_GROUPS as $group) <option value="{{ $group }}">{{ $group }}</option> @endforeach
                        </select>
                        @error('blood_group') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Emergency contact</label>
                        <input wire:model="emergency_contact" type="text" placeholder="Name and phone" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('emergency_contact') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Address</label>
                        <textarea wire:model="address" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                        @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Allergies</label>
                        <textarea wire:model="allergies" rows="2" placeholder="Drug, food or other allergies" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                        @error('allergies') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Medical history</label>
                        <textarea wire:model="medical_history" rows="3" placeholder="Past illnesses, surgeries, long-term conditions, current medicines" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                        @error('medical_history') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea wire:model="notes" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                        @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save patient</button>
                </div>
            </form>
        </div>
    @endif
</div>
