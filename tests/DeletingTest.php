<?php

namespace RedisTests;

use App\Packages\Points\Models\Point;
use RedisCache\Exceptions\NotUsedTraitException;
use RedisCache\Repositories\RedisCacheRepository;
use Tests\TestCase;

class DeletingTest extends TestCase
{
    /**
     * @var int
     */
    private $id = 1997014;
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
    public function testDeletingTest()
    {
        $redis = RedisCacheRepository::getStatic()->setModel($this->model);

        $redis->clearCache();

        $redis->find($this->id);
        if (app('redis')->exists($this->model . '_' . $this->id)==0){
            $this->assertNull('1');
        }

        $this->model::query()->find($this->id)->delete();
        $this->assertEquals(0,app('redis')->exists($this->model . '_' . $this->id));

    }
}
