<template>
  <div class="min-h-screen bg-slate-900 flex flex-col justify-center items-center p-4 selection:bg-rose-500 selection:text-white">
    <div class="max-w-md w-full bg-slate-800/90 border border-slate-700/80 rounded-3xl p-8 shadow-2xl backdrop-blur-sm text-center space-y-6">
      <!-- Warning Icon -->
      <div class="w-16 h-16 mx-auto rounded-2xl bg-rose-500/10 border border-rose-500/30 flex items-center justify-center text-rose-400">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
      </div>

      <!-- Header & Message -->
      <div class="space-y-2">
        <span class="px-3 py-1 rounded-full bg-rose-500/10 text-rose-400 border border-rose-500/30 text-[10px] font-black uppercase tracking-widest font-mono">
          Service Inactive
        </span>
        <h1 class="text-2xl font-black text-white font-heading tracking-tight">
          Subscription Expired
        </h1>
        <p class="text-xs text-slate-400 leading-relaxed max-w-sm mx-auto">
          Subscription expired. Ask the store owner to renew.
        </p>
      </div>

      <!-- Tenant Info Card -->
      <div v-if="tenant" class="bg-slate-900/60 border border-slate-700/50 rounded-2xl p-4 text-left space-y-1.5 font-mono text-xs">
        <div class="flex justify-between text-slate-400">
          <span>Store / Tenant:</span>
          <span class="font-bold text-slate-200">{{ tenant.name }}</span>
        </div>
        <div class="flex justify-between text-slate-400">
          <span>Plan:</span>
          <span class="font-semibold text-slate-300">{{ tenant.plan_name || 'Standard' }}</span>
        </div>
        <div class="flex justify-between text-slate-400">
          <span>Current Status:</span>
          <span class="font-bold text-rose-400 uppercase tracking-wider">{{ tenant.subscription_status || 'EXPIRED' }}</span>
        </div>
      </div>

      <!-- Action Button -->
      <div class="pt-2">
        <form @submit.prevent="logout">
          <button
            type="submit"
            class="w-full py-3.5 px-4 rounded-xl bg-slate-700 hover:bg-slate-600 active:scale-98 text-white font-bold text-xs uppercase tracking-wider transition-all duration-150 shadow-lg"
          >
            Sign Out
          </button>
        </form>
      </div>
    </div>

    <!-- Footer Note -->
    <p class="text-[11px] text-slate-500 mt-6 font-mono text-center">
      IOT POS Enterprise Cloud &bull; Regional SaaS Platform
    </p>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';

defineProps({
  tenant: Object,
});

const logout = () => {
  router.post('/logout');
};
</script>
