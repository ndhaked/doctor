<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    public const DAYS = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];

    protected $fillable = [
        'admin_id', 'name', 'qualification', 'specialization', 'registration_no', 'phone', 'email',
        'clinic_name', 'clinic_address', 'working_hours', 'consultation_fee', 'slot_minutes', 'signature', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'working_hours' => 'array',
            'consultation_fee' => 'decimal:2',
            'slot_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function defaultWorkingHours(): array
    {
        $hours = [];

        foreach (array_keys(self::DAYS) as $day) {
            $hours[$day] = ['open' => $day !== 'sun', 'from' => '09:00', 'to' => '17:00'];
        }

        return $hours;
    }

    /** Working hours with every day present, so screens never have to guess. */
    public function hours(): array
    {
        return array_replace_recursive(self::defaultWorkingHours(), $this->working_hours ?? []);
    }

    public function isOpenOn(CarbonInterface $date): bool
    {
        return (bool) ($this->hours()[strtolower(substr($date->englishDayOfWeek, 0, 3))]['open'] ?? false);
    }

    public function signatureUrl(): ?string
    {
        return $this->signature ? asset($this->signature) : null;
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function consultationFields(): HasMany
    {
        return $this->hasMany(ConsultationField::class)->orderBy('sort_order')->orderBy('id');
    }
}
