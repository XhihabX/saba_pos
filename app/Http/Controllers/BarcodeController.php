<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BarcodeController extends Controller
{
    private function getTenantId()
    {
        $user = auth()->user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }
        if ($user->role === 'super_admin') {
            return Store::first()->tenant_id ?? 1;
        }
        if (!$user->tenant_id) {
            abort(403, 'Merchant tenant context required');
        }
        return $user->tenant_id;
    }

    public function index()
    {
        $tenantId = $this->getTenantId();
        $products = Product::where('tenant_id', $tenantId)
            ->select('id', 'name', 'sku', 'barcode', 'selling_price')
            ->get();

        return Inertia::render('Products/Barcodes', [
            'products' => $products,
        ]);
    }
}
