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
