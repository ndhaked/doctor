<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GalleryVideo extends Model
{
    protected $fillable = ['title', 'description', 'youtube_url', 'youtube_id', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Returns the 11-character YouTube video id from a youtube.com / youtu.be link
     * (watch, shorts, live, embed or short links), or null when it is not a YouTube video link.
     */
    public static function extractYoutubeId(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '' || ! preg_match('~^https?://~i', $url)) {
            return null;
        }

        $parts = parse_url($url);
        $host = strtolower(preg_replace('/^(www|m|music)\./i', '', $parts['host'] ?? ''));
        $path = $parts['path'] ?? '';
        $id = null;

        if ($host === 'youtu.be') {
            $id = trim($path, '/');
        } elseif (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
            if (preg_match('~^/(?:shorts|live|embed|v)/([^/?#]+)~', $path, $m)) {
                $id = $m[1];
            } elseif (rtrim($path, '/') === '/watch') {
                parse_str($parts['query'] ?? '', $query);
                $id = $query['v'] ?? null;
            }
        }

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : null;
    }

    public function thumbnailUrl(): string
    {
        return 'https://img.youtube.com/vi/'.$this->youtube_id.'/hqdefault.jpg';
    }

    /** Player address; autoplay is only used after a visitor clicks the thumbnail. */
    public function embedUrl(): string
    {
        return 'https://www.youtube-nocookie.com/embed/'.$this->youtube_id.'?autoplay=1&rel=0';
    }

    public function watchUrl(): string
    {
        return 'https://www.youtube.com/watch?v='.$this->youtube_id;
    }
}
