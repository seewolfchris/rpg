<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\World;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GmIndexController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && ($user->isAdmin() || $user->hasAnyCoGmCampaignAccess()),
            403
        );

        $activeWorldSlug = (string) $request->attributes->get('active_world_slug', '');

        if ($activeWorldSlug === '') {
            $activeWorldSlug = World::defaultSlug();
        }

        return view('gm.index', compact('activeWorldSlug'));
    }
}
