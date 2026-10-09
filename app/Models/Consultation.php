<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Consultation extends Model
{
    /** The standard vitals; the doctor can still add anything else as a custom field. */
    public const VITALS = [
        'bp' => 'BP (mmHg)', 'pulse' => 'Pulse (/min)', 'temperature' => 'Temp (°F)', 'spo2' => 'SpO2 (%)',
        'weight' => 'Weight (kg)', 'height' => 'Height (cm)',
    ];

    protected $fillable = [
        'doctor_id', 'patient_id', 'appointment_id', 'consulted_at', 'chief_complaint', 'symptoms', 'vitals',
        'examination', 'diagnosis', 'treatment', 'notes', 'custom_values', 'tests', 'advice', 'follow_up_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'consulted_at' => 'datetime',
            'vitals' => 'array',
            'custom_values' => 'array',
            'follow_up_date' => 'date:Y-m-d',
        ];
    }

    public function isFinal(): bool
    {
        return $this->status === 'final';
    }

    public function scopeForDoctor(Builder $query, int $doctorId): Builder
    {
        return $query->where('doctor_id', $doctorId);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }
}
