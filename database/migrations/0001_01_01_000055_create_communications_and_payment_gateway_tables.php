<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('channel', 20); // sms, whatsapp, email, in_app
            $table->string('recipient', 120);
            $table->string('locale', 5)->default('en');
            $table->text('message');
            $table->string('provider_id', 120)->nullable();
            $table->string('status', 30)->default('sent'); // queued, sent, failed, skipped_quiet_hours
            $table->decimal('cost_pkr', 8, 2)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'channel', 'created_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::create('payment_gateway_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('fee_invoice_id')->nullable();
            $table->string('provider', 30); // 1bill, jazzcash, easypaisa
            $table->string('provider_reference', 120);
            $table->decimal('amount_pkr', 12, 2);
            $table->string('status', 30)->default('settled');
            $table->jsonb('payload_json')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference'], 'uk_gateway_provider_ref');
            $table->index(['tenant_id', 'fee_invoice_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach (['notification_deliveries', 'payment_gateway_transactions'] as $table) {
                DB::statement("ALTER TABLE \"{$table}\" ENABLE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE \"{$table}\" FORCE ROW LEVEL SECURITY");
                DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON \"{$table}\"");
                DB::statement("
                    CREATE POLICY tenant_isolation_policy ON \"{$table}\"
                    USING (\"tenant_id\" = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid)
                    WITH CHECK (\"tenant_id\" = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid)
                ");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (['payment_gateway_transactions', 'notification_deliveries'] as $table) {
                DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON \"{$table}\"");
            }
        }
        Schema::dropIfExists('payment_gateway_transactions');
        Schema::dropIfExists('notification_deliveries');
    }
};
