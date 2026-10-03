<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-50 dark:bg-slate-950 min-h-screen text-slate-900 dark:text-slate-100 selection:bg-indigo-500 selection:text-white transition-colors duration-200">
      <!-- Dark Hero Banner -->
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
              Monitor active SaaS plan tier, monthly recurring payments (MRR), license renewals, and billing history.
            </p>
          </div>
          <div class="flex items-center gap-3">
            <span class="px-4 py-2 rounded-2xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 font-extrabold text-xs flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
              STATUS: {{ (tenant?.subscription_status || 'ACTIVE').toUpperCase() }}
            </span>
          </div>
        </div>
      </div>

      <!-- Plan Cards Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Growth Plan Card -->
        <div class="bg-white dark:bg-slate-900/90 border-2 border-indigo-500 rounded-3xl p-6 relative flex flex-col justify-between shadow-xl dark:shadow-2xl">
          <div class="absolute -top-3.5 right-6 px-3 py-1 rounded-full bg-indigo-500 text-white font-extrabold text-[10px] uppercase tracking-wider shadow-lg">
            ACTIVE PLAN
          </div>
          <div>
            <div class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">Growth Multi-Store</div>
            <div class="text-3xl font-black font-heading text-slate-900 dark:text-white mt-2">৳3,999 <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">/ mo</span></div>
            <p class="text-slate-600 dark:text-slate-400 text-xs mt-2 border-b border-slate-100 dark:border-slate-800 pb-4">Ideal for multi-outlet retail chains requiring real-time inventory sync and cashier staff management.</p>
            
            <ul class="mt-4 space-y-2.5 text-xs text-slate-700 dark:text-slate-300 font-medium">
              <li class="flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 text-emerald-500 dark:text-emerald-400 shrink-0" />
                <span>Up to 5 Store Outlet Branches</span>
              </li>
              <li class="flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 text-emerald-500 dark:text-emerald-400 shrink-0" />
                <span>Unlimited Cashier Terminal Logins</span>
              </li>
              <li class="flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 text-emerald-500 dark:text-emerald-400 shrink-0" />
                <span>Offline PWA Selling & Local Cache</span>
              </li>
              <li class="flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 text-emerald-500 dark:text-emerald-400 shrink-0" />
                <span>SMS Gateway & Thermal Printers</span>
              </li>
            </ul>
          </div>
          <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800">
            <button class="w-full py-3 rounded-2xl bg-indigo-600 dark:bg-indigo-500 text-white font-extrabold text-xs shadow-lg shadow-indigo-500/20 cursor-default">
              Current Active Tier
            </button>
          </div>
        </div>

        <!-- Enterprise Tier Card -->
        <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 flex flex-col justify-between shadow-lg">
          <div>
            <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Enterprise Unlimited</div>
            <div class="text-3xl font-black font-heading text-slate-900 dark:text-white mt-2">৳8,999 <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">/ mo</span></div>
            <p class="text-slate-600 dark:text-slate-400 text-xs mt-2 border-b border-slate-100 dark:border-slate-800 pb-4">Designed for mega retail brands requiring custom API integrations, dedicated DB, and 24/7 priority support.</p>
            
            <ul class="mt-4 space-y-2.5 text-xs text-slate-600 dark:text-slate-400 font-medium">
              <li class="flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 text-indigo-500 dark:text-indigo-400 shrink-0" />
                <span>Unlimited Store Outlets</span>
              </li>
              <li class="flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 text-indigo-500 dark:text-indigo-400 shrink-0" />
                <span>Dedicated Cloud Server & DB</span>
              </li>
              <li class="flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 text-indigo-500 dark:text-indigo-400 shrink-0" />
                <span>Custom ERP & Accounting Sync</span>
              </li>
              <li class="flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 text-indigo-500 dark:text-indigo-400 shrink-0" />
                <span>24/7 VIP Phone Support</span>
              </li>
            </ul>
          </div>
          <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800">
            <button class="w-full py-3 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-extrabold text-xs transition-colors">
              Upgrade to Enterprise
            </button>
          </div>
        </div>

        <!-- Billing Summary Card -->
        <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 flex flex-col justify-between shadow-lg">
          <div>
            <div class="text-xs font-bold text-cyan-600 dark:text-cyan-400 uppercase tracking-wider flex items-center gap-2">
              <Zap class="w-4 h-4" />
              <span>Billing Cycle & Invoices</span>
            </div>

            <div class="mt-4 space-y-3">
              <div class="bg-slate-50 dark:bg-slate-950 p-4 rounded-2xl border border-slate-200 dark:border-slate-800">
                <span class="text-slate-500 dark:text-slate-400 text-[10px] uppercase font-bold block">Next Renewal Date</span>
                <span class="text-lg font-black text-slate-900 dark:text-white font-mono mt-0.5 block">30 October 2026</span>
                <span class="text-emerald-600 dark:text-emerald-400 text-xs font-bold mt-1 inline-block">● Auto-renew Enabled</span>
              </div>

              <div class="bg-slate-50 dark:bg-slate-950 p-4 rounded-2xl border border-slate-200 dark:border-slate-800">
                <span class="text-slate-500 dark:text-slate-400 text-[10px] uppercase font-bold block">Payment Method</span>
                <div class="flex items-center gap-2 mt-1">
                  <span class="px-2.5 py-1 rounded bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30 font-bold text-xs font-mono">bKash Merchant</span>
                  <span class="text-xs text-slate-700 dark:text-slate-300 font-mono">017****8899</span>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800">
            <button class="w-full py-3 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-extrabold text-xs transition-colors">
              Download Latest PDF Invoice
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { CreditCard, CheckCircle2, Zap } from 'lucide-vue-next';

defineProps({
  tenant: { type: Object, default: () => ({}) },
});
</script>
