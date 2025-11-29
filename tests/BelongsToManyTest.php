<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\DatabaseTestTrait;
use Michalsn\CodeIgniterRelations\Exceptions\RelationException;
use Michalsn\CodeIgniterRelations\Exceptions\RelationWriteException;
use ReflectionObject;
use stdClass;
use Tests\Support\Database\Seeds\SeedTests;
use Tests\Support\Entities\Course;
use Tests\Support\Entities\Student;
use Tests\Support\Models\CourseModel;
use Tests\Support\Models\StudentModel;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class BelongsToManyTest extends TestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace;
    protected $seed = SeedTests::class;

    public function testEagerLoadStudentCourses()
    {
        /** @var list<Student> $students */
        $students = model(StudentModel::class)->with('courses')->findAll();

        $this->assertCount(3, $students);

        // Alice has 3 courses
        $this->assertCount(3, $students[0]->courses);
        $this->assertInstanceOf(Course::class, $students[0]->courses[0]);
        $this->assertSame('Introduction to Programming', $students[0]->courses[0]->title);

        // Bob has 2 courses
        $this->assertCount(2, $students[1]->courses);

        // Charlie has 3 courses
        $this->assertCount(3, $students[2]->courses);
    }

    public function testEagerLoadCoursesStudents()
    {
        /** @var list<Course> $courses */
        $courses = model(CourseModel::class)->with('students')->findAll();

        $this->assertCount(5, $courses);

        // CS101 has 3 students (Alice, Bob, Charlie)
        $this->assertCount(3, $courses[0]->students);
        $this->assertInstanceOf(Student::class, $courses[0]->students[0]);
        $this->assertSame('Alice Johnson', $courses[0]->students[0]->name);

        // CS201 has 1 student (Alice)
        $this->assertCount(1, $courses[1]->students);

        // CS301 has 2 students (Alice, Charlie)
        $this->assertCount(2, $courses[2]->students);

        // CS202 has 2 students (Bob, Charlie)
        $this->assertCount(2, $courses[3]->students);

        // CS401 has 0 students
        $this->assertCount(0, $courses[4]->students);
    }

    public function testEagerLoadWithQueryConstraint()
    {
        /** @var list<Student> $students */
        $students = model(StudentModel::class)
            ->with('courses', static fn ($model) => $model->where('courses.credits', 4))
            ->findAll();

        // Alice should have 1 course (CS201 - 4 credits)
        $this->assertCount(1, $students[0]->courses);
        $this->assertSame('Data Structures', $students[0]->courses[0]->title);

        // Bob should have 1 course (CS202 - 4 credits)
        $this->assertCount(1, $students[1]->courses);
        $this->assertSame('Database Systems', $students[1]->courses[0]->title);

        // Charlie should have 1 course (CS202 - 4 credits)
        $this->assertCount(1, $students[2]->courses);
    }

    public function testBelongsToManyWithCallbackLimitAndOrderBy()
    {
        /** @var list<Student> $students */
        $students = model(StudentModel::class)
            ->with('courses', static function ($model) {
                $model->orderBy('courses.title', 'ASC')->limit(2);
            })
            ->find([1, 2]);

        $this->assertCount(2, $students);

        // Each student should have exactly 2 courses, ordered by title ASC
        foreach ($students as $student) {
            $this->assertCount(2, $student->courses);

            $this->assertLessThanOrEqual(
                $student->courses[1]->title,
                $student->courses[0]->title,
            );
        }
    }

    public function testEagerLoadSingleRecord()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->with('courses')->find(1);

        $this->assertInstanceOf(Student::class, $student);

        $this->assertCount(3, $student->courses);
        $this->assertSame('Introduction to Programming', $student->courses[0]->title);
        $this->assertSame('Data Structures', $student->courses[1]->title);
        $this->assertSame('Web Development', $student->courses[2]->title);
    }

    public function testEagerLoadSingleNotExistingRecord()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->with('courses')->find(999);

        $this->assertNotInstanceOf(Student::class, $student);
    }

    public function testEagerLoadMultipleNotExistingRecord()
    {
        $students = model(StudentModel::class)->with('courses')->find([998, 999]);

        $this->assertSame([], $students);
    }

    public function testLazyLoadStudentCourses()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(1);

        $this->assertInstanceOf(Student::class, $student);

        $courses = $student->courses;

        $this->assertIsArray($courses);
        $this->assertCount(3, $courses);
        $this->assertInstanceOf(Course::class, $courses[0]);
        $this->assertSame('Introduction to Programming', $courses[0]->title);
    }

    public function testLazyLoadCourseStudents()
    {
        /** @var Course $course */
        $course = model(CourseModel::class)->find(1);

        $students = $course->students;

        $this->assertIsArray($students);
        $this->assertCount(3, $students);
        $this->assertInstanceOf(Student::class, $students[0]);
        $this->assertSame('Alice Johnson', $students[0]->name);
    }

    public function testEagerLoadBelongsToManyWithModelAsArray()
    {
        $students = model(StudentModel::class)
            ->asArray()
            ->with('courses', static function ($model) {
                $model->asArray();
            })
            ->findAll();

        $this->assertIsArray($students[0]);
        $this->assertArrayHasKey('id', $students[0]);
        $this->assertArrayHasKey('name', $students[0]);

        $this->assertArrayHasKey('courses', $students[0]);
        $this->assertIsArray($students[0]['courses']);

        $this->assertIsArray($students[0]['courses'][0]);
        $this->assertArrayHasKey('id', $students[0]['courses'][0]);
        $this->assertArrayHasKey('title', $students[0]['courses'][0]);
    }

    public function testEagerLoadBelongsToManyWithModelAsObject()
    {
        $students = model(StudentModel::class)
            ->asObject()
            ->with('courses', static function ($model) {
                $model->asObject();
            })
            ->findAll();

        $this->assertInstanceOf(stdClass::class, $students[0]);
        $this->assertObjectHasProperty('id', $students[0]);
        $this->assertObjectHasProperty('name', $students[0]);

        $this->assertObjectHasProperty('courses', $students[0]);
        $this->assertIsArray($students[0]->courses);

        $this->assertInstanceOf(stdClass::class, $students[0]->courses[0]);
        $this->assertObjectHasProperty('id', $students[0]->courses[0]);
        $this->assertObjectHasProperty('title', $students[0]->courses[0]);
    }

    public function testAttachSingleId()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(2); // Bob

        $student->courses()->attach(3);

        $pivot = db_connect()->table('course_student')
            ->where('student_id', 2)
            ->where('course_id', 3)
            ->get()
            ->getRow();

        $this->assertInstanceOf(stdClass::class, $pivot);
    }

    public function testAttachMultipleIds()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(3); // Charlie

        $student->courses()->attach([2, 5]);

        $pivotCount = db_connect()->table('course_student')
            ->where('student_id', 3)
            ->whereIn('course_id', [2, 5])
            ->countAllResults();

        $this->assertSame(2, $pivotCount);
    }

    public function testAttachEntity()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(2); // Bob
        /** @var Course $course */
        $course = model(CourseModel::class)->find(3); // CS301

        $student->courses()->attach($course);

        $pivot = db_connect()->table('course_student')
            ->where('student_id', 2)
            ->where('course_id', 3)
            ->get()
            ->getRow();

        $this->assertInstanceOf(stdClass::class, $pivot);
    }

    public function testAttachArrayOfEntities()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(3); // Charlie
        /** @var list<Course> $courses */
        $courses = model(CourseModel::class)->find([2, 5]); // CS201, CS401

        $student->courses()->attach($courses);

        $pivotCount = db_connect()->table('course_student')
            ->where('student_id', 3)
            ->whereIn('course_id', [2, 5])
            ->countAllResults();

        $this->assertSame(2, $pivotCount);
    }

    public function testAttachRequiresParentContext()
    {
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Cannot call attach() without parent context');

        $relation = model(StudentModel::class)->courses();
        $relation->attach(1);
    }

    public function testAttachWithPivotDataSingleId()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(2); // Bob

        // Attach CS301 with grade
        $student->courses()->attach(3, ['grade' => 'A+']);

        $pivot = db_connect()->table('course_student')
            ->where('student_id', 2)
            ->where('course_id', 3)
            ->get()
            ->getRow();

        $this->assertInstanceOf(stdClass::class, $pivot);
        $this->assertSame('A+', $pivot->grade);
    }

    public function testAttachWithPivotDataMultipleIdsSameData()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(3); // Charlie

        // Attach CS201 and CS401 with same grade
        $student->courses()->attach([2, 5], ['grade' => 'B']);

        $pivots = db_connect()->table('course_student')
            ->where('student_id', 3)
            ->whereIn('course_id', [2, 5])
            ->get()
            ->getResult();

        $this->assertCount(2, $pivots);
        $this->assertSame('B', $pivots[0]->grade);
        $this->assertSame('B', $pivots[1]->grade);
    }

    public function testAttachWithPivotDataDifferentDataPerId()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(2); // Bob

        // Attach multiple courses with different grades for each
        $student->courses()->attach([
            3 => ['grade' => 'A'],
            5 => ['grade' => 'B+'],
        ]);

        // Verify CS301 has grade A
        $pivot1 = db_connect()->table('course_student')
            ->where('student_id', 2)
            ->where('course_id', 3)
            ->get()
            ->getRow();

        $this->assertInstanceOf(stdClass::class, $pivot1);
        $this->assertSame('A', $pivot1->grade);

        // Verify CS401 has grade B+
        $pivot2 = db_connect()->table('course_student')
            ->where('student_id', 2)
            ->where('course_id', 5)
            ->get()
            ->getRow();

        $this->assertInstanceOf(stdClass::class, $pivot2);
        $this->assertSame('B+', $pivot2->grade);
    }

    public function testDetachSingleId()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(1); // Alice

        $count = $student->courses()->detach(1);

        $this->assertSame(1, $count);

        $pivot = db_connect()->table('course_student')
            ->where('student_id', 1)
            ->where('course_id', 1)
            ->get()
            ->getRow();

        $this->assertNotInstanceOf(stdClass::class, $pivot);
    }

    public function testDetachMultipleIds()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(1); // Alice

        $count = $student->courses()->detach([1, 2]);

        $this->assertSame(2, $count);

        $pivotCount = db_connect()->table('course_student')
            ->where('student_id', 1)
            ->whereIn('course_id', [1, 2])
            ->countAllResults();

        $this->assertSame(0, $pivotCount);
    }

    public function testDetachAll()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(1); // Alice

        $count = $student->courses()->detach();

        $this->assertSame(3, $count);

        $pivotCount = db_connect()->table('course_student')
            ->where('student_id', 1)
            ->countAllResults();

        $this->assertSame(0, $pivotCount);
    }

    public function testSyncReplacesCourses()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(1); // Alice

        // Alice has courses [1, 2, 3], sync to [1, 4, 5]
        $result = $student->courses()->sync([1, 4, 5]);

        // Should have attached [4, 5] and detached [2, 3]
        $this->assertCount(2, $result['attached']);
        $this->assertContains(4, $result['attached']);
        $this->assertContains(5, $result['attached']);

        $this->assertCount(2, $result['detached']);
        $this->assertContains('2', $result['detached']);
        $this->assertContains('3', $result['detached']);

        $courses = db_connect()->table('course_student')
            ->where('student_id', 1)
            ->get()
            ->getResultArray();

        $courseIds = array_column($courses, 'course_id');
        sort($courseIds);

        $this->assertSame(['1', '4', '5'], $courseIds);
    }

    public function testSyncToEmpty()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(1); // Alice

        // Alice has courses [1, 2, 3], sync to empty array
        $result = $student->courses()->sync([]);

        $this->assertCount(0, $result['attached']);
        $this->assertCount(3, $result['detached']);

        $pivotCount = db_connect()->table('course_student')
            ->where('student_id', 1)
            ->countAllResults();

        $this->assertSame(0, $pivotCount);
    }

    public function testSyncNoChanges()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(1); // Alice

        // Alice has courses [1, 2, 3], sync to same
        $result = $student->courses()->sync([1, 2, 3]);

        $this->assertCount(0, $result['attached']);
        $this->assertCount(0, $result['detached']);
    }

    public function testSyncWithPivotDataAllSame()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(2); // Bob

        // Bob has courses [1, 4], sync to [1, 3, 4] all with grade 'A'
        $result = $student->courses()->sync([1, 3, 4], ['grade' => 'A']);

        // Should attach course 3
        $this->assertCount(1, $result['attached']);
        $this->assertContains(3, $result['attached']);

        // Should update courses 1 and 4 (already attached)
        $this->assertCount(2, $result['updated']);

        $pivots = db_connect()->table('course_student')
            ->where('student_id', 2)
            ->whereIn('course_id', [1, 3, 4])
            ->get()
            ->getResult();

        $this->assertCount(3, $pivots);

        foreach ($pivots as $pivot) {
            $this->assertSame('A', $pivot->grade);
        }
    }

    public function testSyncWithPivotDataMixedFormat()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(2); // Bob

        // Bob has courses [1, 4], sync with mixed format
        $result = $student->courses()->sync([
            1 => ['grade' => 'A+'],  // Update existing
            3 => ['grade' => 'B'],   // Attach new
            4,                       // Keep existing, no update
        ]);

        // Should attach course 3
        $this->assertCount(1, $result['attached']);
        $this->assertContains(3, $result['attached']);

        // Should update course 1
        $this->assertCount(1, $result['updated']);
        $this->assertContains(1, $result['updated']);

        // Verify course 1 has grade A+
        $pivot1 = db_connect()->table('course_student')
            ->where('student_id', 2)
            ->where('course_id', 1)
            ->get()
            ->getRow();

        $this->assertSame('A+', $pivot1->grade);

        // Verify course 3 has grade B
        $pivot3 = db_connect()->table('course_student')
            ->where('student_id', 2)
            ->where('course_id', 3)
            ->get()
            ->getRow();

        $this->assertSame('B', $pivot3->grade);

        // Verify course 4 still exists (no grade change since it wasn't specified)
        $pivot4 = db_connect()->table('course_student')
            ->where('student_id', 2)
            ->where('course_id', 4)
            ->get()
            ->getRow();

        $this->assertInstanceOf(stdClass::class, $pivot4);
    }

    public function testSyncWithPivotDataUpdatesExisting()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(1); // Alice

        // Alice has courses [1, 2, 3] with grades [A, B+, A-]
        // Sync same courses but change grade to 'A+'
        $result = $student->courses()->sync([1, 2, 3], ['grade' => 'A+']);

        // Nothing attached or detached
        $this->assertCount(0, $result['attached']);
        $this->assertCount(0, $result['detached']);

        // All three should be updated
        $this->assertCount(3, $result['updated']);

        // Verify all grades changed to A+
        $pivots = db_connect()->table('course_student')
            ->where('student_id', 1)
            ->whereIn('course_id', [1, 2, 3])
            ->get()
            ->getResult();

        foreach ($pivots as $pivot) {
            $this->assertSame('A+', $pivot->grade);
        }
    }

    public function testSaveCreatesAndAttachesWithArray()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(1); // Alice

        $course = $student->courses()->save([
            'title'   => 'Advanced Algorithms',
            'code'    => 'CS601',
            'credits' => 4,
        ]);

        $this->assertInstanceOf(Course::class, $course);
        $this->assertNotNull($course->id);
        $this->assertSame('Advanced Algorithms', $course->title);

        $this->seeInDatabase('courses', [
            'title' => 'Advanced Algorithms',
            'code'  => 'CS601',
        ]);

        $this->seeInDatabase('course_student', [
            'student_id' => 1,
            'course_id'  => $course->id,
        ]);
    }

    public function testSaveCreatesAndAttachesWithEntity()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(2); // Bob

        $newCourse          = new Course();
        $newCourse->title   = 'Quantum Computing';
        $newCourse->code    = 'CS701';
        $newCourse->credits = 5;

        $course = $student->courses()->save($newCourse);

        $this->assertInstanceOf(Course::class, $course);
        $this->assertNotNull($course->id);
        $this->assertSame('Quantum Computing', $course->title);

        $this->seeInDatabase('course_student', [
            'student_id' => 2,
            'course_id'  => $course->id,
        ]);
    }

    public function testSaveReturnsFalseOnValidationFailure()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(1);

        $result = $student->courses()->save([
            'title'   => '', // Invalid - empty
            'code'    => '',
            'credits' => 999, // Invalid - too high
        ]);

        $this->assertFalse($result);

        $errors = model(CourseModel::class)->errors();
        $this->assertNotEmpty($errors);
    }

    public function testSaveThrowsExceptionWithPrimaryKey()
    {
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Cannot use save() with existing records (primary key "id" found)');

        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(1);

        $student->courses()->save([
            'id'      => 1, // Primary key not allowed
            'title'   => 'Updated Course',
            'code'    => 'CS999',
            'credits' => 3,
        ]);
    }

    public function testSaveThrowsExceptionWithoutParentContext()
    {
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Cannot call save() without parent context');

        $studentModel = model(StudentModel::class);
        $relation     = $studentModel->courses();

        $relation->save(['title' => 'Test']);
    }

    public function testSaveManyCreatesAndAttachesMultiple()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(3); // Charlie

        $ids = $student->courses()->saveMany([
            ['title' => 'Blockchain Basics', 'code' => 'CS801', 'credits' => 3],
            ['title' => 'Cloud Architecture', 'code' => 'CS802', 'credits' => 4],
            ['title' => 'DevOps Practices', 'code' => 'CS803', 'credits' => 3],
        ]);

        $this->assertCount(3, $ids);

        foreach ($ids as $id) {
            $this->seeInDatabase('courses', ['id' => $id]);
            $this->seeInDatabase('course_student', [
                'student_id' => 3,
                'course_id'  => $id,
            ]);
        }
    }

    public function testSaveManyWithEntities()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(2); // Bob

        $course1          = new Course();
        $course1->title   = 'Microservices';
        $course1->code    = 'CS901';
        $course1->credits = 4;

        $course2          = new Course();
        $course2->title   = 'System Design';
        $course2->code    = 'CS902';
        $course2->credits = 5;

        $ids = $student->courses()->saveMany([$course1, $course2]);

        $this->assertCount(2, $ids);

        foreach ($ids as $id) {
            $this->seeInDatabase('course_student', [
                'student_id' => 2,
                'course_id'  => $id,
            ]);
        }
    }

    public function testSaveManyWithMixedArraysAndEntities()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(1); // Alice

        $courseEntity          = new Course();
        $courseEntity->title   = 'Rust Programming';
        $courseEntity->code    = 'CS1001';
        $courseEntity->credits = 3;

        $ids = $student->courses()->saveMany([
            ['title' => 'Go Programming', 'code' => 'CS1002', 'credits' => 3],
            $courseEntity,
            ['title' => 'Swift Development', 'code' => 'CS1003', 'credits' => 4],
        ]);

        $this->assertCount(3, $ids);
    }

    public function testSaveManyThrowsExceptionWithPrimaryKey()
    {
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Cannot use saveMany() with existing records (primary key "id" found at index 1)');

        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(1);

        $student->courses()->saveMany([
            ['title' => 'Course 1', 'code' => 'CS1101', 'credits' => 3],
            ['id'    => 1, 'title' => 'Course 2', 'code' => 'CS1102', 'credits' => 3], // Has PK at index 1
            ['title' => 'Course 3', 'code' => 'CS1103', 'credits' => 3],
        ]);
    }

    public function testSaveManyWithTransactionRollback()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(1);

        try {
            $student->courses()->saveMany([
                ['title' => 'Valid Course', 'code' => 'CS1201', 'credits' => 3],
                ['title' => '', 'code' => '', 'credits' => 999], // Invalid data
                ['title' => 'Another Valid', 'code' => 'CS1203', 'credits' => 3],
            ]);

            $this->fail('Expected RelationWriteException to be thrown');
        } catch (RelationWriteException $e) {
            $this->assertStringContainsString('Batch save failed at record 1. Transaction rolled back.', $e->getMessage());

            $this->dontSeeInDatabase('courses', ['code' => 'CS1201']);
            $this->dontSeeInDatabase('courses', ['code' => 'CS1203']);
        }
    }

    public function testSaveManyWithoutTransactionPartialSuccess()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(2);

        try {
            $student->courses()->saveMany([
                ['title' => 'Valid Course 1', 'code' => 'CS1301', 'credits' => 3],
                ['title' => '', 'code' => '', 'credits' => 999], // Invalid at index 1
                ['title' => 'Valid Course 2', 'code' => 'CS1303', 'credits' => 3],
            ], false); // No transaction

            $this->fail('Expected RelationWriteException to be thrown');
        } catch (RelationWriteException $e) {
            $succeededIds = $e->succeededIds();

            // First and third should succeed
            $this->assertCount(2, $succeededIds);

            $this->seeInDatabase('courses', ['code' => 'CS1301']);
            $this->seeInDatabase('courses', ['code' => 'CS1303']);

            foreach ($succeededIds as $id) {
                $this->seeInDatabase('course_student', [
                    'student_id' => 2,
                    'course_id'  => $id,
                ]);
            }
        }
    }

    public function testSaveManyThrowsExceptionWithoutParentContext()
    {
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Cannot call saveMany() without parent context');

        $studentModel = model(StudentModel::class);
        $relation     = $studentModel->courses();

        $relation->saveMany([['title' => 'Test', 'code' => 'TST', 'credits' => 3]]);
    }

    public function testPivotTableNameGeneration()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(1);

        $reflection = new ReflectionObject($student->courses());
        $property   = $reflection->getProperty('pivotTable');
        $pivotTable = $property->getValue($student->courses());

        // Should be alphabetical order, singular: course_student
        $this->assertSame('course_student', $pivotTable);
    }

    public function testCustomPivotTableName()
    {
        $customModel = new class () extends StudentModel {
            public function enrollments()
            {
                return $this->belongsToMany(CourseModel::class, 'custom_enrollments');
            }
        };

        $relation = $customModel->enrollments();

        $reflection = new ReflectionObject($relation);
        $property   = $reflection->getProperty('pivotTable');
        $pivotTable = $property->getValue($relation);

        $this->assertSame('custom_enrollments', $pivotTable);
    }

    public function testEmptyRelation()
    {
        /** @var Course|null $course */
        $course = model(CourseModel::class)->find(5); // CS401 has no students

        $this->assertInstanceOf(Course::class, $course);

        $students = $course->students;

        $this->assertIsArray($students);
        $this->assertCount(0, $students);
    }

    public function testDuplicateAttachDoesNotCreateDuplicate()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->find(1); // Alice is already enrolled in CS101

        $student->courses()->attach(1);

        $pivotCount = db_connect()->table('course_student')
            ->where('student_id', 1)
            ->where('course_id', 1)
            ->countAllResults();

        $this->assertSame(1, $pivotCount);
    }

    public function testEagerLoadWithPivotSingleColumn()
    {
        /** @var list<Student> $students */
        // Load students with their courses and include the 'grade' pivot column
        $students = model(StudentModel::class)->with('coursesWithGrade')->findAll();

        // Alice has 3 courses
        $this->assertCount(3, $students[0]->coursesWithGrade);

        // Check first course (CS101) has pivot data
        $this->assertTrue(isset($students[0]->coursesWithGrade[0]->pivot));
        $this->assertIsObject($students[0]->coursesWithGrade[0]->pivot);
        $this->assertTrue(isset($students[0]->coursesWithGrade[0]->pivot->grade));
        $this->assertSame('A', $students[0]->coursesWithGrade[0]->pivot->grade);

        // Check second course (CS201)
        $this->assertSame('B+', $students[0]->coursesWithGrade[1]->pivot->grade);

        // Check third course (CS301)
        $this->assertSame('A-', $students[0]->coursesWithGrade[2]->pivot->grade);

        // Bob's first course (CS101)
        $this->assertSame('B', $students[1]->coursesWithGrade[0]->pivot->grade);

        // Charlie's courses have null grades
        $this->assertNull($students[2]->coursesWithGrade[0]->pivot->grade);
    }

    public function testEagerLoadWithPivotMultipleColumns()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->with('coursesWithMultiplePivot')->find(1);

        $this->assertInstanceOf(Student::class, $student);

        $this->assertCount(3, $student->coursesWithMultiplePivot);

        // Check first course has all three pivot columns
        $this->assertTrue(isset($student->coursesWithMultiplePivot[0]->pivot));
        $this->assertInstanceOf(stdClass::class, $student->coursesWithMultiplePivot[0]->pivot);
        $this->assertTrue(isset($student->coursesWithMultiplePivot[0]->pivot->course_id));
        $this->assertTrue(isset($student->coursesWithMultiplePivot[0]->pivot->student_id));
        $this->assertTrue(isset($student->coursesWithMultiplePivot[0]->pivot->grade));
        $this->assertTrue(isset($student->coursesWithMultiplePivot[0]->pivot->created_at));
        $this->assertTrue(isset($student->coursesWithMultiplePivot[0]->pivot->updated_at));
        $this->assertSame('A', $student->coursesWithMultiplePivot[0]->pivot->grade);
        $this->assertNotNull($student->coursesWithMultiplePivot[0]->pivot->created_at);
        $this->assertNotNull($student->coursesWithMultiplePivot[0]->pivot->updated_at);
    }

    public function testEagerLoadWithPivotAsArray()
    {
        $student = model(StudentModel::class)->asArray()->with('coursesWithGrade')->find(1);

        $this->assertIsArray($student);
        $this->assertCount(3, $student['coursesWithGrade']);

        $this->assertArrayHasKey('pivot', $student['coursesWithGrade'][0]);
        $this->assertIsArray($student['coursesWithGrade'][0]['pivot']);
        $this->assertArrayHasKey('grade', $student['coursesWithGrade'][0]['pivot']);
        $this->assertSame('A', $student['coursesWithGrade'][0]['pivot']['grade']);
    }

    public function testEagerLoadWithPivotAsObject()
    {
        $student = model(StudentModel::class)->asObject()->with('coursesWithGrade')->find(1);

        $this->assertInstanceOf(stdClass::class, $student);
        $this->assertIsArray($student->coursesWithGrade);
        $this->assertInstanceOf(stdClass::class, $student->coursesWithGrade[0]);

        $this->assertObjectHasProperty('pivot', $student->coursesWithGrade[0]);
        $this->assertIsObject($student->coursesWithGrade[0]->pivot);
        $this->assertObjectHasProperty('grade', $student->coursesWithGrade[0]->pivot);
        $this->assertSame('A', $student->coursesWithGrade[0]->pivot->grade);
    }

    public function testWithoutPivotNoExtraData()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->with('courses')->find(1);

        $this->assertCount(3, $student->courses);
        $this->assertObjectNotHasProperty('pivot', $student->courses[0]);
    }

    public function testWithPivotChaining()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->with('coursesWithMultiplePivot')->find(1);

        $this->assertCount(3, $student->coursesWithMultiplePivot);

        // Should have all three columns in pivot (chained in relation definition)
        $pivot = $student->coursesWithMultiplePivot[0]->pivot;
        $this->assertTrue(isset($pivot->grade));
        $this->assertTrue(isset($pivot->created_at));
        $this->assertTrue(isset($pivot->updated_at));
    }

    public function testCustomPivotAccessorWithEntities()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->with('coursesWithCustomAccessor')->find(1);

        $this->assertCount(3, $student->coursesWithCustomAccessor);

        // Pivot should NOT exist
        $this->assertFalse(isset($student->coursesWithCustomAccessor[0]->pivot));

        // Custom accessor 'enrollment' should exist
        $this->assertTrue(isset($student->coursesWithCustomAccessor[0]->enrollment));
        $this->assertIsObject($student->coursesWithCustomAccessor[0]->enrollment);
        $this->assertTrue(isset($student->coursesWithCustomAccessor[0]->enrollment->grade));
        $this->assertSame('A', $student->coursesWithCustomAccessor[0]->enrollment->grade);

        $this->assertTrue(isset($student->coursesWithCustomAccessor[0]->enrollment->student_id));
        $this->assertTrue(isset($student->coursesWithCustomAccessor[0]->enrollment->course_id));
    }

    public function testCustomPivotAccessorWithArrays()
    {
        $student = model(StudentModel::class)->asArray()->with('coursesWithCustomAccessor')->find(1);

        $this->assertIsArray($student);
        $this->assertArrayHasKey('coursesWithCustomAccessor', $student);
        $this->assertCount(3, $student['coursesWithCustomAccessor']);

        $this->assertArrayNotHasKey('pivot', $student['coursesWithCustomAccessor'][0]);
        $this->assertArrayHasKey('enrollment', $student['coursesWithCustomAccessor'][0]);
        $this->assertIsArray($student['coursesWithCustomAccessor'][0]['enrollment']);
        $this->assertArrayHasKey('grade', $student['coursesWithCustomAccessor'][0]['enrollment']);
        $this->assertSame('A', $student['coursesWithCustomAccessor'][0]['enrollment']['grade']);
    }

    public function testCustomPivotAccessorWithObjects()
    {
        $student = model(StudentModel::class)->asObject()->with('coursesWithCustomAccessor')->find(1);

        $this->assertInstanceOf(stdClass::class, $student);
        $this->assertTrue(isset($student->coursesWithCustomAccessor));
        $this->assertCount(3, $student->coursesWithCustomAccessor);

        // Should use 'enrollment' instead of 'pivot'
        $this->assertFalse(isset($student->coursesWithCustomAccessor[0]->pivot));
        $this->assertTrue(isset($student->coursesWithCustomAccessor[0]->enrollment));
        $this->assertIsObject($student->coursesWithCustomAccessor[0]->enrollment);
        $this->assertSame('A', $student->coursesWithCustomAccessor[0]->enrollment->grade);
    }

    public function testWithTimestampsIncludesCreatedAndUpdated()
    {
        // Load with timestamps
        /** @var Student|null $student */
        $student = model(StudentModel::class)->with('coursesWithTimestamps')->find(1);

        $this->assertCount(3, $student->coursesWithTimestamps);

        // Check first course has timestamps
        $this->assertTrue(isset($student->coursesWithTimestamps[0]->pivot));
        $this->assertTrue(isset($student->coursesWithTimestamps[0]->pivot->created_at));
        $this->assertTrue(isset($student->coursesWithTimestamps[0]->pivot->updated_at));

        // Verify timestamps are not null
        $this->assertNotNull($student->coursesWithTimestamps[0]->pivot->created_at);
        $this->assertNotNull($student->coursesWithTimestamps[0]->pivot->updated_at);

        // Foreign keys should also be present
        $this->assertTrue(isset($student->coursesWithTimestamps[0]->pivot->student_id));
        $this->assertTrue(isset($student->coursesWithTimestamps[0]->pivot->course_id));
    }

    public function testWithTimestampsCanBeChained()
    {
        /** @var Student|null $student */
        $student = model(StudentModel::class)->with('coursesWithTimestampsAndGrade')->find(1);

        $this->assertCount(3, $student->coursesWithTimestampsAndGrade);

        $pivot = $student->coursesWithTimestampsAndGrade[0]->pivot;

        // Should have grade column
        $this->assertTrue(isset($pivot->grade));
        $this->assertSame('A', $pivot->grade);

        // Should have timestamps
        $this->assertTrue(isset($pivot->created_at));
        $this->assertTrue(isset($pivot->updated_at));
        $this->assertNotNull($pivot->created_at);
        $this->assertNotNull($pivot->updated_at);

        // Should have foreign keys
        $this->assertTrue(isset($pivot->student_id));
        $this->assertTrue(isset($pivot->course_id));
    }

    public function testWithTimestampsAsArray()
    {
        $student = model(StudentModel::class)->asArray()->with('coursesWithTimestamps')->find(1);

        $this->assertIsArray($student);
        $this->assertArrayHasKey('pivot', $student['coursesWithTimestamps'][0]);
        $this->assertIsArray($student['coursesWithTimestamps'][0]['pivot']);

        // Check timestamps are included
        $this->assertArrayHasKey('created_at', $student['coursesWithTimestamps'][0]['pivot']);
        $this->assertArrayHasKey('updated_at', $student['coursesWithTimestamps'][0]['pivot']);
        $this->assertNotNull($student['coursesWithTimestamps'][0]['pivot']['created_at']);
        $this->assertNotNull($student['coursesWithTimestamps'][0]['pivot']['updated_at']);
    }
}
