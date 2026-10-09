<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FaqGroup extends Model
{
    public const PLACEMENTS = [
        'faq_page' => 'FAQs page (tabs)',
        'home' => 'Home page FAQ block',
    ];

    protected $fillable = ['title', 'placement', 'icon', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FaqItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activeItems(): HasMany
    {
        return $this->items()->where('is_active', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopePlacedOn(Builder $query, string $placement): Builder
    {
        return $query->where('placement', $placement);
    }

    public function iconUrl(): ?string
    {
        return $this->icon ? asset($this->icon) : null;
    }
}
