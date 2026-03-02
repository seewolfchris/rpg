<?php

namespace App\Http\Controllers;

use App\Http\Requests\Character\StoreCharacterRequest;
use App\Http\Requests\Character\UpdateCharacterRequest;
use App\Models\Character;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CharacterController extends Controller
{
    public function index(): View
    {
        $characters = Character::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(12);

        return view('characters.index', compact('characters'));
    }

    public function create(): View
    {
        return view('characters.create');
    }

    public function store(StoreCharacterRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['avatar']);

        $character = new Character($data);
        $character->user_id = auth()->id();

        if ($request->hasFile('avatar')) {
            $character->avatar_path = $request->file('avatar')->store('character-avatars', 'public');
        }

        $character->save();

        return redirect()
            ->route('characters.show', $character)
            ->with('status', 'Charakter erstellt.');
    }

    public function show(Character $character): View
    {
        $this->ensureOwnership($character);

        return view('characters.show', compact('character'));
    }

    public function edit(Character $character): View
    {
        $this->ensureOwnership($character);

        return view('characters.edit', compact('character'));
    }

    public function update(UpdateCharacterRequest $request, Character $character): RedirectResponse
    {
        $this->ensureOwnership($character);

        $data = $request->safe()->except(['avatar', 'remove_avatar']);
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

        return redirect()
            ->route('characters.show', $character)
            ->with('status', 'Charakter aktualisiert.');
    }

    public function destroy(Character $character): RedirectResponse
    {
        $this->ensureOwnership($character);

        if ($character->avatar_path) {
            Storage::disk('public')->delete($character->avatar_path);
        }

        $character->delete();

        return redirect()
            ->route('characters.index')
            ->with('status', 'Charakter geloescht.');
    }

    private function ensureOwnership(Character $character): void
    {
        abort_unless($character->user_id === auth()->id(), 403);
    }
}
