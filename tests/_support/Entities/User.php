<?php

declare(strict_types=1);

namespace Tests\Support\Entities;

use CodeIgniter\Entity\Entity;
use CodeIgniter\I18n\Time;
use Michalsn\CodeIgniterRelations\Relations\BelongsTo;
use Michalsn\CodeIgniterRelations\Relations\HasMany;
use Michalsn\CodeIgniterRelations\Relations\HasOne;
use Michalsn\CodeIgniterRelations\Relations\MorphOne;
use Michalsn\CodeIgniterRelations\Traits\HasLazyRelations;

/**
 * User Entity
 *
 * @property array        $comments   Lazy-loaded comments relation
 * @property Country|null $country    Lazy-loaded country relation
 * @property Time         $created_at
 * @property string       $email
 * @property string       $id
 * @property string       $name
 * @property list<Post>   $posts      Lazy-loaded posts relation
 * @property Profile|null $profile    Lazy-loaded profile relation
 * @property Time         $updated_at
 *
 * @method MorphOne  avatar()
 * @method BelongsTo country()
 * @method HasMany   posts()
 * @method HasOne    profile()
 */
class User extends Entity
{
    use HasLazyRelations;

    protected $datamap = [];
    protected $dates   = ['created_at', 'updated_at'];
    protected $casts   = [];
}
