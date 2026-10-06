<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-8 bg-slate-50 dark:bg-slate-950 min-h-screen text-slate-900 dark:text-slate-100 transition-colors duration-200">
      
      <!-- 📜 Printable Z-Report Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-purple-950 to-slate-900 text-white p-6 sm:p-8 shadow-xl border border-slate-800">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-purple-500/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div class="space-y-2">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-purple-500/10 border border-purple-500/20 text-purple-400 font-bold text-xs uppercase tracking-wider">
              <Printer class="w-3.5 h-3.5" />
              <span>Automated Day-End (Z) Report</span>
            </div>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black font-heading tracking-tight text-white">
              Daily Register Shift Z-Report
            </h1>
            <p class="text-xs sm:text-sm text-slate-300 max-w-2xl">
              Official shift closure audit summary for store managers, cash drawer reconciliation, and till over/short tracking.
            </p>
          </div>

          <button 
            @click="window.print()" 
            class="px-5 py-3 rounded-2xl bg-purple-600 hover:bg-purple-500 text-white font-extrabold text-xs shadow-lg shadow-purple-600/25 flex items-center gap-2 transition-all self-start md:self-auto whitespace-nowrap"
          >
            <Printer class="w-4 h-4" />
            <span>Print Z-Report</span>
          </button>
        </div>
      </div>

      <!-- 📑 Printable Z-Report Slip Container -->
      <div class="max-w-xl mx-auto p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl space-y-6 font-mono text-xs">
        <div class="text-center border-b border-dashed border-slate-300 dark:border-slate-700 pb-4 space-y-1">
          <h2 class="text-lg font-black font-sans uppercase tracking-widest text-slate-900 dark:text-white">{{ zData.shift?.store?.name || 'IOT POS STORE' }}</h2>
          <p class="text-[11px] text-slate-500">OFFICIAL DAY-END Z-REPORT</p>
          <p class="text-[10px] text-slate-400">Shift ID: #{{ zData.shift?.id }} | Cashier: {{ zData.shift?.user?.name || 'Cashier' }}</p>
          <p class="text-[10px] text-slate-400">Opened: {{ zData.openedAt }} | Closed: {{ zData.closedAt || 'Active' }}</p>
        </div>

        <!-- Sales Metrics -->
        <div class="space-y-2 border-b border-dashed border-slate-300 dark:border-slate-700 pb-4">
          <div class="flex justify-between font-bold">
            <span>TOTAL SALES INVOICES:</span>
            <span>{{ zData.totalOrders }}</span>
          </div>
          <div class="flex justify-between">
            <span>GROSS PRODUCT SUBTOTAL:</span>
            <span>৳{{ zData.subtotal.toFixed(2) }}</span>
          </div>
          <div class="flex justify-between text-rose-600 dark:text-rose-400">
            <span>(-) DISCOUNTS APPLIED:</span>
            <span>-৳{{ zData.discountTotal.toFixed(2) }}</span>
          </div>
          <div class="flex justify-between text-emerald-600 dark:text-emerald-400">
            <span>(+) VAT / TAX COLLECTED:</span>
            <span>+৳{{ zData.taxTotal.toFixed(2) }}</span>
          </div>
          <div class="flex justify-between font-extrabold text-sm pt-2 border-t border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white">
            <span>GRAND TOTAL SALES:</span>
            <span>৳{{ zData.grandTotal.toFixed(2) }}</span>
          </div>
        </div>

        <!-- Tender Breakdown -->
        <div class="space-y-2 border-b border-dashed border-slate-300 dark:border-slate-700 pb-4">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Tender Channel Breakdown</div>
          <div class="flex justify-between">
            <span>CASH SALES:</span>
            <span>৳{{ zData.cashSales.toFixed(2) }}</span>
          </div>
          <div class="flex justify-between">
            <span>CARD SALES:</span>
            <span>৳{{ zData.cardSales.toFixed(2) }}</span>
          </div>
          <div class="flex justify-between">
            <span>MOBILE WALLET (MFS):</span>
            <span>৳{{ zData.mobileSales.toFixed(2) }}</span>
          </div>
        </div>

        <!-- Cash Drawer Reconciliation -->
        <div class="space-y-2">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Till Cash Reconciliation</div>
          <div class="flex justify-between">
            <span>OPENING FLOAT BALANCE:</span>
            <span>৳{{ (parseFloat(zData.shift?.opening_cash) || 0).toFixed(2) }}</span>
          </div>
          <div class="flex justify-between font-bold">
            <span>EXPECTED CASH IN DRAWER:</span>
            <span>৳{{ zData.expectedCash.toFixed(2) }}</span>
          </div>
          <div class="flex justify-between font-bold text-indigo-600 dark:text-indigo-400">
            <span>COUNTED PHYSICAL CASH:</span>
            <span>৳{{ zData.actualCash.toFixed(2) }}</span>
          </div>
          <div class="flex justify-between font-black text-sm pt-2 border-t border-slate-200 dark:border-slate-800" :class="zData.variance < 0 ? 'text-rose-600' : (zData.variance > 0 ? 'text-amber-500' : 'text-emerald-600')">
            <span>CASH OVER / SHORT VARIANCE:</span>
            <span>{{ zData.variance >= 0 ? '+' : '' }}৳{{ zData.variance.toFixed(2) }}</span>
          </div>
        </div>

        <div class="text-center pt-4 text-[10px] text-slate-400 border-t border-slate-200 dark:border-slate-800">
          *** END OF Z-REPORT ***
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Printer } from 'lucide-vue-next';

const props = defineProps({
  zData: Object,
});
</script>
