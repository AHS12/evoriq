<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectIfSetupRequired;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            RedirectIfSetupRequired::class,
            EnsureUserIsActive::class,
        ]);

        // The setup gate must run before authentication so an uninstalled
        // app sends guests to the wizard instead of the login screen.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: RedirectIfSetupRequired::class,
        );

        // Keep the developer tools reachable while the app is in maintenance
        // mode so a super admin can toggle it back off.
        $middleware->preventRequestsDuringMaintenance([
            'admin/settings/developer',
            'admin/settings/developer/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Render a branded Inertia error page for browser requests instead of
        // the default Symfony error screen.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            $renderable = [403, 404, 419, 429, 500, 503];

            if ($request->expectsJson() || ! in_array($status, $renderable, true)) {
                return $response;
            }

            return Inertia::render('errors/error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
