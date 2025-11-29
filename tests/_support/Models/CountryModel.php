<?php

declare(strict_types=1);

namespace Tests\Support\Models;

use CodeIgniter\Model;
use Michalsn\CodeIgniterRelations\Relations\HasManyThrough;
use Michalsn\CodeIgniterRelations\Relations\HasOneThrough;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;
use Tests\Support\Entities\Country;

class CountryModel extends Model
{
    use HasRelations;

    protected $table           = 'countries';
    protected $primaryKey      = 'id';
    protected $returnType      = Country::class;
    protected $allowedFields   = ['name', 'code'];
    protected $useTimestamps   = true;
    protected $validationRules = [
        'name' => 'required|min_length[2]',
        'code' => 'required|exact_length[2]',
    ];

    /**
     * Country has one latest post through users
     *
     * This demonstrates HasOneThrough - accessing a distantly related
     * single record through an intermediate model.
     *
     * Convention:
     * - First key: country_id (on users table)
     * - Second key: user_id (on posts table)
     * - Local key: id (on countries table)
     * - Second local key: id (on users table)
     */
    public function latestPost(): HasOneThrough
    {
        $relation = $this->hasOneThrough(PostModel::class, UserModel::class);

        // Add constraint to get only the latest post
        $relation->setQueryCallback(static function ($model) {
            $model->orderBy('created_at', 'DESC');
        });

        return $relation;
    }

    /**
     * Country has many posts through users
     *
     * This demonstrates HasManyThrough - accessing all distantly related
     * records through an intermediate model.
     *
     * Example: Get all posts written by users from this country
     */
    public function posts(): HasManyThrough
    {
        return $this->hasManyThrough(PostModel::class, UserModel::class);
    }

    /**
     * Country has many published posts through users
     *
     * Example with query constraint
     */
    public function publishedPosts(): HasManyThrough
    {
        $relation = $this->hasManyThrough(PostModel::class, UserModel::class);

        $relation->setQueryCallback(static function ($model) {
            $model->where('status', 'published');
        });

        return $relation;
    }

    /**
     * Country has many comments through users
     *
     * Another example of hasManyThrough with a different final model
     */
    public function comments(): HasManyThrough
    {
        return $this->hasManyThrough(CommentModel::class, UserModel::class);
    }
}
