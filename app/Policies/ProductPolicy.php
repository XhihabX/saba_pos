<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function view(User $user, Product $product): bool
    {
        return $user->role === 'super_admin' || $user->tenant_id === $product->tenant_id;
    }

    public function update(User $user, Product $product): bool
    {
        return $user->role === 'super_admin' || ($user->tenant_id === $product->tenant_id && in_array($user->role, ['merchant', 'store_manager']));
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->role === 'super_admin' || ($user->tenant_id === $product->tenant_id && $user->role === 'merchant');
    }
}
