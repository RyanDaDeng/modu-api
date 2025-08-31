<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bookmark;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BookmarkController extends Controller
{
    /**
     * Display all user's bookmarks (no pagination).
     */
    public function index()
    {
        $bookmarks = Auth::user()->bookmarks()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $bookmarks,
            'count' => $bookmarks->count()
        ]);
    }

    /**
     * Store a new bookmark or update existing one for the same comic.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'comic_id' => 'required|string',
            'comic_name' => 'required|string|max:255',
            'chapter_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if exact same bookmark (comic + chapter) already exists
        $exactBookmark = Auth::user()->bookmarks()
            ->where('comic_id', $request->comic_id)
            ->where('chapter_id', $request->chapter_id)
            ->first();

        if ($exactBookmark) {
            // Exact same bookmark already exists
            return response()->json([
                'message' => '该章节已添加书签',
                'bookmark' => $exactBookmark,
                'already_exists' => true
            ]);
        }

        // Check if bookmark exists for this comic (different chapter)
        $existingBookmark = Auth::user()->bookmarks()
            ->where('comic_id', $request->comic_id)
            ->first();

        // Check if user has reached the bookmark limit (50)
        $bookmarkCount = Auth::user()->bookmarks()->count();
        
        if (!$existingBookmark && $bookmarkCount >= 50) {
            return response()->json([
                'message' => '书签数量已达上限（50个），请删除一些书签后再试',
                'error_type' => 'limit_reached'
            ], 422);
        }

        if ($existingBookmark) {
            // Update existing bookmark to new chapter
            $existingBookmark->update([
                'chapter_id' => $request->chapter_id,
                'comic_name' => $request->comic_name,
            ]);

            return response()->json([
                'message' => '书签已更新到当前章节',
                'bookmark' => $existingBookmark,
                'is_update' => true
            ]);
        } else {
            // Create new bookmark
            $bookmark = Auth::user()->bookmarks()->create($request->only([
                'comic_id',
                'comic_name',
                'chapter_id'
            ]));

            return response()->json([
                'message' => '书签已添加',
                'bookmark' => $bookmark,
                'is_update' => false
            ], 201);
        }
    }

    /**
     * Remove the specified bookmark.
     */
    public function destroy($id)
    {
        $bookmark = Auth::user()->bookmarks()->find($id);

        if (!$bookmark) {
            return response()->json(['message' => '书签不存在'], 404);
        }

        $bookmark->delete();

        return response()->json(['message' => '书签已删除']);
    }
}