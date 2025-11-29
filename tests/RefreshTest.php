<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\Seeds\SeedTests;
use Tests\Support\Entities\Country;
use Tests\Support\Entities\Student;
use Tests\Support\Entities\User;
use Tests\Support\Models\CountryModel;
use Tests\Support\Models\PostModel;
use Tests\Support\Models\StudentModel;
use Tests\Support\Models\UserModel;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class RefreshTest extends TestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace;
    protected $seed = SeedTests::class;

    public function testRefreshReloadsEntityAttributes()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);
        $this->assertInstanceOf(User::class, $user);

        $originalName = $user->name;

        model(UserModel::class)->update($user->id, ['name' => 'Updated Name']);

        $this->assertSame($originalName, $user->name);

        $user->refresh();

        $this->assertSame('Updated Name', $user->name);
    }

    public function testRefreshReloadsAllLoadedRelations()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->with(['country', 'posts'])->find(1);
        $this->assertInstanceOf(User::class, $user);
        $this->assertInstanceOf(Country::class, $user->country);

        $originalPostCount = count($user->posts);

        model(UserModel::class)->update($user->id, ['country_id' => '2']);
        $user->country_id = '2';

        model(PostModel::class)->insert([
            'user_id' => $user->id,
            'title'   => 'New Post After Load',
            'content' => 'Test content',
            'status'  => 'published',
        ]);

        $this->assertSame('1', $user->country->id);
        $this->assertCount($originalPostCount, $user->posts);

        $user->refresh();

        $this->assertSame('2', $user->country->id);
        $this->assertCount($originalPostCount + 1, $user->posts);
    }

    public function testRefreshOnlyReloadsLoadedRelations()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->with('country')->find(1);
        $this->assertInstanceOf(User::class, $user);
        $this->assertInstanceOf(Country::class, $user->country);

        $loadedRelations = $user->getLoadedRelations();
        $this->assertArrayHasKey('country', $loadedRelations);
        $this->assertArrayNotHasKey('posts', $loadedRelations);

        $user->refresh();

        $loadedRelations = $user->getLoadedRelations();
        $this->assertArrayHasKey('country', $loadedRelations);
        $this->assertArrayNotHasKey('posts', $loadedRelations);
    }

    public function testRefreshWithNestedRelations()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->with(['posts', 'posts.comments'])->find(1);
        $this->assertInstanceOf(User::class, $user);

        $originalPostCount = count($user->posts);

        model(PostModel::class)->insert([
            'user_id' => $user->id,
            'title'   => 'New Post with Comments',
            'content' => 'Test content',
            'status'  => 'published',
        ]);

        $this->assertCount($originalPostCount, $user->posts);

        $user->refresh();
        $this->assertCount($originalPostCount + 1, $user->posts);

        foreach ($user->posts as $post) {
            $loadedRelations = $post->getLoadedRelations();
            $this->assertArrayHasKey('comments', $loadedRelations);
            $this->assertSame('eager', $loadedRelations['comments']['type']);
        }
    }

    public function testRefreshPreservesQueryCallbacks()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->with('publishedPosts')->find(1);
        $this->assertInstanceOf(User::class, $user);

        $publishedCount = count($user->publishedPosts);

        model(PostModel::class)->insert([
            'user_id' => $user->id,
            'title'   => 'Draft Post',
            'content' => 'Draft content',
            'status'  => 'draft',
        ]);

        model(PostModel::class)->insert([
            'user_id' => $user->id,
            'title'   => 'Published Post',
            'content' => 'Published content',
            'status'  => 'published',
        ]);

        $user->refresh();

        $this->assertCount($publishedCount + 1, $user->publishedPosts);

        foreach ($user->publishedPosts as $post) {
            $this->assertSame('published', $post->status);
        }
    }

    public function testRefreshWithLazyLoadedRelation()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);

        $originalPostCount = count($user->posts);

        model(PostModel::class)->insert([
            'user_id' => $user->id,
            'title'   => 'New Post',
            'content' => 'Content',
            'status'  => 'published',
        ]);

        $this->assertCount($originalPostCount, $user->posts);

        $user->refresh();

        $this->assertCount($originalPostCount + 1, $user->posts);
    }

    public function testBelongsToAssociateUpdatesInMemory()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->with('country')->find(1);
        $this->assertInstanceOf(Country::class, $user->country);
        $this->assertSame('1', $user->country->id);

        /** @var Country|null $newCountry */
        $newCountry = model(CountryModel::class)->find(2);
        $user->country()->associate($newCountry);

        $this->assertSame('2', $user->country->id);
        $this->assertSame($newCountry->name, $user->country->name);
    }

    public function testBelongsToDissociateUpdatesInMemory()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->with('country')->find(1);
        $this->assertInstanceOf(Country::class, $user->country);

        $user->country()->dissociate();

        $this->assertNull($user->country);
    }

    public function testBelongsToAssociateWithLazyLoad()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);

        $country = $user->country;
        $this->assertInstanceOf(Country::class, $country);
        $this->assertSame('1', $country->id);

        /** @var Country|null $newCountry */
        $newCountry = model(CountryModel::class)->find(2);
        $user->country()->associate($newCountry);

        $this->assertSame('2', $user->country->id);
    }

    public function testBelongsToManyAttachRequiresManualRefresh()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->with('courses')->find(1);
        $this->assertInstanceOf(Student::class, $student);

        $originalCourseCount = count($student->courses);

        $student->courses()->attach(4);

        $this->assertCount($originalCourseCount, $student->courses);

        $student->refresh();

        $this->assertCount($originalCourseCount + 1, $student->courses);
    }

    public function testBelongsToManyDetachRequiresManualRefresh()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->with('courses')->find(1);
        $this->assertInstanceOf(Student::class, $student);

        $originalCourseCount = count($student->courses);
        $this->assertGreaterThan(0, $originalCourseCount);

        $firstCourseId = $student->courses[0]->id;
        $student->courses()->detach($firstCourseId);

        $this->assertCount($originalCourseCount, $student->courses);

        $student->refresh();

        $this->assertCount($originalCourseCount - 1, $student->courses);
    }

    public function testBelongsToManySyncRequiresManualRefresh()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->with('courses')->find(1);
        $this->assertInstanceOf(Student::class, $student);

        $originalCourseCount = count($student->courses);

        $student->courses()->sync([1, 2, 3]);

        $this->assertCount($originalCourseCount, $student->courses);

        $student->refresh();

        $this->assertCount(3, $student->courses);
    }

    public function testRefreshAfterRelationSave()
    {
        /** @var User|null $user */
        $user              = model(UserModel::class)->with('posts')->find(1);
        $originalPostCount = count($user->posts);

        $user->posts()->save([
            'title'   => 'New Post via Relation',
            'content' => 'Test content',
            'status'  => 'published',
        ]);

        $this->assertCount($originalPostCount, $user->posts);

        $user->refresh();
        $this->assertCount($originalPostCount + 1, $user->posts);
    }

    public function testRefreshAfterRelationSaveMany()
    {
        /** @var User|null $user */
        $user              = model(UserModel::class)->with('posts')->find(1);
        $originalPostCount = count($user->posts);

        $user->posts()->saveMany([
            [
                'title'   => 'Post 1 via SaveMany',
                'content' => 'Content 1',
                'status'  => 'published',
            ],
            [
                'title'   => 'Post 2 via SaveMany',
                'content' => 'Content 2',
                'status'  => 'draft',
            ],
        ]);

        $this->assertCount($originalPostCount, $user->posts);

        $user->refresh();
        $this->assertCount($originalPostCount + 2, $user->posts);
    }

    public function testMultipleRefreshCalls()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->with('country')->find(1);

        model(UserModel::class)->update($user->id, ['country_id' => 2]);
        $user->refresh();
        $this->assertSame('2', $user->country->id);

        model(UserModel::class)->update($user->id, ['country_id' => 1]);
        $user->refresh();
        $this->assertSame('1', $user->country->id);

        model(UserModel::class)->update($user->id, ['country_id' => 2]);
        $user->refresh();
        $this->assertSame('2', $user->country->id);
    }

    public function testRefreshAfterEntitySave()
    {
        /** @var User|null $user */
        $user              = model(UserModel::class)->with('posts')->find(1);
        $originalPostCount = count($user->posts);

        $user->name = 'Updated Name';
        $user->save();

        model(PostModel::class)->insert([
            'user_id' => $user->id,
            'title'   => 'Post Added After Save',
            'content' => 'Content',
            'status'  => 'published',
        ]);

        $this->assertCount($originalPostCount, $user->posts);

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertCount($originalPostCount + 1, $user->posts);
    }
}
