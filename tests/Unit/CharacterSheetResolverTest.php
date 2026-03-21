<?php

namespace Tests\Unit;

use App\Models\World;
use App\Support\CharacterSheetResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterSheetResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_world_specific_species_and_callings_for_gegenwart(): void
    {
        $world = World::query()->where('slug', 'gegenwart')->firstOrFail();

        $sheet = app(CharacterSheetResolver::class)->resolveForWorldId((int) $world->id);

        $speciesKeys = array_keys((array) ($sheet['species'] ?? []));
        $callingKeys = array_keys((array) ($sheet['callings'] ?? []));

        $this->assertSame(['mensch'], $speciesKeys);
        $this->assertContains('polizist', $callingKeys);
        $this->assertNotContains('magier', $callingKeys);
        $this->assertSame([], array_values((array) ($sheet['magic_capable_species'] ?? [])));
        $this->assertSame([], array_values((array) ($sheet['magic_capable_callings'] ?? [])));
    }

    public function test_it_marks_psioniker_as_magic_capable_in_scifi(): void
    {
        $world = World::query()->where('slug', 'sci-fi')->firstOrFail();

        $sheet = app(CharacterSheetResolver::class)->resolveForWorldId((int) $world->id);

        $speciesKeys = array_keys((array) ($sheet['species'] ?? []));
        $callingKeys = array_keys((array) ($sheet['callings'] ?? []));

        $this->assertContains('xeno', $speciesKeys);
        $this->assertContains('psioniker', $callingKeys);
        $this->assertContains('psioniker', (array) ($sheet['magic_capable_callings'] ?? []));
    }
}
