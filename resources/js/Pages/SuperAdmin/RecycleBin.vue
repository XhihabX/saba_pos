<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-100 min-h-screen">
      <!-- Title Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div class="flex items-center gap-2 text-rose-600 font-bold text-xs uppercase tracking-wider">
            <Trash2 class="w-4 h-4" />
            <span>SaaS Recycle Bin & Recovery Center</span>
          </div>
          <h1 class="text-2xl font-black font-heading text-slate-900 mt-1">Suspended Tenants & Inactive Outlets</h1>
          <p class="text-xs text-slate-500 mt-1">Review suspended merchant accounts, restore store outlets, and manage system retention</p>
        </div>
      </div>

      <!-- Suspended / Rejected Tenants Grid -->
      <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-4">
        <h3 class="font-bold text-base font-heading text-slate-900 flex items-center gap-2">
          <ShieldAlert class="w-5 h-5 text-amber-600" />
          <span>Suspended & Rejected Merchant Businesses</span>
        </h3>

        <div v-if="suspendedTenants && suspendedTenants.length > 0" class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] bg-slate-50">
                <th class="py-3 px-3">Business & Code</th>
                <th class="py-3 px-3">Contact Email</th>
                <th class="py-3 px-3">Plan Tier</th>
                <th class="py-3 px-3 text-center">Status</th>
                <th class="py-3 px-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr v-for="tenant in suspendedTenants" :key="tenant.id" class="hover:bg-slate-50">
                <td class="py-3.5 px-3">
                  <div class="font-bold text-slate-900 text-sm">{{ tenant.name }}</div>
                  <div class="text-[10px] text-slate-500 font-mono">{{ tenant.code }}</div>
                </td>
                <td class="py-3.5 px-3 text-slate-700">{{ tenant.email }}</td>
                <td class="py-3.5 px-3 text-indigo-700 font-bold">{{ tenant.plan_name }}</td>
                <td class="py-3.5 px-3 text-center">
                  <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-rose-50 text-rose-700 border border-rose-200">
                    {{ tenant.subscription_status }}
                  </span>
                </td>
                <td class="py-3.5 px-3 text-right">
                  <button 
                    @click="restoreTenant(tenant)"
                    class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs"
                  >
                    Restore & Activate
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="text-center py-8 text-slate-500 text-xs font-medium">
          No suspended or rejected merchant accounts in recycle bin.
        </div>
      </div>

      <!-- Inactive Store Outlets -->
      <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-4">
        <h3 class="font-bold text-base font-heading text-slate-900 flex items-center gap-2">
          <Store class="w-5 h-5 text-indigo-600" />
          <span>Inactive / Deactivated Store Outlets</span>
        </h3>

        <div v-if="inactiveStores && inactiveStores.length > 0" class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] bg-slate-50">
                <th class="py-3 px-3">Store Name & Code</th>
                <th class="py-3 px-3">Merchant Tenant</th>
                <th class="py-3 px-3">Address</th>
                <th class="py-3 px-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr v-for="store in inactiveStores" :key="store.id" class="hover:bg-slate-50">
                <td class="py-3.5 px-3">
                  <div class="font-bold text-slate-900 text-sm">{{ store.name }}</div>
                  <div class="text-[10px] text-slate-500 font-mono">{{ store.code }}</div>
                </td>
                <td class="py-3.5 px-3 text-slate-700 font-bold">{{ store.tenant?.name || 'N/A' }}</td>
                <td class="py-3.5 px-3 text-slate-600">{{ store.address || 'N/A' }}</td>
                <td class="py-3.5 px-3 text-right">
                  <button 
                    @click="restoreStore(store)"
                    class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-xs"
                  >
                    Reactivate Outlet
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="text-center py-8 text-slate-500 text-xs font-medium">
          No deactivated store outlets found.
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Trash2, ShieldAlert, Store } from 'lucide-vue-next';

defineProps({
  suspendedTenants: Array,
  inactiveStores: Array,
});

const restoreTenant = (tenant) => {
  if (confirm(`Restore and activate merchant subscription for '${tenant.name}'?`)) {
    router.post(`/super-admin/tenants/${tenant.id}/update`, {
      name: tenant.name,
      email: tenant.email,
      phone: tenant.phone,
      plan_name: tenant.plan_name,
      mrr_amount: tenant.mrr_amount,
      subscription_status: 'active',
    });
  }
};

const restoreStore = (store) => {
  if (confirm(`Reactivate store outlet '${store.name}'?`)) {
    router.post(`/super-admin/stores/${store.id}/toggle`);
  }
};
</script>
