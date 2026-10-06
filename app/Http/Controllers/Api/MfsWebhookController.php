<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MfsTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MfsWebhookController extends Controller
{
    /**
     * Webhook endpoint receiving SMS/Notifications from Android MFS Listener App
     */
    public function handle(Request $request)
    {
        $timestamp = (int) ($request->header('X-MFS-Timestamp') ?? $request->input('timestamp') ?? time());
        $providedSignature = $request->header('X-MFS-Signature') ?? $request->input('signature');
        $providedSecret = $request->header('X-MFS-Secret') ?? $request->input('secret_key') ?? $request->input('secret');

        $expectedSecret = config('services.mfs.secret_key', env('MFS_WEBHOOK_SECRET', 'iot_pos_mfs_secret_key_2026'));

        // 1. Timestamp Drift Replay Protection (5-minute / 300s window)
        if (!app()->environment('testing') && abs(time() - $timestamp) > 300) {
            Log::warning("MFS Webhook Replay Blocked: Expired timestamp {$timestamp} from IP: " . $request->ip());
            return response()->json([
                'success' => false,
                'message' => 'MFS Webhook request expired or replay attempt detected.',
            ], 403);
        }

        // 2. HMAC-SHA256 or Secret Key Verification
        $isValidAuth = false;
        if (!empty($providedSecret) && hash_equals($expectedSecret, (string) $providedSecret)) {
            $isValidAuth = true;
        }

        if (!empty($providedSignature)) {
            $trxIdForSig = strtoupper(trim((string) $request->input('trx_id', '')));
            $amountForSig = (string) $request->input('amount', '');
            $expectedSignature = hash_hmac('sha256', "{$timestamp}.{$trxIdForSig}.{$amountForSig}", $expectedSecret);
            if (hash_equals($expectedSignature, (string) $providedSignature)) {
                $isValidAuth = true;
            }
        }

        if (app()->environment('testing') && empty($providedSecret) && empty($providedSignature)) {
            $isValidAuth = true;
        }

        if (!$isValidAuth) {
            Log::warning("Unauthorized MFS Webhook attempt from IP: " . $request->ip());
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

        $trxId = strtoupper(trim($validated['trx_id']));

        // Check duplicate TrxID
        $existing = MfsTransaction::where('trx_id', $trxId)->first();
        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'MFS Transaction already logged',
                'data' => $existing,
            ], 200);
        }

        $transaction = MfsTransaction::create([
            'trx_id' => $trxId,
            'sender' => $validated['sender'] ?? null,
            'amount' => $validated['amount'],
            'gateway' => strtolower($validated['gateway'] ?? 'bkash'),
            'status' => 'unclaimed',
        ]);

        Log::info("MFS Webhook Logged: TrxID {$trxId}, Amount {$validated['amount']}, Gateway {$transaction->gateway}");

        return response()->json([
            'success' => true,
            'message' => 'MFS Transaction logged successfully',
            'data' => $transaction,
        ], 201);
    }
}
