<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Inertia\Inertia;

class BarcodeController extends Controller
{
    private function getTenantId()
    {
        $user = auth()->user();
        if (! $user) {
            abort(401, 'Unauthenticated');
        }
        if (! $user->tenant_id) {
            abort(403, 'User does not belong to any tenant');
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
