<?php

namespace App\Http\Middleware;

use Closure;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CheckVipStatus
{
    public function handle(Request $request, Closure $next)
    {
        // VIP status is now checked dynamically via hasActiveVip() method
        // No need to update any fields
        
        return $next($request);
    }
}