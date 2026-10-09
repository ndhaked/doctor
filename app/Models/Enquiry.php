<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Enquiry extends Model
{
    /** Where the enquiry was sent from (form on the website). */
    public const SOURCES = [
        'appointment' => 'Book appointment section',
        'popup' => 'Appointment popup',
        'contact' => 'Contact Us page',
        'strip' => 'Home info strip',
    ];

    protected $fillable = ['source', 'name', 'phone', 'email', 'message', 'consent', 'status', 'ip', 'user_agent'];

    protected function casts(): array
    {
        return ['consent' => 'boolean'];
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('status', 'new');
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function isNew(): bool
    {
        return $this->status === 'new';
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }
}
