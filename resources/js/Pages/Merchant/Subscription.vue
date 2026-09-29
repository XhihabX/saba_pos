<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-100 min-h-screen">
      <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <h1 class="text-2xl font-extrabold font-heading text-slate-900">SaaS Subscription & Billing</h1>
        <p class="text-xs text-slate-500 mt-1">Manage your merchant SaaS subscription tier and renewal status</p>
      </div>

      <div class="p-8 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-6 max-w-4xl">
        <div class="flex items-center justify-between pb-6 border-b border-slate-200">
          <div>
            <div class="text-xs text-slate-500 uppercase font-bold">Active Plan Tier</div>
            <div class="text-2xl font-extrabold font-heading text-indigo-700">{{ tenant.plan_name || 'Growth Multi-Store' }}</div>
          </div>
          <span :class="['px-3 py-1 rounded-full text-xs font-extrabold uppercase', tenant.subscription_status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200']">
            {{ tenant.subscription_status || 'active' }}
          </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
          <div>
            <span class="text-slate-500 text-xs block mb-1 font-semibold">Monthly Billing Fee</span>
            <span class="font-bold text-slate-900 font-mono text-lg">৳{{ tenant.mrr_amount || 3999 }}.00 / mo</span>
          </div>
          <div>
            <span class="text-slate-500 text-xs block mb-1 font-semibold">Renewal Due Date</span>
            <span class="font-bold text-slate-800 font-mono text-lg">30 Days Remaining</span>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineProps({
  tenant: Object,
});
</script>
