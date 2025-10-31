<?php

namespace App\Models;

use App\Http\Controllers\Api\Video\OnlineVideoApiController;
use Laravel\Scout\Searchable;

class OnlineVideoSearch extends OnlineVideo
{
    use Searchable;
    protected $connection = 'video';
    protected $table = 'online_videos';

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $array = $this->toArray();

        return [
            'id' => $array['id'],
            'name' => $array['name'],
            'type_name' => $array['type_name'],
            'source' => $array['source'],
            'is_synced' => (int)$array['is_synced'],
            'created_at' => $array['created_at'],
        ];
    }

    /**
     * Determine if the model should be searchable.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->getAttribute('is_synced') > 0 && in_array($this->getAttribute('source'), OnlineVideoApiController::$sources);
    }

    public function searchableAs(): string
    {
        return 'video_index';
    }
}
