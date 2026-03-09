<?php

namespace Tests\Feature;

use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpPageTest extends TestCase
{
    use RefreshDatabase;

    private function defaultWorld(): World
    {
        return World::query()
            ->where('slug', (string) config('worlds.default_slug'))
            ->firstOrFail();
    }

    public function test_help_route_redirects_to_knowledge_center(): void
    {
        $response = $this->get(route('help.index'));

        $response->assertRedirect(route('knowledge.index', ['world' => $this->defaultWorld()]));
    }

    public function test_knowledge_center_pages_are_accessible_for_guests(): void
    {
        $world = $this->defaultWorld();

        $this->get(route('knowledge.index', ['world' => $world]))
            ->assertOk()
            ->assertSeeText('Wissenszentrum')
            ->assertSeeText('Wie spielt man?');

        $this->get(route('knowledge.how-to-play', ['world' => $world]))
            ->assertOk()
            ->assertSeeText('Schnellstart in 7 Schritten')
            ->assertSeeText('Ich-Perspektive');

        $this->get(route('knowledge.rules', ['world' => $world]))
            ->assertOk()
            ->assertSeeText('Regelwerk')
            ->assertSeeText('Prozentproben (d100)');

        $this->get(route('knowledge.encyclopedia', ['world' => $world]))
            ->assertOk()
            ->assertSeeText('Enzyklopädie · '.$world->name)
            ->assertSeeText('Zeitalter der Sonnenkronen');
    }

    public function test_rules_page_uses_gm_only_probe_wording_without_d20_legacy(): void
    {
        $response = $this->get(route('knowledge.rules', ['world' => $this->defaultWorld()]));

        $response->assertOk()
            ->assertSeeText('Proben werden nur durch GM oder Co-GM ausgelöst.')
            ->assertSeeText('Anlass, Ziel-Held, Probe-Eigenschaft und Modifikator')
            ->assertSeeText('Das Ergebnis wird automatisch berechnet')
            ->assertDontSeeText('d20');
    }
}
