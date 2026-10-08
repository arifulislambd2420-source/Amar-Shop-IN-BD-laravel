<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OrderRiskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
    /** Validation messages in Bangla (the app locale has no Bangla translation files). */
    private const MESSAGES = [
        'name.required' => 'আপনার নাম লিখুন।',
        'name.max' => 'নাম অনেক বড় হয়ে গেছে।',
        'phone.required' => 'মোবাইল নম্বর লিখুন।',
        'phone.max' => 'সঠিক মোবাইল নম্বর দিন।',
        'password.required' => 'পাসওয়ার্ড লিখুন।',
        'password.min' => 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।',
        'password.max' => 'পাসওয়ার্ড অনেক বড় হয়ে গেছে।',
    ];

    public function showLogin()
    {
        return view('customer.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string'],
        ], self::MESSAGES);

        // The same number may be stored as 01…, 880… or 1… (older accounts) —
        // log in with whichever form this account was saved under.
        $variants = $this->phoneVariants($credentials['phone']);
        $phone = User::whereIn('phone', $variants)->value('phone') ?? $variants[0];

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
            'phone' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, \Closure $fail) {
                // A Bangladeshi mobile number: 01XXXXXXXXX, optionally with the 88 / +88 prefix.
                if (! preg_match('/^(?:88)?01[3-9]\d{8}$/', $this->normalizePhone((string) $value))) {
                    $fail('সঠিক মোবাইল নম্বর দিন (যেমন 01712345678)।');
                }
            }],
            'password' => ['required', 'string', 'min:6', 'max:255'],
        ], self::MESSAGES);

        // Saved as 01XXXXXXXXX; any other form of the same number counts as taken.
        $variants = $this->phoneVariants($data['phone']);
        $phone = $variants[0];

        if (User::whereIn('phone', $variants)->exists()) {
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

        return redirect()->intended(route('home'));
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

    /** 01XXXXXXXXX first, then the 880… / 1… forms of the same number (see OrderRiskService). */
    private function phoneVariants(string $raw): array
    {
        return app(OrderRiskService::class)->phoneVariants($this->normalizePhone($raw));
    }
}
