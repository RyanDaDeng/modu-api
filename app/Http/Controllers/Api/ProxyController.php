<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DecryptService;
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

    private $decryptService;
    
    // Custom encryption key - change this to your own secret
    private $customEncryptionKey = 'MoDu18Comic2024!';

    public function __construct()
    {
        $this->decryptService = new DecryptService();
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

            if(isset($cached['data']) && isset($cached['status']) && $cached['status'] == 200){
                // Cached data is already encrypted with our custom encryption
                return response()->json(['encrypted' => $cached['data']], $cached['status'])
                    ->header('X-Cache-Hit', 'true');
            }
        }

        try {
            // Generate timestamp and token
            $timestamp = time();
            $tokenData = $this->decryptService->generateToken($timestamp);

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
                'token' => $tokenData['token'],
                'tokenParam' => $tokenData['tokenParam'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ])->timeout(30)->get($url);

            // Get the raw response body
            $responseBody = $response->body();
            $responseStatus = $response->status();

            // Parse response
            $responseData = json_decode($responseBody, true);

            // Check if response needs decryption
            if (isset($responseData['data']) && is_string($responseData['data'])) {
                // Decrypt the data field
                try {
                    $decrypted = $this->decryptService->decryptData($timestamp, $responseData['data']);
                    $responseData['data'] = $decrypted;
                } catch (\Exception $e) {
                    \Log::error('Decryption failed', [
                        'path' => $path,
                        'error' => $e->getMessage()
                    ]);
                    // Return original if decryption fails
                }
            }

            // Encrypt the data with our custom encryption
            $encryptedData = $this->customEncrypt($responseData);
            
            // Only cache successful responses
            if ($response->successful()) {
                // Cache the encrypted response
                Cache::put($cacheKey, [
                    'data' => $encryptedData,
                    'status' => $responseStatus
                ], now()->addMinutes(30));
            }

            // Return the encrypted response
            return response()->json(['encrypted' => $encryptedData], $responseStatus)
                ->header('X-Cache-Hit', 'false');

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
     * Custom encryption for our data
     */
    private function customEncrypt($data)
    {
        // Convert data to JSON string with UTF-8 encoding
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Generate key from the key string (matching frontend)
        $key = substr(hash('sha256', $this->customEncryptionKey), 0, 32);
        
        // Convert JSON string to bytes for proper UTF-8 handling
        $dataBytes = unpack('C*', $jsonData);
        $keyBytes = unpack('C*', $key);
        
        // XOR encryption at byte level
        $encrypted = '';
        $keyLen = count($keyBytes);
        $dataLen = count($dataBytes);
        
        for ($i = 1; $i <= $dataLen; $i++) {
            $keyIndex = (($i - 1) % $keyLen) + 1;
            $encrypted .= chr($dataBytes[$i] ^ $keyBytes[$keyIndex]);
        }
        
        // Base64 encode and add a signature
        $result = base64_encode($encrypted);
        
        // Add a hash for integrity check
        $hash = substr(md5($result . $this->customEncryptionKey), 0, 8);
        
        return $hash . '.' . $result;
    }
}
