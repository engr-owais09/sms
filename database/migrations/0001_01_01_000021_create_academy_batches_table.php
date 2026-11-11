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
        Schema::create('academy_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('academy_course_id')->constrained('academy_courses')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('name', 100); // e.g. "Evening Batch 2026-A"
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('max_capacity')->default(50);
            $table->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('upcoming'); // 'upcoming', 'in_progress', 'completed', 'cancelled'
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'academy_course_id', 'name']);
            $table->unique(['tenant_id', 'id']); // Enables composite foreign keys
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academy_batches');
    }
};

