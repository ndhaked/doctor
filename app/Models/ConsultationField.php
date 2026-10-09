<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ConsultationField extends Model
{
    public const TYPES = ['text' => 'Short text', 'textarea' => 'Long text', 'number' => 'Number', 'date' => 'Date', 'select' => 'Choice list', 'checkbox' => 'Yes / No'];

    protected $fillable = ['doctor_id', 'label', 'type', 'options', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function choices(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->options))));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
