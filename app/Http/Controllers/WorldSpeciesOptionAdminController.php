<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\WorldCharacterOptions\ReorderWorldCharacterOptionAction;
use App\Http\Controllers\Concerns\NormalizesWorldCharacterOptionInput;
use App\Http\Requests\WorldCharacterOptions\MoveWorldCharacterOptionRequest;
use App\Http\Requests\WorldCharacterOptions\StoreWorldSpeciesOptionRequest;
use App\Http\Requests\WorldCharacterOptions\UpdateWorldSpeciesOptionRequest;
use App\Models\World;
use App\Models\WorldSpecies;
use Illuminate\Http\RedirectResponse;

class WorldSpeciesOptionAdminController extends Controller
{
    use NormalizesWorldCharacterOptionInput;

    public function __construct(
        private readonly ReorderWorldCharacterOptionAction $reorderWorldCharacterOptionAction,
    ) {}

    public function store(StoreWorldSpeciesOptionRequest $request, World $world): RedirectResponse
    {
        $validated = $request->validated();

        WorldSpecies::query()->create([
            'world_id' => (int) $world->id,
            'key' => (string) $validated['key'],
            'label' => (string) $validated['label'],
            'description' => $this->trimNullable($validated['description'] ?? null),
            'modifiers_json' => $this->decodeJsonArray($validated['modifiers_json'] ?? null),
            'le_bonus' => (int) ($validated['le_bonus'] ?? 0),
            'ae_bonus' => (int) ($validated['ae_bonus'] ?? 0),
            'position' => (int) ($validated['position'] ?? 0),
            'is_magic_capable' => $request->boolean('is_magic_capable'),
            'is_template' => $request->boolean('is_template'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', 'Spezies hinzugefuegt.');
    }

    public function update(
        UpdateWorldSpeciesOptionRequest $request,
        World $world,
        WorldSpecies $speciesOption
    ): RedirectResponse {
        $this->ensureSpeciesBelongsToWorld($world, $speciesOption);

        $validated = $request->validated();

        $speciesOption->update([
            'key' => (string) $validated['key'],
            'label' => (string) $validated['label'],
            'description' => $this->trimNullable($validated['description'] ?? null),
            'modifiers_json' => $this->decodeJsonArray($validated['modifiers_json'] ?? null),
            'le_bonus' => (int) ($validated['le_bonus'] ?? 0),
            'ae_bonus' => (int) ($validated['ae_bonus'] ?? 0),
            'position' => (int) ($validated['position'] ?? 0),
            'is_magic_capable' => $request->boolean('is_magic_capable'),
            'is_template' => $request->boolean('is_template'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', 'Spezies aktualisiert.');
    }

    public function toggle(World $world, WorldSpecies $speciesOption): RedirectResponse
    {
        $this->ensureSpeciesBelongsToWorld($world, $speciesOption);

        $speciesOption->is_active = ! $speciesOption->is_active;
        $speciesOption->save();

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', $speciesOption->is_active ? 'Spezies aktiviert.' : 'Spezies deaktiviert.');
    }

    public function move(
        MoveWorldCharacterOptionRequest $request,
        World $world,
        WorldSpecies $speciesOption
    ): RedirectResponse {
        $this->ensureSpeciesBelongsToWorld($world, $speciesOption);

        $this->reorderWorldCharacterOptionAction->execute(
            worldId: (int) $world->id,
            optionId: (int) $speciesOption->id,
            table: 'world_species',
            direction: $request->direction(),
        );

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', 'Spezies-Sortierung aktualisiert.');
    }

    private function ensureSpeciesBelongsToWorld(World $world, WorldSpecies $speciesOption): void
    {
        abort_unless((int) $speciesOption->world_id === (int) $world->id, 404);
    }
}
