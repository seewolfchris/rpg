<?php

namespace App\Http\Controllers;

use App\Models\User;
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

        return view('gm.index');
    }
}
