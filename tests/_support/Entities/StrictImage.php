<?php

declare(strict_types=1);

namespace Tests\Support\Entities;

use Michalsn\CodeIgniterRelations\Relations\MorphTo;
use Michalsn\CodeIgniterRelations\Traits\HasLazyRelations;

/**
 * @property object|null $imageable
 *
 * @method MorphTo imageable()
 */
class StrictImage extends StrictEntity
{
    use HasLazyRelations;

    protected $attributes = [
        'id'             => null,
        'imageable_type' => null,
        'imageable_id'   => null,
        'url'            => null,
        'alt_text'       => null,
        'created_at'     => null,
        'updated_at'     => null,
    ];
    protected $datamap = [];
    protected $dates   = ['created_at', 'updated_at'];
    protected $casts   = [];
}
