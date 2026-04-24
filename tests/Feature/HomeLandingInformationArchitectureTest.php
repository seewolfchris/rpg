<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeLandingInformationArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_contains_all_required_landing_section_ids(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();

        foreach ([
            'hero',
            'kurzintro',
            'was-ist-rpg',
            'wie-funktionierts',
            'welten',
            'einstieg',
            'warum-schriftbasiert',
            'faq-anfaenger',
            'finaler-cta',
        ] as $sectionId) {
            $response->assertSee('id="'.$sectionId.'"', false);
        }
    }

    public function test_home_contains_information_flow_anchor_links(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('href="#wie-funktionierts"', false)
            ->assertSee('href="#welten"', false);
    }

    public function test_home_primary_start_target_depends_on_auth_state(): void
    {
        $guestResponse = $this->get(route('home'));

        $guestResponse->assertOk()
            ->assertSee('href="'.route('register').'"', false)
            ->assertDontSee('href="'.route('dashboard').'"', false);

        $user = User::factory()->create();

        $authResponse = $this->actingAs($user)->get(route('home'));

        $authResponse->assertOk()
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertDontSee('href="'.route('register').'"', false);
    }
}
