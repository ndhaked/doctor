<?php

use App\Models\Consultation;
use App\Models\PrescriptionItem;
use App\Support\ClinicContext;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component
{
    public Consultation $consultation;

    public string $chief_complaint = '';
    public string $symptoms = '';
    public array $vitals = [];
    public string $examination = '';
    public string $diagnosis = '';
    public string $treatment = '';
    public string $notes = '';
    public array $custom = [];
    public string $tests = '';
    public string $advice = '';
    public string $follow_up_date = '';
    public array $items = [];

    public ?string $status = null;

    public function mount(Consultation $consultation): void
    {
        abort_unless($consultation->doctor_id === ClinicContext::id(), 404);

        $this->consultation = $consultation;

        foreach (['chief_complaint', 'symptoms', 'examination', 'diagnosis', 'treatment', 'notes', 'tests', 'advice'] as $field) {
            $this->$field = (string) $consultation->$field;
        }

        $this->follow_up_date = $consultation->follow_up_date?->format('Y-m-d') ?? '';

        foreach (array_keys(Consultation::VITALS) as $key) {
            $this->vitals[$key] = (string) ($consultation->vitals[$key] ?? '');
        }

        foreach ($this->fields as $field) {
            $value = $consultation->custom_values[$field->id] ?? ($field->type === 'checkbox' ? false : '');
            $this->custom[$field->id] = $field->type === 'checkbox' ? (bool) $value : (string) $value;
        }

        $this->items = $consultation->items->map(fn ($i) => $i->only(['medicine', 'dosage', 'frequency', 'duration', 'instructions']))->all();

        if ($this->items === []) {
            $this->addItem();
        }
    }

    #[Computed]
    public function canEdit(): bool
    {
        return auth('admin')->user()->can('manage consultations');
    }

    #[Computed]
    public function fields()
    {
        return ClinicContext::doctor()->consultationFields()->active()->get();
    }

    #[Computed]
    public function patient()
    {
        return $this->consultation->patient;
    }

    #[Computed]
    public function history()
    {
        return Consultation::forDoctor(ClinicContext::id())
            ->where('patient_id', $this->consultation->patient_id)
            ->where('id', '!=', $this->consultation->id)
            ->latest('consulted_at')->limit(3)->get();
    }

    /** Medicines used before, offered as suggestions while typing. */
    #[Computed]
    public function medicineSuggestions()
    {
        return PrescriptionItem::query()
            ->whereIn('consultation_id', Consultation::forDoctor(ClinicContext::id())->select('id'))
            ->select('medicine')->distinct()->orderBy('medicine')->limit(200)->pluck('medicine');
    }

    public function addItem(): void
    {
        $this->items[] = ['medicine' => '', 'dosage' => '', 'frequency' => '', 'duration' => '', 'instructions' => ''];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);

        if ($this->items === []) {
            $this->addItem();
        }
    }

    protected function rules(): array
    {
        $rules = [
            'chief_complaint' => ['nullable', 'string', 'max:2000'],
            'symptoms' => ['nullable', 'string', 'max:3000'],
            'examination' => ['nullable', 'string', 'max:3000'],
            'diagnosis' => ['nullable', 'string', 'max:2000'],
            'treatment' => ['nullable', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'tests' => ['nullable', 'string', 'max:2000'],
            'advice' => ['nullable', 'string', 'max:2000'],
            'follow_up_date' => ['nullable', 'date', 'after_or_equal:'.$this->consultation->consulted_at->format('Y-m-d')],
            'vitals.*' => ['nullable', 'string', 'max:30'],
            'items' => ['array', 'max:40'],
            'items.*.medicine' => ['nullable', 'string', 'max:150'],
            'items.*.dosage' => ['nullable', 'string', 'max:80'],
            'items.*.frequency' => ['nullable', 'string', 'max:80'],
            'items.*.duration' => ['nullable', 'string', 'max:80'],
            'items.*.instructions' => ['nullable', 'string', 'max:200'],
        ];

        foreach ($this->fields as $field) {
            $rules["custom.{$field->id}"] = match ($field->type) {
                'number' => ['nullable', 'numeric'],
                'date' => ['nullable', 'date'],
                'select' => ['nullable', 'in:'.implode(',', $field->choices())],
                'checkbox' => ['boolean'],
                'textarea' => ['nullable', 'string', 'max:3000'],
                default => ['nullable', 'string', 'max:500'],
            };
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'follow_up_date.after_or_equal' => 'The follow-up date cannot be before the consultation date.',
            'custom.*.numeric' => 'Please enter a number.',
            'custom.*.in' => 'Please choose one of the listed options.',
        ];
    }

    protected function persist(string $status): void
    {
        abort_unless($this->canEdit, 403);

        $this->validate();

        DB::transaction(function () use ($status) {
            $custom = [];

            foreach ($this->fields as $field) {
                $custom[$field->id] = $this->custom[$field->id] ?? null;
            }

            $this->consultation->update([
                'chief_complaint' => $this->chief_complaint ?: null,
                'symptoms' => $this->symptoms ?: null,
                'vitals' => array_filter($this->vitals, fn ($v) => $v !== '' && $v !== null),
                'examination' => $this->examination ?: null,
                'diagnosis' => $this->diagnosis ?: null,
                'treatment' => $this->treatment ?: null,
                'notes' => $this->notes ?: null,
                'custom_values' => $custom,
                'tests' => $this->tests ?: null,
                'advice' => $this->advice ?: null,
                'follow_up_date' => $this->follow_up_date ?: null,
                'status' => $status,
            ]);

            $this->consultation->items()->delete();

            foreach (collect($this->items)->filter(fn ($i) => trim($i['medicine'] ?? '') !== '')->values() as $order => $item) {
                $this->consultation->items()->create([
                    'medicine' => trim($item['medicine']),
                    'dosage' => $item['dosage'] ?: null,
                    'frequency' => $item['frequency'] ?: null,
                    'duration' => $item['duration'] ?: null,
                    'instructions' => $item['instructions'] ?: null,
                    'sort_order' => $order + 1,
                ]);
            }

            $appointment = $this->consultation->appointment;

            if ($appointment && $appointment->isOpen()) {
                $appointment->update(['status' => $status === 'final' ? 'completed' : 'in_consultation']);
            } elseif ($appointment && $appointment->status === 'completed' && $status === 'draft') {
                $appointment->update(['status' => 'in_consultation']);
            }
        });

        $this->consultation->refresh();
        unset($this->history, $this->medicineSuggestions);
    }

    public function saveDraft(): void
    {
        $this->persist('draft');
        $this->status = 'Draft saved.';
    }

    public function finalize(): void
    {
        abort_unless($this->canEdit, 403);

        if (trim($this->chief_complaint) === '' && trim($this->diagnosis) === '') {
            $this->addError('diagnosis', 'Please enter the chief complaint or the diagnosis before finishing.');

            return;
        }

        $this->persist('final');
        $this->status = 'Consultation completed.';
    }

    public function reopen(): void
    {
        $this->persist('draft');
        $this->status = 'Consultation reopened for editing.';
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Consultation</h2>
    </x-slot:header>

    @php
        $patient = $this->patient;
        $final = $consultation->isFinal();
        $edit = $this->canEdit;
        $off = ! $edit ? 'disabled' : '';
        $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-50 disabled:text-gray-500';
    @endphp

    <div class="mb-4"><a href="{{ route('admin.clinic.patients.show', ['patient' => $patient, 'tab' => 'consultations']) }}" wire:navigate class="text-sm text-indigo-600 hover:text-indigo-800">&larr; {{ $patient->name }}</a></div>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    {{-- Patient banner --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3 rounded-xl border border-gray-200 bg-white p-5">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">{{ $patient->name }}</h3>
            <p class="text-sm text-gray-500">{{ $patient->uhid }}@if ($patient->age() !== null) &middot; {{ $patient->age() }} yrs @endif @if ($patient->gender) &middot; {{ \App\Models\Patient::GENDERS[$patient->gender] }} @endif &middot; {{ $consultation->consulted_at->format('d M Y, h:i A') }}</p>
            @if ($patient->allergies) <p class="mt-2 inline-block rounded bg-red-50 px-2 py-1 text-xs font-medium text-red-700">Allergies: {{ $patient->allergies }}</p> @endif
            @if ($patient->medical_history) <p class="mt-2 max-w-3xl text-xs text-gray-500"><span class="font-medium text-gray-600">History:</span> {{ \Illuminate\Support\Str::limit($patient->medical_history, 220) }}</p> @endif
        </div>
        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $final ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' }}">{{ $final ? 'Completed' : 'In progress' }}</span>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_280px]">
        <form wire:submit.prevent class="space-y-6">
            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
                <h4 class="text-sm font-semibold text-gray-900">History</h4>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Chief complaint</label>
                    <textarea wire:model="chief_complaint" rows="2" {{ $off }} class="{{ $inputClass }}"></textarea>
                    @error('chief_complaint') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Symptoms</label>
                    <textarea wire:model="symptoms" rows="2" {{ $off }} class="{{ $inputClass }}"></textarea>
                    @error('symptoms') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </section>

            <section class="space-y-3 rounded-xl border border-gray-200 bg-white p-5">
                <h4 class="text-sm font-semibold text-gray-900">Vitals</h4>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach (\App\Models\Consultation::VITALS as $key => $label)
                        <div>
                            <label class="block text-xs font-medium text-gray-600">{{ $label }}</label>
                            <input wire:model="vitals.{{ $key }}" type="text" {{ $off }} class="{{ $inputClass }}">
                            @error("vitals.$key") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
                <h4 class="text-sm font-semibold text-gray-900">Examination &amp; diagnosis</h4>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Examination</label>
                    <textarea wire:model="examination" rows="3" {{ $off }} class="{{ $inputClass }}"></textarea>
                    @error('examination') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Diagnosis</label>
                    <textarea wire:model="diagnosis" rows="2" {{ $off }} class="{{ $inputClass }}"></textarea>
                    @error('diagnosis') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Treatment</label>
                    <textarea wire:model="treatment" rows="3" {{ $off }} placeholder="Procedures done, treatment plan" class="{{ $inputClass }}"></textarea>
                    @error('treatment') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Notes <span class="font-normal text-gray-400">(private, not printed)</span></label>
                    <textarea wire:model="notes" rows="2" {{ $off }} class="{{ $inputClass }}"></textarea>
                    @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </section>

            {{-- The doctor's own fields --}}
            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-gray-900">Specialty fields</h4>
                    @if (auth('admin')->user()->can('manage clinic settings'))
                        <a href="{{ route('admin.clinic.settings') }}#fields" wire:navigate class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Add or change fields</a>
                    @endif
                </div>
                @forelse ($this->fields as $field)
                    <div wire:key="field-{{ $field->id }}">
                        @if ($field->type === 'checkbox')
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" wire:model="custom.{{ $field->id }}" {{ $off }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"> {{ $field->label }}
                            </label>
                        @else
                            <label class="block text-sm font-medium text-gray-700">{{ $field->label }}</label>
                            @if ($field->type === 'textarea')
                                <textarea wire:model="custom.{{ $field->id }}" rows="3" {{ $off }} class="{{ $inputClass }}"></textarea>
                            @elseif ($field->type === 'select')
                                <select wire:model="custom.{{ $field->id }}" {{ $off }} class="{{ $inputClass }}">
                                    <option value="">Select&hellip;</option>
                                    @foreach ($field->choices() as $choice) <option value="{{ $choice }}">{{ $choice }}</option> @endforeach
                                </select>
                            @else
                                <input wire:model="custom.{{ $field->id }}" type="{{ $field->type === 'number' ? 'number' : ($field->type === 'date' ? 'date' : 'text') }}" step="any" {{ $off }} class="{{ $inputClass }}">
                            @endif
                        @endif
                        @error("custom.{$field->id}") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No specialty fields yet. Add fields such as &ldquo;Tooth number&rdquo;, &ldquo;ECG findings&rdquo; or &ldquo;Skin type&rdquo; in Clinic Settings, and they will appear here on every consultation.</p>
                @endforelse
            </section>

            {{-- Prescription --}}
            <section class="space-y-3 rounded-xl border border-gray-200 bg-white p-5">
                <h4 class="text-sm font-semibold text-gray-900">Prescription</h4>
                <datalist id="medicine-list">@foreach ($this->medicineSuggestions as $m) <option value="{{ $m }}"> @endforeach</datalist>

                @foreach ($items as $i => $item)
                    <div wire:key="item-{{ $i }}" class="rounded-lg border border-gray-200 p-3">
                        <div class="grid gap-2 sm:grid-cols-[2fr_1fr_1fr_1fr]">
                            <div>
                                <label class="block text-xs font-medium text-gray-600">Medicine</label>
                                <input wire:model="items.{{ $i }}.medicine" list="medicine-list" type="text" {{ $off }} class="{{ $inputClass }}">
                                @error("items.$i.medicine") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600">Dosage</label>
                                <input wire:model="items.{{ $i }}.dosage" type="text" placeholder="500 mg" {{ $off }} class="{{ $inputClass }}">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600">Frequency</label>
                                <input wire:model="items.{{ $i }}.frequency" type="text" placeholder="1-0-1" {{ $off }} class="{{ $inputClass }}">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600">Duration</label>
                                <input wire:model="items.{{ $i }}.duration" type="text" placeholder="5 days" {{ $off }} class="{{ $inputClass }}">
                            </div>
                        </div>
                        <div class="mt-2 flex items-end gap-2">
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-gray-600">Instructions</label>
                                <input wire:model="items.{{ $i }}.instructions" type="text" placeholder="After food" {{ $off }} class="{{ $inputClass }}">
                            </div>
                            @if ($edit)
                                <button type="button" wire:click="removeItem({{ $i }})" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-medium text-red-600 hover:bg-red-50">Remove</button>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if ($edit)
                    <button type="button" wire:click="addItem" class="rounded-lg border border-dashed border-gray-300 px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50">+ Add medicine</button>
                @endif

                <div class="grid gap-4 pt-2 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tests advised</label>
                        <textarea wire:model="tests" rows="2" {{ $off }} class="{{ $inputClass }}"></textarea>
                        @error('tests') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Advice</label>
                        <textarea wire:model="advice" rows="2" {{ $off }} class="{{ $inputClass }}"></textarea>
                        @error('advice') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="max-w-xs">
                    <label class="block text-sm font-medium text-gray-700">Follow-up date</label>
                    <input wire:model="follow_up_date" type="date" {{ $off }} class="{{ $inputClass }}">
                    @error('follow_up_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </section>

            @if ($edit)
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" wire:click="saveDraft" wire:loading.attr="disabled" class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">Save draft</button>
                    @if ($final)
                        <button type="button" wire:click="reopen" class="rounded-lg border border-amber-300 bg-amber-50 px-5 py-2.5 text-sm font-semibold text-amber-700 hover:bg-amber-100">Reopen to edit</button>
                    @endif
                    <button type="button" wire:click="finalize" wire:loading.attr="disabled" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ $final ? 'Save & keep completed' : 'Complete consultation' }}</button>
                </div>
            @endif
        </form>

        {{-- Side panel --}}
        <aside class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm">
                <h4 class="mb-2 font-semibold text-gray-900">Next steps</h4>
                <div class="space-y-2">
                    <a href="{{ route('admin.clinic.consultations.prescription', $consultation) }}" target="_blank" class="block rounded-lg border border-gray-300 px-3 py-2 text-center font-medium text-gray-700 hover:bg-gray-50">Print prescription</a>
                    @if (auth('admin')->user()->can('manage billing'))
                        @if ($consultation->invoice)
                            <a href="{{ route('admin.clinic.billing.show', $consultation->invoice) }}" wire:navigate class="block rounded-lg border border-gray-300 px-3 py-2 text-center font-medium text-gray-700 hover:bg-gray-50">View invoice {{ $consultation->invoice->number }}</a>
                        @else
                            <a href="{{ route('admin.clinic.billing.create', ['patient' => $patient->id, 'consultation' => $consultation->id]) }}" wire:navigate class="block rounded-lg border border-gray-300 px-3 py-2 text-center font-medium text-gray-700 hover:bg-gray-50">Create invoice</a>
                        @endif
                    @endif
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm">
                <h4 class="mb-2 font-semibold text-gray-900">Previous visits</h4>
                @forelse ($this->history as $h)
                    <a href="{{ route('admin.clinic.consultations.edit', $h) }}" wire:navigate wire:key="hist-{{ $h->id }}" class="block border-t border-gray-100 py-2 first:border-0 hover:text-indigo-700">
                        <p class="font-medium text-gray-800">{{ $h->consulted_at->format('d M Y') }}</p>
                        <p class="text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($h->diagnosis ?: $h->chief_complaint ?: 'No details', 70) }}</p>
                    </a>
                @empty
                    <p class="text-xs text-gray-400">This is the first recorded visit.</p>
                @endforelse
            </div>
        </aside>
    </div>
</div>
