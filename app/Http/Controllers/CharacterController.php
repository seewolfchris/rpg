<?php

namespace App\Http\Controllers;

use App\Actions\Character\CreateCharacterAction;
use App\Actions\Character\DeleteCharacterAction;
use App\Domain\Character\CharacterProgressionService;
use App\Exceptions\CharacterDeletionFailedException;
use App\Http\Requests\Character\StoreCharacterRequest;
use App\Http\Requests\Character\UpdateCharacterRequest;
use App\Models\Character;
use App\Models\World;
use App\Services\Character\AttributeNormalizer;
use App\Support\CharacterInventoryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class CharacterController extends Controller
{
    public function __construct(
        private readonly CharacterInventoryService $inventoryService,
        private readonly CharacterProgressionService $progressionService,
        private readonly CreateCharacterAction $createCharacterAction,
        private readonly DeleteCharacterAction $deleteCharacterAction,
        private readonly AttributeNormalizer $attributeNormalizer,
    ) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        $selectedWorldSlug = trim((string) $request->query('world', (string) $request->session()->get('world_slug', World::defaultSlug())));
        $characterStatusOptions = array_keys((array) config('characters.statuses', []));
        $selectedStatus = (string) $request->query('status', 'all');

        if (! in_array($selectedStatus, array_merge(['all'], $characterStatusOptions), true)) {
            $selectedStatus = 'all';
        }

        $worlds = World::query()->active()->ordered()->get(['id', 'name', 'slug']);
        $selectedWorld = $worlds->firstWhere('slug', $selectedWorldSlug) ?? $worlds->first();

        if ($selectedWorld) {
            $request->session()->put('world_slug', $selectedWorld->slug);
        }

        $characters = Character::query()
            ->when($selectedWorld, fn ($query) => $query->where('world_id', $selectedWorld->id))
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
        $previousInventory = $this->inventoryService->normalize($character->inventory ?? []);

        $data = $this->attributeNormalizer->normalizeForUpdate($request, $character);

        $character->fill($data);

        if ($request->boolean('remove_avatar') && $character->avatar_path) {
            Storage::disk('public')->delete($character->avatar_path);
            $character->avatar_path = null;
        }

        if ($request->hasFile('avatar')) {
            if ($character->avatar_path) {
                Storage::disk('public')->delete($character->avatar_path);
            }

            $character->avatar_path = $request->file('avatar')->store('character-avatars', 'public');
        }

        $character->save();
        $nextInventory = $this->inventoryService->normalize($character->inventory ?? []);
        $this->inventoryService->log(
            character: $character,
            actorUserId: (int) auth()->id(),
            source: 'character_sheet_update',
            operations: $this->inventoryService->diff($previousInventory, $nextInventory),
            context: ['character_id' => $character->id],
        );

        return redirect()
            ->route('characters.show', $character)
            ->with('status', 'Charakter aktualisiert.');
    }

    public function inlineUpdate(Request $request, Character $character): View|RedirectResponse
    {
        $this->ensureCanManageCharacter($character);

        $statusOptions = array_keys((array) config('characters.statuses', []));

        $validated = $request->validate([
            'epithet' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in($statusOptions)],
            'bio' => ['required', 'string', 'max:12000'],
            'concept' => ['nullable', 'string', 'max:180'],
            'world_connection' => ['nullable', 'string', 'max:2000'],
            'gm_secret' => ['nullable', 'string', 'max:3000'],
            'gm_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $character->fill($validated);
        $character->save();

        if ($request->header('HX-Request') === 'true') {
            return view('characters.partials.inline-editor', compact('character'));
        }

        return redirect()
            ->route('characters.show', $character)
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
            (int) $character->user_id === (int) $user->id || $user->isGmOrAdmin(),
            403
        );
    }

    private function ensureCanManageCharacter(Character $character): void
    {
        $user = auth()->user();

        abort_unless(
            (int) $character->user_id === (int) $user->id || $user->isGmOrAdmin(),
            403
        );
    }
}
