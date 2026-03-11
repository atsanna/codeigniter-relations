<?php

declare(strict_types=1);

namespace Tests\Support\Models;

use Tests\Support\Entities\StrictImage;

class StrictImageModel extends ImageModel
{
    protected $returnType = StrictImage::class;
}
