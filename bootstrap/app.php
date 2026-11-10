<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureManager;
use App\Http\Middleware\EnsureOnboarded;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveReviewToken;
use App\Http\Middleware\SetActiveBrand;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'manager' => EnsureManager::class,
            'brand' => SetActiveBrand::class,
            'onboarded' => EnsureOnboarded::class,
            'review.token' => ResolveReviewToken::class,
        ]);

        // La marca debe estar en el contenedor antes de resolver los
        // route-model-bindings para que BrandScope filtre (404 cruzado).
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            SetActiveBrand::class,
        );
        // El portal también fija la marca antes del route-model-binding.
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            ResolveReviewToken::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
