<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\WorldCharacterOptions\ReorderWorldCharacterOptionAction;
use App\Http\Controllers\Concerns\NormalizesWorldCharacterOptionInput;
use App\Http\Requests\WorldCharacterOptions\MoveWorldCharacterOptionRequest;
use App\Http\Requests\WorldCharacterOptions\StoreWorldCallingOptionRequest;
use App\Http\Requests\WorldCharacterOptions\UpdateWorldCallingOptionRequest;
use App\Models\World;
use App\Models\WorldCalling;
use Illuminate\Http\RedirectResponse;

class WorldCallingOptionAdminController extends Controller
{
    use NormalizesWorldCharacterOptionInput;

    public function __construct(
        private readonly ReorderWorldCharacterOptionAction $reorderWorldCharacterOptionAction,
    ) {}

    public function store(StoreWorldCallingOptionRequest $request, World $world): RedirectResponse
    {
        $validated = $request->validated();

        WorldCalling::query()->create([
            'world_id' => (int) $world->id,
            'key' => (string) $validated['key'],
            'label' => (string) $validated['label'],
            'description' => $this->trimNullable($validated['description'] ?? null),
            'minimums_json' => $this->decodeJsonArray($validated['minimums_json'] ?? null),
            'bonuses_json' => $this->decodeJsonArray($validated['bonuses_json'] ?? null),
            'position' => (int) ($validated['position'] ?? 0),
            'is_magic_capable' => $request->boolean('is_magic_capable'),
            'is_custom' => $request->boolean('is_custom'),
            'is_template' => $request->boolean('is_template'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', 'Berufung hinzugefuegt.');
    }

    public function update(
        UpdateWorldCallingOptionRequest $request,
        World $world,
        WorldCalling $callingOption
    ): RedirectResponse {
        $this->ensureCallingBelongsToWorld($world, $callingOption);

        $validated = $request->validated();

        $callingOption->update([
            'key' => (string) $validated['key'],
            'label' => (string) $validated['label'],
            'description' => $this->trimNullable($validated['description'] ?? null),
            'minimums_json' => $this->decodeJsonArray($validated['minimums_json'] ?? null),
            'bonuses_json' => $this->decodeJsonArray($validated['bonuses_json'] ?? null),
            'position' => (int) ($validated['position'] ?? 0),
            'is_magic_capable' => $request->boolean('is_magic_capable'),
            'is_custom' => $request->boolean('is_custom'),
            'is_template' => $request->boolean('is_template'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', 'Berufung aktualisiert.');
    }

    public function toggle(World $world, WorldCalling $callingOption): RedirectResponse
    {
        $this->ensureCallingBelongsToWorld($world, $callingOption);

        $callingOption->is_active = ! $callingOption->is_active;
        $callingOption->save();

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', $callingOption->is_active ? 'Berufung aktiviert.' : 'Berufung deaktiviert.');
    }

    public function move(
        MoveWorldCharacterOptionRequest $request,
        World $world,
        WorldCalling $callingOption
    ): RedirectResponse {
        $this->ensureCallingBelongsToWorld($world, $callingOption);

        $this->reorderWorldCharacterOptionAction->execute(
            worldId: (int) $world->id,
            optionId: (int) $callingOption->id,
            table: 'world_callings',
            direction: $request->direction(),
        );

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', 'Berufungs-Sortierung aktualisiert.');
    }

    private function ensureCallingBelongsToWorld(World $world, WorldCalling $callingOption): void
    {
        abort_unless((int) $callingOption->world_id === (int) $world->id, 404);
    }
}
