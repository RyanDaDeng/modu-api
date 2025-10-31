<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoComment extends Model
{
    use HasFactory;
    protected $connection = 'video';
    protected $fillable = [
        'video_id',
        'user_id',
        'content',
        'status'
    ];

    protected $casts = [
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Status constants
    const STATUS_DELETED = 0;
    const STATUS_NORMAL = 1;
    const STATUS_BLOCKED = 2;

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        // Update comments_count when a comment is created
        static::created(function ($comment) {
            if ($comment->status === self::STATUS_NORMAL) {
                $comment->video()->increment('comments_count');
            }
        });

        // Update comments_count when a comment is updated
        static::updated(function ($comment) {
            // If status changed from normal to deleted/blocked
            if ($comment->isDirty('status')) {
                $oldStatus = $comment->getOriginal('status');
                $newStatus = $comment->status;

                if ($oldStatus === self::STATUS_NORMAL && $newStatus !== self::STATUS_NORMAL) {
                    $comment->video()->decrement('comments_count');
                } elseif ($oldStatus !== self::STATUS_NORMAL && $newStatus === self::STATUS_NORMAL) {
                    $comment->video()->increment('comments_count');
                }
            }
        });

        // Update comments_count when a comment is deleted
        static::deleted(function ($comment) {
            if ($comment->status === self::STATUS_NORMAL) {
                $comment->video()->decrement('comments_count');
            }
        });
    }

    /**
     * Get the user that owns the comment.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the video that owns the comment.
     */
    public function video()
    {
        return $this->belongsTo(OnlineVideo::class, 'video_id');
    }

    /**
     * Scope a query to only include normal comments.
     */
    public function scopeNormal($query)
    {
        return $query->where('status', self::STATUS_NORMAL);
    }

    /**
     * Check if the comment belongs to a user.
     */
    public function isOwnedBy($userId)
    {
        return $this->user_id == $userId;
    }
}
