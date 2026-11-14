<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function login(): View
    {
        return view('auth.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::validate($credentials)) {
            return back()->withErrors(['email' => __('These credentials do not match our records.')])
                ->onlyInput('email');
        }

        $user = User::query()->where('email', $credentials['email'])->firstOrFail();

        if ($user->mfa_enabled_at) {
            $request->session()->regenerate();
            $request->session()->put('pending_mfa_user_id', $user->id);
            $request->session()->put('pending_mfa_remember', $request->boolean('remember'));

            return redirect()->route('mfa.challenge');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showRegister(): View
    {
        abort_unless(config('edvora.self_registration'), 404);

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        abort_unless(config('edvora.self_registration'), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = User::create($validated); // Password hashed by User cast.
        Auth::login($user);
        $request->session()->regenerate();

        // Never auto-enroll or auto-assign a privileged role.
        return redirect()->route('dashboard');
    }

    public function forgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function emailResetLink(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($validated);

        // Do not reveal whether the email is registered.
        return back()->with('status', __('If that account exists, a password reset link has been sent.'));
    }

    public function resetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $data,
            static function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => \Illuminate\Support\Str::random(60),
                ])->save();
            }
        );

        return $status === Password::PasswordReset
            ? redirect()->route('login')->with('status', __('Your password has been updated.'))
            : back()->withErrors(['email' => __($status)]);
    }
}
