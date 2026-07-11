<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileNavigationNoJsContractTest extends TestCase
{
    public function test_mobile_navigation_stays_visible_when_scripting_is_unavailable(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);

        $noScriptMediaStart = strpos($css, '@media (max-width: 639px) and (scripting: none)');
        $desktopMediaStart = strpos($css, '@media (min-width: 640px)', $noScriptMediaStart ?: 0);

        $this->assertNotFalse($noScriptMediaStart);
        $this->assertNotFalse($desktopMediaStart);

        $noScriptCss = substr($css, $noScriptMediaStart, $desktopMediaStart - $noScriptMediaStart);

        $this->assertStringContainsString('.app-mobile-nav-trigger,', $noScriptCss);
        $this->assertStringContainsString('display: none !important;', $noScriptCss);
        $this->assertStringContainsString('.app-nav {', $noScriptCss);
        $this->assertStringContainsString('position: static;', $noScriptCss);
        $this->assertStringContainsString('transform: none;', $noScriptCss);
        $this->assertStringContainsString('opacity: 1;', $noScriptCss);
        $this->assertStringContainsString('visibility: visible;', $noScriptCss);
        $this->assertStringContainsString('pointer-events: auto;', $noScriptCss);
    }
}
