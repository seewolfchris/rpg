<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderWorldMarkerEffectiveWorldTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_header_uses_session_world_name_instead_of_theme_fallback(): void
    {
        $user = User::factory()->create();
        $world = World::query()->where('slug', 'klassische-fantasy')->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession(['world_slug' => $world->slug])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSeeText($world->name)
            ->assertDontSeeText('STD · Standardwelt');

        $html = $response->getContent();
        $this->assertIsString($html);

        $xpath = $this->toXPath($html);

        $this->assertSame('KF · Klassische Fantasy', $this->headerWorldMarkerText($xpath));

        $dashboardWorldNames = $xpath->query("//section[contains(@class, 'ui-card')]//p[normalize-space()='Klassische Fantasy']");
        $this->assertGreaterThanOrEqual(1, $dashboardWorldNames->length);
    }

    public function test_dashboard_header_uses_configured_default_world_when_session_world_is_missing(): void
    {
        config(['worlds.default_slug' => 'klassische-fantasy']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSessionHas('world_slug', 'klassische-fantasy')
            ->assertDontSeeText('STD · Standardwelt');

        $html = $response->getContent();
        $this->assertIsString($html);

        $xpath = $this->toXPath($html);

        $this->assertSame('KF · Klassische Fantasy', $this->headerWorldMarkerText($xpath));
    }

    public function test_route_world_takes_precedence_over_session_world_for_header_marker(): void
    {
        $routeWorld = World::query()->where('slug', 'kriminalfaelle')->firstOrFail();
        $sessionWorld = World::query()->where('slug', 'klassische-fantasy')->firstOrFail();

        $response = $this->withSession(['world_slug' => $sessionWorld->slug])
            ->get(route('worlds.show', ['world' => $routeWorld]));

        $response->assertOk()
            ->assertDontSeeText('STD · Standardwelt');

        $html = $response->getContent();
        $this->assertIsString($html);

        $xpath = $this->toXPath($html);

        $this->assertSame('K · Kriminalfälle', $this->headerWorldMarkerText($xpath));
    }

    private function headerWorldMarkerText(\DOMXPath $xpath): string
    {
        $headerMarkers = $xpath->query("//header//div[@data-world-marker]");
        $this->assertSame(1, $headerMarkers->length);

        return $this->normalizeText($headerMarkers->item(0)?->textContent ?? '');
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
}
