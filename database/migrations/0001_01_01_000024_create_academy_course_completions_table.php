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
        Schema::create('academy_course_completions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('academy_course_id')->constrained('academy_courses')->cascadeOnDelete();
            $table->date('completion_date');
            $table->string('final_grade_or_score', 30)->nullable();
            $table->string('certificate_number', 100)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'student_id']);
            $table->unique(['tenant_id', 'id']); // Enables composite foreign keys
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academy_course_completions');
    }
};

