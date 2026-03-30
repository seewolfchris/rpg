<?php

namespace App\Http\Controllers;

use App\Actions\Character\CreateCharacterAction;
use App\Actions\Character\DeleteCharacterAction;
use App\Actions\Character\UpdateCharacterAction;
use App\Actions\Character\UpdateCharacterInlineAction;
use App\Domain\Character\CharacterProgressionService;
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
        private readonly CharacterProgressionService $progressionService,
        private readonly CreateCharacterAction $createCharacterAction,
        private readonly DeleteCharacterAction $deleteCharacterAction,
        private readonly UpdateCharacterAction $updateCharacterAction,
        private readonly UpdateCharacterInlineAction $updateCharacterInlineAction,
    ) {}

    public function index(Request $request): View
    {
        $user = $this->authenticatedUser($request);
        $selectedWorldSlug = trim((string) $request->query('world', (string) $request->session()->get('world_slug', World::defaultSlug())));
        $characterStatusOptions = array_keys((array) config('characters.statuses', []));
        $selectedStatus = (string) $request->query('status', 'all');

        if (! in_array($selectedStatus, array_merge(['all'], $characterStatusOptions), true)) {
            $selectedStatus = 'all';
        }

        $worlds = World::query()->active()->ordered()->get(['id', 'name', 'slug']);
        $selectedWorld = $worlds->firstWhere('slug', $selectedWorldSlug) ?? $worlds->first();
        $selectedWorldId = $selectedWorld instanceof World ? (int) $selectedWorld->id : null;

        if ($selectedWorld) {
            $request->session()->put('world_slug', $selectedWorld->slug);
        }

        $characters = Character::query()
            ->when($selectedWorldId !== null, fn ($query) => $query->where('world_id', $selectedWorldId))
            ->when($selectedStatus !== 'all', fn ($query) => $query->where('status', $selectedStatus))
            ->when(
                ! $user->isGmOrAdmin(),
                fn ($query) => $query->where('user_id', $user->id)
            )
            ->with(['user', 'world'])
            ->latest()
            ->paginate(12);

        return view('characters.index', [
            'characters' => $characters,
            'worlds' => $worlds,
            'selectedWorld' => $selectedWorld,
            'selectedStatus' => $selectedStatus,
            'characterStatuses' => (array) config('characters.statuses', []),
        ]);
    }

    public function create(Request $request): View
    {
        $worlds = World::query()->active()->ordered()->get(['id', 'name', 'slug']);
        $selectedWorldSlug = trim((string) $request->query('world', (string) $request->session()->get('world_slug', World::defaultSlug())));
        $selectedWorld = $worlds->firstWhere('slug', $selectedWorldSlug) ?? $worlds->first();

        return view('characters.create', compact('worlds', 'selectedWorld'));
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
            $inventoryLogs = $character->inventoryLogs()
                ->with('actor:id,name')
                ->limit(25)
                ->get();
            $progressionEvents = $character->progressionEvents()
                ->with(['actorUser:id,name', 'campaign:id,title', 'scene:id,title'])
                ->limit(20)
                ->get();
            $progressionState = $this->progressionService->describe($character);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('characters.index')
                ->with('error', 'Charakterdetails konnten nicht geladen werden.');
        }

        return view('characters.show', compact('character', 'inventoryLogs', 'progressionEvents', 'progressionState'));
    }

    public function edit(Character $character): View
    {
        $this->ensureCanManageCharacter($character);

        $worlds = World::query()->active()->ordered()->get(['id', 'name', 'slug']);

        return view('characters.edit', compact('character', 'worlds'));
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
