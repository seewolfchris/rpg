<?php

namespace Tests\Feature;

use Tests\TestCase;

class SceneReadingModeMobileContractTest extends TestCase
{
    public function test_mobile_reading_mode_css_removes_sticky_and_fixed_chrome(): void
    {
        $cssPath = resource_path('css/app.css');
        $css = file_get_contents($cssPath);

        $this->assertIsString($css);

        $this->assertMatchesRegularExpression(
            '/@media\s*\(max-width:\s*640px\)\s*\{[\s\S]*body\.is-reading-mode\s+\.reading-chapter-header\s*\{[\s\S]*position:\s*static;/',
            $css,
        );

        $this->assertMatchesRegularExpression(
            '/@media\s*\(max-width:\s*640px\)\s*\{[\s\S]*body\.is-reading-mode\s+\.reading-progress-bookmark\s*\{[\s\S]*position:\s*static;/',
            $css,
        );
    }
}
