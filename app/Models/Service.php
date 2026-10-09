<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = ['title', 'description', 'image', 'icon', 'link_url', 'is_active', 'sort_order'];

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

    public function imageUrl(): ?string
    {
        return $this->image ? asset($this->image) : null;
    }

    public function href(): string
    {
        return $this->link_url ?: '#';
    }

    /** Default section texts; editable in the admin and stored in `settings`. */
    public static function sectionDefaults(): array
    {
        return [
            'services.eyebrow' => 'Services',
            'services.heading' => 'Our Main Expertise Services',
            'services.intro' => "Get advanced treatment for diabetes, hypertension, asthma, viral fever, dengue, anemia, and others by ".\App\Support\SiteSettings::brandName()." Clinic.\n(Experienced General Physician in Your City)",
        ];
    }

    /** @return array{eyebrow: string, heading: string, intro: string} */
    public static function sectionText(): array
    {
        $values = Setting::many(self::sectionDefaults());

        return [
            'eyebrow' => $values['services.eyebrow'],
            'heading' => $values['services.heading'],
            'intro' => $values['services.intro'],
        ];
    }
}
