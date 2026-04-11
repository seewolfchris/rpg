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

        $response->assertRedirect(route('knowledge.global.index'));
    }

    public function test_global_knowledge_center_pages_are_accessible_for_guests(): void
    {
        $this->get(route('knowledge.global.index'))
            ->assertOk()
            ->assertSeeText('Plattformwissen')
            ->assertSeeText('Weltenbezogenes Wissen');

        $this->get(route('knowledge.global.how-to-play'))
            ->assertOk()
            ->assertSeeText('Schnellstart in 7 Schritten')
            ->assertSeeText('Ich-Perspektive');

        $this->get(route('knowledge.global.rules'))
            ->assertOk()
            ->assertSeeText('Regelwerk')
            ->assertSeeText('Prozentproben (d100)')
            ->assertSeeText('Glossar')
            ->assertSeeText('Abkürzungen');

        $this->get(route('knowledge.global.encyclopedia'))
            ->assertOk()
            ->assertSeeText('Enzyklopädie je Welt')
            ->assertSeeText('Enzyklopädie öffnen');
    }

    public function test_world_knowledge_center_pages_are_accessible_for_guests(): void
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
            ->assertSeeText('Prozentproben (d100)')
            ->assertSeeText('Glossar')
            ->assertSeeText('Abkürzungen');

        $this->get(route('knowledge.encyclopedia', ['world' => $world]))
            ->assertOk()
            ->assertSeeText('Enzyklopädie · '.$world->name)
            ->assertSeeText('Einträge sichtbar');
    }

    public function test_rules_page_uses_gm_only_probe_wording_without_d20_legacy(): void
    {
        $response = $this->get(route('knowledge.global.rules'));

        $response->assertOk()
            ->assertSeeText('Proben werden nur durch GM oder Co-GM ausgelöst.')
            ->assertSeeText('Anlass, Ziel-Held, Probe-Eigenschaft und Modifikator')
            ->assertSeeText('Die Rechnung bleibt klar')
            ->assertDontSeeText('d20');
    }

    public function test_world_markdown_preview_routes_return_404_when_feature_is_disabled(): void
    {
        $world = $this->defaultWorld();
        config()->set('content.world_markdown_preview', false);

        $this->get(route('knowledge.world-overview', ['world' => $world]))
            ->assertNotFound();

        $this->get(route('knowledge.lore', ['world' => $world]))
            ->assertNotFound();
    }

    public function test_world_markdown_preview_routes_are_accessible_when_feature_is_enabled(): void
    {
        $world = $this->defaultWorld();
        config()->set('content.world_markdown_preview', true);

        $this->get(route('knowledge.world-overview', ['world' => $world]))
            ->assertOk()
            ->assertSeeText('Weltüberblick (Markdown)')
            ->assertSeeText('Chroniken der Asche');

        $this->get(route('knowledge.lore', ['world' => $world]))
            ->assertOk()
            ->assertSeeText('Welt-Lore (Markdown)')
            ->assertSeeText('Lore-Index');

        $this->get(route('knowledge.lore', ['world' => $world, 'category' => 'zeitalter']))
            ->assertOk()
            ->assertSeeText('Zeitalter')
            ->assertSeeText('Der Aschenfall');
    }
}
