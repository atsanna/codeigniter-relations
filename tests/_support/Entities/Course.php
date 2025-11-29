<?php

declare(strict_types=1);

namespace Tests\Support\Entities;

use CodeIgniter\Entity\Entity;
use Michalsn\CodeIgniterRelations\Traits\HasLazyRelations;

class Course extends Entity
{
    use HasLazyRelations;

    protected $dates = [
        'created_at',
        'updated_at',
    ];
    protected $casts = [
        'id'      => 'int',
        'credits' => 'int',
    ];
}
