<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    protected $table = 'staff';

    protected $fillable = ['doctor_id', 'staff_role_id', 'admin_id', 'name', 'phone', 'email', 'address', 'joined_on', 'monthly_salary', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['joined_on' => 'date:Y-m-d', 'monthly_salary' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function scopeForDoctor(Builder $query, int $doctorId): Builder
    {
        return $query->where('doctor_id', $doctorId);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(StaffRole::class, 'staff_role_id');
    }

    public function patients(): BelongsToMany
    {
        return $this->belongsToMany(Patient::class, 'staff_patient')->withPivot('note')->withTimestamps()->withTrashed();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(StaffTask::class)->orderByRaw("status = 'done'")->orderBy('due_date')->orderByDesc('id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(StaffShift::class)->orderByDesc('shift_date')->orderBy('starts_at');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(StaffAttendance::class)->orderByDesc('work_date');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(StaffPayment::class)->orderByDesc('paid_on')->orderByDesc('id');
    }
}
