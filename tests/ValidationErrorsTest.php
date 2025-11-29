<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\DatabaseTestTrait;
use Michalsn\CodeIgniterRelations\Exceptions\RelationWriteException;
use Tests\Support\Database\Seeds\SeedTests;
use Tests\Support\Entities\Post;
use Tests\Support\Entities\Profile;
use Tests\Support\Entities\User;
use Tests\Support\Models\PostModel;
use Tests\Support\Models\ProfileModel;
use Tests\Support\Models\UserModel;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class ValidationErrorsTest extends TestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace;
    protected $seed = SeedTests::class;

    public function testSaveValidationErrorsAccessible()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);

        $profile = $user->profile()->save([
            'id'      => 1,
            'bio'     => 'ab', // Too short
            'avatar'  => '',
            'website' => '',
        ]);

        $this->assertFalse($profile);

        $errors = model(ProfileModel::class)->errors();
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('bio', $errors);
    }

    public function testSaveValidationErrorOnCreate()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(3);

        $post = $user->posts()->save([
            'title'   => 'ab', // Too short - min_length[3]
            'content' => 'Some content',
            'status'  => 'draft',
        ]);

        $this->assertFalse($post);

        $errors = model(PostModel::class)->errors();
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('title', $errors);
    }

    public function testSaveManyValidationErrorsInException()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);

        $postsData = [
            [
                'title'   => 'Valid Post',
                'content' => 'Valid content',
                'status'  => 'published',
            ],
            [
                'title'   => 'ab', // Invalid
                'content' => 'Content',
                'status'  => 'draft',
            ],
        ];

        try {
            $user->posts()->saveMany($postsData, useTransaction: false);
            $this->fail('Expected RelationWriteException was not thrown');
        } catch (RelationWriteException $e) {
            $this->assertCount(1, $e->succeededIds());
            $this->assertSame([1], $e->failedIndexes());

            $errors = $e->errors();
            $this->assertArrayHasKey(1, $errors);
        }
    }

    public function testSaveManyMixedValidationErrors()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->with('posts')->find(1);

        $existingPost = $user->posts[0];

        $postsData = [
            // Update existing with invalid data
            [
                'id'      => $existingPost->id,
                'title'   => 'ab', // Invalid
                'content' => $existingPost->content,
                'status'  => $existingPost->status,
            ],
            // Insert new with valid data
            [
                'title'   => 'New Valid Post',
                'content' => 'New content',
                'status'  => 'published',
            ],
        ];

        try {
            $user->posts()->saveMany($postsData, useTransaction: false);
            $this->fail('Expected RelationWriteException was not thrown');
        } catch (RelationWriteException $e) {
            $this->assertCount(1, $e->succeededIds());
            $this->assertSame([0], $e->failedIndexes());
        }
    }

    public function testPolymorphicSaveManyValidationErrors()
    {
        /** @var Post|null $post */
        $post = model(PostModel::class)->find(1);

        $commentsData = [
            ['content' => 'Valid comment content', 'user_id' => 1],
            ['content' => 'ab', 'user_id' => 2], // Invalid
            ['content' => 'Another valid comment', 'user_id' => 3],
        ];

        try {
            $post->comments()->saveMany($commentsData, useTransaction: false);
            $this->fail('Expected RelationWriteException was not thrown');
        } catch (RelationWriteException $e) {
            $this->assertCount(2, $e->succeededIds());
            $this->assertSame([1], $e->failedIndexes());

            $errors = $e->errors();
            $this->assertArrayHasKey(1, $errors);
        }
    }

    public function testMultipleRelationErrorsIndependent()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);

        $profile = $user->profile()->save([
            'bio'     => 'ab', // Invalid
            'avatar'  => '',
            'website' => '',
        ]);

        $this->assertFalse($profile);

        $profileErrors = model(ProfileModel::class)->errors();
        $this->assertNotEmpty($profileErrors);

        $post = $user->posts()->save([
            'title'   => 'ab', // Invalid
            'content' => 'Content',
            'status'  => 'draft',
        ]);

        $this->assertFalse($post);

        $postErrors = model(PostModel::class)->errors();
        $this->assertNotEmpty($postErrors);

        $this->assertNotSame($profileErrors, $postErrors);
    }

    public function testSuccessfulSaveWithValidData()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(3);

        $profile = $user->profile()->save([
            'bio'     => 'This is a valid bio that meets all requirements',
            'avatar'  => 'avatar.jpg',
            'website' => 'https://example.com',
        ]);

        $this->assertInstanceOf(Profile::class, $profile);

        $errors = model(ProfileModel::class)->errors();
        $this->assertEmpty($errors);
    }

    public function testSuccessfulSaveManyWithValidData()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);

        $postsData = [
            ['title' => 'Valid Post 1', 'content' => 'Content 1', 'status' => 'published'],
            ['title' => 'Valid Post 2', 'content' => 'Content 2', 'status' => 'draft'],
            ['title' => 'Valid Post 3', 'content' => 'Content 3', 'status' => 'published'],
        ];

        $ids = $user->posts()->saveMany($postsData);

        $this->assertCount(3, $ids);

        foreach ($ids as $id) {
            $this->seeInDatabase('posts', ['id' => $id, 'user_id' => $user->id]);
        }
    }

    public function testErrorsAreClearedBetweenOperations()
    {
        /** @var User|null $user */
        $user = model(UserModel::class)->find(1);

        $invalidPost = $user->posts()->save([
            'title'   => 'ab', // Invalid
            'content' => 'Content',
            'status'  => 'draft',
        ]);

        $this->assertFalse($invalidPost);
        $this->assertNotEmpty(model(PostModel::class)->errors());

        $validPost = $user->posts()->save([
            'title'   => 'Valid Title',
            'content' => 'Valid content',
            'status'  => 'published',
        ]);

        $this->assertInstanceOf(Post::class, $validPost);

        $errors = model(PostModel::class)->errors();
        $this->assertEmpty($errors);
    }
}
