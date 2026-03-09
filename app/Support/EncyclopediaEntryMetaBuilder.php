<?php

namespace App\Support;

use App\Models\EncyclopediaEntry;
use Illuminate\Support\Str;

class EncyclopediaEntryMetaBuilder
{
    private const IMAGE_PROMPT_CONTEXT_MAX_LENGTH = 280;

    /**
     * @return array<int, array{label:string,url:string,category:string,slug:string}>
     */
    public function extractInternalLinks(string $content, int $limit = 12): array
    {
        $matches = [];
        $pattern = '/\[(?<label>[^\]]+)\]\((?<url>(?:\/w\/[a-z0-9\-]+)?\/wissen\/enzyklopaedie\/(?<category>[a-z0-9\-]+)\/(?<slug>[a-z0-9\-]+))\)/iu';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        if ($matches === []) {
            return [];
        }

        $links = [];
        $seenUrls = [];

        foreach ($matches as $match) {
            $url = trim((string) ($match['url'] ?? ''));

            if ($url === '' || isset($seenUrls[$url])) {
                continue;
            }

            $seenUrls[$url] = true;
            $links[] = [
                'label' => trim((string) ($match['label'] ?? '')),
                'url' => $url,
                'category' => trim((string) ($match['category'] ?? '')),
                'slug' => trim((string) ($match['slug'] ?? '')),
            ];

            if (count($links) >= $limit) {
                break;
            }
        }

        return $links;
    }

    /**
     * @return list<string>
     */
    public function buildImagePrompts(EncyclopediaEntry $entry): array
    {
        $title = trim(str_replace('"', '', $entry->title));
        $excerpt = trim((string) ($entry->excerpt ?? ''));
        $category = trim((string) ($entry->category?->name ?? 'Enzyklopädie'));
        $worldName = trim((string) ($entry->category?->world?->name ?? 'C76-RPG'));

        $categoryDirective = match ($entry->category?->slug) {
            'monster-bestiarium' => 'focus on creature anatomy, scarred hide, predatory stance, field-guide realism',
            'waffen-ruestungen-relikte' => 'focus on material detail, wear marks, smithing history, practical combat look',
            'heldenarchetypen-berufungen' => 'focus on character silhouette, role identity, emotional tension',
            'magie-liturgie' => 'focus on ritual atmosphere, ash particles, sacred symbols, dangerous mysticism',
            default => 'focus on environmental storytelling and grounded dark-fantasy realism',
        };

        $contextSource = $excerpt !== ''
            ? $excerpt
            : trim(strip_tags($entry->content));

        $context = Str::limit(
            preg_replace('/\s+/u', ' ', $contextSource) ?? '',
            self::IMAGE_PROMPT_CONTEXT_MAX_LENGTH,
            '…'
        );

        return [
            "{$title} in {$worldName}, atmospheric concept art, {$categoryDirective}, "
                ."cinematic lighting, immersive detail, no copyrighted references. Context: {$context}",
            "Cinematic mid-shot of {$title}, category {$category}, weathered textures, "
                ."distinct mood for world {$worldName}, realistic materials, dramatic composition, 35mm lens look",
            "Wide establishing shot inspired by {$title}, devastated architecture, "
                ."world-specific landmarks from {$worldName}, immersive matte painting style, high detail",
        ];
    }
}
