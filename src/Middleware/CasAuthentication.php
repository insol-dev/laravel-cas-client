<?php

namespace CasSystem\LaravelClient\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use CasSystem\LaravelClient\Services\CasAuthService;

class CasAuthentication
{
    protected $casAuth;

    public function __construct(CasAuthService $casAuth)
    {
        $this->casAuth = $casAuth;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $guard = null): mixed
    {
        // 1. Check if user is already authenticated in Laravel session
        if (Auth::guard($guard)->check()) {
            return $next($request);
        }

        // 2. Check if we have a valid session from previous CAS cycle
        $sessionUser = $request->session()->get('cas_user');

        $token = $request->query('token') ?: $request->bearerToken();

        if (!$token) {
            // If we have a session user but no token, we might be okay if we trust the session
            if ($sessionUser) {
                return $next($request); 
            }
            return $this->redirectToLogin($request);
        }

        // 3. Validate Token
        $user = $this->casAuth->validateToken($token);
        
        if (!$user) {
            return $this->redirectToLogin($request);
        }

        $request->session()->put('cas_token', $token);
        $request->session()->put('cas_user', $user);

        return $next($request);
    }

    protected function redirectToLogin(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $loginUrl = $this->casAuth->getLoginUrl($request->fullUrl());
        return redirect($loginUrl);
    }
}