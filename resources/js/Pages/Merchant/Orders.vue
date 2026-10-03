<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-50 dark:bg-slate-950 min-h-screen text-slate-900 dark:text-slate-100 selection:bg-indigo-500 selection:text-white transition-colors duration-200">
      <!-- Dark Hero Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 border border-indigo-500/20 p-6 sm:p-8 shadow-2xl">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 font-mono text-xs font-bold uppercase tracking-wider mb-3">
              <ShoppingBag class="w-4 h-4" />
              <span>Consolidated Retail Ledger</span>
            </div>
            <h1 class="text-3xl font-black font-heading tracking-tight text-white">Sales Orders & Thermal Invoices</h1>
            <p class="text-slate-400 text-xs sm:text-sm mt-1 max-w-2xl">
              Inspect real-time sales transactions, customer receipts, payment methods (Cash, Card, Mobile Banking), and payment status across all outlets.
            </p>
          </div>
        </div>
      </div>

      <!-- Summary Stats Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 sm:gap-6">
        <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Gross Sales Revenue</span>
            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
              <DollarSign class="w-5 h-5" />
            </div>
          </div>
          <div class="text-2xl font-black text-slate-900 dark:text-white mt-2 font-heading">৳{{ (stats.total_revenue || 0).toLocaleString() }}</div>
        </div>

        <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Sales Invoices</span>
            <div class="w-9 h-9 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
              <Receipt class="w-5 h-5" />
            </div>
          </div>
          <div class="text-2xl font-black text-slate-900 dark:text-white mt-2 font-heading">{{ (stats.total_orders || 0).toLocaleString() }}</div>
        </div>

        <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Paid Receipts</span>
            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
              <CheckCircle2 class="w-5 h-5" />
            </div>
          </div>
          <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-2 font-heading">{{ (stats.paid_orders || 0).toLocaleString() }}</div>
        </div>

        <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Unpaid / Credit Due</span>
            <div class="w-9 h-9 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center">
              <Clock class="w-5 h-5" />
            </div>
          </div>
          <div class="text-2xl font-black text-rose-600 dark:text-rose-400 mt-2 font-heading">{{ (stats.due_orders || 0).toLocaleString() }}</div>
        </div>
      </div>

      <!-- Main Orders Table -->
      <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xl">
        <!-- Search & Filter Controls -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
            <div class="relative w-full sm:w-72">
              <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input v-model="searchQuery" type="text" placeholder="Search invoice # or customer name..." class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl text-xs text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-indigo-500 transition-colors" />
            </div>

            <select v-model="statusFilter" class="px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500">
              <option value="ALL">All Payment Statuses</option>
              <option value="PAID">PAID Invoices</option>
              <option value="DUE">DUE / Credit Invoices</option>
              <option value="PARTIAL">PARTIAL Payment Invoices</option>
            </select>
          </div>

          <div class="text-xs text-slate-500 dark:text-slate-400 font-semibold">
            Showing {{ filteredOrders.length }} orders
          </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="border-b border-slate-100 dark:border-slate-800 text-slate-400 font-bold uppercase tracking-wider">
                <th class="pb-3 px-3">Invoice #</th>
                <th class="pb-3 px-3">Store Branch</th>
                <th class="pb-3 px-3">Customer</th>
                <th class="pb-3 px-3">Payment Method</th>
                <th class="pb-3 px-3">Status</th>
                <th class="pb-3 px-3">Grand Total</th>
                <th class="pb-3 px-3">Cashier</th>
                <th class="pb-3 px-3 text-right">Receipt Preview</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <tr v-for="order in filteredOrders" :key="order.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors">
                <td class="py-3.5 px-3 font-mono font-black text-indigo-600 dark:text-indigo-400">{{ order.invoice_number || ('INV-' + order.id) }}</td>
                <td class="py-3.5 px-3 font-semibold text-slate-900 dark:text-slate-100">{{ order.store?.name || 'Main Branch' }}</td>
                <td class="py-3.5 px-3 text-slate-700 dark:text-slate-300 font-medium">{{ order.customer?.name || 'Walk-in Customer' }}</td>
                <td class="py-3.5 px-3">
                  <span class="px-2.5 py-1 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-mono text-[10px] uppercase font-bold">
                    {{ order.payment_method || 'CASH' }}
                  </span>
                </td>
                <td class="py-3.5 px-3">
                  <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider inline-flex items-center gap-1"
                    :class="{
                      'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30': (order.payment_status || 'PAID') === 'PAID',
                      'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30': (order.payment_status || '').toUpperCase() === 'DUE',
                      'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30': (order.payment_status || '').toUpperCase() === 'PARTIAL'
                    }">
                    ● {{ (order.payment_status || 'PAID').toUpperCase() }}
                  </span>
                </td>
                <td class="py-3.5 px-3 font-black font-mono text-slate-900 dark:text-slate-100">৳{{ (order.total_amount || 0).toLocaleString() }}</td>
                <td class="py-3.5 px-3 text-slate-600 dark:text-slate-400">{{ order.cashier?.name || 'Store Cashier' }}</td>
                <td class="py-3.5 px-3 text-right">
                  <button @click="openReceiptModal(order)" class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-indigo-500/10 hover:text-indigo-600 dark:hover:text-indigo-400 text-slate-700 dark:text-slate-300 font-semibold transition-colors inline-flex items-center gap-1.5">
                    <Printer class="w-3.5 h-3.5 text-indigo-500" />
                    <span>View Receipt</span>
                  </button>
                </td>
              </tr>
              <tr v-if="filteredOrders.length === 0">
                <td colspan="8" class="py-8 text-center text-slate-400 text-xs">
                  No sales orders found matching your search.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Thermal Receipt Modal Preview -->
      <div v-if="selectedOrder" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 max-w-sm w-full shadow-2xl space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <h3 class="text-sm font-black text-slate-900 dark:text-white font-mono flex items-center gap-2">
              <Printer class="w-4 h-4 text-indigo-500" />
              <span>Thermal Receipt {{ selectedOrder.invoice_number || ('INV-' + selectedOrder.id) }}</span>
            </h3>
            <button @click="selectedOrder = null" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-xl font-bold">&times;</button>
          </div>

          <!-- Printable Thermal Paper Simulation -->
          <div class="bg-white text-slate-900 p-4 rounded-xl border border-slate-300 font-mono text-[11px] leading-tight space-y-3 shadow-inner">
            <div class="text-center space-y-1 pb-2 border-b border-dashed border-slate-300">
              <div class="text-sm font-black uppercase">{{ selectedOrder.store?.name || 'SABA RETAIL STORE' }}</div>
              <div class="text-[9px] text-slate-600">POS Sales Terminal Receipt</div>
              <div class="text-[9px] text-slate-500">Date: {{ new Date(selectedOrder.created_at || Date.now()).toLocaleString() }}</div>
            </div>

            <div class="flex justify-between text-[10px]">
              <span>Customer:</span>
              <span class="font-bold">{{ selectedOrder.customer?.name || 'Walk-in Customer' }}</span>
            </div>
            <div class="flex justify-between text-[10px]">
              <span>Payment Mode:</span>
              <span class="font-bold">{{ selectedOrder.payment_method || 'CASH' }}</span>
            </div>

            <!-- Items -->
            <div class="py-2 border-y border-dashed border-slate-300 space-y-1.5">
              <div v-for="(item, idx) in (selectedOrder.items || [{ name: 'Retail Store Item', qty: 1, price: selectedOrder.total_amount || 0 }])" :key="idx" class="flex justify-between items-center">
                <div>
                  <div class="font-bold text-slate-900">{{ item.name || 'POS Product Item' }}</div>
                  <div class="text-[9px] text-slate-500">{{ item.qty || 1 }} x ৳{{ (item.price || 0).toLocaleString() }}</div>
                </div>
                <div class="font-bold">৳{{ ((item.qty || 1) * (item.price || 0)).toLocaleString() }}</div>
              </div>
            </div>

            <!-- Summary Totals -->
            <div class="space-y-1 text-right pt-1">
              <div class="flex justify-between">
                <span>Subtotal:</span>
                <span>৳{{ (selectedOrder.subtotal || selectedOrder.total_amount || 0).toLocaleString() }}</span>
              </div>
              <div class="flex justify-between">
                <span>Vat / Tax:</span>
                <span>৳{{ (selectedOrder.tax_amount || 0).toLocaleString() }}</span>
              </div>
              <div class="flex justify-between text-xs font-black pt-1 border-t border-slate-300 text-slate-900">
                <span>GRAND TOTAL:</span>
                <span>৳{{ (selectedOrder.total_amount || 0).toLocaleString() }}</span>
              </div>
            </div>

            <div class="text-center pt-2 text-[9px] text-slate-500 border-t border-dashed border-slate-300">
              Thank you for shopping with us!<br/>
              Powered by Saba POS System
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button @click="selectedOrder = null" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-semibold">Close</button>
            <button @click="window.print()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow">Print Receipt</button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ShoppingBag, DollarSign, Receipt, CheckCircle2, Clock, Search, Printer } from 'lucide-vue-next';

const props = defineProps({
  orders: { type: Array, default: () => [] },
  stats: { type: Object, default: () => ({ total_revenue: 0, total_orders: 0, paid_orders: 0, due_orders: 0 }) },
});

const searchQuery = ref('');
const statusFilter = ref('ALL');
const selectedOrder = ref(null);

const filteredOrders = computed(() => {
  let list = props.orders;
  if (statusFilter.value !== 'ALL') {
    list = list.filter(o => (o.payment_status || 'PAID').toUpperCase() === statusFilter.value);
  }
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(o => 
      (o.invoice_number && o.invoice_number.toLowerCase().includes(q)) ||
      (o.customer?.name && o.customer.name.toLowerCase().includes(q)) ||
      (o.store?.name && o.store.name.toLowerCase().includes(q))
    );
  }
  return list;
});

const openReceiptModal = (order) => {
  selectedOrder.value = order;
};
</script>
