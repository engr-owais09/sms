<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('school_student_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignUuid('school_section_id')->constrained('school_sections')->cascadeOnDelete();
            $table->string('roll_number', 30)->nullable();
            $table->string('enrollment_status', 30)->default('active'); // 'active', 'promoted', 'retained', 'withdrawn'
            $table->date('enrolled_at');
            $table->timestamps();

            // Strict School Constraint: One section enrollment per session per student
            $table->unique(['tenant_id', 'academic_session_id', 'student_id'], 'uk_school_enrollment_student_session');

            // Composite Tenant-Aware Foreign Key to prevent cross-tenant section injection
            $table->foreign(['tenant_id', 'school_section_id'])
                ->references(['tenant_id', 'id'])
                ->on('school_sections')
                ->onDelete('restrict');

            $table->unique(['tenant_id', 'id']); // Enables composite foreign keys
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_student_enrollments');
    }
};

