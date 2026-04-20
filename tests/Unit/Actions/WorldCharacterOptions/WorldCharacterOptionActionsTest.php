<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\WorldCharacterOptions;

use App\Actions\WorldCharacterOptions\CreateWorldCallingOptionAction;
use App\Actions\WorldCharacterOptions\CreateWorldSpeciesOptionAction;
use App\Actions\WorldCharacterOptions\MoveWorldCallingOptionAction;
use App\Actions\WorldCharacterOptions\MoveWorldSpeciesOptionAction;
use App\Actions\WorldCharacterOptions\ToggleWorldCallingOptionAction;
use App\Actions\WorldCharacterOptions\ToggleWorldSpeciesOptionAction;
use App\Actions\WorldCharacterOptions\UpdateWorldCallingOptionAction;
use App\Actions\WorldCharacterOptions\UpdateWorldSpeciesOptionAction;
use App\Models\World;
use App\Models\WorldCalling;
use App\Models\WorldSpecies;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorldCharacterOptionActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_species_actions_create_update_toggle_and_move(): void
    {
        $world = World::factory()->create();
        $speciesA = app(CreateWorldSpeciesOptionAction::class)->execute($world, [
            'key' => 'mensch',
            'label' => 'Mensch',
            'description' => 'Basis',
            'modifiers_json' => '{"mu": 1}',
            'le_bonus' => 1,
            'ae_bonus' => 0,
            'position' => 10,
            'is_magic_capable' => false,
            'is_template' => true,
            'is_active' => true,
        ]);
        $speciesB = app(CreateWorldSpeciesOptionAction::class)->execute($world, [
            'key' => 'xeno',
            'label' => 'Xeno',
            'description' => 'Alien',
            'modifiers_json' => '{}',
            'le_bonus' => 0,
            'ae_bonus' => 2,
            'position' => 20,
            'is_magic_capable' => true,
            'is_template' => true,
            'is_active' => true,
        ]);

        app(UpdateWorldSpeciesOptionAction::class)->execute($world, $speciesA, [
            'key' => 'mensch',
            'label' => 'Mensch Prime',
            'description' => 'Prime',
            'modifiers_json' => '{"kl": 2}',
            'le_bonus' => 3,
            'ae_bonus' => 1,
            'position' => 15,
            'is_magic_capable' => true,
            'is_template' => false,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('world_species', [
            'id' => $speciesA->id,
            'label' => 'Mensch Prime',
            'is_template' => false,
            'is_magic_capable' => true,
        ]);

        $nextActive = app(ToggleWorldSpeciesOptionAction::class)->execute($world, $speciesA);
        $this->assertFalse($nextActive);

        app(MoveWorldSpeciesOptionAction::class)->execute($world, $speciesB, 'up');

        $orderedSpeciesIds = WorldSpecies::query()
            ->where('world_id', $world->id)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $this->assertSame([(int) $speciesB->id, (int) $speciesA->id], $orderedSpeciesIds);
    }

    public function test_species_update_throws_on_world_context_mismatch(): void
    {
        $world = World::factory()->create();
        $otherWorld = World::factory()->create();
        $species = WorldSpecies::query()->create([
            'world_id' => $world->id,
            'key' => 'mensch',
            'label' => 'Mensch',
            'description' => null,
            'modifiers_json' => [],
            'le_bonus' => 0,
            'ae_bonus' => 0,
            'position' => 10,
            'is_magic_capable' => false,
            'is_active' => true,
            'is_template' => true,
        ]);

        $this->expectException(ModelNotFoundException::class);

        app(UpdateWorldSpeciesOptionAction::class)->execute($otherWorld, $species, [
            'key' => 'mensch',
            'label' => 'Mismatch',
            'description' => null,
            'modifiers_json' => '{}',
            'le_bonus' => 0,
            'ae_bonus' => 0,
            'position' => 10,
            'is_magic_capable' => false,
            'is_active' => true,
            'is_template' => true,
        ]);
    }

    public function test_calling_actions_create_update_toggle_and_move(): void
    {
        $world = World::factory()->create();
        $callingA = app(CreateWorldCallingOptionAction::class)->execute($world, [
            'key' => 'analyst',
            'label' => 'Analyst',
            'description' => 'A',
            'minimums_json' => '{"kl": 10}',
            'bonuses_json' => '{"kl": 5}',
            'position' => 10,
            'is_magic_capable' => false,
            'is_custom' => true,
            'is_template' => false,
            'is_active' => true,
        ]);
        $callingB = app(CreateWorldCallingOptionAction::class)->execute($world, [
            'key' => 'operator',
            'label' => 'Operator',
            'description' => 'B',
            'minimums_json' => '{}',
            'bonuses_json' => '{}',
            'position' => 20,
            'is_magic_capable' => true,
            'is_custom' => true,
            'is_template' => false,
            'is_active' => true,
        ]);

        app(UpdateWorldCallingOptionAction::class)->execute($world, $callingA, [
            'key' => 'analyst',
            'label' => 'Analyst Prime',
            'description' => 'Prime',
            'minimums_json' => '{"kl": 12}',
            'bonuses_json' => '{"kl": 6}',
            'position' => 15,
            'is_magic_capable' => true,
            'is_custom' => true,
            'is_template' => false,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('world_callings', [
            'id' => $callingA->id,
            'label' => 'Analyst Prime',
            'is_magic_capable' => true,
        ]);

        $nextActive = app(ToggleWorldCallingOptionAction::class)->execute($world, $callingA);
        $this->assertFalse($nextActive);

        app(MoveWorldCallingOptionAction::class)->execute($world, $callingB, 'up');

        $orderedCallingIds = WorldCalling::query()
            ->where('world_id', $world->id)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $this->assertSame([(int) $callingB->id, (int) $callingA->id], $orderedCallingIds);
    }
}
