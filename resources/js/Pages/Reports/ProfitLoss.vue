<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-6">
      <!-- Title Bar -->
      <div>
        <h1 class="text-2xl sm:text-3xl font-black font-heading text-slate-900">
          Profit & Loss Statement
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">
          Automated Accounting with COGS, Gross Profit, and Net Profit
        </p>
      </div>

      <!-- Financial Formula Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
        <!-- Revenue Card -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-xs">
          <div class="text-xs text-slate-500 uppercase font-bold mb-2">1. Total Gross Revenue</div>
          <div class="text-2xl font-black font-heading text-emerald-700">
            ৳{{ formatMoney(totalSales) }}
          </div>
          <div class="text-[11px] text-slate-500 mt-1">Total completed POS sales</div>
        </div>

        <!-- COGS Card -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-xs">
          <div class="text-xs text-slate-500 uppercase font-bold mb-2">2. Cost of Goods Sold (COGS)</div>
          <div class="text-2xl font-black font-heading text-rose-700">
            -৳{{ formatMoney(cogs) }}
          </div>
          <div class="text-[11px] text-slate-500 mt-1">Direct item purchase cost</div>
        </div>

        <!-- Gross Profit Card -->
        <div class="p-6 rounded-3xl bg-indigo-50 border border-indigo-200 shadow-xs">
          <div class="text-xs text-indigo-900 uppercase font-bold mb-2">3. Gross Profit (Revenue - COGS)</div>
          <div class="text-2xl font-black font-heading text-indigo-800">
            ৳{{ formatMoney(grossProfit) }}
          </div>
          <div class="text-[11px] text-indigo-700 mt-1 font-bold">Margin: {{ getGrossMargin() }}%</div>
        </div>

        <!-- Net Profit Card -->
        <div class="p-6 rounded-3xl bg-emerald-50 border border-emerald-300 shadow-md">
          <div class="text-xs text-emerald-900 uppercase font-black mb-2">4. Net Operating Profit</div>
          <div class="text-3xl font-black font-heading text-emerald-800">
            ৳{{ formatMoney(netProfit) }}
          </div>
          <div class="text-[11px] text-emerald-700 mt-1 font-bold">Net profit after expenses</div>
        </div>
      </div>

      <!-- Accounting Breakdown Table -->
      <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-xs space-y-4">
        <h3 class="font-bold text-base font-heading text-slate-900">Financial Ledger Summary</h3>
        
        <div class="space-y-2 text-xs font-mono">
          <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 flex justify-between items-center text-slate-800">
            <span>(+) Gross Product Sales</span>
            <span class="font-bold text-emerald-700">৳{{ formatMoney(totalSales) }}</span>
          </div>

          <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 flex justify-between items-center text-slate-800">
            <span>(-) Cost of Goods Sold (Purchase Cost of Items Sold)</span>
            <span class="font-bold text-rose-700">-৳{{ formatMoney(cogs) }}</span>
          </div>

          <div class="p-3.5 rounded-2xl bg-indigo-50 border border-indigo-200 flex justify-between items-center text-indigo-900 font-bold">
            <span>(=) Gross Profit</span>
            <span class="text-indigo-800">৳{{ formatMoney(grossProfit) }}</span>
          </div>

          <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 flex justify-between items-center text-slate-800">
            <span>(-) Total Store Expenses (Rent, Utilities, Supplies)</span>
            <span class="font-bold text-rose-700">-৳{{ formatMoney(totalExpenses) }}</span>
          </div>

          <div class="p-4 rounded-2xl bg-emerald-100 border border-emerald-300 flex justify-between items-center text-emerald-950 font-black text-sm font-sans">
            <span>(=) Net Operating Profit</span>
            <span class="font-mono text-emerald-800 text-base">৳{{ formatMoney(netProfit) }}</span>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
  startDate: String,
  endDate: String,
  totalSales: Number,
  cogs: Number,
  grossProfit: Number,
  totalExpenses: Number,
  netProfit: Number,
});

const formatMoney = (val) => {
  return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const getGrossMargin = () => {
  if (!props.totalSales || props.totalSales === 0) return '0.00';
  return ((props.grossProfit / props.totalSales) * 100).toFixed(2);
};
</script>
