<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
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
        /** @var array{name: string, email: string, password: string} $validated */
        $validated = $request->validated();
        $user = $this->registerUserAction->execute($validated);

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
