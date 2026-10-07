<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->role === 'super_admin' || $user->tenant_id === $order->tenant_id;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->role === 'super_admin' || ($user->tenant_id === $order->tenant_id && in_array($user->role, ['merchant', 'store_manager']));
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->role === 'super_admin' || ($user->tenant_id === $order->tenant_id && $user->role === 'merchant');
    }
}
