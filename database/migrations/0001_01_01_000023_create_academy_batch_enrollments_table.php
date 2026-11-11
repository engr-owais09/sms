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
        Schema::create('academy_batch_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('academy_batch_id')->constrained('academy_batches')->cascadeOnDelete();
            $table->string('fee_schedule_type', 50)->default('fixed_course_fee'); // 'fixed_course_fee', 'per_subject', 'monthly_installment'
            $table->decimal('agreed_fee_amount', 12, 2);
            $table->string('enrollment_status', 30)->default('active'); // 'active', 'completed', 'dropped'
            $table->date('enrolled_at');
            $table->timestamps();

            // Unique per specific batch: A student can enroll in multiple DIFFERENT batches/courses concurrently
            $table->unique(['tenant_id', 'academy_batch_id', 'student_id'], 'uk_academy_batch_student_enrollment');

            // Composite Tenant-Aware Foreign Key to prevent cross-tenant batch injection
            $table->foreign(['tenant_id', 'academy_batch_id'])
                ->references(['tenant_id', 'id'])
                ->on('academy_batches')
                ->onDelete('restrict');

            $table->unique(['tenant_id', 'id']); // Enables composite foreign keys
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academy_batch_enrollments');
    }
};

