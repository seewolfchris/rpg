<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\HtmlString;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class SceneDescriptionRenderer
{
    private const INLINE_IMAGE_PLACEHOLDER_PATTERN = '/\[bild:([1-4])\]/iu';

    private const INLINE_IMAGE_PLACEHOLDER_SPLIT_PATTERN = '/(\[bild:[1-4]\])/iu';

    public function __construct(
        private readonly InlineImageSlotResolver $inlineImageSlotResolver,
    ) {}

    /**
     * @param  iterable<int, mixed>  $inlineImages
     */
    public function render(string $description, iterable $inlineImages): PostContentRenderResult
    {
        $imagesBySlot = $this->inlineImageSlotResolver
            ->resolve($inlineImages)
            ->mediaBySlot();
        $inlineMediaIds = [];
        $html = $this->renderWithInlineImages($description, $imagesBySlot, $inlineMediaIds);

        return new PostContentRenderResult(
            new HtmlString($html),
            array_values(array_unique($inlineMediaIds)),
        );
    }

    /**
     * @param  array<int, Media>  $imagesBySlot
     * @param  list<int>  $inlineMediaIds
     */
    private function renderWithInlineImages(string $description, array $imagesBySlot, array &$inlineMediaIds): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $description);

        if (preg_match(self::INLINE_IMAGE_PLACEHOLDER_PATTERN, $normalized) !== 1) {
            return $this->renderPlainSegment($normalized);
        }

        $parts = preg_split(
            self::INLINE_IMAGE_PLACEHOLDER_SPLIT_PATTERN,
            $normalized,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        ) ?: [];

        $htmlParts = [];

        foreach ($parts as $part) {
            if (preg_match(self::INLINE_IMAGE_PLACEHOLDER_PATTERN, $part, $matches) === 1) {
                $htmlParts[] = $this->renderInlineImagePlaceholder((int) $matches[1], $imagesBySlot, $inlineMediaIds);

                continue;
            }

            $html = $this->renderPlainSegment($part);

            if ($html !== '') {
                $htmlParts[] = $html;
            }
        }

        return implode("\n", $htmlParts);
    }

    /**
     * @param  array<int, Media>  $imagesBySlot
     * @param  list<int>  $inlineMediaIds
     */
    private function renderInlineImagePlaceholder(int $slot, array $imagesBySlot, array &$inlineMediaIds): string
    {
        $media = $imagesBySlot[$slot] ?? null;

        if (! $media instanceof Media) {
            return '<span data-scene-inline-image-missing="1" class="inline-flex rounded border border-stone-700/70 bg-black/25 px-2 py-0.5 text-xs italic text-stone-400">Bild '.$slot.' nicht verfügbar</span>';
        }

        $mediaId = (int) $media->id;
        $inlineMediaIds[] = $mediaId;
        $mediaUrl = e($media->getUrl());

        return '<figure data-scene-inline-image="1" data-scene-inline-image-slot="'.$slot.'" data-scene-media-id="'.$mediaId.'" class="my-5 overflow-hidden rounded-lg border border-stone-700/80 bg-black/30">'
            .'<a href="'.$mediaUrl.'" target="_blank" rel="noopener noreferrer" aria-label="Originalbild '.$slot.' zur Szenenbeschreibung öffnen">'
            .'<img src="'.$mediaUrl.'" alt="Bild '.$slot.' zur Szenenbeschreibung" loading="lazy" class="w-full object-cover">'
            .'</a>'
            .'</figure>';
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
}
