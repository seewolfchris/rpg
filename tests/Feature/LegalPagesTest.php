<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_legal_routes_are_not_exposed(): void
    {
        $this->get('/impressum')->assertNotFound();
        $this->get('/datenschutz')->assertNotFound();
        $this->get('/copyright')->assertNotFound();
        $this->get('/urheberrecht')->assertNotFound();
    }

    public function test_legal_links_are_visible_on_landing_and_auth_layout(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('https://c76.org/impressum/')
            ->assertSee('https://c76.org/datenschutz/')
            ->assertSeeText('©2026 copyright by C. Sieber | all rights reserved');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('https://c76.org/impressum/')
            ->assertSee('https://c76.org/datenschutz/')
            ->assertSeeText('©2026 copyright by C. Sieber | all rights reserved');
    }
}
