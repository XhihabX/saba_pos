<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-100 min-h-screen">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div class="flex items-center gap-2 text-indigo-600 font-bold text-xs uppercase tracking-wider">
            <Truck class="w-4 h-4" />
            <span>Merchant HQ Portal</span>
          </div>
          <h1 class="text-2xl font-black font-heading text-slate-900 mt-1">Supplier Directory & Accounts</h1>
          <p class="text-slate-500 text-xs mt-1">Manage vendor ledgers, purchase accounts, and outstanding balances.</p>
        </div>
        <button 
          @click="openAddModal"
          class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-md flex items-center gap-2 shrink-0"
        >
          <Plus class="w-4 h-4" />
          <span>Add New Supplier</span>
        </button>
      </div>

      <!-- Suppliers Table -->
      <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 font-semibold uppercase tracking-wider">
                <th class="py-3.5 px-4">Supplier Company</th>
                <th class="py-3.5 px-4">Contact Person</th>
                <th class="py-3.5 px-4">Phone / Email</th>
                <th class="py-3.5 px-4 text-center">Total Orders</th>
                <th class="py-3.5 px-4 text-right">Due Balance</th>
                <th class="py-3.5 px-4 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr v-for="supplier in suppliers" :key="supplier.id" class="hover:bg-slate-50 transition-colors">
                <td class="py-3.5 px-4 font-bold text-slate-900 flex items-center gap-2">
                  <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-700 flex items-center justify-center font-bold">
                    {{ supplier.company_name.charAt(0) }}
                  </div>
                  {{ supplier.company_name }}
                </td>
                <td class="py-3.5 px-4 text-slate-700 font-medium">{{ supplier.contact_person || 'N/A' }}</td>
                <td class="py-3.5 px-4 text-slate-700">
                  <div>{{ supplier.phone || 'N/A' }}</div>
                  <div class="text-[10px] text-slate-400">{{ supplier.email }}</div>
                </td>
                <td class="py-3.5 px-4 text-center font-bold text-indigo-700">{{ supplier.purchases_count || 0 }} POs</td>
                <td class="py-3.5 px-4 text-right font-bold" :class="supplier.due_balance > 0 ? 'text-rose-600' : 'text-emerald-700'">
                  ৳{{ parseFloat(supplier.due_balance || 0).toLocaleString() }}
                </td>
                <td class="py-3.5 px-4 text-center">
                  <div class="flex items-center justify-center gap-2">
                    <button @click="openEditModal(supplier)" class="p-1 rounded text-slate-400 hover:text-indigo-600 transition-colors" title="Edit Supplier">
                      <Edit3 class="w-4 h-4" />
                    </button>
                    <button @click="deleteSupplier(supplier)" class="p-1 rounded text-slate-400 hover:text-rose-600 transition-colors" title="Delete Supplier">
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="suppliers.length === 0">
                <td colspan="6" class="py-8 text-center text-slate-500 font-medium">No suppliers recorded yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add/Edit Supplier Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div class="bg-white border border-slate-200 rounded-3xl p-6 w-full max-w-lg shadow-2xl space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-slate-200">
            <h3 class="text-lg font-bold text-slate-900">{{ isEditing ? 'Edit Supplier' : 'Add New Supplier' }}</h3>
            <button @click="showModal = false" class="text-slate-400 hover:text-slate-700">&times;</button>
          </div>

          <form @submit.prevent="submitForm" class="space-y-4 text-xs">
            <div>
              <label class="block text-slate-700 font-semibold mb-1">Company Name</label>
              <input v-model="form.company_name" required type="text" placeholder="e.g. Apex Distribution Ltd" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:border-indigo-600" />
            </div>

            <div>
              <label class="block text-slate-700 font-semibold mb-1">Contact Person</label>
              <input v-model="form.contact_person" type="text" placeholder="e.g. Tanvir Ahmed" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:border-indigo-600" />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-slate-700 font-semibold mb-1">Phone Number</label>
                <input v-model="form.phone" type="text" placeholder="+880 1800-000000" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:border-indigo-600" />
              </div>
              <div>
                <label class="block text-slate-700 font-semibold mb-1">Email</label>
                <input v-model="form.email" type="email" placeholder="vendor@apex.com" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:outline-none focus:border-indigo-600" />
              </div>
            </div>

            <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
              <button type="button" @click="showModal = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold">Cancel</button>
              <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-md">{{ isEditing ? 'Update Supplier' : 'Save Supplier' }}</button>
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
import { Truck, Plus, Edit3, Trash2 } from 'lucide-vue-next';

defineProps({
  suppliers: Array,
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

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
