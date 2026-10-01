<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-900 min-h-screen text-slate-100 selection:bg-cyan-500 selection:text-white">
      <!-- Dark Hero Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-cyan-950 to-slate-900 border border-cyan-500/20 p-6 sm:p-8 shadow-2xl">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 font-mono text-xs font-bold uppercase tracking-wider mb-3">
              <Clock class="w-4 h-4" />
              <span>Store Manager Register Audit</span>
            </div>
            <h1 class="text-3xl font-black font-heading tracking-tight text-white">Cash Register Shift Audits</h1>
            <p class="text-slate-400 text-xs sm:text-sm mt-1 max-w-2xl">
              Reconcile cashier opening floats, counted closing cash, cash sales, and detect cash drawer discrepancies in real time.
            </p>
          </div>
        </div>
      </div>

      <!-- Shifts Table Card -->
      <div class="bg-slate-800/90 border border-slate-700/80 rounded-3xl p-6 shadow-2xl space-y-4 backdrop-blur-md">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-700/60">
          <div class="relative w-full sm:w-80">
            <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input 
              v-model="searchQuery" 
              type="text" 
              placeholder="Search by cashier staff name..." 
              class="w-full pl-10 pr-4 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs text-slate-100 placeholder-slate-400 focus:outline-none focus:border-cyan-500"
            />
          </div>
          <span class="text-xs text-slate-400 font-mono">Shift Audits Recorded: {{ shiftsList.length }}</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="text-slate-400 border-b border-slate-700/60 uppercase text-[10px] bg-slate-900/60 font-semibold tracking-wider">
                <th class="py-3.5 px-4">Cashier Staff</th>
                <th class="py-3.5 px-4">Opened Timestamp</th>
                <th class="py-3.5 px-4 text-right">Opening Float</th>
                <th class="py-3.5 px-4 text-right">Cash Sales</th>
                <th class="py-3.5 px-4 text-right">Expected Drawer</th>
                <th class="py-3.5 px-4 text-right">Counted Cash</th>
                <th class="py-3.5 px-4 text-right">Cash Variance</th>
                <th class="py-3.5 px-4 text-center">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/60">
              <tr v-for="shift in filteredShifts" :key="shift.id" class="hover:bg-slate-700/30 transition-colors">
                <td class="py-4 px-4 font-bold text-white text-sm font-heading flex items-center gap-3">
                  <div class="w-9 h-9 rounded-xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 flex items-center justify-center font-bold">
                    {{ (shift.user?.name || 'C').charAt(0) }}
                  </div>
                  <span>{{ shift.user?.name || 'Cashier Staff' }}</span>
                </td>
                <td class="py-4 px-4 text-slate-300 font-mono text-xs">{{ formatDate(shift.opened_at) }}</td>
                <td class="py-4 px-4 text-right font-mono text-slate-300">৳{{ formatMoney(shift.opening_cash) }}</td>
                <td class="py-4 px-4 text-right font-mono font-bold text-emerald-400">৳{{ formatMoney(shift.total_cash_sales) }}</td>
                <td class="py-4 px-4 text-right font-mono font-bold text-cyan-400">৳{{ formatMoney(shift.expected_cash) }}</td>
                <td class="py-4 px-4 text-right font-mono font-bold text-white">৳{{ formatMoney(shift.closing_cash_counted) }}</td>
                <td class="py-4 px-4 text-right font-mono font-bold text-sm">
                  <span :class="shift.cash_difference >= 0 ? 'text-emerald-400' : 'text-rose-400'">
                    ৳{{ formatMoney(shift.cash_difference) }}
                  </span>
                </td>
                <td class="py-4 px-4 text-center">
                  <span :class="[
                    'px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider border', 
                    shift.status === 'open' ? 'bg-amber-500/10 text-amber-400 border-amber-500/30' : 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30'
                  ]">
                    {{ shift.status }}
                  </span>
                </td>
              </tr>
              <tr v-if="filteredShifts.length === 0">
                <td colspan="8" class="py-12 text-center text-slate-400 font-medium">No register shifts recorded yet.</td>
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
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Clock, Search } from 'lucide-vue-next';

const props = defineProps({
  shifts: Object,
});

const searchQuery = ref('');

const shiftsList = computed(() => props.shifts?.data || props.shifts || []);

const filteredShifts = computed(() => {
  if (!searchQuery.value.trim()) return shiftsList.value;
  const q = searchQuery.value.toLowerCase().trim();
  return shiftsList.value.filter(s => 
    s.user?.name && s.user.name.toLowerCase().includes(q)
  );
});

const formatMoney = (val) => {
  return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatDate = (dateStr) => {
  if (!dateStr) return 'N/A';
  return new Date(dateStr).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
};
</script>
