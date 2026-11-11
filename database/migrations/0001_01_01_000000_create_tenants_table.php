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
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 30)->default('school'); // 'school', 'academy', 'hybrid'
            $table->string('default_locale', 10)->default('en'); // 'en', 'ur'
            $table->string('status', 30)->default('trial'); // 'trial', 'active', 'suspended'
            $table->unsignedSmallInteger('onboarding_step')->default(1);
            $table->boolean('onboarding_completed')->default(false);
            $table->jsonb('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
