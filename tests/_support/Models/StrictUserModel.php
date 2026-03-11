<?php

declare(strict_types=1);

namespace Tests\Support\Models;

use Tests\Support\Entities\StrictUser;

class StrictUserModel extends UserModel
{
    protected $returnType = StrictUser::class;
}
