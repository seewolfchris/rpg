<?php

namespace Tests\Unit;

use App\Support\PushNarrativeTextResolver;
use Tests\TestCase;

class PushNarrativeTextResolverTest extends TestCase
{
    public function test_resolves_world_specific_template_for_chroniken_der_asche(): void
    {
        $resolver = app(PushNarrativeTextResolver::class);

        $resolved = $resolver->resolve('scene_new_post', 'chroniken-der-asche', [
            'author' => 'Mara',
            'scene' => 'Staubtor',
            'excerpt' => 'Der Pfad glimmt zwischen den Mauern.',
        ]);

        $this->assertSame('Neues Fluestern aus der Asche', $resolved['title']);
        $this->assertSame(
            'Mara setzt in "Staubtor" den naechsten Satz: Der Pfad glimmt zwischen den Mauern.',
            $resolved['body'],
        );
        $this->assertSame('Zur Lesespur', $resolved['action_label']);
    }

    public function test_falls_back_to_default_template_for_unknown_world(): void
    {
        $resolver = app(PushNarrativeTextResolver::class);

        $resolved = $resolver->resolve('campaign_invitation', 'nachtmeer', [
            'inviter' => 'Ilyas',
            'campaign' => 'Schattenkueste',
        ]);

        $this->assertSame('Neue Kampagneneinladung', $resolved['title']);
        $this->assertSame('Ilyas laedt dich zu "Schattenkueste" ein.', $resolved['body']);
        $this->assertSame('Einladungen', $resolved['action_label']);
    }
}
