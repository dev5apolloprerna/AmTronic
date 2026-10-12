<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $user = $token
            ? User::with('designation')->where('api_token', hash('sha256', $token))->first()
            : $this->userFromSignedDocumentLink($request);

        if (! $user || ! $user->canApiLogin()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Bearer tokens expire (see auth.api_token_ttl). Tokens issued before
        // expiry was added have no date; they keep working until next login.
        if ($token && $user->api_token_expires_at?->isPast()) {
            return response()->json(['message' => 'Token expired. Please log in again.'], 401);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
    
    /**
     * PDF links are opened by a browser or the device's PDF viewer, which cannot
     * attach the bearer token used by the API client. A valid Laravel signature
     * safely carries the owner identity without exposing that token in the URL.
     */
    private function userFromSignedDocumentLink(Request $request): ?User
    {
        if (! $request->routeIs('api.*.pdf') || ! $request->hasValidSignature(false)) {
            return null;
        }

        return User::with('designation')->find($request->integer('user'));
    }
}
