<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutWidthContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_app_shell_renders_wide_page_helpers(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('class="app-header ui-page-shell ui-page-wide', false)
            ->assertSee('class="app-main-shell ui-page-shell ui-page-wide', false)
            ->assertSee('class="ui-page-shell ui-page-wide pb-8"', false);
    }

    public function test_overview_pages_use_wide_variant(): void
    {
        $world = $this->defaultWorld();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['world_slug' => $world->slug])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<section class="ui-page-wide space-y-6">', false);

        $this->actingAs($user)
            ->withSession(['world_slug' => $world->slug])
            ->get(route('campaigns.index', ['world' => $world]))
            ->assertOk()
            ->assertSee('<section class="ui-page-wide space-y-6">', false);

        $this->actingAs($user)
            ->get(route('leaderboard.index'))
            ->assertOk()
            ->assertSee('<section class="ui-page-wide space-y-6">', false);
    }

    public function test_reading_pages_keep_limited_page_and_content_widths(): void
    {
        $this->get(route('knowledge.global.rules'))
            ->assertOk()
            ->assertSee('<section class="knowledge-rulebook-page ui-page-medium space-y-6">', false)
            ->assertSee('class="knowledge-content text-[#cccccc]"', false);

        $this->get(route('knowledge.global.how-to-play'))
            ->assertOk()
            ->assertSee('<section class="ui-page-medium space-y-6">', false);
    }

    public function test_landing_page_keeps_its_own_width_system(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('landing-shell', false)
            ->assertDontSee('app-main-shell ui-page-shell', false)
            ->assertDontSee('ui-page-wide', false);
    }

    private function defaultWorld(): World
    {
        $world = World::query()
            ->where('slug', (string) config('worlds.default_slug'))
            ->first();

        if ($world instanceof World) {
            return $world;
        }

        return World::factory()->chronikenDerAsche()->create();
    }
}
