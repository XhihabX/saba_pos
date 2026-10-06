<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-8 bg-slate-50 dark:bg-slate-950 min-h-screen text-slate-900 dark:text-slate-100 transition-colors duration-200">
      
      <!-- 🛡️ Hero Header Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-emerald-950 to-slate-900 text-white p-6 sm:p-8 shadow-xl border border-slate-800">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-emerald-500/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 space-y-2">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 font-bold text-xs uppercase tracking-wider">
            <ShieldCheck class="w-3.5 h-3.5" />
            <span>Chain-Wide Security Audit & Operational Compliance</span>
          </div>
          <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black font-heading tracking-tight text-white">
            Merchant Audit Security Trail
          </h1>
          <p class="text-xs sm:text-sm text-slate-300 max-w-2xl">
            Immutable log of cashier sales checkouts, register shift reconciliations, stock write-offs, customer due collections, purchase orders, and staff account actions across all store outlets.
          </p>
        </div>
      </div>

      <!-- 📊 Security Metric Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Actions</span>
            <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
              <ShieldCheck class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-slate-900 dark:text-white">{{ stats.total_actions || 0 }}</div>
          <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Total recorded tenant events</div>
        </div>

        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">POS Sales Transactions</span>
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
              <ShoppingCart class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-emerald-600 dark:text-emerald-400">{{ stats.pos_sales_count || 0 }}</div>
          <div class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">Counter checkouts completed</div>
        </div>

        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Shift Drawer Audits</span>
            <div class="w-10 h-10 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
              <Clock class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-amber-600 dark:text-amber-400">{{ stats.shift_audits_count || 0 }}</div>
          <div class="text-[11px] font-semibold text-amber-600 dark:text-amber-400">Shift opening & close counts</div>
        </div>

        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Inventory Adjustments</span>
            <div class="w-10 h-10 rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold">
              <AlertTriangle class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-rose-600 dark:text-rose-400">{{ stats.stock_adjustments_count || 0 }}</div>
          <div class="text-[11px] font-semibold text-rose-600 dark:text-rose-400">Stock damage & write-offs</div>
        </div>
      </div>

      <!-- 📋 Audit Logs Table Panel -->
      <div class="p-6 sm:p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-6">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <div>
            <h3 class="font-black text-xl font-heading text-slate-900 dark:text-white">Tenant Security Audit Trail</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Filter actions by store outlet, user, or event type</p>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <!-- Store Outlet Filter -->
            <select 
              v-model="selectedStore" 
              @change="applyFilters"
              class="px-3.5 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-emerald-500"
            >
              <option value="">All Outlets</option>
              <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>

            <!-- Search Input -->
            <div class="relative min-w-[240px]">
              <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input 
                type="text" 
                v-model="searchQuery" 
                @keyup.enter="applyFilters"
                placeholder="Search description, user..." 
                class="w-full pl-10 pr-4 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:border-emerald-500"
              />
            </div>

            <!-- Export CSV Button -->
            <button 
              @click="downloadCSV" 
              class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700 font-extrabold text-xs flex items-center gap-2 transition-all active:scale-95"
              title="Export Audit Logs as CSV"
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
                <th class="py-4 px-4">User & Outlet</th>
                <th class="py-4 px-4">Action Type</th>
                <th class="py-4 px-4">Description</th>
                <th class="py-4 px-4 text-right">IP Address</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-sans">
              <tr v-for="log in allLogs" :key="log.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                <td class="py-4 px-4 font-mono text-[11px] text-slate-500 dark:text-slate-400 whitespace-nowrap">
                  {{ formatDate(log.created_at) }}
                </td>
                <td class="py-4 px-4 whitespace-nowrap">
                  <span class="font-bold text-slate-900 dark:text-slate-100 text-sm block">{{ log.user_name || log.user?.name || 'System' }}</span>
                  <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold block">{{ log.store?.name || 'Chain HQ' }}</span>
                </td>
                <td class="py-4 px-4 whitespace-nowrap">
                  <span :class="['px-3 py-1 rounded-full text-[10px] font-black uppercase font-mono border tracking-wider', 
                    log.action.includes('checkout') || log.action.includes('paid') ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800' :
                    (log.action.includes('deleted') || log.action.includes('adjusted') ? 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800' : 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800')]">
                    {{ log.action }}
                  </span>
                </td>
                <td class="py-4 px-4 text-slate-700 dark:text-slate-300 font-medium">
                  {{ log.description }}
                </td>
                <td class="py-4 px-4 text-right font-mono text-[11px] text-slate-500 dark:text-slate-400 whitespace-nowrap">
                  {{ log.ip_address || '127.0.0.1' }}
                </td>
              </tr>
              <tr v-if="allLogs.length === 0">
                <td colspan="5" class="py-12 text-center text-slate-400">
                  <ShieldCheck class="w-8 h-8 mx-auto mb-2 opacity-50" />
                  <p class="font-bold text-xs">No merchant audit logs recorded yet</p>
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
import { ShieldCheck, Search, ShoppingCart, Clock, AlertTriangle, Download } from 'lucide-vue-next';
import { exportToCSV } from '@/Utils/csvExport';

const props = defineProps({
  logs: [Object, Array],
  stores: Array,
  stats: Object,
  filters: Object,
});

const searchQuery = ref(props.filters?.search || '');
const selectedStore = ref(props.filters?.store_id || '');

const allLogs = computed(() => {
  return Array.isArray(props.logs) ? props.logs : (props.logs?.data || []);
});

const applyFilters = () => {
  router.get('/merchant/audit-logs', {
    search: searchQuery.value,
    store_id: selectedStore.value,
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

const downloadCSV = () => {
  const columns = [
    { key: 'created_at', label: 'Timestamp' },
    { key: 'user_name', label: 'User Name', formatter: (val, item) => val || item.user?.name || 'System' },
    { key: 'store.name', label: 'Store Outlet', formatter: (val) => val || 'Chain HQ' },
    { key: 'action', label: 'Action Type' },
    { key: 'description', label: 'Description' },
    { key: 'ip_address', label: 'IP Address' },
  ];
  exportToCSV('merchant_audit_security_logs', columns, allLogs.value);
};
</script>
