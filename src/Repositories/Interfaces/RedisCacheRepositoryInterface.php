<?php


namespace RedisCache\Repositories\Interfaces;


interface RedisCacheRepositoryInterface
{
    public function find(int $id);

    public function getAttribute(string $attribute);

    public function setAttribute(string $attribute,$value);
}
