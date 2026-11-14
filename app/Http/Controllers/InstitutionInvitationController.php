<?php

namespace App\Http\Controllers;

use App\Enums\AccountRole;
use App\Models\Campus;
use App\Models\Institution;
use App\Models\InstitutionInvitation;
use App\Models\User;
use App\Services\InstitutionInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class InstitutionInvitationController extends Controller
{
    public function store(Request $request, Institution $institution, InstitutionInvitationService $service): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', 'max:40'],
            'campus_id' => ['nullable', 'integer'],
        ]);

        $role = AccountRole::tryFrom($data['role']);
        if (! $role) {
            return back()->withErrors(['role' => 'Unknown account role.']);
        }

        $campus = isset($data['campus_id'])
            ? Campus::query()->where('institution_id', $institution->id)->findOrFail($data['campus_id'])
            : null;

        $service->invite($request->user(), $institution, $data['email'], $role, $campus);

        return back()->with('status', 'Invitation sent to the requested email address.');
    }

    public function revoke(Request $request, Institution $institution, InstitutionInvitation $invitation, InstitutionInvitationService $service): RedirectResponse
    {
        $service->revoke($request->user(), $institution, $invitation);

        return back()->with('status', 'Invitation revoked; its link can no longer be used.');
    }

    public function show(Request $request, string $token): View|RedirectResponse
    {
        $invitation = InstitutionInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->with(['institution', 'role', 'campus'])
            ->firstOrFail();
        abort_unless($invitation->isRedeemable(), 404);

        $existingUser = User::query()->where('email', $invitation->email)->exists();
        if ($request->user()) {
            abort_unless(hash_equals(mb_strtolower($request->user()->email), $invitation->email), 403);
        } elseif ($existingUser) {
            $request->session()->put('url.intended', route('invitations.show', ['token' => $token]));
            return redirect()->route('login')->with('status', 'Sign in with your invited email to accept the invitation.');
        }

        return view('auth.invitation', compact('invitation', 'token', 'existingUser'));
    }

    public function accept(Request $request, string $token, InstitutionInvitationService $service): RedirectResponse
    {
        $invitation = InstitutionInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->with('institution')
            ->firstOrFail();
        abort_unless($invitation->isRedeemable(), 404);

        $existing = User::query()->where('email', $invitation->email)->first();
        $user = $request->user();

        if (! $user) {
            if ($existing) {
                $request->session()->put('url.intended', route('invitations.show', ['token' => $token]));
                return redirect()->route('login');
            }

            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
            ]);

            $user = DB::transaction(function () use ($invitation, $data, $service, $token): User {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $invitation->email,
                    'password' => $data['password'],
                ]);
                // The invitation link proves control of this email.
                $user->forceFill(['email_verified_at' => now()])->save();
                $service->accept($token, $user);
                return $user;
            });
            Auth::login($user);
            $request->session()->regenerate();
        } else {
            abort_unless(hash_equals(mb_strtolower($user->email), $invitation->email), 403);
            $service->accept($token, $user);
        }

        $request->session()->forget('url.intended');

        return redirect()->route('dashboard')
            ->with('status', 'Your institution invitation has been accepted.');
    }
}
