<?php

declare(strict_types=1);

/**
 * Example Profile Entity demonstrating lazy loading
 */

namespace Tests\Support\Entities;

use CodeIgniter\Entity\Entity;
use CodeIgniter\I18n\Time;
use Michalsn\CodeIgniterRelations\Traits\HasLazyRelations;

/**
 * Profile Entity
 *
 * @property string    $avatar
 * @property string    $bio
 * @property Time      $created_at
 * @property string    $id
 * @property Time      $updated_at
 * @property User|null $user       Lazy-loaded user relation
 * @property string    $user_id
 * @property string    $website
 */
class Profile extends Entity
{
    use HasLazyRelations;

    protected $datamap = [];
    protected $dates   = ['created_at', 'updated_at'];
    protected $casts   = [];
}
