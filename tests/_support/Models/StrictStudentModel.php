<?php

declare(strict_types=1);

namespace Tests\Support\Models;

use Tests\Support\Entities\StrictStudent;

class StrictStudentModel extends StudentModel
{
    protected $returnType = StrictStudent::class;
}
