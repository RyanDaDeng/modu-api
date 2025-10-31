<?php

namespace App\Http\Controllers\Api\Video;

use App\Http\Controllers\Controller;
use App\Models\VideoCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class VideoCollectionController extends Controller
{
    /**
     * Display a listing of user's video collections.
     */
    public function index(Request $request)
    {
        $collections = Auth::user()->videoCollections()
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($collections);
    }

    /**
     * Store a newly created video collection.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'video_id' => 'required|string',
            'name' => 'required|string|max:255',
            'cover' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if already collected
        $exists = Auth::user()->videoCollections()
            ->where('video_id', $request->video_id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Video already collected'], 409);
        }

        $collection = Auth::user()->videoCollections()->create($request->only([
            'video_id',
            'name',
            'cover'
        ]));

        return response()->json([
            'message' => 'Video collected successfully',
            'collection' => $collection
        ], 201);
    }

    /**
     * Check if a video is collected by the user.
     */
    public function check($videoId)
    {
        $isCollected = Auth::user()->videoCollections()
            ->where('video_id', $videoId)
            ->exists();

        return response()->json(['is_collected' => $isCollected]);
    }

    /**
     * Remove the specified video collection.
     */
    public function destroy($videoId)
    {
        $collection = Auth::user()->videoCollections()
            ->where('video_id', $videoId)
            ->first();

        if (!$collection) {
            return response()->json(['message' => 'Collection not found'], 404);
        }

        $collection->delete();

        return response()->json(['message' => 'Collection removed successfully']);
    }

    /**
     * Toggle collection status for a video.
     */
    public function toggle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'video_id' => 'required|string',
            'name' => 'required|string|max:255',
            'cover' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $collection = Auth::user()->videoCollections()
            ->where('video_id', $request->video_id)
            ->first();

        if ($collection) {
            // If exists, remove it
            $collection->delete();
            return response()->json([
                'message' => 'Collection removed',
                'is_collected' => false
            ]);
        } else {
            // If not exists, add it
            $collection = Auth::user()->videoCollections()->create($request->only([
                'video_id',
                'name',
                'cover'
            ]));
            return response()->json([
                'message' => 'Collection added',
                'is_collected' => true,
                'collection' => $collection
            ], 201);
        }
    }

    /**
     * Get video collection statistics for the user.
     */
    public function stats()
    {
        $count = Auth::user()->videoCollections()->count();

        return response()->json([
            'total_collections' => $count
        ]);
    }
}
