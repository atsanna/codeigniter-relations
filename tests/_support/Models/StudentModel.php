<?php

declare(strict_types=1);

namespace Tests\Support\Models;

use CodeIgniter\Model;
use Michalsn\CodeIgniterRelations\Relations\BelongsToMany;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;
use Tests\Support\Entities\Student;

class StudentModel extends Model
{
    use HasRelations;

    protected $table          = 'students';
    protected $primaryKey     = 'id';
    protected $useTimestamps  = true;
    protected $returnType     = Student::class;
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'name',
        'email',
        'enrollment_date',
    ];
    protected $dates = [
        'created_at',
        'updated_at',
        'enrollment_date',
    ];
    protected $validationRules = [
        'name'  => 'required|max_length[255]',
        'email' => 'required|valid_email|max_length[255]',
    ];

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(CourseModel::class);
    }

    public function coursesWithGrade(): BelongsToMany
    {
        return $this->belongsToMany(CourseModel::class)->withPivot('grade');
    }

    public function coursesWithMultiplePivot(): BelongsToMany
    {
        return $this->belongsToMany(CourseModel::class)->withPivot(['grade', 'created_at', 'updated_at']);
    }

    public function coursesWithCustomAccessor(): BelongsToMany
    {
        return $this->belongsToMany(CourseModel::class)->withPivot('grade')->as('enrollment');
    }

    public function coursesWithTimestamps(): BelongsToMany
    {
        return $this->belongsToMany(CourseModel::class)->withTimestamps();
    }

    public function coursesWithTimestampsAndGrade(): BelongsToMany
    {
        return $this->belongsToMany(CourseModel::class)->withPivot('grade')->withTimestamps();
    }
}
