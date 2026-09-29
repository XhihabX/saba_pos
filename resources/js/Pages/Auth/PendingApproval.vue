<template>
  <div class="min-h-screen bg-slate-900 text-slate-100 flex items-center justify-center p-4 lg:p-8 selection:bg-amber-500 selection:text-white">
    <div class="max-w-xl w-full bg-slate-800/90 border border-slate-700/80 rounded-3xl p-8 sm:p-10 shadow-2xl backdrop-blur-xl text-center space-y-6">
      
      <!-- Top Animated Status Badge -->
      <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 font-extrabold text-xs uppercase tracking-widest animate-pulse">
        <Clock class="w-4 h-4" />
        <span>Payment Verification Pending</span>
      </div>

      <!-- Icon Header -->
      <div class="w-20 h-20 rounded-3xl bg-gradient-to-tr from-amber-500 to-orange-600 flex items-center justify-center mx-auto shadow-lg shadow-amber-500/20">
        <ShieldAlert class="w-10 h-10 text-slate-950" />
      </div>

      <div>
        <h2 class="text-2xl sm:text-3xl font-black font-heading text-white tracking-tight mb-2">
          Registration Received!
        </h2>
        <p class="text-xs sm:text-sm text-slate-300 leading-relaxed max-w-md mx-auto">
          Thank you for registering <span class="font-bold text-amber-400">{{ tenant?.name || 'your store' }}</span> on <span class="font-bold text-white">Saba POS</span>. Your manual payment is awaiting Super Admin verification.
        </p>
      </div>

      <!-- Submitted Payment Summary Box -->
      <div v-if="tenant" class="bg-slate-900/80 border border-slate-700/60 rounded-2xl p-5 text-left space-y-3 text-xs">
        <div class="flex items-center justify-between border-b border-slate-800 pb-2">
          <span class="text-slate-400">Selected Plan</span>
          <span class="font-bold text-white bg-slate-800 px-2.5 py-1 rounded-lg border border-slate-700">{{ tenant.plan_name }} (৳{{ tenant.mrr_amount }}/mo)</span>
        </div>
        <div class="flex items-center justify-between border-b border-slate-800 pb-2">
          <span class="text-slate-400">Payment Gateway</span>
          <span class="font-bold uppercase text-emerald-400 tracking-wider">{{ tenant.payment_method || 'bKash / Nagad' }}</span>
        </div>
        <div class="flex items-center justify-between border-b border-slate-800 pb-2">
          <span class="text-slate-400">Sender Phone / Account</span>
          <span class="font-mono font-bold text-slate-200">{{ tenant.sender_number || 'N/A' }}</span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-slate-400">Transaction ID (TrxID)</span>
          <span class="font-mono font-black text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/20">{{ tenant.transaction_id || 'N/A' }}</span>
        </div>
      </div>

      <!-- Instructions & SLA -->
      <div class="bg-amber-950/30 border border-amber-500/20 rounded-2xl p-4 text-xs text-amber-200/90 text-left flex gap-3 items-start">
        <Info class="w-5 h-5 text-amber-400 shrink-0 mt-0.5" />
        <div class="space-y-1">
          <span class="font-extrabold block text-amber-300">What happens next?</span>
          <span>Our Super Admin team verifies your Transaction ID with bKash/Nagad logs. Verification typically takes <strong class="text-white">5 to 15 minutes</strong> during business hours. Once verified, your account will be activated automatically.</span>
        </div>
      </div>

      <!-- Actions -->
      <div class="pt-4 flex items-center justify-center gap-4">
        <button 
          @click="refreshPage"
          class="px-6 py-3 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-black text-xs transition-all shadow-lg flex items-center gap-2"
        >
          <RefreshCw class="w-4 h-4" />
          <span>Check Verification Status</span>
        </button>

        <Link 
          href="/logout" 
          method="post" 
          as="button"
          class="px-5 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs border border-slate-700"
        >
          Sign Out
        </Link>
      </div>

      <p class="text-[11px] text-slate-500 pt-2">
        Need urgent activation? Call Saba POS Support at <span class="text-slate-400 font-bold">+880 1711 000111</span>
      </p>

    </div>
  </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { Clock, ShieldAlert, Info, RefreshCw } from 'lucide-vue-next';

defineProps({
  tenant: Object,
});

const refreshPage = () => {
  router.reload();
};
</script>
