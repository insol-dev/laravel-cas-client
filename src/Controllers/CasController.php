<?php

namespace CasSystem\LaravelClient\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use CasSystem\LaravelClient\Services\CasAuthService;

class CasController extends Controller
{
    protected $casAuth;

    public function __construct(CasAuthService $casAuth)
    {
        $this->casAuth = $casAuth;
    }

    /**
     * Redirect to CAS login
     */
    public function login(Request $request): RedirectResponse
    {
        $returnUrl = $request->query('return_url', route('cas.callback'));
        $loginUrl = $this->casAuth->getLoginUrl($returnUrl);
        
        return redirect($loginUrl);
    }

    /**
     * Handle CAS callback
     */
    public function callback(Request $request)
    {
        $token = $request->query('token');
        logger()->info('CasController: Callback hit', ['token' => substr($token, 0, 10)]); // Log entry
        
        $dashboardPath = config('cas-client.routes.user_dashboard', '/dashboard');

        if (!$token) {
            return redirect($dashboardPath)->withErrors(['cas' => 'No authentication token provided']);
        }

        $user = $this->casAuth->validateToken($token);
        
        if (!$user) {
            return redirect($dashboardPath)->withErrors(['cas' => 'Invalid or expired authentication token']);
        }

        // Store user data in session
        $request->session()->put('cas_token', $token);
        $request->session()->put('cas_user', $user);
        $request->session()->put('authenticated', true);

        // Attempt to login with Laravel Auth
        try {
            $userModel = config('cas-client.user.model', 'App\Models\User');
            $createLocalUsers = config('cas-client.user.create_local_users', false);
            
            logger()->info('Attempting local login for user', ['email' => $user['email'], 'model' => $userModel]);

            $localUser = $userModel::where('email', $user['email'])->first();
            
            if (!$localUser) {
                if ($createLocalUsers) {
                    logger()->info('User not found, creating new user');
                    // Create new user if not exists
                    $localUser = new $userModel();
                    $localUser->name = $user['name'] ?? $user['username'];
                    $localUser->email = $user['email'];
                    $localUser->password = bcrypt(str()->random(16));
                    $localUser->cas_user = true; // Mark as CAS user
                    $localUser->save();
                    logger()->info('New user created', ['id' => $localUser->id]);
                } else {
                    logger()->warning('User not found locally and auto-creation disabled', ['email' => $user['email']]);
                    // User not found and creation disabled
                    // Clear CAS session we just set
                    $request->session()->forget(['cas_token', 'cas_user', 'authenticated']);
                    
                    return redirect('/')->withErrors(['email' => 'User not found in this system.']);
                }
            } else {
                logger()->info('User found', ['id' => $localUser->id]);
            }
            
            \Illuminate\Support\Facades\Auth::login($localUser);
            logger()->info('Auth::login called successfully', ['user_id' => \Illuminate\Support\Facades\Auth::id()]);
        } catch (\Exception $e) {
            logger()->error('Failed to login local user: ' . $e->getMessage());
            logger()->error($e->getTraceAsString());
            
        }
        
        // If this is an API request, return JSON
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'user' => $user,
                'token' => $token
            ]);
        }

        // Redirect to the URL the user originally intended to visit, or fall back to configured dashboard
        return redirect()->intended($dashboardPath)->with('success', 'Successfully logged in via CAS');
    }

    /**
     * Get current user info
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->session()->get('cas_user');
        $token = $request->session()->get('cas_token');

        if (!$user || !$token) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        // Validate token is still valid
        $freshUser = $this->casAuth->getUserFromToken($token);
        if (!$freshUser) {
            // Token expired, try to refresh
            $freshUser = $this->casAuth->validateToken($token);
            if (!$freshUser) {
                $request->session()->forget(['cas_token', 'cas_user', 'authenticated']);
                return response()->json(['error' => 'Token expired'], 401);
            }
        }

        return response()->json([
            'user' => $freshUser,
            'authenticated' => true
        ]);
    }

    /**
     * Logout from CAS
     */
    public function logout(Request $request)
    {
        $token = $request->session()->get('cas_token');
        
        // Logout from CAS server
        $this->casAuth->logout($token);
        
        // Clear local session
        $request->session()->forget(['cas_token', 'cas_user', 'authenticated']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Logged out successfully']);
        }

        return redirect('/')->with('success', 'Logged out successfully');
    }

    /**
     * Validate credentials for CAS system (credential verification endpoint)
     * This endpoint is called by the CAS system to verify user credentials
     */
    public function validateCredentials(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $validationOnly = $request->boolean('validation_only', false);

        $userModel = config('cas-client.user.model', 'App\Models\User');
        
        $user = $userModel::where('email', $request->username)
            ->orWhere('name', $request->username)
            ->first();

        if (!$user) {
            return response()->json([
                'valid' => false,
                'authenticated' => false,
                'message' => 'User not found'
            ], 401);
        }

        // Verify password using Laravel's Hash facade
        if (!\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            return response()->json([
                'valid' => false,
                'authenticated' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Credentials are valid
        if ($validationOnly) {
            return response()->json([
                'valid' => true,
                'authenticated' => true,
                'message' => 'Credentials validated successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'validation_method' => 'password_hash_check'
                ]
            ]);
        } else {
            auth()->login($user);
            return response()->json([
                'valid' => true,
                'authenticated' => true,
                'message' => 'User authenticated successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'session_created' => true
                ]
            ]);
        }
    }
}