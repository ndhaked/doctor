<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAttendance extends Model
{
    protected $table = 'staff_attendance';

    public const STATUSES = ['present' => 'Present', 'half_day' => 'Half day', 'leave' => 'On leave', 'absent' => 'Absent'];

    protected $fillable = ['staff_id', 'work_date', 'status', 'check_in', 'check_out', 'note'];

    protected function casts(): array
    {
        return ['work_date' => 'date:Y-m-d'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
