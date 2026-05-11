<?php

namespace App\Http\Controllers;

use App\Actions\Admin\ApproveUserAction;
use App\Actions\Admin\ReactivateUserAction;
use App\Actions\Admin\SuspendUserAction;
use App\Actions\Admin\UpdateUserModerationPermissionAction;
use App\Http\Requests\Admin\ApproveUserRequest;
use App\Http\Requests\Admin\ReactivateUserRequest;
use App\Http\Requests\Admin\SuspendUserRequest;
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
        private readonly ApproveUserAction $approveUserAction,
        private readonly SuspendUserAction $suspendUserAction,
        private readonly ReactivateUserAction $reactivateUserAction,
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
            ->orderByRaw("CASE role WHEN 'admin' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.users.moderation', compact('users', 'search'));
    }

    public function update(UpdateUserModerationRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $actor = $this->authenticatedUser($request);

        try {
            $this->updateUserModerationPermissionAction->execute($actor, $user, [
                'role' => (string) $validated['role'],
                'can_create_campaigns' => (bool) $validated['can_create_campaigns'],
                'can_post_without_moderation' => (bool) $validated['can_post_without_moderation'],
            ]);
        } catch (ValidationException $exception) {
            return back()->withErrors([
                'user' => $this->firstValidationMessage($exception),
            ]);
        }

        return redirect()
            ->route('admin.users.moderation.index', ['q' => $request->query('q')])
            ->with('status', 'Plattformrechte für '.$user->name.' aktualisiert.');
    }

    public function approve(ApproveUserRequest $request, User $user): RedirectResponse
    {
        $actor = $this->authenticatedUser($request);

        try {
            $this->approveUserAction->execute($actor, $user);
        } catch (ValidationException $exception) {
            return back()->withErrors([
                'user' => $this->firstValidationMessage($exception),
            ]);
        }

        return redirect()
            ->route('admin.users.moderation.index', ['q' => $request->query('q')])
            ->with('status', 'Account für '.$user->name.' freigeschaltet.');
    }

    public function suspend(SuspendUserRequest $request, User $user): RedirectResponse
    {
        $actor = $this->authenticatedUser($request);
        $validated = $request->validated();

        try {
            $this->suspendUserAction->execute($actor, $user, (string) ($validated['status_reason'] ?? ''));
        } catch (ValidationException $exception) {
            return back()->withErrors([
                'user' => $this->firstValidationMessage($exception),
            ]);
        }

        return redirect()
            ->route('admin.users.moderation.index', ['q' => $request->query('q')])
            ->with('status', 'Account für '.$user->name.' gesperrt.');
    }

    public function reactivate(ReactivateUserRequest $request, User $user): RedirectResponse
    {
        $actor = $this->authenticatedUser($request);

        try {
            $this->reactivateUserAction->execute($actor, $user);
        } catch (ValidationException $exception) {
            return back()->withErrors([
                'user' => $this->firstValidationMessage($exception),
            ]);
        }

        return redirect()
            ->route('admin.users.moderation.index', ['q' => $request->query('q')])
            ->with('status', 'Account für '.$user->name.' reaktiviert.');
    }

    private function firstValidationMessage(ValidationException $exception): string
    {
        foreach ($exception->errors() as $messages) {
            $firstMessage = $messages[0] ?? null;

            if (is_string($firstMessage) && $firstMessage !== '') {
                return $firstMessage;
            }
        }

        return 'Benutzerverwaltung konnte nicht aktualisiert werden.';
    }
}
