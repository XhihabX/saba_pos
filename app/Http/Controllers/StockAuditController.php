<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Stock;
use App\Models\StockAudit;
use App\Models\StockAuditItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockAuditController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $audits = StockAudit::where('tenant_id', $tenantId)
            ->with(['store', 'creator', 'approver', 'items.product', 'items.variant'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($audits);
    }

    public function store(Request $request)
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $tenantId = auth()->user()->tenant_id;

        $audit = StockAudit::create([
            'tenant_id' => $tenantId,
            'store_id' => $request->store_id,
            'reference_no' => 'AUD-'.strtoupper(Str::random(8)),
            'status' => 'draft',
            'notes' => $request->notes,
            'created_by' => auth()->id(),
        ]);

        // Auto-populate expected stock items from active stocks
        $stocks = Stock::where('store_id', $request->store_id)
            ->with(['product', 'variant'])
            ->get();

        foreach ($stocks as $stock) {
            $unitCost = $stock->variant?->cost_price ?? $stock->product?->purchase_cost ?? 0;

            StockAuditItem::create([
                'stock_audit_id' => $audit->id,
                'product_id' => $stock->product_id,
                'variant_id' => $stock->variant_id,
                'expected_qty' => $stock->quantity,
                'counted_qty' => $stock->quantity, // default to expected until updated
                'variance_qty' => 0,
                'unit_cost' => $unitCost,
                'variance_value' => 0.00,
            ]);
        }

        return response()->json([
            'message' => 'Stock audit started successfully.',
            'audit' => $audit->load('items'),
        ], 201);
    }

    public function updateItems(Request $request, StockAudit $audit)
    {
        $tenantId = auth()->user()->tenant_id;
        if ($audit->tenant_id !== $tenantId) {
            abort(403, 'Unauthorized cross-tenant stock audit modification.');
        }

        if ($audit->status === 'approved') {
            return response()->json(['message' => 'Approved stock audit cannot be modified.'], 422);
        }

        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:stock_audit_items,id',
            'items.*.counted_qty' => 'required|integer|min:0',
        ]);

        foreach ($request->items as $itemData) {
            $item = StockAuditItem::where('stock_audit_id', $audit->id)
                ->where('id', $itemData['id'])
                ->first();

            if ($item) {
                $countedQty = (int) $itemData['counted_qty'];
                $varianceQty = $countedQty - $item->expected_qty;
                $varianceValue = $varianceQty * $item->unit_cost;

                $item->update([
                    'counted_qty' => $countedQty,
                    'variance_qty' => $varianceQty,
                    'variance_value' => $varianceValue,
                ]);
            }
        }

        $audit->update(['status' => 'in_progress']);

        return response()->json([
            'message' => 'Stock audit quantities updated successfully.',
            'audit' => $audit->fresh()->load('items'),
        ]);
    }

    public function approve(Request $request, StockAudit $audit)
    {
        $tenantId = auth()->user()->tenant_id;
        if ($audit->tenant_id !== $tenantId) {
            abort(403, 'Unauthorized cross-tenant stock audit approval.');
        }

        if ($audit->status === 'approved') {
            return response()->json(['message' => 'Stock audit has already been approved.'], 422);
        }

        DB::transaction(function () use ($audit, $tenantId) {
            foreach ($audit->items as $item) {
                if ($item->variance_qty !== 0) {
                    $stock = Stock::where('store_id', $audit->store_id)
                        ->where('product_id', $item->product_id)
                        ->where('variant_id', $item->variant_id)
                        ->lockForUpdate()
                        ->first();

                    if ($stock) {
                        $stock->update(['quantity' => $item->counted_qty]);
                    } else {
                        Stock::create([
                            'tenant_id' => $tenantId,
                            'store_id' => $audit->store_id,
                            'product_id' => $item->product_id,
                            'variant_id' => $item->variant_id,
                            'quantity' => $item->counted_qty,
                        ]);
                    }
                }
            }

            $audit->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            AuditLog::create([
                'tenant_id' => $tenantId,
                'user_id' => auth()->id(),
                'action' => 'stock_audit_approved',
                'description' => "Approved stock audit {$audit->reference_no} for store ID {$audit->store_id}",
                'ip_address' => request()->ip(),
            ]);
        });

        return response()->json([
            'message' => 'Stock audit approved and stock adjustments applied successfully.',
            'audit' => $audit->fresh()->load('items'),
        ]);
    }
}
