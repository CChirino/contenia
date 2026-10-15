<?php

use App\Http\Controllers\Admin\BrandContactController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\BrandLogoController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RenderFrameController;
use App\Http\Controllers\ReviewPortalController;
use App\Http\Controllers\ReviewRequestController;
use App\Http\Controllers\Settings\BrandProfileController;
use App\Http\Controllers\Settings\CalendarEventController;
use App\Http\Controllers\Settings\ContentPillarController;
use App\Http\Controllers\Settings\NetworkStrategyController;
use App\Models\Brand;
use Illuminate\Support\Facades\Route;

/**
 * Marca por defecto para redirecciones: la última usada o la primera
 * asignada (los admin ven todas las marcas).
 */
$defaultBrand = function () {
    $user = auth()->user();

    return $user?->lastBrand ?? $user?->brands()->first() ?? Brand::first();
};

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Rutas públicas usadas por el render (logo) y por Chromium (firmadas).
Route::get('/brand/logo.svg', function () {
    return response()->file(resource_path('brand/logo.svg'), ['Content-Type' => 'image/svg+xml']);
})->name('brand.logo');

// Logo por marca: público y solo lectura (Chromium lo usa en el render).
Route::get('/b/{brand:slug}/logo', BrandLogoController::class)
    ->name('brands.logo');

// Administración de marcas y usuarios: solo admin.
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
    Route::get('/brands/create', [BrandController::class, 'create'])->name('brands.create');
    Route::post('/brands', [BrandController::class, 'store'])->name('brands.store');
    Route::get('/brands/{brand}/edit', [BrandController::class, 'edit'])->name('brands.edit');
    Route::put('/brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
    Route::post('/brands/{brand}/toggle', [BrandController::class, 'toggle'])->name('brands.toggle');
    Route::post('/brands/{brand}/contacts', [BrandContactController::class, 'store'])->name('brands.contacts.store');
    Route::delete('/brands/{brand}/contacts/{contact}', [BrandContactController::class, 'destroy'])->name('brands.contacts.destroy');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
});

Route::get('/render/{brand:slug}/{post}/{frame}', RenderFrameController::class)
    ->middleware('signed')->name('render.frame');
Route::get('/assets/{brand:slug}/{post}/{file}', AssetController::class)
    ->middleware('signed')->name('assets.show');

// Portal público del cliente (el token identifica la marca; el portal
// llega en el Paso 4 — por ahora la ruta existe para generar el enlace).
// Portal público del cliente: sin auth ni marca; el token la resuelve
// (ResolveReviewToken fija la marca y frena enlaces revocados/vencidos).
Route::prefix('review/{token}')->middleware(['throttle:60,1', 'review.token'])->name('review.')->group(function () {
    Route::get('/', [ReviewPortalController::class, 'show'])->name('show');
    Route::post('/items/{post}', [ReviewPortalController::class, 'item'])->name('item');
    Route::post('/weeks/{week_from}/approve', [ReviewPortalController::class, 'approveWeek'])
        ->where('week_from', '\\d{4}-\\d{2}-\\d{2}')->name('week.approve');
    Route::post('/weeks/{week_from}/comment', [ReviewPortalController::class, 'weekComment'])
        ->where('week_from', '\\d{4}-\\d{2}-\\d{2}')->name('week.comment');
    Route::post('/approve-all', [ReviewPortalController::class, 'approveAll'])->name('approve_all');
    Route::post('/submit', [ReviewPortalController::class, 'submit'])->name('submit');
});

// URL antigua sin marca: redirige a la marca del usuario (301).
Route::get('/plans/{year}/{month}', function (int $year, int $month) use ($defaultBrand) {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return redirect()->route('plans.show', [
        'brand' => $defaultBrand()?->slug,
        'year' => $year,
        'month' => $month,
    ], 301);
});

Route::get('/dashboard', function () use ($defaultBrand) {
    $now = now('America/Caracas');

    return redirect()->route('plans.show', [
        'brand' => $defaultBrand()?->slug,
        'year' => $now->year,
        'month' => $now->month,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::prefix('b/{brand:slug}')->middleware(['auth', 'brand'])->group(function () {
    Route::get('/plans/{year}/{month}', [PlanController::class, 'show'])->name('plans.show');
    Route::post('/plans/{year}/{month}/generate', [PlanController::class, 'generate'])->name('plans.generate');
    Route::get('/plans/{year}/{month}/status', [PlanController::class, 'status'])->name('plans.status');
    Route::post('/plans/{year}/{month}/weeks/{from}/regenerate', [PlanController::class, 'regenerateWeek'])
        ->where('from', '\d{4}-\d{2}-\d{2}')->name('plans.weeks.regenerate');
    Route::delete('/plans/{year}/{month}/error', [PlanController::class, 'clearError'])->name('plans.error.clear');

    Route::post('/plans/{year}/{month}/reviews', [ReviewRequestController::class, 'store'])
        ->name('reviews.store');
    Route::post('/reviews/{review}/revoke', [ReviewRequestController::class, 'revoke'])
        ->name('reviews.revoke');
    Route::post('/reviews/{review}/rotate', [ReviewRequestController::class, 'rotate'])
        ->name('reviews.rotate');

    Route::patch('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::post('/posts/{post}/approve', [PostController::class, 'approve'])->name('posts.approve');
    Route::post('/posts/{post}/regenerate', [PostController::class, 'regenerate'])->name('posts.regenerate');
    Route::post('/posts/{post}/render', [PostController::class, 'render'])->name('posts.render');
    Route::get('/posts/{post}/download', [PostController::class, 'download'])->name('posts.download');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::prefix('b/{brand:slug}/settings')->middleware(['auth', 'brand'])->name('settings.')->group(function () {
    Route::get('brand', [BrandProfileController::class, 'edit'])->name('brand.edit');
    Route::put('brand', [BrandProfileController::class, 'update'])->name('brand.update');

    Route::get('networks', [NetworkStrategyController::class, 'index'])->name('networks.index');
    Route::put('networks', [NetworkStrategyController::class, 'update'])->name('networks.update');

    Route::get('pillars', [ContentPillarController::class, 'index'])->name('pillars.index');
    Route::post('pillars', [ContentPillarController::class, 'store'])->name('pillars.store');
    Route::put('pillars/{pillar}', [ContentPillarController::class, 'update'])->name('pillars.update');
    Route::delete('pillars/{pillar}', [ContentPillarController::class, 'destroy'])->name('pillars.destroy');

    Route::get('calendar', [CalendarEventController::class, 'index'])->name('calendar.index');
    Route::post('calendar', [CalendarEventController::class, 'store'])->name('calendar.store');
    Route::put('calendar/{event}', [CalendarEventController::class, 'update'])->name('calendar.update');
    Route::delete('calendar/{event}', [CalendarEventController::class, 'destroy'])->name('calendar.destroy');
});

require __DIR__.'/auth.php';
