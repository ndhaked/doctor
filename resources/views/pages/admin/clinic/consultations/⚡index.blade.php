<?php

use App\Models\Consultation;
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

    #[Url(except: '')]
    public string $filter = ''; // '' | draft | final | followup

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function consultations()
    {
        return Consultation::forDoctor(ClinicContext::id())
            ->with('patient')
            ->when($this->filter === 'draft' || $this->filter === 'final', fn ($q) => $q->where('status', $this->filter))
            ->when($this->filter === 'followup', fn ($q) => $q->whereNotNull('follow_up_date')->whereDate('follow_up_date', '<=', today()->addDays(7)))
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('patient', fn ($p) => $p->search($this->search))
                ->orWhere('diagnosis', 'like', '%'.trim($this->search).'%')))
            ->orderByDesc('consulted_at')
            ->paginate(15);
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Consultations</h2>
    </x-slot:header>

    <p class="mb-4 text-sm text-gray-500">Start a consultation from the Appointments queue or from a patient&rsquo;s page. All visits are listed here.</p>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search patient or diagnosis..." class="w-full sm:w-72 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
        <select wire:model.live="filter" class="rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            <option value="">All consultations</option>
            <option value="draft">In progress (draft)</option>
            <option value="final">Completed</option>
            <option value="followup">Follow-up due within 7 days</option>
        </select>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Patient</th>
                    <th class="px-4 py-3 font-medium whitespace-nowrap">Date</th>
                    <th class="px-4 py-3 font-medium hidden md:table-cell">Diagnosis</th>
                    <th class="px-4 py-3 font-medium hidden lg:table-cell">Follow-up</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->consultations as $c)
                    <tr wire:key="cons-{{ $c->id }}">
                        <td class="px-4 py-3"><a href="{{ route('admin.clinic.patients.show', $c->patient_id) }}" wire:navigate class="font-medium text-gray-900 hover:text-indigo-700">{{ $c->patient->name }}</a><p class="text-xs text-gray-400">{{ $c->patient->uhid }}</p></td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $c->consulted_at->format('d M Y, h:i A') }}</td>
                        <td class="px-4 py-3 text-gray-600 hidden md:table-cell">{{ \Illuminate\Support\Str::limit($c->diagnosis ?: $c->chief_complaint ?: '—', 60) }}</td>
                        <td class="px-4 py-3 text-gray-600 hidden lg:table-cell">{{ $c->follow_up_date?->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $c->isFinal() ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' }}">{{ $c->isFinal() ? 'Completed' : 'Draft' }}</span></td>
                        <td class="px-4 py-3 text-right space-x-3 whitespace-nowrap">
                            <a href="{{ route('admin.clinic.consultations.edit', $c) }}" wire:navigate class="font-medium text-indigo-600 hover:text-indigo-800">Open</a>
                            <a href="{{ route('admin.clinic.consultations.prescription', $c) }}" target="_blank" class="font-medium text-gray-500 hover:text-gray-800">Print</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-gray-400">No consultations found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $this->consultations->links() }}</div>
</div>
