<?php

namespace App\Http\Controllers;

use App\Http\Requests\World\StoreWorldRequest;
use App\Http\Requests\World\UpdateWorldRequest;
use App\Models\World;
use Illuminate\Http\RedirectResponse;
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
        World::query()->create($request->validated());

        return redirect()
            ->route('admin.worlds.index')
            ->with('status', 'Welt erstellt.');
    }

    public function edit(World $world): View
    {
        return view('worlds.admin.edit', compact('world'));
    }

    public function update(UpdateWorldRequest $request, World $world): RedirectResponse
    {
        $world->update($request->validated());

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', 'Welt aktualisiert.');
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
