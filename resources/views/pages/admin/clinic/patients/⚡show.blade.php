<?php

use App\Models\Consultation;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Support\ClinicContext;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::admin')] class extends Component
{
    use WithFileUploads;

    public Patient $patient;

    #[Url(as: 'tab', except: 'overview')]
    public string $tab = 'overview';

    // Medical summary (editable on the overview tab)
    public string $allergies = '';
    public string $medical_history = '';
    public string $notes = '';

    // Document upload
    public string $doc_title = '';
    public $doc_file = null;

    public ?string $status = null;

    public function mount(Patient $patient): void
    {
        abort_unless($patient->doctor_id === ClinicContext::id(), 404);

        $this->patient = $patient;
        $this->allergies = (string) $patient->allergies;
        $this->medical_history = (string) $patient->medical_history;
        $this->notes = (string) $patient->notes;

        if (! array_key_exists($this->tab, $this->tabs)) {
            $this->tab = 'overview';
        }
    }

    protected function allowed(string $permission): bool
    {
        return auth('admin')->user()->can($permission);
    }

    /** The tabs this person may see: medical, financial and scheduling data are separate permissions. */
    #[Computed]
    public function tabs(): array
    {
        $tabs = ['overview' => 'Overview'];

        if ($this->allowed('manage appointments')) {
            $tabs['visits'] = 'Appointments';
        }

        if ($this->allowed('view medical records')) {
            $tabs['consultations'] = 'Consultations & treatment';
            $tabs['prescriptions'] = 'Prescriptions';
            $tabs['followups'] = 'Follow-ups';
            $tabs['documents'] = 'Documents';
        }

        if ($this->allowed('manage billing')) {
            $tabs['billing'] = 'Billing & payments';
        }

        return $tabs;
    }

    public function setTab(string $tab): void
    {
        if (array_key_exists($tab, $this->tabs)) {
            $this->tab = $tab;
        }
    }

    #[Computed]
    public function appointments()
    {
        return $this->patient->appointments()->limit(50)->get();
    }

    #[Computed]
    public function consultations()
    {
        return $this->patient->consultations()->with('items')->limit(50)->get();
    }

    #[Computed]
    public function invoices()
    {
        return $this->patient->invoices()->with('payments')->limit(50)->get();
    }

    #[Computed]
    public function documents()
    {
        return $this->patient->documents()->get();
    }

    #[Computed]
    public function balance(): float
    {
        return round((float) $this->patient->invoices()->owing()->get()->sum(fn ($i) => $i->due()), 2);
    }

    public function saveSummary(): void
    {
        $data = $this->validate([
            'allergies' => ['nullable', 'string', 'max:1000'],
            'medical_history' => ['nullable', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->patient->update(array_map(fn ($v) => $v === '' ? null : $v, $data));

        $this->status = 'Patient details saved.';
    }

    public function startConsultation(): void
    {
        abort_unless($this->allowed('manage consultations'), 403);

        $consultation = Consultation::create([
            'doctor_id' => $this->patient->doctor_id,
            'patient_id' => $this->patient->id,
            'consulted_at' => now(),
        ]);

        $this->redirectRoute('admin.clinic.consultations.edit', $consultation, navigate: true);
    }

    public function addDocument(): void
    {
        abort_unless($this->allowed('view medical records'), 403);

        $data = $this->validate([
            'doc_title' => ['required', 'string', 'max:150'],
            'doc_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'doc_title.required' => 'Please give the document a name, for example "Blood test 12 May".',
            'doc_file.required' => 'Choose a file to upload.',
            'doc_file.mimes' => 'Only PDF, JPG, PNG or WebP files can be uploaded.',
            'doc_file.max' => 'The file must be 10 MB or smaller.',
        ]);

        $this->patient->documents()->create([
            'title' => $data['doc_title'],
            'file' => $this->doc_file->store('patient-documents/'.$this->patient->id, 'local'),
            'uploaded_by' => auth('admin')->id(),
        ]);

        $this->reset(['doc_title', 'doc_file']);
        $this->status = 'Document added.';
        unset($this->documents);
    }

    public function deleteDocument(int $id): void
    {
        abort_unless($this->allowed('view medical records'), 403);

        $document = $this->patient->documents()->findOrFail($id);
        Storage::disk('local')->delete($document->file);
        $document->delete();

        $this->status = 'Document deleted.';
        unset($this->documents);
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Patient</h2>
    </x-slot:header>

    @php
        $p = $patient;
        $tabs = $this->tabs;
    @endphp

    <div class="mb-4"><a href="{{ route('admin.clinic.patients.index') }}" wire:navigate class="text-sm text-indigo-600 hover:text-indigo-800">&larr; All patients</a></div>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4 rounded-xl border border-gray-200 bg-white p-5">
        <div>
            <h3 class="text-xl font-semibold text-gray-900">{{ $p->name }}</h3>
            <p class="mt-1 text-sm text-gray-500">
                {{ $p->uhid }}
                @if ($p->age() !== null) &middot; {{ $p->age() }} yrs @endif
                @if ($p->gender) &middot; {{ \App\Models\Patient::GENDERS[$p->gender] }} @endif
                @if ($p->blood_group) &middot; {{ $p->blood_group }} @endif
            </p>
            <p class="mt-1 text-sm text-gray-600"><a href="tel:{{ preg_replace('/[^\d+]/', '', $p->phone) }}" class="text-indigo-600 hover:text-indigo-800">{{ $p->phone }}</a>@if ($p->email) &middot; {{ $p->email }} @endif</p>
            @if ($p->allergies)
                <p class="mt-2 inline-block rounded bg-red-50 px-2 py-1 text-xs font-medium text-red-700">Allergies: {{ $p->allergies }}</p>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($this->balance > 0)
                <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-600">Due {{ number_format($this->balance, 2) }}</span>
            @endif
            @if (auth('admin')->user()->can('manage appointments'))
                <a href="{{ route('admin.clinic.appointments.index', ['patient' => $p->id]) }}" wire:navigate class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Book appointment</a>
            @endif
            @if (auth('admin')->user()->can('manage consultations'))
                <button type="button" wire:click="startConsultation" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">New consultation</button>
            @endif
            @if (auth('admin')->user()->can('manage billing'))
                <a href="{{ route('admin.clinic.billing.create', ['patient' => $p->id]) }}" wire:navigate class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">New invoice</a>
            @endif
        </div>
    </div>

    {{-- Tabs --}}
    <div class="mb-4 flex flex-wrap gap-1 border-b border-gray-200">
        @foreach ($tabs as $key => $label)
            <button type="button" wire:click="setTab('{{ $key }}')"
                class="-mb-px border-b-2 px-4 py-2 text-sm font-medium {{ $tab === $key ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-800' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'overview')
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-gray-200 bg-white p-5">
                <h4 class="mb-3 text-sm font-semibold text-gray-900">Personal details</h4>
                <dl class="space-y-2 text-sm">
                    <div class="flex gap-3"><dt class="w-32 shrink-0 text-gray-500">Date of birth</dt><dd class="text-gray-800">{{ $p->dob?->format('d M Y') ?? '—' }}</dd></div>
                    <div class="flex gap-3"><dt class="w-32 shrink-0 text-gray-500">Address</dt><dd class="whitespace-pre-line text-gray-800">{{ $p->address ?: '—' }}</dd></div>
                    <div class="flex gap-3"><dt class="w-32 shrink-0 text-gray-500">Emergency contact</dt><dd class="text-gray-800">{{ $p->emergency_contact ?: '—' }}</dd></div>
                    <div class="flex gap-3"><dt class="w-32 shrink-0 text-gray-500">Registered</dt><dd class="text-gray-800">{{ $p->created_at->format('d M Y') }}</dd></div>
                </dl>
                <p class="mt-3 text-xs text-gray-400">Use &ldquo;Edit&rdquo; in the patient list to change these details.</p>
            </section>

            <form wire:submit="saveSummary" class="space-y-3 rounded-xl border border-gray-200 bg-white p-5">
                <h4 class="text-sm font-semibold text-gray-900">Medical summary</h4>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Allergies</label>
                    <textarea wire:model="allergies" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                    @error('allergies') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Medical history</label>
                    <textarea wire:model="medical_history" rows="4" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                    @error('medical_history') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea wire:model="notes" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                    @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save</button>
            </form>
        </div>
    @endif

    @if ($tab === 'visits')
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500"><tr><th class="px-4 py-3 font-medium">When</th><th class="px-4 py-3 font-medium">Reason</th><th class="px-4 py-3 font-medium">Status</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->appointments as $a)
                        <tr wire:key="appt-{{ $a->id }}">
                            <td class="px-4 py-3 text-gray-800">{{ $a->scheduled_at->format('d M Y, h:i A') }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $a->reason ?: \App\Models\Appointment::TYPES[$a->type] ?? '—' }}</td>
                            <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $a->badgeClasses() }}">{{ $a->statusLabel() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-10 text-center text-gray-400">No appointments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($tab === 'consultations')
        <div class="space-y-3">
            @forelse ($this->consultations as $c)
                <div wire:key="cons-{{ $c->id }}" class="rounded-xl border border-gray-200 bg-white p-5">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ $c->consulted_at->format('d M Y, h:i A') }}
                                <span class="ml-2 rounded-full px-2 py-0.5 text-xs font-medium {{ $c->isFinal() ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' }}">{{ $c->isFinal() ? 'Final' : 'Draft' }}</span>
                            </p>
                            @if ($c->chief_complaint) <p class="mt-1 text-sm text-gray-600"><span class="text-gray-400">Complaint:</span> {{ $c->chief_complaint }}</p> @endif
                            @if ($c->diagnosis) <p class="text-sm text-gray-600"><span class="text-gray-400">Diagnosis:</span> {{ $c->diagnosis }}</p> @endif
                            @if ($c->treatment) <p class="text-sm text-gray-600"><span class="text-gray-400">Treatment:</span> {{ $c->treatment }}</p> @endif
                        </div>
                        <a href="{{ route('admin.clinic.consultations.edit', $c) }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Open</a>
                    </div>
                </div>
            @empty
                <p class="rounded-xl border border-gray-200 bg-white py-10 text-center text-sm text-gray-400">No consultations yet.</p>
            @endforelse
        </div>
    @endif

    @if ($tab === 'prescriptions')
        <div class="space-y-3">
            @forelse ($this->consultations->filter(fn ($c) => $c->items->isNotEmpty()) as $c)
                <div wire:key="rx-{{ $c->id }}" class="rounded-xl border border-gray-200 bg-white p-5">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-900">{{ $c->consulted_at->format('d M Y') }}</p>
                        <a href="{{ route('admin.clinic.consultations.prescription', $c) }}" target="_blank" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Print</a>
                    </div>
                    <ul class="space-y-1 text-sm text-gray-700">
                        @foreach ($c->items as $item)
                            <li>&bull; <span class="font-medium">{{ $item->medicine }}</span> {{ $item->dosage }} {{ $item->frequency ? '&middot; '.$item->frequency : '' }} {{ $item->duration ? '&middot; '.$item->duration : '' }}</li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <p class="rounded-xl border border-gray-200 bg-white py-10 text-center text-sm text-gray-400">No prescriptions yet.</p>
            @endforelse
        </div>
    @endif

    @if ($tab === 'followups')
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500"><tr><th class="px-4 py-3 font-medium">Follow-up date</th><th class="px-4 py-3 font-medium">From visit</th><th class="px-4 py-3 font-medium">State</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->consultations->filter(fn ($c) => $c->follow_up_date)->sortByDesc('follow_up_date') as $c)
                        @php $overdue = $c->follow_up_date->isPast() && ! $c->follow_up_date->isToday(); @endphp
                        <tr wire:key="fu-{{ $c->id }}">
                            <td class="px-4 py-3 text-gray-800">{{ $c->follow_up_date->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $c->consulted_at->format('d M Y') }} &mdash; {{ \Illuminate\Support\Str::limit($c->diagnosis ?: $c->chief_complaint, 60) }}</td>
                            <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $overdue ? 'bg-red-50 text-red-600' : 'bg-sky-50 text-sky-700' }}">{{ $overdue ? 'Overdue' : ($c->follow_up_date->isToday() ? 'Today' : 'Upcoming') }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-10 text-center text-gray-400">No follow-ups planned.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($tab === 'documents')
        <form wire:submit="addDocument" class="mb-4 grid gap-3 rounded-xl border border-gray-200 bg-white p-5 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700">Document name</label>
                <input wire:model="doc_title" type="text" placeholder="e.g. Blood test report" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('doc_title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">File (PDF or image, up to 10 MB)</label>
                <input wire:model="doc_file" type="file" accept=".pdf,image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                <div wire:loading wire:target="doc_file" class="text-xs text-indigo-600">Uploading&hellip;</div>
                @error('doc_file') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Upload</button>
        </form>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <ul class="divide-y divide-gray-100">
                @forelse ($this->documents as $doc)
                    <li wire:key="doc-{{ $doc->id }}" class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                        <div>
                            <p class="font-medium text-gray-900">{{ $doc->title }}</p>
                            <p class="text-xs text-gray-400">{{ $doc->created_at->format('d M Y') }}</p>
                        </div>
                        <div class="space-x-3">
                            <a href="{{ $doc->url() }}" target="_blank" class="font-medium text-indigo-600 hover:text-indigo-800">View</a>
                            <button type="button" wire:click="deleteDocument({{ $doc->id }})" wire:confirm="Delete this document?" class="font-medium text-red-600 hover:text-red-800">Delete</button>
                        </div>
                    </li>
                @empty
                    <li class="px-4 py-10 text-center text-sm text-gray-400">No documents or reports yet.</li>
                @endforelse
            </ul>
        </div>
    @endif

    @if ($tab === 'billing')
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500"><tr><th class="px-4 py-3 font-medium">Invoice</th><th class="px-4 py-3 font-medium">Date</th><th class="px-4 py-3 font-medium text-right">Total</th><th class="px-4 py-3 font-medium text-right">Paid</th><th class="px-4 py-3 font-medium">Status</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->invoices as $inv)
                        <tr wire:key="inv-{{ $inv->id }}">
                            <td class="px-4 py-3"><a href="{{ route('admin.clinic.billing.show', $inv) }}" wire:navigate class="font-medium text-indigo-600 hover:text-indigo-800">{{ $inv->number }}</a></td>
                            <td class="px-4 py-3 text-gray-600">{{ $inv->invoice_date->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right text-gray-800">{{ number_format($inv->total, 2) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ number_format($inv->paid_amount, 2) }}</td>
                            <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $inv->badgeClasses() }}">{{ $inv->statusLabel() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">No invoices yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
