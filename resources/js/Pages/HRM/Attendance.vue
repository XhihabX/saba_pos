<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-6">
      <!-- Title & Clock In/Out Action Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl sm:text-3xl font-black font-heading text-slate-900">
            HRM Staff Attendance & Shifts
          </h1>
          <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">
            Clock In / Clock Out attendance tracking, work hours, and shift logs
          </p>
        </div>

        <button 
          @click="toggleClock" 
          :class="['px-6 py-3 rounded-2xl font-extrabold text-sm shadow-md flex items-center justify-center gap-2 transition-all active:scale-95 text-white', activeClockIn ? 'bg-amber-600 hover:bg-amber-700' : 'bg-emerald-600 hover:bg-emerald-700']"
        >
          <Clock class="w-5 h-5" />
          <span>{{ activeClockIn ? '⏱ Clock Out (End Shift)' : '⚡ Clock In (Start Shift)' }}</span>
        </button>
      </div>

      <!-- Current Shift Status Alert (Posify Style) -->
      <div :class="['p-4 rounded-2xl border flex items-center justify-between text-xs font-bold', activeClockIn ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-slate-50 border-slate-200 text-slate-700']">
        <div class="flex items-center gap-2">
          <div :class="['w-3 h-3 rounded-full', activeClockIn ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400']"></div>
          <span>Status: {{ activeClockIn ? 'Currently Clocked In since ' + formatTime(activeClockIn.clock_in) : 'Not Clocked In' }}</span>
        </div>
        <span v-if="activeClockIn" class="font-mono text-emerald-700">Station IP: {{ activeClockIn.ip_address || '127.0.0.1' }}</span>
      </div>

      <!-- Attendance Table Card -->
      <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-xs space-y-4">
        <h3 class="font-bold text-base font-heading text-slate-900">Recent Attendance Logs</h3>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] font-bold">
                <th class="py-3 px-3">Staff Member</th>
                <th class="py-3 px-3">Clock In</th>
                <th class="py-3 px-3">Clock Out</th>
                <th class="py-3 px-3 text-center">Total Hours</th>
                <th class="py-3 px-3">Shift Notes</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="att in attendances.data || attendances" :key="att.id" class="hover:bg-slate-50 transition-colors">
                <td class="py-3.5 px-3">
                  <div class="font-bold text-slate-900 font-heading">{{ att.user?.name || 'Cashier Staff' }}</div>
                  <div class="text-[10px] text-slate-400">{{ att.user?.email }}</div>
                </td>
                <td class="py-3.5 px-3 font-mono text-slate-700">{{ formatDateTime(att.clock_in) }}</td>
                <td class="py-3.5 px-3 font-mono text-slate-700">
                  <span v-if="att.clock_out">{{ formatDateTime(att.clock_out) }}</span>
                  <span v-else class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-bold border border-emerald-200 text-[10px]">Active Shift</span>
                </td>
                <td class="py-3.5 px-3 text-center font-mono font-bold text-emerald-700">
                  {{ att.total_hours ? att.total_hours + ' hrs' : '-' }}
                </td>
                <td class="py-3.5 px-3 text-slate-500 text-[11px]">{{ att.notes || 'Normal shift' }}</td>
              </tr>
              <tr v-if="!attendances || (attendances.data && attendances.data.length === 0)">
                <td colspan="5" class="py-8 text-center text-slate-400">No staff attendance logs recorded yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Clock } from 'lucide-vue-next';

const props = defineProps({
  attendances: Object,
  activeClockIn: Object,
});

const formatTime = (dateStr) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const formatDateTime = (dateStr) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
};

const toggleClock = () => {
  router.post('/hrm/attendance/toggle', { notes: 'Clocked via station header' });
};
</script>
