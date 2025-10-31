<?php

namespace App\Services;

use App\Models\OnlineVideo;
use Illuminate\Support\Facades\Cache;

class RandomVideoService
{
    /**
     * 获取随机视频 - 带缓存版本
     */
    public static function getRandomVideos($limit = 10, $typeName = null, $sources = ['lajiao', 'senlin', 'lebo'])
    {
        // 生成缓存键
        $cacheKey = 'random_videos:' . md5(json_encode([
            'limit' => $limit,
            'type_name' => $typeName,
            'sources' => $sources,
            'rand' => floor(time() / 300) // 5分钟内使用相同的随机结果
        ]));

        return Cache::remember($cacheKey, 300, function () use ($limit, $typeName, $sources) {
            $query = OnlineVideo::query()
                ->where('is_synced', true)
                ->whereIn('source', $sources);

            if ($typeName) {
                $query->where('type_name', $typeName);
            }

            return $query->select([
                    'id', 'name', 'cover_img', 'views',
                    'favorites_count', 'comments_count',
                    'type_name', 'source', 'created_at', 'md5'
                ])
                ->latest('id')
                ->offset(rand(0, 1000))
                ->limit($limit)
                ->get();
        });
    }
}
