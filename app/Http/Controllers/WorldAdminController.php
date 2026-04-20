<?php

namespace App\Http\Controllers;

use App\Actions\World\CreateWorldAction;
use App\Actions\World\DeleteWorldAction;
use App\Actions\World\ReorderWorldsAction;
use App\Actions\World\UpdateWorldAction;
use App\Http\Requests\World\StoreWorldRequest;
use App\Http\Requests\World\UpdateWorldRequest;
use App\Models\World;
use App\Support\WorldCharacterOptionTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorldAdminController extends Controller
{
    public function __construct(
        private readonly CreateWorldAction $createWorldAction,
        private readonly DeleteWorldAction $deleteWorldAction,
        private readonly UpdateWorldAction $updateWorldAction,
        private readonly ReorderWorldsAction $reorderWorldsAction,
    ) {}

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
        $world = $this->createWorldAction->execute(
            $request->validated(),
        );

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
        $this->updateWorldAction->execute($world, $request->validated());

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', 'Welt aktualisiert.');
    }

    public function toggleActive(World $world): RedirectResponse
    {
        try {
            $nextIsActive = $this->updateWorldAction->toggleActive($world);
        } catch (ValidationException $exception) {
            return back()->withErrors([
                'world' => $this->firstValidationMessage($exception),
            ]);
        }

        return redirect()
            ->route('admin.worlds.index')
            ->with('status', $nextIsActive ? 'Welt aktiviert.' : 'Welt deaktiviert.');
    }

    public function move(World $world, string $direction): RedirectResponse
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            abort(404);
        }

        $moved = $this->reorderWorldsAction->execute($world, $direction);

        if (! $moved) {
            return redirect()
                ->route('admin.worlds.index')
                ->with('status', $direction === 'up'
                    ? 'Welt ist bereits ganz oben.'
                    : 'Welt ist bereits ganz unten.');
        }

        return redirect()
            ->route('admin.worlds.index')
            ->with('status', 'Welt-Sortierung aktualisiert.');
    }

    public function destroy(World $world): RedirectResponse
    {
        try {
            $this->deleteWorldAction->execute($world);
        } catch (ValidationException $exception) {
            return back()->withErrors([
                'world' => $this->firstValidationMessage($exception),
            ]);
        }

        return redirect()
            ->route('admin.worlds.index')
            ->with('status', 'Welt gelöscht.');
    }

    private function firstValidationMessage(ValidationException $exception): string
    {
        foreach ($exception->errors() as $messages) {
            $firstMessage = $messages[0] ?? null;

            if (is_string($firstMessage) && $firstMessage !== '') {
                return $firstMessage;
            }
        }

        return 'Die Welt konnte nicht aktualisiert werden.';
    }
}
