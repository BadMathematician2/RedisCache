<?php


namespace RedisCache\Repositories\Interfaces;


interface RedisCacheRepositoryInterface
{
    public function find(int $id);

    public function getAttribute($id, $attribute);

    public function setAttribute($id,$attribute,$value);
}
