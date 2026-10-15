<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'muk.role' => \App\Http\Middleware\EnsureMukRole::class,
            'asesi.website' => \App\Http\Middleware\EnsureAsesiWebsite::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API MUK: 404 tanpa nama model/ID (pesan default "No query results for model [App\Models\...]").
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/muk/*')) {
                return response()->json(['message' => 'Data tidak ditemukan.'], 404);
            }
        });
    })->create();
