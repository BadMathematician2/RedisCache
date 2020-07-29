<?php


namespace RedisCache\Repositories;


use RedisCache\Exceptions\NotUsedTraitException;
use RedisCache\Repositories\Interfaces\RedisCacheRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Class RedisCacheRepository
 * @package App\Packages\RedisCache\Repositories
 */
class RedisCacheRepository implements RedisCacheRepositoryInterface
{

    /**
     * @var Model
     */
    protected $model;
    /**
     * @var array
     */
    protected $caches = [];

    /**
     * @return array
     */
    public function getCaches(): array
    {
        return $this->caches;
    }

    /**
     * @return static
     */
    static function getStatic()
    {
        return new static();
    }
    public function setCaches()
    {
        $keys = app('redis')->keys(str_ireplace('\\', '?', $this->model . '_*'));
        foreach ($keys as $key) {
            $this->caches += [$key => app('redis')->get($key)];
        }
    }

    /**
     * @param string $model
     * @return RedisCacheRepository
     */
    public function setModel(string $model)
    {
        $this->model = $model;
        $this->setCaches();
        return $this;
    }

    /**
     * @param int $id
     * @return bool
     */
    private function existInCache($id)
    {
        if (app('redis')->exists($this->model . '_' . $id) === 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param int $id
     * @return bool|mixed|string
     */
    private function getFromCache($id)
    {
        return unserialize(app('redis')->get($this->model . '_' . $id));
    }

    /**
     * @param int $id
     */
    private function setInCache($id)
    {
        $keys = app('redis')->keys(str_ireplace('\\', '?', $this->model . '_*'));
        if (sizeof($keys) >= config('redisCache.max_count')) {
            $this->deleteWithMinTime($keys);
        }

        $cache = $this->model::query()->find($id);
        app('redis')->set($this->model . '_' . $id, serialize($cache), 'ex', config('redisCache.time'));

        if (array_key_exists($this->model . '_' . $id,$this->caches)){
            unset($this->caches[$this->model . '_' . $id]);
        }

        $this->caches += [$this->model . '_' . $id => serialize($cache)];
    }

    /**
     * @param array $keys
     */
    private function deleteWithMinTime($keys)
    {
        $min = app('redis')->ttl($keys[0]);
        $key_with_min = $keys[0];
        foreach ($keys as $key) {
            if (app('redis')->ttl($key) < $min) {
                $min = app('redis')->ttl($key);
                $key_with_min = $key;
            }
        }
        app('redis')->del($key_with_min);
    }

    /**
     * @param int $id
     * @return bool|mixed|string
     * @throws NotUsedTraitException
     */
    public function find($id)
    {

        if (!method_exists($this->model, 'checkCacheTrait')) {
            throw new NotUsedTraitException('Sorry, but your model don\'t use the important trait');
        }

        if (!$this->existInCache($id)) {
            $this->setInCache($id);
        }

        return $this->getFromCache($id);
    }

    /**
     * @param int $id
     * @param string $attribute
     * @return mixed
     */
    public function getAttribute($id, $attribute)
    {
        if (!array_key_exists($this->model . '_' . $id, $this->caches)) {
            $this->setInCache($id);
        }
        $cache = unserialize($this->caches[$this->model . '_' . $id]);

        return $cache->$attribute;
    }

    /**
     * @param int $id
     * @param string $attribute
     * @param $value
     */
    public function setAttribute($id, $attribute, $value)
    {
        $this->model::query()->find($id)->setAttribute($attribute,$value)->save();
        $this->setInCache($id);
    }

    /**
     * @return string
     */
    public function clearCache()
    {
        $keys = app('redis')->keys(str_ireplace('\\', '?', $this->model . '_*'));
        foreach ($keys as $key) {
             app('redis')->del($key);
        }
        return 'Done';
    }

}
