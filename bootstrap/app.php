<?php

use App\Support\ErrorPageRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.token' => \App\Http\Middleware\VerifyAdminToken::class,
            'admin.can' => \App\Http\Middleware\EnsureAdminAbility::class,
            'site.feature' => \App\Http\Middleware\EnsureSiteFeature::class,
        ]);
        $middleware->web(prepend: [
            \App\Http\Middleware\CheckMaintenanceMode::class,
            \App\Http\Middleware\ApplySiteRedirects::class,
        ]);
        $middleware->api(prepend: [
            \App\Http\Middleware\CheckMaintenanceMode::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\EnsureUserNotBlocked::class,
            \App\Http\Middleware\NoindexPaginationPages::class,
        ]);
        // Guest library endpoints must work over plain HTTP even when the browser
        // drops/ignores session cookies between page render and the AJAX call.
        $middleware->validateCsrfTokens(except: [
            'api/series/*/favourite',
            'api/series/*/watch-history',
        ]);
        $middleware->redirectGuestsTo('/?auth=login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Страница не найдена.'], 404);
            }

            return ErrorPageRenderer::response(404);
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            $code = $e->getStatusCode();
            if (!in_array($code, [403, 419, 500, 503], true)) {
                return null;
            }

            if ($request->expectsJson()) {
                $message = $code === 419
                    ? 'Сессия истекла. Обновите страницу и попробуйте снова.'
                    : ('Ошибка ' . $code);

                return response()->json(['message' => $message], $code);
            }

            return ErrorPageRenderer::response($code);
        });
    })->create();
