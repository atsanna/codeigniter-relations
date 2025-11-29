<?php

declare(strict_types=1);

namespace Tests\Support\Models;

use CodeIgniter\Model;
use Michalsn\CodeIgniterRelations\Relations\BelongsTo;
use Michalsn\CodeIgniterRelations\Relations\HasMany;
use Michalsn\CodeIgniterRelations\Relations\HasOne;
use Michalsn\CodeIgniterRelations\Relations\MorphOne;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;
use Tests\Support\Entities\User;

class UserModel extends Model
{
    use HasRelations;

    protected $table                  = 'users';
    protected $primaryKey             = 'id';
    protected $returnType             = User::class;
    protected $allowedFields          = ['name', 'email', 'country_id'];
    protected $useTimestamps          = true;
    protected bool $updateOnlyChanged = false;

    /**
     * User has many posts
     *
     * Convention:
     * - Foreign key: user_id (on posts table)
     * - Local key: id (on users table)
     */
    public function posts(): HasMany
    {
        return $this->hasMany(PostModel::class);
    }

    /**
     * User has one profile
     *
     * Convention:
     * - Foreign key: user_id (on profiles table)
     * - Local key: id (on users table)
     */
    public function profile(): HasOne
    {
        return $this->hasOne(ProfileModel::class);
    }

    /**
     * User has many comments
     */
    public function comments(): HasMany
    {
        return $this->hasMany(CommentModel::class);
    }

    /**
     * Example: User has many published posts (with constraint)
     *
     * This demonstrates how you can define relation methods
     * that include query constraints.
     */
    public function publishedPosts(): HasMany
    {
        $relation = $this->hasMany(PostModel::class);
        $relation->setQueryCallback(static function ($model) {
            $model->where('status', 'published');
        });

        return $relation;
    }

    /**
     * User belongs to a country
     *
     * Convention:
     * - Foreign key: country_id (on users table)
     * - Owner key: id (on countries table)
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(CountryModel::class);
    }

    /**
     * User has one avatar image (polymorphic)
     *
     * This demonstrates MorphOne - a polymorphic one-to-one relation.
     * The image can belong to either a User or a Post.
     *
     * Convention:
     * - Morph name: 'imageable'
     * - Type field: imageable_type (stores 'Examples\Models\UserModel')
     * - ID field: imageable_id (stores user id)
     * - Local key: id (on users table)
     */
    public function avatar(): MorphOne
    {
        return $this->morphOne(ImageModel::class, 'imageable');
    }

    /**
     * User has one latest avatar image (of many)
     *
     * Gets the most recent avatar when user has multiple images.
     */
    public function latestAvatar(): MorphOne
    {
        return $this->morphOne(ImageModel::class, 'imageable')->latestOfMany();
    }

    /**
     * User has one oldest avatar image (of many)
     *
     * Gets the first avatar when user has multiple images.
     */
    public function oldestAvatar(): MorphOne
    {
        return $this->morphOne(ImageModel::class, 'imageable')->oldestOfMany();
    }

    /**
     * User has one latest post (of many)
     *
     * Demonstrates "of many" - gets the most recent post when user has multiple posts.
     */
    public function latestPost(): HasOne
    {
        return $this->hasOne(PostModel::class)->latestOfMany();
    }

    /**
     * User has one oldest post (of many)
     *
     * Gets the first post when user has multiple posts.
     */
    public function oldestPost(): HasOne
    {
        return $this->hasOne(PostModel::class)->oldestOfMany();
    }

    /**
     * User has one latest published post (of many with constraint)
     *
     * Gets the most recent published post when user has multiple posts.
     * Combines "of many" with query constraints.
     */
    public function latestPublishedPost(): HasOne
    {
        $relation = $this->hasOne(PostModel::class)->latestOfMany();
        $relation->setQueryCallback(static function ($model) {
            $model->where('status', 'published');
        });

        return $relation;
    }
}
