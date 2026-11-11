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
        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('admission_number', 50);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('gender', 20)->default('male'); // 'male', 'female', 'other'
            $table->date('date_of_birth');
            $table->text('b_form_encrypted')->nullable(); // Sensitive PII (AES-256)
            $table->string('b_form_blind_index', 64)->nullable(); // Blind index for fast equality search
            $table->string('blood_group', 10)->nullable();
            $table->string('status', 30)->default('active'); // 'inquiry', 'applicant', 'active', 'suspended', 'alumni', 'transferred', 'expelled'
            $table->date('admission_date');
            $table->jsonb('emergency_contact')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'admission_number']);
            $table->index(['tenant_id', 'b_form_blind_index']);
            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'id']); // Enables composite foreign keys
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};

