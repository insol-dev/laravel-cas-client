# Laravel CAS Client Package

A Laravel package for seamless single sign-on against **One System** (the CAS server). This package provides secure SSO authentication with JWT tokens, optional HMAC signature validation, and role-based access control.

## Requirements

- PHP `^7.2 | ^7.3 | ^7.4 | ^8.0` (PHP 8.1+ recommended)
- Laravel `^7.0 | ^8.0 | ^9.0 | ^10.0 | ^11.0 | ^12.0`
- `firebase/php-jwt ^6.0`, `guzzlehttp/guzzle ^7.0` (installed automatically)

## Features

- 🔐 **Secure SSO Authentication** - JWT token-based authentication
- 🛡️ **Signature Validation** - HMAC SHA-256 request signing
- 👥 **Role-Based Access Control** - Middleware for role protection
- 🔧 **Easy Configuration** - Environment-based setup
- 📝 **Comprehensive Logging** - Authentication event tracking
- ⚡ **Performance Optimized** - Token caching and validation
- 🎯 **Laravel Integration** - Native Laravel guards and middleware

## Installation

### 1. Install via Composer

```bash
composer require cas-system/laravel-client
```

### 2. Run the Installer (recommended)

```bash
php artisan cas:install
```

The installer publishes the config and seeds the required `.env` keys. The package stores CAS authentication state in Laravel's session and cache, so it does **not** add database columns and you do **not** need to run a package migration.

Prefer to do it by hand? Publish just the config with the `cas-client-config` tag:

```bash
php artisan vendor:publish --tag=cas-client-config
```

### 3. Configure Environment Variables

Add the following to your `.env` file. The `client_id` / `client_secret` are issued when you register this client in **One System** — store the secret server-side only, never in browser code:

```env
# One System (CAS) server
CAS_SERVER_URL=http://127.0.0.1:8001
CAS_CLIENT_ID=your_client_id
CAS_CLIENT_SECRET=your_client_secret
CAS_CREATE_LOCAL_USERS=true

# Callback the browser is sent back to after login
CAS_CALLBACK_URL=https://yourapp.com/cas/callback

# Security settings (optional — must match the server if enabled)
CAS_ENABLE_SIGNATURE_VALIDATION=true
CAS_SIGNATURE_SECRET=your-shared-signature-secret
```

## Quick Start

### 1. Protect Routes with Middleware

The service provider auto-registers two middleware aliases: `cas.auth` and `cas.role`.

```php
// In routes/web.php

// Any authenticated CAS user
Route::middleware(['cas.auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/profile', [ProfileController::class, 'show']);
});

// Restrict by role — user needs ANY of the listed roles
Route::middleware(['cas.auth', 'cas.role:admin,manager'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
});
```

### 2. Manual Authentication

```php
use CasSystem\LaravelClient\Facades\CasClient;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $returnUrl = $request->query('return_url', route('dashboard'));
        $loginUrl = CasClient::getLoginUrl($returnUrl);
        return redirect($loginUrl);
    }

    public function callback(Request $request)
    {
        $token = $request->query('token');
        
        if (!$token) {
            return redirect()->route('login')->with('error', 'No authentication token provided');
        }

        $user = CasClient::validateToken($token);
        
        if ($user) {
            // Store user data in session
            session([
                'cas_user' => $user,
                'cas_token' => $token,
                'authenticated' => true
            ]);
            
            return redirect()->route('dashboard')->with('success', 'Login successful');
        }
        
        return redirect()->route('login')->with('error', 'Authentication failed');
    }

    public function logout(Request $request)
    {
        $token = session('cas_token');
        
        // Logout from CAS server
        CasClient::logout($token);
        
        // Clear local session
        session()->forget(['cas_user', 'cas_token', 'authenticated']);
        session()->invalidate();
        session()->regenerateToken();
        
        return redirect('/')->with('success', 'Logged out successfully');
    }
}
```

### 3. Access User Data

```php
// In your controllers
public function dashboard(Request $request)
{
    $user = session('cas_user');
    $username = $user['username'];
    $email = $user['email'];
    $roles = $user['roles'] ?? [];
    
    return view('dashboard', compact('user', 'username', 'email', 'roles'));
}

// Check user roles
use CasSystem\LaravelClient\Facades\CasClient;

if (CasClient::userHasRole($user, 'admin')) {
    // User has admin role
}

if (CasClient::userHasAnyRole($user, ['admin', 'manager'])) {
    // User has admin OR manager role
}

if (CasClient::userHasAllRoles($user, ['user', 'verified'])) {
    // User has BOTH user AND verified roles
}
```

### 4. Blade Templates

```blade
{{-- In your Blade templates --}}
@if(session('authenticated'))
    <div class="user-info">
        <h3>Welcome, {{ session('cas_user.name') }}</h3>
        <p>Email: {{ session('cas_user.email') }}</p>
        <p>Roles: {{ implode(', ', session('cas_user.roles', [])) }}</p>
    </div>
    
    <form method="POST" action="{{ route('cas.logout') }}">
        @csrf
        <button type="submit" class="btn btn-danger">Logout</button>
    </form>
@else
    <a href="{{ route('cas.login') }}" class="btn btn-primary">Sign in with One System</a>
@endif
```

## Configuration

### Environment Variables

```env
# Required Settings
CAS_SERVER_URL=http://127.0.0.1:8001              # One System (CAS) server URL
CAS_CLIENT_ID=your_client_id                      # Your registered client ID
CAS_CLIENT_SECRET=your_client_secret              # Client secret (server-side only)

# Callback Configuration
CAS_CALLBACK_URL=https://yourapp.com/cas/callback # Where the server redirects after login

# Security Settings
CAS_ENABLE_SIGNATURE_VALIDATION=true              # Enable HMAC request signing
CAS_SIGNATURE_SECRET=your-shared-signature-secret # HMAC signature secret (must match server)
CAS_VERIFY_SSL=true                               # Verify SSL certificates

# Optional Settings
CAS_TIMEOUT=30                                     # HTTP request timeout (seconds)
CAS_USER_MODEL=App\Models\User                     # Eloquent model for optional local login/provisioning
CAS_USER_DASHBOARD=/dashboard                      # Redirect target after login
CAS_ROUTES_ENABLED=true                            # Register the package's /cas/* routes
CAS_CACHE_ENABLED=true                            # Enable user data caching
CAS_CACHE_TTL=3600                                # Cache time-to-live (seconds)
CAS_LOGGING_ENABLED=true                          # Enable authentication logging
```

### Advanced Configuration

Edit `config/cas-client.php` for advanced options:

```php
return [
    // User management — a local User record is found/created on successful login
    'user' => [
        'create_local_users' => env('CAS_CREATE_LOCAL_USERS', true),
        'model' => env('CAS_USER_MODEL', 'App\Models\Auth\User'),
        'defaults' => [
            'user_type' => 'Guest',
        ],
    ],

    // Route configuration — package routes auto-registered under this prefix
    'routes' => [
        'enabled' => env('CAS_ROUTES_ENABLED', true),
        'prefix' => env('CAS_ROUTES_PREFIX', 'cas'),
        'middleware' => ['web'],
        'user_dashboard' => env('CAS_USER_DASHBOARD', '/dashboard'),
    ],

    // Cache validated user data to cut calls to the server
    'cache' => [
        'enabled' => env('CAS_CACHE_ENABLED', true),
        'ttl' => env('CAS_CACHE_TTL', 3600),
        'prefix' => 'cas_',
    ],

    // Logging configuration
    'logging' => [
        'enabled' => env('CAS_LOGGING_ENABLED', true),
        'channel' => env('CAS_LOG_CHANNEL', 'single'),
        'level' => env('CAS_LOG_LEVEL', 'info'),
    ],
];
```

## Middleware

### CasAuthentication Middleware

Protects routes requiring CAS authentication:

```php
Route::middleware(['cas.auth'])->group(function () {
    Route::get('/protected', [Controller::class, 'method']);
});
```

### CasRole Middleware

Protects routes requiring specific roles:

```php
// Single role
Route::middleware(['cas.auth', 'cas.role:admin'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
});

// Multiple roles (user needs ANY of these roles)
Route::middleware(['cas.auth', 'cas.role:admin,manager,supervisor'])->group(function () {
    Route::get('/management', [ManagementController::class, 'index']);
});
```

## API Reference

### CasAuthService Methods

```php
// Build the SSO login URL -> {CAS_SERVER_URL}/sso/login?client_id=...
$loginUrl = CasClient::getLoginUrl($returnUrl);

// Validate a single-use token SERVER-TO-SERVER.
// POST {CAS_SERVER_URL}/api/sso/validate  { token, client_id, client_secret }
// On 200 returns the user array { id, username, email, ... }, else null.
$user = CasClient::validateToken($token);

// Get cached user data (no network call)
$user = CasClient::getUserFromToken($token);

// Service-to-service token issuance for a known user.
// POST {CAS_SERVER_URL}/api/sso/token  { client_id, client_secret, username }
$result = CasClient::generateSSOToken('jane.doe'); // ['token' => ..., 'redirect_url' => ...]

// Logout -> POST {CAS_SERVER_URL}/api/logout
$success = CasClient::logout($token);

// Role checking helpers
$hasRole = CasClient::userHasRole($user, 'admin');
$hasAnyRole = CasClient::userHasAnyRole($user, ['admin', 'manager']);
$hasAllRoles = CasClient::userHasAllRoles($user, ['user', 'verified']);
```

### User Data Structure

```php
$user = [
    'id' => 1,
    'username' => 'john_doe',
    'email' => 'john@example.com',
    'name' => 'John Doe',
    'roles' => ['user', 'manager'],
    // Additional fields from CAS server
];
```

## Security Features

### Signature Validation

When enabled, all requests to the CAS server are signed with HMAC SHA-256:

```php
// Automatic signature generation
$signature = hash_hmac('sha256', $payload, $secret);
```

The payload includes:
- HTTP method
- Request URI
- Request body
- Timestamp
- Client ID

### Token Caching

User data is cached to reduce CAS server load:

```php
// Cached for performance
Cache::put("cas_user_{$token}", $userData, $ttl);
```

### Error Handling

Comprehensive error handling for all CAS operations:

```php
try {
    $user = CasClient::validateToken($token);
} catch (CasAuthException $e) {
    Log::error('CAS authentication failed', ['error' => $e->getMessage()]);
}
```

## Troubleshooting

### Common Issues

1. **Authentication Loop**
   - Check `CAS_CALLBACK_URL` matches your route
   - Verify session configuration
   - Ensure middleware order is correct

2. **Token Validation Fails**
   - Verify client credentials in CAS server
   - Check `CAS_SIGNATURE_SECRET` if using signatures
   - Ensure CAS server is accessible

3. **Role Access Denied**
   - Verify user has required roles in CAS
   - Check role middleware configuration
   - Ensure roles are properly synced

### Debug Mode

Enable debug logging:

```env
CAS_LOGGING_ENABLED=true
CAS_LOG_LEVEL=debug
```

### Testing

Test your configuration:

```bash
# Test One System (CAS) server connectivity
curl -I http://127.0.0.1:8001/health

# Test token validation
php artisan tinker
>>> app(\CasSystem\LaravelClient\Services\CasAuthService::class)->validateToken('your-test-token');
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Support

For support, please contact your CAS system administrator or create an issue in the project repository.

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details on how to contribute to this package.

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for details on recent changes.
