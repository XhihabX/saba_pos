<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-100 min-h-screen">
      <!-- Title & Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div class="flex items-center gap-2 text-rose-600 font-bold text-xs uppercase tracking-wider">
            <CreditCard class="w-4 h-4" />
            <span>SaaS Financial Verifications</span>
          </div>
          <h1 class="text-2xl font-black font-heading text-slate-900 mt-1">Merchant Subscription Payment Ledger</h1>
          <p class="text-xs text-slate-500 mt-1">Complete log of manual mobile payment submissions (bKash, Nagad, Rocket, Bank) and verification status</p>
        </div>
      </div>

      <!-- Transactions Table Panel -->
      <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <h3 class="font-bold text-base font-heading text-slate-900">Payment Transactions ({{ tenants.length }})</h3>
          
          <div class="w-full sm:w-72 relative">
            <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input 
              type="text" 
              v-model="searchQuery" 
              placeholder="Search by TrxID, Phone, or Merchant..." 
              class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-900 focus:outline-none focus:border-rose-500"
            />
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] bg-slate-50">
                <th class="py-3 px-3">Merchant Business</th>
                <th class="py-3 px-3">Payment Method</th>
                <th class="py-3 px-3">Sender Number</th>
                <th class="py-3 px-3 font-mono">Transaction ID (TrxID)</th>
                <th class="py-3 px-3 text-right">Plan Amount (৳)</th>
                <th class="py-3 px-3 text-center">Status</th>
                <th class="py-3 px-3 text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 font-sans">
              <tr v-for="t in filteredTenants" :key="t.id" class="hover:bg-slate-50">
                <td class="py-3.5 px-3">
                  <span class="font-bold text-slate-900 text-sm font-heading block">{{ t.name }}</span>
                  <span class="text-[10px] font-mono text-slate-500">{{ t.email }}</span>
                </td>
                <td class="py-3.5 px-3 font-bold uppercase text-pink-600">
                  {{ t.payment_method || 'bKash' }}
                </td>
                <td class="py-3.5 px-3 font-mono font-bold text-slate-800">
                  {{ t.sender_number || 'N/A' }}
                </td>
                <td class="py-3.5 px-3 font-mono font-black text-amber-600">
                  <span class="bg-amber-50 px-2 py-1 rounded border border-amber-200">{{ t.transaction_id || 'N/A' }}</span>
                </td>
                <td class="py-3.5 px-3 text-right font-mono font-extrabold text-emerald-700">
                  ৳{{ formatMoney(t.mrr_amount) }}
                </td>
                <td class="py-3.5 px-3 text-center">
                  <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase', 
                    t.subscription_status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 
                    (t.subscription_status === 'pending_approval' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-rose-50 text-rose-700 border border-rose-200')]">
                    {{ t.subscription_status }}
                  </span>
                </td>
                <td class="py-3.5 px-3 text-right">
                  <button 
                    v-if="t.subscription_status === 'pending_approval'"
                    @click="approveTenant(t)" 
                    class="px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] shadow-xs"
                  >
                    Verify & Activate
                  </button>
                  <span v-else class="text-xs text-slate-400 font-bold font-mono">Verified</span>
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
import { CreditCard, Search } from 'lucide-vue-next';

const props = defineProps({
  tenants: Array,
});

const searchQuery = ref('');

const filteredTenants = computed(() => {
  if (!searchQuery.value.trim()) return props.tenants || [];
  const q = searchQuery.value.toLowerCase();
  return (props.tenants || []).filter(t =>
    (t.name && t.name.toLowerCase().includes(q)) ||
    (t.transaction_id && t.transaction_id.toLowerCase().includes(q)) ||
    (t.sender_number && t.sender_number.toLowerCase().includes(q))
  );
});

const formatMoney = (val) => {
  return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const approveTenant = (t) => {
  if (confirm(`Approve manual payment for '${t.name}' (TrxID: ${t.transaction_id})?`)) {
    router.post(`/super-admin/tenants/${t.id}/approve`);
  }
};
</script>
