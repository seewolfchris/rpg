<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Throwable;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('status', 'Wenn ein Konto mit dieser E-Mail existiert, wurde ein Reset-Link versendet.');
        }

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        return back()->with('status', 'Wenn ein Konto mit dieser E-Mail existiert, wurde ein Reset-Link versendet.');
    }
}
