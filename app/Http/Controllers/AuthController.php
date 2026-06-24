<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'handle' => ['required', 'string', 'max:30', 'alpha_dash', 'unique:profiles,handle'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'], // 'hashed' 캐스트가 해싱
        ]);

        $user->profile()->create([
            'handle' => $validated['handle'],
            'display_name' => $validated['name'],
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('calendar', ['handle' => $user->profile->handle]);
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => '이메일 또는 비밀번호가 올바르지 않아요.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(
            route('calendar', ['handle' => Auth::user()->profile->handle])
        );
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
