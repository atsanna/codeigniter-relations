<?php

declare(strict_types=1);

namespace Tests\Support\Models;

use CodeIgniter\Model;
use Michalsn\CodeIgniterRelations\Relations\BelongsTo;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;
use Tests\Support\Entities\Comment;

class CommentModel extends Model
{
    use HasRelations;

    protected $table           = 'comments';
    protected $primaryKey      = 'id';
    protected $returnType      = Comment::class;
    protected $allowedFields   = ['post_id', 'user_id', 'content'];
    protected $useTimestamps   = true;
    protected $validationRules = [
        'content' => 'required|min_length[3]',
    ];

    /**
     * Comment belongs to a post
     *
     * Convention:
     * - Foreign key: post_id (on comments table)
     * - Owner key: id (on posts table)
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(PostModel::class);
    }

    /**
     * Comment belongs to a user (author)
     *
     * Convention:
     * - Foreign key: user_id (on comments table)
     * - Owner key: id (on users table)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class);
    }
}
