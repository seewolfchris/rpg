<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountStatusController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $this->authenticatedUser($request);

        return view('auth.account-status', compact('user'));
    }
}
