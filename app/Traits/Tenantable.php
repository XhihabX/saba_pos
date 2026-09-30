<?php

namespace App\Traits;

use App\Models\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;

trait Tenantable
{
    /**
     * Boot the tenantable trait for a model.
     */
    protected static function bootTenantable(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (Auth::check()) {
                $user = Auth::user();
                if ($user->role !== 'super_admin' && $user->tenant_id && ! $model->tenant_id) {
                    $model->tenant_id = $user->tenant_id;
                }
            }
        });
    }

    /**
     * Scope a query to include all tenants (for super admins or cross-tenant scripts).
     */
    public function scopeWithoutTenant($query)
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
}
