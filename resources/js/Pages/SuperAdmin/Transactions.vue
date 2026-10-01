<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-8 bg-slate-50/70 min-h-screen">
      <!-- 💳 Hero Header Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-rose-950 to-slate-900 text-white p-6 sm:p-8 shadow-xl border border-slate-800">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-rose-500/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 space-y-2">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 font-bold text-xs uppercase tracking-wider">
            <CreditCard class="w-3.5 h-3.5" />
            <span>SaaS Financial Audit & Verifications</span>
          </div>
          <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black font-heading tracking-tight text-white">
            Merchant Subscription Payment Ledger
          </h1>
          <p class="text-xs sm:text-sm text-slate-300 max-w-2xl">
            Complete audit trail of bKash, Nagad, Rocket, and Bank transfer submissions with manual TrxID verification controls
          </p>
        </div>
      </div>

      <!-- 📊 Metric Stat Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Submissions</span>
            <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
              <CreditCard class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-slate-900">{{ tenants.length }} Ledger Entries</div>
          <div class="text-[11px] font-semibold text-slate-500">Across all onboarding channels</div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Pending Verification</span>
            <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
              <Clock class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-amber-600">{{ pendingCount }} Pending</div>
          <div class="text-[11px] font-semibold text-amber-700">Awaiting TrxID check</div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Verified Volume</span>
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
              <CheckCircle2 class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-emerald-700">৳{{ formatMoney(verifiedAmount) }}</div>
          <div class="text-[11px] font-semibold text-emerald-600">Collected subscription fees</div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Primary Channel</span>
            <div class="w-10 h-10 rounded-2xl bg-pink-50 text-pink-600 flex items-center justify-center font-bold">
              <Smartphone class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-pink-600">bKash & Nagad</div>
          <div class="text-[11px] font-semibold text-slate-500">Mobile banking transfers</div>
        </div>
      </div>

      <!-- 💳 Transactions Ledger Panel -->
      <div class="p-6 sm:p-8 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h3 class="font-black text-xl font-heading text-slate-900">Subscription Payment Submissions</h3>
            <p class="text-xs text-slate-500">Audit manual bKash/Nagad payment submissions</p>
          </div>

          <div class="flex items-center gap-3 w-full sm:w-auto">
            <select v-model="statusFilter" class="px-3.5 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-xs font-bold text-slate-700 focus:outline-none focus:border-rose-500">
              <option value="">All Statuses</option>
              <option value="pending_approval">Pending Approval</option>
              <option value="active">Verified & Active</option>
              <option value="suspended">Suspended / Rejected</option>
            </select>

            <div class="w-full sm:w-72 relative">
              <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input 
                type="text" 
                v-model="searchQuery" 
                placeholder="Search TrxID, Phone, or Merchant..." 
                class="w-full pl-10 pr-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-xs text-slate-900 focus:outline-none focus:border-rose-500"
              />
            </div>
          </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200/80">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] bg-slate-50/80 font-bold tracking-wider">
                <th class="py-4 px-4">Merchant Business</th>
                <th class="py-4 px-4">Payment Method</th>
                <th class="py-4 px-4">Sender Number</th>
                <th class="py-4 px-4 font-mono">Transaction ID (TrxID)</th>
                <th class="py-4 px-4 text-right">Plan Amount (৳)</th>
                <th class="py-4 px-4 text-center">Status</th>
                <th class="py-4 px-4 text-right">Verification Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-sans">
              <tr v-for="t in filteredTenants" :key="t.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="py-4 px-4">
                  <span class="font-bold text-slate-900 text-sm font-heading block">{{ t.name }}</span>
                  <span class="text-[10px] font-mono text-slate-500">{{ t.email }}</span>
                </td>
                <td class="py-4 px-4">
                  <span class="px-2.5 py-1 rounded-lg bg-pink-50 border border-pink-100 text-pink-700 font-black text-xs uppercase">
                    {{ t.payment_method || 'bKash' }}
                  </span>
                </td>
                <td class="py-4 px-4 font-mono font-bold text-slate-800">
                  {{ t.sender_number || 'N/A' }}
                </td>
                <td class="py-4 px-4 font-mono font-black text-amber-600">
                  <span class="bg-amber-50 px-2.5 py-1 rounded-lg border border-amber-200 inline-flex items-center gap-1.5">
                    {{ t.transaction_id || 'N/A' }}
                  </span>
                </td>
                <td class="py-4 px-4 text-right font-mono font-extrabold text-emerald-700 text-sm">
                  ৳{{ formatMoney(t.mrr_amount) }}
                </td>
                <td class="py-4 px-4 text-center">
                  <span :class="['px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border', 
                    t.subscription_status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 
                    (t.subscription_status === 'pending_approval' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-rose-50 text-rose-700 border-rose-200')]">
                    {{ t.subscription_status }}
                  </span>
                </td>
                <td class="py-4 px-4 text-right">
                  <button 
                    v-if="t.subscription_status === 'pending_approval'"
                    @click="approveTenant(t)" 
                    class="px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-md transition-all cursor-pointer"
                  >
                    Verify & Activate
                  </button>
                  <span v-else class="text-xs text-slate-400 font-extrabold font-mono flex items-center justify-end gap-1">
                    <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600" />
                    <span>Verified</span>
                  </span>
                </td>
              </tr>
              <tr v-if="filteredTenants.length === 0">
                <td colspan="7" class="py-12 text-center text-slate-400">
                  <CreditCard class="w-8 h-8 mx-auto mb-2 opacity-50" />
                  <p class="font-bold text-xs">No transaction records found matching your filter</p>
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
import { CreditCard, Search, Clock, CheckCircle2, Smartphone } from 'lucide-vue-next';

const props = defineProps({
  tenants: Array,
});

const searchQuery = ref('');
const statusFilter = ref('');

const pendingCount = computed(() => (props.tenants || []).filter(t => t.subscription_status === 'pending_approval').length);
const verifiedAmount = computed(() => (props.tenants || [])
  .filter(t => t.subscription_status === 'active')
  .reduce((sum, t) => sum + (parseFloat(t.mrr_amount) || 0), 0));

const filteredTenants = computed(() => {
  let list = props.tenants || [];
  if (statusFilter.value) {
    list = list.filter(t => t.subscription_status === statusFilter.value);
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(t =>
      (t.name && t.name.toLowerCase().includes(q)) ||
      (t.transaction_id && t.transaction_id.toLowerCase().includes(q)) ||
      (t.sender_number && t.sender_number.toLowerCase().includes(q))
    );
  }
  return list;
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

