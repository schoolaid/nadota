<?php

namespace SchoolAid\Nadota\Tests\Fixtures;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema for the "school" fixture domain used by the composite-resource and
 * HTTP route tests. It lives apart from TestCase::setUpDatabase() so the
 * legacy test_models tables stay untouched; tests opt in through the
 * InteractsWithNadotaApi concern (or by calling FixtureSchema::create()).
 *
 * Relationship map:
 *   School  1-n Grade  1-n Student  1-1 StudentProfile
 *   School  n-n(through Grade) Student          (HasManyThrough)
 *   Grade   1-1(through Student) StudentProfile  (HasOneThrough)
 *   Student n-n Course  (course_student pivot with score)
 *   Student/Grade 1-n Note (morph "notable")      (MorphMany / MorphOne / MorphTo)
 *   Student n-n Label (morph "labelable")          (MorphToMany / MorphedByMany)
 */
class FixtureSchema
{
    public static function create(): void
    {
        if (Schema::hasTable('fx_schools')) {
            return;
        }

        Schema::create('fx_schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city')->nullable();
            $table->timestamps();
        });

        Schema::create('fx_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable();
            $table->string('name');
            $table->integer('level')->default(1);
            $table->timestamps();
        });

        Schema::create('fx_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('password')->nullable();
            $table->text('bio')->nullable();
            $table->text('rich_bio')->nullable();
            $table->text('html_notes')->nullable();
            $table->integer('age')->nullable();
            $table->decimal('balance', 10, 2)->nullable();
            $table->date('birth_date')->nullable();
            $table->dateTime('enrolled_at')->nullable();
            $table->string('class_time')->nullable();
            $table->string('favorite_color')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('accept_terms')->default(false);
            $table->string('status')->nullable();
            $table->string('gender')->nullable();
            $table->json('interests')->nullable();
            $table->json('settings')->nullable();
            $table->json('skills')->nullable();
            $table->json('meta')->nullable();
            $table->text('snippet')->nullable();
            $table->text('template')->nullable();
            $table->string('avatar')->nullable();
            $table->string('document')->nullable();
            $table->json('photos')->nullable();
            $table->text('signature')->nullable();
            $table->string('uuid')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('fx_student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable();
            $table->string('nickname')->nullable();
            $table->text('about')->nullable();
            $table->timestamps();
        });

        Schema::create('fx_courses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->integer('credits')->default(0);
            $table->timestamps();
        });

        Schema::create('fx_course_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id');
            $table->foreignId('student_id');
            $table->integer('score')->nullable();
            $table->timestamps();
        });

        Schema::create('fx_notes', function (Blueprint $table) {
            $table->id();
            $table->string('notable_type')->nullable();
            $table->unsignedBigInteger('notable_id')->nullable();
            $table->text('body');
            $table->boolean('pinned')->default(false);
            $table->timestamps();
        });

        Schema::create('fx_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->nullable();
            $table->timestamps();
        });

        Schema::create('fx_labelables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_id');
            $table->string('labelable_type');
            $table->unsignedBigInteger('labelable_id');
            $table->timestamps();
        });
    }
}
