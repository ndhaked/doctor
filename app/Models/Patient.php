<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use SoftDeletes;

    public const GENDERS = ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'];

    public const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    protected $fillable = [
        'doctor_id', 'uhid', 'name', 'gender', 'dob', 'phone', 'email', 'address', 'blood_group',
        'emergency_contact', 'allergies', 'medical_history', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['dob' => 'date:Y-m-d', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // The patient number is built from the row id, so it is unique without any counter table
        static::created(function (Patient $patient) {
            if (str_starts_with($patient->uhid, 'TMP-')) {
                $patient->forceFill(['uhid' => 'P-'.str_pad((string) $patient->id, 6, '0', STR_PAD_LEFT)])->saveQuietly();
            }
        });
    }

    /** Creates a patient and gives it its number. */
    public static function register(array $attributes): self
    {
        return self::create($attributes + ['uhid' => 'TMP-'.bin2hex(random_bytes(6))]);
    }

    public function age(): ?int
    {
        return $this->dob?->age;
    }

    public function scopeForDoctor(Builder $query, int $doctorId): Builder
    {
        return $query->where('doctor_id', $doctorId);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        return $term === '' ? $query : $query->where(fn ($q) => $q
            ->where('name', 'like', "%$term%")
            ->orWhere('phone', 'like', "%$term%")
            ->orWhere('uhid', 'like', "%$term%")
            ->orWhere('email', 'like', "%$term%"));
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PatientDocument::class)->latest();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class)->orderByDesc('scheduled_at');
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class)->orderByDesc('consulted_at');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->orderByDesc('invoice_date')->orderByDesc('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('paid_at');
    }
}
