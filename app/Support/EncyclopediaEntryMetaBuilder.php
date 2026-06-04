<?php

namespace App\Support;

class EncyclopediaEntryMetaBuilder
{
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
            $url = trim((string) $match['url']);

            if ($url === '' || isset($seenUrls[$url])) {
                continue;
            }

            $seenUrls[$url] = true;
            $links[] = [
                'label' => trim((string) $match['label']),
                'url' => $url,
                'category' => trim((string) $match['category']),
                'slug' => trim((string) $match['slug']),
            ];

            if (count($links) >= $limit) {
                break;
            }
        }

        return $links;
    }
}
