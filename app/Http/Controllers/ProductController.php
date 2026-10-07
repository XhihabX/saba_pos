<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProductController extends Controller
{
    private function getTenantId()
    {
        $user = auth()->user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }
        if (!$user->tenant_id) {
            abort(403, 'User does not belong to any tenant');
        }
        return $user->tenant_id;
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId();

        $products = Product::where('tenant_id', $tenantId)
            ->with(['category', 'brand', 'unit', 'stocks.store'])
            ->latest()
            ->paginate(15);

        $categories = Category::where('tenant_id', $tenantId)->where('is_active', true)->get();
        $brands = Brand::all();
        $units = Unit::all();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'units' => $units,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => [
                'required',
                'string',
                Rule::unique('products', 'sku')->where(function ($q) use ($tenantId) {
                    return $q->where('tenant_id', $tenantId);
                }),
            ],
            'barcode' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'unit_id' => 'nullable|exists:units,id',
            'purchase_cost' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'alert_quantity' => 'nullable|numeric|min:0',
            'has_serial' => 'nullable|boolean',
            'initial_stock' => 'nullable|numeric|min:0',
        ]);

        $validated['tenant_id'] = $tenantId;

        $product = Product::create($validated);

        // Add initial stock across stores belonging to this tenant
        $stores = Store::where('tenant_id', $tenantId)->get();
        foreach ($stores as $store) {
            Stock::create([
                'store_id' => $store->id,
                'product_id' => $product->id,
                'quantity' => $validated['initial_stock'] ?? 10,
            ]);
        }

        return redirect()->back()->with('success', 'Product created successfully!');
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId();
        $product = Product::where('id', $id)->where('tenant_id', $tenantId)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => [
                'required',
                'string',
                Rule::unique('products', 'sku')->ignore($product->id)->where(function ($q) use ($tenantId) {
                    return $q->where('tenant_id', $tenantId);
                }),
            ],
            'barcode' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'unit_id' => 'nullable|exists:units,id',
            'purchase_cost' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'alert_quantity' => 'nullable|numeric|min:0',
            'has_serial' => 'nullable|boolean',
        ]);

        $product->update($validated);

        return redirect()->back()->with('success', 'Product updated successfully!');
    }

    public function delete($id)
    {
        $tenantId = $this->getTenantId();
        $product = Product::where('id', $id)->where('tenant_id', $tenantId)->firstOrFail();
        $productName = $product->name;
        $product->delete();

        return redirect()->back()->with('success', "Product '{$productName}' deleted.");
    }

    public function storeCategory(Request $request)
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        Category::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'slug' => \Illuminate\Support\Str::slug($validated['name']),
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', "Category '{$validated['name']}' created!");
    }

    public function storeBrand(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        Brand::firstOrCreate([
            'name' => $validated['name'],
        ], [
            'slug' => \Illuminate\Support\Str::slug($validated['name']),
        ]);

        return redirect()->back()->with('success', "Brand '{$validated['name']}' created!");
    }

    public function storeUnit(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'required|string|max:50',
        ]);

        Unit::firstOrCreate([
            'short_name' => $validated['short_name'],
        ], [
            'name' => $validated['name'],
        ]);

        return redirect()->back()->with('success', "Unit '{$validated['name']}' created!");
    }
}
