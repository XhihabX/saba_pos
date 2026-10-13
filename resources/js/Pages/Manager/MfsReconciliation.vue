<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-900 min-h-screen text-slate-100 selection:bg-purple-500 selection:text-white">
      <!-- Dark Hero Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-purple-950 to-slate-900 border border-purple-500/20 p-6 sm:p-8 shadow-2xl">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-purple-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-purple-500/10 border border-purple-500/30 text-purple-400 font-mono text-xs font-bold uppercase tracking-wider mb-3">
              <Smartphone class="w-4 h-4" />
              <span>Mobile Wallet Tender Audit</span>
            </div>
            <h1 class="text-3xl font-black font-heading tracking-tight text-white">MFS Reconciliation</h1>
            <p class="text-slate-400 text-xs sm:text-sm mt-1 max-w-2xl">
              Audit counter-verified bKash, Nagad, and Rocket payments against SMS listener webhooks. Unmatched transactions older than 24h trigger discrepancy alerts.
            </p>
          </div>
          <div class="flex items-center gap-4 flex-wrap">
            <div class="px-5 py-3 rounded-2xl bg-purple-500/10 border border-purple-500/30 text-right">
              <div class="text-[11px] font-bold text-purple-400 uppercase tracking-wider">Total Unmatched</div>
              <div class="text-2xl font-black text-purple-300 font-mono">{{ unmatchedCount }}</div>
            </div>
            <div class="px-5 py-3 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-right">
              <div class="text-[11px] font-bold text-rose-400 uppercase tracking-wider">> 24h Unmatched</div>
              <div class="text-2xl font-black text-rose-300 font-mono">{{ expired24hCount }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters & MFS Table -->
      <div class="bg-slate-800/90 border border-slate-700/80 rounded-3xl p-6 shadow-2xl space-y-4 backdrop-blur-md">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-700/60">
          <div class="flex items-center gap-2">
            <button 
              @click="setFilter('all')"
              :class="activeFilter === 'all' ? 'bg-purple-600 text-white font-bold' : 'bg-slate-700/60 text-slate-300 hover:bg-slate-700'"
              class="px-4 py-1.5 rounded-xl text-xs transition"
            >
              All Tenders
            </button>
            <button 
              @click="setFilter('unmatched')"
              :class="activeFilter === 'unmatched' ? 'bg-rose-600 text-white font-bold' : 'bg-slate-700/60 text-slate-300 hover:bg-slate-700'"
              class="px-4 py-1.5 rounded-xl text-xs transition"
            >
              Unmatched ({{ unmatchedCount }})
            </button>
            <button 
              @click="setFilter('matched')"
              :class="activeFilter === 'matched' ? 'bg-emerald-600 text-white font-bold' : 'bg-slate-700/60 text-slate-300 hover:bg-slate-700'"
              class="px-4 py-1.5 rounded-xl text-xs transition"
            >
              Auto-Matched
            </button>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="text-slate-400 border-b border-slate-700/60 uppercase text-[10px] bg-slate-900/60 font-semibold tracking-wider">
                <th class="py-3.5 px-4">Invoice No</th>
                <th class="py-3.5 px-4">Cashier</th>
                <th class="py-3.5 px-4">Outlet</th>
                <th class="py-3.5 px-4">Gateway</th>
                <th class="py-3.5 px-4">TrxID</th>
                <th class="py-3.5 px-4">Amount</th>
                <th class="py-3.5 px-4">Time</th>
                <th class="py-3.5 px-4 text-right">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
              <tr v-if="!paymentsList.length">
                <td colspan="8" class="text-center py-8 text-slate-400">
                  No counter-verified MFS payments found under this filter.
                </td>
              </tr>
              <tr 
                v-for="p in paymentsList" 
                :key="p.id" 
                class="hover:bg-slate-700/30 transition"
                :class="p.is_unmatched_24h ? 'bg-rose-950/20' : ''"
              >
                <td class="py-3.5 px-4 font-mono font-bold text-cyan-400">
                  {{ p.invoice_no }}
                </td>
                <td class="py-3.5 px-4 font-medium text-slate-200">
                  {{ p.cashier_name }}
                </td>
                <td class="py-3.5 px-4 text-slate-400">
                  {{ p.store_name }}
                </td>
                <td class="py-3.5 px-4">
                  <span class="px-2 py-0.5 rounded font-mono text-[10px] font-bold bg-purple-500/10 text-purple-300 border border-purple-500/20">
                    {{ p.payment_method }}
                  </span>
                </td>
                <td class="py-3.5 px-4 font-mono font-bold text-amber-300">
                  {{ p.trx_id }}
                </td>
                <td class="py-3.5 px-4 font-mono font-bold text-emerald-400">
                  ৳{{ p.amount.toFixed(2) }}
                </td>
                <td class="py-3.5 px-4 text-slate-400" :title="p.created_at">
                  {{ p.time_ago }}
                </td>
                <td class="py-3.5 px-4 text-right">
                  <span 
                    v-if="p.is_matched" 
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30"
                  >
                    <CheckCircle class="w-3 h-3" />
                    <span>Auto-Matched</span>
                  </span>
                  <span 
                    v-else-if="p.is_unmatched_24h" 
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40 animate-pulse"
                    title="No SMS match received after 24h. Discrepancy alert logged."
                  >
                    <AlertTriangle class="w-3 h-3" />
                    <span>Unmatched (>24h)</span>
                  </span>
                  <span 
                    v-else 
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/30"
                  >
                    <Clock class="w-3 h-3" />
                    <span>Pending SMS</span>
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
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Smartphone, CheckCircle, Clock, AlertTriangle } from 'lucide-vue-next';

const props = defineProps({
  payments: Object,
  unmatchedCount: Number,
  expired24hCount: Number,
  filters: Object,
});

const activeFilter = ref(props.filters?.status || 'all');

const paymentsList = computed(() => {
  return props.payments?.data || [];
});

const setFilter = (status) => {
  activeFilter.value = status;
  router.get('/manager/mfs-reconciliation', {
    status: status === 'all' ? null : status,
  }, {
    preserveState: true,
    replace: true,
  });
};
</script>
