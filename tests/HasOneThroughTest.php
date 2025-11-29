<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\DatabaseTestTrait;
use Michalsn\CodeIgniterRelations\Exceptions\RelationException;
use stdClass;
use Tests\Support\Database\Seeds\SeedTests;
use Tests\Support\Entities\Country;
use Tests\Support\Entities\Post;
use Tests\Support\Models\CountryModel;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class HasOneThroughTest extends TestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace;
    protected $seed = SeedTests::class;

    public function testEagerLoadHasOneThroughWithFind()
    {
        /** @var Country|null $country */
        $country = model(CountryModel::class)->find(1);
        $this->assertInstanceOf(Country::class, $country);

        $this->assertFalse(isset($country->latestPost));

        $country = model(CountryModel::class)->with('latestPost')->find(1);
        $this->assertIsObject($country);

        $this->assertInstanceOf(Post::class, $country->latestPost);
    }

    public function testEagerLoadHasOneThroughWithFindAll()
    {
        $countries = model(CountryModel::class)->findAll();
        $this->assertNotEmpty($countries);
        $this->assertFalse(isset($countries[0]->latestPost));

        $countries = model(CountryModel::class)->with('latestPost')->findAll();
        $this->assertNotEmpty($countries);

        $this->assertTrue(isset($countries[0]->latestPost));
    }

    public function testEagerLoadHasOneThroughWithCallback()
    {
        $country = model(CountryModel::class)
            ->with('latestPost', static function ($model) {
                $model->where('status', 'published');
            })
            ->find(1);

        $this->assertInstanceOf(Country::class, $country);
        $this->assertTrue(isset($country->latestPost));

        $this->assertInstanceOf(Post::class, $country->latestPost);
        $this->assertSame('published', $country->latestPost->status);
    }

    public function testEagerLoadHasOneThroughReturnsNull()
    {
        $countries = model(CountryModel::class)->with('latestPost')->findAll();

        $this->assertGreaterThan(0, count($countries));

        foreach ($countries as $country) {
            $this->assertTrue(isset($country->latestPost));
        }
    }

    public function testEagerLoadHasOneThroughWithModelAsArray()
    {
        $countries = model(CountryModel::class)
            ->asArray()
            ->with('latestPost', static function ($model) {
                $model->asArray();
            })
            ->findAll();

        $this->assertIsArray($countries[0]);
        $this->assertArrayHasKey('id', $countries[0]);
        $this->assertArrayHasKey('name', $countries[0]);

        $this->assertArrayHasKey('latestPost', $countries[0]);

        if ($countries[0]['latestPost'] !== null) {
            $this->assertIsArray($countries[0]['latestPost']);
            $this->assertArrayHasKey('user_id', $countries[0]['latestPost']);
        }
    }

    public function testEagerLoadHasOneThroughWithModelAsObject()
    {
        $countries = model(CountryModel::class)
            ->asObject()
            ->with('latestPost', static function ($model) {
                $model->asObject();
            })
            ->findAll();

        $this->assertInstanceOf(stdClass::class, $countries[0]);
        $this->assertObjectHasProperty('id', $countries[0]);
        $this->assertObjectHasProperty('name', $countries[0]);

        $this->assertObjectHasProperty('latestPost', $countries[0]);

        if ($countries[0]->latestPost !== null) {
            $this->assertInstanceOf(stdClass::class, $countries[0]->latestPost);
            $this->assertObjectHasProperty('user_id', $countries[0]->latestPost);
        }
    }

    public function testHasOneThroughDoesNotLoadWithoutWith()
    {
        $country = model(CountryModel::class)->find(1);

        $this->assertIsObject($country);

        $this->assertFalse(isset($country->latestPost));
    }

    public function testLazyLoadHasOneThrough()
    {
        $country = model(CountryModel::class)->find(1);
        $this->assertInstanceOf(Country::class, $country);

        $this->assertFalse(isset($country->latestPost));

        $latestPost = $country->latestPost;

        $this->assertNotNull($latestPost);
        $this->assertInstanceOf(Post::class, $latestPost);

        $this->assertTrue(isset($country->latestPost));
        $this->assertSame($latestPost, $country->latestPost);
    }

    public function testSaveThrowsException()
    {
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Cannot save data through HasOneThrough relation. Through relations are read-only. To modify related data, save directly through the intermediate model.');

        $countryModel = model(CountryModel::class);
        $relation     = $countryModel->latestPost();

        $relation->save([
            'id'      => 1,
            'title'   => 'Updated Post',
            'content' => 'Updated',
            'status'  => 'published',
        ]);
    }

    public function testSaveManyThrowsException()
    {
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Cannot save data through HasOneThrough relation. Through relations are read-only. To modify related data, save directly through the intermediate model.');

        $countryModel = model(CountryModel::class);
        $relation     = $countryModel->latestPost();

        $relation->saveMany([
            ['id' => 1, 'title' => 'Post 1', 'content' => 'Content 1', 'status' => 'published'],
            ['id' => 2, 'title' => 'Post 2', 'content' => 'Content 2', 'status' => 'draft'],
        ]);
    }

    public function testHasOneThroughRelationStructure()
    {
        $countryModel = model(CountryModel::class);
        $relation     = $countryModel->latestPost();

        $this->assertTrue($relation->getType()->isSingular());
    }

    public function testHasOneThroughWithMultipleCountries()
    {
        $countries = model(CountryModel::class)->with('latestPost')->findAll();

        foreach ($countries as $country) {
            $this->assertTrue(isset($country->latestPost));
            $this->assertInstanceOf(Post::class, $country->latestPost);
        }
    }
}
