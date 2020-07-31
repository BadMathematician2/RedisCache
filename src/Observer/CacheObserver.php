<?php


namespace RedisCache\Observer;


use Illuminate\Database\Eloquent\Model;

class CacheObserver
{
    /**
     * @param Model $cache
     */
    public function updated(Model $cache)
    {
        $this->clearCache($cache);
    }


    /**
     * @param Model $cache
     */
    public function deleting(Model $cache)
    {
        $this->clearCache($cache);
    }


    /**
     * @param Model $cache
     */
    private function clearCache(Model $cache)
    {
        app('redis')->del((array)get_class($cache) . '_' . $cache->getKey());
        app('redis')->decr('n');
    }

}
