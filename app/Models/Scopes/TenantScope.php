<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use App\Models\Store;

class TenantScope implements Scope
{
    /**
     * Recursion guard to prevent infinite loops during auth resolution.
     */
    protected static bool $isResolvingAuth = false;

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Bypass TenantScope on User model queries to prevent Auth::user() infinite recursion loops
        if ($model instanceof \App\Models\User || static::$isResolvingAuth) {
            return;
        }

        static::$isResolvingAuth = true;

        try {
            if (Auth::check()) {
                $user = Auth::user();

                if ($user && $user->role !== 'super_admin' && $user->tenant_id) {
                    $builder->where($model->getTable() . '.tenant_id', $user->tenant_id);
                }
            }
        } finally {
            static::$isResolvingAuth = false;
        }
    }
}

