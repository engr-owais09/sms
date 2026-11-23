<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('tenant_id');
            $table->index(['tenant_id', 'user_id']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('branch_id');
            $table->index(['tenant_id', 'user_id']);
        });

        Schema::create('notices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id')->nullable();
            $table->unsignedBigInteger('author_user_id')->nullable();
            $table->string('title', 200);
            $table->string('title_ur', 200)->nullable();
            $table->text('body');
            $table->text('body_ur')->nullable();
            $table->string('audience', 30)->default('all'); // all, parents, students, staff
            $table->uuid('school_class_id')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'audience', 'is_published']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE "notices" ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE "notices" FORCE ROW LEVEL SECURITY');
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON "notices"');
            DB::statement('
                CREATE POLICY tenant_isolation_policy ON "notices"
                USING ("tenant_id" = NULLIF(current_setting(\'app.current_tenant_id\', true), \'\')::uuid)
                WITH CHECK ("tenant_id" = NULLIF(current_setting(\'app.current_tenant_id\', true), \'\')::uuid)
            ');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON "notices"');
        }
        Schema::dropIfExists('notices');

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'user_id']);
            $table->dropColumn('user_id');
        });
    }
};
