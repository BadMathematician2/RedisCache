<?php


namespace RedisCache\Traits;


use RedisCache\Observer\CacheObserver;

/**
 * Trait CacheTrait
 * @package App\Packages\RedisCache\Traits
 */
trait CacheTrait
{
    public static function bootCacheTrait()
    {
        static::observe(CacheObserver::class);
    }

    public static function checkCacheTrait()
    {
        return true;
    }



}
