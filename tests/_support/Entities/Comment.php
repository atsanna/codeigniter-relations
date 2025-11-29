<?php

declare(strict_types=1);

namespace Tests\Support\Entities;

use CodeIgniter\Entity\Entity;
use Michalsn\CodeIgniterRelations\Traits\HasLazyRelations;

/**
 * Comment Entity
 *
 * @property string $content
 * @property string $created_at
 * @property int    $id
 * @property array  $post       Lazy-loaded post relation
 * @property int    $post_id
 * @property string $updated_at
 * @property array  $user       Lazy-loaded user relation
 * @property int    $user_id
 */
class Comment extends Entity
{
    use HasLazyRelations;

    protected $datamap = [];
    protected $dates   = ['created_at', 'updated_at'];
    protected $casts   = [];
}
