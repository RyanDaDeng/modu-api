<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DecryptService;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    private $decryptService;
    
    public function __construct()
    {
        $this->decryptService = new DecryptService();
    }
    
    /**
     * Get catalog/ranking data
     */
    public function getRanking(Request $request)
    {
        $baseUrl = 'https://www.cdnmhwscc.vip';
        $endpoint = '/categories/filter';
        
        // Get parameters from request
        $params = [
            'page' => $request->get('page', 0),
            'category_id' => $request->get('category_id', 0),
            'o' => $request->get('order', 'mr'), // mr = most recent, mv = most viewed, mp = most picture, md = most liked
            't' => $request->get('time', 't'), // t = today, w = week, m = month, a = all
            'f' => $request->get('filter', 'c') // c = category filter
        ];
        
        $url = $baseUrl . $endpoint;
        
        try {
            $timestamp = time();
            $tokenData = $this->decryptService->generateToken($timestamp);
            
            // Make the request
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
            
            // Decrypt if needed
            if (isset($responseData['data']) && is_string($responseData['data'])) {
                $decrypted = $this->decryptService->decryptData($timestamp, $responseData['data']);
                
                // Process the data - add any additional logic here
                // For example, you might want to:
                // - Filter out certain content
                // - Add additional fields
                // - Cache the results
                // - Store rankings in database
                
                return response()->json([
                    'success' => true,
                    'data' => $decrypted
                ], 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            } else {
                // Data is not encrypted
                return response()->json([
                    'success' => true,
                    'data' => $responseData
                ], 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch ranking data',
                'message' => $e->getMessage()
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }
    
    /**
     * Get categories list
     */
    public function getCategories(Request $request)
    {
        $baseUrl = 'https://www.cdnmhwscc.vip';
        $endpoint = '/categories';
        
        $url = $baseUrl . $endpoint;
        
        try {
            $timestamp = time();
            $tokenData = $this->decryptService->generateToken($timestamp);
            
            // Make the request
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'token' => $tokenData['token'],
                    'tokenparam' => $tokenData['tokenParam'],
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ],
                'timeout' => 30,
                'verify' => false
            ]);
            
            $rawResponse = $response->getBody()->getContents();
            $responseData = json_decode($rawResponse, true);
            
            // Decrypt if needed
            if (isset($responseData['data']) && is_string($responseData['data'])) {
                $decrypted = $this->decryptService->decryptData($timestamp, $responseData['data']);
                
                return response()->json([
                    'success' => true,
                    'data' => $decrypted
                ], 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            } else {
                return response()->json([
                    'success' => true,
                    'data' => $responseData
                ], 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch categories',
                'message' => $e->getMessage()
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }
}