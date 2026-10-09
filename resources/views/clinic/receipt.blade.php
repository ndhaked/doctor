@extends('clinic.print-layout')

@section('title', 'Receipt '.$payment->receipt_number)

@section('body')
    <div class="meta">
        <span><strong>Payment receipt {{ $payment->receipt_number }}</strong></span>
        <span class="muted">Date: {{ $payment->paid_at->format('d M Y, h:i A') }}</span>
    </div>

    <p style="margin-top:18px">Received with thanks from <strong>{{ $patient->name }}</strong> <span class="muted">({{ $patient->uhid }})</span> the sum of</p>
    <p style="font-size:28px;font-weight:700;margin:6px 0">{{ $settings['currency'] }}{{ number_format($payment->amount, 2) }}</p>

    <table style="margin-top:16px">
        <tbody>
            <tr><td class="muted" style="width:180px">Towards invoice</td><td>{{ $invoice->number }} ({{ $invoice->invoice_date->format('d M Y') }})</td></tr>
            <tr><td class="muted">Payment method</td><td>{{ \App\Models\Payment::METHODS[$payment->method] ?? $payment->method }}</td></tr>
            @if ($payment->reference) <tr><td class="muted">Reference</td><td>{{ $payment->reference }}</td></tr> @endif
            <tr><td class="muted">Invoice total</td><td>{{ $settings['currency'] }}{{ number_format($invoice->total, 2) }}</td></tr>
            <tr><td class="muted">Balance due after this payment</td><td><strong>{{ $settings['currency'] }}{{ number_format($invoice->due(), 2) }}</strong></td></tr>
        </tbody>
    </table>

    <div class="sign"><span class="line">Received by</span></div>
@endsection

@section('footer'){{ $settings['invoice_note'] }}@endsection
