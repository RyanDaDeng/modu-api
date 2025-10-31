<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;

class OnlineVideo extends Model
{
    use HasFactory;

    protected $connection = 'video';

    protected $guarded = [];

    /**
     * Note: We don't auto-load relationships here to avoid performance issues
     * Load them explicitly when needed using with()
     */

    /**
     * Cache TTL in seconds (4 hours)
     *
     * @var int
     */
    protected $cacheTTL = 14400;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'comments_count' => 'integer',
    ];

    /**
     * 获取视频的所有收藏记录
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(VideoFavorite::class, 'video_id');
    }

    /**
     * 获取收藏此视频的用户
     */
    public function favoriteUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'video_favorites', 'video_id', 'user_id')
                    ->withTimestamps();
    }

    /**
     * 检查指定用户是否收藏了此视频
     * Cached to avoid repeated queries
     */
    public function isFavoritedBy($userId): bool
    {
        return Cache::remember(
            "video_{$this->id}_favorited_by_{$userId}",
            300, // 5 minutes cache
            function () use ($userId) {
                return $this->favorites()->where('user_id', $userId)->exists();
            }
        );
    }

    /**
     * 获取视频的所有评论
     */
    public function comments(): HasMany
    {
        return $this->hasMany(VideoComment::class, 'video_id');
    }

    /**
     * 查询优化：基础查询条件
     */
    public function scopeActive($query)
    {
        return $query->where('is_synced', true);
    }

    /**
     * 查询优化：按来源筛选
     */
    public function scopeFromSources($query, array $sources)
    {
        return $query->whereIn('source', $sources);
    }

    /**
     * 查询优化：排除特定类型
     */
    public function scopeExcludeTypes($query, array $types)
    {
        return $query->whereNotIn('type_name', $types);
    }

    /**
     * 查询优化：时间范围筛选
     */
    public function scopeTimeFilter($query, $filter)
    {
        switch ($filter) {
            case '7days':
                return $query->where('created_at', '>=', now()->subDays(7));
            case '30days':
                return $query->where('created_at', '>=', now()->subDays(30));
            default:
                return $query;
        }
    }

    /**
     * 查询优化：排序方式
     */
    public function scopeSortBy($query, $sortFilter)
    {
        switch ($sortFilter) {
            case 'hot':
                return $query->orderBy('views', 'desc');
            case 'favorites':
                return $query->orderBy('favorites_count', 'desc');
            case 'comments':
                return $query->orderBy('comments_count', 'desc');
            case 'latest':
            default:
                return $query->orderBy('created_at', 'desc');
        }
    }
}
