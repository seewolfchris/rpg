<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminUserModerationController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $users = User::query()
            ->when($search !== '', function ($query) use ($search): void {
                $searchTerm = '%'.$search.'%';
                $query->where(function ($innerQuery) use ($searchTerm): void {
                    $innerQuery->where('name', 'like', $searchTerm)
                        ->orWhere('email', 'like', $searchTerm);
                });
            })
            ->orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'gm' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.users.moderation', compact('users', 'search'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'can_post_without_moderation' => ['required', 'boolean'],
        ]);

        $isEnabled = (bool) $validated['can_post_without_moderation'];
        $isTargetPlayer = $user->hasRole(UserRole::PLAYER);

        if (! $isTargetPlayer && $isEnabled) {
            return back()->withErrors([
                'user' => 'Das Recht kann nur für Spieler aktiviert werden.',
            ]);
        }

        $user->can_post_without_moderation = $isTargetPlayer ? $isEnabled : false;
        $user->save();

        return redirect()
            ->route('admin.users.moderation.index', ['q' => $request->query('q')])
            ->with('status', 'Moderationsrecht für '.$user->name.' aktualisiert.');
    }
}
