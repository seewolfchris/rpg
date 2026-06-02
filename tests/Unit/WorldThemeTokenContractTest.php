<?php

namespace Tests\Unit;

use App\Support\WorldThemeResolver;
use Tests\TestCase;

class WorldThemeTokenContractTest extends TestCase
{
    public function test_resolver_exposes_color_and_marker_tokens_for_all_seed_worlds(): void
    {
        $resolver = app(WorldThemeResolver::class);

        foreach ($this->expectedWorldProfiles() as $slug => $expected) {
            $resolved = $resolver->resolve($slug);

            $this->assertSame($slug, $resolved['world_slug']);
            $this->assertSame($slug, $resolved['theme_key']);
            $this->assertSame($expected['label'], $resolved['label']);
            $this->assertSame($expected['marker_label'], $resolved['marker_label']);
            $this->assertSame($expected['marker_bg'], $resolved['marker_bg']);
            $this->assertSame($expected['marker_fg'], $resolved['marker_fg']);
            $this->assertSame($expected['marker_border'], $resolved['marker_border']);
            $this->assertNotSame('default', $resolved['theme_key']);
            $this->assertNotSame('', $resolved['marker_symbol']);

            foreach ($expected['css_variables'] as $name => $value) {
                $this->assertSame($value, $resolved['css_variables'][$name] ?? null, $slug.' '.$name);
                $this->assertStringContainsString($name.': '.$value, $resolved['css_variable_style']);
            }
        }
    }

    public function test_resolver_returns_marker_defaults_for_unknown_world_theme(): void
    {
        $resolved = app(WorldThemeResolver::class)->resolve('unbekannte-welt');

        $this->assertSame('unbekannte-welt', $resolved['world_slug']);
        $this->assertSame('default', $resolved['theme_key']);
        $this->assertNotSame('', $resolved['css_variable_style']);
        $this->assertNotSame('', $resolved['marker_label']);
        $this->assertNotSame('', $resolved['marker_symbol']);
        $this->assertNotSame('', $resolved['marker_bg']);
        $this->assertNotSame('', $resolved['marker_fg']);
        $this->assertNotSame('', $resolved['marker_border']);
    }

    /**
     * @return array<string, array{
     *   label: string,
     *   marker_label: string,
     *   marker_bg: string,
     *   marker_fg: string,
     *   marker_border: string,
     *   css_variables: array<string, string>
     * }>
     */
    private function expectedWorldProfiles(): array
    {
        return [
            'chroniken-der-asche' => [
                'label' => 'Chroniken der Asche',
                'marker_label' => 'CA',
                'marker_bg' => 'rgb(51 28 18 / 88%)',
                'marker_fg' => '#f2c08d',
                'marker_border' => 'rgb(196 106 50 / 58%)',
                'css_variables' => [
                    '--accent' => '#c46a32',
                    '--accent-strong' => 'rgb(196 106 50 / 24%)',
                    '--world-bg-top' => 'rgb(87 45 28 / 34%)',
                    '--world-bg-mid' => 'rgb(42 31 29 / 26%)',
                    '--world-bg-bottom' => '#060403',
                    '--world-glow-primary' => 'rgb(196 106 50 / 26%)',
                    '--world-glow-secondary' => 'rgb(120 53 15 / 18%)',
                ],
            ],
            'klassische-fantasy' => [
                'label' => 'Klassische Fantasy',
                'marker_label' => 'KF',
                'marker_bg' => 'rgb(13 43 28 / 90%)',
                'marker_fg' => '#d7c56d',
                'marker_border' => 'rgb(79 176 109 / 58%)',
                'css_variables' => [
                    '--accent' => '#4fb06d',
                    '--accent-strong' => 'rgb(79 176 109 / 22%)',
                    '--world-bg-top' => 'rgb(25 86 55 / 30%)',
                    '--world-bg-mid' => 'rgb(43 64 35 / 22%)',
                    '--world-bg-bottom' => '#040b07',
                    '--world-glow-primary' => 'rgb(79 176 109 / 24%)',
                    '--world-glow-secondary' => 'rgb(215 197 109 / 16%)',
                ],
            ],
            'kriminalfaelle' => [
                'label' => 'Kriminalfälle',
                'marker_label' => 'K',
                'marker_bg' => 'rgb(39 29 22 / 90%)',
                'marker_fg' => '#f0d2a2',
                'marker_border' => 'rgb(183 121 69 / 58%)',
                'css_variables' => [
                    '--accent' => '#b77945',
                    '--accent-strong' => 'rgb(183 121 69 / 22%)',
                    '--world-bg-top' => 'rgb(72 47 33 / 32%)',
                    '--world-bg-mid' => 'rgb(34 34 39 / 24%)',
                    '--world-bg-bottom' => '#070707',
                    '--world-glow-primary' => 'rgb(183 121 69 / 22%)',
                    '--world-glow-secondary' => 'rgb(80 80 86 / 18%)',
                ],
            ],
            'gegenwart' => [
                'label' => 'Gegenwart',
                'marker_label' => 'G',
                'marker_bg' => 'rgb(18 31 43 / 90%)',
                'marker_fg' => '#c7d7e6',
                'marker_border' => 'rgb(95 143 184 / 58%)',
                'css_variables' => [
                    '--accent' => '#5f8fb8',
                    '--accent-strong' => 'rgb(95 143 184 / 22%)',
                    '--world-bg-top' => 'rgb(31 54 72 / 30%)',
                    '--world-bg-mid' => 'rgb(30 38 48 / 24%)',
                    '--world-bg-bottom' => '#05070a',
                    '--world-glow-primary' => 'rgb(95 143 184 / 22%)',
                    '--world-glow-secondary' => 'rgb(100 116 139 / 16%)',
                ],
            ],
            'sci-fi' => [
                'label' => 'Sci-Fi',
                'marker_label' => 'SF',
                'marker_bg' => 'rgb(10 34 43 / 90%)',
                'marker_fg' => '#b8f3ff',
                'marker_border' => 'rgb(86 199 217 / 58%)',
                'css_variables' => [
                    '--accent' => '#56c7d9',
                    '--accent-strong' => 'rgb(86 199 217 / 22%)',
                    '--world-bg-top' => 'rgb(24 79 92 / 30%)',
                    '--world-bg-mid' => 'rgb(47 38 82 / 24%)',
                    '--world-bg-bottom' => '#03060a',
                    '--world-glow-primary' => 'rgb(86 199 217 / 24%)',
                    '--world-glow-secondary' => 'rgb(139 92 246 / 16%)',
                ],
            ],
            'postapokalypse' => [
                'label' => 'Postapokalypse',
                'marker_label' => 'PA',
                'marker_bg' => 'rgb(42 33 19 / 90%)',
                'marker_fg' => '#e2c08a',
                'marker_border' => 'rgb(164 111 58 / 58%)',
                'css_variables' => [
                    '--accent' => '#a46f3a',
                    '--accent-strong' => 'rgb(164 111 58 / 22%)',
                    '--world-bg-top' => 'rgb(87 65 34 / 30%)',
                    '--world-bg-mid' => 'rgb(54 63 37 / 22%)',
                    '--world-bg-bottom' => '#060604',
                    '--world-glow-primary' => 'rgb(164 111 58 / 24%)',
                    '--world-glow-secondary' => 'rgb(85 107 47 / 16%)',
                ],
            ],
        ];
    }
}
