<?php

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Patient;
use App\Support\ClinicContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component
{
    #[Url(as: 'date', except: '')]
    public string $date = '';

    // Calendar month shown (first day of the month, Y-m-d)
    public string $month = '';

    // Form
    public bool $showModal = false;
    public ?int $editingId = null;
    public ?int $patient_id = null;
    public string $patient_search = '';
    public string $appt_date = '';
    public string $appt_time = '';
    public string $type = 'new';
    public int $duration_minutes = 15;
    public string $reason = '';
    public string $notes = '';

    // Cancel dialog
    public ?int $cancellingId = null;
    public string $cancel_reason = '';

    public ?string $status = null;

    public function mount(): void
    {
        $this->date = $this->parseDate($this->date)->format('Y-m-d');
        $this->month = Carbon::parse($this->date)->startOfMonth()->format('Y-m-d');

        // Coming from a patient's page: open the booking form for that patient
        $patientId = (int) request()->query('patient');

        if ($patientId && Patient::forDoctor(ClinicContext::id())->whereKey($patientId)->exists()) {
            $this->openCreate();
            $this->patient_id = $patientId;
        }
    }

    protected function parseDate(?string $value): Carbon
    {
        try {
            return $value ? Carbon::createFromFormat('Y-m-d', $value)->startOfDay() : today();
        } catch (\Throwable) {
            return today();
        }
    }

    public function selectedDay(): Carbon
    {
        return $this->parseDate($this->date);
    }

    /* ---------- navigation ---------- */

    public function goTo(string $date): void
    {
        $day = $this->parseDate($date);
        $this->date = $day->format('Y-m-d');
        $this->month = $day->copy()->startOfMonth()->format('Y-m-d');
    }

    public function shiftDay(int $days): void
    {
        $this->goTo($this->selectedDay()->addDays($days)->format('Y-m-d'));
    }

    public function shiftMonth(int $months): void
    {
        $this->month = Carbon::parse($this->month)->addMonthsNoOverflow($months)->startOfMonth()->format('Y-m-d');
    }

    /* ---------- data ---------- */

    #[Computed]
    public function doctor()
    {
        return ClinicContext::doctor();
    }

    #[Computed]
    public function appointments()
    {
        return Appointment::forDoctor(ClinicContext::id())
            ->onDate($this->selectedDay())
            ->with(['patient', 'consultation'])
            ->orderBy('scheduled_at')
            ->get();
    }

    #[Computed]
    public function queue()
    {
        return $this->appointments
            ->whereIn('status', ['checked_in', 'in_consultation'])
            ->sortBy([['token_no', 'asc'], ['scheduled_at', 'asc']])
            ->values();
    }

    /** Number of live appointments on each day of the shown month. */
    #[Computed]
    public function monthCounts(): array
    {
        $start = Carbon::parse($this->month)->startOfMonth();

        return Appointment::forDoctor(ClinicContext::id())
            ->whereBetween('scheduled_at', [$start, $start->copy()->endOfMonth()])
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->get(['scheduled_at'])
            ->groupBy(fn ($a) => $a->scheduled_at->format('Y-m-d'))
            ->map->count()
            ->all();
    }

    #[Computed]
    public function patientMatches()
    {
        if ($this->patient_id || trim($this->patient_search) === '') {
            return collect();
        }

        return Patient::forDoctor(ClinicContext::id())->search($this->patient_search)->orderBy('name')->limit(6)->get();
    }

    #[Computed]
    public function chosenPatient(): ?Patient
    {
        return $this->patient_id ? Patient::forDoctor(ClinicContext::id())->find($this->patient_id) : null;
    }

    protected function refreshData(): void
    {
        unset($this->appointments, $this->queue, $this->monthCounts);
    }

    /* ---------- booking ---------- */

    public function openCreate(): void
    {
        $this->resetForm();
        $this->appt_date = $this->date;
        $this->duration_minutes = ClinicContext::doctor()->slot_minutes;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $appointment = Appointment::forDoctor(ClinicContext::id())->findOrFail($id);

        $this->resetForm();
        $this->editingId = $appointment->id;
        $this->patient_id = $appointment->patient_id;
        $this->appt_date = $appointment->scheduled_at->format('Y-m-d');
        $this->appt_time = $appointment->scheduled_at->format('H:i');
        $this->type = $appointment->type;
        $this->duration_minutes = $appointment->duration_minutes;
        $this->reason = (string) $appointment->reason;
        $this->notes = (string) $appointment->notes;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'patient_id', 'patient_search', 'appt_date', 'appt_time', 'reason', 'notes']);
        $this->type = 'new';
        $this->resetValidation();
        unset($this->patientMatches, $this->chosenPatient);
    }

    public function choosePatient(int $id): void
    {
        $this->patient_id = Patient::forDoctor(ClinicContext::id())->findOrFail($id)->id;
        $this->patient_search = '';
        unset($this->patientMatches, $this->chosenPatient);
    }

    public function changePatient(): void
    {
        $this->patient_id = null;
        unset($this->patientMatches, $this->chosenPatient);
    }

    public function saveAppointment(): void
    {
        $data = $this->validate([
            'patient_id' => ['required', 'integer'],
            'appt_date' => ['required', 'date_format:Y-m-d'],
            'appt_time' => ['required', 'date_format:H:i'],
            'type' => ['required', 'in:'.implode(',', array_keys(Appointment::TYPES))],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'reason' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'patient_id.required' => 'Please choose the patient.',
            'appt_date.required' => 'Please choose a date.',
            'appt_time.required' => 'Please choose a time.',
        ]);

        $doctor = ClinicContext::doctor();

        abort_unless(Patient::forDoctor($doctor->id)->whereKey($data['patient_id'])->exists(), 404);

        $start = Carbon::createFromFormat('Y-m-d H:i', $data['appt_date'].' '.$data['appt_time']);
        $end = $start->copy()->addMinutes($data['duration_minutes']);

        // The doctor's schedule: open day, inside working hours
        $hours = $doctor->hours()[strtolower(substr($start->englishDayOfWeek, 0, 3))];

        if (! $hours['open']) {
            $this->addError('appt_date', 'The doctor is not available on '.$start->englishDayOfWeek.'s.');

            return;
        }

        if ($data['appt_time'] < $hours['from'] || $end->format('H:i') > $hours['to'] || $end->format('Y-m-d') !== $start->format('Y-m-d')) {
            $this->addError('appt_time', "Working hours that day are {$hours['from']} to {$hours['to']}.");

            return;
        }

        if ($start->isPast() && ! $this->editingId && $start->lt(now()->subMinutes(5)) && ! $start->isToday()) {
            $this->addError('appt_date', 'Please choose today or a future date.');

            return;
        }

        $clash = Appointment::forDoctor($doctor->id)
            ->whereIn('status', ['scheduled', 'checked_in', 'in_consultation'])
            ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->onDate($start)
            ->get()
            ->first(fn ($a) => $a->scheduled_at < $end && $a->scheduled_at->copy()->addMinutes($a->duration_minutes) > $start);

        if ($clash) {
            $this->addError('appt_time', 'That time overlaps another appointment ('.$clash->scheduled_at->format('h:i A').'). Please choose a different time.');

            return;
        }

        $payload = [
            'patient_id' => $data['patient_id'],
            'scheduled_at' => $start,
            'duration_minutes' => $data['duration_minutes'],
            'type' => $data['type'],
            'reason' => $data['reason'] ?: null,
            'notes' => $data['notes'] ?: null,
        ];

        if ($this->editingId) {
            Appointment::forDoctor($doctor->id)->findOrFail($this->editingId)->update($payload);
            $this->status = 'Appointment updated.';
        } else {
            Appointment::create($payload + ['doctor_id' => $doctor->id, 'status' => 'scheduled']);
            $this->status = 'Appointment booked for '.$start->format('d M, h:i A').'.';
        }

        $this->goTo($start->format('Y-m-d'));
        $this->closeModal();
        $this->refreshData();
    }

    /* ---------- status changes ---------- */

    protected function find(int $id): Appointment
    {
        return Appointment::forDoctor(ClinicContext::id())->findOrFail($id);
    }

    public function checkIn(int $id): void
    {
        $appointment = $this->find($id);

        if ($appointment->status !== 'scheduled') {
            return;
        }

        DB::transaction(function () use ($appointment) {
            $token = (int) Appointment::forDoctor($appointment->doctor_id)->onDate($appointment->scheduled_at)->max('token_no') + 1;
            $appointment->update(['status' => 'checked_in', 'checked_in_at' => now(), 'token_no' => $token]);
        });

        $this->status = "{$appointment->patient->name} checked in (token {$appointment->token_no}).";
        $this->refreshData();
    }

    public function startConsultation(int $id): void
    {
        abort_unless(auth('admin')->user()->can('manage consultations'), 403);

        $appointment = $this->find($id);

        if (! in_array($appointment->status, ['checked_in', 'in_consultation', 'scheduled'], true)) {
            return;
        }

        if ($appointment->status === 'scheduled') {
            $appointment->update(['status' => 'checked_in', 'checked_in_at' => now(), 'token_no' => (int) Appointment::forDoctor($appointment->doctor_id)->onDate($appointment->scheduled_at)->max('token_no') + 1]);
        }

        $appointment->update(['status' => 'in_consultation']);

        $consultation = Consultation::firstOrCreate(
            ['appointment_id' => $appointment->id],
            ['doctor_id' => $appointment->doctor_id, 'patient_id' => $appointment->patient_id, 'consulted_at' => now(), 'chief_complaint' => $appointment->reason]
        );

        $this->redirectRoute('admin.clinic.consultations.edit', $consultation, navigate: true);
    }

    public function askCancel(int $id): void
    {
        $this->cancellingId = $this->find($id)->id;
        $this->cancel_reason = '';
    }

    public function closeCancel(): void
    {
        $this->cancellingId = null;
        $this->cancel_reason = '';
    }

    public function confirmCancel(): void
    {
        $this->validate(['cancel_reason' => ['nullable', 'string', 'max:200']]);

        $appointment = $this->find((int) $this->cancellingId);

        if ($appointment->isOpen()) {
            $appointment->update(['status' => 'cancelled', 'cancel_reason' => $this->cancel_reason ?: null]);
            $this->status = 'Appointment cancelled.';
        }

        $this->closeCancel();
        $this->refreshData();
    }

    public function markNoShow(int $id): void
    {
        $appointment = $this->find($id);

        if ($appointment->status === 'scheduled') {
            $appointment->update(['status' => 'no_show']);
            $this->status = 'Marked as no-show.';
            $this->refreshData();
        }
    }
}
?>

<div wire:poll.20s>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Appointments</h2>
    </x-slot:header>

    @php
        $day = $this->selectedDay();
        $doctor = $this->doctor;
        $dayHours = $doctor->hours()[strtolower(substr($day->englishDayOfWeek, 0, 3))];
        $canConsult = auth('admin')->user()->can('manage consultations');
    @endphp

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[300px_1fr]">
        {{-- Calendar --}}
        <div class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                @php
                    $monthStart = \Carbon\Carbon::parse($month);
                    $gridStart = $monthStart->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
                    $counts = $this->monthCounts;
                @endphp
                <div class="mb-3 flex items-center justify-between">
                    <button type="button" wire:click="shiftMonth(-1)" class="rounded p-1 text-gray-500 hover:bg-gray-100" aria-label="Previous month">&lsaquo;</button>
                    <span class="text-sm font-semibold text-gray-900">{{ $monthStart->format('F Y') }}</span>
                    <button type="button" wire:click="shiftMonth(1)" class="rounded p-1 text-gray-500 hover:bg-gray-100" aria-label="Next month">&rsaquo;</button>
                </div>
                <div class="grid grid-cols-7 gap-1 text-center text-[11px] text-gray-400">
                    @foreach (['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'] as $d) <span>{{ $d }}</span> @endforeach
                </div>
                <div class="mt-1 grid grid-cols-7 gap-1">
                    @for ($i = 0; $i < 42; $i++)
                        @php
                            $cell = $gridStart->copy()->addDays($i);
                            $inMonth = $cell->month === $monthStart->month;
                            $key = $cell->format('Y-m-d');
                            $selected = $key === $day->format('Y-m-d');
                            $closed = ! $doctor->isOpenOn($cell);
                        @endphp
                        <button type="button" wire:click="goTo('{{ $key }}')" wire:key="cal-{{ $key }}"
                            class="relative flex h-9 flex-col items-center justify-center rounded-md text-xs {{ $selected ? 'bg-indigo-600 font-semibold text-white' : ($inMonth ? ($closed ? 'text-gray-300 hover:bg-gray-50' : 'text-gray-800 hover:bg-gray-100') : 'text-gray-300 hover:bg-gray-50') }} {{ $cell->isToday() && ! $selected ? 'ring-1 ring-indigo-400' : '' }}">
                            {{ $cell->day }}
                            @if (($counts[$key] ?? 0) > 0)
                                <span class="absolute bottom-0.5 h-1 w-1 rounded-full {{ $selected ? 'bg-white' : 'bg-indigo-500' }}"></span>
                            @endif
                        </button>
                    @endfor
                </div>
                <button type="button" wire:click="goTo('{{ today()->format('Y-m-d') }}')" class="mt-3 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Today</button>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm">
                <p class="font-semibold text-gray-900">{{ $doctor->name }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ $day->format('l') }}:
                    @if ($dayHours['open']) {{ \Carbon\Carbon::createFromFormat('H:i', $dayHours['from'])->format('h:i A') }} &ndash; {{ \Carbon\Carbon::createFromFormat('H:i', $dayHours['to'])->format('h:i A') }}
                    @else <span class="text-red-500">Closed</span> @endif
                </p>
                <p class="mt-1 text-xs text-gray-400">{{ $doctor->slot_minutes }} minute slots</p>
            </div>
        </div>

        <div class="space-y-6">
            {{-- Day header --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="shiftDay(-1)" class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-gray-600 hover:bg-gray-50" aria-label="Previous day">&lsaquo;</button>
                    <h3 class="text-base font-semibold text-gray-900">{{ $day->format('l, d F Y') }}@if ($day->isToday()) <span class="ml-1 rounded-full bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700">Today</span>@endif</h3>
                    <button type="button" wire:click="shiftDay(1)" class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-gray-600 hover:bg-gray-50" aria-label="Next day">&rsaquo;</button>
                </div>
                <button type="button" wire:click="openCreate" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Book appointment</button>
            </div>

            {{-- Waiting queue --}}
            <section class="rounded-xl border border-gray-200 bg-white">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                    <h4 class="text-sm font-semibold text-gray-900">Waiting queue</h4>
                    <span class="text-xs text-gray-400">{{ $this->queue->where('status', 'checked_in')->count() }} waiting</span>
                </div>
                <ul class="divide-y divide-gray-100">
                    @forelse ($this->queue as $q)
                        <li wire:key="queue-{{ $q->id }}" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-50 text-sm font-semibold text-indigo-700">{{ $q->token_no }}</span>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $q->patient->name }}</p>
                                    <p class="text-xs text-gray-400">Booked {{ $q->scheduled_at->format('h:i A') }} &middot; arrived {{ $q->checked_in_at?->format('h:i A') }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $q->badgeClasses() }}">{{ $q->statusLabel() }}</span>
                                @if ($canConsult)
                                    <button type="button" wire:click="startConsultation({{ $q->id }})" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">{{ $q->status === 'in_consultation' ? 'Continue' : 'Start consultation' }}</button>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="px-4 py-6 text-center text-sm text-gray-400">Nobody is waiting. Patients appear here after they are checked in.</li>
                    @endforelse
                </ul>
            </section>

            {{-- All appointments of the day --}}
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <div class="border-b border-gray-200 px-4 py-3"><h4 class="text-sm font-semibold text-gray-900">Schedule ({{ $this->appointments->count() }})</h4></div>
                <ul class="divide-y divide-gray-100">
                    @forelse ($this->appointments as $a)
                        <li wire:key="appt-{{ $a->id }}" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 {{ in_array($a->status, ['cancelled', 'no_show']) ? 'opacity-60' : '' }}">
                            <div class="flex items-center gap-4">
                                <span class="w-20 text-sm font-semibold text-gray-800">{{ $a->scheduled_at->format('h:i A') }}</span>
                                <div>
                                    <a href="{{ route('admin.clinic.patients.show', $a->patient_id) }}" wire:navigate class="text-sm font-medium text-gray-900 hover:text-indigo-700">{{ $a->patient->name }}</a>
                                    <p class="text-xs text-gray-400">{{ \App\Models\Appointment::TYPES[$a->type] ?? '' }}@if ($a->reason) &middot; {{ $a->reason }}@endif @if ($a->cancel_reason) &middot; {{ $a->cancel_reason }}@endif</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="rounded-full px-2 py-0.5 font-medium {{ $a->badgeClasses() }}">{{ $a->statusLabel() }}@if ($a->token_no) &middot; #{{ $a->token_no }}@endif</span>
                                @if ($a->status === 'scheduled')
                                    <button type="button" wire:click="checkIn({{ $a->id }})" class="rounded border border-indigo-200 bg-indigo-50 px-2 py-1 font-medium text-indigo-700 hover:bg-indigo-100">Check in</button>
                                @endif
                                @if ($a->isOpen())
                                    <button type="button" wire:click="openEdit({{ $a->id }})" class="rounded border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-50">Edit</button>
                                    @if ($a->status === 'scheduled')
                                        <button type="button" wire:click="markNoShow({{ $a->id }})" class="rounded border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-50">No show</button>
                                    @endif
                                    <button type="button" wire:click="askCancel({{ $a->id }})" class="rounded border border-red-200 px-2 py-1 text-red-600 hover:bg-red-50">Cancel</button>
                                @elseif ($a->status === 'completed' && $a->consultation && auth('admin')->user()->can('view medical records'))
                                    <a href="{{ route('admin.clinic.consultations.edit', $a->consultation) }}" wire:navigate class="rounded border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-50">Open consultation</a>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="px-4 py-10 text-center text-sm text-gray-400">{{ $dayHours['open'] ? 'No appointments on this day.' : 'The doctor is not working on this day.' }}</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>

    {{-- Book / edit --}}
    @if ($showModal)
        <div class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-black/40 px-4 py-8" wire:key="appt-modal">
            <form wire:submit="saveAppointment" class="w-full max-w-lg space-y-4 rounded-xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit appointment' : 'Book appointment' }}</h3>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Patient *</label>
                    @if ($this->chosenPatient)
                        <div class="mt-1 flex items-center justify-between rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm">
                            <span><span class="font-medium text-gray-900">{{ $this->chosenPatient->name }}</span> <span class="text-gray-400">{{ $this->chosenPatient->uhid }} &middot; {{ $this->chosenPatient->phone }}</span></span>
                            @unless ($editingId) <button type="button" wire:click="changePatient" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Change</button> @endunless
                        </div>
                    @else
                        <input wire:model.live.debounce.250ms="patient_search" type="search" placeholder="Search by name, phone or number..." autofocus class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @if ($this->patientMatches->isNotEmpty())
                            <ul class="mt-1 divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200">
                                @foreach ($this->patientMatches as $m)
                                    <li wire:key="match-{{ $m->id }}"><button type="button" wire:click="choosePatient({{ $m->id }})" class="block w-full px-3 py-2 text-left text-sm hover:bg-indigo-50"><span class="font-medium text-gray-900">{{ $m->name }}</span> <span class="text-gray-400">{{ $m->uhid }} &middot; {{ $m->phone }}</span></button></li>
                                @endforeach
                            </ul>
                        @elseif (trim($patient_search) !== '')
                            <p class="mt-1 text-xs text-gray-500">No patient found. <a href="{{ route('admin.clinic.patients.index') }}" wire:navigate class="text-indigo-600 hover:text-indigo-800">Register a new patient</a> first.</p>
                        @endif
                    @endif
                    @error('patient_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Date *</label>
                        <input wire:model="appt_date" type="date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('appt_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Time *</label>
                        <input wire:model="appt_time" type="time" step="{{ max(60, $doctor->slot_minutes * 60) }}" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('appt_time') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Visit type</label>
                        <select wire:model="type" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @foreach (\App\Models\Appointment::TYPES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Length (minutes)</label>
                        <input wire:model="duration_minutes" type="number" min="5" max="480" step="5" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('duration_minutes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Reason for visit</label>
                    <input wire:model="reason" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('reason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea wire:model="notes" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                    @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Close</button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ $editingId ? 'Save changes' : 'Book' }}</button>
                </div>
            </form>
        </div>
    @endif

    {{-- Cancel --}}
    @if ($cancellingId)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 px-4" wire:key="cancel-modal">
            <form wire:submit="confirmCancel" class="w-full max-w-md space-y-4 rounded-xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900">Cancel appointment</h3>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Reason (optional)</label>
                    <input wire:model="cancel_reason" type="text" autofocus class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('cancel_reason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeCancel" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Keep it</button>
                    <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500">Cancel appointment</button>
                </div>
            </form>
        </div>
    @endif
</div>
