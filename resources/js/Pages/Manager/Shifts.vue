<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-100 min-h-screen">
      <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <h1 class="text-2xl font-extrabold font-heading text-slate-900">Cash Register Shift Audits</h1>
        <p class="text-xs text-slate-500 mt-1">Reconcile opening cash float, counted closing cash, and cash drawer variance</p>
      </div>

      <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-4">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] bg-slate-50">
                <th class="py-3 px-3">Cashier Staff</th>
                <th class="py-3 px-3">Opened At</th>
                <th class="py-3 px-3 text-right">Opening Cash</th>
                <th class="py-3 px-3 text-right">Cash Sales</th>
                <th class="py-3 px-3 text-right">Expected Cash</th>
                <th class="py-3 px-3 text-right">Counted Cash</th>
                <th class="py-3 px-3 text-right">Cash Variance</th>
                <th class="py-3 px-3 text-center">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr v-for="shift in (shifts.data || shifts)" :key="shift.id" class="hover:bg-slate-50">
                <td class="py-3.5 px-3 font-bold text-slate-900 text-sm font-heading">{{ shift.user?.name || 'Cashier' }}</td>
                <td class="py-3.5 px-3 text-slate-700 font-mono">{{ formatDate(shift.opened_at) }}</td>
                <td class="py-3.5 px-3 text-right font-mono text-slate-700">৳{{ formatMoney(shift.opening_cash) }}</td>
                <td class="py-3.5 px-3 text-right font-mono text-emerald-700">৳{{ formatMoney(shift.total_cash_sales) }}</td>
                <td class="py-3.5 px-3 text-right font-mono text-indigo-700">৳{{ formatMoney(shift.expected_cash) }}</td>
                <td class="py-3.5 px-3 text-right font-mono font-bold text-slate-900">৳{{ formatMoney(shift.closing_cash_counted) }}</td>
                <td class="py-3.5 px-3 text-right font-mono font-bold">
                  <span :class="shift.cash_difference >= 0 ? 'text-emerald-700' : 'text-rose-600'">
                    ৳{{ formatMoney(shift.cash_difference) }}
                  </span>
                </td>
                <td class="py-3.5 px-3 text-center">
                  <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase', shift.status === 'open' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200']">
                    {{ shift.status }}
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
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineProps({
  shifts: Object,
});

const formatMoney = (val) => {
  return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatDate = (dateStr) => {
  if (!dateStr) return 'N/A';
  return new Date(dateStr).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
};
</script>
