<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class PostContentRenderer
{
    public function render(string $content, ?string $format): HtmlString
    {
        $normalizedFormat = match ($format) {
            'markdown', 'bbcode', 'plain' => $format,
            default => 'plain',
        };

        $html = match ($normalizedFormat) {
            'markdown' => $this->renderMarkdownWithSpoilers($content),
            'bbcode' => $this->renderBbcodeWithSpoilers($content),
            default => $this->renderPlainWithSpoilers($content),
        };

        if ($html === '') {
            $html = '<p class="text-stone-400 italic">Kein Inhalt.</p>';
        }

        return new HtmlString($html);
    }

    private function renderMarkdownWithSpoilers(string $content): string
    {
        return $this->renderWithSpoilers(
            $content,
            fn (string $segment): string => $this->renderMarkdownSegment($segment),
        );
    }

    private function renderBbcodeWithSpoilers(string $content): string
    {
        return $this->renderWithSpoilers(
            $content,
            fn (string $segment): string => $this->renderMarkdownSegment($this->convertBbcodeToMarkdown($segment)),
        );
    }

    private function renderPlainWithSpoilers(string $content): string
    {
        return $this->renderWithSpoilers(
            $content,
            fn (string $segment): string => $this->renderPlainSegment($segment),
        );
    }

    private function renderWithSpoilers(string $content, callable $renderer): string
    {
        $segments = preg_split(
            '/(\[spoiler\].*?\[\/spoiler\])/is',
            str_replace(["\r\n", "\r"], "\n", $content),
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        ) ?: [];

        if ($segments === []) {
            return '';
        }

        $htmlParts = [];

        foreach ($segments as $segment) {
            if (preg_match('/^\[spoiler\](.*?)\[\/spoiler\]$/is', $segment, $matches) === 1) {
                $spoilerInnerHtml = $renderer(trim($matches[1]));
                $spoilerInnerHtml = $spoilerInnerHtml !== ''
                    ? $spoilerInnerHtml
                    : '<p class="text-stone-400 italic">Kein Inhalt.</p>';

                $htmlParts[] = '<details data-post-spoiler class="my-3 rounded-md border border-stone-700/80 bg-black/35 p-3">'
                    .'<summary class="spoiler-summary cursor-pointer text-xs uppercase tracking-[0.08em] text-amber-300">Spoiler</summary>'
                    .'<div class="spoiler-panel mt-3">'.$spoilerInnerHtml.'</div>'
                    .'</details>';

                continue;
            }

            $segmentHtml = $renderer($segment);

            if ($segmentHtml !== '') {
                $htmlParts[] = $segmentHtml;
            }
        }

        return implode("\n", $htmlParts);
    }

    private function renderMarkdownSegment(string $segment): string
    {
        if (trim($segment) === '') {
            return '';
        }

        return (string) Str::markdown($segment, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
    }

    private function renderPlainSegment(string $segment): string
    {
        if (trim($segment) === '') {
            return '';
        }

        $paragraphs = preg_split('/\n{2,}/', trim($segment)) ?: [];
        $htmlParts = [];

        foreach ($paragraphs as $paragraph) {
            $trimmedParagraph = trim($paragraph);

            if ($trimmedParagraph === '') {
                continue;
            }

            $htmlParts[] = '<p>'.nl2br(e($trimmedParagraph), false).'</p>';
        }

        return implode("\n", $htmlParts);
    }

    private function convertBbcodeToMarkdown(string $content): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $content);

        $value = preg_replace_callback('/\[code\](.*?)\[\/code\]/is', function (array $matches): string {
            $body = trim($matches[1], "\n");

            return "\n```\n".$body."\n```\n";
        }, $value) ?? $value;

        $value = preg_replace_callback('/\[quote\](.*?)\[\/quote\]/is', function (array $matches): string {
            $lines = preg_split('/\n/', trim($matches[1])) ?: [];
            $quotedLines = [];

            foreach ($lines as $line) {
                $quotedLines[] = '> '.$line;
            }

            return "\n".implode("\n", $quotedLines)."\n";
        }, $value) ?? $value;

        $value = preg_replace('/\[b\](.*?)\[\/b\]/is', '**$1**', $value) ?? $value;
        $value = preg_replace('/\[i\](.*?)\[\/i\]/is', '*$1*', $value) ?? $value;
        $value = preg_replace('/\[u\](.*?)\[\/u\]/is', '__$1__', $value) ?? $value;
        $value = preg_replace('/\[s\](.*?)\[\/s\]/is', '~~$1~~', $value) ?? $value;

        $value = preg_replace_callback('/\[url=(.*?)\](.*?)\[\/url\]/is', function (array $matches): string {
            $url = trim($matches[1], " \t\n\r\0\x0B\"'");
            $label = trim($matches[2]);

            if (! $this->isAllowedUrlScheme($url)) {
                return $label;
            }

            return '['.$label.']('.$url.')';
        }, $value) ?? $value;

        $value = preg_replace_callback('/\[url\](.*?)\[\/url\]/is', function (array $matches): string {
            $url = trim($matches[1], " \t\n\r\0\x0B\"'");

            if (! $this->isAllowedUrlScheme($url)) {
                return $url;
            }

            return '['.$url.']('.$url.')';
        }, $value) ?? $value;

        return $value;
    }

    private function isAllowedUrlScheme(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! is_string($scheme) || $scheme === '') {
            return false;
        }

        return in_array(strtolower($scheme), ['http', 'https', 'mailto'], true);
    }
}
