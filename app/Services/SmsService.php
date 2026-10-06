<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Send SMS Notification using merchant's configured gateway or system default log
     */
    public static function sendSms(int $storeId, string $recipientPhone, string $message): bool
    {
        if (empty($recipientPhone) || empty($message)) {
            return false;
        }

        $store = Store::find($storeId);
        $gatewayUrl = $store?->sms_gateway_url ?? config('services.sms.gateway_url');
        $apiKey = $store?->sms_api_key ?? config('services.sms.api_key');

        if (!$gatewayUrl) {
            // Feature flag / fallback: Log SMS to system audit/application logs when gateway URL is not configured
            Log::info("SMS Gateway [Simulated - No Gateway URL Configured] to {$recipientPhone}: {$message}");
            return true;
        }

        try {
            $response = Http::timeout(5)->post($gatewayUrl, [
                'api_key' => $apiKey,
                'to' => $recipientPhone,
                'message' => $message,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("SMS Dispatch Error to {$recipientPhone}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send Customer Due Payment Reminder SMS
     */
    public static function sendDueReminder(int $storeId, string $customerName, string $phone, float $dueAmount): bool
    {
        $store = Store::find($storeId);
        $storeName = $store?->name ?? 'Our Outlet';
        $formattedDue = number_format($dueAmount, 2);
        
        $msg = "Dear {$customerName}, your current outstanding balance at {$storeName} is BDT {$formattedDue}. Please clear your due at your earliest convenience. Thank you!";
        return self::sendSms($storeId, $phone, $msg);
    }
}
