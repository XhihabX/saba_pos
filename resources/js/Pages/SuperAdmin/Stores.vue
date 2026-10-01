<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-8 bg-slate-50/70 min-h-screen">
      <!-- 🏢 Header Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-rose-950 to-slate-900 text-white p-6 sm:p-8 shadow-xl border border-slate-800">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-rose-500/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div class="space-y-2">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 font-bold text-xs uppercase tracking-wider">
              <Store class="w-3.5 h-3.5" />
              <span>SaaS Outlets Infrastructure</span>
            </div>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black font-heading tracking-tight text-white">
              Global Store Outlets Directory
            </h1>
            <p class="text-xs sm:text-sm text-slate-300 max-w-2xl">
              Directory of all active and inactive branch outlets, cash registers, tax structures, and regional coverage across all merchants
            </p>
          </div>

          <button 
            @click="showCreateModal = true"
            class="px-5 py-3 rounded-2xl bg-gradient-to-r from-rose-600 to-amber-500 hover:from-rose-500 hover:to-amber-400 text-white font-black text-xs shadow-lg shadow-rose-600/30 flex items-center gap-2 cursor-pointer shrink-0"
          >
            <Plus class="w-4 h-4 stroke-[3]" />
            <span>+ Create Store Outlet</span>
          </button>
        </div>
      </div>

      <!-- 📊 Stat Metrics Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Outlets</span>
            <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold">
              <Store class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-slate-900">{{ allStores.length }} Outlets</div>
          <div class="text-[11px] font-semibold text-slate-500">Across all onboarded merchant chains</div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Active Registers</span>
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
              <CheckCircle2 class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-emerald-700">{{ activeStoresCount }} Active</div>
          <div class="text-[11px] font-semibold text-emerald-600">Processing POS transactions</div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Deactivated Outlets</span>
            <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
              <Building2 class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-amber-700">{{ inactiveStoresCount }} Offline</div>
          <div class="text-[11px] font-semibold text-amber-600">Temporarily paused branches</div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Avg Tax / VAT Rate</span>
            <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
              <MapPin class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-slate-900">5.0% VAT</div>
          <div class="text-[11px] font-semibold text-indigo-600">Standard Bangladesh VAT compliance</div>
        </div>
      </div>

      <!-- 🏬 Outlets Directory & Control Panel -->
      <div class="p-6 sm:p-8 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h3 class="font-black text-xl font-heading text-slate-900">Outlets Master Directory</h3>
            <p class="text-xs text-slate-500">Search and manage store locations across all merchant chains</p>
          </div>

          <div class="flex items-center gap-3 w-full sm:w-auto">
            <select v-model="statusFilter" class="px-3.5 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-xs font-bold text-slate-700 focus:outline-none focus:border-rose-500">
              <option value="">All Statuses</option>
              <option value="active">Active Outlets</option>
              <option value="inactive">Inactive Outlets</option>
            </select>

            <div class="w-full sm:w-72 relative">
              <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input 
                type="text" 
                v-model="searchQuery" 
                placeholder="Search outlet name, code, city..." 
                class="w-full pl-10 pr-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-xs text-slate-900 focus:outline-none focus:border-rose-500"
              />
            </div>
          </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200/80">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] bg-slate-50/80 font-bold tracking-wider">
                <th class="py-4 px-4">Outlet Name & Code</th>
                <th class="py-4 px-4">Merchant Chain</th>
                <th class="py-4 px-4">Location & Address</th>
                <th class="py-4 px-4 text-center">Tax Rate</th>
                <th class="py-4 px-4 text-center">Status</th>
                <th class="py-4 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-sans">
              <tr v-for="store in filteredStores" :key="store.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="py-4 px-4">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold shrink-0">
                      <Store class="w-4 h-4" />
                    </div>
                    <div>
                      <span class="font-bold text-slate-900 text-sm font-heading block">{{ store.name }}</span>
                      <span class="text-[10px] font-mono text-slate-500">{{ store.code }} | {{ store.email || 'no-email@store.com' }}</span>
                    </div>
                  </div>
                </td>
                <td class="py-4 px-4">
                  <span class="font-extrabold text-indigo-700 block text-xs">{{ store.tenant?.name || 'Platform HQ' }}</span>
                  <span class="text-[10px] text-slate-500 font-mono">{{ store.tenant?.code || 'N/A' }}</span>
                </td>
                <td class="py-4 px-4 text-slate-700">
                  <div class="flex items-center gap-1.5 font-medium">
                    <MapPin class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                    <span>{{ store.address || 'Dhaka, Bangladesh' }}</span>
                  </div>
                </td>
                <td class="py-4 px-4 text-center font-mono font-bold text-slate-800">
                  <span class="px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200">
                    {{ store.default_tax_rate || 5 }}% VAT
                  </span>
                </td>
                <td class="py-4 px-4 text-center">
                  <span :class="['px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border', store.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200']">
                    {{ store.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td class="py-4 px-4 text-right">
                  <button 
                    @click="toggleStore(store)" 
                    :class="['px-3 py-1.5 rounded-xl font-extrabold text-xs border transition-all cursor-pointer', store.is_active ? 'bg-amber-50 text-amber-800 border-amber-200 hover:bg-amber-600 hover:text-white' : 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-600 hover:text-white']"
                  >
                    {{ store.is_active ? 'Deactivate' : 'Activate' }}
                  </button>
                </td>
              </tr>
              <tr v-if="filteredStores.length === 0">
                <td colspan="6" class="py-12 text-center text-slate-400">
                  <Store class="w-8 h-8 mx-auto mb-2 opacity-50" />
                  <p class="font-bold text-xs">No store outlets found matching your criteria</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- CREATE STORE OUTLET MODAL -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
      <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 sm:p-8 space-y-6 shadow-2xl animate-in fade-in zoom-in duration-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200">
          <div>
            <h3 class="font-black text-xl font-heading text-slate-900">Create New Store Outlet</h3>
            <p class="text-xs text-slate-500">Add a new physical branch register</p>
          </div>
          <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-700">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submitCreateStore" class="space-y-4 text-xs">
          <div>
            <label class="block text-slate-700 font-bold mb-1">Outlet Name</label>
            <input type="text" v-model="createForm.name" required placeholder="e.g. Dhanmondi Flagship Outlet" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-bold focus:outline-none focus:border-rose-500" />
          </div>

          <div>
            <label class="block text-slate-700 font-bold mb-1">Outlet Code</label>
            <input type="text" v-model="createForm.code" required placeholder="STR-001" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-mono focus:outline-none focus:border-rose-500" />
          </div>

          <div>
            <label class="block text-slate-700 font-bold mb-1">Physical Address</label>
            <textarea v-model="createForm.address" rows="2" placeholder="House 12, Road 5, Dhanmondi, Dhaka" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500"></textarea>
          </div>

          <div>
            <label class="block text-slate-700 font-bold mb-1">Default Tax / VAT Rate (%)</label>
            <input type="number" step="0.01" v-model.number="createForm.default_tax_rate" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-mono font-bold focus:outline-none focus:border-rose-500" />
          </div>

          <div class="pt-4 flex gap-3">
            <button type="submit" class="flex-1 py-3.5 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs shadow-md cursor-pointer">
              Save Store Outlet
            </button>
            <button type="button" @click="showCreateModal = false" class="px-6 py-3.5 rounded-2xl bg-slate-100 text-slate-700 font-bold text-xs hover:bg-slate-200 cursor-pointer">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Store, Search, Plus, MapPin, CheckCircle2, Building2, X } from 'lucide-vue-next';

const props = defineProps({
  stores: [Object, Array],
});

const searchQuery = ref('');
const statusFilter = ref('');
const showCreateModal = ref(false);

const createForm = useForm({
  name: '',
  code: '',
  address: '',
  default_tax_rate: 5.0,
});

const allStores = computed(() => {
  return Array.isArray(props.stores) ? props.stores : (props.stores?.data || []);
});

const activeStoresCount = computed(() => allStores.value.filter(s => s.is_active).length);
const inactiveStoresCount = computed(() => allStores.value.filter(s => !s.is_active).length);

const filteredStores = computed(() => {
  let list = allStores.value;
  if (statusFilter.value === 'active') {
    list = list.filter(s => s.is_active);
  } else if (statusFilter.value === 'inactive') {
    list = list.filter(s => !s.is_active);
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(s =>
      (s.name && s.name.toLowerCase().includes(q)) ||
      (s.code && s.code.toLowerCase().includes(q)) ||
      (s.address && s.address.toLowerCase().includes(q)) ||
      (s.tenant?.name && s.tenant.name.toLowerCase().includes(q))
    );
  }
  return list;
});

const toggleStore = (store) => {
  if (confirm(`Toggle active status for store outlet '${store.name}'?`)) {
    router.post(`/super-admin/stores/${store.id}/toggle`);
  }
};

const submitCreateStore = () => {
  createForm.post('/super-admin/stores', {
    onSuccess: () => {
      showCreateModal.value = false;
      createForm.reset();
    }
  });
};
</script>

