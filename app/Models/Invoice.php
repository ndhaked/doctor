<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public const CATEGORIES = ['consultation' => 'Consultation', 'treatment' => 'Treatment', 'procedure' => 'Procedure', 'lab' => 'Lab / Test', 'other' => 'Other'];

    public const STATUSES = ['unpaid' => 'Unpaid', 'partial' => 'Partly paid', 'paid' => 'Paid', 'cancelled' => 'Cancelled'];

    protected $fillable = [
        'doctor_id', 'patient_id', 'consultation_id', 'appointment_id', 'number', 'invoice_date',
        'subtotal', 'discount', 'total', 'paid_amount', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date:Y-m-d',
            'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'total' => 'decimal:2', 'paid_amount' => 'decimal:2',
        ];
    }

    public function due(): float
    {
        return $this->status === 'cancelled' ? 0.0 : max(0.0, round((float) $this->total - (float) $this->paid_amount, 2));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function badgeClasses(): string
    {
        return match ($this->status) {
            'paid' => 'bg-green-50 text-green-700',
            'partial' => 'bg-amber-50 text-amber-700',
            'cancelled' => 'bg-gray-100 text-gray-500',
            default => 'bg-red-50 text-red-600',
        };
    }

    /** Recalculates the totals and the paid status from the items and payments. */
    public function recalculate(): void
    {
        $subtotal = round((float) $this->items()->sum('amount'), 2);
        $discount = min((float) $this->discount, $subtotal);
        $total = round($subtotal - $discount, 2);
        $paid = round((float) $this->payments()->sum('amount'), 2);

        $status = $this->status === 'cancelled' ? 'cancelled' : match (true) {
            $total > 0 && $paid >= $total => 'paid',
            $paid > 0 => 'partial',
            default => 'unpaid',
        };

        $this->forceFill(['subtotal' => $subtotal, 'discount' => $discount, 'total' => $total, 'paid_amount' => $paid, 'status' => $status])->save();
    }

    public function scopeForDoctor(Builder $query, int $doctorId): Builder
    {
        return $query->where('doctor_id', $doctorId);
    }

    public function scopeOwing(Builder $query): Builder
    {
        return $query->whereIn('status', ['unpaid', 'partial']);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_at');
    }
}
