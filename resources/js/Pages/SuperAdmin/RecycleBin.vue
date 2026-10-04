<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-8 bg-slate-50/70 min-h-screen">
      <!-- 🗑️ Hero Header Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-rose-950 to-slate-900 text-white p-6 sm:p-8 shadow-xl border border-slate-800">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-rose-500/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 space-y-2">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 font-bold text-xs uppercase tracking-wider">
            <Trash2 class="w-3.5 h-3.5" />
            <span>SaaS Recovery & Retention Center</span>
          </div>
          <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black font-heading tracking-tight text-white">
            Suspended Merchants & Inactive Outlets
          </h1>
          <p class="text-xs sm:text-sm text-slate-300 max-w-2xl">
            Audit soft-deleted records, reactivate suspended merchant accounts, and restore deactivated store outlets
          </p>
        </div>
      </div>

      <!-- 📊 Metric Stat Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Suspended Merchants</span>
            <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold">
              <ShieldAlert class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-rose-700">{{ (suspendedTenants || []).length }} Accounts</div>
          <div class="text-[11px] font-semibold text-rose-600">Pending recovery or termination</div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Inactive Outlets</span>
            <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
              <Store class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-amber-700">{{ (inactiveStores || []).length }} Outlets</div>
          <div class="text-[11px] font-semibold text-amber-600">Temporarily offline registers</div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">1-Click Restoration SLA</span>
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
              <RefreshCw class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-emerald-700">Instant Restore</div>
          <div class="text-[11px] font-semibold text-emerald-600">Full data retention guaranteed</div>
        </div>
      </div>

      <!-- 🛡️ Suspended / Rejected Tenants Panel -->
      <div class="p-6 sm:p-8 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-6">
        <div class="flex items-center justify-between">
          <h3 class="font-black text-xl font-heading text-slate-900 flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center">
              <ShieldAlert class="w-5 h-5" />
            </div>
            <span>Suspended & Rejected Merchant Businesses</span>
          </h3>
        </div>

        <div v-if="suspendedTenants && suspendedTenants.length > 0" class="overflow-x-auto rounded-2xl border border-slate-200/80">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] bg-slate-50/80 font-bold tracking-wider">
                <th class="py-4 px-4">Business & Code</th>
                <th class="py-4 px-4">Contact Email</th>
                <th class="py-4 px-4">Plan Tier</th>
                <th class="py-4 px-4 text-center">Status</th>
                <th class="py-4 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-sans">
              <tr v-for="tenant in suspendedTenants" :key="tenant.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="py-4 px-4">
                  <div class="font-bold text-slate-900 text-sm font-heading">{{ tenant.name }}</div>
                  <div class="text-[10px] text-slate-500 font-mono">{{ tenant.code }}</div>
                </td>
                <td class="py-4 px-4 text-slate-700 font-mono">{{ tenant.email }}</td>
                <td class="py-4 px-4">
                  <span class="px-2.5 py-1 rounded-lg bg-indigo-50 border border-indigo-100 text-indigo-700 font-extrabold text-xs">
                    {{ tenant.plan_name }}
                  </span>
                </td>
                <td class="py-4 px-4 text-center">
                  <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-50 text-rose-700 border border-rose-200">
                    {{ tenant.subscription_status }}
                  </span>
                </td>
                <td class="py-4 px-4 text-right space-x-2">
                  <button 
                    @click="restoreTenant(tenant)"
                    class="px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-md transition-all cursor-pointer"
                  >
                    Restore
                  </button>
                  <button 
                    @click="forceDeleteTenant(tenant)"
                    class="px-3.5 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs shadow-md transition-all cursor-pointer"
                  >
                    Permanently Delete
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="text-center py-12 text-slate-400 font-bold text-xs">
          No suspended or rejected merchant accounts in recycle bin.
        </div>
      </div>

      <!-- 🏬 Inactive Store Outlets Panel -->
      <div class="p-6 sm:p-8 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-6">
        <div class="flex items-center justify-between">
          <h3 class="font-black text-xl font-heading text-slate-900 flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
              <Store class="w-5 h-5" />
            </div>
            <span>Inactive / Deactivated Store Outlets</span>
          </h3>
        </div>

        <div v-if="inactiveStores && inactiveStores.length > 0" class="overflow-x-auto rounded-2xl border border-slate-200/80">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] bg-slate-50/80 font-bold tracking-wider">
                <th class="py-4 px-4">Store Name & Code</th>
                <th class="py-4 px-4">Merchant Chain</th>
                <th class="py-4 px-4">Address</th>
                <th class="py-4 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-sans">
              <tr v-for="store in inactiveStores" :key="store.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="py-4 px-4">
                  <div class="font-bold text-slate-900 text-sm font-heading">{{ store.name }}</div>
                  <div class="text-[10px] text-slate-500 font-mono">{{ store.code }}</div>
                </td>
                <td class="py-4 px-4 text-indigo-700 font-bold">{{ store.tenant?.name || 'N/A' }}</td>
                <td class="py-4 px-4 text-slate-600 font-medium">{{ store.address || 'Dhaka, Bangladesh' }}</td>
                <td class="py-4 px-4 text-right space-x-2">
                  <button 
                    @click="restoreStore(store)"
                    class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-md transition-all cursor-pointer"
                  >
                    Reactivate
                  </button>
                  <button 
                    @click="forceDeleteStore(store)"
                    class="px-3.5 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs shadow-md transition-all cursor-pointer"
                  >
                    Permanently Delete
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="text-center py-12 text-slate-400 font-bold text-xs">
          No deactivated store outlets found.
        </div>
      </div>

      <!-- 👤 Trashed Staff & User Accounts Panel -->
      <div class="p-6 sm:p-8 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-6">
        <div class="flex items-center justify-between">
          <h3 class="font-black text-xl font-heading text-slate-900 flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center">
              <Users class="w-5 h-5" />
            </div>
            <span>Trashed Staff & User Accounts</span>
          </h3>
        </div>

        <div v-if="trashedUsers && trashedUsers.length > 0" class="overflow-x-auto rounded-2xl border border-slate-200/80">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] bg-slate-50/80 font-bold tracking-wider">
                <th class="py-4 px-4">User Name</th>
                <th class="py-4 px-4">Email</th>
                <th class="py-4 px-4">Role</th>
                <th class="py-4 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-sans">
              <tr v-for="u in trashedUsers" :key="u.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="py-4 px-4 font-extrabold text-slate-900">{{ u.name }}</td>
                <td class="py-4 px-4 text-slate-600 font-mono">{{ u.email }}</td>
                <td class="py-4 px-4">
                  <span class="px-2 py-1 rounded bg-slate-100 text-slate-800 font-bold uppercase text-[10px]">{{ u.role }}</span>
                </td>
                <td class="py-4 px-4 text-right space-x-2">
                  <button @click="restoreUser(u)" class="px-3.5 py-1.5 rounded-xl bg-emerald-600 text-white font-extrabold text-xs">Restore</button>
                  <button @click="forceDeleteUser(u)" class="px-3.5 py-1.5 rounded-xl bg-rose-600 text-white font-extrabold text-xs">Permanently Delete</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="text-center py-12 text-slate-400 font-bold text-xs">
          No trashed user accounts.
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Trash2, ShieldAlert, Store, RefreshCw, Users } from 'lucide-vue-next';

defineProps({
  suspendedTenants: Array,
  inactiveStores: Array,
  trashedUsers: Array,
  trashedProducts: Array,
});

const restoreTenant = (tenant) => {
  if (confirm(`Restore and activate merchant subscription for '${tenant.name}'?`)) {
    router.post(`/super-admin/tenants/${tenant.id}/restore`);
  }
};

const forceDeleteTenant = (tenant) => {
  if (confirm(`⚠️ PERMANENT DELETE: Are you sure you want to permanently delete tenant '${tenant.name}'? This cannot be undone.`)) {
    router.delete(`/super-admin/tenants/${tenant.id}/force`);
  }
};

const restoreStore = (store) => {
  if (confirm(`Reactivate store outlet '${store.name}'?`)) {
    router.post(`/super-admin/stores/${store.id}/restore`);
  }
};

const forceDeleteStore = (store) => {
  if (confirm(`⚠️ PERMANENT DELETE: Are you sure you want to permanently delete store '${store.name}'?`)) {
    router.delete(`/super-admin/stores/${store.id}/force`);
  }
};

const restoreUser = (u) => {
  if (confirm(`Restore user account '${u.name}'?`)) {
    router.post(`/super-admin/users/${u.id}/restore`);
  }
};

const forceDeleteUser = (u) => {
  if (confirm(`⚠️ PERMANENT DELETE: Are you sure you want to permanently purge user '${u.name}'?`)) {
    router.delete(`/super-admin/users/${u.id}/force`);
  }
};
</script>

