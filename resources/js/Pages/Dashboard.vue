<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-6">
      <!-- Title Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl sm:text-3xl font-black font-heading text-slate-900">
            ERP Business Overview
          </h1>
          <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">
            Real-time sales revenue, inventory alerts, and top items
          </p>
        </div>

        <Link 
          href="/pos" 
          class="px-5 py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-sm shadow-md flex items-center justify-center gap-2 transition-all active:scale-95"
        >
          <ShoppingCart class="w-4 h-4" />
          <span>Launch POS Register</span>
        </Link>
      </div>

      <!-- KPI Stat Cards Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- Today Sales Card -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-xs flex flex-col justify-between">
          <div class="flex items-center justify-between mb-4">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Today Sales</span>
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 flex items-center justify-center">
              <DollarSign class="w-5 h-5" />
            </div>
          </div>
          <div>
            <div class="text-2xl sm:text-3xl font-black font-heading text-emerald-700">
              ৳{{ formatMoney(todaySales) }}
            </div>
            <div class="text-[11px] text-slate-500 mt-1">Real-time store register total</div>
          </div>
        </div>

        <!-- Orders Count Card -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-xs flex flex-col justify-between">
          <div class="flex items-center justify-between mb-4">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Orders Completed</span>
            <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-200 text-indigo-700 flex items-center justify-center">
              <ShoppingBag class="w-5 h-5" />
            </div>
          </div>
          <div>
            <div class="text-2xl sm:text-3xl font-black font-heading text-indigo-700">
              {{ todayOrdersCount }} Invoices
            </div>
            <div class="text-[11px] text-slate-500 mt-1">Transactions today</div>
          </div>
        </div>

        <!-- Customers Card -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-xs flex flex-col justify-between">
          <div class="flex items-center justify-between mb-4">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Customers</span>
            <div class="w-10 h-10 rounded-2xl bg-cyan-50 border border-cyan-200 text-cyan-700 flex items-center justify-center">
              <Users class="w-5 h-5" />
            </div>
          </div>
          <div>
            <div class="text-2xl sm:text-3xl font-black font-heading text-cyan-700">
              {{ totalCustomers }} Active
            </div>
            <div class="text-[11px] text-slate-500 mt-1">Walk-in & Regular clients</div>
          </div>
        </div>

        <!-- Low Stock Alert Card -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-xs flex flex-col justify-between">
          <div class="flex items-center justify-between mb-4">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Low Stock Alert</span>
            <div class="w-10 h-10 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 flex items-center justify-center">
              <AlertTriangle class="w-5 h-5" />
            </div>
          </div>
          <div>
            <div class="text-2xl sm:text-3xl font-black font-heading text-rose-700">
              {{ lowStockCount }} Products
            </div>
            <div class="text-[11px] text-slate-500 mt-1">Requires stock re-order</div>
          </div>
        </div>
      </div>

      <!-- Recent Orders & Top Items Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Recent Orders (2 cols) -->
        <div class="lg:col-span-2 p-6 rounded-3xl bg-white border border-slate-200 shadow-xs flex flex-col">
          <div class="flex items-center justify-between mb-5">
            <h3 class="font-bold text-base font-heading text-slate-900 flex items-center gap-2">
              <Clock class="w-4 h-4 text-emerald-700" />
              <span>Recent POS Transactions</span>
            </h3>
            <Link href="/pos" class="text-xs text-emerald-700 hover:underline font-bold">
              New Sale +
            </Link>
          </div>

          <div class="overflow-x-auto flex-1">
            <table class="w-full text-left text-xs">
              <thead>
                <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] font-bold">
                  <th class="py-2.5 px-3">Invoice #</th>
                  <th class="py-2.5 px-3">Customer</th>
                  <th class="py-2.5 px-3">Method</th>
                  <th class="py-2.5 px-3 text-right">Amount</th>
                  <th class="py-2.5 px-3 text-center">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="order in recentOrders" :key="order.id" class="hover:bg-slate-50 transition-colors">
                  <td class="py-3 px-3 font-mono font-bold text-slate-900">{{ order.invoice_no }}</td>
                  <td class="py-3 px-3 text-slate-700">{{ order.customer?.name || 'Walk-in' }}</td>
                  <td class="py-3 px-3 uppercase text-slate-500 font-mono text-[11px]">{{ order.payment_method }}</td>
                  <td class="py-3 px-3 text-right font-extrabold text-emerald-700 font-mono">৳{{ formatMoney(order.grand_total) }}</td>
                  <td class="py-3 px-3 text-center">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                      {{ order.payment_status }}
                    </span>
                  </td>
                </tr>
                <tr v-if="!recentOrders || recentOrders.length === 0">
                  <td colspan="5" class="py-8 text-center text-slate-500">No transactions recorded today</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Top Selling Products (1 col) -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-xs flex flex-col">
          <h3 class="font-bold text-base font-heading text-slate-900 flex items-center gap-2 mb-5">
            <TrendingUp class="w-4 h-4 text-cyan-700" />
            <span>Top Performing Items</span>
          </h3>

          <div class="space-y-3.5 flex-1">
            <div 
              v-for="(item, idx) in topProducts" 
              :key="idx" 
              class="p-3 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-between"
            >
              <div>
                <div class="font-bold text-xs text-slate-900 font-heading">{{ item.product_name }}</div>
                <div class="text-[10px] text-slate-500 font-mono">Qty Sold: {{ item.total_qty }}</div>
              </div>
              <div class="text-right font-extrabold text-xs text-cyan-700 font-mono">
                ৳{{ formatMoney(item.total_sales) }}
              </div>
            </div>

            <div v-if="!topProducts || topProducts.length === 0" class="py-12 text-center text-slate-500 text-xs">
              Complete POS sales to generate top performance rankings
            </div>
          </div>
        </div>
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { 
  DollarSign, 
  ShoppingBag, 
  Users, 
  AlertTriangle, 
  Clock, 
  TrendingUp, 
  ShoppingCart 
} from 'lucide-vue-next';

defineProps({
  todaySales: Number,
  todayOrdersCount: Number,
  totalCustomers: Number,
  lowStockCount: Number,
  recentOrders: Array,
  topProducts: Array,
});

const formatMoney = (val) => {
  return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>
