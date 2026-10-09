<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Symptom extends Model
{
    /** Default texts for the section heading (editable in the admin; stored in `settings`). */
    public const SECTION_DEFAULTS = [
        'symptoms.eyebrow' => 'Symptoms',
        'symptoms.heading' => 'Symptoms That You Should Visit General Physician In Jaipur!',
        'symptoms.intro' => 'Some common signs that may indicate the need to see a physician in Jaipur include:',
    ];

    protected $fillable = ['title', 'description', 'icon', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function iconUrl(): ?string
    {
        return $this->icon ? asset($this->icon) : null;
    }

    /** @return array{eyebrow: string, heading: string, intro: string} */
    public static function sectionText(): array
    {
        $values = Setting::many(self::SECTION_DEFAULTS);

        return [
            'eyebrow' => $values['symptoms.eyebrow'],
            'heading' => $values['symptoms.heading'],
            'intro' => $values['symptoms.intro'],
        ];
    }
}
