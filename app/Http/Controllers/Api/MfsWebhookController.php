<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MfsTransaction;
use App\Models\OrderPayment;
use App\Models\Tenant;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MfsWebhookController extends Controller
{
    /**
     * Webhook endpoint receiving SMS/Notifications from Android MFS Listener App
     */
    public function handle(Request $request)
    {
        $tenantIdHeader = $request->header('X-MFS-Tenant-ID') ?? $request->header('X-MFS-Tenant') ?? $request->input('tenant_id') ?? $request->input('tenant_code');
        $tenant = null;
        if (! empty($tenantIdHeader)) {
            $tenant = Tenant::where('id', $tenantIdHeader)->orWhere('code', $tenantIdHeader)->first();
            if (! $tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid tenant specified for MFS webhook.',
                ], 422);
            }
        }

        $expectedSecret = (string) (($tenant ? $tenant->mfs_webhook_secret : null) ?? config('services.mfs.secret_key') ?? '');

        // Reject with 503 if no secret is configured (per-tenant or env)
        if (empty($expectedSecret)) {
            Log::warning('MFS Webhook unconfigured attempt from IP: '.$request->ip());

            return response()->json([
                'success' => false,
                'message' => 'MFS Webhook service is unconfigured. Secret key required.',
            ], 503);
        }

        // 1. Mandatory Timestamp Drift Replay Protection (5-minute / 300s window) - Never skipped
        $rawTimestamp = $request->header('X-MFS-Timestamp') ?? $request->input('timestamp');
        if ($rawTimestamp === null || ! is_numeric($rawTimestamp) || abs(time() - (int) $rawTimestamp) > 300) {
            Log::warning("MFS Webhook Replay Blocked: Expired or missing timestamp {$rawTimestamp} from IP: ".$request->ip());

            return response()->json([
                'success' => false,
                'message' => 'MFS Webhook request expired or replay attempt detected.',
            ], 403);
        }
        $timestamp = (int) $rawTimestamp;

        // 2. Signature / Secret Authentication
        $providedSignature = $request->header('X-MFS-Signature') ?? $request->input('signature');
        $providedSecret = $request->header('X-MFS-Secret') ?? $request->input('secret_key') ?? $request->input('secret');

        $isValidAuth = false;

        if (! empty($providedSecret) && hash_equals($expectedSecret, (string) $providedSecret)) {
            $isValidAuth = true;
        }

        if (! empty($providedSignature)) {
            $rawBody = $request->getContent();
            $expectedBodySignature = hash_hmac('sha256', $rawBody, $expectedSecret);

            $trxIdForSig = strtoupper(trim((string) $request->input('trx_id', '')));
            $amountForSig = (string) $request->input('amount', '');
            $expectedParamSignature = hash_hmac('sha256', "{$timestamp}.{$trxIdForSig}.{$amountForSig}", $expectedSecret);

            if (hash_equals($expectedBodySignature, (string) $providedSignature) || hash_equals($expectedParamSignature, (string) $providedSignature)) {
                $isValidAuth = true;
            }
        }

        if (! $isValidAuth) {
            Log::warning('Unauthorized MFS Webhook attempt from IP: '.$request->ip());

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized MFS Webhook authentication failed. Valid HMAC signature or secret key required.',
            ], 401);
        }

        $validated = $request->validate([
            'trx_id' => 'required|string|max:64',
            'sender' => 'nullable|string|max:32',
            'amount' => 'required|numeric|min:0.01',
            'gateway' => 'nullable|string|in:bkash,nagad,rocket,upay,other',
        ]);

        $user = $request->user();
        $targetTenantId = ($tenant ? $tenant->id : null) ?? ($user ? $user->tenant_id : null);
        if (! $targetTenantId) {
            $firstTenant = Tenant::first();
            $targetTenantId = $firstTenant ? $firstTenant->id : null;
        }

        if (! $targetTenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant identification required for MFS webhook.',
            ], 422);
        }

        $trxId = strtoupper(trim($validated['trx_id']));

        // Check duplicate TrxID
        $existing = MfsTransaction::where('tenant_id', $targetTenantId)
            ->where('trx_id', $trxId)
            ->first();

        if ($existing) {
            if ($existing->status === 'unclaimed') {
                $counterPayment = OrderPayment::where('tenant_id', $targetTenantId)
                    ->where('reference_no', $trxId)
                    ->where('verification_status', 'counter_verified')
                    ->whereRaw('ABS(amount - ?) < 0.01', [(float) $existing->amount])
                    ->first();

                if ($counterPayment) {
                    $existing->update([
                        'status' => 'claimed',
                        'order_id' => $counterPayment->order_id,
                    ]);

                    $counterPayment->update([
                        'verification_status' => 'auto_verified',
                    ]);

                    AuditLogger::log(
                        'mfs_webhook_auto_matched',
                        "Counter-verified MFS payment auto-matched for order #{$counterPayment->order_id} (TrxID: {$trxId})",
                        [
                            'order_id' => $counterPayment->order_id,
                            'order_payment_id' => $counterPayment->id,
                            'trx_id' => $trxId,
                            'amount' => (float) $existing->amount,
                        ],
                        $counterPayment->order->store_id ?? 1,
                        $targetTenantId
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'MFS Transaction already logged',
                'data' => $existing,
            ], 200);
        }

        $transaction = MfsTransaction::create([
            'tenant_id' => $targetTenantId,
            'trx_id' => $trxId,
            'sender' => $validated['sender'] ?? null,
            'amount' => $validated['amount'],
            'gateway' => strtolower($validated['gateway'] ?? 'bkash'),
            'status' => 'unclaimed',
        ]);

        // Auto-match counter-verified payment if it already arrived at the counter
        $counterPayment = OrderPayment::where('tenant_id', $targetTenantId)
            ->where('reference_no', $trxId)
            ->where('verification_status', 'counter_verified')
            ->whereRaw('ABS(amount - ?) < 0.01', [(float) $validated['amount']])
            ->first();

        if ($counterPayment) {
            $transaction->update([
                'status' => 'claimed',
                'order_id' => $counterPayment->order_id,
            ]);

            $counterPayment->update([
                'verification_status' => 'auto_verified',
            ]);

            AuditLogger::log(
                'mfs_webhook_auto_matched',
                "Counter-verified MFS payment auto-matched via late webhook for order #{$counterPayment->order_id} (TrxID: {$trxId}, Amount: ৳{$validated['amount']})",
                [
                    'order_id' => $counterPayment->order_id,
                    'order_payment_id' => $counterPayment->id,
                    'trx_id' => $trxId,
                    'amount' => (float) $validated['amount'],
                ],
                $counterPayment->order->store_id ?? 1,
                $targetTenantId
            );
        }

        Log::info("MFS Webhook Logged: TrxID {$trxId}, Tenant {$targetTenantId}, Amount {$validated['amount']}, Gateway {$transaction->gateway}");

        return response()->json([
            'success' => true,
            'message' => 'MFS Transaction logged successfully',
            'data' => $transaction,
        ], 201);
    }
}
