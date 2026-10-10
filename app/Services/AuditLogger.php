<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Record an immutable audit log entry across any portal.
     */
    public static function log(string $action, string $description, array $payload = [], ?int $storeId = null, ?int $tenantId = null): AuditLog
    {
        $user = Auth::user();

        $resolvedTenantId = $tenantId ?? ($user?->tenant_id);
        $resolvedStoreId = $storeId ?? ($user?->store_id);
        $userName = $user?->name ?? 'System Guest';

        return AuditLog::create([
            'tenant_id' => $resolvedTenantId,
            'store_id' => $resolvedStoreId,
            'user_id' => $user?->id,
            'user_name' => $userName,
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => substr(request()->header('User-Agent') ?? '', 0, 255),
            'payload' => ! empty($payload) ? $payload : null,
        ]);
    }
}
