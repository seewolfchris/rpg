<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\World;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorldController extends Controller
{
    public function index(Request $request): View
    {
        $worlds = World::query()
            ->active()
            ->ordered()
            ->withCount('campaigns')
            ->get();

        return view('worlds.index', compact('worlds'));
    }

    public function show(World $world): View
    {
        $featuredCampaigns = Campaign::query()
            ->forWorld($world)
            ->where('is_public', true)
            ->with('owner')
            ->latest()
            ->limit(6)
            ->get();

        return view('worlds.show', compact('world', 'featuredCampaigns'));
    }

    public function activate(Request $request, World $world): RedirectResponse
    {
        $request->session()->put('world_slug', $world->slug);

        if ($request->user()) {
            return redirect()->route('campaigns.index', ['world' => $world]);
        }

        return redirect()->route('worlds.show', ['world' => $world]);
    }
}
