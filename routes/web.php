<?php

use App\Http\Controllers\Admin\BrandContactController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\OnboardingApplyController;
use App\Http\Controllers\Admin\PortfolioController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\BrandLogoController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RenderFrameController;
use App\Http\Controllers\ReviewPortalController;
use App\Http\Controllers\ReviewRequestController;
use App\Http\Controllers\Settings\BrandProfileController;
use App\Http\Controllers\Settings\CalendarEventController;
use App\Http\Controllers\Settings\ContentPillarController;
use App\Http\Controllers\Settings\GuidelineController;
use App\Http\Controllers\Settings\NetworkStrategyController;
use App\Models\Brand;
use Illuminate\Support\Facades\Route;

/**
 * Marca por defecto para redirecciones: la última usada o la primera
 * asignada (los admin ven todas las marcas). Para no-admin solo cuentan
 * marcas asignadas — null si no tiene ninguna.
 */
$defaultBrand = function () {
    $user = auth()->user();

    if (! $user) {
        return null;
    }

    if ($user->isAdmin()) {
        return $user->lastBrand ?? Brand::first();
    }

    return $user->last_brand_id
        ? $user->brands()->find($user->last_brand_id) ?? $user->brands()->first()
        : $user->brands()->first();
};

/**
 * A dónde va un usuario sin marca: quien puede crear marcas entra al
 * wizard; el resto ve la página "sin marcas" en vez de un 403.
 */
$noBrandResponse = function () {
    $user = auth()->user();

    return $user?->canCreateBrands()
        ? redirect()->route('onboarding.show')
        : Inertia\Inertia::render('Onboarding/NoBrands');
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

// Asistente de primeros pasos: bajo auth pero sin `onboarded` (es el destino).
Route::prefix('onboarding')->middleware('auth')->name('onboarding.')->group(function () {
    Route::get('/', [OnboardingController::class, 'show'])->name('show');
    Route::patch('/state', [OnboardingController::class, 'state'])->name('state');
    Route::post('/skip', [OnboardingController::class, 'skip'])->name('skip');
    Route::post('/restart', [OnboardingController::class, 'restart'])->name('restart');
    Route::post('/complete', [OnboardingController::class, 'complete'])->name('complete');
    Route::post('/brand', [OnboardingController::class, 'storeBrand'])->name('brand.store');
    Route::get('/status', [OnboardingController::class, 'status'])->name('status');
    Route::post('/apply-all', [OnboardingController::class, 'applyAll'])->name('apply-all');
    Route::post('/manual', [OnboardingController::class, 'manualSetup'])->name('manual');
    Route::get('/preview', [OnboardingController::class, 'preview'])->name('preview');
});

// Gestión de marcas: admin (todas) y gestor (solo las asignadas — lo
// verifica `can:manage,brand` en cada ruta con {brand}).
Route::middleware(['auth', 'manager', 'onboarded'])->group(function () {
    Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
    Route::get('/brands/create', [BrandController::class, 'create'])
        ->middleware('can:create,'.Brand::class)->name('brands.create');
    Route::post('/brands', [BrandController::class, 'store'])
        ->middleware('can:create,'.Brand::class)->name('brands.store');
    Route::get('/brands/{brand}/edit', [BrandController::class, 'edit'])
        ->middleware('can:manage,brand')->name('brands.edit');
    Route::put('/brands/{brand}', [BrandController::class, 'update'])
        ->middleware('can:manage,brand')->name('brands.update');
    Route::post('/brands/{brand}/toggle', [BrandController::class, 'toggle'])
        ->middleware('can:manage,brand')->name('brands.toggle');
    Route::post('/brands/{brand}/onboarding', [BrandController::class, 'runOnboarding'])
        ->middleware('can:manage,brand')->name('brands.onboarding.run');
    Route::post('/brands/{brand}/guidelines/distill', [BrandController::class, 'distillGuidelines'])
        ->middleware('can:manage,brand')->name('brands.guidelines.distill');

    // Operadores de la marca: sync desde la UI y alta/baja individuales
    // (solo usuarios con role=operator; la policy `manage` fija el alcance).
    Route::post('/brands/{brand}/users', [BrandController::class, 'syncOperators'])
        ->middleware('can:manage,brand')->name('brands.users.sync');
    Route::post('/brands/{brand}/users/{user}', [BrandController::class, 'attachOperator'])
        ->middleware('can:manage,brand')->name('brands.users.attach');
    Route::delete('/brands/{brand}/users/{user}', [BrandController::class, 'detachOperator'])
        ->middleware('can:manage,brand')->name('brands.users.detach');

    // Aplicar la propuesta de onboarding por secciones (borrador → datos reales).
    Route::post('/brands/{brand}/onboarding/apply/profile', [OnboardingApplyController::class, 'profile'])
        ->middleware('can:manage,brand')->name('brands.onboarding.apply.profile');
    Route::post('/brands/{brand}/onboarding/apply/pillars', [OnboardingApplyController::class, 'pillars'])
        ->middleware('can:manage,brand')->name('brands.onboarding.apply.pillars');
    Route::post('/brands/{brand}/onboarding/apply/strategies', [OnboardingApplyController::class, 'strategies'])
        ->middleware('can:manage,brand')->name('brands.onboarding.apply.strategies');
    Route::post('/brands/{brand}/onboarding/apply/palette', [OnboardingApplyController::class, 'palette'])
        ->middleware('can:manage,brand')->name('brands.onboarding.apply.palette');
    Route::post('/brands/{brand}/onboarding/apply/logo', [OnboardingApplyController::class, 'logo'])
        ->middleware('can:manage,brand')->name('brands.onboarding.apply.logo');
    Route::post('/brands/{brand}/contacts', [BrandContactController::class, 'store'])
        ->middleware('can:manage,brand')->name('brands.contacts.store');
    Route::delete('/brands/{brand}/contacts/{contact}', [BrandContactController::class, 'destroy'])
        ->middleware('can:manage,brand')->name('brands.contacts.destroy');
});

// Administración global (usuarios, cartera y catálogo de planes): solo admin.
Route::middleware(['auth', 'admin', 'onboarded'])->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{user}/restore', [UserController::class, 'restore'])
        ->whereNumber('user')->name('users.restore');

    // Cartera: consumo y extensiones de cuota por marca.
    Route::get('/portfolio', [PortfolioController::class, 'index'])
        ->name('portfolio.index');
    Route::post('/portfolio/{brand}/extensions', [PortfolioController::class, 'extend'])
        ->name('portfolio.extend');

    // Catálogo de planes (names admin.plans.*: las rutas de la grilla ya usan plans.*).
    Route::get('/plans', [App\Http\Controllers\Admin\PlanController::class, 'index'])->name('admin.plans.index');
    Route::get('/plans/create', [App\Http\Controllers\Admin\PlanController::class, 'create'])->name('admin.plans.create');
    Route::post('/plans', [App\Http\Controllers\Admin\PlanController::class, 'store'])->name('admin.plans.store');
    Route::get('/plans/{plan}/edit', [App\Http\Controllers\Admin\PlanController::class, 'edit'])->name('admin.plans.edit');
    Route::put('/plans/{plan}', [App\Http\Controllers\Admin\PlanController::class, 'update'])->name('admin.plans.update');
    Route::delete('/plans/{plan}', [App\Http\Controllers\Admin\PlanController::class, 'destroy'])->name('admin.plans.destroy');
});

Route::get('/render/{brand:slug}/{post}/{frame}', RenderFrameController::class)
    ->middleware('signed')->name('render.frame');
// El nombre del archivo va en la query (?file=frame-01.png): si la ruta
// terminara en .png/.mp4, Nginx (p. ej. CloudPanel) la serviría como
// estático de public/ y devolvería 404 sin pasar por Laravel.
Route::get('/assets/{brand:slug}/{post}', AssetController::class)
    ->middleware('signed')->name('assets.show');

// Portal público del cliente: sin auth ni marca; el token la resuelve
// (ResolveReviewToken fija la marca y frena enlaces revocados/vencidos).
Route::prefix('review/{token}')->middleware(['throttle:60,1', 'review.token'])->name('review.')->group(function () {
    Route::get('/', [ReviewPortalController::class, 'show'])->name('show');
    Route::post('/items/{post}', [ReviewPortalController::class, 'item'])->name('item');
    Route::post('/weeks/{week_from}/approve', [ReviewPortalController::class, 'approveWeek'])
        ->where('week_from', '\d{4}-\d{2}-\d{2}')->name('week.approve');
    Route::post('/weeks/{week_from}/comment', [ReviewPortalController::class, 'weekComment'])
        ->where('week_from', '\d{4}-\d{2}-\d{2}')->name('week.comment');
    Route::post('/approve-all', [ReviewPortalController::class, 'approveAll'])->name('approve_all');
    Route::post('/submit', [ReviewPortalController::class, 'submit'])->name('submit');
});

// URL antigua sin marca: redirige a la marca del usuario (301).
Route::get('/plans/{year}/{month}', function (int $year, int $month) use ($defaultBrand, $noBrandResponse) {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    if (($brand = $defaultBrand()) === null) {
        return $noBrandResponse();
    }

    return redirect()->route('plans.show', [
        'brand' => $brand->slug,
        'year' => $year,
        'month' => $month,
    ], 301);
});

Route::get('/dashboard', function () use ($defaultBrand, $noBrandResponse) {
    $now = now('America/Caracas');

    if (($brand = $defaultBrand()) === null) {
        return $noBrandResponse();
    }

    return redirect()->route('plans.show', [
        'brand' => $brand->slug,
        'year' => $now->year,
        'month' => $now->month,
    ]);
})->middleware(['auth', 'verified', 'onboarded'])->name('dashboard');

Route::prefix('b/{brand:slug}')->middleware(['auth', 'brand', 'onboarded'])->group(function () {
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

    Route::post('/plans/{year}/{month}/posts/from-media', [PostController::class, 'storeFromMedia'])
        ->name('posts.from-media');
    Route::post('/posts/{post}/compose', [PostController::class, 'compose'])->name('posts.compose');
    Route::patch('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::post('/posts/{post}/approve', [PostController::class, 'approve'])->name('posts.approve');
    Route::post('/posts/{post}/regenerate', [PostController::class, 'regenerate'])->name('posts.regenerate');
    Route::post('/posts/{post}/render', [PostController::class, 'render'])->name('posts.render');
    Route::get('/posts/{post}/download', [PostController::class, 'download'])->name('posts.download');
});

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::prefix('b/{brand:slug}/settings')->middleware(['auth', 'brand', 'onboarded'])->name('settings.')->group(function () {
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

    // Pautas aprendidas: la IA propone, el operador confirma.
    Route::get('guidelines', [GuidelineController::class, 'index'])->name('guidelines.index');
    Route::post('guidelines', [GuidelineController::class, 'store'])->name('guidelines.store');
    Route::post('guidelines/{guideline}/activate', [GuidelineController::class, 'activate'])->name('guidelines.activate');
    Route::post('guidelines/{guideline}/discard', [GuidelineController::class, 'discard'])->name('guidelines.discard');
    Route::post('guidelines/{guideline}/retire', [GuidelineController::class, 'retire'])->name('guidelines.retire');
    Route::post('guidelines/{guideline}/keep', [GuidelineController::class, 'keep'])->name('guidelines.keep');
    Route::put('guidelines/{guideline}', [GuidelineController::class, 'update'])->name('guidelines.update');
    Route::delete('guidelines/{guideline}', [GuidelineController::class, 'destroy'])->name('guidelines.destroy');
});

require __DIR__.'/auth.php';
