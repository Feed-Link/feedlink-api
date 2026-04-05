<?php

use App\Http\Middleware\IsAdminMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'is_admin'           => IsAdminMiddleware::class,
            'role'               => RoleMiddleware::class,
            'permission'         => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e) {
            // Handle expired JWT tokens
            if ($e instanceof \Lcobucci\JWT\Validation\RequiredConstraintsViolated) {
                return response()->json([
                    'message' => 'Token expired or invalid',
                    'error' => 'token_expired',
                ], 401);
            }

            // Handle other JWT validation errors
            if ($e instanceof \Lcobucci\JWT\Validation\FailedValidation) {
                return response()->json([
                    'message' => 'Invalid token',
                    'error' => 'invalid_token',
                ], 401);
            }

            // Handle missing or invalid bearer token
            if ($e instanceof \League\OAuth2\Server\Exception\OAuthServerException) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error' => 'unauthorized',
                ], 401);
            }
        });
    })->create();
