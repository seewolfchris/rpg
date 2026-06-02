<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorldMarkerComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_world_show_renders_world_marker_with_name_and_non_color_marker_tokens(): void
    {
        $world = $this->resolveChronikenWorld();

        $response = $this->get(route('worlds.show', ['world' => $world]));
        $response->assertOk()
            ->assertSee('data-world-marker', false);

        $html = $response->getContent();
        $this->assertIsString($html);

        $xpath = $this->toXPath($html);

        $nameNodes = $xpath->query("//div[@data-world-marker]//span[contains(@class, 'world-marker-name') and normalize-space()='".$world->name."']");
        $this->assertGreaterThanOrEqual(1, $nameNodes->length);

        $labelNodes = $xpath->query("//div[@data-world-marker]//span[contains(@class, 'world-marker-token') and normalize-space()='CA']");
        $this->assertGreaterThanOrEqual(1, $labelNodes->length);

        $symbolNodes = $xpath->query("//div[@data-world-marker]//span[contains(@class, 'world-marker-symbol')]");
        $this->assertSame(0, $symbolNodes->length);
    }

    public function test_dashboard_renders_world_marker_when_selected_world_is_available(): void
    {
        $world = $this->resolveChronikenWorld();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['world_slug' => $world->slug])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('data-world-marker', false)
            ->assertSeeText($world->name);

        $html = $response->getContent();
        $this->assertIsString($html);

        $xpath = $this->toXPath($html);
        $dashboardWorldMarkerNodes = $xpath->query("//section[contains(@class, 'ui-card')]//div[@data-world-marker]");

        $this->assertGreaterThanOrEqual(1, $dashboardWorldMarkerNodes->length);
    }

    private function toXPath(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        @$document->loadHTML($html);

        return new \DOMXPath($document);
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
