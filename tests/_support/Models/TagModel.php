<?php

declare(strict_types=1);

namespace Tests\Support\Models;

use CodeIgniter\Model;
use Michalsn\CodeIgniterRelations\Relations\BelongsTo;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;
use Tests\Support\Entities\Tag;

class TagModel extends Model
{
    use HasRelations;

    protected $table         = 'tags';
    protected $primaryKey    = 'id';
    protected $returnType    = Tag::class;
    protected $allowedFields = ['post_id', 'name'];
    protected $useTimestamps = true;

    /**
     * Tag belongs to a post
     *
     * Convention:
     * - Foreign key: post_id (on tags table)
     * - Owner key: id (on posts table)
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(PostModel::class);
    }
}
