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
final class MorphOneTest extends TestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace;
    protected $seed = SeedTests::class;

    public function testEagerLoadMorphOneWithFindOnPost()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);
        $this->assertInstanceOf(Post::class, $post);

        $isset = isset($post->featuredImage);
        $this->assertFalse($isset);

        /** @var Post|null $post */
        $post = model(PostModel::class)->with('featuredImage')->find(1);
        $this->assertInstanceOf(Post::class, $post);

        $isset = isset($post->featuredImage);
        $this->assertTrue($isset);

        if ($post->featuredImage !== null) {
            $this->assertIsObject($post->featuredImage);
            $this->assertSame('Tests\\Support\\Models\\PostModel', $post->featuredImage->imageable_type);
            $this->assertSame('1', $post->featuredImage->imageable_id);
        }
    }

    public function testEagerLoadMorphOneWithFindOnUser()
    {
        $user = model(UserModel::class)->find(1);
        $this->assertInstanceOf(User::class, $user);

        $isset = isset($user->avatar);
        $this->assertFalse($isset);

        $user = model(UserModel::class)->with('avatar')->find(1);
        $this->assertInstanceOf(User::class, $user);

        $isset = isset($user->avatar);
        $this->assertTrue($isset);

        if ($user->avatar !== null) {
            $this->assertIsObject($user->avatar);
            $this->assertSame('Tests\\Support\\Models\\UserModel', $user->avatar->imageable_type);
            $this->assertSame('1', $user->avatar->imageable_id);
        }
    }

    public function testEagerLoadMorphOneWithFindAll()
    {
        $posts = model(PostModel::class)->findAll();
        $this->assertInstanceOf(Post::class, $posts[0]);

        $isset = isset($posts[0]->featuredImage);
        $this->assertFalse($isset);

        $posts = model(PostModel::class)->with('featuredImage')->findAll();
        $this->assertInstanceOf(Post::class, $posts[0]);

        $isset = isset($posts[0]->featuredImage);
        $this->assertTrue($isset);
    }

    public function testEagerLoadMorphOneAsArray()
    {
        $post = model(PostModel::class)
            ->with('featuredImage', static function ($model) {
                $model->asArray();
            })
            ->find(1);

        $this->assertInstanceOf(Post::class, $post);
        $this->assertTrue(isset($post->featuredImage));
        $this->assertNotEmpty($post->featuredImage);
    }

    public function testEagerLoadMorphOneWithModelAsArray()
    {
        $posts = model(PostModel::class)
            ->asArray()
            ->with('featuredImage', static function ($model) {
                $model->asArray();
            })
            ->findAll();

        $this->assertIsArray($posts[0]);
        $this->assertArrayHasKey('id', $posts[0]);
        $this->assertArrayHasKey('title', $posts[0]);

        $this->assertArrayHasKey('featuredImage', $posts[0]);

        if ($posts[0]['featuredImage'] !== null) {
            $this->assertIsArray($posts[0]['featuredImage']);
            $this->assertArrayHasKey('imageable_type', $posts[0]['featuredImage']);
            $this->assertArrayHasKey('imageable_id', $posts[0]['featuredImage']);
        }
    }

    public function testEagerLoadMorphOneWithModelAsObject()
    {
        $posts = model(PostModel::class)
            ->asObject()
            ->with('featuredImage', static function ($model) {
                $model->asObject();
            })
            ->findAll();

        $this->assertInstanceOf(stdClass::class, $posts[0]);
        $this->assertObjectHasProperty('id', $posts[0]);
        $this->assertObjectHasProperty('title', $posts[0]);

        $this->assertObjectHasProperty('featuredImage', $posts[0]);

        if ($posts[0]->featuredImage !== null) {
            $this->assertInstanceOf(stdClass::class, $posts[0]->featuredImage);
            $this->assertObjectHasProperty('imageable_type', $posts[0]->featuredImage);
            $this->assertObjectHasProperty('imageable_id', $posts[0]->featuredImage);
        }
    }

    public function testMorphOneDoesNotLoadWithoutWith()
    {
        $post = model(PostModel::class)->find(1);

        $this->assertInstanceOf(Post::class, $post);

        $isset = isset($post->featuredImage);
        $this->assertFalse($isset);
    }

    public function testMorphOneWithCallback()
    {
        $post = model(PostModel::class)
            ->with('featuredImage', static function ($model) {
                $model->where('imageable_id >', 0); // Always true, just testing callback works
            })
            ->find(1);

        $this->assertInstanceOf(Post::class, $post);
        $this->assertTrue(isset($post->featuredImage));
    }

    public function testLazyLoadMorphOne()
    {
        $post = model(PostModel::class)->find(1);
        $this->assertInstanceOf(Post::class, $post);

        $image = $post->featuredImage;

        if ($image !== null) {
            $this->assertIsObject($image);
            $this->assertSame('Tests\\Support\\Models\\PostModel', $image->imageable_type);
            $this->assertSame('1', $image->imageable_id);
        }
    }

    public function testSaveUpdatesExistingImage()
    {
        $post = model(PostModel::class)->find(1);
        $this->assertInstanceOf(Post::class, $post);

        $currentImage = $post->featuredImage;

        $updatedImage = $post->featuredImage()->save([
            'id'       => $currentImage->id,
            'url'      => 'https://example.com/updated-via-save.jpg',
            'alt_text' => 'Updated via save',
        ]);

        $this->assertNotFalse($updatedImage);

        $this->seeInDatabase('images', [
            'id'  => $currentImage->id,
            'url' => 'https://example.com/updated-via-save.jpg',
        ]);
    }

    public function testSaveCreatesNewImageWhenNoneExists()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(2);

        $image = $post->featuredImage()->save([
            'url'      => 'https://example.com/created-via-save.jpg',
            'alt_text' => 'Created via save',
        ]);

        $this->assertNotFalse($image);

        $this->seeInDatabase('images', [
            'imageable_type' => PostModel::class,
            'imageable_id'   => $post->id,
            'url'            => 'https://example.com/created-via-save.jpg',
        ]);
    }

    public function testSaveSetsMorphFieldsWhenCreating()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(2);

        $avatar = $user->avatar()->save([
            'url'      => 'https://example.com/avatar-save.jpg',
            'alt_text' => 'Avatar saved',
        ]);

        $this->assertNotFalse($avatar);

        $this->seeInDatabase('images', [
            'imageable_type' => UserModel::class,
            'imageable_id'   => $user->id,
            'url'            => 'https://example.com/avatar-save.jpg',
        ]);
    }

    public function testSaveReturnsEntityOnSuccess()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(2);

        $image = $post->featuredImage()->save([
            'url'      => 'https://example.com/success-save.jpg',
            'alt_text' => 'Success',
        ]);

        $this->assertNotFalse($image);
    }

    public function testSaveReturnsFalseOnValidationFailure()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(2);

        $image = $post->featuredImage()->save([
            'url'      => '', // Invalid if required
            'alt_text' => '',
        ]);

        $this->assertFalse($image);

        $errors = model(ImageModel::class)->errors();
        $this->assertNotEmpty($errors);
    }

    public function testSaveThrowsExceptionWithoutParentContext()
    {
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Cannot call save() without parent context');

        $postModel = model(PostModel::class);
        $relation  = $postModel->featuredImage();

        $relation->save(['url' => 'test.jpg']);
    }

    public function testSaveWithArrayData()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(2);

        $imageData = [
            'url'      => 'https://example.com/array-save.jpg',
            'alt_text' => 'Array save',
        ];

        $image = $post->featuredImage()->save($imageData);

        $this->assertNotFalse($image);
    }

    public function testLazyLoadThenSave()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(2);

        $image = $post->featuredImage;
        $this->assertNull($image);

        $newImage = $post->featuredImage()->save([
            'url'      => 'https://example.com/lazy-insert.jpg',
            'alt_text' => 'Lazy then insert',
        ]);

        $this->assertNotFalse($newImage);

        $this->seeInDatabase('images', [
            'imageable_type' => PostModel::class,
            'imageable_id'   => $post->id,
            'url'            => 'https://example.com/lazy-insert.jpg',
        ]);
    }

    public function testSaveDoesNotCreateDuplicateImage()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(2);

        $image1 = $post->featuredImage()->save([
            'url'      => 'https://example.com/first-image.jpg',
            'alt_text' => 'First image',
        ]);

        $this->assertNotFalse($image1);

        $imageCount = model(ImageModel::class)
            ->where('imageable_type', PostModel::class)
            ->where('imageable_id', $post->id)
            ->countAllResults();

        $this->assertSame(1, $imageCount);

        $this->seeInDatabase('images', [
            'imageable_type' => PostModel::class,
            'imageable_id'   => $post->id,
            'url'            => 'https://example.com/first-image.jpg',
        ]);
    }

    public function testMorphFieldsPreservedOnUpdate()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(2);

        $image = $post->featuredImage()->save([
            'url'      => 'https://example.com/original.jpg',
            'alt_text' => 'Original',
        ]);

        $this->assertNotFalse($image);

        /** @var Image|null $createdImage */
        $createdImage = model(ImageModel::class)
            ->where('imageable_type', PostModel::class)
            ->where('imageable_id', $post->id)
            ->first();

        $updated = $post->featuredImage()->save([
            'id'       => $createdImage->id,
            'url'      => 'https://example.com/updated.jpg',
            'alt_text' => 'Updated',
        ]);

        $this->assertNotFalse($updated);

        $this->seeInDatabase('images', [
            'id'             => $createdImage->id,
            'imageable_type' => PostModel::class,
            'imageable_id'   => $post->id,
            'url'            => 'https://example.com/updated.jpg',
        ]);
    }
}
