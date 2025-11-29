<?php

declare(strict_types=1);

namespace Tests\Support\Models;

use CodeIgniter\Model;
use Michalsn\CodeIgniterRelations\Relations\MorphTo;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;
use Tests\Support\Entities\Image;

class ImageModel extends Model
{
    use HasRelations;

    protected $table           = 'images';
    protected $primaryKey      = 'id';
    protected $returnType      = Image::class;
    protected $allowedFields   = ['imageable_type', 'imageable_id', 'url', 'alt_text'];
    protected $useTimestamps   = true;
    protected $validationRules = [
        'url' => 'required',
    ];

    /**
     * Image belongs to imageable (morphTo)
     *
     * This is the inverse of a polymorphic relation.
     * The image can belong to either a Post or a User (or any other model).
     *
     * Convention:
     * - Morph name: 'imageable'
     * - Type field: imageable_type
     * - ID field: imageable_id
     */
    public function imageable(): MorphTo
    {
        return $this->morphTo('imageable');
    }
}
