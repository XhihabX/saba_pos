<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-900 min-h-screen text-slate-100 selection:bg-indigo-500 selection:text-white">
      <!-- Dark Hero Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 border border-indigo-500/20 p-6 sm:p-8 shadow-2xl">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 font-mono text-xs font-bold uppercase tracking-wider mb-3">
              <Truck class="w-4 h-4" />
              <span>Merchant HQ Procurement</span>
            </div>
            <h1 class="text-3xl font-black font-heading tracking-tight text-white">Supplier Directory & Ledgers</h1>
            <p class="text-slate-400 text-xs sm:text-sm mt-1 max-w-2xl">
              Track vendor contacts, wholesale accounts payable, purchase history, and outstanding supplier balances.
            </p>
          </div>
          <button 
            @click="openAddModal"
            class="px-5 py-3 rounded-2xl bg-gradient-to-r from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 text-white font-extrabold text-xs shadow-lg shadow-indigo-500/20 flex items-center gap-2 shrink-0 transition-all hover:scale-105 active:scale-95"
          >
            <Plus class="w-4 h-4" />
            <span>Add New Supplier</span>
          </button>
        </div>
      </div>

      <!-- KPI Stat Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5 backdrop-blur-md flex items-center justify-between shadow-lg">
          <div>
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Total Suppliers</span>
            <span class="text-2xl font-black font-heading text-white mt-1 block">{{ suppliers.length }} Vendors</span>
          </div>
          <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 flex items-center justify-center font-bold">
            <Truck class="w-6 h-6" />
          </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5 backdrop-blur-md flex items-center justify-between shadow-lg">
          <div>
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Total PO Orders</span>
            <span class="text-2xl font-black font-heading text-cyan-400 mt-1 block">{{ totalPurchaseOrders }} POs</span>
          </div>
          <div class="w-12 h-12 rounded-2xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 flex items-center justify-center font-bold">
            <ShoppingBag class="w-6 h-6" />
          </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5 backdrop-blur-md flex items-center justify-between shadow-lg">
          <div>
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Accounts Payable Due</span>
            <span class="text-2xl font-black font-heading text-rose-400 mt-1 block">৳{{ totalDueBalance.toLocaleString() }}</span>
          </div>
          <div class="w-12 h-12 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 flex items-center justify-center font-bold">
            <DollarSign class="w-6 h-6" />
          </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5 backdrop-blur-md flex items-center justify-between shadow-lg">
          <div>
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Clear Balance Vendors</span>
            <span class="text-2xl font-black font-heading text-emerald-400 mt-1 block">{{ clearSuppliersCount }} Clean</span>
          </div>
          <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center font-bold">
            <CheckCircle2 class="w-6 h-6" />
          </div>
        </div>
      </div>

      <!-- Suppliers Table Card -->
      <div class="bg-slate-800/90 border border-slate-700/80 rounded-3xl p-6 shadow-2xl space-y-4 backdrop-blur-md">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-700/60">
          <div class="relative w-full sm:w-80">
            <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input 
              v-model="searchQuery" 
              type="text" 
              placeholder="Search vendor company, contact, or email..." 
              class="w-full pl-10 pr-4 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs text-slate-100 placeholder-slate-400 focus:outline-none focus:border-indigo-500"
            />
          </div>

          <span class="text-xs text-slate-400 font-mono">Showing {{ filteredSuppliers.length }} suppliers</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="text-slate-400 border-b border-slate-700/60 uppercase text-[10px] bg-slate-900/60 font-semibold tracking-wider">
                <th class="py-3.5 px-4">Supplier Company</th>
                <th class="py-3.5 px-4">Contact Representative</th>
                <th class="py-3.5 px-4">Phone / Email</th>
                <th class="py-3.5 px-4 text-center">Purchase Orders</th>
                <th class="py-3.5 px-4 text-right">Outstanding Due Balance</th>
                <th class="py-3.5 px-4 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/60">
              <tr v-for="supplier in filteredSuppliers" :key="supplier.id" class="hover:bg-slate-700/30 transition-colors">
                <td class="py-4 px-4 font-bold text-white text-sm font-heading flex items-center gap-3">
                  <div class="w-9 h-9 rounded-xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 flex items-center justify-center font-bold">
                    {{ supplier.company_name.charAt(0) }}
                  </div>
                  <span>{{ supplier.company_name }}</span>
                </td>
                <td class="py-4 px-4 text-slate-300 font-medium">{{ supplier.contact_person || 'N/A' }}</td>
                <td class="py-4 px-4 text-slate-300">
                  <div class="font-mono text-xs text-white">{{ supplier.phone || 'N/A' }}</div>
                  <div class="text-[10px] text-slate-400 font-mono">{{ supplier.email }}</div>
                </td>
                <td class="py-4 px-4 text-center">
                  <span class="px-2.5 py-1 rounded-xl bg-indigo-500/10 text-indigo-400 border border-indigo-500/30 font-bold text-xs font-mono">
                    {{ supplier.purchases_count || 0 }} POs
                  </span>
                </td>
                <td class="py-4 px-4 text-right font-bold font-mono text-sm" :class="supplier.due_balance > 0 ? 'text-rose-400' : 'text-emerald-400'">
                  ৳{{ parseFloat(supplier.due_balance || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                </td>
                <td class="py-4 px-4 text-center">
                  <div class="flex items-center justify-center gap-2">
                    <button 
                      @click="openEditModal(supplier)" 
                      class="p-2 rounded-xl bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 transition-colors" 
                      title="Edit Supplier"
                    >
                      <Edit3 class="w-4 h-4" />
                    </button>
                    <button 
                      @click="deleteSupplier(supplier)" 
                      class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 transition-colors" 
                      title="Delete Supplier"
                    >
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredSuppliers.length === 0">
                <td colspan="6" class="py-12 text-center text-slate-400 font-medium">No supplier vendors recorded yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add/Edit Supplier Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-md p-4">
        <div class="bg-slate-900 border border-slate-700 rounded-3xl p-6 w-full max-w-lg shadow-2xl space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <h3 class="text-lg font-black text-white font-heading flex items-center gap-2">
              <Truck class="w-5 h-5 text-indigo-400" />
              <span>{{ isEditing ? 'Edit Supplier Account' : 'Add New Supplier Account' }}</span>
            </h3>
            <button @click="showModal = false" class="text-slate-400 hover:text-white text-xl font-bold">&times;</button>
          </div>

          <form @submit.prevent="submitForm" class="space-y-4 text-xs">
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Company / Vendor Name</label>
              <input v-model="form.company_name" required type="text" placeholder="e.g. Apex Distribution Ltd" class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-slate-100 focus:outline-none focus:border-indigo-500" />
            </div>

            <div>
              <label class="block text-slate-300 font-semibold mb-1">Contact Person</label>
              <input v-model="form.contact_person" type="text" placeholder="e.g. Tanvir Ahmed" class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-slate-100 focus:outline-none focus:border-indigo-500" />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-slate-300 font-semibold mb-1">Phone Number</label>
                <input v-model="form.phone" type="text" placeholder="+880 1800-000000" class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-slate-100 focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-slate-300 font-semibold mb-1">Email Address</label>
                <input v-model="form.email" type="email" placeholder="vendor@apex.com" class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-slate-100 focus:outline-none focus:border-indigo-500" />
              </div>
            </div>

            <div class="pt-4 border-t border-slate-800 flex justify-end gap-3">
              <button type="button" @click="showModal = false" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl font-semibold">Cancel</button>
              <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-500/20">{{ isEditing ? 'Update Supplier' : 'Save Supplier' }}</button>
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
import { Truck, Plus, ShoppingBag, DollarSign, CheckCircle2, Search, Edit3, Trash2 } from 'lucide-vue-next';

const props = defineProps({
  suppliers: { type: Array, default: () => [] },
});

const searchQuery = ref('');
const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const totalPurchaseOrders = computed(() => props.suppliers.reduce((sum, s) => sum + (s.purchases_count || 0), 0));
const totalDueBalance = computed(() => props.suppliers.reduce((sum, s) => sum + (parseFloat(s.due_balance) || 0), 0));
const clearSuppliersCount = computed(() => props.suppliers.filter(s => !s.due_balance || parseFloat(s.due_balance) === 0).length);

const filteredSuppliers = computed(() => {
  if (!searchQuery.value.trim()) return props.suppliers;
  const q = searchQuery.value.toLowerCase().trim();
  return props.suppliers.filter(s => 
    s.company_name.toLowerCase().includes(q) || 
    (s.contact_person && s.contact_person.toLowerCase().includes(q)) ||
    (s.email && s.email.toLowerCase().includes(q))
  );
});

const form = ref({
  company_name: '',
  contact_person: '',
  phone: '',
  email: '',
  due_balance: 0,
});

const openAddModal = () => {
  isEditing.value = false;
  editingId.value = null;
  form.value = {
    company_name: '',
    contact_person: '',
    phone: '',
    email: '',
    due_balance: 0,
  };
  showModal.value = true;
};

const openEditModal = (supplier) => {
  isEditing.value = true;
  editingId.value = supplier.id;
  form.value = {
    company_name: supplier.company_name,
    contact_person: supplier.contact_person || '',
    phone: supplier.phone || '',
    email: supplier.email || '',
    due_balance: supplier.due_balance || 0,
  };
  showModal.value = true;
};

const submitForm = () => {
  if (isEditing.value) {
    router.post(`/merchant/suppliers/${editingId.value}/update`, form.value, {
      onSuccess: () => {
        showModal.value = false;
      }
    });
  } else {
    router.post('/merchant/suppliers', form.value, {
      onSuccess: () => {
        showModal.value = false;
      }
    });
  }
};

const deleteSupplier = (supplier) => {
  if (confirm(`Are you sure you want to delete supplier '${supplier.company_name}'?`)) {
    router.delete(`/merchant/suppliers/${supplier.id}`);
  }
};
</script>
