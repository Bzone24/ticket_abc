<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('admin.auth.login.login');
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');
        if (! Auth::guard('admin')->attempt($credentials)) {
            return back()->withErrors(['email' => 'The provided credentials do not match our records. '])->withInput();
        }
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');

    }
}
