<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DecryptService;
use Illuminate\Http\Request;

class TestDecryptController extends Controller
{
    private $decryptService;
    
    public function __construct()
    {
        $this->decryptService = new DecryptService();
    }
    
    /**
     * Test decryption with catalog API
     */
    public function testCatalog(Request $request)
    {
        $baseUrl = 'https://www.cdnmhwscc.vip';
        $endpoint = '/categories/filter';
        
        // Get parameters from request or use defaults
        $params = [
            'page' => $request->get('page', 0),
            'category_id' => $request->get('category_id', 0),
            'o' => $request->get('order', 'mr'),
            't' => $request->get('time', 't'),
            'f' => $request->get('filter', 'c')
        ];
        
        $url = $baseUrl . $endpoint;
        
        try {
            // First, let's try a manual approach for debugging
            $timestamp = time();
            $tokenData = $this->decryptService->generateToken($timestamp);
            
            // Make the request manually
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'token' => $tokenData['token'],
                    'tokenparam' => $tokenData['tokenParam'],
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ],
                'query' => $params,
                'timeout' => 30,
                'verify' => false
            ]);
            
            $rawResponse = $response->getBody()->getContents();
            $responseData = json_decode($rawResponse, true);
            
            // Debug info
            $debugInfo = [
                'timestamp' => $timestamp,
                'token' => $tokenData,
                'raw_response_type' => gettype($responseData),
                'has_data' => isset($responseData['data']),
                'data_type' => isset($responseData['data']) ? gettype($responseData['data']) : null,
            ];
            
            // Try to decrypt if data is encrypted
            if (isset($responseData['data']) && is_string($responseData['data'])) {
                try {
                    $decrypted = $this->decryptService->decryptData($timestamp, $responseData['data']);
                    return response()->json([
                        'success' => true,
                        'decrypted' => true,
                        'data' => $decrypted,
                        'debug' => $debugInfo
                    ], 200, [], JSON_UNESCAPED_UNICODE);
                } catch (\Exception $decryptError) {
                    // Try with string timestamp
                    try {
                        $decrypted = $this->decryptService->decryptData((string)$timestamp, $responseData['data']);
                        return response()->json([
                            'success' => true,
                            'decrypted' => true,
                            'data' => $decrypted,
                            'debug' => array_merge($debugInfo, ['used_string_key' => true])
                        ], 200, [], JSON_UNESCAPED_UNICODE);
                    } catch (\Exception $e2) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Decryption failed',
                            'debug' => $debugInfo,
                            'encrypted_data_sample' => substr($responseData['data'], 0, 100),
                            'error_details' => $e2->getMessage()
                        ], 200, [], JSON_UNESCAPED_UNICODE);
                    }
                }
            } else {
                // Data is not encrypted or doesn't exist
                return response()->json([
                    'success' => true,
                    'decrypted' => false,
                    'data' => $responseData,
                    'debug' => $debugInfo
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Test raw decryption with provided data
     */
    public function testDecrypt(Request $request)
    {
        $request->validate([
            'key' => 'required',
            'cipher_text' => 'required'
        ]);
        
        try {
            $decrypted = $this->decryptService->decryptData(
                $request->input('key'),
                $request->input('cipher_text')
            );
            
            return response()->json([
                'success' => true,
                'decrypted_data' => $decrypted
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Test token generation
     */
    public function testToken(Request $request)
    {
        $key = $request->get('key', time());
        $token = $this->decryptService->generateToken($key);
        
        return response()->json([
            'success' => true,
            'key' => $key,
            'token' => $token
        ]);
    }
    
    /**
     * Test fetching any API endpoint with decryption
     */
    public function testFetch(Request $request)
    {
        $request->validate([
            'url' => 'required|url'
        ]);
        
        $url = $request->input('url');
        $params = $request->except(['url']);
        
        try {
            $result = $this->decryptService->fetchAndDecrypt($url, $params);
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}