<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-8 bg-slate-50 dark:bg-slate-950 min-h-screen text-slate-900 dark:text-slate-100 transition-colors duration-200">
      
      <!-- 🛡️ Hero Header Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-6 sm:p-8 shadow-xl border border-slate-800">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 space-y-2">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 font-bold text-xs uppercase tracking-wider">
            <ShieldCheck class="w-3.5 h-3.5" />
            <span>Branch Operational Audit & Till Security</span>
          </div>
          <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black font-heading tracking-tight text-white">
            Branch Audit Security Logs
          </h1>
          <p class="text-xs sm:text-sm text-slate-300 max-w-2xl">
            Real-time audit log of cashier sales checkouts, register float reconciliations, stock transfers, customer returns, and branch operational expense entries.
          </p>
        </div>
      </div>

      <!-- 📊 Security Metric Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Branch Total Actions</span>
            <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
              <ShieldCheck class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-slate-900 dark:text-white">{{ stats.total_actions || 0 }}</div>
          <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Total actions recorded for this store</div>
        </div>

        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">POS Sales Transactions</span>
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
              <ShoppingCart class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-emerald-600 dark:text-emerald-400">{{ stats.pos_sales_count || 0 }}</div>
          <div class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">Counter sales completed</div>
        </div>

        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Shift Drawer Audits</span>
            <div class="w-10 h-10 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
              <Clock class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-amber-600 dark:text-amber-400">{{ stats.shift_audits_count || 0 }}</div>
          <div class="text-[11px] font-semibold text-amber-600 dark:text-amber-400">Register float reconciliations</div>
        </div>
      </div>

      <!-- 📋 Audit Logs Table Panel -->
      <div class="p-6 sm:p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h3 class="font-black text-xl font-heading text-slate-900 dark:text-white">Branch Audit Log History</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Audit records for cashiers and store supervisors</p>
          </div>

          <div class="flex items-center gap-3">
            <div class="w-full sm:w-72 relative">
              <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input 
                type="text" 
                v-model="searchQuery" 
                @keyup.enter="applyFilters"
                placeholder="Search description, user..." 
                class="w-full pl-10 pr-4 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
              />
            </div>

            <button 
              @click="handleExport" 
              class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs flex items-center gap-2 transition-colors whitespace-nowrap"
            >
              <Download class="w-4 h-4" />
              <span>Export CSV</span>
            </button>
          </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200/80 dark:border-slate-800">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 uppercase text-[10px] bg-slate-50/80 dark:bg-slate-950/60 font-bold tracking-wider">
                <th class="py-4 px-4">Timestamp</th>
                <th class="py-4 px-4">Cashier / Staff User</th>
                <th class="py-4 px-4">Action Type</th>
                <th class="py-4 px-4">Description</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-sans">
              <tr v-for="log in allLogs" :key="log.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                <td class="py-4 px-4 font-mono text-[11px] text-slate-500 dark:text-slate-400 whitespace-nowrap">
                  {{ formatDate(log.created_at) }}
                </td>
                <td class="py-4 px-4 whitespace-nowrap font-bold text-slate-900 dark:text-slate-100 text-sm">
                  {{ log.user_name || log.user?.name || 'Cashier' }}
                </td>
                <td class="py-4 px-4 whitespace-nowrap">
                  <span :class="['px-3 py-1 rounded-full text-[10px] font-black uppercase font-mono border tracking-wider', 
                    log.action.includes('checkout') || log.action.includes('opened') ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800' :
                    (log.action.includes('closed') || log.action.includes('returned') ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800' : 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800')]">
                    {{ log.action }}
                  </span>
                </td>
                <td class="py-4 px-4 text-slate-700 dark:text-slate-300 font-medium">
                  {{ log.description }}
                </td>
              </tr>
              <tr v-if="allLogs.length === 0">
                <td colspan="4" class="py-12 text-center text-slate-400">
                  <ShieldCheck class="w-8 h-8 mx-auto mb-2 opacity-50" />
                  <p class="font-bold text-xs">No branch audit logs recorded yet</p>
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
import { ShieldCheck, Search, ShoppingCart, Clock, Download } from 'lucide-vue-next';
import { exportToCSV } from '@/Utils/csvExport';

const props = defineProps({
  logs: [Object, Array],
  stats: Object,
  filters: Object,
});

const searchQuery = ref(props.filters?.search || '');

const allLogs = computed(() => {
  return Array.isArray(props.logs) ? props.logs : (props.logs?.data || []);
});

const applyFilters = () => {
  router.get('/manager/audit-logs', {
    search: searchQuery.value,
  }, { preserveState: true, replace: true });
};

const formatDate = (dateStr) => {
  if (!dateStr) return 'Just now';
  return new Date(dateStr).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const handleExport = () => {
  const columns = [
    { label: 'ID', key: 'id' },
    { label: 'Date', key: 'created_at' },
    { label: 'User', key: 'user_name' },
    { label: 'Action', key: 'action' },
    { label: 'Description', key: 'description' },
  ];
  exportToCSV('manager_branch_audit_logs', columns, allLogs.value);
};
</script>
