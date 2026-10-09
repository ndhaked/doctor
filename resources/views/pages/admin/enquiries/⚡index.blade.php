<?php

use App\Models\Enquiry;
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

    #[Url(except: '')]
    public string $source = '';

    #[Url(except: '')]
    public string $filter = ''; // '' = all, 'new' = unread only

    public ?int $viewingId = null;

    public ?string $status = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSource(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function enquiries()
    {
        return Enquiry::query()
            ->when($this->source !== '', fn ($q) => $q->where('source', $this->source))
            ->when($this->filter === 'new', fn ($q) => $q->unread())
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('phone', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%')
                ->orWhere('message', 'like', '%'.$this->search.'%')))
            ->latestFirst()
            ->paginate(15);
    }

    #[Computed]
    public function unreadCount(): int
    {
        return Enquiry::unread()->count();
    }

    #[Computed]
    public function viewing(): ?Enquiry
    {
        return $this->viewingId ? Enquiry::find($this->viewingId) : null;
    }

    public function openEnquiry(int $id): void
    {
        $enquiry = Enquiry::findOrFail($id);

        if ($enquiry->isNew()) {
            $enquiry->update(['status' => 'read']);
        }

        $this->viewingId = $enquiry->id;
        $this->refreshLists();
    }

    public function closeView(): void
    {
        $this->viewingId = null;
    }

    public function toggleRead(int $id): void
    {
        $enquiry = Enquiry::findOrFail($id);
        $enquiry->update(['status' => $enquiry->isNew() ? 'read' : 'new']);

        $this->refreshLists();
    }

    public function markAllRead(): void
    {
        Enquiry::unread()->update(['status' => 'read']);

        $this->status = 'All enquiries marked as read.';
        $this->refreshLists();
    }

    public function delete(int $id): void
    {
        Enquiry::findOrFail($id)->delete();

        if ($this->viewingId === $id) {
            $this->viewingId = null;
        }

        $this->status = 'Enquiry deleted.';
        $this->refreshLists();
    }

    protected function refreshLists(): void
    {
        unset($this->enquiries, $this->unreadCount, $this->viewing);
    }
}
?>

{{-- The whole inbox re-checks for new enquiries every few seconds, so messages appear without reloading --}}
<div wire:poll.8s>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Enquiries</h2>
    </x-slot:header>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500">
            Messages and appointment requests sent from the website forms. New ones appear here automatically.
            <span class="ml-1 inline-flex items-center gap-1 text-xs text-green-700"><span class="h-2 w-2 animate-pulse rounded-full bg-green-500"></span> Live</span>
        </p>
        <div class="flex items-center gap-3">
            <span class="rounded-full {{ $this->unreadCount > 0 ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-500' }} px-3 py-1 text-xs font-semibold">{{ $this->unreadCount }} unread</span>
            @if ($this->unreadCount > 0)
                <button wire:click="markAllRead" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Mark all as read</button>
            @endif
        </div>
    </div>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search name, phone, email or message..."
            class="w-full sm:w-80 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
        <select wire:model.live="source" class="rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            <option value="">All forms</option>
            @foreach (\App\Models\Enquiry::SOURCES as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="filter" class="rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            <option value="">All messages</option>
            <option value="new">Unread only</option>
        </select>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium">From</th>
                    <th class="px-4 py-3 font-medium hidden md:table-cell">Form</th>
                    <th class="px-4 py-3 font-medium hidden lg:table-cell">Message</th>
                    <th class="px-4 py-3 font-medium whitespace-nowrap">Received</th>
                    <th class="px-4 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->enquiries as $enquiry)
                    <tr wire:key="enquiry-{{ $enquiry->id }}" class="{{ $enquiry->isNew() ? 'bg-indigo-50/50' : '' }}">
                        <td class="px-4 py-3">
                            <div class="flex items-start gap-2">
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $enquiry->isNew() ? 'bg-indigo-600' : 'bg-transparent' }}" title="{{ $enquiry->isNew() ? 'Unread' : 'Read' }}"></span>
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 {{ $enquiry->isNew() ? 'font-semibold' : '' }}">{{ $enquiry->name }}</p>
                                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $enquiry->phone) }}" class="text-xs text-indigo-600 hover:text-indigo-800">{{ $enquiry->phone }}</a>
                                    @if ($enquiry->email) <span class="text-xs text-gray-400"> &middot; {{ $enquiry->email }}</span> @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-600 hidden md:table-cell">{{ $enquiry->sourceLabel() }}</td>
                        <td class="px-4 py-3 text-gray-600 hidden lg:table-cell"><span class="block max-w-xs truncate">{{ $enquiry->message ?: '—' }}</span></td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-600" title="{{ $enquiry->created_at->format('d M Y, h:i A') }}">{{ $enquiry->created_at->diffForHumans() }}</td>
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                            <button wire:click="openEnquiry({{ $enquiry->id }})" class="text-indigo-600 hover:text-indigo-800 font-medium">View</button>
                            <button wire:click="toggleRead({{ $enquiry->id }})" class="text-gray-500 hover:text-gray-800 font-medium">{{ $enquiry->isNew() ? 'Mark read' : 'Mark unread' }}</button>
                            <button wire:click="delete({{ $enquiry->id }})" wire:confirm="Delete this enquiry?" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-gray-400">
                            {{ ($search !== '' || $source !== '' || $filter !== '') ? 'No enquiries match your filters.' : 'No enquiries yet. Messages sent from the website forms will appear here.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $this->enquiries->links() }}</div>

    @if ($this->viewing)
        @php $v = $this->viewing; @endphp
        <div class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-black/40 px-4 py-8" wire:key="enquiry-modal">
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $v->name }}</h3>
                        <p class="text-xs text-gray-500">{{ $v->sourceLabel() }} &middot; {{ $v->created_at->format('d M Y, h:i A') }} ({{ $v->created_at->diffForHumans() }})</p>
                    </div>
                    <button type="button" wire:click="closeView" class="text-gray-400 hover:text-gray-600" aria-label="Close">&times;</button>
                </div>

                <dl class="space-y-3 text-sm">
                    <div class="flex gap-3"><dt class="w-20 shrink-0 font-medium text-gray-500">Phone</dt><dd><a href="tel:{{ preg_replace('/[^\d+]/', '', $v->phone) }}" class="text-indigo-600 hover:text-indigo-800">{{ $v->phone }}</a></dd></div>
                    <div class="flex gap-3"><dt class="w-20 shrink-0 font-medium text-gray-500">Email</dt><dd>@if ($v->email)<a href="mailto:{{ $v->email }}" class="text-indigo-600 hover:text-indigo-800">{{ $v->email }}</a>@else<span class="text-gray-400">Not given</span>@endif</dd></div>
                    <div class="flex gap-3"><dt class="w-20 shrink-0 font-medium text-gray-500">Message</dt><dd class="whitespace-pre-line text-gray-800">{{ $v->message ?: 'No message.' }}</dd></div>
                    <div class="flex gap-3"><dt class="w-20 shrink-0 font-medium text-gray-500">Consent</dt><dd class="text-gray-800">{{ $v->consent ? 'Agreed to be contacted by call, SMS, email or WhatsApp' : 'No' }}</dd></div>
                </dl>

                <div class="mt-6 flex justify-between gap-2 border-t border-gray-200 pt-4">
                    <button type="button" wire:click="delete({{ $v->id }})" wire:confirm="Delete this enquiry?" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">Delete</button>
                    <div class="flex gap-2">
                        <button type="button" wire:click="toggleRead({{ $v->id }})" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ $v->isNew() ? 'Mark read' : 'Mark unread' }}</button>
                        <button type="button" wire:click="closeView" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
