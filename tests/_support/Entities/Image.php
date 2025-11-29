<?php

declare(strict_types=1);

namespace Tests\Support\Entities;

use CodeIgniter\Entity\Entity;
use Michalsn\CodeIgniterRelations\Relations\MorphTo;
use Michalsn\CodeIgniterRelations\Traits\HasLazyRelations;

/**
 * @method MorphTo imageable()
 */
class Image extends Entity
{
    use HasLazyRelations;

    protected $datamap = [];
    protected $dates   = ['created_at', 'updated_at'];
    protected $casts   = [];
}
