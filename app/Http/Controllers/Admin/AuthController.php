<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Show login form
     */
    public function showLogin()
    {
        return view('admin.auth.login');
    }

    /**
     * Handle login request
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');
        $remember = $request->filled('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            if (!Auth::user()->is_active) {
                Auth::logout();
                throw ValidationException::withMessages([
                    'email' => ['Your account is deactivated.'],
                ]);
            }

            // Check if user is admin
            if (!Auth::user()->isAdmin()) {
                Auth::logout();
                throw ValidationException::withMessages([
                    'email' => ['You do not have admin access.'],
                ]);
            }

            return redirect()->intended(route('admin.dashboard'))
                ->with('success', 'Welcome back, ' . Auth::user()->name . '!');
        }

        throw ValidationException::withMessages([
            'email' => ['The provided credentials do not match our records.'],
        ]);
    }

    /**
     * Handle logout request
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')
            ->with('success', 'You have been logged out successfully.');
    }
}

