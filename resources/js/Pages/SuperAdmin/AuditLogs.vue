<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-100 min-h-screen">
      <!-- Title & Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div class="flex items-center gap-2 text-rose-600 font-bold text-xs uppercase tracking-wider">
            <ShieldCheck class="w-4 h-4" />
            <span>Platform Security & Governance</span>
          </div>
          <h1 class="text-2xl font-black font-heading text-slate-900 mt-1">System Audit Logs & Security Trail</h1>
          <p class="text-xs text-slate-500 mt-1">Full immutable audit trail of administrative actions, merchant onboarding, manual payment approvals, and system events</p>
        </div>
      </div>

      <!-- Audit Logs Table Panel -->
      <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <h3 class="font-bold text-base font-heading text-slate-900">Audit Trail Events ({{ logs.total || logs.length || 0 }})</h3>
          
          <div class="w-full sm:w-72 relative">
            <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input 
              type="text" 
              v-model="searchQuery" 
              placeholder="Search audit trail..." 
              class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-900 focus:outline-none focus:border-rose-500"
            />
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] bg-slate-50">
                <th class="py-3 px-3">Timestamp</th>
                <th class="py-3 px-3">User & IP Address</th>
                <th class="py-3 px-3">Action Type</th>
                <th class="py-3 px-3">Description & Event Details</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 font-sans">
              <tr v-for="log in filteredLogs" :key="log.id" class="hover:bg-slate-50">
                <td class="py-3 px-3 font-mono text-[11px] text-slate-500 whitespace-nowrap">
                  {{ formatDate(log.created_at) }}
                </td>
                <td class="py-3 px-3 whitespace-nowrap">
                  <span class="font-bold text-slate-900 block">{{ log.user_name || 'System' }}</span>
                  <span class="text-[10px] font-mono text-slate-500">{{ log.ip_address || '127.0.0.1' }}</span>
                </td>
                <td class="py-3 px-3 whitespace-nowrap">
                  <span :class="['px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase font-mono border', 
                    log.action.includes('approved') ? 'bg-emerald-50 text-emerald-700 border-emerald-200' :
                    (log.action.includes('rejected') || log.action.includes('deleted') ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-indigo-50 text-indigo-700 border-indigo-200')]">
                    {{ log.action }}
                  </span>
                </td>
                <td class="py-3 px-3 text-slate-700">
                  {{ log.description }}
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
import { ShieldCheck, Search } from 'lucide-vue-next';

const props = defineProps({
  logs: [Object, Array],
});

const searchQuery = ref('');

const allLogs = computed(() => {
  return Array.isArray(props.logs) ? props.logs : (props.logs?.data || []);
});

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
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
};
</script>
