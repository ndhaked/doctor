<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new #[Layout('layouts::admin')] class extends Component
{
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name = '';
    public array $selectedPermissions = [];

    protected const PROTECTED_ROLE = 'Super Admin';

    #[Computed]
    public function roles()
    {
        return Role::where('guard_name', 'admin')->withCount(['permissions', 'users'])->orderBy('name')->get();
    }

    #[Computed]
    public function permissions()
    {
        return Permission::where('guard_name', 'admin')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'selectedPermissions']);
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $role = Role::findOrFail($id);

        $this->editingId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
        $this->showModal = true;
    }

    public function save(): void
    {
        $isProtected = $this->editingId && Role::find($this->editingId)?->name === self::PROTECTED_ROLE;

        $data = $this->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')->where('guard_name', 'admin')->ignore($this->editingId),
            ],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['exists:permissions,name'],
        ]);

        $role = Role::updateOrCreate(
            ['id' => $this->editingId],
            ['name' => $isProtected ? self::PROTECTED_ROLE : $data['name'], 'guard_name' => 'admin']
        );

        $role->syncPermissions($isProtected ? Permission::where('guard_name', 'admin')->pluck('name') : $data['selectedPermissions']);

        unset($this->roles);

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        $role = Role::findOrFail($id);

        if ($role->name === self::PROTECTED_ROLE) {
            $this->addError('delete', 'The Super Admin role cannot be deleted.');
            return;
        }

        if ($role->users()->count() > 0) {
            $this->addError('delete', 'This role is still assigned to one or more admins.');
            return;
        }

        $role->delete();

        unset($this->roles);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Roles</h2>
    </x-slot:header>

    <div class="flex justify-end mb-4">
        <button wire:click="create" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
            + Add Role
        </button>
    </div>

    @error('delete')
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
    @enderror

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium">Role</th>
                    <th class="px-4 py-3 font-medium">Permissions</th>
                    <th class="px-4 py-3 font-medium">Admins</th>
                    <th class="px-4 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($this->roles as $role)
                    <tr wire:key="role-{{ $role->id }}">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            {{ $role->name }}
                            @if ($role->name === 'Super Admin')
                                <span class="ml-1 text-xs text-gray-400">(full access)</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $role->permissions_count }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $role->users_count }}</td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <button wire:click="edit({{ $role->id }})" class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
                            @if ($role->name !== 'Super Admin')
                                <button
                                    wire:click="delete({{ $role->id }})"
                                    wire:confirm="Are you sure you want to delete this role?"
                                    class="text-red-600 hover:text-red-800 font-medium"
                                >Delete</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-400">No roles found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/30 px-4" wire:key="role-modal">
            <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ $editingId ? 'Edit Role' : 'Add Role' }}</h3>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name</label>
                        <input
                            wire:model="name"
                            type="text"
                            @disabled($editingId && $name === 'Super Admin')
                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100"
                        >
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Permissions</label>
                        <div class="space-y-1">
                            @foreach ($this->permissions as $permission)
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input
                                        type="checkbox"
                                        wire:model="selectedPermissions"
                                        value="{{ $permission->name }}"
                                        @disabled($editingId && $name === 'Super Admin')
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    >
                                    {{ $permission->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
