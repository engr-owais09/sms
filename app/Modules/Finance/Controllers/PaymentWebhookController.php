<?php

namespace App\Modules\Finance\Controllers;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\PaymentGatewayTransaction;
use App\Modules\Finance\Services\PaymentProcessingService;
use App\Modules\Institute\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request, string $provider, PaymentProcessingService $paymentService): JsonResponse
    {
        if (!in_array($provider, ['1bill', 'jazzcash', 'easypaisa'], true)) {
            return response()->json(['error' => 'Unsupported payment provider.'], 404);
        }

        $secret = (string) config('services.payment_webhooks.secret', env('PAYMENT_WEBHOOK_SECRET', 'educore_sandbox_webhook_secret'));
        $rawBody = $request->getContent();
        $providedSig = (string) $request->header('X-Gateway-Signature', '');
        $expectedSig = hash_hmac('sha256', $rawBody, $secret);

        if ($providedSig === '' || !hash_equals($expectedSig, $providedSig)) {
            return response()->json(['error' => 'Invalid webhook signature.'], 401);
        }

        $payload = $request->validate([
            'provider_reference' => ['required', 'string', 'max:120'],
            'invoice_id' => ['required', 'uuid'],
            'tenant_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        // Idempotency check
        $existing = PaymentGatewayTransaction::withoutGlobalScopes()
            ->where('provider', $provider)
            ->where('provider_reference', $payload['provider_reference'])
            ->first();

        if ($existing) {
            return response()->json(['status' => 'already_processed', 'transaction_id' => $existing->id], 200);
        }

        $tenant = Tenant::findOrFail($payload['tenant_id']);
        app(TenantContext::class)->setTenant($tenant);
        PostgresTenantContext::set($tenant->id);

        try {
            $tx = DB::transaction(function () use ($provider, $payload, $tenant, $paymentService): PaymentGatewayTransaction {
                PostgresTenantContext::set($tenant->id, isLocal: true);

                $invoice = FeeInvoice::with('student')->where('tenant_id', $tenant->id)->findOrFail($payload['invoice_id']);
                $systemReceiverId = \App\Models\User::where('tenant_id', $tenant->id)->value('id');
                $paymentService->processPayment(
                    $invoice->student,
                    (float) $payload['amount'],
                    [
                        'payment_method' => 'bank_transfer',
                        'instrument_reference' => $payload['provider_reference'],
                        'received_by_user_id' => $systemReceiverId,
                        'target_invoice_ids' => [$invoice->id],
                    ]
                );

                return PaymentGatewayTransaction::create([
                    'tenant_id' => $tenant->id,
                    'fee_invoice_id' => $invoice->id,
                    'provider' => $provider,
                    'provider_reference' => $payload['provider_reference'],
                    'amount_pkr' => (float) $payload['amount'],
                    'status' => 'settled',
                    'payload_json' => $payload,
                ]);
            });
        } finally {
            PostgresTenantContext::reset();
            app(TenantContext::class)->clear();
        }

        return response()->json(['status' => 'settled', 'transaction_id' => $tx->id], 200);
    }
}
