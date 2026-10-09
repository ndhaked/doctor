<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Support\ClinicContext;
use App\Support\ClinicSettings;
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
    public string $filter = ''; // '' | unpaid | partial | paid | cancelled | owing

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function invoices()
    {
        return Invoice::forDoctor(ClinicContext::id())
            ->with('patient')
            ->when($this->filter === 'owing', fn ($q) => $q->owing())
            ->when(in_array($this->filter, ['unpaid', 'partial', 'paid', 'cancelled'], true), fn ($q) => $q->where('status', $this->filter))
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('number', 'like', '%'.trim($this->search).'%')
                ->orWhereHas('patient', fn ($p) => $p->search($this->search))))
            ->orderByDesc('invoice_date')->orderByDesc('id')
            ->paginate(15);
    }

    /** @return array{pending: float, today: float, month: float} */
    #[Computed]
    public function totals(): array
    {
        $doctorId = ClinicContext::id();

        $pending = Invoice::forDoctor($doctorId)->owing()->get()->sum(fn ($i) => $i->due());
        $payments = Payment::whereHas('invoice', fn ($q) => $q->forDoctor($doctorId));

        return [
            'pending' => round((float) $pending, 2),
            'today' => round((float) (clone $payments)->whereDate('paid_at', today())->sum('amount'), 2),
            'month' => round((float) (clone $payments)->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'), 2),
        ];
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Billing &amp; Payments</h2>
    </x-slot:header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-5"><p class="text-sm text-gray-500">Collected today</p><p class="mt-1 text-2xl font-semibold text-gray-900">{{ \App\Support\ClinicSettings::money($this->totals['today']) }}</p></div>
        <div class="rounded-xl border border-gray-200 bg-white p-5"><p class="text-sm text-gray-500">Collected this month</p><p class="mt-1 text-2xl font-semibold text-gray-900">{{ \App\Support\ClinicSettings::money($this->totals['month']) }}</p></div>
        <div class="rounded-xl border border-gray-200 bg-white p-5"><p class="text-sm text-gray-500">Pending payments</p><p class="mt-1 text-2xl font-semibold text-red-600">{{ \App\Support\ClinicSettings::money($this->totals['pending']) }}</p></div>
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search invoice or patient..." class="w-full sm:w-72 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            <select wire:model.live="filter" class="rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <option value="">All invoices</option>
                <option value="owing">Pending (unpaid or partly paid)</option>
                <option value="unpaid">Unpaid</option>
                <option value="partial">Partly paid</option>
                <option value="paid">Paid</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
        <a href="{{ route('admin.clinic.billing.create') }}" wire:navigate class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">New invoice</a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Invoice</th>
                    <th class="px-4 py-3 font-medium">Patient</th>
                    <th class="px-4 py-3 font-medium hidden sm:table-cell">Date</th>
                    <th class="px-4 py-3 font-medium text-right">Total</th>
                    <th class="px-4 py-3 font-medium text-right hidden md:table-cell">Due</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->invoices as $inv)
                    <tr wire:key="inv-{{ $inv->id }}">
                        <td class="px-4 py-3"><a href="{{ route('admin.clinic.billing.show', $inv) }}" wire:navigate class="font-medium text-indigo-600 hover:text-indigo-800">{{ $inv->number }}</a></td>
                        <td class="px-4 py-3 text-gray-800">{{ $inv->patient->name }}</td>
                        <td class="px-4 py-3 text-gray-600 hidden sm:table-cell">{{ $inv->invoice_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right text-gray-800">{{ \App\Support\ClinicSettings::money($inv->total) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600 hidden md:table-cell">{{ $inv->due() > 0 ? \App\Support\ClinicSettings::money($inv->due()) : '—' }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $inv->badgeClasses() }}">{{ $inv->statusLabel() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-gray-400">No invoices found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $this->invoices->links() }}</div>
</div>
