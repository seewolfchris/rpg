<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\WorldCharacterOptions\CreateWorldCallingOptionAction;
use App\Actions\WorldCharacterOptions\MoveWorldCallingOptionAction;
use App\Actions\WorldCharacterOptions\ToggleWorldCallingOptionAction;
use App\Actions\WorldCharacterOptions\UpdateWorldCallingOptionAction;
use App\Http\Requests\WorldCharacterOptions\MoveWorldCharacterOptionRequest;
use App\Http\Requests\WorldCharacterOptions\StoreWorldCallingOptionRequest;
use App\Http\Requests\WorldCharacterOptions\UpdateWorldCallingOptionRequest;
use App\Models\World;
use App\Models\WorldCalling;
use Illuminate\Http\RedirectResponse;

class WorldCallingOptionAdminController extends Controller
{
    public function __construct(
        private readonly CreateWorldCallingOptionAction $createWorldCallingOptionAction,
        private readonly UpdateWorldCallingOptionAction $updateWorldCallingOptionAction,
        private readonly ToggleWorldCallingOptionAction $toggleWorldCallingOptionAction,
        private readonly MoveWorldCallingOptionAction $moveWorldCallingOptionAction,
    ) {}

    public function store(StoreWorldCallingOptionRequest $request, World $world): RedirectResponse
    {
        $this->createWorldCallingOptionAction->execute(
            world: $world,
            data: $this->normalizedPayload($request, true),
        );

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', 'Berufung hinzugefuegt.');
    }

    public function update(
        UpdateWorldCallingOptionRequest $request,
        World $world,
        WorldCalling $callingOption
    ): RedirectResponse {
        $this->updateWorldCallingOptionAction->execute(
            world: $world,
            callingOption: $callingOption,
            data: $this->normalizedPayload($request, false),
        );

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', 'Berufung aktualisiert.');
    }

    public function toggle(World $world, WorldCalling $callingOption): RedirectResponse
    {
        $nextIsActive = $this->toggleWorldCallingOptionAction->execute($world, $callingOption);

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', $nextIsActive ? 'Berufung aktiviert.' : 'Berufung deaktiviert.');
    }

    public function move(
        MoveWorldCharacterOptionRequest $request,
        World $world,
        WorldCalling $callingOption
    ): RedirectResponse {
        $this->moveWorldCallingOptionAction->execute(
            world: $world,
            callingOption: $callingOption,
            direction: $request->direction(),
        );

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', 'Berufungs-Sortierung aktualisiert.');
    }

    /**
     * @param  StoreWorldCallingOptionRequest|UpdateWorldCallingOptionRequest  $request
     * @return array<string, mixed>
     */
    private function normalizedPayload(
        StoreWorldCallingOptionRequest|UpdateWorldCallingOptionRequest $request,
        bool $defaultIsActive,
    ): array {
        return array_merge($request->validated(), [
            'is_magic_capable' => $request->boolean('is_magic_capable'),
            'is_custom' => $request->boolean('is_custom'),
            'is_template' => $request->boolean('is_template'),
            'is_active' => $request->boolean('is_active', $defaultIsActive),
        ]);
    }
}
