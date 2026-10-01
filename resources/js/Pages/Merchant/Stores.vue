<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-900 min-h-screen text-slate-100 selection:bg-indigo-500 selection:text-white">
      <!-- Dark Hero Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 border border-indigo-500/20 p-6 sm:p-8 shadow-2xl">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 font-mono text-xs font-bold uppercase tracking-wider mb-3">
              <Building2 class="w-4 h-4" />
              <span>Merchant HQ Enterprise</span>
            </div>
            <h1 class="text-3xl font-black font-heading tracking-tight text-white">Multi-Outlet Branch Directory</h1>
            <p class="text-slate-400 text-xs sm:text-sm mt-1 max-w-2xl">
              Configure retail outlets, local tax compliance rules, branch staff allocations, and receipt currency standards.
            </p>
          </div>
          <button 
            @click="openAddModal"
            class="px-5 py-3 rounded-2xl bg-gradient-to-r from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 text-white font-extrabold text-xs shadow-lg shadow-indigo-500/20 flex items-center gap-2 shrink-0 transition-all hover:scale-105 active:scale-95"
          >
            <Plus class="w-4 h-4" />
            <span>Add New Outlet Branch</span>
          </button>
        </div>
      </div>

      <!-- KPI Stat Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5 backdrop-blur-md flex items-center justify-between shadow-lg">
          <div>
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Total Branches</span>
            <span class="text-2xl font-black font-heading text-white mt-1 block">{{ stores.length }} Outlets</span>
          </div>
          <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 flex items-center justify-center font-bold">
            <Building2 class="w-6 h-6" />
          </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5 backdrop-blur-md flex items-center justify-between shadow-lg">
          <div>
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Active Registers</span>
            <span class="text-2xl font-black font-heading text-emerald-400 mt-1 block">{{ activeStoresCount }} Active</span>
          </div>
          <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center font-bold">
            <Store class="w-6 h-6" />
          </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5 backdrop-blur-md flex items-center justify-between shadow-lg">
          <div>
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Assigned Staff</span>
            <span class="text-2xl font-black font-heading text-purple-400 mt-1 block">{{ totalStaffCount }} Staff</span>
          </div>
          <div class="w-12 h-12 rounded-2xl bg-purple-500/10 border border-purple-500/30 text-purple-400 flex items-center justify-center font-bold">
            <Users class="w-6 h-6" />
          </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5 backdrop-blur-md flex items-center justify-between shadow-lg">
          <div>
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Total Sales</span>
            <span class="text-2xl font-black font-heading text-cyan-400 mt-1 block">{{ totalOrdersCount }} Orders</span>
          </div>
          <div class="w-12 h-12 rounded-2xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 flex items-center justify-center font-bold">
            <ShoppingBag class="w-6 h-6" />
          </div>
        </div>
      </div>

      <!-- Search & Stores List -->
      <div class="space-y-4">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
          <div class="relative w-full sm:w-80">
            <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input 
              v-model="searchQuery" 
              type="text" 
              placeholder="Search outlets by name or code..." 
              class="w-full pl-10 pr-4 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-xs text-slate-100 placeholder-slate-400 focus:outline-none focus:border-indigo-500"
            />
          </div>
          <span class="text-xs text-slate-400 font-mono">Showing {{ filteredStores.length }} of {{ stores.length }} store outlets</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div 
            v-for="store in filteredStores" 
            :key="store.id"
            class="bg-slate-800/90 border border-slate-700/80 rounded-3xl p-6 hover:border-indigo-500/60 hover:shadow-2xl transition-all flex flex-col justify-between group"
          >
            <div>
              <div class="flex items-center justify-between">
                <div class="px-3 py-1 rounded-xl bg-indigo-500/10 text-indigo-400 border border-indigo-500/30 font-mono font-bold text-xs">
                  {{ store.code || `STR-${store.id}` }}
                </div>
                <span class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-400 bg-emerald-500/10 border border-emerald-500/30 px-3 py-1 rounded-full">
                  <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                  Active Outlet
                </span>
              </div>

              <h3 class="text-xl font-bold text-white mt-4 group-hover:text-indigo-400 transition-colors font-heading">{{ store.name }}</h3>
              <p class="text-slate-400 text-xs mt-1.5 flex items-center gap-1.5">
                <MapPin class="w-3.5 h-3.5 text-slate-500 shrink-0" />
                <span>{{ store.address || 'Central District Store Location' }}</span>
              </p>

              <div class="grid grid-cols-2 gap-3 mt-5 pt-4 border-t border-slate-700/60 text-xs">
                <div class="bg-slate-900/50 p-3 rounded-xl border border-slate-800">
                  <span class="text-slate-400 block font-semibold text-[10px] uppercase">Staff Assigned</span>
                  <span class="font-bold text-white text-sm mt-0.5 block">{{ store.users_count || 0 }} Members</span>
                </div>
                <div class="bg-slate-900/50 p-3 rounded-xl border border-slate-800">
                  <span class="text-slate-400 block font-semibold text-[10px] uppercase">Total Orders</span>
                  <span class="font-bold text-white text-sm mt-0.5 block">{{ store.orders_count || 0 }} Sales</span>
                </div>
                <div class="bg-slate-900/50 p-3 rounded-xl border border-slate-800">
                  <span class="text-slate-400 block font-semibold text-[10px] uppercase">Tax / VAT Rate</span>
                  <span class="font-bold text-indigo-400 mt-0.5 block">{{ store.default_tax_rate }}%</span>
                </div>
                <div class="bg-slate-900/50 p-3 rounded-xl border border-slate-800">
                  <span class="text-slate-400 block font-semibold text-[10px] uppercase">Currency</span>
                  <span class="font-bold text-emerald-400 mt-0.5 block">{{ store.currency_symbol }} (BDT)</span>
                </div>
              </div>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-700/60 flex items-center justify-between">
              <span class="text-xs text-slate-500 font-mono">ID: #{{ store.id }}</span>
              <div class="flex items-center gap-2">
                <button 
                  @click="openEditModal(store)" 
                  class="px-3.5 py-1.5 rounded-xl bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 font-bold text-xs transition-colors flex items-center gap-1.5"
                >
                  <Edit3 class="w-3.5 h-3.5" />
                  <span>Edit Outlet</span>
                </button>
                <button 
                  @click="deleteStore(store)" 
                  class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 transition-colors" 
                  title="Delete Outlet Branch"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Add / Edit Store Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-md p-4">
        <div class="bg-slate-900 border border-slate-700 rounded-3xl p-6 w-full max-w-lg shadow-2xl space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <div class="flex items-center gap-2 text-indigo-400 font-bold text-sm">
              <Building2 class="w-5 h-5" />
              <h3 class="text-lg font-black text-white font-heading">{{ isEditing ? 'Edit Store Branch' : 'Add New Store Branch' }}</h3>
            </div>
            <button @click="showModal = false" class="text-slate-400 hover:text-white text-xl font-bold">&times;</button>
          </div>

          <form @submit.prevent="submitForm" class="space-y-4 text-xs">
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Outlet Branch Name</label>
              <input 
                v-model="form.name" 
                required 
                type="text" 
                placeholder="e.g. Uttara Branch Outlet" 
                class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-slate-100 focus:outline-none focus:border-indigo-500" 
              />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-slate-300 font-semibold mb-1">Phone Number</label>
                <input 
                  v-model="form.phone" 
                  type="text" 
                  placeholder="+880 1700-000000" 
                  class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-slate-100 focus:outline-none focus:border-indigo-500" 
                />
              </div>
              <div>
                <label class="block text-slate-300 font-semibold mb-1">Email Address</label>
                <input 
                  v-model="form.email" 
                  type="email" 
                  placeholder="uttara@store.com" 
                  class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-slate-100 focus:outline-none focus:border-indigo-500" 
                />
              </div>
            </div>

            <div>
              <label class="block text-slate-300 font-semibold mb-1">Store Physical Address</label>
              <textarea 
                v-model="form.address" 
                rows="2" 
                placeholder="Sector 7, Uttara, Dhaka" 
                class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-slate-100 focus:outline-none focus:border-indigo-500"
              ></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-slate-300 font-semibold mb-1">VAT/Tax Rate (%)</label>
                <input 
                  v-model="form.default_tax_rate" 
                  type="number" 
                  step="0.01" 
                  class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-slate-100 focus:outline-none focus:border-indigo-500" 
                />
              </div>
              <div>
                <label class="block text-slate-300 font-semibold mb-1">Currency Symbol</label>
                <input 
                  v-model="form.currency_symbol" 
                  type="text" 
                  class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-slate-100 focus:outline-none focus:border-indigo-500" 
                />
              </div>
            </div>

            <div class="pt-4 border-t border-slate-800 flex justify-end gap-3">
              <button 
                type="button" 
                @click="showModal = false" 
                class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl font-semibold"
              >
                Cancel
              </button>
              <button 
                type="submit" 
                class="px-5 py-2.5 bg-gradient-to-r from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-500/20"
              >
                {{ isEditing ? 'Update Branch' : 'Create Branch' }}
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
import { Building2, Plus, MapPin, Edit3, Trash2, Store, Users, ShoppingBag, Search } from 'lucide-vue-next';

const props = defineProps({
  stores: { type: Array, default: () => [] },
});

const searchQuery = ref('');
const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = ref({
  name: '',
  phone: '',
  email: '',
  address: '',
  default_tax_rate: 5.0,
  currency_symbol: '৳',
});

const activeStoresCount = computed(() => props.stores.length);
const totalStaffCount = computed(() => props.stores.reduce((acc, s) => acc + (s.users_count || 0), 0));
const totalOrdersCount = computed(() => props.stores.reduce((acc, s) => acc + (s.orders_count || 0), 0));

const filteredStores = computed(() => {
  if (!searchQuery.value.trim()) return props.stores;
  const q = searchQuery.value.toLowerCase().trim();
  return props.stores.filter(s => 
    s.name.toLowerCase().includes(q) || 
    (s.code && s.code.toLowerCase().includes(q)) ||
    (s.address && s.address.toLowerCase().includes(q))
  );
});

const openAddModal = () => {
  isEditing.value = false;
  editingId.value = null;
  form.value = {
    name: '',
    phone: '',
    email: '',
    address: '',
    default_tax_rate: 5.0,
    currency_symbol: '৳',
  };
  showModal.value = true;
};

const openEditModal = (store) => {
  isEditing.value = true;
  editingId.value = store.id;
  form.value = {
    name: store.name,
    phone: store.phone || '',
    email: store.email || '',
    address: store.address || '',
    default_tax_rate: store.default_tax_rate,
    currency_symbol: store.currency_symbol,
  };
  showModal.value = true;
};

const submitForm = () => {
  if (isEditing.value) {
    router.post(`/merchant/stores/${editingId.value}/update`, form.value, {
      onSuccess: () => {
        showModal.value = false;
      }
    });
  } else {
    router.post('/merchant/stores', form.value, {
      onSuccess: () => {
        showModal.value = false;
      }
    });
  }
};

const deleteStore = (store) => {
  if (confirm(`Are you sure you want to delete outlet branch '${store.name}'?`)) {
    router.delete(`/merchant/stores/${store.id}`);
  }
};
</script>
