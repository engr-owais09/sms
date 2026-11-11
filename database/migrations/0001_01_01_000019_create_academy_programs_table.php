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
        Schema::create('academy_programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('name', 150); // e.g. "MDCAT Entry Test Prep"
            $table->string('category', 50)->default('entry_test'); // 'entry_test', 'tuition', 'language', 'professional'
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'branch_id', 'name']);
            $table->unique(['tenant_id', 'id']); // Enables composite foreign keys
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academy_programs');
    }
};

