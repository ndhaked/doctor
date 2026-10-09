<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    protected $fillable = ['title', 'slug', 'excerpt', 'content', 'author', 'image', 'published_at', 'is_active'];

    protected function casts(): array
    {
        return [
            'published_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Newest first. */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function url(): string
    {
        return route('health-tips.show', $this);
    }

    public function imageUrl(): ?string
    {
        return $this->image ? asset($this->image) : null;
    }

    /**
     * Turn the article text into safe HTML. Everything is escaped first, then a small set of
     * markers is converted, so admins can format without being able to inject markup:
     *
     *   ## Heading        -> large heading
     *   ### Sub-heading   -> bold sub-heading
     *   - item            -> bullet list (consecutive lines starting with "- ")
     *   **bold**          -> bold text
     *   [label](https://example.com) or [label](/page) -> link
     *   blank line        -> new paragraph
     */
    public function formattedContent(): HtmlString
    {
        $arrow = '<svg class="mt-[5px] h-3.5 w-3.5 shrink-0 text-emerald-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25L21 12m0 0l-3.75 3.75M21 12H3"/></svg>';

        $inline = function (string $text): string {
            $text = e($text);
            $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);

            return preg_replace(
                '/\[([^\]]+)\]\((https?:\/\/[^\s)]+|\/[^\s)]*)\)/',
                '<a href="$2" class="font-semibold text-emerald-800 underline">$1</a>',
                $text
            );
        };

        $html = '';

        foreach (preg_split('/\R{2,}/', trim((string) $this->content)) as $block) {
            $block = trim($block);

            if ($block === '') {
                continue;
            }

            $lines = preg_split('/\R/', $block);

            if (str_starts_with($block, '### ')) {
                $html .= '<h3>'.$inline(substr($lines[0], 4)).'</h3>';
                $block = trim(implode("\n", array_slice($lines, 1)));
            } elseif (str_starts_with($block, '## ')) {
                $html .= '<h2>'.$inline(substr($lines[0], 3)).'</h2>';
                $block = trim(implode("\n", array_slice($lines, 1)));
            }

            if ($block === '') {
                continue;
            }

            $lines = preg_split('/\R/', $block);

            if (collect($lines)->every(fn ($line) => str_starts_with(ltrim($line), '- '))) {
                $html .= '<ul>'.collect($lines)
                    ->map(fn ($line) => '<li>'.$arrow.'<span>'.$inline(substr(ltrim($line), 2)).'</span></li>')
                    ->implode('').'</ul>';
            } else {
                $html .= '<p>'.nl2br($inline($block), false).'</p>';
            }
        }

        return new HtmlString($html);
    }

    /** A unique slug based on the title (adds -2, -3 ... when taken). */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'post';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
