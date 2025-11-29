<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\DatabaseTestTrait;
use Michalsn\CodeIgniterRelations\Exceptions\RelationException;
use Michalsn\CodeIgniterRelations\Exceptions\RelationWriteException;
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
final class MorphManyTest extends TestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace;
    protected $seed = SeedTests::class;

    public function testEagerLoadMorphManyWithFind()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);
        $this->assertInstanceOf(Post::class, $post);

        $isset = isset($post->images);
        $this->assertFalse($isset);

        $post = model(PostModel::class)->with('images')->find(1);
        $this->assertInstanceOf(Post::class, $post);

        $isset = isset($post->images);
        $this->assertTrue($isset);

        $this->assertIsArray($post->images);

        if ($post->images !== []) {
            $this->assertInstanceOf(Image::class, $post->images[0]);
            $this->assertSame(PostModel::class, $post->images[0]->imageable_type);
            $this->assertSame('1', $post->images[0]->imageable_id);
        }
    }

    public function testEagerLoadMorphManyWithFindAll()
    {
        $posts = model(PostModel::class)->findAll();
        $this->assertInstanceOf(Post::class, $posts[0]);

        $isset = isset($posts[0]->images);
        $this->assertFalse($isset);

        $posts = model(PostModel::class)->with('images')->findAll();
        $this->assertInstanceOf(Post::class, $posts[0]);

        $isset = isset($posts[0]->images);
        $this->assertTrue($isset);

        $this->assertIsArray($posts[0]->images);
    }

    public function testEagerLoadMorphManyAsArray()
    {
        $post = model(PostModel::class)
            ->with('images', static function ($model) {
                $model->asArray();
            })
            ->find(1);

        $this->assertInstanceOf(Post::class, $post);
        $this->assertTrue(isset($post->images));

        $this->assertIsArray($post->images);

        if ($post->images !== []) {
            $this->assertIsArray($post->images[0]);
        }
    }

    public function testEagerLoadMorphManyWithModelAsArray()
    {
        $posts = model(PostModel::class)
            ->asArray()
            ->with('images', static function ($model) {
                $model->asArray();
            })
            ->findAll();

        $this->assertIsArray($posts[0]);
        $this->assertArrayHasKey('id', $posts[0]);
        $this->assertArrayHasKey('title', $posts[0]);

        $this->assertArrayHasKey('images', $posts[0]);
        $this->assertIsArray($posts[0]['images']);

        if ($posts[0]['images'] !== []) {
            $this->assertIsArray($posts[0]['images'][0]);
            $this->assertArrayHasKey('imageable_type', $posts[0]['images'][0]);
            $this->assertArrayHasKey('imageable_id', $posts[0]['images'][0]);
        }
    }

    public function testEagerLoadMorphManyWithModelAsObject()
    {
        $posts = model(PostModel::class)
            ->asObject()
            ->with('images', static function ($model) {
                $model->asObject();
            })
            ->findAll();

        $this->assertInstanceOf(stdClass::class, $posts[0]);
        $this->assertObjectHasProperty('id', $posts[0]);
        $this->assertObjectHasProperty('title', $posts[0]);

        $this->assertObjectHasProperty('images', $posts[0]);
        $this->assertIsArray($posts[0]->images);

        if ($posts[0]->images !== []) {
            $this->assertInstanceOf(stdClass::class, $posts[0]->images[0]);
            $this->assertObjectHasProperty('imageable_type', $posts[0]->images[0]);
            $this->assertObjectHasProperty('imageable_id', $posts[0]->images[0]);
        }
    }

    public function testMorphManyDoesNotLoadWithoutWith()
    {
        $post = model(PostModel::class)->find(1);

        $this->assertInstanceOf(Post::class, $post);

        $isset = isset($post->images);
        $this->assertFalse($isset);
    }

    public function testMorphManyWithCallback()
    {
        $post = model(PostModel::class)
            ->with('images', static function ($model) {
                $model->where('imageable_id >', 0); // Always true, just testing callback works
            })
            ->find(1);

        $this->assertInstanceOf(Post::class, $post);
        $this->assertTrue(isset($post->images));
        $this->assertIsArray($post->images);
    }

    public function testMorphManyWithCallbackLimitAndOrderBy()
    {
        /** @var list<Post> $posts */
        $posts = model(PostModel::class)
            ->with('images', static function ($model) {
                $model->orderBy('id', 'DESC')->limit(1);
            })
            ->find([1, 5]);

        $this->assertCount(2, $posts);

        // Each post should have exactly 1 images, ordered by id DESC
        foreach ($posts as $post) {
            $this->assertCount(1, $post->images);
        }
    }

    public function testLazyLoadMorphMany()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);
        $this->assertInstanceOf(Post::class, $post);

        $images = $post->images;

        $this->assertIsArray($images);

        if ($images !== []) {
            $this->assertInstanceOf(Image::class, $images[0]);
            $this->assertSame('1', $images[0]->imageable_id);
        }
    }

    public function testSaveUpdatesExistingImage()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->with('images')->find(1);
        $this->assertInstanceOf(Post::class, $post);

        $existingImage = $post->images[0];
        $this->assertInstanceOf(Image::class, $existingImage);

        $updatedImage = $post->images()->save([
            'id'       => $existingImage->id,
            'url'      => 'https://example.com/updated.jpg',
            'alt_text' => $existingImage->alt_text,
        ]);

        $this->assertInstanceOf(Image::class, $updatedImage);
        $this->assertSame($existingImage->id, $updatedImage->id);
        $this->assertSame('https://example.com/updated.jpg', $updatedImage->url);

        $this->seeInDatabase('images', [
            'id'  => $existingImage->id,
            'url' => 'https://example.com/updated.jpg',
        ]);
    }

    public function testSaveCreatesNewImageWhenNoneExists()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);

        $image = $post->images()->save([
            'url'      => 'https://example.com/created-via-save.jpg',
            'alt_text' => 'Created via save',
        ]);

        $this->assertInstanceOf(Image::class, $image);
        $this->assertSame($post->id, $image->imageable_id);
        $this->assertSame(PostModel::class, $image->imageable_type);
        $this->assertSame('https://example.com/created-via-save.jpg', $image->url);

        $this->seeInDatabase('images', [
            'imageable_id'   => $post->id,
            'imageable_type' => PostModel::class,
            'url'            => 'https://example.com/created-via-save.jpg',
        ]);
    }

    public function testSaveReturnsEntityOnSuccess()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);

        $image = $post->images()->save([
            'url'      => 'https://example.com/save-success.jpg',
            'alt_text' => 'Save Success',
        ]);

        $this->assertInstanceOf(Image::class, $image);
    }

    public function testSaveReturnsFalseOnValidationFailure()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);

        $image = $post->images()->save([
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
        $relation  = $postModel->images();

        $relation->save(['url' => 'https://example.com/test.jpg']);
    }

    public function testSaveWithArrayData()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);

        $imageData = [
            'url'      => 'https://example.com/array-save.jpg',
            'alt_text' => 'Array Save',
        ];

        $image = $post->images()->save($imageData);

        $this->assertInstanceOf(Image::class, $image);
        $this->assertSame('https://example.com/array-save.jpg', $image->url);
    }

    public function testSaveWithEntityData()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->with('images')->find(1);

        if (count($post->images) === 0) {
            $image = $post->images()->save([
                'url'      => 'https://example.com/initial.jpg',
                'alt_text' => 'Initial',
            ]);
        } else {
            $image = $post->images[0];
        }

        $image->url = 'https://example.com/entity-save.jpg';

        $savedImage = $post->images()->save($image);

        $this->assertInstanceOf(Image::class, $savedImage);
        $this->assertSame('https://example.com/entity-save.jpg', $savedImage->url);

        $this->seeInDatabase('images', [
            'id'  => $image->id,
            'url' => 'https://example.com/entity-save.jpg',
        ]);
    }

    public function testSaveManyMixedUpdatesAndInserts()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);

        /** @var Image $existingImage */
        $existingImage = $post->images()->save([
            'url'      => 'https://example.com/existing.jpg',
            'alt_text' => 'Existing',
        ]);

        $imagesData = [
            // Update existing
            [
                'id'       => $existingImage->id,
                'url'      => 'https://example.com/updated-via-savemany.jpg',
                'alt_text' => $existingImage->alt_text,
            ],
            // Insert new
            [
                'url'      => 'https://example.com/new-via-savemany.jpg',
                'alt_text' => 'New via SaveMany',
            ],
        ];

        $ids = $post->images()->saveMany($imagesData);

        $this->assertCount(2, $ids);

        $this->seeInDatabase('images', [
            'id'  => $existingImage->id,
            'url' => 'https://example.com/updated-via-savemany.jpg',
        ]);

        $this->seeInDatabase('images', [
            'imageable_id'   => $post->id,
            'imageable_type' => PostModel::class,
            'url'            => 'https://example.com/new-via-savemany.jpg',
        ]);
    }

    public function testSaveManyWithTransactionRollsBackOnFailure()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);

        $imagesData = [
            ['url' => 'https://example.com/valid.jpg', 'alt_text' => 'Valid'],
            ['url' => '', 'alt_text' => ''], // Invalid
        ];

        try {
            $post->images()->saveMany($imagesData, useTransaction: true);
            $this->fail('Expected RelationWriteException was not thrown');
        } catch (RelationWriteException $e) {
            $this->assertStringContainsString('Batch save failed at record 1. Transaction rolled back.', $e->getMessage());
        }
    }

    public function testSaveManyWithoutTransactionPartialSuccess()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);

        $imagesData = [
            ['url' => 'https://example.com/valid.jpg', 'alt_text' => 'Valid'],
            ['url' => '', 'alt_text' => ''], // Invalid
        ];

        try {
            $post->images()->saveMany($imagesData, useTransaction: false);
            $this->fail('Expected RelationWriteException was not thrown');
        } catch (RelationWriteException $e) {
            $this->assertStringContainsString('Batch operation completed with failures', $e->getMessage());
            $this->assertCount(1, $e->succeededIds());
        }
    }

    public function testSaveManyReturnsArrayOfIds()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);

        $imagesData = [
            ['url' => 'https://example.com/image1.jpg', 'alt_text' => 'Image 1'],
            ['url' => 'https://example.com/image2.jpg', 'alt_text' => 'Image 2'],
        ];

        $ids = $post->images()->saveMany($imagesData);

        $this->assertCount(2, $ids);
    }

    public function testSaveManyAllValidationErrorsCaptured()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);

        $imagesData = [
            ['url' => '', 'alt_text' => ''], // Invalid
            ['url' => 'https://example.com/valid.jpg', 'alt_text' => 'Valid'],
            ['url' => '', 'alt_text' => ''], // Invalid
        ];

        try {
            $post->images()->saveMany($imagesData, useTransaction: false);
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

        $postModel = model(PostModel::class);
        $relation  = $postModel->images();

        $relation->saveMany([['url' => 'https://example.com/test.jpg']]);
    }

    public function testSaveManyEmptyArray()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);

        $ids = $post->images()->saveMany([]);

        $this->assertEmpty($ids);
    }

    public function testSaveSetsMorphTypeAndIdCorrectly()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);

        $image = $post->images()->save([
            'url'      => 'https://example.com/morph-test.jpg',
            'alt_text' => 'Morph Test',
        ]);

        $this->assertNotFalse($image);

        $this->seeInDatabase('images', [
            'id'             => $image->id,
            'imageable_type' => PostModel::class,
            'imageable_id'   => $post->id,
            'url'            => 'https://example.com/morph-test.jpg',
        ]);
    }

    public function testSaveSetsMorphFieldsWhenCreating()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);

        $image = $post->images()->save([
            'url'      => 'https://example.com/morph-save-test.jpg',
            'alt_text' => 'Morph Save Test',
        ]);

        $this->assertNotFalse($image);

        $this->assertSame(PostModel::class, $image->imageable_type);
        $this->assertSame($post->id, $image->imageable_id);
    }

    public function testSaveManySetsMorphFieldsForAllRecords()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);

        $imagesData = [
            ['url' => 'https://example.com/savemany-morph1.jpg', 'alt_text' => 'SaveMany Morph 1'],
            ['url' => 'https://example.com/savemany-morph2.jpg', 'alt_text' => 'SaveMany Morph 2'],
        ];

        $ids = $post->images()->saveMany($imagesData);

        foreach ($ids as $id) {
            /** @var Image $image */
            $image = model(ImageModel::class)->find($id);
            $this->assertSame(PostModel::class, $image->imageable_type);
            $this->assertSame($post->id, $image->imageable_id);
        }
    }

    public function testMultipleParentTypesMorphToSameModel()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);
        /** @var Image $postImage */
        $postImage = $post->images()->save([
            'url'      => 'https://example.com/post-image.jpg',
            'alt_text' => 'Post Image',
        ]);

        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);
        /** @var Image $userImage */
        $userImage = $user->avatar()->save([
            'url'      => 'https://example.com/user-image.jpg',
            'alt_text' => 'User Image',
        ]);

        $this->seeInDatabase('images', [
            'id'             => $postImage->id,
            'imageable_type' => PostModel::class,
            'imageable_id'   => $post->id,
        ]);

        $this->seeInDatabase('images', [
            'id'             => $userImage->id,
            'imageable_type' => UserModel::class,
            'imageable_id'   => $user->id,
        ]);
    }

    public function testLazyLoadThenSave()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);
        $this->assertInstanceOf(Post::class, $post);

        $images = $post->images;
        $this->assertIsArray($images);

        $newImage = $post->images()->save([
            'url'      => 'https://example.com/lazy-then-save.jpg',
            'alt_text' => 'Lazy then save',
        ]);

        $this->assertInstanceOf(Image::class, $newImage);
        $this->assertSame($post->id, $newImage->imageable_id);
        $this->assertSame(PostModel::class, $newImage->imageable_type);
    }

    public function testMultipleSaveAttempts()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);

        $image1 = $post->images()->save([
            'url'      => 'https://example.com/first.jpg',
            'alt_text' => 'First Image',
        ]);

        $this->assertInstanceOf(Image::class, $image1);

        $image2 = $post->images()->save([
            'url'      => 'https://example.com/second.jpg',
            'alt_text' => 'Second Image',
        ]);

        $this->assertInstanceOf(Image::class, $image2);

        $this->seeInDatabase('images', [
            'imageable_id'   => $post->id,
            'imageable_type' => PostModel::class,
            'url'            => 'https://example.com/first.jpg',
        ]);

        $this->seeInDatabase('images', [
            'imageable_id'   => $post->id,
            'imageable_type' => PostModel::class,
            'url'            => 'https://example.com/second.jpg',
        ]);
    }
}
