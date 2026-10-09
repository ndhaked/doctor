<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A report or scan of a patient. The file is kept on the private "local" disk and served only to permitted staff. */
class PatientDocument extends Model
{
    protected $fillable = ['patient_id', 'title', 'file', 'uploaded_by'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function url(): string
    {
        return route('admin.clinic.documents.show', $this);
    }
}
