# Many to many / Belongs To many

A many-to-many relationship between two models using an intermediate pivot table to link records.

### When to Use

Use `BelongsToMany` when both models can have multiple related records. Common examples:

- Students belong to many Courses (and Courses have many Students)
- Users belong to many Roles (and Roles have many Users)
- Products belong to many Tags (and Tags have many Products)

### Database Structure

```
students
--------
id                (primary key)
name
email
enrollment_date

courses
-------
id                (primary key)
title
code
credits

course_student (pivot)
----------------------
id                (primary key)
course_id         (foreign key to courses)
student_id        (foreign key to students)
grade             (optional pivot data)
created_at
updated_at
```

#### Pivot Table Naming Convention

The pivot table name should be derived from both table names in **alphabetical order** and **singular form**:

- `course_student` (correct)
- NOT `student_course`
- NOT `courses_students`

### Defining the Relation

```php
use Michalsn\CodeIgniterRelations\Relations\BelongsToMany;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;

class StudentModel extends Model
{
    use HasRelations;

    protected $table = 'students';
    protected $returnType = Student::class;

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(CourseModel::class);
    }
}
```

```php
class CourseModel extends Model
{
    use HasRelations;

    protected $table = 'courses';
    protected $returnType = Course::class;

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(StudentModel::class);
    }
}
```

#### Custom Pivot Table and Keys

```php
public function courses(): BelongsToMany
{
    return $this->belongsToMany(
        CourseModel::class,
        'enrollments',      // Custom pivot table
        'student_key',      // Parent foreign key in pivot
        'course_key',       // Related foreign key in pivot
        'id',               // Parent primary key
        'id'                // Related primary key
    );
}
```

### Reading Data

#### Eager Loading

```php
// Get student with courses
$student = model(StudentModel::class)->with('courses')->find(1);
foreach ($student->courses as $course) {
    echo "{$course->title} ({$course->code})";
}

// Get course with students
$course = model(CourseModel::class)->with('students')->find(1);
foreach ($course->students as $student) {
    echo $student->name;
}
```

#### Lazy Loading

```php
$student = model(StudentModel::class)->find(1);
foreach ($student->courses as $course) {
    echo $course->title; // Courses are loaded automatically
}
```

#### With Query Constraints

```php
$student = model(StudentModel::class)
    ->with('courses', fn($model) => $model->where('courses.credits >', 3))
    ->find(1);
```

### Pivot Data

#### Retrieving Pivot Columns

By default, only foreign keys are included. Use `withPivot()` to retrieve additional columns:

```php
public function courses(): BelongsToMany
{
    return $this->belongsToMany(CourseModel::class)
        ->withPivot('grade');  // Single column
}

public function coursesWithDetails(): BelongsToMany
{
    return $this->belongsToMany(CourseModel::class)
        ->withPivot(['grade', 'enrolled_at', 'completed_at']);  // Multiple columns
}
```

Access pivot data on related records:

```php
$student = model(StudentModel::class)->with('courses')->find(1);

foreach ($student->courses as $course) {
    echo $course->title;
    echo $course->pivot->student_id;  // Foreign key (always included)
    echo $course->pivot->course_id;   // Foreign key (always included)
    echo $course->pivot->grade;       // Additional column from withPivot()
}
```

#### Custom Pivot Accessor

Customize the pivot property name using `as()`:

```php
public function courses(): BelongsToMany
{
    return $this->belongsToMany(CourseModel::class)
        ->withPivot('grade')
        ->as('enrollment');  // Custom accessor
}

// Usage
$course->enrollment->grade;
$course->enrollment->student_id;
```

#### Including Timestamps

Use `withTimestamps()` to automatically include `created_at` and `updated_at`:

```php
public function courses(): BelongsToMany
{
    return $this->belongsToMany(CourseModel::class)
        ->withPivot('grade')
        ->withTimestamps();
}

// Access timestamps
$course->pivot->created_at;
$course->pivot->updated_at;
```

Custom timestamp column names:

```php
public function courses(): BelongsToMany
{
    return $this->belongsToMany(CourseModel::class)
        ->withTimestamps('enrolled_at', 'modified_at');
}
```

### Writing Data

#### Attaching Records

Use `attach()` to create pivot table entries:

```php
$student = model(StudentModel::class)->find(1);

// Attach single course
$student->courses()->attach(3);

// Attach multiple courses
$student->courses()->attach([3, 4, 5]);

// Attach using entity
$course = model(CourseModel::class)->find(3);
$student->courses()->attach($course);

// Attach multiple entities
$courses = model(CourseModel::class)->find([3, 4, 5]);
$student->courses()->attach($courses);
```

##### Attaching with Pivot Data

```php
// Single course with pivot data
$student->courses()->attach(3, ['grade' => 'A', 'enrolled_at' => '2024-01-15']);

// Multiple courses with same pivot data
$student->courses()->attach([3, 4, 5], ['grade' => 'B']);

// Multiple courses with different pivot data
$student->courses()->attach([
    3 => ['grade' => 'A', 'enrolled_at' => '2024-01-15'],
    4 => ['grade' => 'B+', 'enrolled_at' => '2024-01-20'],
    5 => ['grade' => 'A-', 'enrolled_at' => '2024-01-25'],
]);
```

When using `withTimestamps()`, timestamps are automatically added.

#### Detaching Records

Use `detach()` to remove pivot table entries:

```php
$student = model(StudentModel::class)->find(1);

// Detach single course
$count = $student->courses()->detach(3);

// Detach multiple courses
$count = $student->courses()->detach([3, 4]);

// Detach all courses
$count = $student->courses()->detach();
```

#### Syncing Relationships

Use `sync()` to make the relationship match exactly a list of IDs:

```php
$student = model(StudentModel::class)->find(1);

// Student will have exactly these courses
$result = $student->courses()->sync([1, 3, 5]);

// Returns what changed
echo count($result['attached']); // Newly attached IDs
echo count($result['detached']); // Removed IDs

// Sync to empty (detach all)
$student->courses()->sync([]);
```

##### Syncing with Pivot Data

```php
// Same pivot data for all
$result = $student->courses()->sync([1, 2, 3], ['active' => true]);

// Different pivot data for each
$result = $student->courses()->sync([
    1 => ['grade' => 'A', 'active' => true],
    2 => ['grade' => 'B+', 'active' => true],
    3 => ['grade' => 'A-', 'active' => false],
]);

// Mixed format
$result = $student->courses()->sync([
    1 => ['grade' => 'A+'],  // Update grade
    2,                       // Keep (no pivot changes)
    3 => ['grade' => 'B'],   // Update or set grade
]);
```

When syncing with pivot data, the result includes:

- `attached` - Newly attached IDs
- `detached` - Removed IDs
- `updated` - Existing IDs with updated pivot data

#### Creating and Attaching New Records

Use `save()` to create a new related record and automatically attach it:

```php
$student = model(StudentModel::class)->find(1);

// Create new course and attach
$course = $student->courses()->save([
    'title'   => 'Machine Learning',
    'code'    => 'CS405',
    'credits' => 4,
]);

// Using entity
$newCourse = new Course();
$newCourse->title = 'Deep Learning';
$newCourse->code = 'CS505';
$newCourse->credits = 5;
$course = $student->courses()->save($newCourse);
```

**Important:** `save()` only works with NEW records (no primary key). Use `attach()` for existing records.

Use `saveMany()` to create and attach multiple new records:

```php
$student = model(StudentModel::class)->find(1);

// Create and attach multiple courses
$ids = $student->courses()->saveMany([
    ['title' => 'AI Basics', 'code' => 'CS501', 'credits' => 3],
    ['title' => 'Neural Networks', 'code' => 'CS502', 'credits' => 4],
]);

// Disable transaction for partial success
$ids = $student->courses()->saveMany([...], useTransaction: false);
```

### Available Methods

| Method | Description |
|--------|-------------|
| `attach($ids, $pivotData = [])` | Create pivot entries to link existing records |
| `detach($ids = null)` | Remove pivot entries (detach all if no IDs provided) |
| `sync($ids, $pivotData = [])` | Synchronize to match exactly the provided IDs |
| `save($data)` | Create new related record and attach it |
| `saveMany($data, $useTransaction = true)` | Create multiple new related records and attach them |
| `withPivot($columns)` | Include additional pivot table columns |
| `withTimestamps($created = 'created_at', $updated = 'updated_at')` | Include pivot timestamps |
| `as($accessor)` | Customize pivot property name |
