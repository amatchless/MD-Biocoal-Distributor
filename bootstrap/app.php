<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\EnsureUserIsActive::class,
        ]);
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Expired page (419): the form's CSRF token is stale because the session
        // timed out or the tab was left open. Send people somewhere useful instead
        // of the bare "Page Expired" screen. Logging out with an expired session
        // just lands on the login page, which is what they wanted anyway; with a
        // live session the refreshed page carries a fresh token, so retrying works.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }

            if (! $request->user()) {
                return redirect()->route('login')
                    ->with('status', 'Your session expired. Please sign in again.');
            }

            return back()->withInput($request->except(['_token', 'password', 'password_confirmation']))
                ->withErrors(['session' => 'This page had expired. Please try again.']);
        });
    })->create();
