<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\Seeds\SeedTests;
use Tests\Support\Entities\Country;
use Tests\Support\Entities\Profile;
use Tests\Support\Entities\StrictImage;
use Tests\Support\Entities\StrictStudent;
use Tests\Support\Entities\StrictUser;
use Tests\Support\Entities\User;
use Tests\Support\Models\CountryModel;
use Tests\Support\Models\StrictImageModel;
use Tests\Support\Models\StrictStudentModel;
use Tests\Support\Models\StrictUserModel;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class StrictEntityRelationsTest extends TestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace;
    protected $seed = SeedTests::class;

    public function testEagerLoadsHasOneRelationOnStrictEntity(): void
    {
        $user = model(StrictUserModel::class)->with('profile')->find(1);

        $this->assertInstanceOf(StrictUser::class, $user);
        $this->assertInstanceOf(Profile::class, $user->profile);
        $this->assertSame('1', $user->profile->user_id);
    }

    public function testEagerLoadsHasManyRelationOnStrictEntity(): void
    {
        $user = model(StrictUserModel::class)->with('posts')->find(1);

        $this->assertInstanceOf(StrictUser::class, $user);
        $this->assertCount(2, $user->posts);
        $this->assertSame('Getting Started with CodeIgniter 4', $user->posts[0]->title);
    }

    public function testLazyLoadsHasOneRelationOnStrictEntity(): void
    {
        $user = model(StrictUserModel::class)->find(1);

        $this->assertInstanceOf(StrictUser::class, $user);
        $this->assertInstanceOf(Profile::class, $user->profile);
        $this->assertSame('1', $user->profile->user_id);
    }

    public function testLazyLoadsHasManyRelationOnStrictEntity(): void
    {
        $user = model(StrictUserModel::class)->find(1);

        $this->assertInstanceOf(StrictUser::class, $user);
        $this->assertCount(2, $user->posts);
        $this->assertSame('Getting Started with CodeIgniter 4', $user->posts[0]->title);
    }

    public function testAssociateUpdatesLoadedBelongsToRelationOnStrictEntity(): void
    {
        $user    = model(StrictUserModel::class)->find(1);
        $country = model(CountryModel::class)->find(2);

        $this->assertInstanceOf(StrictUser::class, $user);
        $this->assertInstanceOf(Country::class, $country);

        $result = $user->country()->associate($country);

        $this->assertTrue($result);
        $this->assertSame('2', (string) $user->country_id);
        $this->assertInstanceOf(Country::class, $user->country);
        $this->assertSame('2', $user->country->id);
    }

    public function testEagerLoadsMorphToRelationOnStrictEntity(): void
    {
        $image = model(StrictImageModel::class)->with('imageable')->find(1);

        $this->assertInstanceOf(StrictImage::class, $image);
        $this->assertInstanceOf(User::class, $image->imageable);
        $this->assertSame('1', $image->imageable->id);
    }

    public function testLazyLoadsMorphToRelationOnStrictEntity(): void
    {
        $image = model(StrictImageModel::class)->find(1);

        $this->assertInstanceOf(StrictImage::class, $image);
        $this->assertInstanceOf(User::class, $image->imageable);
        $this->assertSame('1', $image->imageable->id);
    }

    public function testEagerLoadsBelongsToManyRelationOnStrictEntity(): void
    {
        $student = model(StrictStudentModel::class)->with('courses')->find(1);

        $this->assertInstanceOf(StrictStudent::class, $student);
        $this->assertCount(3, $student->courses);
        $this->assertSame('Introduction to Programming', $student->courses[0]->title);
    }

    public function testLazyLoadsBelongsToManyRelationOnStrictEntity(): void
    {
        $student = model(StrictStudentModel::class)->find(1);

        $this->assertInstanceOf(StrictStudent::class, $student);
        $this->assertCount(3, $student->courses);
        $this->assertSame('Introduction to Programming', $student->courses[0]->title);
    }
}
