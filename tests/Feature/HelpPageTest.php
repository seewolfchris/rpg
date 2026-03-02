<?php

namespace Tests\Feature;

use Tests\TestCase;

class HelpPageTest extends TestCase
{
    public function test_help_page_is_accessible_for_guests(): void
    {
        $response = $this->get(route('help.index'));

        $response->assertOk();
        $response->assertSeeText('Hilfe');
        $response->assertSeeText('Begriffe');
        $response->assertSeeText('IC (In Character)');
        $response->assertSeeText('OOC (Out Of Character)');
    }
}
