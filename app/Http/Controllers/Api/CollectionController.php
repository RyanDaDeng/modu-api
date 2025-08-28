<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CollectionController extends Controller
{
    /**
     * Display a listing of user's collections.
     */
    public function index(Request $request)
    {
        $collections = Auth::user()->collections()
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($collections);
    }

    /**
     * Store a newly created collection.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'comic_id' => 'required|string',
            'name' => 'required|string|max:255',
            'author' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if already collected
        $exists = Auth::user()->collections()
            ->where('comic_id', $request->comic_id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Comic already collected'], 409);
        }

        $collection = Auth::user()->collections()->create($request->only([
            'comic_id',
            'name',
            'author'
        ]));

        return response()->json([
            'message' => 'Comic collected successfully',
            'collection' => $collection
        ], 201);
    }

    /**
     * Check if a comic is collected by the user.
     */
    public function check($comicId)
    {
        $isCollected = Auth::user()->collections()
            ->where('comic_id', $comicId)
            ->exists();

        return response()->json(['is_collected' => $isCollected]);
    }

    /**
     * Remove the specified collection.
     */
    public function destroy($comicId)
    {
        $collection = Auth::user()->collections()
            ->where('comic_id', $comicId)
            ->first();

        if (!$collection) {
            return response()->json(['message' => 'Collection not found'], 404);
        }

        $collection->delete();

        return response()->json(['message' => 'Collection removed successfully']);
    }

    /**
     * Toggle collection status for a comic.
     */
    public function toggle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'comic_id' => 'required|string',
            'name' => 'required|string|max:255',
            'author' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $collection = Auth::user()->collections()
            ->where('comic_id', $request->comic_id)
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
            $collection = Auth::user()->collections()->create($request->only([
                'comic_id',
                'name',
                'author',
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
     * Get collection statistics for the user.
     */
    public function stats()
    {
        $count = Auth::user()->collections()->count();
        
        return response()->json([
            'total_collections' => $count
        ]);
    }
}