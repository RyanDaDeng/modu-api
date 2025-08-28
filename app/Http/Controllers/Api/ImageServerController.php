<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ImageServerController extends Controller
{
    /**
     * Get list of available image servers with 1-day cache
     */
    public function index()
    {
        // Cache key for servers list (doesn't change often)
        $cacheKey = 'image_servers_list';
        
        // Try to get cached servers list (1 day TTL)
        $servers = Cache::remember($cacheKey, now()->addDay(), function () {
            return [
                [
                    'id' => 1,
                    'name' => '服务器 1 (默认)',
                    'url' => 'https://cdn-msp.jm18c-twie.club'
                ],
                [
                    'id' => 2,
                    'name' => '服务器 2',
                    'url' => 'https://cdn-msp.jmapiproxy1.cc'
                ],
                [
                    'id' => 3,
                    'name' => '服务器 3',
                    'url' => 'https://cdn-msp.jmapiproxy2.cc'
                ],
                [
                    'id' => 4,
                    'name' => '服务器 4',
                    'url' => 'https://cdn-msp.jmapiproxy3.cc'
                ],
                [
                    'id' => 5,
                    'name' => '服务器 5',
                    'url' => 'https://cdn-msp2.jmapiproxy1.cc'
                ]
            ];
        });

        // Get user's current server if authenticated (not cached as it's user-specific)
        $currentServer = null;
        if (Auth::check()) {
            $user = Auth::user();
            $currentServer = $user->img_server ?: $servers[0]['url'];
        }

        return response()->json([
            'servers' => $servers,
            'current' => $currentServer
        ])->header('X-Cache-TTL', '1 day');
    }

    /**
     * Update user's image server preference
     */
    public function update(Request $request)
    {
        $request->validate([
            'img_server' => 'required|string|url'
        ]);

        $user = Auth::user();
        $user->img_server = $request->img_server;
        $user->save();

        return response()->json([
            'message' => 'Image server updated successfully',
            'img_server' => $user->img_server
        ]);
    }
}
