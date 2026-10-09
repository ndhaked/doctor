@extends('clinic.print-layout')

@section('title', 'Prescription - '.$patient->name)

@section('body')
    <div class="meta">
        <span><strong>{{ $patient->name }}</strong>@if ($patient->age() !== null), {{ $patient->age() }} yrs @endif @if ($patient->gender) / {{ \App\Models\Patient::GENDERS[$patient->gender] }} @endif</span>
        <span class="muted">Patient no: {{ $patient->uhid }}</span>
        <span class="muted">Date: {{ $consultation->consulted_at->format('d M Y') }}</span>
    </div>

    @if ($patient->allergies) <p style="color:#b91c1c"><strong>Allergies:</strong> {{ $patient->allergies }}</p> @endif

    @if ($consultation->chief_complaint) <h2>Chief complaint</h2><p class="pre">{{ $consultation->chief_complaint }}</p> @endif
    @if ($consultation->symptoms) <h2>Symptoms</h2><p class="pre">{{ $consultation->symptoms }}</p> @endif

    @php $vitals = collect($consultation->vitals ?? [])->filter(); @endphp
    @if ($vitals->isNotEmpty())
        <h2>Vitals</h2>
        <p>@foreach ($vitals as $key => $value){{ \App\Models\Consultation::VITALS[$key] ?? $key }}: <strong>{{ $value }}</strong>@if (! $loop->last) &nbsp;&nbsp;|&nbsp;&nbsp; @endif @endforeach</p>
    @endif

    @if ($consultation->examination) <h2>Examination</h2><p class="pre">{{ $consultation->examination }}</p> @endif

    @foreach ($customFields as $field)
        @php $value = $consultation->custom_values[$field->id]; @endphp
        <h2>{{ $field->label }}</h2><p class="pre">{{ $field->type === 'checkbox' ? 'Yes' : $value }}</p>
    @endforeach

    @if ($consultation->diagnosis) <h2>Diagnosis</h2><p class="pre"><strong>{{ $consultation->diagnosis }}</strong></p> @endif
    @if ($consultation->treatment) <h2>Treatment</h2><p class="pre">{{ $consultation->treatment }}</p> @endif

    @if ($consultation->items->isNotEmpty())
        <h2>Rx</h2>
        <table>
            <thead><tr><th style="width:32px">#</th><th>Medicine</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Instructions</th></tr></thead>
            <tbody>
                @foreach ($consultation->items as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td><strong>{{ $item->medicine }}</strong></td>
                        <td>{{ $item->dosage }}</td>
                        <td>{{ $item->frequency }}</td>
                        <td>{{ $item->duration }}</td>
                        <td>{{ $item->instructions }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($consultation->tests) <h2>Tests advised</h2><p class="pre">{{ $consultation->tests }}</p> @endif
    @if ($consultation->advice) <h2>Advice</h2><p class="pre">{{ $consultation->advice }}</p> @endif
    @if ($consultation->follow_up_date) <p style="margin-top:14px"><strong>Follow-up on {{ $consultation->follow_up_date->format('d M Y') }}</strong></p> @endif

    <div class="sign">
        @if ($doctor->signatureUrl()) <img src="{{ $doctor->signatureUrl() }}" alt="Signature"><br> @endif
        <span class="line">{{ $doctor->name }}</span>
    </div>
@endsection

@section('footer'){{ $settings['rx_note'] }}@endsection
