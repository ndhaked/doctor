<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffPayment extends Model
{
    public const TYPES = ['salary' => 'Salary', 'bonus' => 'Bonus', 'advance' => 'Advance', 'other' => 'Other'];

    protected $fillable = ['staff_id', 'type', 'period', 'amount', 'paid_on', 'method', 'note'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_on' => 'date:Y-m-d'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
