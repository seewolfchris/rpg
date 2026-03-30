<?php

namespace App\Http\Controllers;

use App\Actions\Character\BuildCharacterCreateDataAction;
use App\Actions\Character\BuildCharacterEditDataAction;
use App\Actions\Character\BuildCharacterIndexDataAction;
use App\Actions\Character\BuildCharacterShowDataAction;
use App\Actions\Character\CreateCharacterAction;
use App\Actions\Character\DeleteCharacterAction;
use App\Actions\Character\UpdateCharacterAction;
use App\Actions\Character\UpdateCharacterInlineAction;
use App\Exceptions\CharacterDeletionFailedException;
use App\Http\Requests\Character\StoreCharacterRequest;
use App\Http\Requests\Character\UpdateCharacterRequest;
use App\Models\Character;
use App\Models\World;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class CharacterController extends Controller
{
    public function __construct(
        private readonly BuildCharacterCreateDataAction $buildCharacterCreateDataAction,
        private readonly BuildCharacterEditDataAction $buildCharacterEditDataAction,
        private readonly BuildCharacterIndexDataAction $buildCharacterIndexDataAction,
        private readonly BuildCharacterShowDataAction $buildCharacterShowDataAction,
        private readonly CreateCharacterAction $createCharacterAction,
        private readonly DeleteCharacterAction $deleteCharacterAction,
        private readonly UpdateCharacterAction $updateCharacterAction,
        private readonly UpdateCharacterInlineAction $updateCharacterInlineAction,
    ) {}

    public function index(Request $request): View
    {
        $user = $this->authenticatedUser($request);
        $selectedWorldSlug = trim((string) $request->query('world', (string) $request->session()->get('world_slug', World::defaultSlug())));
        $selectedStatus = (string) $request->query('status', 'all');
        $indexData = $this->buildCharacterIndexDataAction->execute(
            user: $user,
            selectedWorldSlug: $selectedWorldSlug,
            selectedStatus: $selectedStatus,
        );

        if ($indexData->selectedWorld instanceof World) {
            $request->session()->put('world_slug', $indexData->selectedWorld->slug);
        }

        return view('characters.index', [
            'characters' => $indexData->characters,
            'worlds' => $indexData->worlds,
            'selectedWorld' => $indexData->selectedWorld,
            'selectedStatus' => $indexData->selectedStatus,
            'characterStatuses' => $indexData->characterStatuses,
        ]);
    }

    public function create(Request $request): View
    {
        $selectedWorldSlug = trim((string) $request->query('world', (string) $request->session()->get('world_slug', World::defaultSlug())));
        $createData = $this->buildCharacterCreateDataAction->execute($selectedWorldSlug);

        return view('characters.create', [
            'worlds' => $createData->worlds,
            'selectedWorld' => $createData->selectedWorld,
        ]);
    }

    public function store(StoreCharacterRequest $request): RedirectResponse
    {
        $character = $this->createCharacterAction->execute($request);

        return redirect()
            ->route('characters.show', $character)
            ->with('status', 'Charakter erstellt.');
    }

    public function show(Character $character): View|RedirectResponse
    {
        $this->ensureCanManageCharacter($character);

        try {
            $showData = $this->buildCharacterShowDataAction->execute($character);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('characters.index')
                ->with('error', 'Charakterdetails konnten nicht geladen werden.');
        }

        return view('characters.show', [
            'character' => $showData->character,
            'inventoryLogs' => $showData->inventoryLogs,
            'progressionEvents' => $showData->progressionEvents,
            'progressionState' => $showData->progressionState,
        ]);
    }

    public function edit(Character $character): View
    {
        $this->ensureCanManageCharacter($character);
        $editData = $this->buildCharacterEditDataAction->execute();

        return view('characters.edit', [
            'character' => $character,
            'worlds' => $editData->worlds,
        ]);
    }

    public function update(UpdateCharacterRequest $request, Character $character): RedirectResponse
    {
        $this->ensureCanManageCharacter($character);

        $this->updateCharacterAction->execute($request, $character);

        return redirect()
            ->route('characters.show', $character)
            ->with('status', 'Charakter aktualisiert.');
    }

    public function inlineUpdate(Request $request, Character $character): View|RedirectResponse
    {
        $this->ensureCanManageCharacter($character);
        $result = $this->updateCharacterInlineAction->execute($request, $character);

        if ($result->shouldRenderFragment) {
            return view('characters.partials.inline-editor', ['character' => $result->character]);
        }

        return redirect()
            ->route('characters.show', $result->character)
            ->with('status', 'Charakter-Schnellbearbeitung gespeichert.');
    }

    public function destroy(Character $character): RedirectResponse
    {
        $this->ensureCanDeleteCharacter($character);

        try {
            $this->deleteCharacterAction->execute($character);
        } catch (ModelNotFoundException) {
            return redirect()
                ->route('characters.index')
                ->with('status', 'Charakter war bereits gelöscht.');
        } catch (CharacterDeletionFailedException $exception) {
            report($exception);

            return redirect()
                ->route('characters.index')
                ->with('error', 'Charakter konnte nicht gelöscht werden.');
        }

        return redirect()
            ->route('characters.index')
            ->with('status', 'Charakter gelöscht.');
    }

    private function ensureCanDeleteCharacter(Character $character): void
    {
        $user = auth()->user();

        abort_unless(
            $user instanceof \App\Models\User
                && ((int) $character->user_id === (int) $user->id || $user->isGmOrAdmin()),
            403
        );
    }

    private function ensureCanManageCharacter(Character $character): void
    {
        $user = auth()->user();

        abort_unless(
            $user instanceof \App\Models\User
                && ((int) $character->user_id === (int) $user->id || $user->isGmOrAdmin()),
            403
        );
    }
}
