<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExpenseController extends Controller
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

    public function index()
    {
        $tenantId = $this->getTenantId();
        $expenses = Expense::where('tenant_id', $tenantId)->latest()->paginate(15);
        $totalExpenses = Expense::where('tenant_id', $tenantId)->sum('amount');

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'totalExpenses' => (float) $totalExpenses,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $tenantId = $this->getTenantId();
        $validated['tenant_id'] = $tenantId;
        $validated['store_id'] = auth()->user()->store_id ?? (Store::where('tenant_id', $tenantId)->first()?->id ?? 1);

        Expense::create($validated);

        return redirect()->back()->with('success', 'Store expense recorded successfully!');
    }

    public function updateExpense(Request $request, $id)
    {
        $tenantId = $this->getTenantId();
        $expense = Expense::where('id', $id)->where('tenant_id', $tenantId)->firstOrFail();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $expense->update($validated);

        return redirect()->back()->with('success', 'Expense record updated successfully!');
    }

    public function deleteExpense($id)
    {
        $tenantId = $this->getTenantId();
        $expense = Expense::where('id', $id)->where('tenant_id', $tenantId)->firstOrFail();
        $expense->delete();

        return redirect()->back()->with('success', 'Expense record deleted successfully!');
    }
}
