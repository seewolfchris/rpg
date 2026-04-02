<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorldCharacterOptions\ImportWorldCharacterOptionTemplateRequest;
use App\Http\Requests\WorldCharacterOptions\StoreWorldCallingOptionRequest;
use App\Http\Requests\WorldCharacterOptions\StoreWorldSpeciesOptionRequest;
use App\Http\Requests\WorldCharacterOptions\UpdateWorldCallingOptionRequest;
use App\Http\Requests\WorldCharacterOptions\UpdateWorldSpeciesOptionRequest;
use App\Models\World;
use App\Models\WorldCalling;
use App\Models\WorldSpecies;
use App\Support\WorldCharacterOptionTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class WorldCharacterOptionsAdminController extends Controller
{
    public function __construct(
        private readonly WorldCharacterOptionTemplateService $templateService,
    ) {}

    public function importTemplate(ImportWorldCharacterOptionTemplateRequest $request, World $world): RedirectResponse
    {
        $validated = $request->validated();

        $result = $this->templateService->importTemplate($world, (string) $validated['template_key']);

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', 'Vorlage importiert: '.$result['species'].' Spezies, '.$result['callings'].' Berufungen.');
    }

    public function storeSpecies(StoreWorldSpeciesOptionRequest $request, World $world): RedirectResponse
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

    public function updateSpecies(UpdateWorldSpeciesOptionRequest $request, World $world, WorldSpecies $speciesOption): RedirectResponse
    {
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

    public function toggleSpecies(World $world, WorldSpecies $speciesOption): RedirectResponse
    {
        $this->ensureSpeciesBelongsToWorld($world, $speciesOption);

        $speciesOption->is_active = ! $speciesOption->is_active;
        $speciesOption->save();

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', $speciesOption->is_active ? 'Spezies aktiviert.' : 'Spezies deaktiviert.');
    }

    public function moveSpecies(World $world, WorldSpecies $speciesOption, string $direction): RedirectResponse
    {
        $this->ensureSpeciesBelongsToWorld($world, $speciesOption);
        $this->reorderOption($world, $speciesOption, $direction, 'world_species');

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', 'Spezies-Sortierung aktualisiert.');
    }

    public function storeCalling(StoreWorldCallingOptionRequest $request, World $world): RedirectResponse
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

    public function updateCalling(UpdateWorldCallingOptionRequest $request, World $world, WorldCalling $callingOption): RedirectResponse
    {
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

    public function toggleCalling(World $world, WorldCalling $callingOption): RedirectResponse
    {
        $this->ensureCallingBelongsToWorld($world, $callingOption);

        $callingOption->is_active = ! $callingOption->is_active;
        $callingOption->save();

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', $callingOption->is_active ? 'Berufung aktiviert.' : 'Berufung deaktiviert.');
    }

    public function moveCalling(World $world, WorldCalling $callingOption, string $direction): RedirectResponse
    {
        $this->ensureCallingBelongsToWorld($world, $callingOption);
        $this->reorderOption($world, $callingOption, $direction, 'world_callings');

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', 'Berufungs-Sortierung aktualisiert.');
    }

    private function ensureSpeciesBelongsToWorld(World $world, WorldSpecies $speciesOption): void
    {
        abort_unless((int) $speciesOption->world_id === (int) $world->id, 404);
    }

    private function ensureCallingBelongsToWorld(World $world, WorldCalling $callingOption): void
    {
        abort_unless((int) $callingOption->world_id === (int) $world->id, 404);
    }

    private function reorderOption(World $world, WorldSpecies|WorldCalling $option, string $direction, string $table): void
    {
        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        $orderedIds = DB::table($table)
            ->where('world_id', (int) $world->id)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        $currentIndex = array_search((int) $option->id, $orderedIds, true);
        if ($currentIndex === false) {
            return;
        }

        $targetIndex = $direction === 'up'
            ? $currentIndex - 1
            : $currentIndex + 1;

        if (! isset($orderedIds[$targetIndex])) {
            return;
        }

        [$orderedIds[$currentIndex], $orderedIds[$targetIndex]] = [$orderedIds[$targetIndex], $orderedIds[$currentIndex]];

        DB::transaction(function () use ($table, $orderedIds): void {
            foreach ($orderedIds as $index => $id) {
                DB::table($table)
                    ->where('id', $id)
                    ->update([
                        'position' => ($index + 1) * 10,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonArray(mixed $raw): array
    {
        if (! is_string($raw)) {
            return [];
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function trimNullable(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
