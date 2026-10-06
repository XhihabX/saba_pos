<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-8 bg-slate-50 dark:bg-slate-950 min-h-screen text-slate-900 dark:text-slate-100 transition-colors duration-200">
      
      <!-- 🇧🇩 Hero Header Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-emerald-950 to-slate-900 text-white p-6 sm:p-8 shadow-xl border border-slate-800">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-emerald-500/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div class="space-y-2">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 font-bold text-xs uppercase tracking-wider">
              <FileText class="w-3.5 h-3.5" />
              <span>NBR Statutory Compliance (Mushak 6.3)</span>
            </div>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black font-heading tracking-tight text-white">
              National Board of Revenue (NBR) VAT Report
            </h1>
            <p class="text-xs sm:text-sm text-slate-300 max-w-2xl">
              Itemized sales tax summary, store Business Identification Numbers (BIN), and Mushak-6.3 VAT breakdown for tax audits.
            </p>
          </div>

          <button 
            @click="handleExport" 
            class="px-5 py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs shadow-lg shadow-emerald-600/25 flex items-center gap-2 transition-all self-start md:self-auto whitespace-nowrap"
          >
            <Download class="w-4 h-4" />
            <span>Export NBR Mushak CSV</span>
          </button>
        </div>
      </div>

      <!-- 📊 Statutory VAT Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Gross Sales (Taxable)</span>
            <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
              <DollarSign class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-slate-900 dark:text-white">৳{{ totalSubtotal.toLocaleString() }}</div>
          <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Net taxable turnover</div>
        </div>

        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Output VAT Collected</span>
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
              <Receipt class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-emerald-600 dark:text-emerald-400">৳{{ totalVat.toLocaleString() }}</div>
          <div class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">Total NBR tax payable</div>
        </div>

        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Gross Sales Total</span>
            <div class="w-10 h-10 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
              <ShoppingCart class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-amber-600 dark:text-amber-400">৳{{ totalSales.toLocaleString() }}</div>
          <div class="text-[11px] font-semibold text-amber-600 dark:text-amber-400">Combined gross revenue</div>
        </div>
      </div>

      <!-- 📋 Branch Outlet BIN Breakdown -->
      <div class="p-6 sm:p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-6">
        <div>
          <h3 class="font-black text-xl font-heading text-slate-900 dark:text-white">Store Branch BIN & VAT Breakdown</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400">Registered Business Identification Numbers (BIN) & tax collection stats</p>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200/80 dark:border-slate-800">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 uppercase text-[10px] bg-slate-50/80 dark:bg-slate-950/60 font-bold tracking-wider">
                <th class="py-4 px-4">Store Outlet</th>
                <th class="py-4 px-4">NBR BIN Number</th>
                <th class="py-4 px-4">Invoices Issued</th>
                <th class="py-4 px-4">Net Sales (৳)</th>
                <th class="py-4 px-4">VAT Collected (৳)</th>
                <th class="py-4 px-4">Gross Revenue (৳)</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-sans">
              <tr v-for="item in vatByStore" :key="item.store_name" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                <td class="py-4 px-4 font-bold text-slate-900 dark:text-slate-100 text-sm whitespace-nowrap">
                  {{ item.store_name }}
                </td>
                <td class="py-4 px-4 font-mono text-emerald-600 dark:text-emerald-400 font-extrabold whitespace-nowrap">
                  {{ item.bin_number }}
                </td>
                <td class="py-4 px-4 font-bold text-slate-700 dark:text-slate-300">
                  {{ item.order_count }} orders
                </td>
                <td class="py-4 px-4 font-mono font-bold text-slate-900 dark:text-slate-100">
                  ৳{{ item.net_amount.toLocaleString() }}
                </td>
                <td class="py-4 px-4 font-mono font-black text-emerald-600 dark:text-emerald-400">
                  ৳{{ item.vat_collected.toLocaleString() }}
                </td>
                <td class="py-4 px-4 font-mono font-extrabold text-indigo-600 dark:text-indigo-400">
                  ৳{{ item.gross_total.toLocaleString() }}
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
import { FileText, DollarSign, ShoppingCart, Download, Receipt } from 'lucide-vue-next';
import { exportToCSV } from '@/Utils/csvExport';

const props = defineProps({
  startDate: String,
  endDate: String,
  totalSales: Number,
  totalSubtotal: Number,
  totalVat: Number,
  vatByStore: Array,
  orders: Array,
});

const handleExport = () => {
  const columns = [
    { label: 'Store Name', key: 'store_name' },
    { label: 'BIN Number', key: 'bin_number' },
    { label: 'Invoices Count', key: 'order_count' },
    { label: 'Net Sales (৳)', key: 'net_amount' },
    { label: 'VAT Collected (৳)', key: 'vat_collected' },
    { label: 'Gross Total (৳)', key: 'gross_total' },
  ];
  exportToCSV('nbr_mushak_63_vat_report', columns, props.vatByStore);
};
</script>
