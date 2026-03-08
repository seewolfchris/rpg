<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_legal_pages_are_accessible_for_guests(): void
    {
        $this->get(route('legal.imprint'))
            ->assertOk()
            ->assertSeeText('Impressum');

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSeeText('Datenschutzerklärung');

        $this->get(route('legal.copyright'))
            ->assertOk()
            ->assertSeeText('Urheberrecht und Rechtehinweise');
    }

    public function test_legal_links_are_visible_on_landing_and_auth_layout(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('legal.imprint'))
            ->assertSee(route('legal.privacy'))
            ->assertSee(route('legal.copyright'));

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('legal.imprint'))
            ->assertSee(route('legal.privacy'))
            ->assertSee(route('legal.copyright'));
    }
}
