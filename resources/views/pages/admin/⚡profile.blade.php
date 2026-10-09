<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

new #[Layout('layouts::admin')] class extends Component
{
    public string $name = '';
    public string $email = '';

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public bool $showDeleteModal = false;
    public string $delete_password = '';

    public function mount(): void
    {
        $this->name = Auth::guard('admin')->user()->name;
        $this->email = Auth::guard('admin')->user()->email;
    }

    public function updateProfileInformation(): void
    {
        $admin = Auth::guard('admin')->user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $admin->update($validated);

        $this->dispatch('profile-updated');
    }

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password:admin'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::guard('admin')->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }

    public function confirmAccountDeletion(): void
    {
        $this->reset('delete_password');
        $this->showDeleteModal = true;
    }

    public function deleteAccount(): void
    {
        if (Admin::count() <= 1) {
            $this->addError('delete_password', 'You cannot delete the only remaining admin account.');
            return;
        }

        $this->validate([
            'delete_password' => ['required', 'string', 'current_password:admin'],
        ]);

        $admin = Auth::guard('admin')->user();

        Auth::guard('admin')->logout();

        $admin->delete();

        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(route('admin.login'), navigate: true);
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Profile</h2>
    </x-slot:header>

    <div class="space-y-6 max-w-2xl">
        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <header>
                <h2 class="text-lg font-medium text-gray-900">Profile Information</h2>
                <p class="mt-1 text-sm text-gray-600">Update your account's profile information.</p>
            </header>

            <form wire:submit="updateProfileInformation" class="mt-6 space-y-6">
                <div>
                    <x-input-label for="name" value="Name" />
                    <x-text-input wire:model="name" id="name" type="text" required autofocus autocomplete="name" />
                    <x-input-error :messages="$errors->get('name')" />
                </div>

                <div>
                    <x-input-label for="email" value="Email" />
                    <x-text-input id="email" type="email" value="{{ $email }}" disabled autocomplete="username" class="mt-1 block w-full bg-gray-100 cursor-not-allowed" />
                    <p class="mt-1 text-xs text-gray-500">Email address cannot be changed.</p>
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>Save</x-primary-button>

                    <x-action-message on="profile-updated">Saved.</x-action-message>
                </div>
            </form>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <header>
                <h2 class="text-lg font-medium text-gray-900">Update Password</h2>
                <p class="mt-1 text-sm text-gray-600">Ensure your account is using a long, random password to stay secure.</p>
            </header>

            <form wire:submit="updatePassword" class="mt-6 space-y-6">
                <div>
                    <x-input-label for="current_password" value="Current Password" />
                    <x-text-input wire:model="current_password" id="current_password" type="password" autocomplete="current-password" />
                    <x-input-error :messages="$errors->get('current_password')" />
                </div>

                <div>
                    <x-input-label for="password" value="New Password" />
                    <x-text-input wire:model="password" id="password" type="password" autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password')" />
                </div>

                <div>
                    <x-input-label for="password_confirmation" value="Confirm Password" />
                    <x-text-input wire:model="password_confirmation" id="password_confirmation" type="password" autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password_confirmation')" />
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>Save</x-primary-button>

                    <x-action-message on="password-updated">Saved.</x-action-message>
                </div>
            </form>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <header>
                <h2 class="text-lg font-medium text-gray-900">Delete Account</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.
                </p>
            </header>

            <div class="mt-6">
                <x-danger-button wire:click="confirmAccountDeletion">Delete Account</x-danger-button>
            </div>
        </section>
    </div>

    @if ($showDeleteModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/30 px-4" wire:key="delete-account-modal">
            <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6">
                <form wire:submit="deleteAccount">
                    <h2 class="text-lg font-medium text-gray-900">Are you sure you want to delete your account?</h2>

                    <p class="mt-1 text-sm text-gray-600">
                        Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.
                    </p>

                    <div class="mt-6">
                        <x-input-label for="delete_password" value="Password" class="sr-only" />
                        <x-text-input
                            wire:model="delete_password"
                            id="delete_password"
                            type="password"
                            placeholder="Password"
                            autofocus
                        />
                        <x-input-error :messages="$errors->get('delete_password')" />
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button wire:click="$set('showDeleteModal', false)">Cancel</x-secondary-button>
                        <x-danger-button type="submit">Delete Account</x-danger-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
