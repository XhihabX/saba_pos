<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-8 bg-slate-50/70 min-h-screen">
      <!-- 👥 Hero Header Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-rose-950 to-slate-900 text-white p-6 sm:p-8 shadow-xl border border-slate-800">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-rose-500/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div class="space-y-2">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 font-bold text-xs uppercase tracking-wider">
              <UserCheck class="w-3.5 h-3.5" />
              <span>Platform User Security & Access</span>
            </div>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black font-heading tracking-tight text-white">
              Global Users & Role Control Matrix
            </h1>
            <p class="text-xs sm:text-sm text-slate-300 max-w-2xl">
              Manage authentication, role credentials, password resets, and store permissions across Super Admins, CEOs, Store Managers, and Cashiers
            </p>
          </div>

          <button 
            @click="showCreateModal = true"
            class="px-5 py-3 rounded-2xl bg-gradient-to-r from-rose-600 to-amber-500 hover:from-rose-500 hover:to-amber-400 text-white font-black text-xs shadow-lg shadow-rose-600/30 flex items-center gap-2 cursor-pointer shrink-0"
          >
            <UserPlus class="w-4 h-4 stroke-[3]" />
            <span>+ Create System User</span>
          </button>
        </div>
      </div>

      <!-- 📊 KPI Stat Cards Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Accounts</span>
            <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
              <UsersIcon class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-slate-900">{{ allUsers.length }} Users</div>
          <div class="text-[11px] font-semibold text-slate-500">Across all platform roles</div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Super Admins</span>
            <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold">
              <ShieldCheck class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-rose-700">{{ superAdminsCount }} Admins</div>
          <div class="text-[11px] font-semibold text-rose-600">Full system governance</div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Merchant Executives</span>
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
              <Building2 class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-emerald-700">{{ merchantsCount }} CEOs</div>
          <div class="text-[11px] font-semibold text-emerald-600">Business chain owners</div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Store Managers & Cashiers</span>
            <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
              <Store class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-slate-900">{{ staffCount }} Staff</div>
          <div class="text-[11px] font-semibold text-amber-600">Branch register operators</div>
        </div>
      </div>

      <!-- 👤 Users Directory Panel -->
      <div class="p-6 sm:p-8 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h3 class="font-black text-xl font-heading text-slate-900">User Accounts Directory</h3>
            <p class="text-xs text-slate-500">Search users, update roles, and issue security password overrides</p>
          </div>

          <div class="flex items-center gap-3 w-full sm:w-auto">
            <select v-model="roleFilter" class="px-3.5 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-xs font-bold text-slate-700 focus:outline-none focus:border-rose-500">
              <option value="">All Roles</option>
              <option value="super_admin">Super Admins</option>
              <option value="merchant">Merchants</option>
              <option value="store_manager">Store Managers</option>
              <option value="cashier">Cashiers</option>
            </select>

            <div class="w-full sm:w-72 relative">
              <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input 
                type="text" 
                v-model="searchQuery" 
                placeholder="Search user name or email..." 
                class="w-full pl-10 pr-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-xs text-slate-900 focus:outline-none focus:border-rose-500"
              />
            </div>
          </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200/80">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] bg-slate-50/80 font-bold tracking-wider">
                <th class="py-4 px-4">User Details</th>
                <th class="py-4 px-4">Access Role</th>
                <th class="py-4 px-4">Merchant Chain</th>
                <th class="py-4 px-4">Assigned Store</th>
                <th class="py-4 px-4 text-right">Security Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-sans">
              <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="py-4 px-4">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-2xl bg-slate-100 border border-slate-200 text-slate-800 font-black flex items-center justify-center shrink-0">
                      {{ user.name ? user.name.charAt(0).toUpperCase() : 'U' }}
                    </div>
                    <div>
                      <span class="font-bold text-slate-900 text-sm font-heading block">{{ user.name }}</span>
                      <span class="text-[10px] font-mono text-slate-500">{{ user.email }}</span>
                    </div>
                  </div>
                </td>
                <td class="py-4 px-4">
                  <span :class="['px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border', 
                    user.role === 'super_admin' ? 'bg-rose-50 text-rose-700 border-rose-200' : 
                    (user.role === 'merchant' ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 
                    (user.role === 'store_manager' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-slate-100 text-slate-700 border-slate-200'))]">
                    {{ user.role.replace('_', ' ') }}
                  </span>
                </td>
                <td class="py-4 px-4">
                  <span class="font-bold text-indigo-700 block text-xs">{{ user.tenant?.name || 'Platform HQ' }}</span>
                  <span class="text-[10px] text-slate-500 font-mono">{{ user.tenant?.code || 'N/A' }}</span>
                </td>
                <td class="py-4 px-4 text-slate-700">
                  <span class="px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 font-medium">
                    {{ user.store?.name || 'All Outlets' }}
                  </span>
                </td>
                <td class="py-4 px-4 text-right space-x-1">
                  <button 
                    @click="resetPassword(user)" 
                    class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs border border-slate-200 transition-all cursor-pointer"
                  >
                    Reset Password
                  </button>

                  <button 
                    @click="deleteUser(user)" 
                    class="p-1.5 rounded-xl bg-rose-50 hover:bg-rose-600 text-rose-700 hover:text-white font-bold border border-rose-200 transition-all cursor-pointer inline-flex items-center"
                    title="Delete User Account"
                  >
                    <Trash2 class="w-4 h-4" />
                  </button>
                </td>
              </tr>
              <tr v-if="filteredUsers.length === 0">
                <td colspan="5" class="py-12 text-center text-slate-400">
                  <UserCheck class="w-8 h-8 mx-auto mb-2 opacity-50" />
                  <p class="font-bold text-xs">No users found matching your filter</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- CREATE SYSTEM USER MODAL -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
      <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 sm:p-8 space-y-6 shadow-2xl animate-in fade-in zoom-in duration-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200">
          <div>
            <h3 class="font-black text-xl font-heading text-slate-900">Create System User Account</h3>
            <p class="text-xs text-slate-500">Issue credentials for new admin or staff</p>
          </div>
          <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-700">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submitCreateUser" class="space-y-4 text-xs">
          <div>
            <label class="block text-slate-700 font-bold mb-1">Full Name</label>
            <input type="text" v-model="createForm.name" required placeholder="e.g. Mahfuzur Rahman" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-bold focus:outline-none focus:border-rose-500" />
          </div>

          <div>
            <label class="block text-slate-700 font-bold mb-1">Email Address</label>
            <input type="email" v-model="createForm.email" required placeholder="user@iotpos.com" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500" />
          </div>

          <div>
            <label class="block text-slate-700 font-bold mb-1">Password</label>
            <input type="password" v-model="createForm.password" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-mono focus:outline-none focus:border-rose-500" />
          </div>

          <div>
            <label class="block text-slate-700 font-bold mb-1">Assign Security Role</label>
            <select v-model="createForm.role" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-bold focus:outline-none focus:border-rose-500">
              <option value="super_admin">Super Admin (Platform Command)</option>
              <option value="merchant">Merchant CEO (Chain Owner)</option>
              <option value="store_manager">Store Manager (Outlet Admin)</option>
              <option value="cashier">Cashier POS Operator</option>
            </select>
          </div>

          <div class="pt-4 flex gap-3">
            <button type="submit" class="flex-1 py-3.5 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs shadow-md cursor-pointer">
              Create User Credentials
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
import { 
  UserCheck, 
  Search, 
  UserPlus, 
  ShieldCheck, 
  Building2, 
  Store, 
  Users as UsersIcon,
  Trash2,
  X 
} from 'lucide-vue-next';

const props = defineProps({
  users: [Object, Array],
});

const searchQuery = ref('');
const roleFilter = ref('');
const showCreateModal = ref(false);

const createForm = useForm({
  name: '',
  email: '',
  password: 'password123',
  role: 'merchant',
});

const allUsers = computed(() => {
  return Array.isArray(props.users) ? props.users : (props.users?.data || []);
});

const superAdminsCount = computed(() => allUsers.value.filter(u => u.role === 'super_admin').length);
const merchantsCount = computed(() => allUsers.value.filter(u => u.role === 'merchant').length);
const staffCount = computed(() => allUsers.value.filter(u => u.role === 'store_manager' || u.role === 'cashier').length);

const filteredUsers = computed(() => {
  let list = allUsers.value;
  if (roleFilter.value) {
    list = list.filter(u => u.role === roleFilter.value);
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(u =>
      (u.name && u.name.toLowerCase().includes(q)) ||
      (u.email && u.email.toLowerCase().includes(q)) ||
      (u.tenant?.name && u.tenant.name.toLowerCase().includes(q))
    );
  }
  return list;
});

const resetPassword = (user) => {
  const newPass = prompt(`Enter new password for '${user.name}' (${user.email}):`, '123456');
  if (newPass) {
    router.post(`/super-admin/users/${user.id}/reset-password`, { password: newPass });
  }
};

const deleteUser = (user) => {
  if (confirm(`Are you sure you want to delete user account '${user.name}'?`)) {
    router.delete(`/super-admin/users/${user.id}`);
  }
};

const submitCreateUser = () => {
  createForm.post('/super-admin/users', {
    onSuccess: () => {
      showCreateModal.value = false;
      createForm.reset();
    }
  });
};
</script>

