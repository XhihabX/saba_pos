<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-100 min-h-screen">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-2xl font-extrabold font-heading text-slate-900">Store Manager Portal</h1>
            <span class="px-2.5 py-0.5 rounded-full bg-cyan-50 border border-cyan-200 text-cyan-700 text-xs font-bold uppercase">Branch Operations</span>
          </div>
          <p class="text-xs text-slate-500 mt-1">Branch inventory stock intake, cashier shift oversight, and cash reconciliations</p>
        </div>

        <Link href="/manager/shifts" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-xs font-bold text-slate-700 border border-slate-200">
          Shift Audit Reports
        </Link>
      </div>

      <!-- Branch Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs">
          <div class="text-xs text-slate-500 uppercase font-bold mb-2">Today Branch Revenue</div>
          <div class="text-3xl font-extrabold font-heading text-emerald-700">৳{{ formatMoney(todaySales) }}</div>
          <div class="text-[11px] text-slate-500 mt-1 font-medium">Branch sales volume</div>
        </div>

        <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs">
          <div class="text-xs text-slate-500 uppercase font-bold mb-2">Active Register Shifts</div>
          <div class="text-3xl font-extrabold font-heading text-cyan-700">{{ activeShifts ? activeShifts.length : 0 }} Open Shifts</div>
          <div class="text-[11px] text-slate-500 mt-1 font-medium">Cashiers active on registers</div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineProps({
  todaySales: Number,
  activeShifts: Array,
});

const formatMoney = (val) => {
  return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>
