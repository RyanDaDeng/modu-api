<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoFavorite extends Model
{
    use HasFactory;
    protected $connection = 'video';
    protected $fillable = [
        'user_id',
        'video_id',
    ];

    /**
     * 获取收藏的用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取收藏的视频
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(OnlineVideo::class, 'video_id');
    }
}
