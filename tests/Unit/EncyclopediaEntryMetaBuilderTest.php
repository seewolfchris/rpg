<?php

namespace Tests\Unit;

use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Support\EncyclopediaEntryMetaBuilder;
use Tests\TestCase;

class EncyclopediaEntryMetaBuilderTest extends TestCase
{
    public function test_extract_internal_links_returns_unique_encyclopedia_links(): void
    {
        $builder = app(EncyclopediaEntryMetaBuilder::class);

        $content = <<<'MD'
Siehe [Aschenwulf](/wissen/enzyklopaedie/monster-bestiarium/aschenwulf) und
[Aschenwulf](/wissen/enzyklopaedie/monster-bestiarium/aschenwulf) erneut.
Außerdem [Dornhafen](/wissen/enzyklopaedie/regionen/dornhafen-am-roten-delta).
Externer Link: [Beispiel](https://example.org)
MD;

        $links = $builder->extractInternalLinks($content);

        $this->assertCount(2, $links);
        $this->assertSame('Aschenwulf', $links[0]['label']);
        $this->assertSame('/wissen/enzyklopaedie/monster-bestiarium/aschenwulf', $links[0]['url']);
        $this->assertSame('dornhafen-am-roten-delta', $links[1]['slug']);
    }

    public function test_build_image_prompts_includes_entry_context_and_category_directive(): void
    {
        $builder = app(EncyclopediaEntryMetaBuilder::class);

        $category = new EncyclopediaCategory([
            'name' => 'Monster & Bestiarium',
            'slug' => 'monster-bestiarium',
        ]);

        $entry = new EncyclopediaEntry([
            'title' => 'Aschenwulf',
            'excerpt' => 'Rudeljäger aus Ruß und Hunger.',
            'content' => 'Langtext',
        ]);
        $entry->setRelation('category', $category);

        $prompts = $builder->buildImagePrompts($entry);

        $this->assertCount(3, $prompts);
        $this->assertStringContainsString('Aschenwulf', $prompts[0]);
        $this->assertStringContainsString('creature anatomy', $prompts[0]);
        $this->assertStringContainsString('Rudeljäger aus Ruß und Hunger.', $prompts[0]);
    }

    public function test_build_image_prompts_limits_very_long_context(): void
    {
        $builder = app(EncyclopediaEntryMetaBuilder::class);

        $entry = new EncyclopediaEntry([
            'title' => 'Kettenhüter',
            'excerpt' => str_repeat('Asche und Blut ', 120),
            'content' => 'Langtext',
        ]);

        $prompts = $builder->buildImagePrompts($entry);

        $this->assertCount(3, $prompts);
        $this->assertLessThan(900, mb_strlen($prompts[0]));
        $this->assertStringContainsString('Context: ', $prompts[0]);
        $this->assertStringContainsString('…', $prompts[0]);
    }
}
