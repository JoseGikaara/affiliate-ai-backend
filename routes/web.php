<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicLandingPageController;

// Public landing page routes (must come before catch-all)
Route::get('/lp/{subdomain}', [PublicLandingPageController::class, 'show'])
    ->where('subdomain', '[a-z0-9-]+');
Route::get('/landing/{subdomain}', [PublicLandingPageController::class, 'show'])
    ->where('subdomain', '[a-z0-9-]+');

// Only keep welcome route for landing page
Route::get('/', function () {
    return view('welcome');
});

// All other routes are handled by React frontend
require __DIR__.'/auth.php';
