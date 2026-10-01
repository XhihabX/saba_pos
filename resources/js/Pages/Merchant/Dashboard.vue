<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-8 bg-slate-50/70 min-h-screen">
      <!-- 🏢 Hero Header Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-6 sm:p-8 shadow-xl border border-slate-800">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div class="space-y-2">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 font-bold text-xs uppercase tracking-wider">
              <Building2 class="w-3.5 h-3.5" />
              <span>Merchant Executive HQ Portal</span>
            </div>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black font-heading tracking-tight text-white">
              Business Intelligence & Chain Operations
            </h1>
            <p class="text-xs sm:text-sm text-slate-300 max-w-2xl">
              Consolidated multi-store revenue, profit margins after COGS, staff access controls, and chain growth metrics
            </p>
          </div>

          <div class="flex items-center gap-3 shrink-0">
            <Link href="/merchant/stores" class="px-4 py-2.5 rounded-2xl bg-white/10 hover:bg-white/20 text-white font-extrabold text-xs border border-white/20 transition-all flex items-center gap-2">
              <Store class="w-4 h-4" />
              <span>Manage Outlets</span>
            </Link>
            <Link href="/merchant/subscription" class="px-5 py-2.5 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition-all">
              <Zap class="w-4 h-4" />
              <span>SaaS Subscription</span>
            </Link>
          </div>
        </div>
      </div>

      <!-- 📊 Business Performance Cards Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Consolidated Chain Revenue</span>
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
              <DollarSign class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-emerald-700">৳{{ formatMoney(totalSales) }}</div>
          <div class="text-[11px] font-semibold text-emerald-600 flex items-center gap-1">
            <TrendingUp class="w-3.5 h-3.5" />
            <span>Across all retail registers</span>
          </div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Active Store Outlets</span>
            <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
              <Store class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-indigo-700">{{ stores ? stores.length : 1 }} Outlets</div>
          <div class="text-[11px] font-semibold text-indigo-600">Configured branch locations</div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Staff & RBAC Accounts</span>
            <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold">
              <Users class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-slate-900">{{ totalStaff }} Accounts</div>
          <div class="text-[11px] font-semibold text-rose-600">Managers & Cashier terminals</div>
        </div>
      </div>

      <!-- 🏬 Chain Store Outlets Performance Table -->
      <div class="p-6 sm:p-8 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-6">
        <div class="flex items-center justify-between">
          <h3 class="font-black text-xl font-heading text-slate-900 flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
              <Store class="w-5 h-5" />
            </div>
            <span>Chain Store Outlets Overview</span>
          </h3>

          <Link href="/merchant/stores" class="text-xs font-extrabold text-indigo-600 hover:text-indigo-800">
            View All Branches &rarr;
          </Link>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200/80">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] bg-slate-50/80 font-bold tracking-wider">
                <th class="py-4 px-4">Branch Outlet Name</th>
                <th class="py-4 px-4">Branch Code</th>
                <th class="py-4 px-4">Location / Address</th>
                <th class="py-4 px-4 text-center">Total Invoices</th>
                <th class="py-4 px-4 text-center">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-sans">
              <tr v-for="store in stores" :key="store.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="py-4 px-4">
                  <div class="font-bold text-slate-900 text-sm font-heading">{{ store.name }}</div>
                  <div class="text-[10px] text-slate-500 font-mono">VAT #: {{ store.vat_number || 'Standard 5%' }}</div>
                </td>
                <td class="py-4 px-4 font-mono font-extrabold text-indigo-700">{{ store.code }}</td>
                <td class="py-4 px-4 text-slate-700 font-medium">{{ store.address || 'Dhaka, Bangladesh' }}</td>
                <td class="py-4 px-4 text-center font-bold text-emerald-700 font-mono text-sm">
                  <span class="px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200">
                    {{ store.orders_count || 0 }} Orders
                  </span>
                </td>
                <td class="py-4 px-4 text-center">
                  <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                    Active Branch
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
import { Building2, Store, Zap, DollarSign, TrendingUp, Users } from 'lucide-vue-next';

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

