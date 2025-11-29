<?php

declare(strict_types=1);

namespace Tests\Support\Models;

use CodeIgniter\Model;
use Michalsn\CodeIgniterRelations\Relations\BelongsTo;
use Michalsn\CodeIgniterRelations\Relations\HasMany;
use Michalsn\CodeIgniterRelations\Relations\MorphMany;
use Michalsn\CodeIgniterRelations\Relations\MorphOne;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;
use Tests\Support\Entities\Post;

class PostModel extends Model
{
    use HasRelations;

    protected $table                  = 'posts';
    protected $primaryKey             = 'id';
    protected $returnType             = Post::class;
    protected $allowedFields          = ['user_id', 'title', 'content', 'status'];
    protected $useTimestamps          = true;
    protected bool $updateOnlyChanged = false;
    protected $validationRules        = [
        'title'   => 'required|min_length[3]',
        'content' => 'required',
    ];

    /**
     * Post belongs to a user
     *
     * Convention:
     * - Foreign key: user_id (on posts table)
     * - Owner key: id (on users table)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class);
    }

    /**
     * Post has many comments
     *
     * Convention:
     * - Foreign key: post_id (on comments table)
     * - Local key: id (on posts table)
     */
    public function comments(): HasMany
    {
        return $this->hasMany(CommentModel::class);
    }

    /**
     * Post has many tags
     *
     * This demonstrates another hasMany relation on the same model
     */
    public function tags(): HasMany
    {
        return $this->hasMany(TagModel::class);
    }

    /**
     * Post has one featured image (polymorphic)
     *
     * This demonstrates MorphOne - the same image model can be used
     * for both user avatars and post featured images.
     *
     * Convention:
     * - Morph name: 'imageable'
     * - Type field: imageable_type (stores 'Examples\Models\PostModel')
     * - ID field: imageable_id (stores post id)
     * - Local key: id (on posts table)
     */
    public function featuredImage(): MorphOne
    {
        return $this->morphOne(ImageModel::class, 'imageable');
    }

    /**
     * Post has many images (polymorphic)
     *
     * This demonstrates MorphMany - a post can have multiple images
     * (gallery images, inline images, etc.)
     *
     * Convention:
     * - Morph name: 'imageable'
     * - Type field: imageable_type (stores 'Tests\Support\Models\PostModel')
     * - ID field: imageable_id (stores post id)
     * - Local key: id (on posts table)
     */
    public function images(): MorphMany
    {
        return $this->morphMany(ImageModel::class, 'imageable');
    }
}
