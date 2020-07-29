<?php

namespace RedisTests;

use App\Packages\Points\Models\Point;
use RedisCache\Exceptions\NotUsedTraitException;
use RedisCache\Repositories\RedisCacheRepository;
use Tests\TestCase;

class UpdatingTest extends TestCase
{
    /**
     * @var int
     */
    private $id = 66;
    /**
     * @var string
     */
    private $attribute = 'latitude';
    /**
     * @var int
     */
    private $value = 666;
    /**
     * @var string
     */
    private $model = Point::class;

    /**
     * Updating test.
     *
     * @return void
     * @throws NotUsedTraitException
     */
    public function testUpdatingTest()
    {
        $redis = RedisCacheRepository::getStatic()->setModel($this->model);

        $redis->clearCache();

        $redis->find($this->id);
        if (app('redis')->exists($this->model . '_' . $this->id)==0){
            $this->assertNull('1');
        }

        $this->model::query()->find($this->id)->update([$this->attribute => rand(0,10000)]);
        $this->assertEquals(0,app('redis')->exists($this->model . '_' . $this->id));

    }
}
