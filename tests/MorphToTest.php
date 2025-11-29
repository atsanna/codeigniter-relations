<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\DatabaseTestTrait;
use Michalsn\CodeIgniterRelations\Exceptions\RelationException;
use stdClass;
use Tests\Support\Database\Seeds\SeedTests;
use Tests\Support\Entities\Image;
use Tests\Support\Entities\Post;
use Tests\Support\Entities\User;
use Tests\Support\Models\ImageModel;
use Tests\Support\Models\PostModel;
use Tests\Support\Models\UserModel;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class MorphToTest extends TestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace;
    protected $seed = SeedTests::class;

    public function testEagerLoadMorphToWithFind()
    {
        $image = model(ImageModel::class)->find(1);
        $this->assertInstanceOf(Image::class, $image);

        $isset = isset($image->imageable);
        $this->assertFalse($isset);

        $image = model(ImageModel::class)->with('imageable')->find(1);
        $this->assertInstanceOf(Image::class, $image);

        $isset = isset($image->imageable);
        $this->assertTrue($isset);

        if ($image->imageable_type === PostModel::class) {
            $this->assertInstanceOf(Post::class, $image->imageable);
            $this->assertSame($image->imageable_id, $image->imageable->id);
        } elseif ($image->imageable_type === UserModel::class) {
            $this->assertInstanceOf(User::class, $image->imageable);
            $this->assertSame($image->imageable_id, $image->imageable->id);
        }
    }

    public function testEagerLoadMorphToWithFindAll()
    {
        $images = model(ImageModel::class)->findAll();
        $this->assertInstanceOf(Image::class, $images[0]);

        $isset = isset($images[0]->imageable);
        $this->assertFalse($isset);

        $images = model(ImageModel::class)->with('imageable')->findAll();
        $this->assertInstanceOf(Image::class, $images[0]);

        $isset = isset($images[0]->imageable);
        $this->assertTrue($isset);
    }

    public function testEagerLoadMorphToLoadsDifferentTypes()
    {
        $images = model(ImageModel::class)->with('imageable')->findAll();

        $foundUser = false;
        $foundPost = false;

        foreach ($images as $image) {
            $this->assertTrue(isset($image->imageable));

            if ($image->imageable instanceof User) {
                $foundUser = true;

                $this->assertSame($image->imageable_id, $image->imageable->id);
            }

            if ($image->imageable instanceof Post) {
                $foundPost = true;

                $this->assertSame($image->imageable_id, $image->imageable->id);
            }
        }

        $this->assertTrue($foundUser || $foundPost);
    }

    public function testEagerLoadMorphToWithModelAsArray()
    {
        $images = model(ImageModel::class)
            ->asArray()
            ->with('imageable', static function ($model) {
                $model->asArray();
            })
            ->findAll();

        $this->assertIsArray($images[0]);
        $this->assertArrayHasKey('id', $images[0]);
        $this->assertArrayHasKey('imageable_type', $images[0]);
        $this->assertArrayHasKey('imageable_id', $images[0]);

        $this->assertArrayHasKey('imageable', $images[0]);

        if ($images[0]['imageable'] !== null) {
            $this->assertIsArray($images[0]['imageable']);
            $this->assertArrayHasKey('id', $images[0]['imageable']);
        }
    }

    public function testEagerLoadMorphToWithModelAsObject()
    {
        $images = model(ImageModel::class)
            ->asObject()
            ->with('imageable', static function ($model) {
                $model->asObject();
            })
            ->findAll();

        $this->assertInstanceOf(stdClass::class, $images[0]);
        $this->assertObjectHasProperty('id', $images[0]);
        $this->assertObjectHasProperty('imageable_type', $images[0]);
        $this->assertObjectHasProperty('imageable_id', $images[0]);

        $this->assertObjectHasProperty('imageable', $images[0]);

        if ($images[0]->imageable !== null) {
            $this->assertInstanceOf(stdClass::class, $images[0]->imageable);
            $this->assertObjectHasProperty('id', $images[0]->imageable);
        }
    }

    public function testEagerLoadMorphToWithCallback()
    {
        $image = model(ImageModel::class)
            ->with('imageable', static function ($model) {})
            ->find(1);

        $this->assertInstanceOf(Image::class, $image);
        $this->assertTrue(isset($image->imageable));
    }

    public function testEagerLoadMorphToReturnsNull()
    {
        $db = db_connect();
        $db->table('images')->insert([
            'imageable_type' => UserModel::class,
            'imageable_id'   => '9999', // Non-existent user ID
            'url'            => 'https://example.com/test.jpg',
            'alt_text'       => 'Test image',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $imageId = $db->insertID();

        $image = model(ImageModel::class)->with('imageable')->find($imageId);

        $this->assertInstanceOf(Image::class, $image);
        $this->assertNull($image->imageable);
    }

    public function testEagerLoadMorphToThrowsExceptionForInvalidModel()
    {
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Cannot load MorphTo relation: model class "Tests\\Support\\Models\\NonExistentModel" does not exist or could not be instantiated');

        $db = db_connect();
        $db->table('images')->insert([
            'imageable_type' => 'Tests\\Support\\Models\\NonExistentModel',
            'imageable_id'   => '999',
            'url'            => 'https://example.com/test.jpg',
            'alt_text'       => 'Test image',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $imageId = $db->insertID();

        model(ImageModel::class)->with('imageable')->find($imageId);
    }

    public function testMorphToDoesNotLoadWithoutWith()
    {
        $image = model(ImageModel::class)->find(1);

        $this->assertInstanceOf(Image::class, $image);

        $isset = isset($image->imageable);
        $this->assertFalse($isset);
    }

    public function testLazyLoadMorphTo()
    {
        $image = model(ImageModel::class)->find(1);
        $this->assertInstanceOf(Image::class, $image);

        $imageable = $image->imageable;

        $this->assertNotNull($imageable);

        if ($imageable instanceof Post) {
            $this->assertSame($image->imageable_id, $imageable->id);
        } elseif ($imageable instanceof User) {
            $this->assertSame($image->imageable_id, $imageable->id);
        }
    }

    public function testSaveThrowsException()
    {
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Cannot save data through morphTo relation. MorphTo is an inverse polymorphic relation and is read-only. To modify related data, save directly through the parent model.');

        /** @var Image|null $image */
        $image = model(ImageModel::class)->find(1);

        $image->imageable()->save([
            'id'    => 1,
            'name'  => 'Updated User',
            'email' => 'updated@example.com',
        ]);
    }

    public function testSaveManyThrowsException()
    {
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Cannot save data through morphTo relation. MorphTo is an inverse polymorphic relation and is read-only. To modify related data, save directly through the parent model.');

        /** @var Image|null $image */
        $image = model(ImageModel::class)->find(1);

        $image->imageable()->saveMany([
            ['id' => 1, 'name' => 'User 1', 'email' => 'user1@example.com'],
            ['id' => 2, 'name' => 'User 2', 'email' => 'user2@example.com'],
        ]);
    }

    public function testAssociateWithPost()
    {
        /** @var Image|null $image */
        $image = model(ImageModel::class)->find(1);
        $this->assertInstanceOf(Image::class, $image);

        $post = model(PostModel::class)->find(2);
        $this->assertInstanceOf(Post::class, $post);

        $result = $image->imageable()->associate($post);

        $this->assertTrue($result);

        /** @var Image|null $updatedImage */
        $updatedImage = model(ImageModel::class)->find($image->id);
        $this->assertSame(PostModel::class, $updatedImage->imageable_type);
        $this->assertSame($post->id, $updatedImage->imageable_id);

        $this->assertSame(PostModel::class, $image->imageable_type);
        $this->assertSame($post->id, $image->imageable_id);
    }

    public function testAssociateWithUser()
    {
        /** @var Image|null $image */
        $image = model(ImageModel::class)->find(1);
        $this->assertInstanceOf(Image::class, $image);

        $user = model(UserModel::class)->find(2);
        $this->assertInstanceOf(User::class, $user);

        $result = $image->imageable()->associate($user);

        $this->assertTrue($result);

        /** @var Image|null $updatedImage */
        $updatedImage = model(ImageModel::class)->find($image->id);
        $this->assertSame(UserModel::class, $updatedImage->imageable_type);
        $this->assertSame($user->id, $updatedImage->imageable_id);

        $this->assertSame(UserModel::class, $image->imageable_type);
        $this->assertSame($user->id, $image->imageable_id);
    }

    public function testAssociateSwitchesBetweenDifferentTypes()
    {
        /** @var Image|null $image */
        $image = model(ImageModel::class)->find(1);
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);

        $result = $image->imageable()->associate($post);
        $this->assertTrue($result);

        /** @var Image|null $updatedImage */
        $updatedImage = model(ImageModel::class)->find($image->id);
        $this->assertSame(PostModel::class, $updatedImage->imageable_type);
        $this->assertSame($post->id, $updatedImage->imageable_id);

        $this->assertSame(PostModel::class, $image->imageable_type);
        $this->assertSame($post->id, $image->imageable_id);

        $result = $image->imageable()->associate($user);
        $this->assertTrue($result);

        /** @var Image|null $updatedImage */
        $updatedImage = model(ImageModel::class)->find($image->id);
        $this->assertSame(UserModel::class, $updatedImage->imageable_type);
        $this->assertSame($user->id, $updatedImage->imageable_id);

        $this->assertSame(UserModel::class, $image->imageable_type);
        $this->assertSame($user->id, $image->imageable_id);
    }

    public function testDissociateSetsBothFieldsToNull()
    {
        /** @var Image|null $image */
        $image = model(ImageModel::class)->find(1);
        $this->assertInstanceOf(Image::class, $image);
        $this->assertNotNull($image->imageable_type);
        $this->assertNotNull($image->imageable_id);

        $result = $image->imageable()->dissociate();

        $this->assertTrue($result);

        /** @var Image|null $updatedImage */
        $updatedImage = model(ImageModel::class)->find($image->id);
        $this->assertNull($updatedImage->imageable_type);
        $this->assertNull($updatedImage->imageable_id);

        $this->assertNull($image->imageable_type);
        $this->assertNull($image->imageable_id);

        $this->seeInDatabase('images', [
            'id'             => $image->id,
            'imageable_type' => null,
            'imageable_id'   => null,
        ]);
    }

    public function testDissociateThenAssociate()
    {
        /** @var Image|null $image */
        $image = model(ImageModel::class)->find(1);
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);

        $result1 = $image->imageable()->dissociate();
        $this->assertTrue($result1);

        /** @var Image|null $updatedImage */
        $updatedImage = model(ImageModel::class)->find($image->id);
        $this->assertNull($updatedImage->imageable_type);
        $this->assertNull($updatedImage->imageable_id);

        $this->assertNull($image->imageable_type);
        $this->assertNull($image->imageable_id);

        $result2 = $image->imageable()->associate($post);
        $this->assertTrue($result2);

        /** @var Image|null $finalImage */
        $finalImage = model(ImageModel::class)->find($image->id);
        $this->assertSame(PostModel::class, $finalImage->imageable_type);
        $this->assertSame($post->id, $finalImage->imageable_id);

        $this->assertSame(PostModel::class, $image->imageable_type);
        $this->assertSame($post->id, $image->imageable_id);
    }

    public function testMorphToRelationStructure()
    {
        $imageModel = model(ImageModel::class);
        $relation   = $imageModel->imageable();

        $this->assertTrue($relation->getType()->isSingular());
    }

    public function testMorphToWithSpecificType()
    {
        /** @var list<Image> $images */
        $images = model(ImageModel::class)
            ->where('imageable_type', PostModel::class)
            ->with('imageable')
            ->findAll();

        foreach ($images as $image) {
            if ($image->imageable !== null) {
                $this->assertInstanceOf(Post::class, $image->imageable);
            }
        }
    }

    public function testMorphToWithSpecificUser()
    {
        /** @var list<Image> $images */
        $images = model(ImageModel::class)
            ->where('imageable_type', UserModel::class)
            ->with('imageable')
            ->findAll();

        foreach ($images as $image) {
            if ($image->imageable !== null) {
                $this->assertInstanceOf(User::class, $image->imageable);
            }
        }
    }

    public function testLazyLoadThenAssociate()
    {
        /** @var Image|null $image */
        $image = model(ImageModel::class)->find(1);
        $this->assertInstanceOf(Image::class, $image);

        $imageable = $image->imageable;
        $this->assertNotNull($imageable);

        /** @var Post|null $post */
        $post   = model(PostModel::class)->find(2);
        $result = $image->imageable()->associate($post);

        $this->assertTrue($result);

        $this->seeInDatabase('images', [
            'id'             => $image->id,
            'imageable_type' => PostModel::class,
            'imageable_id'   => $post->id,
        ]);

        $this->assertSame(PostModel::class, $image->imageable_type);
        $this->assertSame($post->id, $image->imageable_id);
    }
}
