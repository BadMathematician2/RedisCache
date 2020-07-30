<?php


namespace RedisCache\Repositories;


use App\Packages\RedisCache\src\Exceptions\NotFindModelIdException;
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
    protected $data = [];
    /**
     * @var int|null
     */
    private $id = null;

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return static
     */
    static function getStatic()
    {
        return new static();
    }
    public function setData()
    {
        $this->data = unserialize(app('redis')->get($this->model . '_' . $this->id))->getAttributes();

    }

    /**
     * @param string $model
     * @return RedisCacheRepository
     * @throws NotUsedTraitException
     */
    public function setModel(string $model)
    {
        $this->model = $model;

        if (!method_exists($this->model, 'checkCacheTrait')) {
            throw new NotUsedTraitException('Sorry, but your model don\'t use the important trait');
        }

        return $this;
    }

    /**
     * @return bool
     */
    private function existInCache(): bool
    {
        return app('redis')->exists($this->model . '_' . $this->id);
    }

    /**
     */
    private function setInCache()
    {
        $keys = app('redis')->keys(str_ireplace('\\', '\\\\', $this->model . '_*'));
        if (sizeof($keys) >= config('redisCache.max_count')) {
            $this->deleteWithMinTime($keys);
        }

        $cache = $this->model::query()->find($this->id);
        app('redis')->set($this->model . '_' . $this->id, serialize($cache), 'ex', config('redisCache.time'));

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
     */
    public function find($id)
    {
        $this->id = $id;

        if (!$this->existInCache()) {
            $this->setInCache();
        }
        $this->setData();

        return $this;
    }

    /**
     * @param string $attribute
     * @return mixed
     * @throws NotFindModelIdException
     */
    public function getAttribute($attribute)
    {
        if ( $this->id == null ) {
            throw new NotFindModelIdException('You did not use method find, so i don\' know id.');
        }

        return $this->data[$attribute];
    }

    /**
     * @param string $attribute
     * @param $value
     * @throws NotFindModelIdException
     */
    public function setAttribute($attribute, $value)
    {
        if ( $this->id == null ) {
            throw new NotFindModelIdException('You did not use method find, so i don\' know id.');
        }

        $this->model::query()->find($this->id)->setAttribute($attribute,$value)->save();
        $this->setInCache();
        $this->data[$attribute] = $value;
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
