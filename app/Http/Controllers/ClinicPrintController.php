<?php

namespace App\Http\Controllers;

use App\Models\Consultation;
use App\Models\ConsultationField;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PatientDocument;
use App\Support\ClinicContext;
use App\Support\ClinicSettings;
use Illuminate\Support\Facades\Storage;

/** Printable pages and protected file downloads of the clinic panel. Every record must belong to the current doctor. */
class ClinicPrintController extends Controller
{
    public function prescription(Consultation $consultation)
    {
        abort_unless($consultation->doctor_id === ClinicContext::id(), 404);

        $consultation->load(['patient', 'doctor', 'items']);

        $fields = ConsultationField::where('doctor_id', $consultation->doctor_id)->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn ($f) => filled($consultation->custom_values[$f->id] ?? null));

        return view('clinic.prescription', [
            'consultation' => $consultation,
            'doctor' => $consultation->doctor,
            'patient' => $consultation->patient,
            'customFields' => $fields,
            'settings' => ClinicSettings::content(),
        ]);
    }

    public function invoice(Invoice $invoice)
    {
        abort_unless($invoice->doctor_id === ClinicContext::id(), 404);

        $invoice->load(['patient', 'doctor', 'items', 'payments']);

        return view('clinic.invoice', ['invoice' => $invoice, 'doctor' => $invoice->doctor, 'patient' => $invoice->patient, 'settings' => ClinicSettings::content()]);
    }

    public function receipt(Payment $payment)
    {
        $payment->load(['invoice.doctor', 'patient']);
        abort_unless($payment->invoice->doctor_id === ClinicContext::id(), 404);

        return view('clinic.receipt', ['payment' => $payment, 'invoice' => $payment->invoice, 'doctor' => $payment->invoice->doctor, 'patient' => $payment->patient, 'settings' => ClinicSettings::content()]);
    }

    public function document(PatientDocument $document)
    {
        abort_unless($document->patient->doctor_id === ClinicContext::id(), 404);
        abort_unless(Storage::disk('local')->exists($document->file), 404);

        return Storage::disk('local')->response($document->file, basename($document->file), [
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
