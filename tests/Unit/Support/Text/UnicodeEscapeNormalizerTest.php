<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Text;

use App\Support\Text\UnicodeEscapeNormalizer;
use Tests\TestCase;

class UnicodeEscapeNormalizerTest extends TestCase
{
    public function test_it_decodes_backslash_unicode_escape_sequences(): void
    {
        $normalizer = app(UnicodeEscapeNormalizer::class);

        $this->assertSame('Schlüssel', $normalizer->normalizeVisibleText('Schl\u00fcssel'));
        $this->assertSame('Größe', $normalizer->normalizeVisibleText('Gr\u00f6\u00dfe'));
    }

    public function test_it_decodes_broken_u00_sequences_without_backslash_for_german_umlauts(): void
    {
        $normalizer = app(UnicodeEscapeNormalizer::class);

        $this->assertSame('Schlüssel', $normalizer->normalizeVisibleText('Schlu00fcssel'));
        $this->assertSame('ÄÖÜß', $normalizer->normalizeVisibleText('u00c4u00d6u00dcu00df'));
    }

    public function test_it_keeps_normal_umlaut_text_unchanged(): void
    {
        $normalizer = app(UnicodeEscapeNormalizer::class);

        $this->assertSame('Schlüssel', $normalizer->normalizeVisibleText('Schlüssel'));
        $this->assertSame('Größe', $normalizer->normalizeVisibleText('Größe'));
    }

    public function test_it_does_not_transform_html_into_renderable_markup(): void
    {
        $normalizer = app(UnicodeEscapeNormalizer::class);
        $payload = '<script>alert(1)</script> &lt;b&gt;safe&lt;/b&gt;';

        $this->assertSame($payload, $normalizer->normalizeVisibleText($payload));
    }
}

