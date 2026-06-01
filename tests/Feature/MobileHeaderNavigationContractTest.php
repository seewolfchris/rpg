<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileHeaderNavigationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_layout_exposes_mobile_menu_trigger_sheet_and_backdrop_contract(): void
    {
        $response = $this->get(route('worlds.index'));
        $response->assertOk()
            ->assertSee('data-mobile-nav-trigger', false)
            ->assertSee('data-mobile-nav-backdrop', false)
            ->assertSee('data-mobile-sheet-panel', false)
            ->assertSee('data-pwa-install-button', false);

        $html = $response->getContent();
        $this->assertIsString($html);

        $xpath = $this->toXPath($html);

        $triggerNodes = $xpath->query("//button[@data-mobile-nav-trigger]");
        $this->assertSame(1, $triggerNodes->length);
        $this->assertSame('app-mobile-navigation', $triggerNodes->item(0)?->attributes?->getNamedItem('aria-controls')?->nodeValue);
        $this->assertSame('false', $triggerNodes->item(0)?->attributes?->getNamedItem('aria-expanded')?->nodeValue);

        $backdropNodes = $xpath->query("//button[@data-mobile-nav-backdrop]");
        $this->assertSame(1, $backdropNodes->length);
        $this->assertSame('true', $backdropNodes->item(0)?->attributes?->getNamedItem('aria-hidden')?->nodeValue);

        $navigationNodes = $xpath->query("//nav[@aria-label='Hauptnavigation' and @id='app-mobile-navigation' and @data-mobile-sheet-panel]");
        $this->assertSame(1, $navigationNodes->length);
    }

    public function test_authenticated_layout_keeps_navigation_badges_logout_and_desktop_links(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertOk()
            ->assertSee('Welten')
            ->assertSee('Wissen')
            ->assertSee('Dashboard')
            ->assertSee('id="nav-unread-notifications-badge"', false)
            ->assertSee('id="nav-bookmark-count-badge"', false)
            ->assertSee('data-logout-form', false)
            ->assertSee('data-pwa-install-button', false);

        $html = $response->getContent();
        $this->assertIsString($html);

        $xpath = $this->toXPath($html);

        $navigationNodes = $xpath->query("//nav[@aria-label='Hauptnavigation']");
        $this->assertSame(1, $navigationNodes->length);

        $triggerNodes = $xpath->query("//button[@data-mobile-nav-trigger]");
        $this->assertSame(1, $triggerNodes->length);
    }

    private function toXPath(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        @$document->loadHTML($html);

        return new \DOMXPath($document);
    }
}
