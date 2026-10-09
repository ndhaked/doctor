<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    public const STATUSES = [
        'scheduled' => 'Scheduled',
        'checked_in' => 'Waiting',
        'in_consultation' => 'In consultation',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No show',
    ];

    public const TYPES = ['new' => 'New visit', 'follow_up' => 'Follow-up'];

    protected $fillable = [
        'doctor_id', 'patient_id', 'scheduled_at', 'duration_minutes', 'type', 'status', 'token_no',
        'checked_in_at', 'reason', 'notes', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'checked_in_at' => 'datetime', 'duration_minutes' => 'integer', 'token_no' => 'integer'];
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    /** Tailwind classes for the status badge. */
    public function badgeClasses(): string
    {
        return match ($this->status) {
            'scheduled' => 'bg-sky-50 text-sky-700',
            'checked_in' => 'bg-amber-50 text-amber-700',
            'in_consultation' => 'bg-indigo-50 text-indigo-700',
            'completed' => 'bg-green-50 text-green-700',
            'cancelled' => 'bg-gray-100 text-gray-500',
            default => 'bg-red-50 text-red-600',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['scheduled', 'checked_in', 'in_consultation'], true);
    }

    public function scopeForDoctor(Builder $query, int $doctorId): Builder
    {
        return $query->where('doctor_id', $doctorId);
    }

    public function scopeOnDate(Builder $query, CarbonInterface $date): Builder
    {
        return $query->whereBetween('scheduled_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function consultation(): HasOne
    {
        return $this->hasOne(Consultation::class);
    }
}
