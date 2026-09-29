<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-100 min-h-screen">
      <!-- Title & Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div class="flex items-center gap-2 text-rose-600 font-bold text-xs uppercase tracking-wider">
            <UserCheck class="w-4 h-4" />
            <span>Platform User Directory</span>
          </div>
          <h1 class="text-2xl font-black font-heading text-slate-900 mt-1">Global Users & Security Controls</h1>
          <p class="text-xs text-slate-500 mt-1">Manage all user accounts across Super Admins, Merchant Executives, Store Managers, and Cashiers</p>
        </div>
      </div>

      <!-- Users Table Panel -->
      <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <h3 class="font-bold text-base font-heading text-slate-900">Total System Users ({{ users.total || users.length || 0 }})</h3>
          
          <div class="flex items-center gap-3 w-full sm:w-auto">
            <select v-model="roleFilter" class="px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-bold text-slate-700">
              <option value="">All Roles</option>
              <option value="super_admin">Super Admins</option>
              <option value="merchant">Merchants</option>
              <option value="store_manager">Store Managers</option>
              <option value="cashier">Cashiers</option>
            </select>

            <div class="w-full sm:w-72 relative">
              <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input 
                type="text" 
                v-model="searchQuery" 
                placeholder="Search user name or email..." 
                class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-900 focus:outline-none focus:border-rose-500"
              />
            </div>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] bg-slate-50">
                <th class="py-3 px-3">User Name & Email</th>
                <th class="py-3 px-3">Role</th>
                <th class="py-3 px-3">Merchant Business</th>
                <th class="py-3 px-3">Assigned Store</th>
                <th class="py-3 px-3 text-right">Security Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 font-sans">
              <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-slate-50">
                <td class="py-3.5 px-3">
                  <span class="font-bold text-slate-900 text-sm font-heading block">{{ user.name }}</span>
                  <span class="text-[10px] font-mono text-slate-500">{{ user.email }}</span>
                </td>
                <td class="py-3.5 px-3 font-bold">
                  <span :class="['px-2.5 py-0.5 rounded-full text-[10px] uppercase border', 
                    user.role === 'super_admin' ? 'bg-rose-50 text-rose-700 border-rose-200 font-black' : 
                    (user.role === 'merchant' ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 
                    (user.role === 'store_manager' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-slate-100 text-slate-700 border-slate-200'))]">
                    {{ user.role }}
                  </span>
                </td>
                <td class="py-3.5 px-3">
                  <span class="font-bold text-indigo-700 block">{{ user.tenant?.name || 'Platform HQ' }}</span>
                  <span class="text-[10px] text-slate-500 font-mono">{{ user.tenant?.code || 'N/A' }}</span>
                </td>
                <td class="py-3.5 px-3 text-slate-700">
                  {{ user.store?.name || 'All Outlets' }}
                </td>
                <td class="py-3.5 px-3 text-right">
                  <button @click="resetPassword(user)" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-[11px] border border-slate-200">
                    Reset Password
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
import { UserCheck, Search } from 'lucide-vue-next';

const props = defineProps({
  users: [Object, Array],
});

const searchQuery = ref('');
const roleFilter = ref('');

const allUsers = computed(() => {
  return Array.isArray(props.users) ? props.users : (props.users?.data || []);
});

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
</script>
