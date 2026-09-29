<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-100 min-h-screen">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <h1 class="text-2xl font-extrabold font-heading text-slate-900">Role-Based Access Control (RBAC)</h1>
          <p class="text-xs text-slate-500 mt-1">Manage Store Managers and Cashiers across your retail store branches</p>
        </div>

        <button @click="openAddModal" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-md flex items-center gap-2">
          <span>+ Add Staff Account</span>
        </button>
      </div>

      <!-- Users Table Card -->
      <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-4">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] bg-slate-50">
                <th class="py-3 px-3">Staff Name</th>
                <th class="py-3 px-3">Email Address</th>
                <th class="py-3 px-3">Assigned Role</th>
                <th class="py-3 px-3">Assigned Outlet</th>
                <th class="py-3 px-3 text-center">Status</th>
                <th class="py-3 px-3 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr v-for="user in users" :key="user.id" class="hover:bg-slate-50">
                <td class="py-3.5 px-3 font-bold text-slate-900 text-sm font-heading">{{ user.name }}</td>
                <td class="py-3.5 px-3 text-slate-700 font-mono">{{ user.email }}</td>
                <td class="py-3.5 px-3">
                  <span :class="['px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase', user.role === 'store_manager' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200']">
                    {{ user.role === 'store_manager' ? 'Store Manager' : (user.role === 'merchant' ? 'Business CEO' : 'Cashier') }}
                  </span>
                </td>
                <td class="py-3.5 px-3 text-slate-700 font-medium">{{ user.store?.name || 'All Outlets' }}</td>
                <td class="py-3.5 px-3 text-center">
                  <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">Active</span>
                </td>
                <td class="py-3.5 px-3 text-center">
                  <div class="flex items-center justify-center gap-2" v-if="user.role !== 'merchant'">
                    <button @click="openEditModal(user)" class="p-1 rounded text-slate-400 hover:text-indigo-600 transition-colors" title="Edit Staff">
                      <Edit3 class="w-4 h-4" />
                    </button>
                    <button @click="deleteUser(user)" class="p-1 rounded text-slate-400 hover:text-rose-600 transition-colors" title="Delete Staff">
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ADD/EDIT STAFF MODAL -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
      <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
        <h3 class="font-bold text-lg text-slate-900 font-heading">{{ isEditing ? 'Edit Staff Account' : 'Add New Staff Account' }}</h3>
        <form @submit.prevent="submitUser" class="space-y-3 text-xs">
          <div>
            <label class="block text-slate-700 font-semibold mb-1">Full Name</label>
            <input type="text" v-model="form.name" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-indigo-600" />
          </div>
          <div>
            <label class="block text-slate-700 font-semibold mb-1">Email Address</label>
            <input type="email" v-model="form.email" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-indigo-600" />
          </div>
          <div>
            <label class="block text-slate-700 font-semibold mb-1">{{ isEditing ? 'New Password (Leave blank to keep existing)' : 'Password' }}</label>
            <input type="password" v-model="form.password" :required="!isEditing" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-indigo-600" />
          </div>
          <div>
            <label class="block text-slate-700 font-semibold mb-1">Assigned Role</label>
            <select v-model="form.role" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-indigo-600">
              <option value="store_manager">Store Manager (Branch Supervisor)</option>
              <option value="cashier">Cashier (Front-Desk Terminal)</option>
            </select>
          </div>
          <div>
            <label class="block text-slate-700 font-semibold mb-1">Branch Store</label>
            <select v-model="form.store_id" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-indigo-600">
              <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>

          <!-- Custom RBAC Feature Permission Toggles -->
          <div class="pt-2 border-t border-slate-200">
            <label class="block text-slate-900 font-bold mb-1">Custom RBAC Feature Permissions</label>
            <div class="space-y-1.5 bg-slate-50 p-3 rounded-xl border border-slate-200">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" value="can_manage_inventory" v-model="form.permissions" class="rounded text-indigo-600 focus:ring-indigo-500" />
                <span class="text-slate-800 font-semibold">Inventory & Stock Adjustments</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" value="can_view_reports" v-model="form.permissions" class="rounded text-indigo-600 focus:ring-indigo-500" />
                <span class="text-slate-800 font-semibold">Profit & Loss Accounting Reports</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" value="can_process_returns" v-model="form.permissions" class="rounded text-indigo-600 focus:ring-indigo-500" />
                <span class="text-slate-800 font-semibold">Process Returns & Cash Refunds</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" value="can_manage_expenses" v-model="form.permissions" class="rounded text-indigo-600 focus:ring-indigo-500" />
                <span class="text-slate-800 font-semibold">Log & Manage Store Expenses</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" value="can_manage_suppliers" v-model="form.permissions" class="rounded text-indigo-600 focus:ring-indigo-500" />
                <span class="text-slate-800 font-semibold">Supplier Directory & PO Purchases</span>
              </label>
            </div>
          </div>

          <div class="pt-3 flex gap-3">
            <button type="submit" class="flex-1 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold shadow-md">{{ isEditing ? 'Update Staff' : 'Save Staff' }}</button>
            <button type="button" @click="showModal = false" class="px-5 py-3 rounded-xl bg-slate-100 text-slate-700 font-bold">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Edit3, Trash2 } from 'lucide-vue-next';

const props = defineProps({
  users: Array,
  stores: Array,
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = ref({
  name: '',
  email: '',
  password: 'password123',
  role: 'cashier',
  store_id: props.stores?.[0]?.id || 1,
  permissions: ['can_manage_inventory', 'can_process_returns'],
});

const openAddModal = () => {
  isEditing.value = false;
  editingId.value = null;
  form.value = {
    name: '',
    email: '',
    password: 'password123',
    role: 'cashier',
    store_id: props.stores?.[0]?.id || 1,
    permissions: ['can_manage_inventory', 'can_process_returns'],
  };
  showModal.value = true;
};

const openEditModal = (user) => {
  isEditing.value = true;
  editingId.value = user.id;
  form.value = {
    name: user.name,
    email: user.email,
    password: '',
    role: user.role,
    store_id: user.store_id,
    permissions: Array.isArray(user.permissions) ? [...user.permissions] : ['can_manage_inventory', 'can_process_returns'],
  };
  showModal.value = true;
};

const submitUser = () => {
  if (isEditing.value) {
    router.post(`/merchant/users/${editingId.value}/update`, form.value, {
      onSuccess: () => {
        showModal.value = false;
      }
    });
  } else {
    router.post('/merchant/users', form.value, {
      onSuccess: () => {
        showModal.value = false;
      }
    });
  }
};

const deleteUser = (user) => {
  if (confirm(`Are you sure you want to delete staff account '${user.name}'?`)) {
    router.delete(`/merchant/users/${user.id}`);
  }
};
</script>
