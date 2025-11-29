<?php

declare(strict_types=1);

namespace Tests\Support\Models;

use CodeIgniter\Model;
use Michalsn\CodeIgniterRelations\Relations\BelongsToMany;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;
use Tests\Support\Entities\Course;

class CourseModel extends Model
{
    use HasRelations;

    protected $table          = 'courses';
    protected $primaryKey     = 'id';
    protected $useTimestamps  = true;
    protected $returnType     = Course::class;
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'title',
        'code',
        'credits',
    ];
    protected $dates = [
        'created_at',
        'updated_at',
    ];
    protected $validationRules = [
        'title'   => 'required|max_length[255]',
        'code'    => 'required|max_length[50]',
        'credits' => 'permit_empty|integer',
    ];

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(StudentModel::class);
    }
}
