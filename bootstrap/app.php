<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 纯 API 模式，不使用 stateful 认证
        // $middleware->statefulApi(); // 移除 stateful API

        // API 路由不需要 CSRF 验证
        // 移除 EnsureFrontendRequestsAreStateful 中间件

        // CORS 配置
        $middleware->alias([
            'cors' => \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // 排除所有 API 路由的 CSRF 验证
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'auth/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
