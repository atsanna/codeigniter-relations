<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\Seeds\SeedTests;
use Tests\Support\Entities\User;
use Tests\Support\Models\UserModel;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class EntitySyncTest extends TestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace;
    protected $seed = SeedTests::class;

    public function testSingleEntityWithRelationNotMarkedAsChanged()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->with('posts')->find(1);

        $this->assertInstanceOf(User::class, $user);

        $this->assertTrue(isset($user->posts));
        $this->assertFalse($user->hasChanged());
        $this->assertFalse($user->hasChanged('posts'));
    }

    public function testSingleEntityWithoutRelationNotMarkedAsChanged()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);

        $this->assertInstanceOf(User::class, $user);
        $this->assertFalse($user->hasChanged());
    }

    public function testSingleEntityUserModificationStillTracked()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->with('posts')->find(1);

        $user->name = 'Modified Name';

        $this->assertTrue($user->hasChanged());
        $this->assertTrue($user->hasChanged('name'));
        $this->assertFalse($user->hasChanged('posts'));
    }

    public function testMultipleEntitiesWithRelationNotMarkedAsChanged()
    {
        /** @var list<User> $users */
        $users = model(UserModel::class)->with('posts')->findAll();

        $this->assertGreaterThan(0, count($users));

        foreach ($users as $user) {
            $this->assertFalse($user->hasChanged());
            $this->assertFalse($user->hasChanged('posts'));
        }
    }

    public function testMultipleEntitiesUserModificationStillTracked()
    {
        /** @var list<User> $users */
        $users = model(UserModel::class)->with('posts')->findAll();

        $users[0]->name = 'Modified Name';

        $this->assertTrue($users[0]->hasChanged());
        $this->assertTrue($users[0]->hasChanged('name'));
        $this->assertFalse($users[0]->hasChanged('posts'));

        for ($i = 1; $i < count($users); $i++) {
            $this->assertFalse($users[$i]->hasChanged());
        }
    }

    public function testNestedRelationsTwoLevelsNotMarkedAsChanged()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->with('posts.comments')->find(1);

        $this->assertInstanceOf(User::class, $user);

        $this->assertFalse($user->hasChanged());
        $this->assertFalse($user->hasChanged('posts'));

        if (isset($user->posts) && count($user->posts) > 0) {
            $post = $user->posts[0];
            $this->assertFalse($post->hasChanged());
            $this->assertFalse($post->hasChanged('comments'));
        }
    }

    public function testNestedRelationsThreeLevelsNotMarkedAsChanged()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->with('posts.comments.user')->find(1);

        $this->assertInstanceOf(User::class, $user);

        $this->assertFalse($user->hasChanged());
        $this->assertFalse($user->hasChanged('posts'));

        if (isset($user->posts) && count($user->posts) > 0) {
            $post = $user->posts[0];
            $this->assertFalse($post->hasChanged());
            $this->assertFalse($post->hasChanged('comments'));

            if (isset($post->comments) && count($post->comments) > 0) {
                $comment = $post->comments[0];
                $this->assertFalse($comment->hasChanged());
                $this->assertFalse($comment->hasChanged('user'));
            }
        }
    }

    public function testNestedRelationsDeepModificationTracking()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->with('posts.comments')->find(1);

        $user->name = 'Modified User';

        if (isset($user->posts) && count($user->posts) > 0) {
            $post           = $user->posts;
            $post[0]->title = 'Modified Post';
            $user->posts    = $post;
        }

        $this->assertTrue($user->hasChanged());
        $this->assertTrue($user->hasChanged('name'));
        $this->assertFalse($user->hasChanged('posts')); // Relation itself not changed

        if (isset($user->posts) && count($user->posts) > 0) {
            $this->assertTrue($user->posts[0]->hasChanged());
            $this->assertTrue($user->posts[0]->hasChanged('title'));
            $this->assertFalse($user->posts[0]->hasChanged('comments')); // Relation not changed
        }
    }

    public function testMultipleRelationsNotMarkedAsChanged()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->with(['posts', 'profile', 'country'])->find(1);

        $this->assertInstanceOf(User::class, $user);

        $this->assertFalse($user->hasChanged());
        $this->assertFalse($user->hasChanged('posts'));
        $this->assertFalse($user->hasChanged('profile'));
        $this->assertFalse($user->hasChanged('country'));
    }

    public function testMultipleNestedRelationsNotMarkedAsChanged()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)
            ->with(['posts.comments', 'profile'])
            ->find(1);

        $this->assertInstanceOf(User::class, $user);

        $this->assertFalse($user->hasChanged());
        $this->assertFalse($user->hasChanged('posts'));
        $this->assertFalse($user->hasChanged('profile'));

        if (isset($user->posts) && count($user->posts) > 0) {
            $this->assertFalse($user->posts[0]->hasChanged());
            $this->assertFalse($user->posts[0]->hasChanged('comments'));
        }
    }

    public function testEmptyRelationNotMarkedAsChanged()
    {
        $userModel = model(UserModel::class);
        $userId    = $userModel->insert([
            'name'       => 'No Posts User',
            'email'      => 'noposts@example.com',
            'country_id' => 1,
        ]);

        /** @var User|null $user */
        $user = model(UserModel::class)->with('posts')->find($userId);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEmpty($user->posts);

        $this->assertFalse($user->hasChanged());
        $this->assertFalse($user->hasChanged('posts'));
    }

    public function testRelationLoadedThenEntityReloadedTracksCorrectly()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->with('posts')->find(1);

        $user->name = 'Modified';

        $this->assertTrue($user->hasChanged('name'));
        $this->assertFalse($user->hasChanged('posts'));
    }

    public function testLeafEntitiesAreOptimized()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->with('posts.comments')->find(1);

        $this->assertFalse($user->hasChanged());

        if (isset($user->posts) && count($user->posts) > 0) {
            $this->assertFalse($user->posts[0]->hasChanged());

            if (isset($user->posts[0]->comments) && count($user->posts[0]->comments) > 0) {
                // Leaf entities (comments with no relations) should also not be marked as changed
                $this->assertFalse($user->posts[0]->comments[0]->hasChanged());
            }
        }
    }

    public function testFormEditScenario()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->with('posts')->find(1);

        $this->assertFalse($user->hasChanged());

        $user->name = 'Updated Name';

        $this->assertTrue($user->hasChanged());
        $this->assertTrue($user->hasChanged('name'));
        $this->assertFalse($user->hasChanged('posts'));
        $this->assertFalse($user->hasChanged('email'));
        $this->assertFalse($user->hasChanged('country_id'));
    }

    public function testBulkLoadAndSelectiveUpdate()
    {
        /** @var list<User> $users */
        $users = model(UserModel::class)->with('posts')->findAll();

        $userToUpdate = null;

        foreach ($users as $user) {
            if ($user->id === '1') {
                $userToUpdate = $user;
                break;
            }
        }

        $this->assertInstanceOf(User::class, $userToUpdate);
        $this->assertFalse($userToUpdate->hasChanged());

        // Update only this user
        $userToUpdate->name = 'Selectively Updated';

        $this->assertTrue($userToUpdate->hasChanged());
        $this->assertTrue($userToUpdate->hasChanged('name'));
        $this->assertFalse($userToUpdate->hasChanged('posts'));

        // Other users should not be affected
        foreach ($users as $user) {
            if ($user->id !== '1') {
                $this->assertFalse($user->hasChanged());
            }
        }
    }
}
