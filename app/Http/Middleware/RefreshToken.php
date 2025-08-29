<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class RefreshToken
{
    /**
     * Handle an incoming request.
     * Auto-refresh token if it's about to expire
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Check if user is authenticated via token
        if ($request->user() && $request->bearerToken()) {
            $token = PersonalAccessToken::findToken($request->bearerToken());
            
            if ($token) {
                // Check if token expires in less than 24 hours
                $expiresAt = $token->expires_at;
                if ($expiresAt && $expiresAt->diffInHours(now()) < 24) {
                    // Delete old token
                    $token->delete();
                    
                    // Create new token
                    $newToken = $request->user()->createToken('auth-token')->plainTextToken;
                    
                    // Add new token to response header
                    $response->header('X-New-Token', $newToken);
                }
            }
        }
        
        return $response;
    }
}