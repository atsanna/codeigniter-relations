<?php

declare(strict_types=1);

namespace Tests\Support\Entities;

use CodeIgniter\Entity\Entity;
use Michalsn\CodeIgniterRelations\Relations\BelongsToMany;
use Michalsn\CodeIgniterRelations\Traits\HasLazyRelations;

/**
 * @method BelongsToMany courses()
 */
class Student extends Entity
{
    use HasLazyRelations;

    protected $dates = [
        'created_at',
        'updated_at',
        'enrollment_date',
    ];
    protected $casts = [
        'id' => 'int',
    ];
}
