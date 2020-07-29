<?php


namespace RedisCache;


use RedisCache\Repositories\Interfaces\RedisCacheRepositoryInterface;
use RedisCache\Repositories\RedisCacheRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Class RedisCacheServiceProvider
 * @package App\Packages\RedisCache
 */
class RedisCacheServiceProvider extends ServiceProvider
{

    public function boot()
    {
        $this->configRegister();
    }

    public function register()
    {
        $this->app->bind(
            RedisCacheRepositoryInterface::class,
            RedisCacheRepository::class
        );
    }
    private function configRegister()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/redisCache.php','redisCache'
        );
    }
}
