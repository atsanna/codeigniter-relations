<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\DatabaseTestTrait;
use Michalsn\CodeIgniterRelations\Exceptions\RelationException;
use Michalsn\CodeIgniterRelations\Exceptions\RelationWriteException;
use stdClass;
use Tests\Support\Database\Seeds\SeedTests;
use Tests\Support\Entities\Post;
use Tests\Support\Entities\User;
use Tests\Support\Models\PostModel;
use Tests\Support\Models\UserModel;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class HasManyTest extends TestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace;
    protected $seed = SeedTests::class;

    public function testEagerLoadHasManyWithFind()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);
        $this->assertInstanceOf(User::class, $user);

        $isset = isset($user->posts);
        $this->assertFalse($isset);

        /** @var User|null $user */
        $user = model(UserModel::class)->with('posts')->find(1);
        $this->assertInstanceOf(User::class, $user);

        $isset = isset($user->posts);
        $this->assertTrue($isset);

        $this->assertGreaterThan(0, count($user->posts));
        $this->assertSame('1', $user->posts[0]->user_id);
    }

    public function testEagerLoadHasManyWithFindAll()
    {
        /** @var list<User> $users */
        $users = model(UserModel::class)->findAll();

        $isset = isset($users[0]->posts);
        $this->assertFalse($isset);

        /** @var list<User> $users */
        $users = model(UserModel::class)->with('posts')->findAll();

        $isset = isset($users[0]->posts);
        $this->assertTrue($isset);

        $this->assertNotEmpty($users[0]->posts);
    }

    public function testEagerLoadHasManyAsArray()
    {
        $user = model(UserModel::class)
            ->with('posts', static function ($model) {
                $model->asArray();
            })
            ->find(1);

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue(isset($user->posts));

        $this->assertNotEmpty($user->posts);
        $this->assertSame('1', $user->posts[0]['user_id']);
    }

    public function testEagerLoadHasManyWithModelAsArray()
    {
        $users = model(UserModel::class)
            ->asArray()
            ->with('posts', static function ($model) {
                $model->asArray();
            })
            ->findAll();

        $this->assertIsArray($users[0]);
        $this->assertArrayHasKey('id', $users[0]);
        $this->assertArrayHasKey('name', $users[0]);

        $this->assertArrayHasKey('posts', $users[0]);
        /** @var mixed $posts */
        $posts = $users[0]['posts'];
        $this->assertIsArray($posts);

        if ($posts !== []) {
            $this->assertIsArray($posts[0]);
            $this->assertArrayHasKey('user_id', $posts[0]);
            $this->assertSame($users[0]['id'], $posts[0]['user_id']);
        }
    }

    public function testEagerLoadHasManyWithModelAsObject()
    {
        $users = model(UserModel::class)
            ->asObject()
            ->with('posts', static function ($model) {
                $model->asObject();
            })
            ->findAll();

        $this->assertInstanceOf(stdClass::class, $users[0]);
        $this->assertObjectHasProperty('id', $users[0]);
        $this->assertObjectHasProperty('name', $users[0]);

        $this->assertObjectHasProperty('posts', $users[0]);
        $this->assertIsArray($users[0]->posts);

        if ($users[0]->posts !== []) {
            $this->assertInstanceOf(stdClass::class, $users[0]->posts[0]);
            $this->assertObjectHasProperty('user_id', $users[0]->posts[0]);
            $this->assertSame($users[0]->id, $users[0]->posts[0]->user_id);
        }
    }

    public function testHasManyDoesNotLoadWithoutWith()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);

        $this->assertInstanceOf(User::class, $user);

        $isset = isset($user->posts);
        $this->assertFalse($isset);
    }

    public function testHasManyWithCallback()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)
            ->with('posts', static function ($model) {
                $model->where('status', 'published');
            })
            ->find(1);

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue(isset($user->posts));
        $this->assertNotEmpty($user->posts);

        // All posts should have status = 'published'
        foreach ($user->posts as $post) {
            $this->assertSame('published', $post->status);
        }
    }

    public function testHasManyWithCallbackLimitAndOrderBy()
    {
        /** @var list<User> $users */
        $users = model(UserModel::class)
            ->with('posts', static function ($model) {
                $model->orderBy('created_at', 'DESC')->limit(2);
            })
            ->find([1, 2]);

        $this->assertCount(2, $users);

        // Each user should have at most 2 posts, ordered by created_at DESC
        foreach ($users as $user) {
            $this->assertCount(2, $user->posts);

            $this->assertGreaterThanOrEqual(
                $user->posts[1]->created_at,
                $user->posts[0]->created_at,
            );
        }
    }

    public function testLazyLoadHasMany()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);
        $this->assertInstanceOf(User::class, $user);

        $posts = $user->posts;

        $this->assertGreaterThan(0, count($posts));
        $this->assertSame('1', $posts[0]->user_id);
    }

    public function testSaveUpdatesExistingPost()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->with('posts')->find(1);
        $this->assertInstanceOf(User::class, $user);
        $this->assertGreaterThan(0, count($user->posts));

        $existingPost = $user->posts[0];

        $updatedPost = $user->posts()->save([
            'id'      => $existingPost->id,
            'title'   => 'Updated Post Title',
            'content' => $existingPost->content,
            'status'  => $existingPost->status,
        ]);

        $this->assertInstanceOf(Post::class, $updatedPost);
        $this->assertSame($existingPost->id, $updatedPost->id);
        $this->assertSame('Updated Post Title', $updatedPost->title);

        $this->seeInDatabase('posts', [
            'id'    => $existingPost->id,
            'title' => 'Updated Post Title',
        ]);
    }

    public function testSaveCreatesNewPostWhenNoneExists()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);

        $post = $user->posts()->save([
            'title'   => 'Created via save',
            'content' => 'New save content',
            'status'  => 'published',
        ]);

        $this->assertInstanceOf(Post::class, $post);
        $this->assertSame($user->id, $post->user_id);
        $this->assertSame('Created via save', $post->title);

        $this->seeInDatabase('posts', [
            'user_id' => $user->id,
            'title'   => 'Created via save',
        ]);
    }

    public function testSaveReturnsEntityOnSuccess()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->with('posts')->find(1);

        $post = $user->posts()->save([
            'id'      => $user->posts[0]->id,
            'title'   => 'Save Success Test',
            'content' => 'Success content',
            'status'  => 'published',
        ]);

        $this->assertInstanceOf(Post::class, $post);
    }

    public function testSaveReturnsFalseOnValidationFailure()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->with('posts')->find(1);

        $post = $user->posts()->save([
            'id'      => $user->posts[0]->id,
            'title'   => '', // Invalid if required
            'content' => '',
            'status'  => '',
        ]);

        $this->assertFalse($post);

        $errors = model(PostModel::class)->errors();
        $this->assertNotEmpty($errors);
    }

    public function testSaveThrowsExceptionWithoutParentContext()
    {
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Cannot call save() without parent context');

        $userModel = model(UserModel::class);
        $relation  = $userModel->posts();

        $relation->save(['title' => 'Test']);
    }

    public function testSaveWithArrayData()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);

        $postData = [
            'title'   => 'Array Save Post',
            'content' => 'Array save content',
            'status'  => 'draft',
        ];

        $post = $user->posts()->save($postData);

        $this->assertInstanceOf(Post::class, $post);
        $this->assertSame('Array Save Post', $post->title);
    }

    public function testSaveWithEntityData()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->with('posts')->find(1);

        $post        = $user->posts[0];
        $post->title = 'Entity Save Title';

        $savedPost = $user->posts()->save($post);

        $this->assertInstanceOf(Post::class, $savedPost);
        $this->assertSame('Entity Save Title', $savedPost->title);

        $this->seeInDatabase('posts', [
            'id'    => $post->id,
            'title' => 'Entity Save Title',
        ]);
    }

    public function testSaveManyMixedUpdatesAndInserts()
    {
        /** @var User|null $user */
        $user         = model(UserModel::class)->with('posts')->find(1);
        $existingPost = $user->posts[0];

        $postsData = [
            // Update existing
            [
                'id'      => $existingPost->id,
                'title'   => 'Updated via saveMany',
                'content' => $existingPost->content,
                'status'  => $existingPost->status,
            ],
            // Insert new
            [
                'title'   => 'New via saveMany',
                'content' => 'New content',
                'status'  => 'published',
            ],
        ];

        $ids = $user->posts()->saveMany($postsData);

        $this->assertCount(2, $ids);

        $this->seeInDatabase('posts', [
            'id'    => $existingPost->id,
            'title' => 'Updated via saveMany',
        ]);

        $this->seeInDatabase('posts', [
            'user_id' => $user->id,
            'title'   => 'New via saveMany',
        ]);
    }

    public function testSaveManyWithTransactionRollsBackOnFailure()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->with('posts')->find(1);

        $postsData = [
            ['title' => 'Valid Post', 'content' => 'Content', 'status' => 'published'],
            ['id' => $user->posts[0]->id, 'title' => '', 'content' => '', 'status' => ''], // Invalid
        ];

        try {
            $user->posts()->saveMany($postsData, useTransaction: true);
            $this->fail('Expected RelationWriteException was not thrown');
        } catch (RelationWriteException $e) {
            $this->assertStringContainsString('Batch save failed at record 1. Transaction rolled back.', $e->getMessage());
            $this->assertSame([1], $e->failedIndexes());
            $this->assertSame([
                1 => ['title' => 'The title field is required.', 'content' => 'The content field is required.'],
            ], $e->errors());
        }
    }

    public function testSaveManyWithoutTransactionPartialSuccess()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);

        $postsData = [
            ['title' => 'Valid Post', 'content' => 'Content', 'status' => 'published'],
            ['title' => '', 'content' => '', 'status' => ''], // Invalid
        ];

        try {
            $user->posts()->saveMany($postsData, useTransaction: false);
            $this->fail('Expected RelationWriteException was not thrown');
        } catch (RelationWriteException $e) {
            $this->assertStringContainsString('Batch operation completed with failures', $e->getMessage());
            $this->assertCount(1, $e->succeededIds());
        }
    }

    public function testSaveManyReturnsArrayOfIds()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);

        $postsData = [
            ['title' => 'Post 1', 'content' => 'Content 1', 'status' => 'published'],
            ['title' => 'Post 2', 'content' => 'Content 2', 'status' => 'draft'],
        ];

        $ids = $user->posts()->saveMany($postsData);

        $this->assertCount(2, $ids);
    }

    public function testSaveManyAllValidationErrorsCaptured()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);

        $postsData = [
            ['title' => '', 'content' => '', 'status' => ''], // Invalid
            ['title' => 'Valid', 'content' => 'Content', 'status' => 'published'],
            ['title' => '', 'content' => '', 'status' => ''], // Invalid
        ];

        try {
            $user->posts()->saveMany($postsData, useTransaction: false);
            $this->fail('Expected RelationWriteException was not thrown');
        } catch (RelationWriteException $e) {
            $this->assertCount(2, $e->failedIndexes());
            $this->assertSame([0, 2], $e->failedIndexes());
        }
    }

    public function testSaveManyThrowsExceptionWithoutParentContext()
    {
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Cannot call saveMany() without parent context');

        $userModel = model(UserModel::class);
        $relation  = $userModel->posts();

        $relation->saveMany([['title' => 'Test']]);
    }

    public function testSaveManyEmptyArray()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);

        $ids = $user->posts()->saveMany([]);

        $this->assertEmpty($ids);
    }

    public function testLazyLoadThenISave()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);
        $this->assertInstanceOf(User::class, $user);

        $posts = $user->posts;
        $this->assertNotEmpty($posts);

        $newPost = $user->posts()->save([
            'title'   => 'Lazy then insert',
            'content' => 'Lazy content',
            'status'  => 'published',
        ]);

        $this->assertInstanceOf(Post::class, $newPost);
        $this->assertSame($user->id, $newPost->user_id);
    }

    public function testMultipleSaveAttempts()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);

        $post1 = $user->posts()->save([
            'title'   => 'First Post',
            'content' => 'First content',
            'status'  => 'published',
        ]);

        $this->assertInstanceOf(Post::class, $post1);

        $post2 = $user->posts()->save([
            'title'   => 'Second Post',
            'content' => 'Second content',
            'status'  => 'draft',
        ]);

        $this->assertInstanceOf(Post::class, $post2);

        $this->seeInDatabase('posts', [
            'user_id' => $user->id,
            'title'   => 'First Post',
        ]);

        $this->seeInDatabase('posts', [
            'user_id' => $user->id,
            'title'   => 'Second Post',
        ]);
    }
}
