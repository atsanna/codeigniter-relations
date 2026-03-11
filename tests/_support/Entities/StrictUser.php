<?php

declare(strict_types=1);

namespace Tests\Support\Entities;

use Michalsn\CodeIgniterRelations\Relations\BelongsTo;
use Michalsn\CodeIgniterRelations\Relations\HasMany;
use Michalsn\CodeIgniterRelations\Relations\HasOne;
use Michalsn\CodeIgniterRelations\Traits\HasLazyRelations;

/**
 * @property Country|null $country
 * @property list<Post>   $posts
 * @property Profile|null $profile
 *
 * @method BelongsTo country()
 * @method HasMany   posts()
 * @method HasOne    profile()
 */
class StrictUser extends StrictEntity
{
    use HasLazyRelations;

    protected $attributes = [
        'id'         => null,
        'name'       => null,
        'email'      => null,
        'country_id' => null,
        'created_at' => null,
        'updated_at' => null,
    ];
    protected $datamap = [];
    protected $dates   = ['created_at', 'updated_at'];
    protected $casts   = [];
}
