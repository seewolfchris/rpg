<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\WorldCharacterOptions\CreateWorldSpeciesOptionAction;
use App\Actions\WorldCharacterOptions\MoveWorldSpeciesOptionAction;
use App\Actions\WorldCharacterOptions\ToggleWorldSpeciesOptionAction;
use App\Actions\WorldCharacterOptions\UpdateWorldSpeciesOptionAction;
use App\Http\Requests\WorldCharacterOptions\MoveWorldCharacterOptionRequest;
use App\Http\Requests\WorldCharacterOptions\StoreWorldSpeciesOptionRequest;
use App\Http\Requests\WorldCharacterOptions\UpdateWorldSpeciesOptionRequest;
use App\Models\World;
use App\Models\WorldSpecies;
use Illuminate\Http\RedirectResponse;

class WorldSpeciesOptionAdminController extends Controller
{
    public function __construct(
        private readonly CreateWorldSpeciesOptionAction $createWorldSpeciesOptionAction,
        private readonly UpdateWorldSpeciesOptionAction $updateWorldSpeciesOptionAction,
        private readonly ToggleWorldSpeciesOptionAction $toggleWorldSpeciesOptionAction,
        private readonly MoveWorldSpeciesOptionAction $moveWorldSpeciesOptionAction,
    ) {}

    public function store(StoreWorldSpeciesOptionRequest $request, World $world): RedirectResponse
    {
        $this->createWorldSpeciesOptionAction->execute(
            world: $world,
            data: $this->normalizedPayload($request, true),
        );

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', 'Spezies hinzugefuegt.');
    }

    public function update(
        UpdateWorldSpeciesOptionRequest $request,
        World $world,
        WorldSpecies $speciesOption
    ): RedirectResponse {
        $this->updateWorldSpeciesOptionAction->execute(
            world: $world,
            speciesOption: $speciesOption,
            data: $this->normalizedPayload($request, false),
        );

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', 'Spezies aktualisiert.');
    }

    public function toggle(World $world, WorldSpecies $speciesOption): RedirectResponse
    {
        $nextIsActive = $this->toggleWorldSpeciesOptionAction->execute($world, $speciesOption);

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', $nextIsActive ? 'Spezies aktiviert.' : 'Spezies deaktiviert.');
    }

    public function move(
        MoveWorldCharacterOptionRequest $request,
        World $world,
        WorldSpecies $speciesOption
    ): RedirectResponse {
        $this->moveWorldSpeciesOptionAction->execute(
            world: $world,
            speciesOption: $speciesOption,
            direction: $request->direction(),
        );

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', 'Spezies-Sortierung aktualisiert.');
    }

    /**
     * @param  StoreWorldSpeciesOptionRequest|UpdateWorldSpeciesOptionRequest  $request
     * @return array<string, mixed>
     */
    private function normalizedPayload(
        StoreWorldSpeciesOptionRequest|UpdateWorldSpeciesOptionRequest $request,
        bool $defaultIsActive,
    ): array {
        return array_merge($request->validated(), [
            'is_magic_capable' => $request->boolean('is_magic_capable'),
            'is_template' => $request->boolean('is_template'),
            'is_active' => $request->boolean('is_active', $defaultIsActive),
        ]);
    }
}
