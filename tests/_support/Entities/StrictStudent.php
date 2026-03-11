<?php

declare(strict_types=1);

namespace Tests\Support\Entities;

use Michalsn\CodeIgniterRelations\Relations\BelongsToMany;
use Michalsn\CodeIgniterRelations\Traits\HasLazyRelations;

/**
 * @property list<Course> $courses
 *
 * @method BelongsToMany courses()
 */
class StrictStudent extends StrictEntity
{
    use HasLazyRelations;

    protected $attributes = [
        'id'              => null,
        'name'            => null,
        'email'           => null,
        'enrollment_date' => null,
        'created_at'      => null,
        'updated_at'      => null,
    ];
    protected $datamap = [];
    protected $dates   = ['created_at', 'updated_at', 'enrollment_date'];
    protected $casts   = [
        'id' => 'int',
    ];
}
