<?php

use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\RedirectController;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Catalog\PublicCatalog;
use App\Livewire\Dashboard\CatalogSettings;
use App\Livewire\Dashboard\Overview;
use App\Livewire\Dashboard\ProductDiscovery;
use App\Http\Controllers\Auth\GoogleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// System Health Check Endpoints (for uptime monitors & load balancers)
Route::get('/health', HealthCheckController::class)->name('health');
Route::get('/api/health', HealthCheckController::class)->name('api.health');

// Public Landing Page
Route::get('/', function () {
    return view('welcome');
})->name('home');

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    
    // Google OAuth Routes
    Route::get('/auth/google', [GoogleController::class, 'redirect'])->middleware('throttle:10,1')->name('auth.google');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->middleware('throttle:10,1')->name('auth.google.callback');
});

// Authenticated Affiliate / Admin Routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', Overview::class)->name('dashboard');
    Route::get('/dashboard/discovery', ProductDiscovery::class)->name('dashboard.discovery');
    Route::get('/dashboard/catalog', CatalogSettings::class)->name('dashboard.catalog');
    Route::get('/dashboard/analytics', \App\Livewire\Dashboard\Analytics::class)->name('dashboard.analytics');
    Route::get('/dashboard/tes-link', \App\Livewire\Dashboard\LinkTester::class)->name('dashboard.link-test');
    Route::get('/dashboard/komisi', \App\Livewire\Dashboard\Commissions::class)->name('dashboard.commissions');
    Route::get('/dashboard/ai-agent', \App\Livewire\Dashboard\AiAgent::class)->name('dashboard.ai-agent');

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    })->name('logout');
});

// Public Bio Catalog Routes
Route::get('/@{username}', PublicCatalog::class)->name('catalog.public.at');
Route::get('/catalog/{username}', PublicCatalog::class)->name('catalog.public');
Route::get('/u/{username}', PublicCatalog::class)->name('catalog.public.u');

// Public AI landing pages (pre-sell pages written by the AI Agent)
Route::get('/lp/{slug}', [\App\Http\Controllers\LandingPageController::class, 'show'])
    ->where('slug', '^[a-zA-Z0-9_-]+$')
    ->middleware('throttle:180,1')
    ->name('lp.show');
Route::get('/lp/{slug}/go', [\App\Http\Controllers\LandingPageController::class, 'go'])
    ->where('slug', '^[a-zA-Z0-9_-]+$')
    ->middleware('throttle:180,1')
    ->name('lp.go');

// Public Affiliate Short Link Redirection
// Placed at the end to allow dedicated routes to resolve first
Route::get('/{slug}', [RedirectController::class, 'handle'])
    ->where('slug', '^[a-zA-Z0-9_-]+$')
    ->middleware('throttle:180,1')
    ->name('redirect.slug');
