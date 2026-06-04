<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PostContentRenderer
{
    private const INLINE_IMAGE_PLACEHOLDER_PATTERN = '/\[bild:([1-4])\]/iu';

    private const INLINE_IMAGE_PLACEHOLDER_SPLIT_PATTERN = '/(\[bild:[1-4]\])/iu';

    public function __construct(
        private readonly InlineImageSlotResolver $inlineImageSlotResolver,
    ) {}

    public function render(string $content, ?string $format): HtmlString
    {
        $inlineMediaIds = [];

        return new HtmlString($this->renderContent($content, $format, null, $inlineMediaIds));
    }

    /**
     * @param  iterable<int, mixed>  $inlineImages
     */
    public function renderWithInlineImages(string $content, ?string $format, iterable $inlineImages): PostContentRenderResult
    {
        $inlineImagesByPosition = $this->inlineImagesByPosition($inlineImages);
        $inlineMediaIds = [];
        $html = $this->renderContent($content, $format, $inlineImagesByPosition, $inlineMediaIds);

        return new PostContentRenderResult(
            new HtmlString($html),
            array_values(array_unique($inlineMediaIds)),
        );
    }

    /**
     * @param  array<int, Media>|null  $inlineImagesByPosition
     * @param  list<int>  $inlineMediaIds
     */
    private function renderContent(string $content, ?string $format, ?array $inlineImagesByPosition, array &$inlineMediaIds): string
    {
        $normalizedFormat = match ($format) {
            'markdown', 'bbcode', 'plain' => $format,
            default => 'plain',
        };

        $html = match ($normalizedFormat) {
            'markdown' => $this->renderMarkdownWithSpoilers($content, $inlineImagesByPosition, $inlineMediaIds),
            'bbcode' => $this->renderBbcodeWithSpoilers($content, $inlineImagesByPosition, $inlineMediaIds),
            default => $this->renderPlainWithSpoilers($content, $inlineImagesByPosition, $inlineMediaIds),
        };

        if ($html === '') {
            $html = '<p class="text-stone-400 italic">Kein Inhalt.</p>';
        }

        return $html;
    }

    /**
     * @param  array<int, Media>|null  $inlineImagesByPosition
     * @param  list<int>  $inlineMediaIds
     */
    private function renderMarkdownWithSpoilers(string $content, ?array $inlineImagesByPosition, array &$inlineMediaIds): string
    {
        return $this->renderWithSpoilers(
            $content,
            function (string $segment) use ($inlineImagesByPosition, &$inlineMediaIds): string {
                return $this->renderSegmentWithInlineImages(
                    $segment,
                    fn (string $part): string => $this->renderMarkdownSegment($part),
                    $inlineImagesByPosition,
                    $inlineMediaIds,
                );
            },
        );
    }

    /**
     * @param  array<int, Media>|null  $inlineImagesByPosition
     * @param  list<int>  $inlineMediaIds
     */
    private function renderBbcodeWithSpoilers(string $content, ?array $inlineImagesByPosition, array &$inlineMediaIds): string
    {
        return $this->renderWithSpoilers(
            $content,
            function (string $segment) use ($inlineImagesByPosition, &$inlineMediaIds): string {
                return $this->renderSegmentWithInlineImages(
                    $segment,
                    fn (string $part): string => $this->renderMarkdownSegment($this->convertBbcodeToMarkdown($part)),
                    $inlineImagesByPosition,
                    $inlineMediaIds,
                );
            },
        );
    }

    /**
     * @param  array<int, Media>|null  $inlineImagesByPosition
     * @param  list<int>  $inlineMediaIds
     */
    private function renderPlainWithSpoilers(string $content, ?array $inlineImagesByPosition, array &$inlineMediaIds): string
    {
        return $this->renderWithSpoilers(
            $content,
            function (string $segment) use ($inlineImagesByPosition, &$inlineMediaIds): string {
                return $this->renderSegmentWithInlineImages(
                    $segment,
                    fn (string $part): string => $this->renderPlainSegment($part),
                    $inlineImagesByPosition,
                    $inlineMediaIds,
                );
            },
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

    /**
     * @param  callable(string): string  $renderer
     * @param  array<int, Media>|null  $inlineImagesByPosition
     * @param  list<int>  $inlineMediaIds
     */
    private function renderSegmentWithInlineImages(string $segment, callable $renderer, ?array $inlineImagesByPosition, array &$inlineMediaIds): string
    {
        if ($inlineImagesByPosition === null || preg_match(self::INLINE_IMAGE_PLACEHOLDER_PATTERN, $segment) !== 1) {
            return $renderer($segment);
        }

        $parts = preg_split(
            self::INLINE_IMAGE_PLACEHOLDER_SPLIT_PATTERN,
            $segment,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        ) ?: [];

        if ($parts === []) {
            return '';
        }

        $htmlParts = [];

        foreach ($parts as $part) {
            if (preg_match(self::INLINE_IMAGE_PLACEHOLDER_PATTERN, $part, $matches) === 1) {
                $htmlParts[] = $this->renderInlineImagePlaceholder((int) $matches[1], $inlineImagesByPosition, $inlineMediaIds);

                continue;
            }

            $partHtml = $renderer($part);

            if ($partHtml !== '') {
                $htmlParts[] = $partHtml;
            }
        }

        return implode("\n", $htmlParts);
    }

    /**
     * @param  array<int, Media>  $inlineImagesByPosition
     * @param  list<int>  $inlineMediaIds
     */
    private function renderInlineImagePlaceholder(int $position, array $inlineImagesByPosition, array &$inlineMediaIds): string
    {
        $media = $inlineImagesByPosition[$position] ?? null;

        if (! $media instanceof Media) {
            return '<span data-post-inline-image-missing="1" class="inline-flex rounded border border-stone-700/70 bg-black/25 px-2 py-0.5 text-xs italic text-stone-400">Bild '.$position.' nicht verfügbar</span>';
        }

        $mediaId = (int) $media->id;
        $inlineMediaIds[] = $mediaId;

        return '<figure data-post-inline-image="1" data-post-inline-image-slot="'.$position.'" data-post-media-id="'.$mediaId.'" class="my-5 overflow-hidden rounded-lg border border-stone-700/80 bg-black/30">'
            .'<img src="'.e($media->getUrl()).'" alt="Immersives Bild '.$position.'" loading="lazy" class="w-full object-cover">'
            .'</figure>';
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

    /**
     * @param  iterable<int, mixed>  $inlineImages
     * @return array<int, Media>
     */
    private function inlineImagesByPosition(iterable $inlineImages): array
    {
        return $this->inlineImageSlotResolver
            ->resolve($inlineImages)
            ->mediaBySlot();
    }
}
