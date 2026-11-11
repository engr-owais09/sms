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
        Schema::create('academy_courses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('academy_program_id')->constrained('academy_programs')->cascadeOnDelete();
            $table->string('name', 150); // e.g. "MDCAT Physics Crash Course"
            $table->integer('duration_weeks')->default(8);
            $table->string('billing_type', 50)->default('fixed_course_fee'); // 'fixed_course_fee', 'per_subject', 'monthly_installment'
            $table->decimal('base_fee', 12, 2)->default(0.00);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'academy_program_id', 'name']);
            $table->unique(['tenant_id', 'id']); // Enables composite foreign keys
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academy_courses');
    }
};

