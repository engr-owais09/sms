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
        Schema::create('school_class_subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->boolean('is_elective')->default(false);
            $table->decimal('credit_hours', 4, 2)->default(1.0);
            $table->decimal('pass_marks', 6, 2)->default(33.0);
            $table->decimal('total_marks', 6, 2)->default(100.0);
            $table->timestamps();

            $table->unique(['tenant_id', 'school_class_id', 'subject_id']);
            $table->unique(['tenant_id', 'id']); // Enables composite foreign keys
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_class_subjects');
    }
};

