<?php


namespace RedisCache\Repositories;


use App\Packages\RedisCache\src\Exceptions\WrongCallException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Events\QueryExecuted;
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
     * @var Model
     */
    protected $data;
    /**
     * @var int|null
     */
    private $id = null;

    /**
     * @var RedisCacheRepository[]
     */
    protected $related_model;

    /**
     * @return mixed
     */
    public function getRelatedModel()
    {
        return $this->related_model;
    }

    /**
     * @return Model
     */
    public function getData()
    {
        return $this->data;
    }
    public function setData()
    {
        $this->data = unserialize(app('redis')->get($this->model . '_' . $this->id));

    }
    /**
     * @return string
     */
    private function getKey(): string
    {
        return $this->model . '_' . $this->id;
    }


    public function start()
    {

        \DB::listen(function(QueryExecuted $queryExecuted) {
            echo $this->getEloquentSqlWithBindings($queryExecuted) . '<br>';
        });


    }
    /**
     * @param $query
     * @return string
     */
    public function getEloquentSqlWithBindings(QueryExecuted $query)
    {
        return vsprintf(str_replace('?', '%s', $query->sql), collect($query->bindings)->map(function ($binding) {
            return is_numeric($binding) ? $binding : "'{$binding}'";
        })->toArray());
    }


    public static function make($model)
    {
        try {
            return (new static)->setModel($model);
        } catch (NotUsedTraitException $e) {
            return null;
        }
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
        app('redis')->set($this->getKey(), serialize($cache));
        app('redis')->expire($this->getKey(),config('redisCache.time'));

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
     * @param string $model
     * @return RedisCacheRepository
     * @throws NotUsedTraitException
     */
    private function setModel(string $model)
    {
        $this->model = $model;

        if (!method_exists($this->model, 'checkCacheTrait')) {
            throw new NotUsedTraitException('Sorry, but your model don\'t use the important trait');
        }

        return $this;
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
     */
    public function getAttribute($attribute)
    {
        return $this->data->getAttribute($attribute);
    }

    /**
     * @param string $attribute
     * @param $value
     * @return RedisCacheRepository
     */
    public function setAttribute($attribute, $value)
    {
        $this->data->setAttribute($attribute,$value);
        app('redis')->set($this->getKey(), serialize($this->data));
        app('redis')->expire($this->getKey(),config('redisCache.time'));

        return $this;

    }

    /**
     * @param array $values
     * @return $this
     */
    public function setAttributes($values)
    {
        foreach ($values as $key=> $value){
            $this->setAttribute($key,$value);
        }

        return $this;
    }
    public function save()
    {
        $this->data->save();
    }


    /**
     * @param string[] $relations
     * @return $this
     */
    public function with(...$relations)
    {
        foreach ($relations as $relation) {

            $model = $this->model::query()->with($relation)->getRelation($relation)->getModel();

            $foreign_key = $model->getForeignKey();

            $related_model_name = get_class($model);
            $id = $this->getAttribute($foreign_key);

            $this->related_model[$relation] = RedisCacheRepository::make($related_model_name)->find($id);
        }

        return $this;
    }

    /**
     * @param string $name
     * @return mixed|RedisCacheRepository
     * @throws WrongCallException
     */
    public function __get($name)
    {
        if ( ! array_key_exists($name, $this->getRelatedModel())) {
            throw new WrongCallException('Sorry, i can\'t do that');
        }

        return $this->getRelatedModel()[$name];
    }

    /**
     * @return string
     */
    public function clearCache()
    {
        $keys = app('redis')->keys(str_ireplace('\\', '\\\\', $this->model . '_*'));
        foreach ($keys as $key) {
            app('redis')->del($key);
        }
        return 'Done';
    }

}
