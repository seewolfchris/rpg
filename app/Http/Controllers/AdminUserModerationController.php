<?php

namespace App\Http\Controllers;

use App\Actions\Admin\UpdateUserModerationPermissionAction;
use App\Http\Requests\Admin\UpdateUserModerationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminUserModerationController extends Controller
{
    public function __construct(
        private readonly UpdateUserModerationPermissionAction $updateUserModerationPermissionAction,
    ) {}

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

    public function update(UpdateUserModerationRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $isEnabled = (bool) $validated['can_post_without_moderation'];

        try {
            $this->updateUserModerationPermissionAction->execute($user, $isEnabled);
        } catch (ValidationException $exception) {
            return back()->withErrors([
                'user' => $this->firstValidationMessage($exception),
            ]);
        }

        return redirect()
            ->route('admin.users.moderation.index', ['q' => $request->query('q')])
            ->with('status', 'Moderationsrecht für '.$user->name.' aktualisiert.');
    }

    private function firstValidationMessage(ValidationException $exception): string
    {
        foreach ($exception->errors() as $messages) {
            $firstMessage = $messages[0] ?? null;

            if (is_string($firstMessage) && $firstMessage !== '') {
                return $firstMessage;
            }
        }

        return 'Moderationsrecht konnte nicht aktualisiert werden.';
    }
}
