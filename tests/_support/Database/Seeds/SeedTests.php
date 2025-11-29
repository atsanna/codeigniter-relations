<?php

declare(strict_types=1);

namespace Tests\Support\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;

class SeedTests extends Seeder
{
    public function run()
    {
        $now = Time::now('UTC')->toDateTimeString();

        // Countries
        $countries = [
            ['name' => 'United States', 'code' => 'US', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'United Kingdom', 'code' => 'GB', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Canada', 'code' => 'CA', 'created_at' => $now, 'updated_at' => $now],
        ];

        $this->db->table('countries')->insertBatch($countries);

        // Users
        $users = [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'country_id' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'country_id' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Bob Johnson', 'email' => 'bob@example.com', 'country_id' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Mark Anthony', 'email' => 'mark@example.com', 'country_id' => 1, 'created_at' => $now, 'updated_at' => $now],
        ];

        $this->db->table('users')->insertBatch($users);

        // Profiles (One to one)
        $profiles = [
            [
                'user_id'    => 1,
                'bio'        => 'Software developer and tech enthusiast',
                'avatar'     => 'john.jpg',
                'website'    => 'https://johndoe.com',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id'    => 2,
                'bio'        => 'Designer and content creator',
                'avatar'     => 'jane.jpg',
                'website'    => 'https://janesmith.com',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id'    => 3,
                'bio'        => 'Marketing specialist',
                'avatar'     => 'bob.jpg',
                'website'    => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        $this->db->table('profiles')->insertBatch($profiles);

        // Posts
        $posts = [
            ['user_id' => 1, 'title' => 'Getting Started with CodeIgniter 4', 'content' => 'CodeIgniter 4 is a powerful PHP framework...', 'status' => 'published', 'created_at' => $now, 'updated_at' => $now],
            ['user_id' => 1, 'title' => 'Understanding MVC Architecture', 'content' => 'MVC stands for Model-View-Controller...', 'status' => 'published', 'created_at' => $now, 'updated_at' => $now],
            ['user_id' => 2, 'title' => 'Design Principles for Modern Web Apps', 'content' => 'Modern web design requires attention to...', 'status' => 'published', 'created_at' => $now, 'updated_at' => $now],
            ['user_id' => 2, 'title' => 'Draft Post About Colors', 'content' => 'This is a draft post...', 'status' => 'draft', 'created_at' => $now, 'updated_at' => $now],
            ['user_id' => 3, 'title' => 'Marketing Strategies for 2024', 'content' => 'In 2024, digital marketing will focus on...', 'status' => 'published', 'created_at' => $now, 'updated_at' => $now],
        ];

        $this->db->table('posts')->insertBatch($posts);

        // Comments
        $comments = [
            ['post_id' => 1, 'user_id' => 2, 'content' => 'Great introduction! Very helpful.', 'approved' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['post_id' => 1, 'user_id' => 3, 'content' => 'Thanks for sharing this.', 'approved' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['post_id' => 2, 'user_id' => 2, 'content' => 'Nice explanation of MVC.', 'approved' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['post_id' => 3, 'user_id' => 1, 'content' => 'Excellent design tips!', 'approved' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['post_id' => 3, 'user_id' => 3, 'content' => 'I learned a lot from this.', 'approved' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['post_id' => 5, 'user_id' => 1, 'content' => 'Looking forward to implementing these strategies.', 'approved' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['post_id' => 5, 'user_id' => 2, 'content' => 'Interesting perspective on marketing.', 'approved' => 1, 'created_at' => $now, 'updated_at' => $now],
        ];

        $this->db->table('comments')->insertBatch($comments);

        // Tags
        $tags = [
            ['post_id' => 1, 'name' => 'PHP', 'created_at' => $now, 'updated_at' => $now],
            ['post_id' => 1, 'name' => 'CodeIgniter', 'created_at' => $now, 'updated_at' => $now],
            ['post_id' => 1, 'name' => 'Tutorial', 'created_at' => $now, 'updated_at' => $now],
            ['post_id' => 2, 'name' => 'PHP', 'created_at' => $now, 'updated_at' => $now],
            ['post_id' => 2, 'name' => 'Architecture', 'created_at' => $now, 'updated_at' => $now],
            ['post_id' => 3, 'name' => 'Design', 'created_at' => $now, 'updated_at' => $now],
            ['post_id' => 3, 'name' => 'UI/UX', 'created_at' => $now, 'updated_at' => $now],
            ['post_id' => 5, 'name' => 'Marketing', 'created_at' => $now, 'updated_at' => $now],
            ['post_id' => 5, 'name' => 'Strategy', 'created_at' => $now, 'updated_at' => $now],
        ];

        $this->db->table('tags')->insertBatch($tags);

        // Images (polymorphic)
        $images = [
            ['imageable_type' => 'Tests\\Support\\Models\\UserModel', 'imageable_id' => 1, 'url' => '/images/avatars/john.jpg', 'alt_text' => 'John Doe avatar', 'created_at' => $now, 'updated_at' => $now],
            ['imageable_type' => 'Tests\\Support\\Models\\UserModel', 'imageable_id' => 2, 'url' => '/images/avatars/jane.jpg', 'alt_text' => 'Jane Smith avatar', 'created_at' => $now, 'updated_at' => $now],
            ['imageable_type' => 'Tests\\Support\\Models\\PostModel', 'imageable_id' => 1, 'url' => '/images/posts/codeigniter-intro.jpg', 'alt_text' => 'CodeIgniter intro', 'created_at' => $now, 'updated_at' => $now],
            ['imageable_type' => 'Tests\\Support\\Models\\PostModel', 'imageable_id' => 3, 'url' => '/images/posts/design-principles.jpg', 'alt_text' => 'Modern web design', 'created_at' => $now, 'updated_at' => $now],
            ['imageable_type' => 'Tests\\Support\\Models\\PostModel', 'imageable_id' => 5, 'url' => '/images/posts/marketing-2024.jpg', 'alt_text' => 'Marketing 2024', 'created_at' => $now, 'updated_at' => $now],
        ];

        $this->db->table('images')->insertBatch($images);

        // Courses
        $courses = [
            ['title' => 'Introduction to Programming', 'code' => 'CS101', 'credits' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Data Structures', 'code' => 'CS201', 'credits' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Web Development', 'code' => 'CS301', 'credits' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Database Systems', 'code' => 'CS202', 'credits' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Advanced Algorithms', 'code' => 'CS401', 'credits' => 4, 'created_at' => $now, 'updated_at' => $now],
        ];

        $this->db->table('courses')->insertBatch($courses);

        // Students
        $students = [
            ['name' => 'Alice Johnson', 'email' => 'alice@university.edu', 'enrollment_date' => '2023-09-01', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Bob Williams', 'email' => 'bob@university.edu', 'enrollment_date' => '2023-09-01', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Charlie Brown', 'email' => 'charlie@university.edu', 'enrollment_date' => '2024-01-15', 'created_at' => $now, 'updated_at' => $now],
        ];

        $this->db->table('students')->insertBatch($students);

        // Pivot table: course_student
        $courseStudent = [
            // Alice enrolled in CS101, CS201, CS301
            ['course_id' => 1, 'student_id' => 1, 'grade' => 'A', 'created_at' => $now, 'updated_at' => $now],
            ['course_id' => 2, 'student_id' => 1, 'grade' => 'B+', 'created_at' => $now, 'updated_at' => $now],
            ['course_id' => 3, 'student_id' => 1, 'grade' => 'A-', 'created_at' => $now, 'updated_at' => $now],
            // Bob enrolled in CS101, CS202
            ['course_id' => 1, 'student_id' => 2, 'grade' => 'B', 'created_at' => $now, 'updated_at' => $now],
            ['course_id' => 4, 'student_id' => 2, 'grade' => 'A', 'created_at' => $now, 'updated_at' => $now],
            // Charlie enrolled in CS101, CS202, CS301
            ['course_id' => 1, 'student_id' => 3, 'grade' => null, 'created_at' => $now, 'updated_at' => $now],
            ['course_id' => 4, 'student_id' => 3, 'grade' => null, 'created_at' => $now, 'updated_at' => $now],
            ['course_id' => 3, 'student_id' => 3, 'grade' => null, 'created_at' => $now, 'updated_at' => $now],
        ];

        $this->db->table('course_student')->insertBatch($courseStudent);
    }
}
