<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DecryptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RandomComicsController extends Controller
{
    private $decryptService;

    public function __construct()
    {
        $this->decryptService = new DecryptService();
    }

    /**
     * Get random 5 comics with details (cached for 3 hours)
     */
    public function getRandomComics(Request $request)
    {
        // Create cache key - refreshes every 3 hours to get different random comics
        $cacheKey = 'random_comics:' . floor(time() / 10800); // 10800 seconds = 3 hours

        // Try to get cached response (3 hours TTL)
        $cachedResponse = Cache::remember($cacheKey, now()->addHours(3), function () {
            return $this->fetchRandomComics();
        });

        if ($cachedResponse['success']) {
            return response()->json($cachedResponse, 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                ->header('X-Cache-TTL', '3 hours');
        }

        // If cache fetch failed, try direct fetch
        $result = $this->fetchRandomComics();
        return response()->json($result, $result['success'] ? 200 : 500, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Internal method to fetch random comics
     */
    private function fetchRandomComics()
    {
        try {
            // Step 1: Get list from categories/filter
            $baseUrl = 'https://www.cdnmhwscc.vip';
            $timestamp = time();
            $tokenData = $this->decryptService->generateToken($timestamp);

            // Always use page 1 and randomly select from it
            $page = 1;

            // Make request to categories/filter
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $baseUrl . '/categories/filter', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'token' => $tokenData['token'],
                    'tokenparam' => $tokenData['tokenParam'],
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ],
                'query' => [
                    'page' => 1,
                    'o' => 'mv', // most viewed
                    'category_id' => 0,
                    't' => 'a', // all time
                    'f' => 'c'
                ],
                'timeout' => 30,
                'verify' => false
            ]);

            $rawResponse = $response->getBody()->getContents();
            $responseData = json_decode($rawResponse, true);

            // Decrypt the list data
            $listData = [];
            if (isset($responseData['data']) && is_string($responseData['data'])) {
                $listData = $this->decryptService->decryptData($timestamp, $responseData['data']);
            } else {
                $listData = $responseData['data'] ?? [];
            }

            // Check if we have content
            if (!isset($listData['content']) || empty($listData['content'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'No comics found'
                ], 404);
            }

            // Step 2: Randomly select 5 comics
            $allComics = $listData['content'];
            $comicCount = count($allComics);
            $selectCount = min(10, $comicCount);

            // Shuffle and pick first 5
            shuffle($allComics);
            $selectedComics = array_slice($allComics, 0, $selectCount);

            // Step 3: Get details for each selected comic
            $detailedComics = [];

            foreach ($selectedComics as $comic) {
                $comicId = $comic['id'];

                try {
                    // Get fresh token for each request
                    $detailTimestamp = time();
                    $detailToken = $this->decryptService->generateToken($detailTimestamp);

                    // Request album details
                    $detailResponse = $client->request('GET', $baseUrl . '/album', [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                            'token' => $detailToken['token'],
                            'tokenparam' => $detailToken['tokenParam'],
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                        ],
                        'query' => [
                            'id' => $comicId
                        ],
                        'timeout' => 30,
                        'verify' => false
                    ]);

                    $detailRaw = $detailResponse->getBody()->getContents();
                    $detailData = json_decode($detailRaw, true);

                    // Decrypt detail data if needed
                    $comicDetail = [];
                    if (isset($detailData['data']) && is_string($detailData['data'])) {
                        $comicDetail = $this->decryptService->decryptData($detailTimestamp, $detailData['data']);
                    } else {
                        $comicDetail = $detailData['data'] ?? $detailData;
                    }

                    // Remove unnecessary fields from detail
                    unset($comicDetail['series']);
                    unset($comicDetail['series_id']);
                    unset($comicDetail['related_list']);

                    // Add to results
                    $detailedComics[] = [
                        'id' => $comicId,
                        'basic_info' => $comic,
                        'detail' => $comicDetail
                    ];

                } catch (\Exception $e) {
                    // If one comic fails, continue with others
                    \Log::error('Failed to get comic detail for ID ' . $comicId . ': ' . $e->getMessage());
                    continue;
                }

                // Small delay to avoid rate limiting
                usleep(100000); // 100ms delay
            }

            // Step 4: Try to fetch hot tags
            $hotTags = $this->fetchHotTags();

            return [
                'success' => true,
                'count' => count($detailedComics),
                'comics' => $detailedComics,
                'hot_tags' => $hotTags
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch random comics',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Fetch hot tags from the forum search endpoint
     */
    private function fetchHotTags()
    {
        try {
            $baseUrl = 'https://www.cdnmhwscc.vip';
            $timestamp = time();
            $tokenData = $this->decryptService->generateToken($timestamp);

            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $baseUrl . '/hot_tags', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'token' => $tokenData['token'],
                    'tokenparam' => $tokenData['tokenParam'],
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ],
                'query' => [
                    'mode' => 'search'
                ],
                'timeout' => 10,
                'verify' => false
            ]);

            $rawResponse = $response->getBody()->getContents();

            $responseData = json_decode($rawResponse, true);

            // Decrypt if needed
            $searchData = [];
            if (isset($responseData['data']) && is_string($responseData['data'])) {
                $searchData = $this->decryptService->decryptData($timestamp, $responseData['data']);
            } else {
                $searchData = $responseData['data'] ?? $responseData;
            }

            // Extract hot tags from search data
            if (is_array($searchData)) {
                return $searchData;
            }

            // Return empty array if no tags found
            return [];

        } catch (\Exception $e) {
            // Log error but don't fail the whole request
            \Log::warning('Failed to fetch hot tags: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get single comic detail (cached for 3 hours)
     */
    public function getComicDetail(Request $request, $id)
    {
        // Create cache key for this specific comic
        $cacheKey = 'comic_detail:' . $id;

        // Try to get cached response (3 hours TTL)
        $cachedResponse = Cache::remember($cacheKey, now()->addHours(3), function () use ($id) {
            return $this->fetchComicDetail($id);
        });

        if ($cachedResponse['success']) {
            return response()->json($cachedResponse, 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                ->header('X-Cache-TTL', '3 hours');
        }

        // If cache fetch failed, return error
        return response()->json($cachedResponse, 500, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Internal method to fetch comic detail
     */
    private function fetchComicDetail($id)
    {
        try {
            $baseUrl = 'https://www.cdnmhwscc.vip';
            $timestamp = time();
            $tokenData = $this->decryptService->generateToken($timestamp);

            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $baseUrl . '/album', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'token' => $tokenData['token'],
                    'tokenparam' => $tokenData['tokenParam'],
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ],
                'query' => [
                    'id' => $id
                ],
                'timeout' => 30,
                'verify' => false
            ]);

            $rawResponse = $response->getBody()->getContents();
            $responseData = json_decode($rawResponse, true);

            // Decrypt if needed
            $comicDetail = [];
            if (isset($responseData['data']) && is_string($responseData['data'])) {
                $comicDetail = $this->decryptService->decryptData($timestamp, $responseData['data']);
            } else {
                $comicDetail = $responseData['data'] ?? $responseData;
            }

            // Remove unnecessary fields
            unset($comicDetail['series']);
            unset($comicDetail['series_id']);
            unset($comicDetail['related_list']);

            return [
                'success' => true,
                'data' => $comicDetail
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch comic detail',
                'message' => $e->getMessage()
            ];
        }
    }
}
