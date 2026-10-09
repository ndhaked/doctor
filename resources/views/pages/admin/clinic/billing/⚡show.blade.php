<?php

use App\Models\Consultation;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Support\ClinicContext;
use App\Support\ClinicSettings;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component
{
    public ?Invoice $invoice = null;

    public ?int $patient_id = null;
    public string $patient_search = '';
    public ?int $consultation_id = null;
    public string $invoice_date = '';
    public string $discount = '0';
    public string $notes = '';
    public array $items = [];

    // Payment form
    public string $pay_amount = '';
    public string $pay_method = 'cash';
    public string $pay_date = '';
    public string $pay_reference = '';

    public ?string $status = null;

    public function mount(?Invoice $invoice = null): void
    {
        $this->pay_date = now()->format('Y-m-d\TH:i');

        if ($invoice && $invoice->exists) {
            abort_unless($invoice->doctor_id === ClinicContext::id(), 404);

            $this->invoice = $invoice;
            $this->patient_id = $invoice->patient_id;
            $this->consultation_id = $invoice->consultation_id;
            $this->invoice_date = $invoice->invoice_date->format('Y-m-d');
            $this->discount = (string) (float) $invoice->discount;
            $this->notes = (string) $invoice->notes;
            $this->items = $invoice->items->map(fn ($i) => [
                'category' => $i->category, 'description' => $i->description,
                'quantity' => (string) (float) $i->quantity, 'unit_price' => (string) (float) $i->unit_price,
            ])->all();
            $this->pay_amount = $invoice->due() > 0 ? (string) $invoice->due() : '';

            return;
        }

        // New invoice, optionally started from a patient or a consultation
        $this->invoice_date = today()->format('Y-m-d');
        $doctor = ClinicContext::doctor();

        $consultation = request()->integer('consultation')
            ? Consultation::forDoctor($doctor->id)->find(request()->integer('consultation'))
            : null;

        $patientId = $consultation?->patient_id ?? request()->integer('patient');

        if ($patientId && Patient::forDoctor($doctor->id)->whereKey($patientId)->exists()) {
            $this->patient_id = $patientId;
        }

        $this->consultation_id = $consultation?->id;

        $this->items = [[
            'category' => 'consultation',
            'description' => ClinicSettings::content()['visit_fee_label'],
            'quantity' => '1',
            'unit_price' => (string) (float) $doctor->consultation_fee,
        ]];
    }

    protected function locked(): bool
    {
        return (bool) $this->invoice && $this->invoice->status === 'cancelled';
    }

    #[Computed]
    public function patient(): ?Patient
    {
        return $this->patient_id ? Patient::forDoctor(ClinicContext::id())->withTrashed()->find($this->patient_id) : null;
    }

    #[Computed]
    public function patientMatches()
    {
        if ($this->patient_id || trim($this->patient_search) === '') {
            return collect();
        }

        return Patient::forDoctor(ClinicContext::id())->search($this->patient_search)->orderBy('name')->limit(6)->get();
    }

    public function choosePatient(int $id): void
    {
        abort_if($this->invoice, 403);

        $this->patient_id = Patient::forDoctor(ClinicContext::id())->findOrFail($id)->id;
        $this->patient_search = '';
        unset($this->patient, $this->patientMatches);
    }

    public function changePatient(): void
    {
        abort_if($this->invoice, 403);

        $this->patient_id = null;
        unset($this->patient, $this->patientMatches);
    }

    public function addItem(): void
    {
        $this->items[] = ['category' => 'treatment', 'description' => '', 'quantity' => '1', 'unit_price' => '0'];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);

        if ($this->items === []) {
            $this->addItem();
        }
    }

    /** @return array{subtotal: float, discount: float, total: float} */
    public function sums(): array
    {
        $subtotal = collect($this->items)->sum(fn ($i) => round((float) ($i['quantity'] ?? 0) * (float) ($i['unit_price'] ?? 0), 2));
        $discount = min(max((float) $this->discount, 0), $subtotal);

        return ['subtotal' => round($subtotal, 2), 'discount' => round($discount, 2), 'total' => round($subtotal - $discount, 2)];
    }

    public function save(): void
    {
        abort_if($this->locked(), 403);

        $data = $this->validate([
            'patient_id' => ['required', 'integer'],
            'invoice_date' => ['required', 'date_format:Y-m-d'],
            'discount' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:60'],
            'items.*.category' => ['required', 'in:'.implode(',', array_keys(Invoice::CATEGORIES))],
            'items.*.description' => ['required', 'string', 'max:200'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ], [
            'patient_id.required' => 'Please choose the patient.',
            'items.*.description.required' => 'Describe each charge.',
            'items.*.quantity.min' => 'Quantity must be more than zero.',
            'items.*.unit_price.numeric' => 'Enter the price as a number.',
        ]);

        $doctorId = ClinicContext::id();
        abort_unless(Patient::forDoctor($doctorId)->withTrashed()->whereKey($data['patient_id'])->exists(), 404);

        $sums = $this->sums();

        if ((float) $data['discount'] > $sums['subtotal']) {
            $this->addError('discount', 'The discount cannot be more than the subtotal.');

            return;
        }

        if ($this->invoice && $sums['total'] < (float) $this->invoice->paid_amount) {
            $this->addError('discount', 'The total cannot be lower than the '.ClinicSettings::money($this->invoice->paid_amount).' already paid. Remove a payment first.');

            return;
        }

        DB::transaction(function () use ($data, $doctorId) {
            if ($this->invoice) {
                $invoice = $this->invoice;
                $invoice->update(['invoice_date' => $data['invoice_date'], 'discount' => $data['discount'], 'notes' => $data['notes'] ?: null]);
            } else {
                $invoice = Invoice::create([
                    'doctor_id' => $doctorId,
                    'patient_id' => $data['patient_id'],
                    'consultation_id' => $this->consultation_id,
                    'number' => 'TMP-'.bin2hex(random_bytes(6)),
                    'invoice_date' => $data['invoice_date'],
                    'discount' => $data['discount'],
                    'notes' => $data['notes'] ?: null,
                ]);
                $invoice->update(['number' => ClinicSettings::content()['invoice_prefix'].'-'.now()->format('Y').'-'.str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT)]);
            }

            $invoice->items()->delete();

            foreach (array_values($data['items']) as $order => $item) {
                $invoice->items()->create([
                    'category' => $item['category'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'amount' => round((float) $item['quantity'] * (float) $item['unit_price'], 2),
                    'sort_order' => $order + 1,
                ]);
            }

            $invoice->recalculate();
            $this->invoice = $invoice->fresh();
        });

        $this->redirectRoute('admin.clinic.billing.show', $this->invoice, navigate: true);
    }

    public function addPayment(): void
    {
        abort_unless($this->invoice && ! $this->locked(), 403);

        $data = $this->validate([
            'pay_amount' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'pay_method' => ['required', 'in:'.implode(',', array_keys(Payment::METHODS))],
            'pay_date' => ['required', 'date'],
            'pay_reference' => ['nullable', 'string', 'max:150'],
        ], [
            'pay_amount.required' => 'Enter the amount received.',
            'pay_amount.gt' => 'The amount must be more than zero.',
        ]);

        $invoice = $this->invoice->fresh();

        if ((float) $data['pay_amount'] > $invoice->due() + 0.001) {
            $this->addError('pay_amount', 'The amount is more than the '.ClinicSettings::money($invoice->due()).' still due.');

            return;
        }

        DB::transaction(function () use ($data, $invoice) {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'patient_id' => $invoice->patient_id,
                'receipt_number' => 'TMP-'.bin2hex(random_bytes(6)),
                'amount' => $data['pay_amount'],
                'method' => $data['pay_method'],
                'paid_at' => $data['pay_date'],
                'reference' => $data['pay_reference'] ?: null,
                'received_by' => auth('admin')->id(),
            ]);
            $payment->update(['receipt_number' => 'RCP-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT)]);

            $invoice->recalculate();
        });

        $this->invoice = $invoice->fresh();
        $this->pay_amount = $this->invoice->due() > 0 ? (string) $this->invoice->due() : '';
        $this->pay_reference = '';
        $this->pay_date = now()->format('Y-m-d\TH:i');
        $this->status = 'Payment recorded.';
    }

    public function deletePayment(int $id): void
    {
        abort_unless($this->invoice && ! $this->locked(), 403);

        $this->invoice->payments()->findOrFail($id)->delete();
        $this->invoice->recalculate();
        $this->invoice = $this->invoice->fresh();
        $this->pay_amount = $this->invoice->due() > 0 ? (string) $this->invoice->due() : '';
        $this->status = 'Payment removed.';
    }

    public function cancelInvoice(): void
    {
        abort_unless($this->invoice, 403);

        if ($this->invoice->payments()->exists()) {
            $this->addError('cancel', 'This invoice has payments. Remove them first, then cancel it.');

            return;
        }

        $this->invoice->update(['status' => 'cancelled']);
        $this->invoice->recalculate();
        $this->invoice = $this->invoice->fresh();
        $this->status = 'Invoice cancelled.';
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">{{ $invoice ? 'Invoice '.$invoice->number : 'New invoice' }}</h2>
    </x-slot:header>

    @php
        $money = fn ($v) => \App\Support\ClinicSettings::money($v);
        $sums = $this->sums();
        $locked = $invoice && $invoice->status === 'cancelled';
        $input = 'mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-50';
    @endphp

    <div class="mb-4"><a href="{{ route('admin.clinic.billing.index') }}" wire:navigate class="text-sm text-indigo-600 hover:text-indigo-800">&larr; All invoices</a></div>

    @if ($status)
        <div wire:key="status-{{ md5($status) }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition
            class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    @if ($invoice)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-5">
            <div>
                <p class="text-sm text-gray-500">{{ $invoice->patient->name }} &middot; {{ $invoice->patient->uhid }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $money($invoice->total) }}
                    <span class="ml-2 rounded-full px-2.5 py-0.5 align-middle text-xs font-medium {{ $invoice->badgeClasses() }}">{{ $invoice->statusLabel() }}</span></p>
                <p class="text-sm text-gray-500">Paid {{ $money($invoice->paid_amount) }} @if ($invoice->due() > 0) &middot; <span class="font-medium text-red-600">Due {{ $money($invoice->due()) }}</span> @endif</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.clinic.billing.print', $invoice) }}" target="_blank" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Print invoice</a>
                @unless ($locked)
                    <button type="button" wire:click="cancelInvoice" wire:confirm="Cancel this invoice?" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">Cancel invoice</button>
                @endunless
            </div>
            @error('cancel') <p class="w-full text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_340px]">
        <form wire:submit="save" class="space-y-6">
            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
                <h4 class="text-sm font-semibold text-gray-900">Patient &amp; date</h4>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Patient *</label>
                    @if ($this->patient)
                        <div class="mt-1 flex items-center justify-between rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm">
                            <span><span class="font-medium text-gray-900">{{ $this->patient->name }}</span> <span class="text-gray-400">{{ $this->patient->uhid }} &middot; {{ $this->patient->phone }}</span></span>
                            @unless ($invoice) <button type="button" wire:click="changePatient" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Change</button> @endunless
                        </div>
                    @else
                        <input wire:model.live.debounce.250ms="patient_search" type="search" placeholder="Search by name, phone or number..." class="{{ $input }}">
                        @if ($this->patientMatches->isNotEmpty())
                            <ul class="mt-1 divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200">
                                @foreach ($this->patientMatches as $m)
                                    <li wire:key="match-{{ $m->id }}"><button type="button" wire:click="choosePatient({{ $m->id }})" class="block w-full px-3 py-2 text-left text-sm hover:bg-indigo-50"><span class="font-medium text-gray-900">{{ $m->name }}</span> <span class="text-gray-400">{{ $m->uhid }} &middot; {{ $m->phone }}</span></button></li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                    @error('patient_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="max-w-xs">
                    <label class="block text-sm font-medium text-gray-700">Invoice date</label>
                    <input wire:model="invoice_date" type="date" @disabled($locked) class="{{ $input }}">
                    @error('invoice_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </section>

            <section class="space-y-3 rounded-xl border border-gray-200 bg-white p-5">
                <h4 class="text-sm font-semibold text-gray-900">Charges</h4>
                @foreach ($items as $i => $item)
                    <div wire:key="item-{{ $i }}" class="rounded-lg border border-gray-200 p-3">
                        <div class="grid gap-2 sm:grid-cols-[1fr_2fr_80px_110px]">
                            <div>
                                <label class="block text-xs font-medium text-gray-600">Type</label>
                                <select wire:model="items.{{ $i }}.category" @disabled($locked) class="{{ $input }}">
                                    @foreach (\App\Models\Invoice::CATEGORIES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600">Description</label>
                                <input wire:model="items.{{ $i }}.description" type="text" @disabled($locked) class="{{ $input }}">
                                @error("items.$i.description") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600">Qty</label>
                                <input wire:model.live.debounce.400ms="items.{{ $i }}.quantity" type="number" step="0.01" min="0" @disabled($locked) class="{{ $input }}">
                                @error("items.$i.quantity") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600">Price</label>
                                <input wire:model.live.debounce.400ms="items.{{ $i }}.unit_price" type="number" step="0.01" min="0" @disabled($locked) class="{{ $input }}">
                                @error("items.$i.unit_price") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs">
                            <span class="text-gray-500">Amount: <span class="font-semibold text-gray-800">{{ $money(round((float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0), 2)) }}</span></span>
                            @unless ($locked) <button type="button" wire:click="removeItem({{ $i }})" class="font-medium text-red-600 hover:text-red-800">Remove</button> @endunless
                        </div>
                    </div>
                @endforeach
                @error('items') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                @unless ($locked)
                    <button type="button" wire:click="addItem" class="rounded-lg border border-dashed border-gray-300 px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50">+ Add charge</button>
                @endunless

                <div class="grid gap-4 pt-2 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Discount</label>
                        <input wire:model.live.debounce.400ms="discount" type="number" step="0.01" min="0" @disabled($locked) class="{{ $input }}">
                        @error('discount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Notes</label>
                        <input wire:model="notes" type="text" @disabled($locked) class="{{ $input }}">
                        @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <dl class="space-y-1 border-t border-gray-100 pt-3 text-sm">
                    <div class="flex justify-between text-gray-600"><dt>Subtotal</dt><dd>{{ $money($sums['subtotal']) }}</dd></div>
                    <div class="flex justify-between text-gray-600"><dt>Discount</dt><dd>&minus; {{ $money($sums['discount']) }}</dd></div>
                    <div class="flex justify-between text-base font-semibold text-gray-900"><dt>Total</dt><dd>{{ $money($sums['total']) }}</dd></div>
                </dl>
            </section>

            @unless ($locked)
                <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ $invoice ? 'Save invoice' : 'Create invoice' }}</button>
            @endunless
        </form>

        {{-- Payments --}}
        <aside class="space-y-4">
            @if ($invoice)
                <section class="rounded-xl border border-gray-200 bg-white p-5">
                    <h4 class="mb-3 text-sm font-semibold text-gray-900">Payments</h4>
                    <ul class="divide-y divide-gray-100 text-sm">
                        @forelse ($invoice->payments as $pay)
                            <li wire:key="pay-{{ $pay->id }}" class="flex items-start justify-between gap-2 py-2">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $money($pay->amount) }} <span class="text-xs font-normal text-gray-400">{{ \App\Models\Payment::METHODS[$pay->method] ?? $pay->method }}</span></p>
                                    <p class="text-xs text-gray-400">{{ $pay->paid_at->format('d M Y, h:i A') }} &middot; {{ $pay->receipt_number }}</p>
                                </div>
                                <div class="space-x-2 whitespace-nowrap text-xs">
                                    <a href="{{ route('admin.clinic.payments.receipt', $pay) }}" target="_blank" class="font-medium text-indigo-600 hover:text-indigo-800">Receipt</a>
                                    @unless ($locked) <button type="button" wire:click="deletePayment({{ $pay->id }})" wire:confirm="Remove this payment?" class="font-medium text-red-600 hover:text-red-800">Remove</button> @endunless
                                </div>
                            </li>
                        @empty
                            <li class="py-2 text-xs text-gray-400">No payments yet.</li>
                        @endforelse
                    </ul>
                </section>

                @if (! $locked && $invoice->due() > 0)
                    <form wire:submit="addPayment" class="space-y-3 rounded-xl border border-gray-200 bg-white p-5">
                        <h4 class="text-sm font-semibold text-gray-900">Record payment</h4>
                        <div>
                            <label class="block text-xs font-medium text-gray-600">Amount (due {{ $money($invoice->due()) }})</label>
                            <input wire:model="pay_amount" type="number" step="0.01" min="0" class="{{ $input }}">
                            @error('pay_amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600">Method</label>
                            <select wire:model="pay_method" class="{{ $input }}">
                                @foreach (\App\Models\Payment::METHODS as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600">Date &amp; time</label>
                            <input wire:model="pay_date" type="datetime-local" class="{{ $input }}">
                            @error('pay_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600">Reference (UPI id, card slip, cheque no.)</label>
                            <input wire:model="pay_reference" type="text" class="{{ $input }}">
                            @error('pay_reference') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500">Record payment</button>
                    </form>
                @endif
            @else
                <p class="rounded-xl border border-gray-200 bg-white p-5 text-sm text-gray-500">Create the invoice first, then record payments (cash, UPI, card or bank) and print receipts.</p>
            @endif
        </aside>
    </div>
</div>
