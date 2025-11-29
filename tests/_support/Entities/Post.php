<?php

declare(strict_types=1);

namespace Tests\Support\Entities;

use CodeIgniter\Entity\Entity;
use CodeIgniter\I18n\Time;
use Michalsn\CodeIgniterRelations\Relations\BelongsTo;
use Michalsn\CodeIgniterRelations\Relations\HasMany;
use Michalsn\CodeIgniterRelations\Relations\MorphMany;
use Michalsn\CodeIgniterRelations\Relations\MorphOne;
use Michalsn\CodeIgniterRelations\Traits\HasLazyRelations;

/**
 * Post Entity
 *
 * @property list<Comment> $comments   Lazy-loaded comments relation
 * @property string        $content
 * @property Time          $created_at
 * @property string        $id
 * @property string        $status
 * @property list<Tag>     $tags       Lazy-loaded tags relation
 * @property string        $title
 * @property Time          $updated_at
 * @property User|null     $user       Lazy-loaded user relation
 * @property string        $user_id
 *
 * @method HasMany   comments()
 * @method MorphOne  featuredImage()
 * @method MorphMany images()
 * @method BelongsTo user()
 */
class Post extends Entity
{
    use HasLazyRelations;

    protected $datamap = [];
    protected $dates   = ['created_at', 'updated_at'];
    protected $casts   = [];
}
