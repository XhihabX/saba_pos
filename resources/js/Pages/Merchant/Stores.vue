<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-100 min-h-screen">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div class="flex items-center gap-2 text-indigo-600 font-bold text-xs uppercase tracking-wider">
            <Building2 class="w-4 h-4" />
            <span>Merchant HQ Portal</span>
          </div>
          <h1 class="text-2xl font-black font-heading text-slate-900 mt-1">Multi-Outlet Store Branches</h1>
          <p class="text-slate-500 text-xs mt-1">Manage physical retail stores, tax rules, and receipt configurations.</p>
        </div>
        <button 
          @click="openAddModal"
          class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-md flex items-center gap-2 shrink-0"
        >
          <Plus class="w-4 h-4" />
          <span>Add New Store Branch</span>
        </button>
      </div>

      <!-- Stores List -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div 
          v-for="store in stores" 
          :key="store.id"
          class="bg-white border border-slate-200 rounded-2xl p-6 hover:border-indigo-500 hover:shadow-md transition-all flex flex-col justify-between shadow-xs"
        >
          <div>
            <div class="flex items-center justify-between">
              <div class="px-2.5 py-1 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 text-xs font-mono font-bold">
                {{ store.code }}
              </div>
              <span class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Active Outlet
              </span>
            </div>

            <h3 class="text-lg font-bold text-slate-900 mt-3">{{ store.name }}</h3>
            <p class="text-slate-500 text-xs mt-1 flex items-center gap-1.5">
              <MapPin class="w-3.5 h-3.5 text-slate-400" />
              {{ store.address || 'Central District Store' }}
            </p>

            <div class="grid grid-cols-2 gap-3 mt-4 pt-4 border-t border-slate-100 text-xs">
              <div>
                <span class="text-slate-400 block font-semibold">Staff Assigned</span>
                <span class="font-bold text-slate-900 text-sm">{{ store.users_count || 0 }} Members</span>
              </div>
              <div>
                <span class="text-slate-400 block font-semibold">Total Orders</span>
                <span class="font-bold text-slate-900 text-sm">{{ store.orders_count || 0 }} Sales</span>
              </div>
              <div>
                <span class="text-slate-400 block font-semibold">Tax Rate</span>
                <span class="font-bold text-slate-800">{{ store.default_tax_rate }}%</span>
              </div>
              <div>
                <span class="text-slate-400 block font-semibold">Currency</span>
                <span class="font-bold text-slate-800">{{ store.currency_symbol }} (BDT)</span>
              </div>
            </div>
          </div>

          <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between">
            <span class="text-xs text-slate-400 font-mono">ID: #{{ store.id }}</span>
            <div class="flex items-center gap-2">
              <button @click="openEditModal(store)" class="px-3 py-1 rounded-lg bg-indigo-50 text-indigo-700 font-bold text-xs hover:bg-indigo-100 transition-colors flex items-center gap-1">
                <Edit3 class="w-3.5 h-3.5" />
                <span>Edit</span>
              </button>
              <button @click="deleteStore(store)" class="p-1 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-slate-100 transition-colors" title="Delete Outlet">
                <Trash2 class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Add / Edit Store Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div class="bg-white border border-slate-200 rounded-3xl p-6 w-full max-w-lg shadow-2xl space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-slate-200">
            <h3 class="text-lg font-bold text-slate-900">{{ isEditing ? 'Edit Store Branch' : 'Add New Store Branch' }}</h3>
            <button @click="showModal = false" class="text-slate-400 hover:text-slate-700">&times;</button>
          </div>

          <form @submit.prevent="submitForm" class="space-y-4 text-xs">
            <div>
              <label class="block text-slate-700 font-semibold mb-1">Outlet Branch Name</label>
              <input v-model="form.name" required type="text" placeholder="e.g. Uttara Branch Outlet" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:border-indigo-600" />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-slate-700 font-semibold mb-1">Phone Number</label>
                <input v-model="form.phone" type="text" placeholder="+880 1700-000000" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:border-indigo-600" />
              </div>
              <div>
                <label class="block text-slate-700 font-semibold mb-1">Email Address</label>
                <input v-model="form.email" type="email" placeholder="uttara@store.com" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:border-indigo-600" />
              </div>
            </div>

            <div>
              <label class="block text-slate-700 font-semibold mb-1">Store Physical Address</label>
              <textarea v-model="form.address" rows="2" placeholder="Sector 7, Uttara, Dhaka" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:border-indigo-600"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-slate-700 font-semibold mb-1">VAT/Tax Rate (%)</label>
                <input v-model="form.default_tax_rate" type="number" step="0.01" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:border-indigo-600" />
              </div>
              <div>
                <label class="block text-slate-700 font-semibold mb-1">Currency Symbol</label>
                <input v-model="form.currency_symbol" type="text" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:border-indigo-600" />
              </div>
            </div>

            <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
              <button type="button" @click="showModal = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold">Cancel</button>
              <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-md">{{ isEditing ? 'Update Branch' : 'Create Branch' }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Building2, Plus, MapPin, Edit3, Trash2 } from 'lucide-vue-next';

defineProps({
  stores: Array,
});

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
