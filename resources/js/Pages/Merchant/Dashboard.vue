<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-8 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen transition-colors">
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

      <!-- ⚠️ Summary Drift Discrepancy Alert Banner -->
      <div v-if="discrepancyAlerts && discrepancyAlerts.length > 0" class="space-y-3">
        <div v-for="alert in discrepancyAlerts" :key="alert.id" class="p-5 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border-2 border-amber-400 dark:border-amber-600 text-amber-900 dark:text-amber-200 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 shadow-md">
          <div class="flex items-start gap-3">
            <div class="p-2 rounded-xl bg-amber-500/20 text-amber-600 dark:text-amber-400 shrink-0 font-bold text-sm">
              ⚠️
            </div>
            <div>
              <h4 class="font-bold text-sm font-heading flex items-center gap-2">
                Daily Sales Summary Drift Detected
                <span class="text-xs px-2 py-0.5 rounded-full bg-amber-200 dark:bg-amber-900 text-amber-900 dark:text-amber-100 font-mono">Store #{{ alert.store_id }} | Date: {{ alert.date }}</span>
              </h4>
              <p class="text-xs mt-1 text-amber-800 dark:text-amber-300">
                A discrepancy was detected between live order aggregates and daily sales summaries.
                Expected grand total: ৳{{ alert.expected?.grand_total ?? 0 }} ({{ alert.expected?.count ?? 0 }} orders) vs Summary actual: ৳{{ alert.actual?.grand_total ?? 0 }} ({{ alert.actual?.count ?? 0 }} orders).
              </p>
            </div>
          </div>
          <button @click="recalculate(alert)" :disabled="recalculating === alert.id" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-extrabold text-xs shadow-md transition-all shrink-0 flex items-center gap-2 disabled:opacity-50">
            <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': recalculating === alert.id }" />
            <span>{{ recalculating === alert.id ? 'Recalculating...' : 'Fix & Recalculate' }}</span>
          </button>
        </div>
      </div>

      <!-- 📊 Business Performance Cards Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800/80 shadow-xs dark:shadow-xl dark:shadow-black/20 space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Consolidated Chain Revenue</span>
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30 flex items-center justify-center font-bold">
              <DollarSign class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-emerald-700 dark:text-emerald-400">৳{{ formatMoney(totalSales) }}</div>
          <div class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
            <TrendingUp class="w-3.5 h-3.5" />
            <span>Across all retail registers</span>
          </div>
        </div>

        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800/80 shadow-xs dark:shadow-xl dark:shadow-black/20 space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Active Store Outlets</span>
            <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/30 flex items-center justify-center font-bold">
              <Store class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-indigo-700 dark:text-indigo-400">{{ stores ? stores.length : 1 }} Outlets</div>
          <div class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400">Configured branch locations</div>
        </div>

        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800/80 shadow-xs dark:shadow-xl dark:shadow-black/20 space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Staff & RBAC Accounts</span>
            <div class="w-10 h-10 rounded-2xl bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-500/30 flex items-center justify-center font-bold">
              <Users class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-slate-900 dark:text-slate-100">{{ totalStaff }} Accounts</div>
          <div class="text-[11px] font-semibold text-rose-600 dark:text-rose-400">Managers & Cashier terminals</div>
        </div>
      </div>

      <!-- 🏬 Chain Store Outlets Performance Table -->
      <div class="p-6 sm:p-8 rounded-3xl bg-white dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800/80 shadow-xs dark:shadow-xl dark:shadow-black/20 space-y-6">
        <div class="flex items-center justify-between">
          <h3 class="font-black text-xl font-heading text-slate-900 dark:text-slate-100 flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
              <Store class="w-5 h-5" />
            </div>
            <span>Chain Store Outlets Overview</span>
          </h3>

          <Link href="/merchant/stores" class="text-xs font-extrabold text-indigo-600 dark:text-indigo-400 hover:underline">
            View All Branches &rarr;
          </Link>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200/80 dark:border-slate-800/80">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 uppercase text-[10px] bg-slate-50/80 dark:bg-slate-800/60 font-bold tracking-wider">
                <th class="py-4 px-4">Branch Outlet Name</th>
                <th class="py-4 px-4">Branch Code</th>
                <th class="py-4 px-4">Location / Address</th>
                <th class="py-4 px-4 text-center">Total Invoices</th>
                <th class="py-4 px-4 text-center">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-sans">
              <tr v-for="store in stores" :key="store.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                <td class="py-4 px-4">
                  <div class="font-bold text-slate-900 dark:text-slate-100 text-sm font-heading">{{ store.name }}</div>
                  <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">VAT #: {{ store.vat_number || 'Standard 5%' }}</div>
                </td>
                <td class="py-4 px-4 font-mono font-extrabold text-indigo-600 dark:text-indigo-400">{{ store.code }}</td>
                <td class="py-4 px-4 text-slate-700 dark:text-slate-300 font-medium">{{ store.address || 'Dhaka, Bangladesh' }}</td>
                <td class="py-4 px-4 text-center font-bold text-emerald-600 dark:text-emerald-400 font-mono text-sm">
                  <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                    {{ store.orders_count || 0 }} Orders
                  </span>
                </td>
                <td class="py-4 px-4 text-center">
                  <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30">
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
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Building2, Store, Zap, DollarSign, TrendingUp, Users, RefreshCw } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
  stores: Array,
  totalSales: Number,
  totalStaff: Number,
  storePerformance: Array,
  discrepancyAlerts: Array,
});

const recalculating = ref(null);

const recalculate = async (alert) => {
  recalculating.value = alert.id;
  try {
    await axios.post('/reports/daily-summary/recalculate', {
      store_id: alert.store_id,
      date: alert.date,
    });
    router.reload({ preserveScroll: true });
  } catch (err) {
    alert('Failed to recalculate: ' + (err.response?.data?.message || err.message));
  } finally {
    recalculating.value = null;
  }
};

const formatMoney = (val) => {
  return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>

