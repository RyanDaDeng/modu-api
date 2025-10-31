<?php

namespace App\Http\Controllers\Api\Video;

use App\Http\Controllers\Api\Common\ApiController;
use App\Models\ForumThread;
use App\Models\OnlineVideo;
use App\Models\OnlineVideoType;
use App\Models\VideoFavorite;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Services\RandomVideoService;
use App\Facades\Meilisearch;

class OnlineVideoApiController extends ApiController
{
    private $notIn = ['男同性恋','重口色情'];
    public static $sources = ['lebo','lajiao','senlin'];
    public function setting(Request $request)
    {
        $source = $request->get('source', null);

        $options = Cache::remember('video-setting-res-'.$source, 86400, function() use($source){
            // 获取所有视频类型
            $types = OnlineVideoType::query()
                ->whereNotIn('name', $this->notIn)
                ->orderBy('name')
                ->get();

            $options = [
            ];

            $types->map(function ($v) use (&$options, $source) {
                // 可以根据资源站动态计算每个类型的数量
                $query = OnlineVideo::query()
                    ->where('type_name', $v['name']);

                if ($source) {
                    $query = $query->where('source', $source);
                }

                $count = $query->count();

                $options[] = [
                    'value' => $v['name'],
                    'label' => $v['name'],
                    'count' => $count
                ];
            });
            return $options;
        });

        // 返回分类和 CDN 域名
        return $this->sendSuccess([
            'categories' => $options,
            'cdn_domain' => config('app.video_cdn_domain')
        ]);
    }

    public function get(Request $request)
    {
        $text = $request->get('text', null);
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 30);

        if(!empty($text)){
            if(!config('app.search')){
                return $this->sendErrorWithMessage('搜索功能暂时不可用');
            }
            $search = $text;

            $results = Meilisearch::index('video_index')->search($search, [
                'limit' => (int) $perPage,
                'offset' =>  (int) ($page - 1) * $perPage,
                'matchingStrategy' => 'all',
                'sort' => ['created_at:desc']
            ]);
            // Get total hits
            $totalHits = $results->getEstimatedTotalHits();

            $ids = [];
            $hits = $results->getHits();

            foreach ($hits as $hit) {
                $ids[] = $hit['id'];
            }
            $threads = OnlineVideo::query()
                ->whereIn('id', $ids)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->sendSuccess( [
                'per_page' => $perPage,
                'current_page' => $page,
                'data' => $threads->toArray(),
                'total' => $totalHits,
                'last_page' => $perPage > 0 ? ceil($totalHits / $perPage) : 0
            ]);
        }
        $type = $request->get('type', null);
        $timeFilter = $request->get('time_filter', 'all');
        $sortFilter = $request->get('sort_filter', 'latest');
        $source = $request->get('source', null);


        // 构建缓存键 - 不包含text参数的查询才缓存
        if (empty($text)) {
            $cacheKey = sprintf(
                'online_videos:%s:%s:%s:%s:%s:%s',
                $type ?: 'all',
                $timeFilter,
                $sortFilter,
                $source ?: 'all',
                $perPage,
                $page
            );

            // 缓存5分钟
            return Cache::remember($cacheKey, 3600, function () use ($request, $type, $timeFilter, $sortFilter, $perPage, $source) {
                return $this->getVideosData($type, $timeFilter, $sortFilter, $perPage, $source);
            });
        }

        return $this->getVideosData($type, $timeFilter, $sortFilter, $perPage, $source);
    }

    private function getVideosData($type, $timeFilter, $sortFilter, $perPage, $source)
    {
        if ($type && OnlineVideoType::query()->where('name', $type)->doesntExist()) {
            return $this->sendErrorWithMessage('您的输入有错。');
        }

        $query = OnlineVideo::query()
            ->select('id', 'created_at', 'cover_img', 'favorites_count', 'comments_count', 'md5', 'name', 'type_name', 'views', 'source')
            ->active();

        // 资源站筛选
        if ($source) {
            $query = $query->where('source', $source);
        }

        // 分类筛选
        if ($type) {
            $query = $query
                ->where('type_name', '=', $type);
        }

        // 时间筛选和排序
        $query = $query->timeFilter($timeFilter)->sortBy($sortFilter);

        $paginator = $query->paginate($perPage);

        return $this->sendSuccess(
            $paginator
        );
    }

    public function getRecommend(Request $request){
        $typeName =  $request->query('type_name');
        // 使用分钟级别的缓存键，确保每分钟都有新的随机数据
        $cacheKey = 'video_recommend_random_' . $typeName . '_' . date('Y-m-d-H-i');

        $data = Cache::remember($cacheKey, 300, function () use( $typeName) {
            return RandomVideoService::getRandomVideos(8, $typeName)->toArray();
        });

        return $this->sendSuccess(
            $data
        );
    }

    /**
     * 获取热门推荐视频 - 随机选取（登录用户即可查看，无需VIP）
     */
    public function getHotRecommendations(Request $request)
    {
        $limit = $request->get('limit', 10);
        $typeName = $request->get('type_name');

        // 构建缓存键 - 添加时间戳到分钟级别，确保每分钟都有新数据
        $cacheKey = 'hot_videos2_random_' . ($typeName ?: 'all') . '_' . $limit . '_' . date('Y-m-d-H-i');

        // 缓存1分钟
        $videos = Cache::remember($cacheKey, 300, function () use ($limit, $typeName) {
            return RandomVideoService::getRandomVideos($limit, $typeName);
        });

        return $this->sendSuccess($videos);
    }

    /**
     * 收藏/取消收藏视频
     */
    public function toggleFavorite(Request $request)
    {
        $request->validate([
            'video_id' => 'required|exists:online_videos,id'
        ]);

        $user = $request->user();
        if (empty($user->video_expired_at) || Carbon::parse($user->video_expired_at, 'UTC')->lessThan(Carbon::now('UTC'))) {
           return $this->sendErrorWithMessage('您没有视频通行证无法操作！');
        }

        $videoId = $request->input('video_id');
        $userId = $user->id;

        DB::beginTransaction();
        try {
            $existingFavorite = VideoFavorite::where('user_id', $userId)
                ->where('video_id', $videoId)
                ->first();

            if ($existingFavorite) {
                // 取消收藏
                $existingFavorite->delete();

                // 更新收藏数
                OnlineVideo::where('id', $videoId)->decrement('favorites_count');

                $message = '取消收藏成功';
                $isFavorited = false;
            } else {
                // 添加收藏
                VideoFavorite::create([
                    'user_id' => $userId,
                    'video_id' => $videoId,
                ]);

                // 更新收藏数
                OnlineVideo::where('id', $videoId)->increment('favorites_count');

                $message = '收藏成功';
                $isFavorited = true;
            }

            DB::commit();

            // 获取最新的收藏数
            $favoritesCount = OnlineVideo::where('id', $videoId)->value('favorites_count');

            return $this->sendSuccess([
                'message' => $message,
                'is_favorited' => $isFavorited,
                'favorites_count' => $favoritesCount
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendErrorWithMessage('操作失败，请稍后重试');
        }
    }

    /**
     * 获取用户收藏的视频列表
     */
    public function getFavorites(Request $request)
    {
        $perPage = $request->get('per_page', 30);

        $favoriteVideos = $request->user()
            ->favoriteVideos()
            ->where('is_synced', true)
            ->paginate($perPage);

        // 添加收藏状态（都是已收藏）
        $favoriteVideos->getCollection()->transform(function ($video) {
            $video->is_favorited = true;
            return $video;
        });

        return $this->sendSuccess($favoriteVideos);
    }

    /**
     * 获取视频详情（包含收藏状态）
     */
    public function getVideoDetail(Request $request, $id)
    {
        $video = OnlineVideo::findOrFail($id);

        // 检查用户是否是视频VIP - 统一使用 UTC 时间进行比较
        $user = $request->user();
        $isVip = $user && !empty($user->video_expired_at) && Carbon::parse($user->video_expired_at, 'UTC')->greaterThan(Carbon::now('UTC'));

        // 如果不是VIP，移除播放URL
        if (!$isVip) {
            unset($video->url);
            unset($video->video_url);
        }

        // 如果用户已登录，检查收藏状态
        if ($user) {
            $video->is_favorited = VideoFavorite::where('user_id', $user->id)
                ->where('video_id', $id)
                ->exists();
        } else {
            $video->is_favorited = false;
        }

        return $this->sendSuccess($video);
    }

    /**
     * Delete a video (Admin only)
     */
    public function destroy($id)
    {
        $video = OnlineVideo::findOrFail($id);

        // Check if user is admin
        if (!auth()->user()->is_admin) {
            return $this->sendError('Unauthorized', 403);
        }

        $video->delete();

        return $this->sendSuccess(['message' => 'Video deleted successfully']);
    }

    /**
     * Update video title (Admin only)
     */
    public function updateTitle(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $video = OnlineVideo::findOrFail($id);

        // Check if user is admin
        if (!auth()->user()->is_admin) {
            return $this->sendError('Unauthorized', 403);
        }

        $video->name = $request->name;
        $video->save();

        return $this->sendSuccess([
            'message' => 'Title updated successfully',
            'video' => $video
        ]);
    }
}
