<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\Seeds\SeedTests;
use Tests\Support\Entities\Post;
use Tests\Support\Entities\User;
use Tests\Support\Models\PostModel;
use Tests\Support\Models\UserModel;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class HasOneOfManyTest extends TestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace;
    protected $seed = SeedTests::class;

    public function testLatestOfManyEagerLoad()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)
            ->with('latestPost')
            ->find(1);

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue(isset($user->latestPost));
        $this->assertNotNull($user->latestPost);

        /** @var list<Post> $allPosts */
        $allPosts = model(PostModel::class)
            ->where('user_id', 1)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        if (count($allPosts) > 0) {
            $this->assertSame($allPosts[0]->id, $user->latestPost->id);
        }
    }

    public function testOldestOfManyEagerLoad()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)
            ->with('oldestPost')
            ->find(1);

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue(isset($user->oldestPost));
        $this->assertNotNull($user->oldestPost);

        /** @var list<Post> $allPosts */
        $allPosts = model(PostModel::class)
            ->where('user_id', 1)
            ->orderBy('created_at', 'ASC')
            ->findAll();

        if (count($allPosts) > 0) {
            $this->assertSame($allPosts[0]->id, $user->oldestPost->id);
        }
    }

    public function testLatestOfManyWithMultipleUsers()
    {
        /** @var list<User> $users */
        $users = model(UserModel::class)
            ->with('latestPost')
            ->findAll();

        $this->assertNotEmpty($users);

        foreach ($users as $user) {
            if (isset($user->latestPost)) {
                // Each user should have their own latest post
                $this->assertSame($user->id, $user->latestPost->user_id);

                // Verify it's actually the latest for this user
                /** @var list<Post> $userPosts */
                $userPosts = model(PostModel::class)
                    ->where('user_id', $user->id)
                    ->orderBy('created_at', 'DESC')
                    ->findAll();

                if (count($userPosts) > 0) {
                    $this->assertSame($userPosts[0]->id, $user->latestPost->id);
                }
            }
        }
    }

    public function testLatestOfManyWithCallback()
    {
        $user = model(UserModel::class)
            ->with('latestPublishedPost')
            ->find(1);

        $this->assertInstanceOf(User::class, $user);

        if (isset($user->latestPublishedPost)) {
            $this->assertSame('published', $user->latestPublishedPost->status);
        }
    }

    public function testLatestOfManyLazyLoad()
    {
        $user = model(UserModel::class)
            ->asObject(User::class)
            ->find(1);

        $this->assertInstanceOf(User::class, $user);

        $latestPost = $user->latestPost;

        if ($latestPost !== null) {
            $this->assertSame('1', $latestPost->user_id);
        }
    }

    public function testOldestOfManyLazyLoad()
    {
        $user = model(UserModel::class)
            ->asObject(User::class)
            ->find(1);

        $this->assertInstanceOf(User::class, $user);

        $oldestPost = $user->oldestPost;

        if ($oldestPost !== null) {
            $this->assertSame('1', $oldestPost->user_id);
        }
    }

    public function testLatestOfManyWithNoRecords()
    {
        $userId = model(UserModel::class)->insert([
            'name'  => 'No Posts User',
            'email' => 'noposts@test.com',
        ]);

        $user = model(UserModel::class)
            ->with('latestPost')
            ->find($userId);

        $this->assertInstanceOf(User::class, $user);
        $this->assertFalse(isset($user->latestPost));
    }

    public function testLatestOfManyWithSingleRecord()
    {
        $userId = model(UserModel::class)->insert([
            'name'  => 'Single Post User',
            'email' => 'single@test.com',
        ]);

        $postId = model(PostModel::class)->insert([
            'user_id' => $userId,
            'title'   => 'Only Post',
            'content' => 'Content',
            'status'  => 'published',
        ]);

        $user = model(UserModel::class)
            ->with('latestPost')
            ->find($userId);

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue(isset($user->latestPost));
        $this->assertSame((string) $postId, $user->latestPost->id);
    }

    public function testOfManyDoesNotAffectRegularHasOne()
    {
        $user = model(UserModel::class)
            ->with('profile')
            ->find(1);

        $this->assertInstanceOf(User::class, $user);
    }

    public function testLatestOfManyReturnsCorrectRecord()
    {
        $userId = model(UserModel::class)->insert([
            'name'  => 'Test User',
            'email' => 'test@test.com',
        ]);

        $db = db_connect();

        $db->table('posts')->insert([
            'user_id'    => $userId,
            'title'      => 'Old Post',
            'content'    => 'Old content',
            'status'     => 'published',
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
        ]);

        $db->table('posts')->insert([
            'user_id'    => $userId,
            'title'      => 'Middle Post',
            'content'    => 'Middle content',
            'status'     => 'published',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
        ]);

        $db->table('posts')->insert([
            'user_id'    => $userId,
            'title'      => 'Newest Post',
            'content'    => 'Newest content',
            'status'     => 'published',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);
        $newestPostId = $db->insertID();

        $user = model(UserModel::class)
            ->with('latestPost')
            ->find($userId);

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue(isset($user->latestPost));
        $this->assertSame((string) $newestPostId, $user->latestPost->id);
        $this->assertSame('Newest Post', $user->latestPost->title);
    }

    public function testOldestOfManyReturnsCorrectRecord()
    {
        $userId = model(UserModel::class)->insert([
            'name'  => 'Test User 2',
            'email' => 'test2@test.com',
        ]);

        $db = db_connect();

        $db->table('posts')->insert([
            'user_id'    => $userId,
            'title'      => 'First Post',
            'content'    => 'First content',
            'status'     => 'published',
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
        ]);
        $oldestPostId = $db->insertID();

        $db->table('posts')->insert([
            'user_id'    => $userId,
            'title'      => 'Second Post',
            'content'    => 'Second content',
            'status'     => 'published',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
        ]);

        $user = model(UserModel::class)
            ->with('oldestPost')
            ->find($userId);

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue(isset($user->oldestPost));
        $this->assertSame((string) $oldestPostId, $user->oldestPost->id);
        $this->assertSame('First Post', $user->oldestPost->title);
    }
}
