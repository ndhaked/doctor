<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HeroSlide extends Model
{
    /** Icons the admin can pick for the quick cards (names understood by <x-icon>). */
    public const ICONS = [
        'droplet' => 'Droplet',
        'heart' => 'Heart',
        'chart' => 'Chart',
        'thermometer' => 'Thermometer',
        'badge-check' => 'Check badge',
        'sparkles' => 'Sparkles',
        'bolt' => 'Bolt',
        'lungs' => 'Lungs',
        'bug' => 'Bug',
        'user-doctor' => 'Doctor',
        'calendar' => 'Calendar',
        'phone' => 'Phone',
        'mail' => 'Mail',
        'star' => 'Star',
        'building' => 'Building',
    ];

    public const CTA_ACTIONS = [
        'appointment' => 'Open appointment popup',
        'contact' => 'Go to Contact Us page',
        'custom' => 'Custom link',
    ];

    public const MAX_CARDS = 4;

    protected $fillable = [
        'image', 'doctor_image', 'doctor_name',
        'badge', 'title', 'credentials', 'specialty', 'description',
        'cards', 'cta_text', 'cta_action', 'cta_url',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'cards' => 'array',
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

    public function imageUrl(): string
    {
        return asset($this->image);
    }

    public function doctorImageUrl(): ?string
    {
        return $this->doctor_image ? asset($this->doctor_image) : null;
    }

    /** Where the call-to-action button should point. */
    public function ctaHref(): string
    {
        return match ($this->cta_action) {
            'contact' => route('contact'),
            'custom' => $this->cta_url ?: '#',
            default => '#appointment',
        };
    }

    public function ctaOpensPopup(): bool
    {
        return $this->cta_action === 'appointment';
    }
}
