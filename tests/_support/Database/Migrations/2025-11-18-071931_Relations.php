<?php

declare(strict_types=1);

namespace Tests\Support\Database\Migrations;

use CodeIgniter\Database\Migration;

class Relations extends Migration
{
    public function up()
    {
        // Countries
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'code'       => ['type' => 'CHAR', 'constraint' => 2, 'null' => false],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->addKey('name');
        $this->forge->createTable('countries');

        // Users
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'country_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->addForeignKey('country_id', 'countries', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('users');

        // Profiles (One to one)
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'bio'        => ['type' => 'TEXT', 'null' => true],
            'avatar'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'website'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('profiles');

        // Posts
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'title'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'content'    => ['type' => 'TEXT', 'null' => false],
            'status'     => ['type' => "ENUM('draft','published','archived')", 'default' => 'draft'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('status');
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('posts');

        // Comments
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'post_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'user_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'content'    => ['type' => 'TEXT', 'null' => false],
            'approved'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('approved');
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('post_id', 'posts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('comments');

        // Tags
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'post_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('name');
        $this->forge->addForeignKey('post_id', 'posts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('tags');

        // Images (Polymorphic)
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'imageable_type' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'imageable_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'url'            => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => false],
            'alt_text'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['imageable_type', 'imageable_id']);
        $this->forge->addKey('created_at');
        $this->forge->createTable('images');

        // Courses (for BelongsToMany)
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'title'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'code'       => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false],
            'credits'    => ['type' => 'INT', 'constraint' => 2, 'default' => 3],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('courses');

        // Students (for BelongsToMany)
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'email'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'enrollment_date' => ['type' => 'DATE', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('students');

        // Pivot table: course_student (alphabetical order, singular)
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'course_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'student_id' => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'grade'      => ['type' => 'VARCHAR', 'constraint' => 2, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['course_id', 'student_id']);
        $this->forge->addForeignKey('course_id', 'courses', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('student_id', 'students', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('course_student');
    }

    public function down()
    {
        $this->forge->dropTable('course_student');
        $this->forge->dropTable('students');
        $this->forge->dropTable('courses');
        $this->forge->dropTable('images');
        $this->forge->dropTable('tags');
        $this->forge->dropTable('comments');
        $this->forge->dropTable('posts');
        $this->forge->dropTable('profiles');
        $this->forge->dropTable('users');
        $this->forge->dropTable('countries');
    }
}
