<?php

namespace RedisTests;


use App\Models\Student;
use App\Packages\Points\Models\Point;
use RedisCache\Repositories\RedisCacheRepository;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;
class ModelsTest extends TestCase
{
    /**
     * @var int
     */
    private $id = 68;
    /**
     * @var string
     */
    private $attribute = 'latitude';
    /**
     * @var int
     */
    private $value = 676;
    /**
     * @var Model
     */
    private $model = Point::class;


    public function testModelsTest()
    {
        $redis = RedisCacheRepository::make($this->model);
        $redis->clearCache();

        $this->check($redis);

        $this->model = Student::class;  //берем нову модель і робимо ті ж самі перевірки
        $this->id = 12;
        $redis = RedisCacheRepository::make($this->model);
        $redis->clearCache();
        $this->attribute = 'name';
        $this->value = 'Somebody 2';

        $this->check($redis);
    }
    private function check($redis)
    {
        $this->assertEquals($this->model::query()->find($this->id), $redis->find($this->id)->getModel()); // Перевірка чи знайдений в репозиторії елемент = елементу із mysql
        $this->assertEquals($this->model::query()->find($this->id)->getAttribute($this->attribute), $redis->getAttribute($this->attribute)); //те саме тільки із конкретним атрибутом
        $redis->setAttribute($this->attribute,$this->value)->save(); //встановлення нового значення
        $this->assertEquals($this->value,$redis->getAttribute($this->attribute)); //перевірка чи правильно встановилось нове значення, беручи із кеша
        $this->assertEquals($this->value,$this->model::query()->find($this->id)->getAttribute($this->attribute));  // перевірка чи правильно встановилось нове значення, беручи із mysql

    }

}
