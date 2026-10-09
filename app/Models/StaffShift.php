<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffShift extends Model
{
    protected $fillable = ['staff_id', 'shift_date', 'starts_at', 'ends_at', 'note'];

    protected function casts(): array
    {
        return ['shift_date' => 'date:Y-m-d'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function timeRange(): string
    {
        return substr($this->starts_at, 0, 5).' – '.substr($this->ends_at, 0, 5);
    }
}
