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
        Schema::create('school_promotions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('from_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignUuid('to_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignUuid('from_section_id')->constrained('school_sections')->cascadeOnDelete();
            $table->foreignUuid('to_section_id')->constrained('school_sections')->cascadeOnDelete();
            $table->string('result_summary', 50)->default('promoted'); // 'promoted', 'retained', 'graduated'
            $table->foreignId('promoted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('promoted_at');
            $table->text('remarks')->nullable();
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
        Schema::dropIfExists('school_promotions');
    }
};

