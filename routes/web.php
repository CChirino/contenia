<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settings\BrandProfileController;
use App\Http\Controllers\Settings\CalendarEventController;
use App\Http\Controllers\Settings\ContentPillarController;
use App\Http\Controllers\Settings\NetworkStrategyController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

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
