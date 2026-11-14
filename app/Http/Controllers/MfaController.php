<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MfaService;
use App\Services\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class MfaController extends Controller
{
    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $this->hasValidChallenge($request)) {
            return redirect()->route('login');
        }

        return view('auth.mfa-challenge');
    }

    public function completeChallenge(Request $request, MfaService $mfa): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
        ]);

        if (! $this->hasValidChallenge($request)) {
            return redirect()->route('login')->withErrors(['code' => 'Your verification period expired. Sign in again.']);
        }

        $id = $request->session()->get('pending_mfa_user_id');
        $user = $id ? User::query()->find($id) : null;
        if (! $user || ! $user->mfa_enabled_at) {
            $request->session()->forget(['pending_mfa_user_id', 'pending_mfa_remember', 'pending_mfa_issued_at']);

            return redirect()->route('login')->withErrors(['email' => 'Sign in again.']);
        }

        if (! $mfa->verifyAndConsume($user, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Invalid or already used authentication code.']);
        }

        $remember = (bool) $request->session()->pull('pending_mfa_remember', false);
        $request->session()->forget(['pending_mfa_user_id', 'pending_mfa_issued_at']);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    private function hasValidChallenge(Request $request): bool
    {
        $id = $request->session()->get('pending_mfa_user_id');
        $issued = $request->session()->get('pending_mfa_issued_at');
        if (! is_numeric($id) || ! is_numeric($issued)
            || (int) $issued > now()->timestamp
            || now()->timestamp - (int) $issued > 300) {
            $request->session()->forget(['pending_mfa_user_id', 'pending_mfa_remember', 'pending_mfa_issued_at']);
            return false;
        }

        return true;
    }

    public function cancelChallenge(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function setup(Request $request, MfaService $mfa, Totp $totp): View
    {
        $user = $request->user();
        if ($user->mfa_enabled_at) {
            return view('auth.mfa-enabled');
        }

        $secret = $mfa->begin($user);

        return view('auth.mfa-setup', [
            'secret' => $secret,
            'provisioningUri' => $totp->provisioningUri($secret, $user->email),
        ]);
    }

    public function enable(Request $request, MfaService $mfa): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'regex:/^[0-9]{6}$/D'],
        ]);

        $codes = $mfa->enable($request->user(), $data['password'], $data['code']);

        return redirect()->route('mfa.recovery')->with('new_recovery_codes', $codes)
            ->with('status', 'Two-factor authentication enabled. Store your recovery codes in a safe location.');
    }

    public function recoveryCodes(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->get('new_recovery_codes');
        if (! is_array($codes) || count($codes) !== 8) {
            return redirect()->route('mfa.setup');
        }

        return view('auth.mfa-recovery', ['codes' => $codes]);
    }
}
