<?php

use App\Models\ConsultationField;
use App\Models\Setting;
use App\Models\Specialization;
use App\Models\StaffRole;
use App\Support\ClinicContext;
use App\Support\ClinicSettings;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component
{
    // Billing & printing
    public string $currency = '';
    public string $invoice_prefix = '';
    public string $invoice_note = '';
    public string $rx_note = '';
    public string $visit_fee_label = '';

    // Specializations
    public string $new_specialization = '';
    public ?int $editingSpecializationId = null;
    public string $specialization_name = '';

    // Staff roles
    public string $new_staff_role = '';
    public ?int $editingRoleId = null;
    public string $role_name = '';

    // Specialty (custom consultation) fields
    public bool $showFieldModal = false;
    public ?int $editingFieldId = null;
    public string $field_label = '';
    public string $field_type = 'text';
    public string $field_options = '';
    public bool $field_active = true;

    public ?string $status = null;

    public function mount(): void
    {
        $s = ClinicSettings::content();

        foreach (['currency', 'invoice_prefix', 'invoice_note', 'rx_note', 'visit_fee_label'] as $key) {
            $this->$key = $s[$key];
        }
    }

    #[Computed]
    public function specializations()
    {
        return Specialization::ordered()->get();
    }

    #[Computed]
    public function fields()
    {
        return ClinicContext::doctor()->consultationFields()->get();
    }

    /* ---------- billing & printing ---------- */

    public function saveBilling(): void
    {
        $data = $this->validate([
            'currency' => ['required', 'string', 'max:6'],
            'invoice_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
            'invoice_note' => ['nullable', 'string', 'max:300'],
            'rx_note' => ['nullable', 'string', 'max:300'],
            'visit_fee_label' => ['required', 'string', 'max:80'],
        ], [
            'invoice_prefix.regex' => 'Use letters and numbers only, for example INV.',
            'currency.required' => 'Enter a currency symbol, for example ₹ or $.',
        ]);

        Setting::put('clinic.currency', $data['currency']);
        Setting::put('clinic.invoice_prefix', $data['invoice_prefix']);
        Setting::put('clinic.invoice_note', $data['invoice_note'] ?? '');
        Setting::put('clinic.rx_note', $data['rx_note'] ?? '');
        Setting::put('clinic.default_visit_fee_label', $data['visit_fee_label']);

        $this->status = 'Billing and printing settings saved.';
    }

    /* ---------- specializations ---------- */

    public function addSpecialization(): void
    {
        $data = $this->validate([
            'new_specialization' => ['required', 'string', 'max:100', 'unique:specializations,name'],
        ], [
            'new_specialization.required' => 'Enter a specialization name.',
            'new_specialization.unique' => 'That specialization is already in the list.',
        ]);

        Specialization::create(['name' => trim($data['new_specialization']), 'sort_order' => (int) Specialization::max('sort_order') + 1]);

        $this->new_specialization = '';
        $this->status = 'Specialization added.';
        unset($this->specializations);
    }

    public function editSpecialization(int $id): void
    {
        $item = Specialization::findOrFail($id);
        $this->editingSpecializationId = $item->id;
        $this->specialization_name = $item->name;
    }

    public function saveSpecialization(): void
    {
        $this->validate([
            'specialization_name' => ['required', 'string', 'max:100', 'unique:specializations,name,'.$this->editingSpecializationId],
        ], ['specialization_name.unique' => 'That specialization is already in the list.']);

        Specialization::findOrFail($this->editingSpecializationId)->update(['name' => trim($this->specialization_name)]);

        $this->reset(['editingSpecializationId', 'specialization_name']);
        $this->status = 'Specialization renamed.';
        unset($this->specializations);
    }

    public function cancelSpecializationEdit(): void
    {
        $this->reset(['editingSpecializationId', 'specialization_name']);
        $this->resetValidation();
    }

    public function toggleSpecialization(int $id): void
    {
        $item = Specialization::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
        unset($this->specializations);
    }

    public function deleteSpecialization(int $id): void
    {
        Specialization::findOrFail($id)->delete();
        $this->status = 'Specialization deleted.';
        unset($this->specializations);
    }

    /* ---------- staff roles ---------- */

    #[Computed]
    public function staffRoles()
    {
        return StaffRole::ordered()->withCount('staff')->get();
    }

    public function addStaffRole(): void
    {
        $data = $this->validate([
            'new_staff_role' => ['required', 'string', 'max:80', 'unique:staff_roles,name'],
        ], ['new_staff_role.required' => 'Enter a role name.', 'new_staff_role.unique' => 'That role already exists.']);

        StaffRole::create(['name' => trim($data['new_staff_role']), 'sort_order' => (int) StaffRole::max('sort_order') + 1]);

        $this->new_staff_role = '';
        $this->status = 'Staff role added.';
        unset($this->staffRoles);
    }

    public function editStaffRole(int $id): void
    {
        $role = StaffRole::findOrFail($id);
        $this->editingRoleId = $role->id;
        $this->role_name = $role->name;
    }

    public function saveStaffRole(): void
    {
        $this->validate(['role_name' => ['required', 'string', 'max:80', 'unique:staff_roles,name,'.$this->editingRoleId]], ['role_name.unique' => 'That role already exists.']);

        StaffRole::findOrFail($this->editingRoleId)->update(['name' => trim($this->role_name)]);

        $this->reset(['editingRoleId', 'role_name']);
        $this->status = 'Staff role renamed.';
        unset($this->staffRoles);
    }

    public function cancelStaffRoleEdit(): void
    {
        $this->reset(['editingRoleId', 'role_name']);
        $this->resetValidation();
    }

    public function toggleStaffRole(int $id): void
    {
        $role = StaffRole::findOrFail($id);
        $role->update(['is_active' => ! $role->is_active]);
        unset($this->staffRoles);
    }

    public function deleteStaffRole(int $id): void
    {
        $role = StaffRole::withCount('staff')->findOrFail($id);

        if ($role->staff_count > 0) {
            $this->addError('staff_role_delete', "{$role->staff_count} staff member(s) still have the role \"{$role->name}\". Hide the role instead, or change their role first.");

            return;
        }

        $role->delete();
        $this->status = 'Staff role deleted.';
        unset($this->staffRoles);
    }

    /* ---------- specialty fields ---------- */

    public function createField(): void
    {
        $this->resetFieldForm();
        $this->showFieldModal = true;
    }

    public function editField(int $id): void
    {
        $field = ClinicContext::doctor()->consultationFields()->findOrFail($id);

        $this->resetFieldForm();
        $this->editingFieldId = $field->id;
        $this->field_label = $field->label;
        $this->field_type = $field->type;
        $this->field_options = (string) $field->options;
        $this->field_active = $field->is_active;
        $this->showFieldModal = true;
    }

    public function closeFieldModal(): void
    {
        $this->showFieldModal = false;
        $this->resetFieldForm();
    }

    protected function resetFieldForm(): void
    {
        $this->reset(['editingFieldId', 'field_label', 'field_options']);
        $this->field_type = 'text';
        $this->field_active = true;
        $this->resetValidation();
    }

    public function saveField(): void
    {
        $data = $this->validate([
            'field_label' => ['required', 'string', 'max:100'],
            'field_type' => ['required', 'in:'.implode(',', array_keys(ConsultationField::TYPES))],
            'field_options' => ['nullable', 'string', 'max:2000'],
            'field_active' => ['boolean'],
        ], ['field_label.required' => 'Please name the field, for example "Tooth number".']);

        if ($data['field_type'] === 'select' && count(array_filter(array_map('trim', preg_split('/\R/', (string) $data['field_options'])))) < 2) {
            $this->addError('field_options', 'Add at least two choices, one per line.');

            return;
        }

        $doctor = ClinicContext::doctor();
        $field = $this->editingFieldId ? $doctor->consultationFields()->findOrFail($this->editingFieldId) : new ConsultationField(['doctor_id' => $doctor->id]);

        $payload = [
            'label' => $data['field_label'],
            'type' => $data['field_type'],
            'options' => $data['field_type'] === 'select' ? $data['field_options'] : null,
            'is_active' => $data['field_active'],
        ];

        if (! $field->exists) {
            $payload['sort_order'] = (int) $doctor->consultationFields()->max('sort_order') + 1;
        }

        $field->fill($payload)->save();

        $this->status = $this->editingFieldId ? 'Field updated.' : 'Field added. It now appears on every consultation.';
        $this->closeFieldModal();
        unset($this->fields);
    }

    public function toggleField(int $id): void
    {
        $field = ClinicContext::doctor()->consultationFields()->findOrFail($id);
        $field->update(['is_active' => ! $field->is_active]);
        unset($this->fields);
    }

    public function moveField(int $id, string $direction): void
    {
        $ids = ClinicContext::doctor()->consultationFields()->pluck('id')->all();
        $pos = array_search($id, $ids, true);
        $other = $direction === 'up' ? $pos - 1 : $pos + 1;

        if ($pos === false || ! isset($ids[$other])) {
            return;
        }

        [$ids[$pos], $ids[$other]] = [$ids[$other], $ids[$pos]];

        foreach ($ids as $order => $fieldId) {
            ConsultationField::whereKey($fieldId)->update(['sort_order' => $order + 1]);
        }

        unset($this->fields);
    }

    /** Fields hold the answers of past consultations, so deleting one hides it from forms but keeps nothing orphaned: answers stay in the old records. */
    public function deleteField(int $id): void
    {
        ClinicContext::doctor()->consultationFields()->findOrFail($id)->delete();
        $this->status = 'Field deleted. Earlier consultations keep their saved answers.';
        unset($this->fields);
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Clinic Settings</h2>
    </x-slot:header>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    <div class="max-w-4xl space-y-6">
        {{-- Specialty fields --}}
        <section id="fields" class="rounded-xl border border-gray-200 bg-white p-5">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Specialty fields</h3>
                    <p class="text-xs text-gray-400">Extra questions shown on every consultation form &mdash; for example &ldquo;Tooth number&rdquo; for a dentist or &ldquo;ECG findings&rdquo; for a cardiologist. They are also printed on the prescription.</p>
                </div>
                <button type="button" wire:click="createField" class="shrink-0 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add field</button>
            </div>
            <ul class="divide-y divide-gray-100 text-sm">
                @forelse ($this->fields as $field)
                    <li wire:key="field-{{ $field->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2.5 {{ $field->is_active ? '' : 'opacity-50' }}">
                        <div>
                            <span class="font-medium text-gray-900">{{ $field->label }}</span>
                            <span class="ml-2 rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-500">{{ \App\Models\ConsultationField::TYPES[$field->type] ?? $field->type }}</span>
                            @unless ($field->is_active) <span class="ml-1 text-[11px] text-gray-400">hidden</span> @endunless
                        </div>
                        <div class="flex gap-3 text-xs">
                            <button type="button" wire:click="editField({{ $field->id }})" class="text-indigo-600 hover:text-indigo-800">Edit</button>
                            <button type="button" wire:click="toggleField({{ $field->id }})" class="text-gray-500 hover:text-gray-800">{{ $field->is_active ? 'Hide' : 'Show' }}</button>
                            <button type="button" wire:click="moveField({{ $field->id }}, 'up')" class="text-gray-500 hover:text-gray-800" aria-label="Move up">&uarr;</button>
                            <button type="button" wire:click="moveField({{ $field->id }}, 'down')" class="text-gray-500 hover:text-gray-800" aria-label="Move down">&darr;</button>
                            <button type="button" wire:click="deleteField({{ $field->id }})" wire:confirm="Delete this field?" class="text-red-600 hover:text-red-800">Delete</button>
                        </div>
                    </li>
                @empty
                    <li class="py-4 text-center text-sm text-gray-400">No specialty fields yet.</li>
                @endforelse
            </ul>
        </section>

        {{-- Billing & printing --}}
        <form wire:submit="saveBilling" class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Billing &amp; printing</h3>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Currency symbol</label>
                    <input wire:model="currency" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('currency') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Invoice number prefix</label>
                    <input wire:model="invoice_prefix" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('invoice_prefix') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">First invoice line</label>
                    <input wire:model="visit_fee_label" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <p class="mt-1 text-xs text-gray-400">Pre-filled with the doctor&rsquo;s consultation fee.</p>
                    @error('visit_fee_label') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Text at the bottom of invoices &amp; receipts</label>
                    <input wire:model="invoice_note" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('invoice_note') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Text at the bottom of prescriptions</label>
                    <input wire:model="rx_note" type="text" placeholder="e.g. Not valid for medico-legal purposes" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('rx_note') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save</button>
        </form>

        {{-- Specializations --}}
        <section class="rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-gray-900">Specializations</h3>
            <p class="mb-3 text-xs text-gray-400">The list offered in the Doctor Profile. Add your own if yours is missing.</p>
            <form wire:submit="addSpecialization" class="mb-3 flex gap-2">
                <input wire:model="new_specialization" type="text" placeholder="e.g. Rheumatologist" class="block w-full max-w-xs rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add</button>
            </form>
            @error('new_specialization') <p class="mb-2 text-sm text-red-600">{{ $message }}</p> @enderror
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($this->specializations as $item)
                    <li wire:key="spec-{{ $item->id }}" class="flex items-center justify-between gap-2 py-2 {{ $item->is_active ? '' : 'opacity-50' }}">
                        @if ($editingSpecializationId === $item->id)
                            <form wire:submit="saveSpecialization" class="flex flex-1 items-center gap-2">
                                <input wire:model="specialization_name" type="text" class="block w-full max-w-xs rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <button type="submit" class="text-xs font-medium text-indigo-600">Save</button>
                                <button type="button" wire:click="cancelSpecializationEdit" class="text-xs text-gray-500">Cancel</button>
                            </form>
                            @error('specialization_name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        @else
                            <span class="text-gray-800">{{ $item->name }}</span>
                            <div class="flex gap-3 text-xs">
                                <button type="button" wire:click="editSpecialization({{ $item->id }})" class="text-indigo-600 hover:text-indigo-800">Rename</button>
                                <button type="button" wire:click="toggleSpecialization({{ $item->id }})" class="text-gray-500 hover:text-gray-800">{{ $item->is_active ? 'Hide' : 'Show' }}</button>
                                <button type="button" wire:click="deleteSpecialization({{ $item->id }})" wire:confirm="Delete this specialization?" class="text-red-600 hover:text-red-800">Delete</button>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    <div class="mt-6 max-w-4xl rounded-xl border border-gray-200 bg-white p-5">
        <h3 class="text-sm font-semibold text-gray-900">Staff roles</h3>
        <p class="mb-3 text-xs text-gray-400">The roles offered when adding staff: compounder, nurse, caregiver, attendant, receptionist, technician and any of your own.</p>
        <form wire:submit="addStaffRole" class="mb-3 flex gap-2">
            <input wire:model="new_staff_role" type="text" placeholder="e.g. Lab assistant" class="block w-full max-w-xs rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add</button>
        </form>
        @error('new_staff_role') <p class="mb-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('staff_role_delete') <p class="mb-2 text-sm text-red-600">{{ $message }}</p> @enderror
        <ul class="divide-y divide-gray-100 text-sm">
            @foreach ($this->staffRoles as $item)
                <li wire:key="srole-{{ $item->id }}" class="flex items-center justify-between gap-2 py-2 {{ $item->is_active ? '' : 'opacity-50' }}">
                    @if ($editingRoleId === $item->id)
                        <form wire:submit="saveStaffRole" class="flex flex-1 items-center gap-2">
                            <input wire:model="role_name" type="text" class="block w-full max-w-xs rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <button type="submit" class="text-xs font-medium text-indigo-600">Save</button>
                            <button type="button" wire:click="cancelStaffRoleEdit" class="text-xs text-gray-500">Cancel</button>
                        </form>
                        @error('role_name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    @else
                        <span class="text-gray-800">{{ $item->name }} <span class="text-xs text-gray-400">({{ $item->staff_count }})</span></span>
                        <div class="flex gap-3 text-xs">
                            <button type="button" wire:click="editStaffRole({{ $item->id }})" class="text-indigo-600 hover:text-indigo-800">Rename</button>
                            <button type="button" wire:click="toggleStaffRole({{ $item->id }})" class="text-gray-500 hover:text-gray-800">{{ $item->is_active ? 'Hide' : 'Show' }}</button>
                            <button type="button" wire:click="deleteStaffRole({{ $item->id }})" wire:confirm="Delete this role?" class="text-red-600 hover:text-red-800">Delete</button>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>

    @if ($showFieldModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-black/40 px-4 py-8" wire:key="field-modal">
            <form wire:submit="saveField" class="w-full max-w-md space-y-4 rounded-xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900">{{ $editingFieldId ? 'Edit field' : 'Add specialty field' }}</h3>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Field name</label>
                    <input wire:model="field_label" type="text" autofocus class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('field_label') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Answer type</label>
                    <select wire:model.live="field_type" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @foreach (\App\Models\ConsultationField::TYPES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </div>
                @if ($field_type === 'select')
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Choices (one per line)</label>
                        <textarea wire:model="field_options" rows="4" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                        @error('field_options') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif
                <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" wire:model="field_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"> Show on the consultation form</label>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeFieldModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save field</button>
                </div>
            </form>
        </div>
    @endif
</div>
