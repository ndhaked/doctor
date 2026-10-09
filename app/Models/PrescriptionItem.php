<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrescriptionItem extends Model
{
    protected $fillable = ['consultation_id', 'medicine', 'dosage', 'frequency', 'duration', 'instructions', 'sort_order'];
}
