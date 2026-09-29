<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-100 min-h-screen">
      <!-- Title & Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div class="flex items-center gap-2 text-rose-600 font-bold text-xs uppercase tracking-wider">
            <Crown class="w-4 h-4" />
            <span>SaaS Platform Intelligence</span>
          </div>
          <h1 class="text-2xl font-black font-heading text-slate-900 mt-1">Platform Analytics & SaaS Growth</h1>
          <p class="text-xs text-slate-500 mt-1">Gross Merchandise Value (GMV), subscriber tier distribution, regional store expansion, and platform revenue metrics</p>
        </div>
      </div>

      <!-- Financial GMV Breakdown Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs flex flex-col justify-between">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Annual Run-Rate (ARR)</span>
            <TrendingUp class="w-5 h-5 text-emerald-600" />
          </div>
          <div>
            <div class="text-3xl font-extrabold font-heading text-emerald-700">৳{{ formatMoney(totalMrr * 12) }}</div>
            <div class="text-[11px] text-emerald-700 font-semibold mt-1">Projected 12-month recurring revenue</div>
          </div>
        </div>

        <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs flex flex-col justify-between">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Platform GMV</span>
            <DollarSign class="w-5 h-5 text-indigo-600" />
          </div>
          <div>
            <div class="text-3xl font-extrabold font-heading text-slate-900">৳{{ formatMoney(totalPlatformSales) }}</div>
            <div class="text-[11px] text-slate-500 font-medium mt-1">Processed across all store registers</div>
          </div>
        </div>

        <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs flex flex-col justify-between">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Avg MRR per Merchant (ARPU)</span>
            <Users class="w-5 h-5 text-rose-600" />
          </div>
          <div>
            <div class="text-3xl font-extrabold font-heading text-rose-700">৳{{ formatMoney(activeTenantsCount > 0 ? totalMrr / activeTenantsCount : 0) }}</div>
            <div class="text-[11px] text-slate-500 font-medium mt-1">Per active subscribed business</div>
          </div>
        </div>

        <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs flex flex-col justify-between">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Store Branches</span>
            <Store class="w-5 h-5 text-amber-600" />
          </div>
          <div>
            <div class="text-3xl font-extrabold font-heading text-amber-700">{{ totalStores }} Active Stores</div>
            <div class="text-[11px] text-slate-500 font-medium mt-1">Across {{ activeTenantsCount }} merchant chains</div>
          </div>
        </div>
      </div>

      <!-- Plan Tier Distribution & Top Stores Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Subscriber Plan Breakdown (5 cols) -->
        <div class="lg:col-span-5 p-6 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-4">
          <h3 class="font-extrabold text-base font-heading text-slate-900 flex items-center gap-2">
            <PieChart class="w-5 h-5 text-rose-600" />
            <span>SaaS Plan Tier Distribution</span>
          </h3>

          <div class="space-y-3">
            <div v-for="plan in planDistribution" :key="plan.name" class="p-4 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
              <div>
                <span class="font-extrabold text-slate-900 text-sm block">{{ plan.name }}</span>
                <span class="text-xs text-slate-500 font-mono">৳{{ formatMoney(plan.monthly_price) }}/mo</span>
              </div>
              <div class="text-right">
                <span class="text-lg font-black text-indigo-700 block font-heading">{{ plan.count }} Businesses</span>
                <span class="text-xs font-extrabold text-emerald-700 font-mono">৳{{ formatMoney(plan.count * plan.monthly_price) }} MRR</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Top Merchant Chains by GMV (7 cols) -->
        <div class="lg:col-span-7 p-6 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-4">
          <h3 class="font-extrabold text-base font-heading text-slate-900 flex items-center gap-2">
            <Building2 class="w-5 h-5 text-indigo-600" />
            <span>Top Performing Merchant Chains</span>
          </h3>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead>
                <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] bg-slate-50">
                  <th class="py-3 px-3">Merchant Business</th>
                  <th class="py-3 px-3 text-center">Stores</th>
                  <th class="py-3 px-3 text-center">Plan Tier</th>
                  <th class="py-3 px-3 text-right">Processed Sales</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200">
                <tr v-for="t in topTenants" :key="t.id" class="hover:bg-slate-50">
                  <td class="py-3 px-3">
                    <span class="font-bold text-slate-900 font-heading block">{{ t.name }}</span>
                    <span class="text-[10px] text-slate-500 font-mono">{{ t.code }}</span>
                  </td>
                  <td class="py-3 px-3 text-center font-bold text-slate-700">{{ t.stores_count || 1 }}</td>
                  <td class="py-3 px-3 text-center font-bold text-indigo-700">{{ t.plan_name }}</td>
                  <td class="py-3 px-3 text-right font-mono font-bold text-emerald-700">৳{{ formatMoney(t.total_sales || 0) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Crown, TrendingUp, DollarSign, Users, Store, PieChart, Building2 } from 'lucide-vue-next';

defineProps({
  totalMrr: Number,
  totalPlatformSales: Number,
  activeTenantsCount: Number,
  totalStores: Number,
  planDistribution: Array,
  topTenants: Array,
});

const formatMoney = (val) => {
  return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>
