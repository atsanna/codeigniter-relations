<?php

declare(strict_types=1);

namespace Tests\Support\Entities;

use CodeIgniter\Entity\Entity;
use Michalsn\CodeIgniterRelations\Traits\HasLazyRelations;

/**
 * Country entity
 *
 * @property string $code
 * @property string $created_at
 * @property string $id
 * @property string $name
 * @property string $updated_at
 */
class Country extends Entity
{
    use HasLazyRelations;

    protected $datamap = [];
    protected $dates   = ['created_at', 'updated_at'];
    protected $casts   = [];
}
