<?php

use App\Models\Patient;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffPayment;
use App\Support\ClinicContext;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component
{
    public Staff $staff;

    #[Url(as: 'tab', except: 'tasks')]
    public string $tab = 'tasks';

    // Patients
    public string $patient_search = '';
    public string $assign_note = '';

    // Tasks
    public string $task_title = '';
    public string $task_details = '';
    public string $task_due = '';
    public ?int $task_patient_id = null;

    // Attendance (month shown + day being marked)
    public string $month = '';
    public string $att_date = '';
    public string $att_status = 'present';
    public string $att_in = '';
    public string $att_out = '';
    public string $att_note = '';

    // Shifts
    public string $shift_date = '';
    public string $shift_from = '09:00';
    public string $shift_to = '17:00';
    public string $shift_note = '';

    // Payments
    public string $pay_type = 'salary';
    public string $pay_period = '';
    public string $pay_amount = '';
    public string $pay_date = '';
    public string $pay_method = 'cash';
    public string $pay_note = '';

    public ?string $status = null;

    public const TABS = ['tasks' => 'Tasks', 'patients' => 'Patients', 'attendance' => 'Attendance', 'shifts' => 'Shifts', 'payments' => 'Salary & payments'];

    public function mount(Staff $staff): void
    {
        abort_unless($staff->doctor_id === ClinicContext::id(), 404);

        $this->staff = $staff;
        $this->month = today()->startOfMonth()->format('Y-m-d');
        $this->att_date = today()->format('Y-m-d');
        $this->shift_date = today()->format('Y-m-d');
        $this->pay_date = today()->format('Y-m-d');
        $this->pay_period = today()->format('Y-m');
        $this->pay_amount = (string) (float) $staff->monthly_salary ?: '';

        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'tasks';
        }
    }

    public function setTab(string $tab): void
    {
        if (array_key_exists($tab, self::TABS)) {
            $this->tab = $tab;
        }
    }

    /* ---------- patients ---------- */

    #[Computed]
    public function assigned()
    {
        return $this->staff->patients()->orderBy('name')->get();
    }

    #[Computed]
    public function patientMatches()
    {
        if (trim($this->patient_search) === '') {
            return collect();
        }

        return Patient::forDoctor($this->staff->doctor_id)->search($this->patient_search)
            ->whereNotIn('id', $this->staff->patients()->withTrashed()->pluck('patients.id'))->orderBy('name')->limit(6)->get();
    }

    public function assignPatient(int $id): void
    {
        $patient = Patient::forDoctor($this->staff->doctor_id)->findOrFail($id);
        $this->staff->patients()->syncWithoutDetaching([$patient->id => ['note' => $this->assign_note ?: null]]);

        $this->reset(['patient_search', 'assign_note']);
        $this->status = "{$patient->name} assigned.";
        unset($this->assigned, $this->patientMatches);
    }

    public function unassignPatient(int $id): void
    {
        $this->staff->patients()->detach($id);
        $this->status = 'Patient unassigned.';
        unset($this->assigned, $this->patientMatches);
    }

    /* ---------- tasks ---------- */

    #[Computed]
    public function tasks()
    {
        return $this->staff->tasks()->with('patient')->get();
    }

    #[Computed]
    public function taskPatients()
    {
        return $this->assigned;
    }

    public function addTask(): void
    {
        $data = $this->validate([
            'task_title' => ['required', 'string', 'max:200'],
            'task_details' => ['nullable', 'string', 'max:1000'],
            'task_due' => ['nullable', 'date'],
            'task_patient_id' => ['nullable', 'integer'],
        ], ['task_title.required' => 'Describe the task.']);

        if ($data['task_patient_id'] && ! Patient::forDoctor($this->staff->doctor_id)->whereKey($data['task_patient_id'])->exists()) {
            abort(404);
        }

        $this->staff->tasks()->create([
            'title' => $data['task_title'],
            'details' => $data['task_details'] ?: null,
            'due_date' => $data['task_due'] ?: null,
            'patient_id' => $data['task_patient_id'] ?: null,
        ]);

        $this->reset(['task_title', 'task_details', 'task_due', 'task_patient_id']);
        $this->status = 'Task added.';
        unset($this->tasks);
    }

    public function toggleTask(int $id): void
    {
        $task = $this->staff->tasks()->findOrFail($id);
        $done = ! $task->isDone();
        $task->update(['status' => $done ? 'done' : 'pending', 'completed_at' => $done ? now() : null]);

        unset($this->tasks);
    }

    public function deleteTask(int $id): void
    {
        $this->staff->tasks()->findOrFail($id)->delete();
        unset($this->tasks);
    }

    /* ---------- attendance ---------- */

    public function shiftMonth(int $by): void
    {
        $this->month = Carbon::parse($this->month)->addMonthsNoOverflow($by)->startOfMonth()->format('Y-m-d');
        unset($this->attendance);
    }

    #[Computed]
    public function attendance()
    {
        $start = Carbon::parse($this->month)->startOfMonth();

        return $this->staff->attendance()->whereBetween('work_date', [$start, $start->copy()->endOfMonth()])->get()->keyBy(fn ($a) => $a->work_date->format('Y-m-d'));
    }

    public function markAttendance(): void
    {
        $data = $this->validate([
            'att_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'att_status' => ['required', 'in:'.implode(',', array_keys(StaffAttendance::STATUSES))],
            'att_in' => ['nullable', 'date_format:H:i'],
            'att_out' => ['nullable', 'date_format:H:i', 'after:att_in'],
            'att_note' => ['nullable', 'string', 'max:200'],
        ], [
            'att_date.before_or_equal' => 'Attendance can only be marked up to today.',
            'att_out.after' => 'Check-out must be after check-in.',
        ]);

        $values = ['status' => $data['att_status'], 'check_in' => $data['att_in'] ?: null, 'check_out' => $data['att_out'] ?: null, 'note' => $data['att_note'] ?: null];
        $record = $this->staff->attendance()->whereDate('work_date', $data['att_date'])->first();

        $record ? $record->update($values) : $this->staff->attendance()->create(['work_date' => $data['att_date']] + $values);

        $this->reset(['att_in', 'att_out', 'att_note']);
        $this->month = Carbon::parse($data['att_date'])->startOfMonth()->format('Y-m-d');
        $this->status = 'Attendance saved for '.Carbon::parse($data['att_date'])->format('d M').'.';
        unset($this->attendance);
    }

    public function clearAttendance(string $date): void
    {
        $this->staff->attendance()->whereDate('work_date', $date)->delete();
        unset($this->attendance);
    }

    /* ---------- shifts ---------- */

    #[Computed]
    public function shifts()
    {
        return $this->staff->shifts()->whereDate('shift_date', '>=', today()->subDays(7))->get();
    }

    public function addShift(): void
    {
        $data = $this->validate([
            'shift_date' => ['required', 'date_format:Y-m-d'],
            'shift_from' => ['required', 'date_format:H:i'],
            'shift_to' => ['required', 'date_format:H:i', 'after:shift_from'],
            'shift_note' => ['nullable', 'string', 'max:200'],
        ], ['shift_to.after' => 'The shift must end after it starts.']);

        $clash = $this->staff->shifts()->whereDate('shift_date', $data['shift_date'])
            ->where('starts_at', '<', $data['shift_to'].':00')->where('ends_at', '>', $data['shift_from'].':00')->exists();

        if ($clash) {
            $this->addError('shift_from', 'This overlaps another shift on the same day.');

            return;
        }

        $this->staff->shifts()->create([
            'shift_date' => $data['shift_date'], 'starts_at' => $data['shift_from'], 'ends_at' => $data['shift_to'], 'note' => $data['shift_note'] ?: null,
        ]);

        $this->reset('shift_note');
        $this->status = 'Shift added.';
        unset($this->shifts);
    }

    public function deleteShift(int $id): void
    {
        $this->staff->shifts()->findOrFail($id)->delete();
        unset($this->shifts);
    }

    /* ---------- payments ---------- */

    #[Computed]
    public function payments()
    {
        return $this->staff->payments()->limit(60)->get();
    }

    public function addPayment(): void
    {
        $data = $this->validate([
            'pay_type' => ['required', 'in:'.implode(',', array_keys(StaffPayment::TYPES))],
            'pay_period' => ['nullable', 'date_format:Y-m'],
            'pay_amount' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'pay_date' => ['required', 'date_format:Y-m-d'],
            'pay_method' => ['required', 'in:cash,upi,card,bank'],
            'pay_note' => ['nullable', 'string', 'max:200'],
        ], ['pay_amount.required' => 'Enter the amount paid.', 'pay_amount.gt' => 'The amount must be more than zero.']);

        $this->staff->payments()->create([
            'type' => $data['pay_type'], 'period' => $data['pay_period'] ?: null, 'amount' => $data['pay_amount'],
            'paid_on' => $data['pay_date'], 'method' => $data['pay_method'], 'note' => $data['pay_note'] ?: null,
        ]);

        $this->reset('pay_note');
        $this->status = 'Payment recorded.';
        unset($this->payments);
    }

    public function deletePayment(int $id): void
    {
        $this->staff->payments()->findOrFail($id)->delete();
        unset($this->payments);
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Staff member</h2>
    </x-slot:header>

    @php
        $s = $staff;
        $money = fn ($v) => \App\Support\ClinicSettings::money($v);
        $input = 'mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm';
    @endphp

    <div class="mb-4"><a href="{{ route('admin.clinic.staff.index') }}" wire:navigate class="text-sm text-indigo-600 hover:text-indigo-800">&larr; All staff</a></div>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    <div class="mb-6 rounded-xl border border-gray-200 bg-white p-5">
        <h3 class="text-xl font-semibold text-gray-900">{{ $s->name }} @unless ($s->is_active) <span class="ml-2 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">Inactive</span> @endunless</h3>
        <p class="mt-1 text-sm text-gray-500">{{ $s->role?->name ?? 'No role' }}@if ($s->joined_on) &middot; joined {{ $s->joined_on->format('d M Y') }} @endif &middot; salary {{ $money($s->monthly_salary) }}/month</p>
        <p class="mt-1 text-sm text-gray-600">{{ $s->phone }}@if ($s->phone && $s->email) &middot; @endif{{ $s->email }}</p>
    </div>

    <div class="mb-4 flex flex-wrap gap-1 border-b border-gray-200">
        @foreach (['tasks' => 'Tasks', 'patients' => 'Patients', 'attendance' => 'Attendance', 'shifts' => 'Shifts', 'payments' => 'Salary & payments'] as $key => $label)
            <button type="button" wire:click="setTab('{{ $key }}')" class="-mb-px border-b-2 px-4 py-2 text-sm font-medium {{ $tab === $key ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-800' }}">{{ $label }}</button>
        @endforeach
    </div>

    {{-- Tasks --}}
    @if ($tab === 'tasks')
        <form wire:submit="addTask" class="mb-4 grid gap-3 rounded-xl border border-gray-200 bg-white p-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">New task</label>
                <input wire:model="task_title" type="text" placeholder="e.g. Change dressing for bed 3" class="{{ $input }}">
                @error('task_title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Due date</label>
                <input wire:model="task_due" type="date" class="{{ $input }}">
                @error('task_due') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">For patient (assigned patients)</label>
                <select wire:model="task_patient_id" class="{{ $input }}">
                    <option value="">None</option>
                    @foreach ($this->taskPatients as $p) <option value="{{ $p->id }}">{{ $p->name }}</option> @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Details</label>
                <input wire:model="task_details" type="text" class="{{ $input }}">
                @error('task_details') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2"><button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add task</button></div>
        </form>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <ul class="divide-y divide-gray-100 text-sm">
                @forelse ($this->tasks as $t)
                    <li wire:key="task-{{ $t->id }}" class="flex items-start justify-between gap-3 px-4 py-3">
                        <label class="flex items-start gap-3">
                            <input type="checkbox" wire:click="toggleTask({{ $t->id }})" @checked($t->isDone()) class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span>
                                <span class="font-medium {{ $t->isDone() ? 'text-gray-400 line-through' : 'text-gray-900' }}">{{ $t->title }}</span>
                                <span class="block text-xs text-gray-400">
                                    @if ($t->patient) {{ $t->patient->name }} &middot; @endif
                                    @if ($t->due_date) <span class="{{ $t->isOverdue() ? 'text-red-600' : '' }}">Due {{ $t->due_date->format('d M') }}</span> @endif
                                    @if ($t->details) &middot; {{ $t->details }} @endif
                                </span>
                            </span>
                        </label>
                        <button type="button" wire:click="deleteTask({{ $t->id }})" wire:confirm="Delete this task?" class="text-xs font-medium text-red-600 hover:text-red-800">Delete</button>
                    </li>
                @empty
                    <li class="px-4 py-10 text-center text-gray-400">No tasks yet.</li>
                @endforelse
            </ul>
        </div>
    @endif

    {{-- Patients --}}
    @if ($tab === 'patients')
        <div class="mb-4 rounded-xl border border-gray-200 bg-white p-5">
            <label class="block text-sm font-medium text-gray-700">Assign a patient</label>
            <input wire:model.live.debounce.250ms="patient_search" type="search" placeholder="Search by name, phone or number..." class="{{ $input }}">
            @if ($this->patientMatches->isNotEmpty())
                <ul class="mt-2 divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200">
                    @foreach ($this->patientMatches as $m)
                        <li wire:key="match-{{ $m->id }}"><button type="button" wire:click="assignPatient({{ $m->id }})" class="block w-full px-3 py-2 text-left text-sm hover:bg-indigo-50"><span class="font-medium text-gray-900">{{ $m->name }}</span> <span class="text-gray-400">{{ $m->uhid }} &middot; {{ $m->phone }}</span></button></li>
                    @endforeach
                </ul>
            @endif
            <div class="mt-3"><input wire:model="assign_note" type="text" placeholder="Care note for this assignment (optional)" class="{{ $input }}"></div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <ul class="divide-y divide-gray-100 text-sm">
                @forelse ($this->assigned as $p)
                    <li wire:key="asg-{{ $p->id }}" class="flex items-center justify-between gap-3 px-4 py-3">
                        <div>
                            <a href="{{ route('admin.clinic.patients.show', $p->id) }}" wire:navigate class="font-medium text-gray-900 hover:text-indigo-700">{{ $p->name }}</a>
                            <p class="text-xs text-gray-400">{{ $p->uhid }}@if ($p->pivot->note) &middot; {{ $p->pivot->note }} @endif</p>
                        </div>
                        <button type="button" wire:click="unassignPatient({{ $p->id }})" wire:confirm="Unassign this patient?" class="text-xs font-medium text-red-600 hover:text-red-800">Unassign</button>
                    </li>
                @empty
                    <li class="px-4 py-10 text-center text-gray-400">No patients assigned.</li>
                @endforelse
            </ul>
        </div>
    @endif

    {{-- Attendance --}}
    @if ($tab === 'attendance')
        @php
            $monthStart = \Carbon\Carbon::parse($month);
            $records = $this->attendance;
            $summary = collect(\App\Models\StaffAttendance::STATUSES)->map(fn ($l, $k) => $records->where('status', $k)->count());
        @endphp
        <div class="grid gap-6 lg:grid-cols-[320px_1fr]">
            <form wire:submit="markAttendance" class="space-y-3 rounded-xl border border-gray-200 bg-white p-5">
                <h4 class="text-sm font-semibold text-gray-900">Mark attendance</h4>
                <div>
                    <label class="block text-xs font-medium text-gray-600">Date</label>
                    <input wire:model="att_date" type="date" max="{{ today()->format('Y-m-d') }}" class="{{ $input }}">
                    @error('att_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">Status</label>
                    <select wire:model="att_status" class="{{ $input }}">
                        @foreach (\App\Models\StaffAttendance::STATUSES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-xs font-medium text-gray-600">In</label><input wire:model="att_in" type="time" class="{{ $input }}">@error('att_in') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
                    <div><label class="block text-xs font-medium text-gray-600">Out</label><input wire:model="att_out" type="time" class="{{ $input }}">@error('att_out') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
                </div>
                <div><label class="block text-xs font-medium text-gray-600">Note</label><input wire:model="att_note" type="text" class="{{ $input }}"></div>
                <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Save</button>
            </form>

            <div class="rounded-xl border border-gray-200 bg-white">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                    <button type="button" wire:click="shiftMonth(-1)" class="rounded p-1 text-gray-500 hover:bg-gray-100" aria-label="Previous month">&lsaquo;</button>
                    <div class="text-center">
                        <p class="text-sm font-semibold text-gray-900">{{ $monthStart->format('F Y') }}</p>
                        <p class="text-xs text-gray-400">@foreach (\App\Models\StaffAttendance::STATUSES as $k => $l) {{ $l }}: {{ $summary[$k] }}@if (! $loop->last) &middot; @endif @endforeach</p>
                    </div>
                    <button type="button" wire:click="shiftMonth(1)" class="rounded p-1 text-gray-500 hover:bg-gray-100" aria-label="Next month">&rsaquo;</button>
                </div>
                <ul class="divide-y divide-gray-100 text-sm">
                    @forelse ($records->sortKeysDesc() as $date => $rec)
                        <li wire:key="att-{{ $date }}" class="flex items-center justify-between gap-3 px-4 py-2.5">
                            <span><span class="inline-block w-24 font-medium text-gray-800">{{ $rec->work_date->format('d M, D') }}</span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ match ($rec->status) { 'present' => 'bg-green-50 text-green-700', 'half_day' => 'bg-amber-50 text-amber-700', 'leave' => 'bg-sky-50 text-sky-700', default => 'bg-red-50 text-red-600' } }}">{{ \App\Models\StaffAttendance::STATUSES[$rec->status] }}</span>
                                @if ($rec->check_in) <span class="ml-2 text-xs text-gray-400">{{ substr($rec->check_in, 0, 5) }}@if ($rec->check_out) &ndash; {{ substr($rec->check_out, 0, 5) }}@endif</span> @endif
                                @if ($rec->note) <span class="ml-2 text-xs text-gray-400">{{ $rec->note }}</span> @endif</span>
                            <button type="button" wire:click="clearAttendance('{{ $date }}')" class="text-xs font-medium text-red-600 hover:text-red-800">Clear</button>
                        </li>
                    @empty
                        <li class="px-4 py-10 text-center text-gray-400">No attendance marked this month.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    @endif

    {{-- Shifts --}}
    @if ($tab === 'shifts')
        <form wire:submit="addShift" class="mb-4 grid gap-3 rounded-xl border border-gray-200 bg-white p-5 sm:grid-cols-4 sm:items-end">
            <div><label class="block text-xs font-medium text-gray-600">Date</label><input wire:model="shift_date" type="date" class="{{ $input }}">@error('shift_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
            <div><label class="block text-xs font-medium text-gray-600">From</label><input wire:model="shift_from" type="time" class="{{ $input }}">@error('shift_from') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
            <div><label class="block text-xs font-medium text-gray-600">To</label><input wire:model="shift_to" type="time" class="{{ $input }}">@error('shift_to') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add shift</button>
            <div class="sm:col-span-4"><input wire:model="shift_note" type="text" placeholder="Note (optional), e.g. Night duty" class="{{ $input }}"></div>
        </form>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <ul class="divide-y divide-gray-100 text-sm">
                @forelse ($this->shifts as $sh)
                    <li wire:key="shift-{{ $sh->id }}" class="flex items-center justify-between gap-3 px-4 py-3">
                        <span><span class="inline-block w-28 font-medium text-gray-800">{{ $sh->shift_date->format('d M, D') }}</span> {{ $sh->timeRange() }} @if ($sh->note) <span class="ml-2 text-xs text-gray-400">{{ $sh->note }}</span> @endif</span>
                        <button type="button" wire:click="deleteShift({{ $sh->id }})" wire:confirm="Delete this shift?" class="text-xs font-medium text-red-600 hover:text-red-800">Delete</button>
                    </li>
                @empty
                    <li class="px-4 py-10 text-center text-gray-400">No shifts planned. Shifts from the last 7 days onward are shown.</li>
                @endforelse
            </ul>
        </div>
    @endif

    {{-- Payments --}}
    @if ($tab === 'payments')
        <form wire:submit="addPayment" class="mb-4 grid gap-3 rounded-xl border border-gray-200 bg-white p-5 sm:grid-cols-3 sm:items-end">
            <div><label class="block text-xs font-medium text-gray-600">Type</label>
                <select wire:model="pay_type" class="{{ $input }}">@foreach (\App\Models\StaffPayment::TYPES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach</select></div>
            <div><label class="block text-xs font-medium text-gray-600">For month</label><input wire:model="pay_period" type="month" class="{{ $input }}">@error('pay_period') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
            <div><label class="block text-xs font-medium text-gray-600">Amount</label><input wire:model="pay_amount" type="number" min="0" step="0.01" class="{{ $input }}">@error('pay_amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
            <div><label class="block text-xs font-medium text-gray-600">Paid on</label><input wire:model="pay_date" type="date" class="{{ $input }}">@error('pay_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
            <div><label class="block text-xs font-medium text-gray-600">Method</label>
                <select wire:model="pay_method" class="{{ $input }}">@foreach (\App\Models\Payment::METHODS as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach</select></div>
            <div><label class="block text-xs font-medium text-gray-600">Note</label><input wire:model="pay_note" type="text" class="{{ $input }}"></div>
            <div class="sm:col-span-3"><button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Record payment</button></div>
        </form>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500"><tr><th class="px-4 py-3 font-medium">Paid on</th><th class="px-4 py-3 font-medium">Type</th><th class="px-4 py-3 font-medium hidden sm:table-cell">For</th><th class="px-4 py-3 font-medium text-right">Amount</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->payments as $pay)
                        <tr wire:key="spay-{{ $pay->id }}">
                            <td class="px-4 py-2.5 text-gray-800">{{ $pay->paid_on->format('d M Y') }}</td>
                            <td class="px-4 py-2.5 text-gray-600">{{ \App\Models\StaffPayment::TYPES[$pay->type] ?? $pay->type }}</td>
                            <td class="px-4 py-2.5 text-gray-600 hidden sm:table-cell">{{ $pay->period ? \Carbon\Carbon::createFromFormat('Y-m', $pay->period)->format('M Y') : '—' }}</td>
                            <td class="px-4 py-2.5 text-right font-medium text-gray-900">{{ $money($pay->amount) }}</td>
                            <td class="px-4 py-2.5 text-right"><button type="button" wire:click="deletePayment({{ $pay->id }})" wire:confirm="Delete this payment record?" class="text-xs font-medium text-red-600 hover:text-red-800">Delete</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">No payments recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
