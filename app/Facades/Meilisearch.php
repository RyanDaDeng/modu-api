<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Meilisearch\Endpoints\Indexes index(string $uid)
 * @method static \Meilisearch\Contracts\IndexesResults getIndexes(array $options = [])
 * @method static array health()
 * @method static array version()
 * @method static array stats()
 * 
 * @see \Meilisearch\Client
 */
class Meilisearch extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'meilisearch';
    }
}