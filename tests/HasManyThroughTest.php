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
final class HasManyThroughTest extends TestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace;
    protected $seed = SeedTests::class;

    public function testEagerLoadHasManyThroughWithFind()
    {
        $country = model(CountryModel::class)->find(1);
        $this->assertIsObject($country);

        $this->assertFalse(isset($country->posts));

        $country = model(CountryModel::class)->with('posts')->find(1);
        $this->assertIsObject($country);

        $this->assertTrue(isset($country->posts));

        $this->assertIsArray($country->posts);

        if ($country->posts !== []) {
            $this->assertInstanceOf(Post::class, $country->posts[0]);
            $this->assertSame($country->posts[0]->user_id, $country->posts[0]->user->id);
        }
    }

    public function testEagerLoadHasManyThroughWithFindAll()
    {
        $countries = model(CountryModel::class)->findAll();
        $this->assertFalse(isset($countries[0]->posts));

        $countries = model(CountryModel::class)->with('posts')->findAll();

        $this->assertTrue(isset($countries[0]->posts));
        $this->assertIsArray($countries[0]->posts);
    }

    public function testEagerLoadHasManyThroughWithCallback()
    {
        $country = model(CountryModel::class)
            ->with('posts', static function ($model) {
                $model->where('status', 'published');
            })
            ->find(1);

        $this->assertIsObject($country);
        $this->assertTrue(isset($country->posts));
        $this->assertIsArray($country->posts);

        foreach ($country->posts as $post) {
            $this->assertInstanceOf(Post::class, $post);
            $this->assertSame('published', $post->status);
        }
    }

    public function testHasManyThroughWithCallbackLimitAndOrderBy()
    {
        /** @var list<Country> $countries */
        $countries = model(CountryModel::class)
            ->with('posts', static function ($model) {
                $model->orderBy('created_at', 'DESC')->limit(2);
            })
            ->find([1, 2]);

        $this->assertCount(2, $countries);

        // Each country should have exactly 2 posts, ordered by created_at DESC
        foreach ($countries as $country) {
            $this->assertCount(2, $country->posts);

            $this->assertGreaterThanOrEqual(
                $country->posts[1]->created_at,
                $country->posts[0]->created_at,
            );
        }
    }

    public function testEagerLoadHasManyThroughWithPredefinedCallback()
    {
        $country = model(CountryModel::class)->with('publishedPosts')->find(1);

        $this->assertIsObject($country);
        $this->assertTrue(isset($country->publishedPosts));
        $this->assertIsArray($country->publishedPosts);

        foreach ($country->publishedPosts as $post) {
            $this->assertInstanceOf(Post::class, $post);
            $this->assertSame('published', $post->status);
        }
    }

    public function testEagerLoadHasManyThroughAsArray()
    {
        $country = model(CountryModel::class)
            ->with('posts', static function ($model) {
                $model->asArray();
            })
            ->find(1);

        $this->assertIsObject($country);
        $this->assertTrue(isset($country->posts));
        $this->assertIsArray($country->posts);

        if ($country->posts !== []) {
            $this->assertIsArray($country->posts[0]);
            $this->assertArrayHasKey('user_id', $country->posts[0]);
        }
    }

    public function testEagerLoadHasManyThroughWithModelAsArray()
    {
        $countries = model(CountryModel::class)
            ->asArray()
            ->with('posts', static function ($model) {
                $model->asArray();
            })
            ->findAll();

        $this->assertIsArray($countries[0]);
        $this->assertArrayHasKey('id', $countries[0]);
        $this->assertArrayHasKey('name', $countries[0]);

        $this->assertArrayHasKey('posts', $countries[0]);
        /** @var mixed $posts */
        $posts = $countries[0]['posts'];
        $this->assertIsArray($posts);

        if ($posts !== []) {
            $this->assertIsArray($posts[0]);
            $this->assertArrayHasKey('user_id', $posts[0]);
        }
    }

    public function testEagerLoadHasManyThroughWithModelAsObject()
    {
        $countries = model(CountryModel::class)
            ->asObject()
            ->with('posts', static function ($model) {
                $model->asObject();
            })
            ->findAll();

        $this->assertInstanceOf(stdClass::class, $countries[0]);
        $this->assertObjectHasProperty('id', $countries[0]);
        $this->assertObjectHasProperty('name', $countries[0]);

        $this->assertObjectHasProperty('posts', $countries[0]);
        $this->assertIsArray($countries[0]->posts);

        if ($countries[0]->posts !== []) {
            $this->assertInstanceOf(stdClass::class, $countries[0]->posts[0]);
            $this->assertObjectHasProperty('user_id', $countries[0]->posts[0]);
        }
    }

    public function testEagerLoadHasManyThroughReturnsEmptyArray()
    {
        $countries = model(CountryModel::class)->with('posts')->findAll();

        $this->assertGreaterThan(0, count($countries));

        foreach ($countries as $country) {
            $this->assertTrue(isset($country->posts));
            $this->assertIsArray($country->posts);
        }
    }

    public function testHasManyThroughDoesNotLoadWithoutWith()
    {
        $country = model(CountryModel::class)->find(1);

        $this->assertIsObject($country);

        $this->assertFalse(isset($country->posts));
    }

    public function testLazyLoadHasManyThrough()
    {
        $country = model(CountryModel::class)->find(1);
        $this->assertInstanceOf(Country::class, $country);

        $this->assertFalse(isset($country->posts));

        $posts = $country->posts;

        $this->assertIsArray($posts, 'Lazy loading should load the posts relation as an array');
        $this->assertNotEmpty($posts, 'Country should have posts through users');
        $this->assertInstanceOf(Post::class, $posts[0]);

        $this->assertTrue(isset($country->posts));
        $this->assertSame($posts, $country->posts);
    }

    public function testSaveThrowsException()
    {
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Cannot save data through HasManyThrough relation. Through relations are read-only. To modify related data, save directly through the intermediate model.');

        $countryModel = model(CountryModel::class);
        $relation     = $countryModel->posts();

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
        $this->expectExceptionMessage('Cannot save data through HasManyThrough relation. Through relations are read-only. To modify related data, save directly through the intermediate model.');

        $countryModel = model(CountryModel::class);
        $relation     = $countryModel->posts();

        $relation->saveMany([
            ['id' => 1, 'title' => 'Post 1', 'content' => 'Content 1', 'status' => 'published'],
            ['id' => 2, 'title' => 'Post 2', 'content' => 'Content 2', 'status' => 'draft'],
        ]);
    }

    public function testHasManyThroughRelationStructure()
    {
        $countryModel = model(CountryModel::class);
        $relation     = $countryModel->posts();

        $this->assertFalse($relation->getType()->isSingular());
    }

    public function testMultipleHasManyThroughRelations()
    {
        $country = model(CountryModel::class)
            ->with('posts')
            ->with('publishedPosts')
            ->find(1);

        $this->assertIsObject($country);

        $this->assertTrue(isset($country->posts));
        $this->assertTrue(isset($country->publishedPosts));

        $this->assertIsArray($country->posts);
        $this->assertIsArray($country->publishedPosts);

        $this->assertLessThanOrEqual(
            count($country->posts),
            count($country->publishedPosts),
        );
    }

    public function testHasManyThroughWithMultipleCountries()
    {
        $countries = model(CountryModel::class)->with('posts')->findAll();

        foreach ($countries as $country) {
            $this->assertTrue(isset($country->posts));
            $this->assertIsArray($country->posts);

            $this->assertContainsOnlyInstancesOf(Post::class, $country->posts);
        }
    }
}
