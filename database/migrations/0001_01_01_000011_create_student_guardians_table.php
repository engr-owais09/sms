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
        Schema::create('student_guardians', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('guardian_id')->constrained('guardians')->cascadeOnDelete();
            $table->string('relationship_type', 50)->default('father'); // 'father', 'mother', 'brother', 'sister', 'uncle', 'legal_guardian'
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_emergency_contact')->default(true);
            $table->boolean('is_financial_sponsor')->default(true);
            $table->boolean('can_pickup')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'student_id', 'guardian_id']);
            $table->unique(['tenant_id', 'id']); // Enables composite foreign keys
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_guardians');
    }
};

