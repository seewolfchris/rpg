<?php

namespace App\Http\Controllers;

use App\Models\World;
use App\Models\WorldCalling;
use App\Models\WorldSpecies;
use App\Support\WorldCharacterOptionTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorldCharacterOptionsAdminController extends Controller
{
    public function __construct(
        private readonly WorldCharacterOptionTemplateService $templateService,
    ) {}

    public function importTemplate(Request $request, World $world): RedirectResponse
    {
        $templateOptions = array_keys($this->templateService->templateSelectOptions());

        $validated = $request->validate([
            'template_key' => ['required', 'string', 'in:'.implode(',', $templateOptions)],
        ]);

        $result = $this->templateService->importTemplate($world, (string) $validated['template_key']);

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', 'Vorlage importiert: '.$result['species'].' Spezies, '.$result['callings'].' Berufungen.');
    }

    public function storeSpecies(Request $request, World $world): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'alpha_dash', 'max:80', 'unique:world_species,key,NULL,id,world_id,'.$world->id],
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'modifiers_json' => ['nullable', 'json'],
            'le_bonus' => ['nullable', 'integer', 'between:-50,50'],
            'ae_bonus' => ['nullable', 'integer', 'between:-50,50'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_magic_capable' => ['sometimes', 'boolean'],
            'is_template' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

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

    public function updateSpecies(Request $request, World $world, WorldSpecies $speciesOption): RedirectResponse
    {
        $this->ensureSpeciesBelongsToWorld($world, $speciesOption);

        $validated = $request->validate([
            'key' => ['required', 'string', 'alpha_dash', 'max:80', 'unique:world_species,key,'.$speciesOption->id.',id,world_id,'.$world->id],
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'modifiers_json' => ['nullable', 'json'],
            'le_bonus' => ['nullable', 'integer', 'between:-50,50'],
            'ae_bonus' => ['nullable', 'integer', 'between:-50,50'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_magic_capable' => ['sometimes', 'boolean'],
            'is_template' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

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

    public function storeCalling(Request $request, World $world): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'alpha_dash', 'max:80', 'unique:world_callings,key,NULL,id,world_id,'.$world->id],
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'minimums_json' => ['nullable', 'json'],
            'bonuses_json' => ['nullable', 'json'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_magic_capable' => ['sometimes', 'boolean'],
            'is_custom' => ['sometimes', 'boolean'],
            'is_template' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

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

    public function updateCalling(Request $request, World $world, WorldCalling $callingOption): RedirectResponse
    {
        $this->ensureCallingBelongsToWorld($world, $callingOption);

        $validated = $request->validate([
            'key' => ['required', 'string', 'alpha_dash', 'max:80', 'unique:world_callings,key,'.$callingOption->id.',id,world_id,'.$world->id],
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'minimums_json' => ['nullable', 'json'],
            'bonuses_json' => ['nullable', 'json'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_magic_capable' => ['sometimes', 'boolean'],
            'is_custom' => ['sometimes', 'boolean'],
            'is_template' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

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
