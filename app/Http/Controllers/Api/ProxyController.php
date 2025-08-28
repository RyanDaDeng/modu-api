<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ProxyController extends Controller
{
    private $servers = [
        'www.cdnmhwscc.vip',
        'www.cdnmhws.cc',
        'www.cdnblackmyth.club',
        'www.cdnuc.vip'
    ];

    private $currentKey;
    private $token;
    private $tokenParam;
    
    // Outer layer encryption key
    private $outerKey = 'JMComicViewer2024';

    public function __construct()
    {
        // Token will be generated per request with client's timestamp
    }

    /**
     * Proxy all requests to third-party API with 30-minute caching
     * Keeps the same URI path and query parameters
     */
    public function proxy(Request $request)
    {
        // Get the path from the request (e.g., 'search', 'latest', 'album')
        $path = $request->path();
        
        // Remove 'api/' prefix if present (from Laravel routing)
        $path = str_replace('api/', '', $path);
        
        // Get all query parameters
        $queryParams = $request->query();
        
        // Create a cache key based on the path and query parameters
        $cacheKey = 'proxy:' . $path . ':' . md5(json_encode($queryParams));
        
        // Check if we have cached data first
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            
            // Cached data is already wrapped and obfuscated
            return response($cached['body'], $cached['status'])
                ->header('Content-Type', 'application/json')
                ->header('X-Cache-Hit', 'true')
                ->header('X-Timestamp', $cached['timestamp']); // Return the original timestamp for decryption
        }
        
        try {
            // Get timestamp from client or use current time
            $clientKey = $request->header('X-Client-Key') ?: floor(time());
            
            // Generate token with client's timestamp
            $salt = '185Hcomic3PAPP7R';
            $tokenParam = $clientKey . ',' . '1.8.0';  // Format: timestamp,version
            $token = md5($clientKey . $salt);
            
            // Choose server (use second server for chapter API if available)
            $server = $this->getServerForPath($path);
            
            // Build the full URL with the original path
            $url = "https://{$server}/{$path}";
            
            // Add all query parameters from the original request
            if (!empty($queryParams)) {
                $url .= '?' . http_build_query($queryParams);
            }

            // Forward the request with authentication headers
            $response = Http::withHeaders([
                'token' => $token,
                'tokenParam' => $tokenParam,
                'Content-Type' => 'application/json'
            ])->timeout(30)->get($url);

            // Get the raw response body
            $responseBody = $response->body();
            $responseStatus = $response->status();
            
            // Wrap response in google_recaptcha field with obfuscation
            $wrappedResponse = json_encode([
                'google_recaptcha' => $this->obfuscateResponse($responseBody)
            ]);
            
            // Only cache successful responses
            if ($response->successful()) {
                // Cache the already obfuscated response WITH the timestamp used
                Cache::put($cacheKey, [
                    'body' => $wrappedResponse,  // Cache the obfuscated version
                    'status' => $responseStatus,
                    'timestamp' => $clientKey  // Store the timestamp used for this request
                ], now()->addMinutes(30));
            }
            
            // Return the wrapped response with timestamp header
            return response($wrappedResponse, $responseStatus)
                ->header('Content-Type', 'application/json')
                ->header('X-Cache-Hit', 'false')
                ->header('X-Timestamp', $clientKey); // Tell frontend which timestamp to use for decryption
                
        } catch (\Exception $e) {
            // Log error for debugging
            \Log::error('Proxy request failed', [
                'path' => $path,
                'url' => $url ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            // Return error response (not cached)
            return response()->json([
                'error' => 'Failed to fetch data from upstream server',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the appropriate server based on the path
     */
    private function getServerForPath($path)
    {
        // Chapter API often uses second server if available
        if ($path === 'chapter' && isset($this->servers[1])) {
            return $this->servers[1];
        }
        
        // Default to first server
        return $this->servers[0];
    }
    
    /**
     * Simple obfuscation for outer layer
     */
    private function obfuscateResponse($data)
    {
        // Convert to base64
        $encoded = base64_encode($data);
        
        // Simple character substitution to make it look more random
        $substitutions = [
            'A' => 'Q', 'B' => 'W', 'C' => 'E', 'D' => 'R', 'E' => 'T',
            'F' => 'Y', 'G' => 'U', 'H' => 'I', 'I' => 'O', 'J' => 'P',
            'K' => 'A', 'L' => 'S', 'M' => 'D', 'N' => 'F', 'O' => 'G',
            'P' => 'H', 'Q' => 'J', 'R' => 'K', 'S' => 'L', 'T' => 'Z',
            'U' => 'X', 'V' => 'C', 'W' => 'V', 'X' => 'B', 'Y' => 'N',
            'Z' => 'M',
            '0' => '9', '1' => '8', '2' => '7', '3' => '6', '4' => '5',
            '5' => '4', '6' => '3', '7' => '2', '8' => '1', '9' => '0',
            '+' => '-', '/' => '_', '=' => '.'
        ];
        
        return strtr($encoded, $substitutions);
    }
}