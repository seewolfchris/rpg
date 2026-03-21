<?php

namespace App\Http\Controllers;

use App\Http\Requests\World\StoreWorldRequest;
use App\Http\Requests\World\UpdateWorldRequest;
use App\Models\World;
use App\Support\WorldCharacterOptionTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WorldAdminController extends Controller
{
    public function index(): View
    {
        $worlds = World::query()
            ->withCount(['campaigns', 'characters', 'encyclopediaCategories'])
            ->ordered()
            ->paginate(20);

        return view('worlds.admin.index', compact('worlds'));
    }

    public function create(): View
    {
        $world = new World;

        return view('worlds.admin.create', compact('world'));
    }

    public function store(StoreWorldRequest $request): RedirectResponse
    {
        $world = World::query()->create($request->validated());

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', 'Welt erstellt. Bitte jetzt eine Charakter-Vorlage importieren.');
    }

    public function edit(World $world): View
    {
        $speciesOptions = $world->speciesOptions()
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        $callingOptions = $world->callingOptions()
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $templateService = app(WorldCharacterOptionTemplateService::class);
        $templateOptions = $templateService->templateSelectOptions();
        $defaultTemplateKey = $templateService->inferTemplateKeyForWorld($world)
            ?? (array_key_first($templateOptions) ?? '');

        return view('worlds.admin.edit', compact(
            'world',
            'speciesOptions',
            'callingOptions',
            'templateOptions',
            'defaultTemplateKey',
        ));
    }

    public function update(UpdateWorldRequest $request, World $world): RedirectResponse
    {
        $world->update($request->validated());

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', 'Welt aktualisiert.');
    }

    public function toggleActive(World $world): RedirectResponse
    {
        if ($world->is_active) {
            if ($world->slug === World::defaultSlug()) {
                return back()->withErrors([
                    'world' => 'Die Standardwelt kann nicht deaktiviert werden.',
                ]);
            }

            $activeWorldCount = World::query()
                ->where('is_active', true)
                ->count();

            if ($activeWorldCount <= 1) {
                return back()->withErrors([
                    'world' => 'Mindestens eine aktive Welt muss bestehen bleiben.',
                ]);
            }
        }

        $world->is_active = ! $world->is_active;
        $world->save();

        return redirect()
            ->route('admin.worlds.index')
            ->with('status', $world->is_active ? 'Welt aktiviert.' : 'Welt deaktiviert.');
    }

    public function move(World $world, string $direction): RedirectResponse
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            abort(404);
        }

        $orderedIds = World::query()
            ->ordered()
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        $currentIndex = array_search((int) $world->id, $orderedIds, true);
        if ($currentIndex === false) {
            return back()->withErrors([
                'world' => 'Welt konnte in der Sortierung nicht gefunden werden.',
            ]);
        }

        $targetIndex = $direction === 'up'
            ? $currentIndex - 1
            : $currentIndex + 1;

        if (! isset($orderedIds[$targetIndex])) {
            return redirect()
                ->route('admin.worlds.index')
                ->with('status', $direction === 'up'
                    ? 'Welt ist bereits ganz oben.'
                    : 'Welt ist bereits ganz unten.');
        }

        [$orderedIds[$currentIndex], $orderedIds[$targetIndex]] = [$orderedIds[$targetIndex], $orderedIds[$currentIndex]];

        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $index => $worldId) {
                World::query()
                    ->whereKey($worldId)
                    ->update(['position' => ($index + 1) * 10]);
            }
        });

        return redirect()
            ->route('admin.worlds.index')
            ->with('status', 'Welt-Sortierung aktualisiert.');
    }

    public function destroy(World $world): RedirectResponse
    {
        $hasDependencies = $world->campaigns()->exists()
            || $world->characters()->exists()
            || $world->encyclopediaCategories()->exists();

        if ($hasDependencies) {
            return back()->withErrors([
                'world' => 'Diese Welt kann nicht gelöscht werden, solange noch Kampagnen, Charaktere oder Wissen daran hängen.',
            ]);
        }

        $world->delete();

        return redirect()
            ->route('admin.worlds.index')
            ->with('status', 'Welt gelöscht.');
    }
}
