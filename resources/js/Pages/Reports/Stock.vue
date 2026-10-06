<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-8 bg-slate-50 dark:bg-slate-950 min-h-screen text-slate-900 dark:text-slate-100 transition-colors duration-200">
      
      <!-- 📦 Hero Header Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-6 sm:p-8 shadow-xl border border-slate-800">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div class="space-y-2">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 font-bold text-xs uppercase tracking-wider">
              <Boxes class="w-3.5 h-3.5" />
              <span>Inventory Valuation & Batch Expiry Control</span>
            </div>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black font-heading tracking-tight text-white">
              Stock Valuation & FEFO Expiry Report
            </h1>
            <p class="text-xs sm:text-sm text-slate-300 max-w-2xl">
              Track total asset valuation at purchase cost, potential retail profit, active stock batches, and expiring inventory.
            </p>
          </div>

          <button 
            @click="handleExport" 
            class="px-5 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs shadow-lg shadow-indigo-600/25 flex items-center gap-2 transition-all self-start md:self-auto whitespace-nowrap"
          >
            <Download class="w-4 h-4" />
            <span>Export Stock Valuation CSV</span>
          </button>
        </div>
      </div>

      <!-- 📊 Metric Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Asset Valuation (At Purchase Cost)</span>
            <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
              <DollarSign class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-slate-900 dark:text-white">৳{{ totalStockValue.toLocaleString() }}</div>
          <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Total capital invested in inventory</div>
        </div>

        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Potential Retail Sales Revenue</span>
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
              <TrendingUp class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-emerald-600 dark:text-emerald-400">৳{{ totalPotentialRetailValue.toLocaleString() }}</div>
          <div class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">Value if all stock sold at retail price</div>
        </div>

        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Batches Expiring Soon (30 Days)</span>
            <div class="w-10 h-10 rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold">
              <AlertTriangle class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-rose-600 dark:text-rose-400">{{ expiringSoon?.length || 0 }} Batches</div>
          <div class="text-[11px] font-semibold text-rose-600 dark:text-rose-400">Requires urgent FEFO dispatch</div>
        </div>
      </div>

      <!-- 📋 Product Stock List -->
      <div class="p-6 sm:p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-6">
        <div>
          <h3 class="font-black text-xl font-heading text-slate-900 dark:text-white">Product Inventory Valuation Catalog</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400">Asset valuation breakdown by product SKU and quantity</p>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200/80 dark:border-slate-800">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 uppercase text-[10px] bg-slate-50/80 dark:bg-slate-950/60 font-bold tracking-wider">
                <th class="py-4 px-4">Product Name</th>
                <th class="py-4 px-4">SKU</th>
                <th class="py-4 px-4">Cost Price (৳)</th>
                <th class="py-4 px-4">Selling Price (৳)</th>
                <th class="py-4 px-4">Total Stock Qty</th>
                <th class="py-4 px-4">Stock Asset Value (৳)</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-sans">
              <tr v-for="p in products" :key="p.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                <td class="py-4 px-4 font-extrabold text-slate-900 dark:text-slate-100 text-sm whitespace-nowrap">
                  {{ p.name }}
                </td>
                <td class="py-4 px-4 font-mono text-slate-500 dark:text-slate-400">
                  {{ p.sku }}
                </td>
                <td class="py-4 px-4 font-mono font-bold text-slate-700 dark:text-slate-300">
                  ৳{{ (parseFloat(p.purchase_cost) || 0).toLocaleString() }}
                </td>
                <td class="py-4 px-4 font-mono font-bold text-emerald-600 dark:text-emerald-400">
                  ৳{{ (parseFloat(p.selling_price) || 0).toLocaleString() }}
                </td>
                <td class="py-4 px-4 font-mono font-black text-indigo-600 dark:text-indigo-400">
                  {{ (p.stocks?.reduce((acc, s) => acc + (parseFloat(s.quantity) || 0), 0) || 0).toLocaleString() }}
                </td>
                <td class="py-4 px-4 font-mono font-extrabold text-slate-900 dark:text-slate-100">
                  ৳{{ ((p.stocks?.reduce((acc, s) => acc + (parseFloat(s.quantity) || 0), 0) || 0) * (parseFloat(p.purchase_cost) || 0)).toLocaleString() }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Boxes, DollarSign, TrendingUp, AlertTriangle, Download } from 'lucide-vue-next';
import { exportToCSV } from '@/Utils/csvExport';

const props = defineProps({
  totalStockValue: Number,
  totalPotentialRetailValue: Number,
  totalProducts: Number,
  products: Array,
  batches: Array,
  expiringSoon: Array,
});

const handleExport = () => {
  const columns = [
    { label: 'Product ID', key: 'id' },
    { label: 'Product Name', key: 'name' },
    { label: 'SKU', key: 'sku' },
    { label: 'Cost Price (৳)', key: 'purchase_cost' },
    { label: 'Selling Price (৳)', key: 'selling_price' },
  ];
  exportToCSV('stock_inventory_valuation_report', columns, props.products);
};
</script>
