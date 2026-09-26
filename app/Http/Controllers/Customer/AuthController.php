<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Customer auth — phone + password against the `users` table via Laravel's
 * default `web` guard (Auth is column-agnostic, so Auth::attempt() works
 * fine with a `phone` column instead of `email`). This is a completely
 * separate guard/session from the Filament `admin` guard used for
 * /admin — see config/auth.php. A customer session can never satisfy an
 * admin-guard check, and vice versa.
 */
class AuthController extends Controller
{
    public function showLogin()
    {
        return view('customer.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $phone = $this->normalizePhone($credentials['phone']);

        if (! Auth::guard('web')->attempt(['phone' => $phone, 'password' => $credentials['password']], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'phone' => 'ফোন নম্বর অথবা পাসওয়ার্ড সঠিক নয়।',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function showRegister()
    {
        return view('customer.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $phone = $this->normalizePhone($data['phone']);

        if (User::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'এই ফোন নম্বর দিয়ে ইতিমধ্যে একাউন্ট আছে।',
            ]);
        }

        $user = User::create([
            'name' => $data['name'],
            'phone' => $phone,
            'password' => Hash::make($data['password']),
        ]);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('home');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function normalizePhone(string $raw): string
    {
        return preg_replace('/[^0-9]/', '', $raw) ?? '';
    }
}
