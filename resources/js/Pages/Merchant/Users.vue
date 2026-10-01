<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-900 min-h-screen text-slate-100 selection:bg-indigo-500 selection:text-white">
      <!-- Dark Hero Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 border border-indigo-500/20 p-6 sm:p-8 shadow-2xl">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 font-mono text-xs font-bold uppercase tracking-wider mb-3">
              <ShieldCheck class="w-4 h-4" />
              <span>RBAC Security Engine</span>
            </div>
            <h1 class="text-3xl font-black font-heading tracking-tight text-white">Staff Credentials & Roles</h1>
            <p class="text-slate-400 text-xs sm:text-sm mt-1 max-w-2xl">
              Provision Store Managers, Cashiers, granular feature permissions, and multi-outlet access control.
            </p>
          </div>
          <button 
            @click="openAddModal"
            class="px-5 py-3 rounded-2xl bg-gradient-to-r from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 text-white font-extrabold text-xs shadow-lg shadow-indigo-500/20 flex items-center gap-2 shrink-0 transition-all hover:scale-105 active:scale-95"
          >
            <UserPlus class="w-4 h-4" />
            <span>Add Staff Account</span>
          </button>
        </div>
      </div>

      <!-- KPI Stat Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5 backdrop-blur-md flex items-center justify-between shadow-lg">
          <div>
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Total Staff Accounts</span>
            <span class="text-2xl font-black font-heading text-white mt-1 block">{{ users.length }} Users</span>
          </div>
          <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 flex items-center justify-center font-bold">
            <Users class="w-6 h-6" />
          </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5 backdrop-blur-md flex items-center justify-between shadow-lg">
          <div>
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Store Managers</span>
            <span class="text-2xl font-black font-heading text-purple-400 mt-1 block">{{ managersCount }} Supervisors</span>
          </div>
          <div class="w-12 h-12 rounded-2xl bg-purple-500/10 border border-purple-500/30 text-purple-400 flex items-center justify-center font-bold">
            <UserCheck class="w-6 h-6" />
          </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5 backdrop-blur-md flex items-center justify-between shadow-lg">
          <div>
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Front-Desk Cashiers</span>
            <span class="text-2xl font-black font-heading text-emerald-400 mt-1 block">{{ cashiersCount }} Terminals</span>
          </div>
          <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center font-bold">
            <Terminal class="w-6 h-6" />
          </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5 backdrop-blur-md flex items-center justify-between shadow-lg">
          <div>
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Active Outlets</span>
            <span class="text-2xl font-black font-heading text-cyan-400 mt-1 block">{{ stores.length }} Locations</span>
          </div>
          <div class="w-12 h-12 rounded-2xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 flex items-center justify-center font-bold">
            <Building2 class="w-6 h-6" />
          </div>
        </div>
      </div>

      <!-- Users Table Card -->
      <div class="bg-slate-800/90 border border-slate-700/80 rounded-3xl p-6 shadow-2xl space-y-4 backdrop-blur-md">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-700/60">
          <div class="relative w-full sm:w-80">
            <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input 
              v-model="searchQuery" 
              type="text" 
              placeholder="Search staff by name or email..." 
              class="w-full pl-10 pr-4 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs text-slate-100 placeholder-slate-400 focus:outline-none focus:border-indigo-500"
            />
          </div>

          <div class="flex items-center gap-2">
            <span class="text-xs text-slate-400 font-mono">Showing {{ filteredUsers.length }} members</span>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="text-slate-400 border-b border-slate-700/60 uppercase text-[10px] bg-slate-900/60 font-semibold tracking-wider">
                <th class="py-3.5 px-4">Staff Member</th>
                <th class="py-3.5 px-4">Email Credentials</th>
                <th class="py-3.5 px-4">Role Tier</th>
                <th class="py-3.5 px-4">Assigned Branch Outlet</th>
                <th class="py-3.5 px-4 text-center">Status</th>
                <th class="py-3.5 px-4 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/60">
              <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-slate-700/30 transition-colors">
                <td class="py-4 px-4 font-bold text-white text-sm font-heading flex items-center gap-3">
                  <div class="w-9 h-9 rounded-xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 flex items-center justify-center font-bold">
                    {{ user.name.charAt(0) }}
                  </div>
                  <span>{{ user.name }}</span>
                </td>
                <td class="py-4 px-4 text-slate-300 font-mono text-xs">{{ user.email }}</td>
                <td class="py-4 px-4">
                  <span :class="[
                    'px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider border', 
                    user.role === 'store_manager' ? 'bg-indigo-500/10 text-indigo-400 border-indigo-500/30' : 
                    (user.role === 'merchant' ? 'bg-purple-500/10 text-purple-400 border-purple-500/30' : 
                    'bg-emerald-500/10 text-emerald-400 border-emerald-500/30')
                  ]">
                    {{ user.role === 'store_manager' ? 'Store Manager' : (user.role === 'merchant' ? 'Business CEO' : 'Cashier') }}
                  </span>
                </td>
                <td class="py-4 px-4 text-slate-300 font-semibold">
                  <div class="flex items-center gap-1.5">
                    <Building2 class="w-3.5 h-3.5 text-slate-400" />
                    <span>{{ user.store?.name || 'All Outlets' }}</span>
                  </div>
                </td>
                <td class="py-4 px-4 text-center">
                  <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">Active</span>
                </td>
                <td class="py-4 px-4 text-center">
                  <div class="flex items-center justify-center gap-2" v-if="user.role !== 'merchant'">
                    <button 
                      @click="openEditModal(user)" 
                      class="p-2 rounded-xl bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 transition-colors" 
                      title="Edit Staff Account"
                    >
                      <Edit3 class="w-4 h-4" />
                    </button>
                    <button 
                      @click="deleteUser(user)" 
                      class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 transition-colors" 
                      title="Delete Staff Account"
                    >
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ADD/EDIT STAFF MODAL -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md">
        <div class="bg-slate-900 border border-slate-700 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
          <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <h3 class="font-black text-lg text-white font-heading flex items-center gap-2">
              <UserCheck class="w-5 h-5 text-indigo-400" />
              <span>{{ isEditing ? 'Edit Staff Account' : 'Add New Staff Account' }}</span>
            </h3>
            <button @click="showModal = false" class="text-slate-400 hover:text-white text-xl font-bold">&times;</button>
          </div>

          <form @submit.prevent="submitUser" class="space-y-3.5 text-xs">
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Full Name</label>
              <input type="text" v-model="form.name" required class="w-full px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-700 text-slate-100 focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Email Address</label>
              <input type="email" v-model="form.email" required class="w-full px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-700 text-slate-100 focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-slate-300 font-semibold mb-1">{{ isEditing ? 'New Password (Leave blank to keep existing)' : 'Password' }}</label>
              <input type="password" v-model="form.password" :required="!isEditing" class="w-full px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-700 text-slate-100 focus:outline-none focus:border-indigo-500" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-slate-300 font-semibold mb-1">Assigned Role</label>
                <select v-model="form.role" class="w-full px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-700 text-slate-100 focus:outline-none focus:border-indigo-500">
                  <option value="store_manager">Store Manager</option>
                  <option value="cashier">Cashier</option>
                </select>
              </div>
              <div>
                <label class="block text-slate-300 font-semibold mb-1">Branch Store</label>
                <select v-model="form.store_id" class="w-full px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-700 text-slate-100 focus:outline-none focus:border-indigo-500">
                  <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </div>
            </div>

            <!-- Custom RBAC Feature Permission Toggles -->
            <div class="pt-3 border-t border-slate-800">
              <label class="block text-slate-200 font-bold mb-2">Custom RBAC Feature Permissions</label>
              <div class="space-y-2 bg-slate-800/80 p-3.5 rounded-2xl border border-slate-700">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" value="can_manage_inventory" v-model="form.permissions" class="rounded text-indigo-500 focus:ring-indigo-500 bg-slate-900 border-slate-700" />
                  <span class="text-slate-300 font-semibold">Inventory & Stock Adjustments</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" value="can_view_reports" v-model="form.permissions" class="rounded text-indigo-500 focus:ring-indigo-500 bg-slate-900 border-slate-700" />
                  <span class="text-slate-300 font-semibold">Profit & Loss Accounting Reports</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" value="can_process_returns" v-model="form.permissions" class="rounded text-indigo-500 focus:ring-indigo-500 bg-slate-900 border-slate-700" />
                  <span class="text-slate-300 font-semibold">Process Returns & Cash Refunds</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" value="can_manage_expenses" v-model="form.permissions" class="rounded text-indigo-500 focus:ring-indigo-500 bg-slate-900 border-slate-700" />
                  <span class="text-slate-300 font-semibold">Log & Manage Store Expenses</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" value="can_manage_suppliers" v-model="form.permissions" class="rounded text-indigo-500 focus:ring-indigo-500 bg-slate-900 border-slate-700" />
                  <span class="text-slate-300 font-semibold">Supplier Directory & PO Purchases</span>
                </label>
              </div>
            </div>

            <div class="pt-4 flex gap-3">
              <button type="submit" class="flex-1 py-3 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 text-white font-bold shadow-lg shadow-indigo-500/20">{{ isEditing ? 'Update Staff' : 'Save Staff' }}</button>
              <button type="button" @click="showModal = false" class="px-5 py-3 rounded-xl bg-slate-800 text-slate-300 font-bold hover:bg-slate-700">Cancel</button>
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
import { ShieldCheck, UserPlus, Users, UserCheck, Terminal, Building2, Search, Edit3, Trash2 } from 'lucide-vue-next';

const props = defineProps({
  users: { type: Array, default: () => [] },
  stores: { type: Array, default: () => [] },
});

const searchQuery = ref('');
const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const managersCount = computed(() => props.users.filter(u => u.role === 'store_manager').length);
const cashiersCount = computed(() => props.users.filter(u => u.role === 'cashier').length);

const filteredUsers = computed(() => {
  if (!searchQuery.value.trim()) return props.users;
  const q = searchQuery.value.toLowerCase().trim();
  return props.users.filter(u => 
    u.name.toLowerCase().includes(q) || 
    u.email.toLowerCase().includes(q)
  );
});

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
