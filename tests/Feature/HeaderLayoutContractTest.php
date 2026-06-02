<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderLayoutContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_keeps_navigation_mobile_sheet_badges_and_compact_world_marker_contract(): void
    {
        $world = $this->resolveChronikenWorld();
        $user = User::factory()->create(['points' => 17]);

        $response = $this->actingAs($user)
            ->withSession(['world_slug' => $world->slug])
            ->get(route('notifications.index'));

        $response->assertOk()
            ->assertSee('id="app-mobile-navigation"', false)
            ->assertSee('data-mobile-nav-trigger', false)
            ->assertSee('data-mobile-nav-backdrop', false)
            ->assertSee('data-mobile-sheet-panel', false)
            ->assertSee('id="nav-unread-notifications-badge"', false)
            ->assertSee('id="nav-bookmark-count-badge"', false)
            ->assertSee('data-logout-form', false)
            ->assertSee('data-pwa-install-button', false);

        $html = $response->getContent();
        $this->assertIsString($html);

        $xpath = $this->toXPath($html);

        $headerMarkers = $xpath->query("//header//div[@data-world-marker]");
        $this->assertSame(1, $headerMarkers->length);

        $headerMarkerText = $this->normalizeText($headerMarkers->item(0)?->textContent ?? '');
        $this->assertSame('CA · Chroniken der Asche', $headerMarkerText);

        $headerMarkerSymbols = $xpath->query("//header//div[@data-world-marker]//span[contains(@class, 'world-marker-symbol')]");
        $this->assertSame(0, $headerMarkerSymbols->length);

        $pointsLinks = $xpath->query("//nav[@id='app-mobile-navigation']//a[contains(normalize-space(), 'Punkte')]");
        $this->assertSame(0, $pointsLinks->length);

        $pointsStatusChips = $xpath->query("//nav[@id='app-mobile-navigation']//div[@data-nav-group='account']//span[contains(@class, 'app-nav-status-chip') and contains(normalize-space(), '17 Punkte')]");
        $this->assertSame(1, $pointsStatusChips->length);

        $primaryLogoutButtons = $xpath->query("//nav[@id='app-mobile-navigation']//div[@data-nav-group='primary']//button[contains(normalize-space(), 'Abmelden')]");
        $this->assertSame(0, $primaryLogoutButtons->length);

        $accountLogoutForms = $xpath->query("//nav[@id='app-mobile-navigation']//div[@data-nav-group='account']//form[@data-logout-form]");
        $this->assertSame(1, $accountLogoutForms->length);
    }

    private function toXPath(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        @$document->loadHTML($html);

        return new \DOMXPath($document);
    }

    private function normalizeText(string $input): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($input));

        return $normalized === null ? trim($input) : $normalized;
    }

    private function resolveChronikenWorld(): World
    {
        $existing = World::query()->where('slug', 'chroniken-der-asche')->first();
        if ($existing instanceof World) {
            return $existing;
        }

        return World::factory()->chronikenDerAsche()->create();
    }
}
