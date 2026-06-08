<?php

namespace App\Http\Controllers;

use App\Actions\Admin\CreateAdminManagedUserAction;
use App\Actions\Admin\DeleteAdminManagedUserAction;
use App\Actions\Admin\UpdateAdminManagedUserAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly CreateAdminManagedUserAction $createAdminManagedUserAction,
        private readonly UpdateAdminManagedUserAction $updateAdminManagedUserAction,
        private readonly DeleteAdminManagedUserAction $deleteAdminManagedUserAction,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->normalizedFilters($request);

        $users = User::query()
            ->where('email', '!=', User::DELETED_USER_SYSTEM_EMAIL)
            ->withCount(['ownedCampaigns', 'campaignMemberships'])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $searchTerm = '%'.$filters['q'].'%';
                $query->where(function ($innerQuery) use ($searchTerm): void {
                    $innerQuery
                        ->where('name', 'like', $searchTerm)
                        ->orWhere('email', 'like', $searchTerm);
                });
            })
            ->when($filters['role'] !== 'all', fn ($query) => $query->where('role', $filters['role']))
            ->when($filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['sl'] !== 'all', fn ($query) => $query->where('can_create_campaigns', $filters['sl'] === '1'))
            ->when($filters['moderation'] !== 'all', fn ($query) => $query->where('can_post_without_moderation', $filters['moderation'] === '1'))
            ->orderByRaw("CASE role WHEN 'admin' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'filters'));
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(StoreAdminUserRequest $request): RedirectResponse
    {
        $actor = $this->authenticatedUser($request);

        /** @var array{name: string, email: string, password: string, role: string, status: string, can_create_campaigns: bool, can_post_without_moderation: bool, status_reason?: string|null} $validated */
        $validated = $request->validated();

        try {
            $user = $this->createAdminManagedUserAction->execute($actor, $validated);
        } catch (ValidationException $exception) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', 'Benutzer '.$user->name.' erstellt.');
    }

    public function show(User $user): View
    {
        $user->loadCount([
            'ownedCampaigns',
            'campaignMemberships',
            'characters',
            'posts',
        ]);

        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        abort_if($user->isDeletedUserSystemAccount(), 404);

        return view('admin.users.edit', compact('user'));
    }

    public function update(UpdateAdminUserRequest $request, User $user): RedirectResponse
    {
        $actor = $this->authenticatedUser($request);

        /** @var array{name: string, email: string, password?: string|null, role: string, status: string, can_create_campaigns: bool, can_post_without_moderation: bool, status_reason?: string|null} $validated */
        $validated = $request->validated();

        try {
            $this->updateAdminManagedUserAction->execute($actor, $user, $validated);
        } catch (ValidationException $exception) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', 'Benutzer '.$user->name.' aktualisiert.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $this->authenticatedUser($request);
        $userName = $user->name;

        try {
            $this->deleteAdminManagedUserAction->execute($actor, $user);
        } catch (ValidationException $exception) {
            return back()->withErrors([
                'user' => $this->firstValidationMessage($exception),
            ]);
        }

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Benutzer '.$userName.' endgültig entfernt.');
    }

    /**
     * @return array{q: string, role: string, status: string, sl: string, moderation: string}
     */
    private function normalizedFilters(Request $request): array
    {
        $role = (string) $request->query('role', 'all');
        if (! in_array($role, ['all', UserRole::PLAYER->value, UserRole::ADMIN->value], true)) {
            $role = 'all';
        }

        $status = (string) $request->query('status', 'all');
        if (! in_array($status, ['all', UserStatus::PENDING->value, UserStatus::ACTIVE->value, UserStatus::SUSPENDED->value], true)) {
            $status = 'all';
        }

        $sl = (string) $request->query('sl', 'all');
        if (! in_array($sl, ['all', '0', '1'], true)) {
            $sl = 'all';
        }

        $moderation = (string) $request->query('moderation', 'all');
        if (! in_array($moderation, ['all', '0', '1'], true)) {
            $moderation = 'all';
        }

        return [
            'q' => trim((string) $request->query('q', '')),
            'role' => $role,
            'status' => $status,
            'sl' => $sl,
            'moderation' => $moderation,
        ];
    }

    private function firstValidationMessage(ValidationException $exception): string
    {
        foreach ($exception->errors() as $messages) {
            $firstMessage = $messages[0] ?? null;

            if (is_string($firstMessage) && $firstMessage !== '') {
                return $firstMessage;
            }
        }

        return 'Benutzer konnte nicht entfernt werden.';
    }
}
