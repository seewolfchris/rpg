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
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $stagedAvatar = $this->stageAvatarUpload($request);
        $replaceAvatar = $stagedAvatar !== null;
        $removeAvatar = $request->boolean('remove_avatar');
        $previousAvatarPath = is_string($character->avatar_path) && $character->avatar_path !== ''
            ? $character->avatar_path
            : null;

        try {
            DB::transaction(function () use (
                $character,
                $data,
                $previousInventory,
                $replaceAvatar,
                $removeAvatar,
                $previousAvatarPath,
                $stagedAvatar
            ): void {
                $character->fill($data);

                if ($removeAvatar && ! $replaceAvatar) {
                    $character->avatar_path = null;
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

                if ($replaceAvatar) {
                    DB::afterCommit(function () use ($character, $stagedAvatar, $previousAvatarPath): void {
                        if ($stagedAvatar === null) {
                            return;
                        }

                        $this->finalizeAvatarReplacement($character, $stagedAvatar, $previousAvatarPath);
                    });

                    return;
                }

                if ($removeAvatar && $previousAvatarPath !== null) {
                    DB::afterCommit(function () use ($previousAvatarPath): void {
                        $this->deletePublicFile($previousAvatarPath);
                    });
                }
            });
        } catch (Throwable $exception) {
            $this->discardStagedAvatar($stagedAvatar);

            throw $exception;
        }

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

    /**
     * @return array{disk: string, staged_path: string, extension: string}|null
     */
    private function stageAvatarUpload(Request $request): ?array
    {
        if (! $request->hasFile('avatar')) {
            return null;
        }

        $file = $request->file('avatar');
        if ($file === null) {
            return null;
        }

        $stagedPath = $file->store('character-avatars/staged', 'public');
        if (! is_string($stagedPath) || trim($stagedPath) === '') {
            throw new \RuntimeException('Avatar-Upload konnte nicht zwischengespeichert werden.');
        }

        $extension = strtolower((string) $file->extension());

        return [
            'disk' => 'public',
            'staged_path' => $stagedPath,
            'extension' => $extension !== '' ? $extension : 'jpg',
        ];
    }

    /**
     * @param  array{disk: string, staged_path: string, extension: string}|null  $stagedAvatar
     */
    private function discardStagedAvatar(?array $stagedAvatar): void
    {
        if ($stagedAvatar === null) {
            return;
        }

        $disk = Storage::disk($stagedAvatar['disk']);
        $stagedPath = $stagedAvatar['staged_path'];

        if ($disk->exists($stagedPath)) {
            $disk->delete($stagedPath);
        }
    }

    /**
     * @param  array{disk: string, staged_path: string, extension: string}  $stagedAvatar
     */
    private function finalizeAvatarReplacement(Character $character, array $stagedAvatar, ?string $previousAvatarPath): void
    {
        $disk = Storage::disk($stagedAvatar['disk']);
        $stagedPath = $stagedAvatar['staged_path'];
        $finalPath = 'character-avatars/'.$character->id.'-'.Str::uuid().'.'.$stagedAvatar['extension'];

        try {
            if (! $disk->exists($stagedPath)) {
                throw new \RuntimeException('Zwischengespeicherter Avatar fehlt bei Finalisierung.');
            }

            if (! $disk->move($stagedPath, $finalPath)) {
                throw new \RuntimeException('Zwischengespeicherter Avatar konnte nicht finalisiert werden.');
            }

            $updated = $character->newQuery()
                ->whereKey($character->getKey())
                ->update(['avatar_path' => $finalPath]);

            if ($updated !== 1) {
                throw new \RuntimeException('Avatar-Pfad konnte nach Finalisierung nicht persistiert werden.');
            }

            $character->avatar_path = $finalPath;

            if (
                is_string($previousAvatarPath)
                && $previousAvatarPath !== ''
                && $previousAvatarPath !== $finalPath
            ) {
                $this->deletePublicFile($previousAvatarPath);
            }
        } catch (Throwable $exception) {
            if ($disk->exists($stagedPath)) {
                $disk->delete($stagedPath);
            }

            if ($disk->exists($finalPath)) {
                $disk->delete($finalPath);
            }

            report($exception);
        }
    }

    private function deletePublicFile(string $path): void
    {
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }
}
