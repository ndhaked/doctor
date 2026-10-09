<?php

use App\Models\Staff;
use App\Models\StaffRole;
use App\Support\ClinicContext;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $role = '';

    public bool $showModal = false;
    public ?int $editingId = null;
    public string $name = '';
    public ?int $staff_role_id = null;
    public string $phone = '';
    public string $email = '';
    public string $address = '';
    public string $joined_on = '';
    public string $monthly_salary = '0';
    public string $notes = '';
    public bool $is_active = true;

    public ?string $status = null;

    #[Computed]
    public function roles()
    {
        return StaffRole::active()->ordered()->get();
    }

    #[Computed]
    public function members()
    {
        return Staff::forDoctor(ClinicContext::id())
            ->with('role')
            ->withCount(['patients', 'tasks as open_tasks_count' => fn ($q) => $q->where('status', 'pending')])
            ->when($this->role !== '', fn ($q) => $q->where('staff_role_id', (int) $this->role))
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.trim($this->search).'%')
                ->orWhere('phone', 'like', '%'.trim($this->search).'%')))
            ->orderByDesc('is_active')->orderBy('name')
            ->get();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $staff = Staff::forDoctor(ClinicContext::id())->findOrFail($id);

        $this->resetForm();
        $this->editingId = $staff->id;
        $this->name = $staff->name;
        $this->staff_role_id = $staff->staff_role_id;

        foreach (['phone', 'email', 'address', 'notes'] as $field) {
            $this->$field = (string) $staff->$field;
        }

        $this->joined_on = $staff->joined_on?->format('Y-m-d') ?? '';
        $this->monthly_salary = (string) (float) $staff->monthly_salary;
        $this->is_active = $staff->is_active;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'staff_role_id', 'phone', 'email', 'address', 'joined_on', 'notes']);
        $this->monthly_salary = '0';
        $this->is_active = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'staff_role_id' => ['nullable', 'integer', 'exists:staff_roles,id'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9][0-9\s\-().]{5,28}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'joined_on' => ['nullable', 'date', 'before_or_equal:today'],
            'monthly_salary' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ], [
            'name.required' => 'Please enter the staff member\'s name.',
            'phone.regex' => 'Please enter a valid phone number.',
        ]);

        $payload = array_map(fn ($v) => $v === '' ? null : $v, $data);

        if ($this->editingId) {
            Staff::forDoctor(ClinicContext::id())->findOrFail($this->editingId)->update($payload);
            $this->status = 'Staff member updated.';
        } else {
            Staff::create($payload + ['doctor_id' => ClinicContext::id()]);
            $this->status = 'Staff member added.';
        }

        $this->closeModal();
        unset($this->members);
    }

    public function toggle(int $id): void
    {
        $staff = Staff::forDoctor(ClinicContext::id())->findOrFail($id);
        $staff->update(['is_active' => ! $staff->is_active]);

        unset($this->members);
    }

    public function delete(int $id): void
    {
        Staff::forDoctor(ClinicContext::id())->findOrFail($id)->delete();

        $this->status = 'Staff member deleted together with their tasks, attendance and payment records.';
        unset($this->members);
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Staff</h2>
    </x-slot:header>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search name or phone..." class="w-full sm:w-64 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            <select wire:model.live="role" class="rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <option value="">All roles</option>
                @foreach ($this->roles as $r) <option value="{{ $r->id }}">{{ $r->name }}</option> @endforeach
            </select>
        </div>
        <button type="button" wire:click="create" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Add staff member</button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($this->members as $m)
            <div wire:key="staff-{{ $m->id }}" class="rounded-xl border border-gray-200 bg-white p-5 {{ $m->is_active ? '' : 'opacity-60' }}">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <a href="{{ route('admin.clinic.staff.show', $m) }}" wire:navigate class="text-base font-semibold text-gray-900 hover:text-indigo-700">{{ $m->name }}</a>
                        <p class="text-sm text-gray-500">{{ $m->role?->name ?? 'No role' }}@unless ($m->is_active) &middot; inactive @endunless</p>
                    </div>
                </div>
                @if ($m->phone) <p class="mt-2 text-sm text-gray-600">{{ $m->phone }}</p> @endif
                <p class="mt-2 text-xs text-gray-400">{{ $m->patients_count }} patients assigned &middot; {{ $m->open_tasks_count }} open tasks</p>
                <div class="mt-3 flex flex-wrap gap-3 text-xs">
                    <a href="{{ route('admin.clinic.staff.show', $m) }}" wire:navigate class="font-medium text-indigo-600 hover:text-indigo-800">Open</a>
                    <button type="button" wire:click="edit({{ $m->id }})" class="text-gray-500 hover:text-gray-800">Edit</button>
                    <button type="button" wire:click="toggle({{ $m->id }})" class="text-gray-500 hover:text-gray-800">{{ $m->is_active ? 'Deactivate' : 'Activate' }}</button>
                    <button type="button" wire:click="delete({{ $m->id }})" wire:confirm="Delete this staff member and all their records?" class="text-red-600 hover:text-red-800">Delete</button>
                </div>
            </div>
        @empty
            <p class="col-span-full rounded-xl border border-gray-200 bg-white py-12 text-center text-sm text-gray-400">No staff yet. Add compounders, nurses, caregivers, receptionists or anyone working with the doctor.</p>
        @endforelse
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-black/40 px-4 py-8" wire:key="staff-modal">
            <form wire:submit="save" class="w-full max-w-xl space-y-4 rounded-xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit staff member' : 'Add staff member' }}</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name *</label>
                        <input wire:model="name" type="text" autofocus class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Role</label>
                        <select wire:model="staff_role_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">Select&hellip;</option>
                            @foreach ($this->roles as $r) <option value="{{ $r->id }}">{{ $r->name }}</option> @endforeach
                        </select>
                        @error('staff_role_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Joining date</label>
                        <input wire:model="joined_on" type="date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('joined_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Monthly salary</label>
                        <input wire:model="monthly_salary" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('monthly_salary') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Address</label>
                    <textarea wire:model="address" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                    @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea wire:model="notes" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                    @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"> Currently working here</label>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save</button>
                </div>
            </form>
        </div>
    @endif
</div>
