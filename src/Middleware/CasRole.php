<?php

namespace CasSystem\LaravelClient\Middleware;

use Closure;
use Illuminate\Http\Request;
use CasSystem\LaravelClient\Services\CasAuthService;

class CasRole
{
    protected $casAuth;

    public function __construct(CasAuthService $casAuth)
    {
        $this->casAuth = $casAuth;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = $request->session()->get('cas_user');

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!empty($roles) && !$this->casAuth->userHasAnyRole($user, $roles)) {
            return response()->json(['message' => 'Insufficient permissions.'], 403);
        }

        return $next($request);
    }
}