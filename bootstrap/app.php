<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use UBO\Common\Problems\ProblemRenderer;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        // Without Laravel's /up: the proxy only sends /api/iam/* here, and the health check is /api/iam/v1/salud.
        apiPrefix: 'api/iam',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind ubo-nginx-proxy: real client IP, and https URLs and secure cookies.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // api/* answers errors in RFC 9457 with the stable code in "error" (UBO_PROBLEMS_ROUTES).
        $exceptions->render(new ProblemRenderer);

        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();
