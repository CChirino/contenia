<?php

use App\Http\Controllers\PlanController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settings\BrandProfileController;
use App\Http\Controllers\Settings\CalendarEventController;
use App\Http\Controllers\Settings\ContentPillarController;
use App\Http\Controllers\Settings\NetworkStrategyController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', function () {
    $now = now('America/Caracas');

    return redirect()->route('plans.show', [
        'year' => $now->year,
        'month' => $now->month,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/plans/{year}/{month}', [PlanController::class, 'show'])->name('plans.show');
    Route::post('/plans/{year}/{month}/generate', [PlanController::class, 'generate'])->name('plans.generate');
    Route::get('/plans/{year}/{month}/status', [PlanController::class, 'status'])->name('plans.status');
    Route::post('/plans/{year}/{month}/weeks/{from}/regenerate', [PlanController::class, 'regenerateWeek'])
        ->where('from', '\d{4}-\d{2}-\d{2}')->name('plans.weeks.regenerate');
    Route::delete('/plans/{year}/{month}/error', [PlanController::class, 'clearError'])->name('plans.error.clear');

    Route::patch('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::post('/posts/{post}/approve', [PostController::class, 'approve'])->name('posts.approve');
    Route::post('/posts/{post}/regenerate', [PostController::class, 'regenerate'])->name('posts.regenerate');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware('auth')->prefix('settings')->name('settings.')->group(function () {
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
