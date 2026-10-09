@extends('clinic.print-layout')

@section('title', 'Invoice '.$invoice->number)

@section('body')
    <div class="meta">
        <span><strong>Invoice {{ $invoice->number }}</strong></span>
        <span class="muted">Date: {{ $invoice->invoice_date->format('d M Y') }}</span>
        <span><span class="badge" @if ($invoice->status !== 'paid') style="background:#fee2e2;color:#991b1b" @endif>{{ strtoupper($invoice->statusLabel()) }}</span></span>
    </div>

    <p><strong>Bill to:</strong> {{ $patient->name }} <span class="muted">({{ $patient->uhid }})</span><br><span class="muted">{{ $patient->phone }}</span></p>

    <table style="margin-top:14px">
        <thead><tr><th style="width:32px">#</th><th>Description</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Amount</th></tr></thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->description }} <span class="muted">({{ \App\Models\Invoice::CATEGORIES[$item->category] ?? $item->category }})</span></td>
                    <td class="num">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                    <td class="num">{{ $settings['currency'] }}{{ number_format($item->unit_price, 2) }}</td>
                    <td class="num">{{ $settings['currency'] }}{{ number_format($item->amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div><span>Subtotal</span><span>{{ $settings['currency'] }}{{ number_format($invoice->subtotal, 2) }}</span></div>
        @if ((float) $invoice->discount > 0) <div><span>Discount</span><span>&minus; {{ $settings['currency'] }}{{ number_format($invoice->discount, 2) }}</span></div> @endif
        <div class="big"><span>Total</span><span>{{ $settings['currency'] }}{{ number_format($invoice->total, 2) }}</span></div>
        <div><span>Paid</span><span>{{ $settings['currency'] }}{{ number_format($invoice->paid_amount, 2) }}</span></div>
        <div style="font-weight:700"><span>Balance due</span><span>{{ $settings['currency'] }}{{ number_format($invoice->due(), 2) }}</span></div>
    </div>

    @if ($invoice->payments->isNotEmpty())
        <h2>Payments received</h2>
        <table>
            <tbody>
                @foreach ($invoice->payments as $pay)
                    <tr><td>{{ $pay->paid_at->format('d M Y') }}</td><td>{{ $pay->receipt_number }}</td><td>{{ \App\Models\Payment::METHODS[$pay->method] ?? $pay->method }}</td><td class="num">{{ $settings['currency'] }}{{ number_format($pay->amount, 2) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($invoice->notes) <p class="muted" style="margin-top:14px">Note: {{ $invoice->notes }}</p> @endif

    <div class="sign"><span class="line">Authorised signatory</span></div>
@endsection

@section('footer'){{ $settings['invoice_note'] }}@endsection
