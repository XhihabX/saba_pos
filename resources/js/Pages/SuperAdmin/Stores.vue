<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-100 min-h-screen">
      <!-- Title & Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div class="flex items-center gap-2 text-rose-600 font-bold text-xs uppercase tracking-wider">
            <Store class="w-4 h-4" />
            <span>SaaS Outlets Infrastructure</span>
          </div>
          <h1 class="text-2xl font-black font-heading text-slate-900 mt-1">Platform Store Outlets Directory</h1>
          <p class="text-xs text-slate-500 mt-1">Global directory of all active and inactive branch outlets across all merchant businesses</p>
        </div>
      </div>

      <!-- Outlets Table Panel -->
      <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <h3 class="font-bold text-base font-heading text-slate-900">Total Outlets ({{ stores.total || stores.length || 0 }})</h3>
          
          <div class="w-full sm:w-72 relative">
            <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input 
              type="text" 
              v-model="searchQuery" 
              placeholder="Search outlet by name, code, or city..." 
              class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-900 focus:outline-none focus:border-rose-500"
            />
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] bg-slate-50">
                <th class="py-3 px-3">Outlet Name & Code</th>
                <th class="py-3 px-3">Merchant Business</th>
                <th class="py-3 px-3">Address & Location</th>
                <th class="py-3 px-3 text-center">Tax / VAT Rate</th>
                <th class="py-3 px-3 text-center">Status</th>
                <th class="py-3 px-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 font-sans">
              <tr v-for="store in filteredStores" :key="store.id" class="hover:bg-slate-50">
                <td class="py-3.5 px-3">
                  <span class="font-bold text-slate-900 text-sm font-heading block">{{ store.name }}</span>
                  <span class="text-[10px] font-mono text-slate-500">{{ store.code }} | {{ store.email || 'N/A' }}</span>
                </td>
                <td class="py-3.5 px-3">
                  <span class="font-bold text-indigo-700 block">{{ store.tenant?.name || 'Main Merchant' }}</span>
                  <span class="text-[10px] text-slate-500 font-mono">{{ store.tenant?.code }}</span>
                </td>
                <td class="py-3.5 px-3 text-slate-700">
                  {{ store.address || 'Dhaka, Bangladesh' }}
                </td>
                <td class="py-3.5 px-3 text-center font-mono font-bold text-slate-800">
                  {{ store.default_tax_rate }}% VAT
                </td>
                <td class="py-3.5 px-3 text-center">
                  <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase border', store.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200']">
                    {{ store.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td class="py-3.5 px-3 text-right">
                  <button @click="toggleStore(store)" :class="['px-2.5 py-1 rounded-lg font-bold text-[11px] border', store.is_active ? 'bg-amber-50 text-amber-800 border-amber-200 hover:bg-amber-600 hover:text-white' : 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-600 hover:text-white']">
                    {{ store.is_active ? 'Deactivate' : 'Activate' }}
                  </button>
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
import { Store, Search } from 'lucide-vue-next';

const props = defineProps({
  stores: [Object, Array],
});

const searchQuery = ref('');

const allStores = computed(() => {
  return Array.isArray(props.stores) ? props.stores : (props.stores?.data || []);
});

const filteredStores = computed(() => {
  if (!searchQuery.value.trim()) return allStores.value;
  const q = searchQuery.value.toLowerCase();
  return allStores.value.filter(s =>
    (s.name && s.name.toLowerCase().includes(q)) ||
    (s.code && s.code.toLowerCase().includes(q)) ||
    (s.tenant?.name && s.tenant.name.toLowerCase().includes(q))
  );
});

const toggleStore = (store) => {
  if (confirm(`Toggle active status for store outlet '${store.name}'?`)) {
    router.post(`/super-admin/stores/${store.id}/toggle`);
  }
};
</script>
