<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-8 bg-slate-50/70 min-h-screen">
      <!-- 📈 Hero Header Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-rose-950 to-slate-900 text-white p-6 sm:p-8 shadow-xl border border-slate-800">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-rose-500/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 space-y-2">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 font-bold text-xs uppercase tracking-wider">
            <BarChart3 class="w-3.5 h-3.5" />
            <span>SaaS Financial & Growth Intelligence</span>
          </div>
          <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black font-heading tracking-tight text-white">
            Platform Analytics & SaaS Metrics
          </h1>
          <p class="text-xs sm:text-sm text-slate-300 max-w-2xl">
            Gross Merchandise Value (GMV), subscriber plan breakdown, ARPU metrics, and merchant performance leaderboards
          </p>
        </div>
      </div>

      <!-- 📊 KPI Cards Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Annual Run-Rate (ARR)</span>
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
              <TrendingUp class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-emerald-700">৳{{ formatMoney(totalMrr * 12) }}</div>
          <div class="text-[11px] font-semibold text-emerald-600">Projected 12-month recurring revenue</div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Platform Gross GMV</span>
            <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
              <DollarSign class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-slate-900">৳{{ formatMoney(totalPlatformSales) }}</div>
          <div class="text-[11px] font-semibold text-slate-500">Processed across all store registers</div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Avg MRR per Merchant (ARPU)</span>
            <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold">
              <Users class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-rose-700">৳{{ formatMoney(activeTenantsCount > 0 ? totalMrr / activeTenantsCount : 0) }}</div>
          <div class="text-[11px] font-semibold text-rose-600">Per active subscribed business</div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Active Outlets</span>
            <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
              <Store class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-slate-900">{{ totalStores }} Outlets</div>
          <div class="text-[11px] font-semibold text-amber-600">Across {{ activeTenantsCount }} merchant chains</div>
        </div>
      </div>

      <!-- 🍰 Plan Tier Distribution & Top Stores Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Subscriber Plan Breakdown (5 cols) -->
        <div class="lg:col-span-5 p-6 sm:p-8 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-6">
          <h3 class="font-black text-xl font-heading text-slate-900 flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center">
              <PieChart class="w-5 h-5" />
            </div>
            <span>SaaS Plan Tier Breakdown</span>
          </h3>

          <div class="space-y-4">
            <div v-for="plan in planDistribution" :key="plan.name" class="p-5 rounded-2xl bg-slate-50/80 border border-slate-200 space-y-2">
              <div class="flex items-center justify-between">
                <div>
                  <span class="font-extrabold text-slate-900 text-sm block">{{ plan.name }}</span>
                  <span class="text-xs text-slate-500 font-mono">৳{{ formatMoney(plan.monthly_price) }}/mo</span>
                </div>
                <div class="text-right">
                  <span class="text-lg font-black text-indigo-700 block font-heading">{{ plan.count }} Businesses</span>
                  <span class="text-xs font-extrabold text-emerald-700 font-mono">৳{{ formatMoney(plan.count * plan.monthly_price) }} MRR</span>
                </div>
              </div>
              
              <!-- Progress Bar -->
              <div class="w-full h-2 rounded-full bg-slate-200 overflow-hidden">
                <div 
                  class="h-full bg-gradient-to-r from-rose-500 to-amber-500 rounded-full" 
                  :style="{ width: `${activeTenantsCount > 0 ? (plan.count / activeTenantsCount) * 100 : 0}%` }"
                ></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Top Merchant Chains by GMV (7 cols) -->
        <div class="lg:col-span-7 p-6 sm:p-8 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-6">
          <h3 class="font-black text-xl font-heading text-slate-900 flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
              <Building2 class="w-5 h-5" />
            </div>
            <span>Top Performing Merchant Chains</span>
          </h3>

          <div class="overflow-x-auto rounded-2xl border border-slate-200/80">
            <table class="w-full text-left text-xs">
              <thead>
                <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] bg-slate-50/80 font-bold tracking-wider">
                  <th class="py-4 px-4">Merchant Business</th>
                  <th class="py-4 px-4 text-center">Stores</th>
                  <th class="py-4 px-4 text-center">Plan Tier</th>
                  <th class="py-4 px-4 text-right">Processed Sales</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-sans">
                <tr v-for="t in topTenants" :key="t.id" class="hover:bg-slate-50/80 transition-colors">
                  <td class="py-4 px-4">
                    <span class="font-bold text-slate-900 text-sm font-heading block">{{ t.name }}</span>
                    <span class="text-[10px] text-slate-500 font-mono">{{ t.code }}</span>
                  </td>
                  <td class="py-4 px-4 text-center font-bold text-slate-700">
                    <span class="px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200">
                      {{ t.stores_count || 1 }}
                    </span>
                  </td>
                  <td class="py-4 px-4 text-center">
                    <span class="px-2.5 py-1 rounded-lg bg-indigo-50 border border-indigo-100 text-indigo-700 font-extrabold text-xs">
                      {{ t.plan_name }}
                    </span>
                  </td>
                  <td class="py-4 px-4 text-right font-mono font-bold text-emerald-700 text-sm">
                    ৳{{ formatMoney(t.total_sales || 0) }}
                  </td>
                </tr>
                <tr v-if="!topTenants || topTenants.length === 0">
                  <td colspan="4" class="py-8 text-center text-slate-400 font-bold text-xs">
                    No merchant sales data recorded yet
                  </td>
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
import { TrendingUp, DollarSign, Users, Store, PieChart, Building2, BarChart3 } from 'lucide-vue-next';

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

