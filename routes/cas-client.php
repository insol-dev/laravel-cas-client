<?php

use Illuminate\Support\Facades\Route;
use CasSystem\LaravelClient\Controllers\CasController;

Route::prefix(config('cas-client.routes.prefix', 'cas'))
    ->middleware(config('cas-client.routes.middleware', ['web']))
    ->group(function () {
        
        // CAS Login redirect
        Route::get('/login', [CasController::class, 'login'])->name('cas.login');
        
        // CAS Callback handler
        Route::get('/callback', [CasController::class, 'callback'])->name('cas.callback');
        
        // CAS Logout
        Route::post('/logout', [CasController::class, 'logout'])->name('cas.logout');
        
        // User endpoint
        Route::get('/user', [CasController::class, 'user'])->name('cas.user');

        // Internal CAS validation endpoint
        Route::post('/auth/validate', [CasController::class, 'validateCredentials'])->name('cas.validate.internal');
    });

// Global validation endpoint for One System compatibility
Route::middleware(['api'])->post('/auth/validate', [CasController::class, 'validateCredentials'])->name('cas.validate.global');