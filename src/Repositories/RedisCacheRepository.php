<?php


namespace RedisCache\Repositories;


use App\Packages\RedisCache\src\Exceptions\WrongCallException;
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
    protected $class;
    /**
     * @var Model
     */
    protected $model;
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
    public function getModel()
    {
        return $this->model;
    }
    private function initData()
    {
        $this->model = unserialize(app('redis')->get($this->class . '_' . $this->id));

    }
    /**
     * @return string
     */
    private function getKey(): string
    {
        return $this->class . '_' . $this->id;
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



    public static function make($class)
    {
        try {
            app('redis')->select(1);
            return (new static)->setClass($class);
        } catch (NotUsedTraitException $e) {
            return null;
        }
    }
    /**
     * @return bool
     */
    private function existInCache(): bool
    {
        return app('redis')->exists($this->class . '_' . $this->id);
    }

    /**
     */
    private function setInCache()
    {
        if (app('redis')->get('n') >= 500 + config('redisCache.max_count')) {
            if (sizeof(app('redis')->keys('_*')) >= 500 + config('redisCache.max_count')) {
                $this->deleteWithMinTime();
                app('redis')->set('n', config('redisCache.max_count'));
            }
        }

        app('redis')->incr('n');

        $cache = $this->class::query()->find($this->id);
        app('redis')->set($this->getKey(), serialize($cache));
        app('redis')->expire($this->getKey(),config('redisCache.time'));

        app('redis')->rPush('times', $this->getKey() . '|' . microtime(true));
    }

    /**
     */
    private function deleteWithMinTime()
    {

        do{
            $key = app('redis')->lPop('times');
            $time = substr(stristr($key, '|'), 1);
        }while ($time <= microtime(true) - config('redisCache.time'));
        app('redis')->del(stristr($key,'|',true));
        for ($i = 0; $i < 500; $i++) {
            $key = app('redis')->lPop('times');
            app('redis')->del(stristr($key,'|',true));
        }




        /*$min = app('redis')->ttl($keys[0]);
        $key_with_min = $keys[0];
        foreach ($keys as $key) {
            if (app('redis')->ttl($key) < $min) {
                $min = app('redis')->ttl($key);
                $key_with_min = $key;
            }
        }
        app('redis')->del($key_with_min);*/
    }

    /**
     * @param string $class
     * @return RedisCacheRepository
     * @throws NotUsedTraitException
     */
    private function setClass(string $class)
    {
        $this->class = $class;

        if (! method_exists($this->class, 'checkCacheTrait')) {
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

        $this->initData();
        app('redis')->expire($this->getKey(),config('redisCache.time'));



        return $this;
    }

    /**
     * @param string $attribute
     * @return mixed
     */
    public function getAttribute($attribute)
    {
        return $this->model->getAttribute($attribute);
    }

    /**
     * @param string $attribute
     * @param $value
     * @return RedisCacheRepository
     */
    public function setAttribute($attribute, $value)
    {
        $this->model->setAttribute($attribute,$value);

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
        app('redis')->set($this->getKey(), serialize($this->model));
        app('redis')->expire($this->getKey(),config('redisCache.time'));

        $this->model->save();
    }

    private function loadRelatedModel($relation)
    {
        $class = $this->class::query()->with($relation)->getRelation($relation)->getModel();

        $foreign_key = $class->getForeignKey();

        $related_class = get_class($class);
        $id = $this->getAttribute($foreign_key);

        $this->related_model[$relation] = RedisCacheRepository::make($related_class)->find($id);
    }

    /**
     * @param string[] $relations
     * @return $this
     */
    public function with(...$relations)
    {
        foreach ($relations as $relation) {

            if ( ! isset($this->getRelatedModel()[$relation]) ) {
                $this->loadRelatedModel($relation);
            }
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

}
