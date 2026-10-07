<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

class StorePolicy
{
    public function view(User $user, Store $store): bool
    {
        return $user->role === 'super_admin' || $user->tenant_id === $store->tenant_id;
    }

    public function update(User $user, Store $store): bool
    {
        return $user->role === 'super_admin' || ($user->tenant_id === $store->tenant_id && in_array($user->role, ['merchant', 'store_manager']));
    }

    public function delete(User $user, Store $store): bool
    {
        return $user->role === 'super_admin' || ($user->tenant_id === $store->tenant_id && $user->role === 'merchant');
    }
}
