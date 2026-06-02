<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardNextStepTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_compact_next_step_card_for_active_world(): void
    {
        $user = User::factory()->create();
        $world = World::query()->where('slug', 'klassische-fantasy')->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession(['world_slug' => $world->slug])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSeeText('Dashboard')
            ->assertSeeText('Weiter ins Spiel')
            ->assertSeeText('Wähle deine nächste Aktion in der aktiven Welt.')
            ->assertSeeText('Aktive Welt:')
            ->assertSeeText('Klassische Fantasy')
            ->assertSeeText('Kampagnen öffnen')
            ->assertSeeText('Charaktere')
            ->assertSeeText('Wissen')
            ->assertSeeText('Wie spielt man?')
            ->assertDontSeeText('Was ist RPG?')
            ->assertDontSeeText('Du brauchst kein Vorwissen.');

        $html = $response->getContent();
        $this->assertIsString($html);

        $this->assertStringContainsString('href="'.route('campaigns.index', ['world' => $world]).'"', $html);
        $this->assertStringContainsString('href="'.route('characters.index', ['world' => $world->slug]).'"', $html);
        $this->assertStringContainsString('href="'.route('knowledge.index', ['world' => $world]).'"', $html);
        $this->assertStringContainsString('href="'.route('knowledge.how-to-play', ['world' => $world]).'"', $html);

        $xpath = $this->toXPath($html);
        $nextStepCards = $xpath->query("//section[@aria-labelledby='dashboard-next-step-title']");

        $this->assertSame(1, $nextStepCards->length);
    }

    public function test_dashboard_next_step_keeps_existing_dashboard_sections(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSeeText('Sichere Zuflucht')
            ->assertSeeText('Aktive Welt')
            ->assertSeeText('Tutorial im Spiel')
            ->assertSeeText('Top-Chronisten');
    }

    private function toXPath(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        @$document->loadHTML($html);

        return new \DOMXPath($document);
    }
}
