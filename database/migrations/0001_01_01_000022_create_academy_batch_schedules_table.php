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
        Schema::create('academy_batch_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('academy_batch_id')->constrained('academy_batches')->cascadeOnDelete();
            $table->unsignedSmallInteger('day_of_week'); // 1 = Monday, 7 = Sunday
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room_number', 50)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'academy_batch_id']);
            $table->unique(['tenant_id', 'id']); // Enables composite foreign keys
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academy_batch_schedules');
    }
};

