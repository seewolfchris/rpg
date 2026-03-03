<?php

namespace Tests\Feature;

use Tests\TestCase;

class HelpPageTest extends TestCase
{
    public function test_help_route_redirects_to_knowledge_center(): void
    {
        $response = $this->get(route('help.index'));

        $response->assertRedirect(route('knowledge.index'));
    }

    public function test_knowledge_center_pages_are_accessible_for_guests(): void
    {
        $this->get(route('knowledge.index'))
            ->assertOk()
            ->assertSeeText('Wissenszentrum')
            ->assertSeeText('Wie spielt man?');

        $this->get(route('knowledge.how-to-play'))
            ->assertOk()
            ->assertSeeText('Schnellstart in 7 Schritten')
            ->assertSeeText('Ich-Perspektive');

        $this->get(route('knowledge.rules'))
            ->assertOk()
            ->assertSeeText('Regelwerk')
            ->assertSeeText('d20-Proben');

        $this->get(route('knowledge.encyclopedia'))
            ->assertOk()
            ->assertSeeText('Enzyklopaedie von Vhal')
            ->assertSeeText('Zeitalter');
    }
}
