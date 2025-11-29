<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\I18n\Time;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;
use Tests\Support\Database\Seeds\SeedTests;
use Tests\Support\Entities\User;
use Tests\Support\Models\UserModel;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class HasEntityWritesTest extends TestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace;
    protected $seed = SeedTests::class;

    public function testSaveNewEntity()
    {
        $user             = new User();
        $user->name       = 'New User';
        $user->email      = 'newuser@example.com';
        $user->country_id = 1;

        // Save using Active Record
        $result = $user->save();

        $this->assertTrue($result);
        $this->assertNotEmpty($user->id, 'ID should be set after save');

        $found = model(UserModel::class)->find($user->id);
        $this->assertInstanceOf(User::class, $found);
        $this->assertSame('New User', $found->name);
        $this->assertSame('newuser@example.com', $found->email);
    }

    public function testSaveExistingEntity()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);
        $this->assertInstanceOf(User::class, $user);

        $originalName = $user->name;

        $user->name = 'Updated Name';
        $result     = $user->save();

        $this->assertTrue($result);

        /** @var User|null $updated */
        $updated = model(UserModel::class)->find(1);
        $this->assertSame('Updated Name', $updated->name);
        $this->assertNotSame($originalName, $updated->name);
    }

    public function testSaveUpdatesTimestamps()
    {
        $user             = new User();
        $user->name       = 'Timestamp Test';
        $user->email      = 'timestamp@example.com';
        $user->country_id = 1;

        $user->save();

        // Check if created_at and updated_at are set (if model uses timestamps)
        $model = model(UserModel::class);
        if (get_model_property($model, 'useTimestamps')) {
            $createdField = get_model_property($model, 'createdField');
            $updatedField = get_model_property($model, 'updatedField');

            $this->assertNotEmpty($user->{$createdField});
            $this->assertNotEmpty($user->{$updatedField});

            // Verify type based on casts
            $casts = get_model_property($model, 'casts');
            if (isset($casts[$createdField]) && in_array($casts[$createdField], ['datetime', 'timestamp'], true)) {
                $this->assertInstanceOf(Time::class, $user->{$createdField});
            }
        }
    }

    public function testSaveUpdatesTimestampOnUpdate()
    {
        $user             = new User();
        $user->name       = 'Update Test';
        $user->email      = 'update@example.com';
        $user->country_id = 1;
        $user->save();

        $model = model(UserModel::class);
        if (! get_model_property($model, 'useTimestamps')) {
            $this->markTestSkipped('Model does not use timestamps');
        }

        $originalUpdated = $user->updated_at;

        // Ensure timestamp difference
        Time::setTestNow(Time::now()->addSeconds(10));

        $user->name = 'Updated Name';
        $user->save();

        $this->assertNotSame($originalUpdated, $user->updated_at);
    }

    public function testTimestampFormatBasedOnDateFormat()
    {
        $user             = new User();
        $user->name       = 'Format Test';
        $user->email      = 'format@example.com';
        $user->country_id = 1;
        $user->save();

        $model        = model(UserModel::class);
        $createdField = get_model_property($model, 'createdField');

        $this->assertInstanceOf(Time::class, $user->{$createdField});
    }

    public function testSaveSyncsOriginalState()
    {
        $user             = new User();
        $user->name       = 'Sync Test';
        $user->email      = 'sync@example.com';
        $user->country_id = 1;
        $user->save();

        $this->assertFalse($user->hasChanged());

        $user->name = 'Modified Again';
        $this->assertTrue($user->hasChanged());
    }

    public function testDeleteEntity()
    {
        $user             = new User();
        $user->name       = 'To Be Deleted';
        $user->email      = 'delete@example.com';
        $user->country_id = 1;
        $user->save();

        $userId = $user->id;
        $this->assertNotEmpty($userId);

        $result = $user->delete();
        $this->assertTrue($result);

        $found = model(UserModel::class)->find($userId);
        $this->assertNull($found);
    }

    public function testDeleteWithSoftDeletes()
    {
        $user             = new User();
        $user->name       = 'Soft Delete Test';
        $user->email      = 'softdelete@example.com';
        $user->country_id = 1;
        $user->save();

        $userId = $user->id;
        $this->assertGreaterThan(0, $userId);

        $result = $user->delete();
        $this->assertTrue($result);
    }

    public function testDeleteRequiresId()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot delete entity without primary key value');

        $user       = new User();
        $user->name = 'No ID';
        $user->delete();
    }

    public function testFindModelClassCaching()
    {
        $user1 = new User();
        $user2 = new User();

        $class1 = $user1->findModelClass();
        $class2 = $user2->findModelClass();

        $this->assertSame(UserModel::class, $class1);
        $this->assertSame($class1, $class2);
    }

    public function testCreateUpdateDelete()
    {
        $user             = new User();
        $user->name       = 'Full Lifecycle Test';
        $user->email      = 'lifecycle@example.com';
        $user->country_id = 1;
        $this->assertTrue($user->save());
        $this->assertNotEmpty($user->id);

        $userId = $user->id;

        $user->name = 'Updated Lifecycle';
        $this->assertTrue($user->save());

        /** @var User|null $found */
        $found = model(UserModel::class)->find($userId);
        $this->assertSame('Updated Lifecycle', $found->name);

        $this->assertTrue($user->delete());

        $notFound = model(UserModel::class)->find($userId);
        $this->assertNull($notFound);
    }
}
