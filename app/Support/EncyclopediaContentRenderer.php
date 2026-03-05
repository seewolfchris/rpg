<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class EncyclopediaContentRenderer
{
    public function render(string $content): HtmlString
    {
        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $content));

        if ($normalized === '') {
            return new HtmlString('<p class="text-stone-400 italic">Kein Inhalt.</p>');
        }

        $html = (string) Str::markdown($normalized, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);

        return new HtmlString($html);
    }
}

