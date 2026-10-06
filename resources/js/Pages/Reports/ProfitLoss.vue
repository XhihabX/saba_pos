<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-6">
      <!-- Title Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl sm:text-3xl font-black font-heading text-slate-900 dark:text-white">
            Profit & Loss Statement
          </h1>
          <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 font-medium mt-1">
            Automated Accounting with COGS, Gross Profit, and Net Profit
          </p>
        </div>

        <button 
          @click="handleExport" 
          class="px-5 py-2.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs flex items-center gap-2 shadow-md shadow-indigo-500/20 transition-all self-start sm:self-auto"
        >
          <Download class="w-4 h-4" />
          <span>Export Financial CSV</span>
        </button>
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

      <!-- Sales Breakdown Grids -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Category Sales Breakdown -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-xs space-y-4">
          <h3 class="font-bold text-base font-heading text-slate-900 flex items-center justify-between">
            <span>📦 Sales by Category</span>
            <span class="text-xs text-slate-500 font-normal">({{ salesByCategory?.length || 0 }} Categories)</span>
          </h3>
          <div class="space-y-2 text-xs">
            <div v-if="!salesByCategory || salesByCategory.length === 0" class="text-slate-400 py-4 text-center">
              No sales data recorded yet.
            </div>
            <div 
              v-for="item in salesByCategory" 
              :key="item.category" 
              class="p-3 rounded-2xl bg-slate-50 border border-slate-200 flex justify-between items-center"
            >
              <div>
                <div class="font-bold text-slate-800">{{ item.category }}</div>
                <div class="text-[10px] text-slate-500">{{ item.qty }} items sold</div>
              </div>
              <div class="font-bold text-slate-900 font-mono">৳{{ formatMoney(item.total) }}</div>
            </div>
          </div>
        </div>

        <!-- Payment Method Breakdown -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-xs space-y-4">
          <h3 class="font-bold text-base font-heading text-slate-900 flex items-center justify-between">
            <span>💳 Payment Channels</span>
            <span class="text-xs text-slate-500 font-normal">({{ salesByPayment?.length || 0 }} Methods)</span>
          </h3>
          <div class="space-y-2 text-xs">
            <div v-if="!salesByPayment || salesByPayment.length === 0" class="text-slate-400 py-4 text-center">
              No payment transactions yet.
            </div>
            <div 
              v-for="item in salesByPayment" 
              :key="item.method" 
              class="p-3 rounded-2xl bg-slate-50 border border-slate-200 flex justify-between items-center"
            >
              <div class="font-bold text-slate-800">{{ item.method }}</div>
              <div class="font-bold text-emerald-700 font-mono">৳{{ formatMoney(item.total) }}</div>
            </div>
          </div>
        </div>

        <!-- Cashier Performance -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-xs space-y-4">
          <h3 class="font-bold text-base font-heading text-slate-900 flex items-center justify-between">
            <span>👤 Cashier Staff Sales</span>
            <span class="text-xs text-slate-500 font-normal">({{ salesByCashier?.length || 0 }} Cashiers)</span>
          </h3>
          <div class="space-y-2 text-xs">
            <div v-if="!salesByCashier || salesByCashier.length === 0" class="text-slate-400 py-4 text-center">
              No cashier sales recorded yet.
            </div>
            <div 
              v-for="item in salesByCashier" 
              :key="item.cashier" 
              class="p-3 rounded-2xl bg-slate-50 border border-slate-200 flex justify-between items-center"
            >
              <div>
                <div class="font-bold text-slate-800">{{ item.cashier }}</div>
                <div class="text-[10px] text-slate-500">{{ item.order_count }} orders processed</div>
              </div>
              <div class="font-bold text-indigo-700 font-mono">৳{{ formatMoney(item.total_sales) }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Download } from 'lucide-vue-next';
import { exportToCSV } from '@/Utils/csvExport';

const props = defineProps({
  startDate: String,
  endDate: String,
  totalSales: Number,
  cogs: Number,
  grossProfit: Number,
  totalExpenses: Number,
  netProfit: Number,
  salesByCategory: Array,
  salesByPayment: Array,
  salesByCashier: Array,
});

const formatMoney = (val) => {
  return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const getGrossMargin = () => {
  if (!props.totalSales || props.totalSales === 0) return '0.00';
  return ((props.grossProfit / props.totalSales) * 100).toFixed(2);
};

const handleExport = () => {
  const financialSummary = [
    { metric: 'Total Gross Revenue', amount: props.totalSales || 0 },
    { metric: 'Cost of Goods Sold (COGS)', amount: props.cogs || 0 },
    { metric: 'Gross Profit', amount: props.grossProfit || 0 },
    { metric: 'Total Store Expenses', amount: props.totalExpenses || 0 },
    { metric: 'Net Operating Profit', amount: props.netProfit || 0 },
  ];
  const columns = [
    { label: 'Financial Metric', key: 'metric' },
    { label: 'Amount (৳)', key: 'amount' },
  ];
  exportToCSV('profit_loss_financial_statement', columns, financialSummary);
};
</script>
