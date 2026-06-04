<?php

namespace Tests\Unit;

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
}
