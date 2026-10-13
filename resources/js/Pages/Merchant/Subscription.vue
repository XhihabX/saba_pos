<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-50 dark:bg-slate-950 min-h-screen text-slate-900 dark:text-slate-100 selection:bg-indigo-500 selection:text-white transition-colors duration-200">
      
      <!-- 💳 Hero Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 border border-indigo-500/20 p-6 sm:p-8 shadow-2xl">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 font-mono text-xs font-bold uppercase tracking-wider mb-3">
              <CreditCard class="w-4 h-4" />
              <span>SaaS Merchant Account</span>
            </div>
            <h1 class="text-3xl font-black font-heading tracking-tight text-white">Subscription & Billing Control</h1>
            <p class="text-slate-400 text-xs sm:text-sm mt-1 max-w-2xl">
              Monitor active SaaS plan tier, renewal dates, and submit manual renewal verification proofs.
            </p>
          </div>
          <div class="flex items-center gap-3">
            <span :class="['px-4 py-2 rounded-2xl border font-extrabold text-xs flex items-center gap-2',
              tenant?.subscription_status === 'active' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30' :
              (tenant?.subscription_status === 'pending_approval' ? 'bg-amber-500/10 text-amber-400 border-amber-500/30' : 'bg-rose-500/10 text-rose-400 border-rose-500/30')
            ]">
              <span :class="['w-2.5 h-2.5 rounded-full',
                tenant?.subscription_status === 'active' ? 'bg-emerald-400 animate-pulse' :
                (tenant?.subscription_status === 'pending_approval' ? 'bg-amber-400 animate-ping' : 'bg-rose-400')
              ]"></span>
              STATUS: {{ (tenant?.subscription_status || 'PENDING').toUpperCase() }}
            </span>

            <button 
              @click="openRenewModal()"
              class="px-5 py-2.5 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs shadow-lg shadow-indigo-600/20 transition-all flex items-center gap-2 cursor-pointer"
            >
              <RefreshCw class="w-4 h-4" />
              <span>Renew / Upgrade Plan</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Current Subscription Overview Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Active Plan Tier</span>
            <Crown class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
          </div>
          <div class="text-2xl font-black font-heading text-slate-900 dark:text-white">
            {{ tenant?.plan_name || 'Growth Multi-Store' }}
          </div>
          <div class="text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400">
            ৳{{ formatMoney(tenant?.mrr_amount || 3999) }} / month
          </div>
        </div>

        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Next Expiry Date</span>
            <Calendar class="w-5 h-5 text-amber-600 dark:text-amber-400" />
          </div>
          <div class="text-2xl font-black font-mono text-slate-900 dark:text-white">
            {{ formatExpiry(tenant?.expires_at) }}
          </div>
          <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">
            {{ expiryDaysLeft(tenant?.expires_at) }}
          </div>
        </div>

        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Last Payment Channel</span>
            <Wallet class="w-5 h-5 text-pink-600 dark:text-pink-400" />
          </div>
          <div class="text-2xl font-black font-heading text-slate-900 dark:text-white uppercase">
            {{ tenant?.payment_method || 'bKash' }}
          </div>
          <div class="text-xs font-mono text-slate-500 dark:text-slate-400">
            Ref: {{ tenant?.transaction_id || 'N/A' }}
          </div>
        </div>
      </div>

      <!-- Available SaaS Plans Grid -->
      <div class="space-y-4">
        <h3 class="text-lg font-black font-heading text-slate-900 dark:text-white flex items-center gap-2">
          <Zap class="w-5 h-5 text-indigo-600" />
          <span>Available SaaS Subscription Tiers</span>
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div 
            v-for="plan in plans" 
            :key="plan.id"
            :class="[
              'rounded-3xl p-6 flex flex-col justify-between border-2 transition-all shadow-md',
              tenant?.plan_name === plan.name 
                ? 'border-indigo-600 bg-white dark:bg-slate-900/90 shadow-indigo-500/10' 
                : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900'
            ]"
          >
            <div>
              <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">{{ plan.name }}</span>
                <span v-if="tenant?.plan_name === plan.name" class="px-2.5 py-0.5 rounded-full bg-indigo-600 text-white font-black text-[10px] uppercase">
                  Current
                </span>
              </div>

              <div class="text-3xl font-black font-heading text-slate-900 dark:text-white mt-3">
                ৳{{ formatMoney(plan.monthly_price) }}
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">/ mo</span>
              </div>

              <p class="text-xs text-slate-600 dark:text-slate-400 mt-2 pb-4 border-b border-slate-100 dark:border-slate-800">
                Max {{ plan.max_stores }} {{ plan.max_stores === 1 ? 'Store Outlet' : 'Store Outlets' }} & {{ plan.max_users }} Staff Logins.
              </p>

              <ul v-if="parseFeatures(plan.features)" class="mt-4 space-y-2 text-xs text-slate-700 dark:text-slate-300">
                <li v-for="(feat, idx) in parseFeatures(plan.features)" :key="idx" class="flex items-center gap-2">
                  <CheckCircle2 class="w-4 h-4 text-emerald-500 shrink-0" />
                  <span>{{ feat }}</span>
                </li>
              </ul>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800">
              <button 
                @click="openRenewModal(plan)"
                :class="[
                  'w-full py-3 rounded-2xl font-extrabold text-xs shadow-md transition-all cursor-pointer',
                  tenant?.plan_name === plan.name 
                    ? 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-indigo-600/20' 
                    : 'bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200'
                ]"
              >
                {{ tenant?.plan_name === plan.name ? 'Renew This Plan' : 'Select & Upgrade' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Subscription Renewal History Table -->
      <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
        <h3 class="font-black text-lg font-heading text-slate-900 dark:text-white flex items-center gap-2">
          <History class="w-5 h-5 text-indigo-600" />
          <span>Renewal Submission History</span>
        </h3>

        <div v-if="history && history.length > 0" class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-800">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 dark:border-slate-800 uppercase text-[10px] bg-slate-50 dark:bg-slate-950 font-bold tracking-wider">
                <th class="py-3.5 px-4">Date</th>
                <th class="py-3.5 px-4">Plan</th>
                <th class="py-3.5 px-4">Method</th>
                <th class="py-3.5 px-4">Reference / TrxID</th>
                <th class="py-3.5 px-4 text-right">Amount (৳)</th>
                <th class="py-3.5 px-4 text-center">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <tr v-for="item in history" :key="item.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50">
                <td class="py-3.5 px-4 font-mono text-slate-600 dark:text-slate-400">
                  {{ formatDate(item.created_at) }}
                </td>
                <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">
                  {{ item.plan?.name || 'SaaS Plan' }}
                </td>
                <td class="py-3.5 px-4">
                  <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                    {{ item.method }}
                  </span>
                </td>
                <td class="py-3.5 px-4 font-mono font-bold text-slate-800 dark:text-slate-200">
                  {{ item.reference }}
                </td>
                <td class="py-3.5 px-4 text-right font-mono font-extrabold text-emerald-600 dark:text-emerald-400">
                  ৳{{ formatMoney(item.amount) }}
                </td>
                <td class="py-3.5 px-4 text-center">
                  <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border',
                    item.status === 'approved' ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300' :
                    (item.status === 'pending' ? 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300' : 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300')
                  ]">
                    {{ item.status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="text-center py-8 text-xs text-slate-500">
          No renewal submissions on record yet.
        </div>
      </div>

      <!-- 📝 Interactive Renew / Upgrade Modal -->
      <div v-if="showRenewModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-xs animate-fade-in">
        <div class="max-w-xl w-full bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl p-6 sm:p-8 space-y-5 max-h-[90vh] overflow-y-auto">
          
          <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <div>
              <h3 class="text-lg font-black font-heading text-slate-900 dark:text-white">Renew / Upgrade Subscription</h3>
              <p class="text-xs text-slate-500">Submit manual payment proof for Super Admin verification</p>
            </div>
            <button @click="showRenewModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 font-bold text-sm">✕</button>
          </div>

          <form @submit.prevent="submitRenewal" class="space-y-4 text-xs">
            
            <!-- Plan Selector -->
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Select SaaS Plan *</label>
              <select v-model="renewForm.plan_id" @change="onPlanChange" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-bold focus:outline-none focus:border-indigo-500">
                <option v-for="p in plans" :key="p.id" :value="p.id">
                  {{ p.name }} — ৳{{ formatMoney(p.monthly_price) }} / mo
                </option>
              </select>
            </div>

            <!-- Amount Due Banner -->
            <div class="p-3.5 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 flex items-center justify-between">
              <span class="text-xs font-bold text-indigo-900 dark:text-indigo-300">Amount to Transfer:</span>
              <span class="text-lg font-black font-mono text-indigo-600 dark:text-indigo-400">৳{{ formatMoney(renewForm.amount) }}</span>
            </div>

            <!-- Payment Method Tabs -->
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Select Payment Channel *</label>
              <div class="grid grid-cols-4 gap-2">
                <button 
                  type="button" 
                  @click="renewForm.payment_method = 'bkash'"
                  :class="['p-2 rounded-xl border font-black text-xs text-center transition-all', renewForm.payment_method === 'bkash' ? 'bg-pink-500 text-white border-pink-500' : 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300']"
                >
                  bKash
                </button>
                <button 
                  type="button" 
                  @click="renewForm.payment_method = 'nagad'"
                  :class="['p-2 rounded-xl border font-black text-xs text-center transition-all', renewForm.payment_method === 'nagad' ? 'bg-orange-500 text-white border-orange-500' : 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300']"
                >
                  Nagad
                </button>
                <button 
                  type="button" 
                  @click="renewForm.payment_method = 'rocket'"
                  :class="['p-2 rounded-xl border font-black text-xs text-center transition-all', renewForm.payment_method === 'rocket' ? 'bg-purple-600 text-white border-purple-600' : 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300']"
                >
                  Rocket
                </button>
                <button 
                  type="button" 
                  @click="renewForm.payment_method = 'bank'"
                  :class="['p-2 rounded-xl border font-black text-xs text-center transition-all', renewForm.payment_method === 'bank' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300']"
                >
                  Bank
                </button>
              </div>
            </div>

            <!-- Instructions Card -->
            <div v-if="!isChannelConfigured" class="p-3.5 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 text-amber-900 dark:text-amber-200 text-xs">
              <strong>Notice:</strong> Receive details for {{ renewForm.payment_method }} have not been configured by the platform administrator yet.
            </div>

            <div v-else-if="renewForm.payment_method !== 'bank'" class="p-3.5 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 space-y-1">
              <div class="flex items-center justify-between">
                <span class="font-bold text-slate-700 dark:text-slate-300 uppercase">{{ renewForm.payment_method }} Receive Number:</span>
                <span class="font-mono font-black text-sm text-slate-900 dark:text-white bg-white dark:bg-slate-900 px-2 py-0.5 rounded">{{ currentChannelNumber }}</span>
              </div>
              <p class="text-[11px] text-slate-500">Send ৳{{ formatMoney(renewForm.amount) }} then enter your sender number and TrxID.</p>
            </div>

            <div v-else class="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 space-y-1 text-[11px]">
              <span class="font-bold text-emerald-900 dark:text-emerald-300 block">Bank Wire Instructions:</span>
              <div class="grid grid-cols-2 gap-1 text-slate-700 dark:text-slate-300">
                <div><strong>Bank:</strong> {{ settings?.bank_name || 'N/A' }}</div>
                <div><strong>A/C Name:</strong> {{ settings?.bank_account_name || 'N/A' }}</div>
                <div><strong>A/C No:</strong> {{ settings?.bank_account_number || 'N/A' }}</div>
                <div><strong>Branch:</strong> {{ settings?.bank_branch_name || 'N/A' }}</div>
              </div>
            </div>

            <!-- Dynamic Fields for MFS -->
            <div v-if="renewForm.payment_method !== 'bank'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Sender Mobile Number *</label>
                <input type="text" v-model="renewForm.sender_number" placeholder="017XXXXXXXX" required class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 font-mono text-slate-900 dark:text-white" />
              </div>
              <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Transaction ID (TrxID) *</label>
                <input type="text" v-model="renewForm.reference" placeholder="e.g. BK89X72901" required class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 font-mono text-slate-900 dark:text-white uppercase" />
              </div>
            </div>

            <!-- Dynamic Fields for Bank -->
            <div v-else class="space-y-3">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Sender Bank Name *</label>
                  <input type="text" v-model="renewForm.bank_name" placeholder="e.g. City Bank" required class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white" />
                </div>
                <div>
                  <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Account Holder Name *</label>
                  <input type="text" v-model="renewForm.account_holder" placeholder="e.g. Rahul Chowdhury" required class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white" />
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Account Last 4 Digits *</label>
                  <input type="text" maxlength="4" v-model="renewForm.account_last4" placeholder="e.g. 9882" required class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 font-mono text-slate-900 dark:text-white" />
                </div>
                <div>
                  <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Transfer Reference / Voucher *</label>
                  <input type="text" v-model="renewForm.reference" placeholder="e.g. TXN-99882" required class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 font-mono text-slate-900 dark:text-white uppercase" />
                </div>
              </div>
            </div>

            <!-- Deposit Proof Attachment (Optional) -->
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Deposit Slip / Screenshot (Max 3MB)</label>
              <input type="file" accept=".jpg,.jpeg,.png,.pdf" @change="onProofChange" class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-700 dark:text-slate-300" />
            </div>

            <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
              <button type="button" @click="showRenewModal = false" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold">
                Cancel
              </button>
              <button type="submit" :disabled="isSubmitting" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold shadow-md disabled:opacity-50">
                {{ isSubmitting ? 'Submitting...' : 'Submit Renewal Proof' }}
              </button>
            </div>
          </form>

        </div>
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { 
  CreditCard, 
  CheckCircle2, 
  Zap, 
  Crown, 
  Calendar, 
  Wallet, 
  RefreshCw,
  History 
} from 'lucide-vue-next';

const props = defineProps({
  tenant: { type: Object, default: () => ({}) },
  plans: { type: Array, default: () => [] },
  settings: { type: Object, default: () => ({}) },
  history: { type: Array, default: () => [] },
});

const showRenewModal = ref(false);
const isSubmitting = ref(false);

const renewForm = ref({
  plan_id: null,
  amount: 3999.00,
  payment_method: 'bkash',
  sender_number: '',
  reference: '',
  bank_name: '',
  account_holder: '',
  account_last4: '',
  proof_file: null,
});

const openRenewModal = (selectedPlan = null) => {
  const plan = selectedPlan || props.plans.find(p => p.name === props.tenant?.plan_name) || props.plans[0];
  if (plan) {
    renewForm.value.plan_id = plan.id;
    renewForm.value.amount = Number(plan.monthly_price);
  }
  renewForm.value.payment_method = 'bkash';
  renewForm.value.sender_number = '';
  renewForm.value.reference = '';
  renewForm.value.bank_name = '';
  renewForm.value.account_holder = '';
  renewForm.value.account_last4 = '';
  renewForm.value.proof_file = null;
  showRenewModal.value = true;
};

const onPlanChange = () => {
  const plan = props.plans.find(p => p.id === renewForm.value.plan_id);
  if (plan) {
    renewForm.value.amount = Number(plan.monthly_price);
  }
};

const onProofChange = (e) => {
  renewForm.value.proof_file = e.target.files[0] || null;
};

const isChannelConfigured = computed(() => {
  const m = renewForm.value.payment_method;
  if (m === 'bkash') return !!props.settings?.bkash_number;
  if (m === 'nagad') return !!props.settings?.nagad_number;
  if (m === 'rocket') return !!props.settings?.rocket_number;
  if (m === 'bank') return !!(props.settings?.bank_name || props.settings?.bank_account_number);
  return false;
});

const currentChannelNumber = computed(() => {
  const m = renewForm.value.payment_method;
  if (m === 'bkash') return props.settings?.bkash_number || '';
  if (m === 'nagad') return props.settings?.nagad_number || '';
  if (m === 'rocket') return props.settings?.rocket_number || '';
  return '';
});

const submitRenewal = () => {
  isSubmitting.value = true;
  router.post('/merchant/subscription/renew', renewForm.value, {
    forceFormData: true,
    onSuccess: () => {
      showRenewModal.value = false;
    },
    onFinish: () => {
      isSubmitting.value = false;
    }
  });
};

const formatMoney = (val) => Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formatExpiry = (dt) => {
  if (!dt) return 'Lifetime / Unexpired';
  const d = new Date(dt);
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatDate = (dt) => {
  if (!dt) return 'N/A';
  const d = new Date(dt);
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const expiryDaysLeft = (dt) => {
  if (!dt) return 'Active Subscription';
  const diff = Math.ceil((new Date(dt) - new Date()) / (1000 * 60 * 60 * 24));
  if (diff < 0) return `${Math.abs(diff)} days past due (Expired)`;
  if (diff === 0) return 'Expires today';
  return `${diff} days remaining`;
};

const parseFeatures = (feat) => {
  if (!feat) return [];
  if (Array.isArray(feat)) return feat;
  try {
    return JSON.parse(feat);
  } catch (e) {
    return [];
  }
};
</script>
