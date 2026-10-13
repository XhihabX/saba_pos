<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-8 bg-slate-50 dark:bg-slate-950 min-h-screen text-slate-900 dark:text-slate-100 transition-colors duration-200">
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
            Audit trail of bKash, Nagad, Rocket, and Bank transfer subscription submissions, renewal approvals, deposit slip downloads, and idempotent cycle extensions
          </p>
        </div>
      </div>

      <!-- 📊 Metric Stat Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Submissions</span>
            <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
              <CreditCard class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-slate-900 dark:text-white">{{ totalSubmissionsCount }} Records</div>
          <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Renewals + Initial Registrations</div>
        </div>

        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pending Verification</span>
            <div class="w-10 h-10 rounded-2xl bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
              <Clock class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-amber-600 dark:text-amber-400">{{ pendingCount }} Pending</div>
          <div class="text-[11px] font-semibold text-amber-700 dark:text-amber-400">Awaiting Super Admin review</div>
        </div>

        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Approved Volume</span>
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
              <CheckCircle2 class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-emerald-700 dark:text-emerald-400">৳{{ formatMoney(approvedAmount) }}</div>
          <div class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">Total verified renewals</div>
        </div>

        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Payment Channels</span>
            <div class="w-10 h-10 rounded-2xl bg-pink-50 dark:bg-pink-950 text-pink-600 dark:text-pink-400 flex items-center justify-center font-bold">
              <Smartphone class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-pink-600 dark:text-pink-400">MFS & Wire</div>
          <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">bKash, Nagad, Rocket, Bank</div>
        </div>
      </div>

      <!-- 💳 Section 1: Subscription Renewals & Payments Ledger -->
      <div class="p-6 sm:p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-2">
              <RefreshCw class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
              <h3 class="font-black text-xl font-heading text-slate-900 dark:text-white">Subscription Renewal Payments</h3>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">All merchant subscription renewals submitted through merchant portal</p>
          </div>

          <div class="flex items-center gap-3 w-full sm:w-auto">
            <select v-model="statusFilter" class="px-3.5 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 focus:outline-none focus:border-rose-500">
              <option value="">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>

            <div class="w-full sm:w-72 relative">
              <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input 
                type="text" 
                v-model="searchQuery" 
                placeholder="Search reference, merchant..." 
                class="w-full pl-10 pr-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-rose-500"
              />
            </div>
          </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200/80 dark:border-slate-800">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 uppercase text-[10px] bg-slate-50/80 dark:bg-slate-800/80 font-bold tracking-wider">
                <th class="py-4 px-4">Merchant Business</th>
                <th class="py-4 px-4">Plan Tier</th>
                <th class="py-4 px-4">Method & Channel Details</th>
                <th class="py-4 px-4 font-mono">Reference / TrxID</th>
                <th class="py-4 px-4 text-right">Amount (৳)</th>
                <th class="py-4 px-4 text-center">Deposit Slip</th>
                <th class="py-4 px-4 text-center">Status</th>
                <th class="py-4 px-4 text-right">Administrative Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-sans">
              <tr v-for="p in filteredPayments" :key="p.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors">
                <td class="py-4 px-4">
                  <span class="font-bold text-slate-900 dark:text-white text-sm font-heading block">{{ p.tenant?.name || 'Unknown' }}</span>
                  <span class="text-[10px] font-mono text-slate-500 dark:text-slate-400">{{ p.tenant?.code }}</span>
                </td>
                <td class="py-4 px-4">
                  <span class="px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-100 dark:border-indigo-900 text-indigo-700 dark:text-indigo-300 font-bold text-[11px]">
                    {{ p.plan?.name || 'SaaS Plan' }}
                  </span>
                </td>
                <td class="py-4 px-4">
                  <div class="space-y-0.5">
                    <span :class="['px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider inline-block',
                      p.method === 'bank' ? 'bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300' : 'bg-pink-100 dark:bg-pink-950 text-pink-700 dark:text-pink-300']">
                      {{ p.method === 'bank' ? 'Bank Wire' : p.method }}
                    </span>
                    <div v-if="p.method === 'bank'" class="text-[10px] text-slate-600 dark:text-slate-400 font-mono">
                      {{ p.bank_name || 'N/A' }} • {{ p.account_holder || 'N/A' }} (****{{ p.account_last4 || '----' }})
                    </div>
                  </div>
                </td>
                <td class="py-4 px-4 font-mono font-black text-amber-600 dark:text-amber-400">
                  <span class="bg-amber-50 dark:bg-amber-950/60 px-2 py-1 rounded-lg border border-amber-200 dark:border-amber-900">
                    {{ p.reference }}
                  </span>
                </td>
                <td class="py-4 px-4 text-right font-mono font-extrabold text-emerald-700 dark:text-emerald-400 text-sm">
                  ৳{{ formatMoney(p.amount) }}
                </td>
                <td class="py-4 px-4 text-center">
                  <a 
                    v-if="p.proof_path" 
                    :href="'/super-admin/subscription-payments/' + p.id + '/proof'" 
                    target="_blank"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950 dark:hover:bg-indigo-900 text-indigo-700 dark:text-indigo-300 font-bold text-[11px] border border-indigo-200 dark:border-indigo-800 transition-all"
                  >
                    <FileText class="w-3.5 h-3.5" />
                    <span>Download</span>
                  </a>
                  <span v-else class="text-[10px] text-slate-400">None</span>
                </td>
                <td class="py-4 px-4 text-center">
                  <span :class="['px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border', 
                    p.status === 'approved' ? 'bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800' : 
                    (p.status === 'pending' ? 'bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800' : 'bg-rose-50 dark:bg-rose-950 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800')]">
                    {{ p.status }}
                  </span>
                  <div v-if="p.reviewer" class="text-[9px] text-slate-400 mt-1">
                    by {{ p.reviewer.name }}
                  </div>
                </td>
                <td class="py-4 px-4 text-right space-x-1.5 whitespace-nowrap">
                  <template v-if="p.status === 'pending'">
                    <button 
                      @click="approvePayment(p)" 
                      class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-xs transition-all cursor-pointer"
                    >
                      Approve (+30d)
                    </button>
                    <button 
                      @click="rejectPayment(p)" 
                      class="px-2.5 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-600 text-rose-700 hover:text-white font-extrabold text-xs border border-rose-200 dark:border-rose-800 transition-all cursor-pointer"
                    >
                      Reject
                    </button>
                  </template>
                  <span v-else class="text-xs text-slate-400 font-mono inline-flex items-center gap-1">
                    <CheckCircle2 v-if="p.status === 'approved'" class="w-3.5 h-3.5 text-emerald-600" />
                    <span>Processed</span>
                  </span>
                </td>
              </tr>
              <tr v-if="filteredPayments.length === 0">
                <td colspan="8" class="py-10 text-center text-slate-400">
                  <CreditCard class="w-8 h-8 mx-auto mb-2 opacity-50" />
                  <p class="font-bold text-xs">No renewal records found matching filter</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 🏬 Section 2: Initial Registration Onboarding Submissions -->
      <div class="p-6 sm:p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-6">
        <div>
          <h3 class="font-black text-xl font-heading text-slate-900 dark:text-white">Initial Registration Onboarding Ledger</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400">Manual submissions recorded during self-service public merchant registration</p>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200/80 dark:border-slate-800">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 uppercase text-[10px] bg-slate-50/80 dark:bg-slate-800/80 font-bold tracking-wider">
                <th class="py-4 px-4">Merchant Business</th>
                <th class="py-4 px-4">Payment Method</th>
                <th class="py-4 px-4">Sender / Bank Details</th>
                <th class="py-4 px-4 font-mono">Reference (TrxID)</th>
                <th class="py-4 px-4 text-right">Plan Amount (৳)</th>
                <th class="py-4 px-4 text-center">Slip Proof</th>
                <th class="py-4 px-4 text-center">Status</th>
                <th class="py-4 px-4 text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-sans">
              <tr v-for="t in filteredTenants" :key="t.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors">
                <td class="py-4 px-4">
                  <span class="font-bold text-slate-900 dark:text-white text-sm font-heading block">{{ t.name }}</span>
                  <span class="text-[10px] font-mono text-slate-500 dark:text-slate-400">{{ t.email }}</span>
                </td>
                <td class="py-4 px-4">
                  <span class="px-2.5 py-1 rounded-lg bg-pink-50 dark:bg-pink-950/60 border border-pink-100 dark:border-pink-900 text-pink-700 dark:text-pink-300 font-black text-xs uppercase">
                    {{ t.payment_method || 'bKash' }}
                  </span>
                </td>
                <td class="py-4 px-4 font-mono font-bold text-slate-800 dark:text-slate-200">
                  <span v-if="t.payment_method === 'bank'">
                    {{ t.bank_name || 'Bank' }} (****{{ t.account_last4 || '----' }})
                  </span>
                  <span v-else>
                    {{ t.sender_number || 'N/A' }}
                  </span>
                </td>
                <td class="py-4 px-4 font-mono font-black text-amber-600 dark:text-amber-400">
                  <span class="bg-amber-50 dark:bg-amber-950/60 px-2 py-1 rounded-lg border border-amber-200 dark:border-amber-900">
                    {{ t.transaction_id || 'N/A' }}
                  </span>
                </td>
                <td class="py-4 px-4 text-right font-mono font-extrabold text-emerald-700 dark:text-emerald-400 text-sm">
                  ৳{{ formatMoney(t.mrr_amount) }}
                </td>
                <td class="py-4 px-4 text-center">
                  <a 
                    v-if="t.proof_path" 
                    :href="'/super-admin/tenants/' + t.id + '/proof'" 
                    target="_blank"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950 dark:hover:bg-indigo-900 text-indigo-700 dark:text-indigo-300 font-bold text-[11px] border border-indigo-200 dark:border-indigo-800 transition-all"
                  >
                    <FileText class="w-3.5 h-3.5" />
                    <span>View Slip</span>
                  </a>
                  <span v-else class="text-[10px] text-slate-400">None</span>
                </td>
                <td class="py-4 px-4 text-center">
                  <span :class="['px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border', 
                    t.subscription_status === 'active' ? 'bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800' : 
                    (t.subscription_status === 'pending_approval' ? 'bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800' : 'bg-rose-50 dark:bg-rose-950 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800')]">
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
                  <span v-else class="text-xs text-slate-400 font-extrabold font-mono inline-flex items-center justify-end gap-1">
                    <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600" />
                    <span>Verified</span>
                  </span>
                </td>
              </tr>
              <tr v-if="filteredTenants.length === 0">
                <td colspan="8" class="py-10 text-center text-slate-400">
                  <CreditCard class="w-8 h-8 mx-auto mb-2 opacity-50" />
                  <p class="font-bold text-xs">No onboarding records found</p>
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
import { CreditCard, Search, Clock, CheckCircle2, Smartphone, FileText, RefreshCw } from 'lucide-vue-next';

const props = defineProps({
  tenants: Array,
  subscriptionPayments: Array,
});

const searchQuery = ref('');
const statusFilter = ref('');

const totalSubmissionsCount = computed(() => {
  return (props.subscriptionPayments?.length || 0) + (props.tenants?.length || 0);
});

const pendingCount = computed(() => {
  const pCount = (props.subscriptionPayments || []).filter(p => p.status === 'pending').length;
  const tCount = (props.tenants || []).filter(t => t.subscription_status === 'pending_approval').length;
  return pCount + tCount;
});

const approvedAmount = computed(() => {
  const paymentsApproved = (props.subscriptionPayments || [])
    .filter(p => p.status === 'approved')
    .reduce((sum, p) => sum + (parseFloat(p.amount) || 0), 0);
  return paymentsApproved;
});

const filteredPayments = computed(() => {
  let list = props.subscriptionPayments || [];
  if (statusFilter.value) {
    list = list.filter(p => p.status === statusFilter.value);
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(p =>
      (p.reference && p.reference.toLowerCase().includes(q)) ||
      (p.tenant?.name && p.tenant.name.toLowerCase().includes(q)) ||
      (p.bank_name && p.bank_name.toLowerCase().includes(q)) ||
      (p.account_holder && p.account_holder.toLowerCase().includes(q))
    );
  }
  return list;
});

const filteredTenants = computed(() => {
  let list = props.tenants || [];
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(t =>
      (t.name && t.name.toLowerCase().includes(q)) ||
      (t.transaction_id && t.transaction_id.toLowerCase().includes(q)) ||
      (t.sender_number && t.sender_number.toLowerCase().includes(q)) ||
      (t.bank_name && t.bank_name.toLowerCase().includes(q))
    );
  }
  return list;
});

const formatMoney = (val) => {
  return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const approvePayment = (payment) => {
  if (confirm(`Approve renewal payment #${payment.id} for '${payment.tenant?.name}' (Ref: ${payment.reference})?`)) {
    router.post(`/super-admin/subscription-payments/${payment.id}/approve`);
  }
};

const rejectPayment = (payment) => {
  const notes = prompt(`Reason for rejecting payment #${payment.id}:`);
  if (notes !== null) {
    router.post(`/super-admin/subscription-payments/${payment.id}/reject`, { notes });
  }
};

const approveTenant = (t) => {
  if (confirm(`Approve initial manual payment for '${t.name}' (TrxID: ${t.transaction_id})?`)) {
    router.post(`/super-admin/tenants/${t.id}/approve`);
  }
};
</script>
