<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\I18n\Time;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\Seeds\SeedTests;
use Tests\Support\Entities\User;
use Tests\Support\Models\ImageModel;
use Tests\Support\Models\UserModel;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class MorphOneOfManyTest extends TestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace;
    protected $seed = SeedTests::class;

    public function testLatestOfManyEagerLoad()
    {
        $userId = model(UserModel::class)->insert([
            'name'  => 'Multi Image User',
            'email' => 'multiimage@test.com',
        ]);

        $db = db_connect();

        $db->table('images')->insert([
            'imageable_type' => 'Tests\\Support\\Models\\UserModel',
            'imageable_id'   => $userId,
            'url'            => 'https://example.com/old.jpg',
            'alt_text'       => 'Old image',
            'created_at'     => date('Y-m-d H:i:s', strtotime('-3 days')),
            'updated_at'     => date('Y-m-d H:i:s', strtotime('-3 days')),
        ]);

        $db->table('images')->insert([
            'imageable_type' => 'Tests\\Support\\Models\\UserModel',
            'imageable_id'   => $userId,
            'url'            => 'https://example.com/newest.jpg',
            'alt_text'       => 'Newest image',
            'created_at'     => date('Y-m-d H:i:s', strtotime('-1 day')),
            'updated_at'     => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);
        $newestImageId = $db->insertID();

        $user = model(UserModel::class)
            ->with('latestAvatar')
            ->find($userId);

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue(isset($user->latestAvatar));
        $this->assertSame((string) $newestImageId, $user->latestAvatar->id);
        $this->assertSame('https://example.com/newest.jpg', $user->latestAvatar->url);
    }

    public function testOldestOfManyEagerLoad()
    {
        $userId = model(UserModel::class)->insert([
            'name'  => 'Multi Image User 2',
            'email' => 'multiimage2@test.com',
        ]);

        $db = db_connect();

        $db->table('images')->insert([
            'imageable_type' => 'Tests\\Support\\Models\\UserModel',
            'imageable_id'   => $userId,
            'url'            => 'https://example.com/oldest.jpg',
            'alt_text'       => 'Oldest image',
            'created_at'     => date('Y-m-d H:i:s', strtotime('-3 days')),
            'updated_at'     => date('Y-m-d H:i:s', strtotime('-3 days')),
        ]);
        $oldestImageId = $db->insertID();

        $db->table('images')->insert([
            'imageable_type' => 'Tests\\Support\\Models\\UserModel',
            'imageable_id'   => $userId,
            'url'            => 'https://example.com/newer.jpg',
            'alt_text'       => 'Newer image',
            'created_at'     => date('Y-m-d H:i:s', strtotime('-1 day')),
            'updated_at'     => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        $user = model(UserModel::class)
            ->with('oldestAvatar')
            ->find($userId);

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue(isset($user->oldestAvatar));
        $this->assertSame((string) $oldestImageId, $user->oldestAvatar->id);
        $this->assertSame('https://example.com/oldest.jpg', $user->oldestAvatar->url);
    }

    public function testLatestOfManyWithMultipleUsers()
    {
        $db = db_connect();

        $user1Id = model(UserModel::class)->insert([
            'name'  => 'User One',
            'email' => 'userone@test.com',
        ]);

        $user2Id = model(UserModel::class)->insert([
            'name'  => 'User Two',
            'email' => 'usertwo@test.com',
        ]);

        // User 1 images
        $db->table('images')->insert([
            'imageable_type' => 'Tests\\Support\\Models\\UserModel',
            'imageable_id'   => $user1Id,
            'url'            => 'https://example.com/user1-old.jpg',
            'alt_text'       => 'User 1 old',
            'created_at'     => date('Y-m-d H:i:s', strtotime('-2 days')),
            'updated_at'     => date('Y-m-d H:i:s', strtotime('-2 days')),
        ]);

        $db->table('images')->insert([
            'imageable_type' => 'Tests\\Support\\Models\\UserModel',
            'imageable_id'   => $user1Id,
            'url'            => 'https://example.com/user1-new.jpg',
            'alt_text'       => 'User 1 new',
            'created_at'     => date('Y-m-d H:i:s', strtotime('-1 day')),
            'updated_at'     => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);
        $user1NewestId = (string) $db->insertID();

        // User 2 images
        $db->table('images')->insert([
            'imageable_type' => 'Tests\\Support\\Models\\UserModel',
            'imageable_id'   => $user2Id,
            'url'            => 'https://example.com/user2-old.jpg',
            'alt_text'       => 'User 2 old',
            'created_at'     => date('Y-m-d H:i:s', strtotime('-3 days')),
            'updated_at'     => date('Y-m-d H:i:s', strtotime('-3 days')),
        ]);

        $db->table('images')->insert([
            'imageable_type' => 'Tests\\Support\\Models\\UserModel',
            'imageable_id'   => $user2Id,
            'url'            => 'https://example.com/user2-new.jpg',
            'alt_text'       => 'User 2 new',
            'created_at'     => date('Y-m-d H:i:s', strtotime('-1 day')),
            'updated_at'     => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);
        $user2NewestId = (string) $db->insertID();

        /** @var list<User> $users */
        $users = model(UserModel::class)
            ->with('latestAvatar')
            ->whereIn('id', [$user1Id, $user2Id])
            ->findAll();

        $this->assertCount(2, $users);

        foreach ($users as $user) {
            if ($user->id === $user1Id) {
                $this->assertTrue(isset($user->latestAvatar));
                $this->assertSame($user1NewestId, $user->latestAvatar->id);
            } elseif ($user->id === $user2Id) {
                $this->assertTrue(isset($user->latestAvatar));
                $this->assertSame($user2NewestId, $user->latestAvatar->id);
            }
        }
    }

    public function testLatestOfManyLazyLoad()
    {
        $userId = model(UserModel::class)->insert([
            'name'  => 'Lazy Load User',
            'email' => 'lazyload@test.com',
        ]);

        $db = db_connect();

        $db->table('images')->insert([
            'imageable_type' => 'Tests\\Support\\Models\\UserModel',
            'imageable_id'   => $userId,
            'url'            => 'https://example.com/lazy-old.jpg',
            'alt_text'       => 'Lazy old',
            'created_at'     => date('Y-m-d H:i:s', strtotime('-2 days')),
            'updated_at'     => date('Y-m-d H:i:s', strtotime('-2 days')),
        ]);

        $db->table('images')->insert([
            'imageable_type' => 'Tests\\Support\\Models\\UserModel',
            'imageable_id'   => $userId,
            'url'            => 'https://example.com/lazy-new.jpg',
            'alt_text'       => 'Lazy new',
            'created_at'     => date('Y-m-d H:i:s', strtotime('-1 day')),
            'updated_at'     => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        $user = model(UserModel::class)
            ->asObject(User::class)
            ->find($userId);

        $this->assertInstanceOf(User::class, $user);

        $latestAvatar = $user->latestAvatar;

        $this->assertNotNull($latestAvatar);
        $this->assertSame('https://example.com/lazy-new.jpg', $latestAvatar->url);
    }

    public function testOldestOfManyLazyLoad()
    {
        $userId = model(UserModel::class)->insert([
            'name'  => 'Lazy Load User 2',
            'email' => 'lazyload2@test.com',
        ]);

        $db = db_connect();

        $db->table('images')->insert([
            'imageable_type' => 'Tests\\Support\\Models\\UserModel',
            'imageable_id'   => $userId,
            'url'            => 'https://example.com/lazy-oldest.jpg',
            'alt_text'       => 'Lazy oldest',
            'created_at'     => date('Y-m-d H:i:s', strtotime('-3 days')),
            'updated_at'     => date('Y-m-d H:i:s', strtotime('-3 days')),
        ]);

        $db->table('images')->insert([
            'imageable_type' => 'Tests\\Support\\Models\\UserModel',
            'imageable_id'   => $userId,
            'url'            => 'https://example.com/lazy-newer.jpg',
            'alt_text'       => 'Lazy newer',
            'created_at'     => date('Y-m-d H:i:s', strtotime('-1 day')),
            'updated_at'     => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        $user = model(UserModel::class)
            ->asObject(User::class)
            ->find($userId);

        $this->assertInstanceOf(User::class, $user);

        $oldestAvatar = $user->oldestAvatar;

        $this->assertNotNull($oldestAvatar);
        $this->assertSame('https://example.com/lazy-oldest.jpg', $oldestAvatar->url);
    }

    public function testLatestOfManyWithNoRecords()
    {
        $userId = model(UserModel::class)->insert([
            'name'  => 'No Image User',
            'email' => 'noimage@test.com',
        ]);

        $user = model(UserModel::class)
            ->with('latestAvatar')
            ->find($userId);

        $this->assertInstanceOf(User::class, $user);
        $this->assertFalse(isset($user->latestAvatar));
    }

    public function testLatestOfManyWithSingleRecord()
    {
        $userId = model(UserModel::class)->insert([
            'name'  => 'Single Image User',
            'email' => 'singleimage@test.com',
        ]);

        $imageId = model(ImageModel::class)->insert([
            'imageable_type' => 'Tests\\Support\\Models\\UserModel',
            'imageable_id'   => $userId,
            'url'            => 'https://example.com/single.jpg',
            'alt_text'       => 'Single image',
        ]);

        $user = model(UserModel::class)
            ->with('latestAvatar')
            ->find($userId);

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue(isset($user->latestAvatar));
        $this->assertSame($imageId, (int) $user->latestAvatar->id);
    }

    public function testOfManyDoesNotAffectRegularMorphOne()
    {
        $user = model(UserModel::class)
            ->with('avatar')
            ->find(1);

        $this->assertInstanceOf(User::class, $user);
    }

    public function testOfManyOnlyGetsRecordsForCorrectMorphType()
    {
        $db = db_connect();

        $userId = model(UserModel::class)->insert([
            'name'  => 'Type Test User',
            'email' => 'typetest@test.com',
        ]);

        $db->table('images')->insert([
            'imageable_type' => 'Tests\\Support\\Models\\UserModel',
            'imageable_id'   => $userId,
            'url'            => 'https://example.com/user-image.jpg',
            'alt_text'       => 'User image',
            'created_at'     => Time::now()->subDays(1)->toDateTimeString(),
            'updated_at'     => Time::now()->subDays(1)->toDateTimeString(),
        ]);
        $userImageId = $db->insertID();

        $db->table('images')->insert([
            'imageable_type' => 'Tests\\Support\\Models\\PostModel',
            'imageable_id'   => $userId, // Same ID but different type
            'url'            => 'https://example.com/post-image.jpg',
            'alt_text'       => 'Post image',
            'created_at'     => Time::now()->subDays(2)->toDateTimeString(),
            'updated_at'     => Time::now()->subDays(2)->toDateTimeString(),
        ]);

        // Get user with latest avatar - should only get user image
        $user = model(UserModel::class)
            ->with('latestAvatar')
            ->find($userId);

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue(isset($user->latestAvatar));
        $this->assertSame($userImageId, (int) $user->latestAvatar->id);
        $this->assertSame('https://example.com/user-image.jpg', $user->latestAvatar->url);
    }
}
