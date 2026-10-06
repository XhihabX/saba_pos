<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-8 bg-slate-50/70 min-h-screen">
      <!-- 🛡️ Hero Header Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-rose-950 to-slate-900 text-white p-6 sm:p-8 shadow-xl border border-slate-800">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-rose-500/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 space-y-2">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 font-bold text-xs uppercase tracking-wider">
            <ShieldCheck class="w-3.5 h-3.5" />
            <span>Platform Security Audit & Compliance</span>
          </div>
          <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black font-heading tracking-tight text-white">
            System Audit Trail & Security Logs
          </h1>
          <p class="text-xs sm:text-sm text-slate-300 max-w-2xl">
            Immutable audit log of administrative actions, merchant onboarding events, manual payment approvals, role changes, and IP addresses
          </p>
        </div>
      </div>

      <!-- 📊 Security Metric Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Audit Events</span>
            <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
              <ShieldCheck class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-slate-900">{{ allLogs.length }} Events</div>
          <div class="text-[11px] font-semibold text-slate-500">Recorded across system lifecycle</div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Approvals Recorded</span>
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
              <CheckCircle2 class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-emerald-700">{{ approvedCount }} Approvals</div>
          <div class="text-[11px] font-semibold text-emerald-600">Manual payment verifications</div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Security Restores/Deletes</span>
            <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold">
              <AlertTriangle class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-rose-700">{{ criticalCount }} High Severity</div>
          <div class="text-[11px] font-semibold text-rose-600">Sensitive admin interventions</div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Audit Trail Integrity</span>
            <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
              <Lock class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-slate-900">256-Bit Immutable</div>
          <div class="text-[11px] font-semibold text-amber-600">Append-only security log</div>
        </div>
      </div>

      <!-- 📋 Audit Logs Table Panel -->
      <div class="p-6 sm:p-8 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h3 class="font-black text-xl font-heading text-slate-900">Security Audit Trail Log</h3>
            <p class="text-xs text-slate-500">Full history of actions taken by Super Admins and System Cron Workers</p>
          </div>

          <div class="flex items-center gap-3">
            <div class="w-full sm:w-72 relative">
              <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input 
                type="text" 
                v-model="searchQuery" 
                placeholder="Search by action, admin name, description..." 
                class="w-full pl-10 pr-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-xs text-slate-900 focus:outline-none focus:border-rose-500"
              />
            </div>

            <button 
              @click="handleExport" 
              class="px-4 py-2.5 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs flex items-center gap-2 transition-colors whitespace-nowrap"
            >
              <Download class="w-4 h-4" />
              <span>Export CSV</span>
            </button>
          </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200/80">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] bg-slate-50/80 font-bold tracking-wider">
                <th class="py-4 px-4">Timestamp</th>
                <th class="py-4 px-4">Admin User & IP</th>
                <th class="py-4 px-4">Action Type</th>
                <th class="py-4 px-4">Description & Event Payload</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-sans">
              <tr v-for="log in filteredLogs" :key="log.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="py-4 px-4 font-mono text-[11px] text-slate-500 whitespace-nowrap">
                  {{ formatDate(log.created_at) }}
                </td>
                <td class="py-4 px-4 whitespace-nowrap">
                  <span class="font-bold text-slate-900 text-sm block">{{ log.user_name || 'System Auto' }}</span>
                  <span class="text-[10px] font-mono text-slate-500 flex items-center gap-1">
                    <Globe class="w-3 h-3 text-slate-400" />
                    <span>{{ log.ip_address || '127.0.0.1' }}</span>
                  </span>
                </td>
                <td class="py-4 px-4 whitespace-nowrap">
                  <span :class="['px-3 py-1 rounded-full text-[10px] font-black uppercase font-mono border tracking-wider', 
                    log.action.includes('approved') ? 'bg-emerald-50 text-emerald-700 border-emerald-200' :
                    (log.action.includes('rejected') || log.action.includes('deleted') || log.action.includes('terminated') ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-indigo-50 text-indigo-700 border-indigo-200')]">
                    {{ log.action }}
                  </span>
                </td>
                <td class="py-4 px-4 text-slate-700 font-medium">
                  {{ log.description }}
                </td>
              </tr>
              <tr v-if="filteredLogs.length === 0">
                <td colspan="4" class="py-12 text-center text-slate-400">
                  <ShieldCheck class="w-8 h-8 mx-auto mb-2 opacity-50" />
                  <p class="font-bold text-xs">No audit logs recorded yet</p>
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
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ShieldCheck, Search, CheckCircle2, AlertTriangle, Lock, Globe, Download } from 'lucide-vue-next';
import { exportToCSV } from '@/Utils/csvExport';

const props = defineProps({
  logs: [Object, Array],
});

const searchQuery = ref('');

const allLogs = computed(() => {
  return Array.isArray(props.logs) ? props.logs : (props.logs?.data || []);
});

const approvedCount = computed(() => allLogs.value.filter(l => (l.action || '').includes('approved')).length);
const criticalCount = computed(() => allLogs.value.filter(l => 
  (l.action || '').includes('deleted') || 
  (l.action || '').includes('rejected') || 
  (l.action || '').includes('terminated')
).length);

const filteredLogs = computed(() => {
  if (!searchQuery.value.trim()) return allLogs.value;
  const q = searchQuery.value.toLowerCase();
  return allLogs.value.filter(l =>
    (l.user_name && l.user_name.toLowerCase().includes(q)) ||
    (l.action && l.action.toLowerCase().includes(q)) ||
    (l.description && l.description.toLowerCase().includes(q))
  );
});

const formatDate = (dateStr) => {
  if (!dateStr) return 'Just now';
  return new Date(dateStr).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
};

const handleExport = () => {
  const columns = [
    { label: 'ID', key: 'id' },
    { label: 'Date', key: 'created_at' },
    { label: 'Admin User', key: 'user_name' },
    { label: 'IP Address', key: 'ip_address' },
    { label: 'Action', key: 'action' },
    { label: 'Description', key: 'description' },
  ];
  exportToCSV('superadmin_system_audit_logs', columns, filteredLogs.value);
};
</script>

