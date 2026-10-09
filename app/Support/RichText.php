<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Tiny, safe formatter for editor-written text. Everything is escaped first; only these markers are converted:
 *
 *   **bold**                                        -> bold text
 *   [label](https://example.com) or [label](/page)  -> link
 *   line break                                      -> <br>
 */
class RichText
{
    public static function inline(string $text): HtmlString
    {
        $text = e($text);
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
        $text = preg_replace(
            '/\[([^\]]+)\]\((https?:\/\/[^\s)]+|\/[^\s)]*)\)/',
            '<a href="$2" class="font-bold text-emerald-950 underline">$1</a>',
            $text
        );

        return new HtmlString(nl2br($text, false));
    }

    /**
     * Split text into paragraphs (blank line between them), each formatted with inline().
     *
     * @return array<int, HtmlString>
     */
    public static function paragraphs(string $text): array
    {
        $parts = array_filter(array_map('trim', preg_split('/\R{2,}/', $text)), fn ($p) => $p !== '');

        return array_values(array_map(fn ($p) => self::inline($p), $parts));
    }

    /**
     * One entry per non-empty line.
     *
     * @return array<int, string>
     */
    public static function lines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', $text)), fn ($l) => $l !== ''));
    }
}
