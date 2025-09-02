<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!$request->user()) {
            return response()->json([
                'message' => '未授权访问'
            ], 401);
        }

        // Check if user is admin
        if (!$request->user()->is_admin) {
            return response()->json([
                'message' => '权限不足，需要管理员权限'
            ], 403);
        }

        return $next($request);
    }
}