<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\DatabaseTestTrait;
use Michalsn\CodeIgniterRelations\Exceptions\RelationException;
use stdClass;
use Tests\Support\Database\Seeds\SeedTests;
use Tests\Support\Entities\Country;
use Tests\Support\Entities\Post;
use Tests\Support\Entities\User;
use Tests\Support\Models\CountryModel;
use Tests\Support\Models\PostModel;
use Tests\Support\Models\UserModel;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class BelongsToTest extends TestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace;
    protected $seed = SeedTests::class;

    public function testEagerLoadBelongsToWithFind()
    {
        $user = model(UserModel::class)->find(1);
        $this->assertInstanceOf(User::class, $user);

        $isset = isset($user->country);
        $this->assertFalse($isset);

        $user = model(UserModel::class)->with('country')->find(1);
        $this->assertInstanceOf(User::class, $user);

        $isset = isset($user->country);
        $this->assertTrue($isset);

        $this->assertSame('1', $user->country_id);
        $this->assertSame('1', $user->country->id);
    }

    public function testEagerLoadBelongsToWithFindAll()
    {
        $users = model(UserModel::class)->findAll();
        $this->assertInstanceOf(User::class, $users[0]);

        $isset = isset($users[0]->country);
        $this->assertFalse($isset);

        $users = model(UserModel::class)->with('country')->findAll();
        $this->assertInstanceOf(User::class, $users[0]);

        $isset = isset($users[0]->country);
        $this->assertTrue($isset);

        $this->assertSame($users[0]->country_id, $users[0]->country->id);
    }

    public function testBelongsToDoesNotLoadWithoutWith()
    {
        $user = model(UserModel::class)->find(1);

        $this->assertInstanceOf(User::class, $user);

        $isset = isset($user->country);
        $this->assertFalse($isset);
    }

    public function testBelongsToWithCallback()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)
            ->with('country', static function ($model) {
                $model->where('id >', 0); // Always true, just testing callback works
            })
            ->find(1);

        $this->assertInstanceOf(Country::class, $user->country);
    }

    public function testEagerLoadBelongsToWithModelAsArray()
    {
        $users = model(UserModel::class)
            ->asArray()
            ->with('country', static function ($model) {
                $model->asArray();
            })
            ->findAll();

        $this->assertIsArray($users[0]);
        $this->assertArrayHasKey('id', $users[0]);
        $this->assertArrayHasKey('name', $users[0]);
        $this->assertArrayHasKey('country_id', $users[0]);

        $this->assertArrayHasKey('country', $users[0]);
        /** @var mixed $country */
        $country = $users[0]['country'];

        if ($country !== null) {
            $this->assertIsArray($country);
            $this->assertArrayHasKey('id', $country);
            $this->assertSame($users[0]['country_id'], $country['id']);
        }
    }

    public function testEagerLoadBelongsToWithModelAsObject()
    {
        $users = model(UserModel::class)
            ->asObject()
            ->with('country', static function ($model) {
                $model->asObject();
            })
            ->findAll();

        $this->assertInstanceOf(stdClass::class, $users[0]);
        $this->assertObjectHasProperty('id', $users[0]);
        $this->assertObjectHasProperty('name', $users[0]);
        $this->assertObjectHasProperty('country_id', $users[0]);

        $this->assertObjectHasProperty('country', $users[0]);

        if ($users[0]->country !== null) {
            $this->assertInstanceOf(stdClass::class, $users[0]->country);
            $this->assertObjectHasProperty('id', $users[0]->country);
            $this->assertSame($users[0]->country_id, $users[0]->country->id);
        }
    }

    public function testLazyLoadBelongsTo()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);
        $this->assertInstanceOf(User::class, $user);

        $country = $user->country;

        $this->assertIsObject($country);
        $this->assertSame('1', $user->country_id);
        $this->assertSame($user->country_id, $country->id);
    }

    public function testSaveUpdatesExistingParent()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('1', $user->country_id);

        $updatedCountry = $user->country()->save([
            'id'   => 1,
            'name' => 'Updated Country Name',
            'code' => 'UC',
        ]);

        $this->assertInstanceOf(Country::class, $updatedCountry);
        $this->assertSame('1', $updatedCountry->id);
        $this->assertSame('Updated Country Name', $updatedCountry->name);

        $this->seeInDatabase('countries', [
            'id'   => 1,
            'name' => 'Updated Country Name',
        ]);

        $this->seeInDatabase('users', [
            'id'         => 1,
            'country_id' => '1',
        ]);
    }

    public function testSaveReturnsEntityOnSuccess()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);

        $country = $user->country()->save([
            'id'   => 2,
            'name' => 'Success Test Country',
            'code' => 'ST',
        ]);

        $this->assertInstanceOf(Country::class, $country);
        $this->assertTrue(isset($country->id));
        $this->assertSame('2', $country->id);

        $this->assertSame(2, $user->country_id);
    }

    public function testSaveReturnsFalseOnValidationFailure()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);

        $country = $user->country()->save([
            'id'   => 1,
            'name' => '', // Invalid if required
            'code' => '',
        ]);

        $this->assertFalse($country);

        $errors = model(CountryModel::class)->errors();
        $this->assertNotEmpty($errors);
    }

    public function testSaveWithoutOwnerKeyCreatesNewParent()
    {
        /** @var User|null $user */
        $user              = model(UserModel::class)->find(1);
        $originalCountryId = $user->country_id;

        // Save without owner key (ID) - should create new country
        $country = $user->country()->save([
            'name' => 'New Country',
            'code' => 'NC',
        ]);

        $this->assertInstanceOf(Country::class, $country);
        $this->assertTrue(isset($country->id));
        $this->assertSame('New Country', $country->name);
        $this->assertSame('NC', $country->code);

        $this->assertSame((int) $country->id, $user->country_id);
        $this->assertNotSame($originalCountryId, $user->country_id);

        $this->seeInDatabase('users', [
            'id'         => $user->id,
            'country_id' => $country->id,
        ]);
    }

    public function testSaveThrowsExceptionWithoutParentContext()
    {
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Cannot call save() without parent context');

        $userModel = model(UserModel::class);
        $relation  = $userModel->country();

        $relation->save(['id' => 1, 'name' => 'Test']);
    }

    public function testSaveWithArrayData()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);

        $countryData = [
            'id'   => 1,
            'name' => 'Array Save Country',
            'code' => 'AS',
        ];

        $country = $user->country()->save($countryData);

        $this->assertInstanceOf(Country::class, $country);
        $this->assertSame('Array Save Country', $country->name);
    }

    public function testSaveWithEntityData()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);

        $country       = $user->country;
        $country->name = 'Entity Save Country';

        $savedCountry = $user->country()->save($country);

        $this->assertInstanceOf(Country::class, $savedCountry);
        $this->assertSame('Entity Save Country', $savedCountry->name);

        $this->seeInDatabase('countries', [
            'id'   => 1,
            'name' => 'Entity Save Country',
        ]);
    }

    public function testSaveManyThrowsException()
    {
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('The saveMany() method is not supported for BelongsTo relations');

        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);

        $user->country()->saveMany([
            ['id' => 1, 'name' => 'Country 1', 'code' => 'C1'],
            ['id' => 2, 'name' => 'Country 2', 'code' => 'C2'],
        ]);
    }

    public function testAssociateWithEntity()
    {
        /** @var Country|null $country */
        $country = model(CountryModel::class)->find(2);
        $this->assertInstanceOf(Country::class, $country);

        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);
        $this->assertInstanceOf(Post::class, $post);

        /** @var User|null $user */
        $user = model(UserModel::class)->find(2);
        $this->assertInstanceOf(User::class, $user);

        $result = $user->country()->associate($country->id);

        $this->assertTrue($result);

        // Verify foreign key updated
        $this->seeInDatabase('users', [
            'id'         => 2,
            'country_id' => '2',
        ]);
    }

    public function testAssociateWithId()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(2);

        $result = $user->country()->associate(2);

        $this->assertTrue($result);

        /** @var User|null $updatedUser */
        $updatedUser = model(UserModel::class)->find(2);
        $this->assertSame('2', $updatedUser->country_id);
    }

    public function testAssociateUpdatesChildForeignKey()
    {
        /** @var User|null $user */
        $user              = model(UserModel::class)->find(1);
        $originalCountryId = $user->country_id;
        $this->assertSame('1', $originalCountryId);

        $result = $user->country()->associate(2);
        $this->assertTrue($result);

        /** @var User|null $updatedUser */
        $updatedUser = model(UserModel::class)->find(1);
        $this->assertSame('2', $updatedUser->country_id);
        $this->assertNotSame($originalCountryId, $updatedUser->country_id);
    }

    public function testAssociateThrowsExceptionWithoutParentContext()
    {
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Cannot call associate() without parent context');

        $userModel = model(UserModel::class);
        $relation  = $userModel->country();

        $relation->associate(1);
    }

    public function testDissociateSetsKeyToNull()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);
        $this->assertNotNull($user->country_id);
        $this->assertSame('1', $user->country_id);

        $this->assertInstanceOf(Country::class, $user->country);

        $result = $user->country()->dissociate();

        $this->assertTrue($result);
        $this->assertNull($user->country_id);
        $this->assertNull($user->country);

        /** @var User|null $updatedUser */
        $updatedUser = model(UserModel::class)->find(1);
        $this->assertNull($updatedUser->country_id);

        $this->seeInDatabase('users', [
            'id'         => 1,
            'country_id' => null,
        ]);
    }

    public function testDissociateThrowsExceptionWithoutParentContext()
    {
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Cannot call dissociate() without parent context');

        $userModel = model(UserModel::class);
        $relation  = $userModel->country();

        $relation->dissociate();
    }

    public function testLazyLoadThenAssociate()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(2);
        $this->assertInstanceOf(User::class, $user);

        $this->assertInstanceOf(Country::class, $user->country);

        $result = $user->country()->associate(1);

        $this->assertSame(1, $user->country_id);
        $this->assertSame('2', $user->country->id);

        $user->refresh();
        $this->assertSame('1', $user->country->id);

        $this->assertTrue($result);
        $this->seeInDatabase('users', [
            'id'         => 2,
            'country_id' => '1',
        ]);
    }

    public function testDissociateThenAssociate()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);
        $this->assertSame('1', $user->country_id);

        $result1 = $user->country()->dissociate();
        $this->assertTrue($result1);

        /** @var User|null $updatedUser */
        $updatedUser = model(UserModel::class)->find(1);
        $this->assertNull($updatedUser->country_id);

        $result2 = $updatedUser->country()->associate(2);
        $this->assertTrue($result2);

        /** @var User|null $finalUser */
        $finalUser = model(UserModel::class)->find(1);
        $this->assertSame('2', $finalUser->country_id);
    }
}
