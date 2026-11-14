<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\InstitutionAccess;
use App\Services\SecurityAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class AccountController extends Controller
{
    public function index(Request $request, InstitutionAccess $access): View
    {
        $user = $request->user();

        $memberships = $user->memberships()->where('status', 'active')
            ->with('institution')->get()
            ->filter(fn ($membership): bool => $membership->institution !== null
                && $access->canViewInstitution($user, $membership->institution))
            ->values();

        // Never expose events where this account is merely the subject of another user's work.
        // Metadata, affected student IDs and the identities of other actors remain private.
        $recentEvents = DB::table('security_events')
            ->where('actor_user_id', $user->id)
            ->select('id', 'event_type', 'created_at')
            ->orderByDesc('id')
            ->simplePaginate(10);

        return view('account.index', compact('user', 'memberships', 'recentEvents'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $user = $request->user();
        $name = trim($data['name']);

        if ($user->name !== $name) {
            $user->forceFill(['name' => $name])->save();
            app(SecurityAudit::class)->record('profile.updated', $user);
        }

        return back()->with('status', 'Your account profile has been saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => Str::random(60),
        ])->save();
        app(SecurityAudit::class)->record('password.updated', $user);
        $request->session()->regenerate();

        return back()->with('status', 'Password updated. Existing remember-me tokens have been revoked.');
    }
}
