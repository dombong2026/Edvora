<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequirePrivilegedMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $privilegedRoles = ['administrator', 'director', 'subdirector', 'manager', 'dean', 'supervisor', 'cashier'];
        $privileged = $user->isPlatformAdmin()
            || $user->roleAssignments()
                ->whereHas('role', fn ($query) => $query->whereIn('code', $privilegedRoles))
                ->exists();

        if ($privileged && ! $user->mfa_enabled_at) {
            return redirect()->route('mfa.setup');
        }

        return $next($request);
    }
}
