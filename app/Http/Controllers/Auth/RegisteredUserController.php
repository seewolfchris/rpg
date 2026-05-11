<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(
        private readonly RegisterUserAction $registerUserAction,
    ) {}

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        /** @var array{name: string, email: string, password: string, terms_accepted: bool} $validated */
        $validated = $request->validated();
        $user = $this->registerUserAction->execute($validated);

        event(new Registered($user));

        return redirect()
            ->route('login')
            ->with('status', 'Account wurde erstellt und wartet auf Freischaltung.');
    }
}
