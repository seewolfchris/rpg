<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class UiActionComponentTest extends TestCase
{
    public function test_action_component_keeps_link_and_button_semantics(): void
    {
        $link = Blade::render(
            '<x-ui.action href="/kampagnen" variant="accent" aria-label="Kampagnen öffnen">Öffnen</x-ui.action>'
        );
        $button = Blade::render(
            '<x-ui.action type="submit" variant="danger" full-width>Speichern</x-ui.action>'
        );

        $this->assertStringContainsString('<a', $link);
        $this->assertStringContainsString('href="/kampagnen"', $link);
        $this->assertStringContainsString('aria-label="Kampagnen öffnen"', $link);
        $this->assertStringContainsString('ui-action', $link);
        $this->assertStringContainsString('ui-btn-accent', $link);

        $this->assertStringContainsString('<button', $button);
        $this->assertStringContainsString('type="submit"', $button);
        $this->assertStringContainsString('ui-btn-danger', $button);
        $this->assertStringContainsString('w-full', $button);
    }

    public function test_disabled_link_has_no_navigation_target(): void
    {
        $html = Blade::render(
            '<x-ui.action href="/kampagnen" disabled>Gesperrt</x-ui.action>'
        );

        $this->assertStringContainsString('aria-disabled="true"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
        $this->assertStringNotContainsString('href="/kampagnen"', $html);
    }
}
