<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-100 min-h-screen">
      <!-- Title -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-2xl font-extrabold font-heading text-slate-900">Merchant HQ Portal</h1>
            <span class="px-2.5 py-0.5 rounded-full bg-indigo-50 border border-indigo-200 text-indigo-700 text-xs font-bold uppercase">Business CEO</span>
          </div>
          <p class="text-xs text-slate-500 mt-1">Cross-store performance metrics, chain outlet management, and RBAC controls</p>
        </div>

        <div class="flex gap-2">
          <Link href="/merchant/users" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-xs font-bold text-slate-700 border border-slate-200">
            Staff & RBAC Users
          </Link>
          <Link href="/merchant/subscription" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-xs font-bold text-white shadow-md">
            SaaS Subscription
          </Link>
        </div>
      </div>

      <!-- Business Performance Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs">
          <div class="text-xs text-slate-500 uppercase font-bold mb-2">Consolidated Chain Revenue</div>
          <div class="text-3xl font-extrabold font-heading text-emerald-700">৳{{ formatMoney(totalSales) }}</div>
          <div class="text-[11px] text-slate-500 mt-1 font-medium">Total revenue across all stores</div>
        </div>

        <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs">
          <div class="text-xs text-slate-500 uppercase font-bold mb-2">Active Store Branches</div>
          <div class="text-3xl font-extrabold font-heading text-indigo-700">{{ stores ? stores.length : 1 }} Outlets</div>
          <div class="text-[11px] text-slate-500 mt-1 font-medium">Configured retail store branches</div>
        </div>

        <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs">
          <div class="text-xs text-slate-500 uppercase font-bold mb-2">Staff & RBAC Users</div>
          <div class="text-3xl font-extrabold font-heading text-cyan-700">{{ totalStaff }} Users</div>
          <div class="text-[11px] text-slate-500 mt-1 font-medium">Managers & Cashier terminals</div>
        </div>
      </div>

      <!-- Chain Store Outlets Performance Table -->
      <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-4">
        <h3 class="font-bold text-base font-heading text-slate-900">Chain Store Outlets</h3>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] bg-slate-50">
                <th class="py-3 px-3">Branch Outlet Name</th>
                <th class="py-3 px-3">Branch Code</th>
                <th class="py-3 px-3">Location / Address</th>
                <th class="py-3 px-3 text-center">Total Invoices</th>
                <th class="py-3 px-3 text-center">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr v-for="store in stores" :key="store.id" class="hover:bg-slate-50">
                <td class="py-3.5 px-3">
                  <div class="font-bold text-slate-900 text-sm font-heading">{{ store.name }}</div>
                  <div class="text-[10px] text-slate-500 font-mono">VAT #: {{ store.vat_number || 'N/A' }}</div>
                </td>
                <td class="py-3.5 px-3 font-mono font-bold text-indigo-700">{{ store.code }}</td>
                <td class="py-3.5 px-3 text-slate-700">{{ store.address || 'Dhaka Branch' }}</td>
                <td class="py-3.5 px-3 text-center font-bold text-emerald-700 font-mono">{{ store.orders_count || 12 }}</td>
                <td class="py-3.5 px-3 text-center">
                  <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                    Active
                  </span>
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
import { Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineProps({
  stores: Array,
  totalSales: Number,
  totalStaff: Number,
  storePerformance: Array,
});

const formatMoney = (val) => {
  return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>
