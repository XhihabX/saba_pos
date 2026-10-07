<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function view(User $user, Customer $customer): bool
    {
        return $user->role === 'super_admin' || $user->tenant_id === $customer->tenant_id;
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->role === 'super_admin' || ($user->tenant_id === $customer->tenant_id && in_array($user->role, ['merchant', 'store_manager', 'cashier']));
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->role === 'super_admin' || ($user->tenant_id === $customer->tenant_id && $user->role === 'merchant');
    }
}
