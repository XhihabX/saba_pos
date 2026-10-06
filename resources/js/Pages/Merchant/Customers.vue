<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-50 dark:bg-slate-950 min-h-screen text-slate-900 dark:text-slate-100 selection:bg-indigo-500 selection:text-white transition-colors duration-200">
      <!-- Dark Hero Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 border border-indigo-500/20 p-6 sm:p-8 shadow-2xl">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 font-mono text-xs font-bold uppercase tracking-wider mb-3">
              <Users class="w-4 h-4" />
              <span>SaaS Customer Relationship Management</span>
            </div>
            <h1 class="text-3xl font-black font-heading tracking-tight text-white">Customer Database & Credit Ledger</h1>
            <p class="text-slate-400 text-xs sm:text-sm mt-1 max-w-2xl">
              Track retail customer profiles, outstanding credit (due accounts), loyalty reward points, and purchase histories.
            </p>
          </div>
          <div class="flex items-center gap-3">
            <button @click="openCreateModal" class="px-5 py-3 rounded-2xl bg-gradient-to-r from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 text-white font-extrabold text-xs shadow-lg shadow-indigo-500/25 flex items-center gap-2 transition-all">
              <Plus class="w-4 h-4" />
              <span>Register New Customer</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6">
        <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Customers</span>
            <div class="w-9 h-9 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
              <Users class="w-5 h-5" />
            </div>
          </div>
          <div class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white mt-2 font-heading">{{ stats.total_customers || 0 }}</div>
        </div>

        <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Customer Credit Due</span>
            <div class="w-9 h-9 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center">
              <DollarSign class="w-5 h-5" />
            </div>
          </div>
          <div class="text-2xl sm:text-3xl font-black text-rose-600 dark:text-rose-400 mt-2 font-heading">৳{{ stats.total_due ? stats.total_due.toLocaleString() : 0 }}</div>
        </div>

        <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Loyalty Points Issued</span>
            <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center">
              <Award class="w-5 h-5" />
            </div>
          </div>
          <div class="text-2xl sm:text-3xl font-black text-amber-600 dark:text-amber-400 mt-2 font-heading">{{ stats.total_points ? stats.total_points.toLocaleString() : 0 }} pts</div>
        </div>
      </div>

      <!-- Main Data Table Container -->
      <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-6 shadow-xl">
        <!-- Search & Filters -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div class="relative w-full sm:w-80">
            <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input v-model="searchQuery" type="text" placeholder="Search customer by name or phone..." class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl text-xs text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-indigo-500 transition-colors" />
          </div>

          <div class="flex items-center gap-3">
            <span class="text-xs text-slate-500 dark:text-slate-400 font-semibold">
              Showing {{ filteredCustomers.length }} customers
            </span>
            <button 
              @click="handleExport" 
              class="px-4 py-2.5 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs flex items-center gap-2 transition-colors whitespace-nowrap"
            >
              <Download class="w-4 h-4" />
              <span>Export CSV</span>
            </button>
          </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="border-b border-slate-100 dark:border-slate-800 text-slate-400 font-bold uppercase tracking-wider">
                <th class="pb-3 px-3">Customer Name</th>
                <th class="pb-3 px-3">Phone</th>
                <th class="pb-3 px-3">Email</th>
                <th class="pb-3 px-3">Credit Due</th>
                <th class="pb-3 px-3">Loyalty Points</th>
                <th class="pb-3 px-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <tr v-for="cust in filteredCustomers" :key="cust.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors">
                <td class="py-3.5 px-3 font-extrabold text-slate-900 dark:text-slate-100">{{ cust.name }}</td>
                <td class="py-3.5 px-3 font-mono text-slate-600 dark:text-slate-400">{{ cust.phone || '-' }}</td>
                <td class="py-3.5 px-3 text-slate-600 dark:text-slate-400">{{ cust.email || '-' }}</td>
                <td class="py-3.5 px-3 font-black font-mono" :class="(cust.due_balance || cust.due) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500 dark:text-slate-400'">
                  ৳{{ ((cust.due_balance || cust.due) || 0).toLocaleString() }}
                </td>
                <td class="py-3.5 px-3 font-bold font-mono text-amber-600 dark:text-amber-400">
                  {{ cust.points || 0 }} pts
                </td>
                <td class="py-3.5 px-3 text-right space-x-2">
                  <button v-if="(cust.due_balance || cust.due) > 0" @click="openPayDueModal(cust)" class="px-2.5 py-1.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 font-extrabold transition-colors">
                    Collect Due
                  </button>
                  <button v-if="(cust.due_balance || cust.due) > 0" @click="sendSms(cust)" class="px-2.5 py-1.5 rounded-xl bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 font-extrabold transition-colors" title="Send SMS Payment Reminder">
                    📱 SMS Due
                  </button>
                  <button @click="openEditModal(cust)" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-indigo-500/10 hover:text-indigo-600 dark:hover:text-indigo-400 text-slate-600 dark:text-slate-300 transition-colors">
                    <Edit3 class="w-4 h-4" />
                  </button>
                  <button @click="deleteCustomer(cust)" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-rose-500/10 hover:text-rose-600 dark:hover:text-rose-400 text-slate-600 dark:text-slate-300 transition-colors">
                    <Trash2 class="w-4 h-4" />
                  </button>
                </td>
              </tr>
              <tr v-if="filteredCustomers.length === 0">
                <td colspan="6" class="py-8 text-center text-slate-400 text-xs">
                  No customers found in database.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Collect Due Payment Modal -->
      <div v-if="showPayDueModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl space-y-5">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <h3 class="text-lg font-black text-slate-900 dark:text-white font-heading flex items-center gap-2">
              <DollarSign class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
              <span>Collect Customer Due Payment</span>
            </h3>
            <button @click="showPayDueModal = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-xl font-bold">&times;</button>
          </div>

          <form @submit.prevent="submitPayDue" class="space-y-4 text-xs">
            <div class="p-3 bg-slate-50 dark:bg-slate-800/80 rounded-2xl border border-slate-200 dark:border-slate-700">
              <div class="font-bold text-slate-900 dark:text-white">{{ activeCustomer?.name }}</div>
              <div class="text-slate-500 text-[11px]">Current Outstanding Due: <span class="font-black text-rose-600 dark:text-rose-400">৳{{ activeCustomer?.due_balance || activeCustomer?.due || 0 }}</span></div>
            </div>

            <div>
              <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">Payment Amount Received (৳)</label>
              <input v-model.number="payDueForm.amount" required type="number" step="0.01" min="0.01" :max="activeCustomer?.due_balance || activeCustomer?.due" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 font-black font-mono focus:outline-none focus:border-emerald-500" />
            </div>

            <div>
              <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">Payment Method</label>
              <select v-model="payDueForm.payment_method" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 focus:outline-none focus:border-emerald-500">
                <option value="cash">Cash Received</option>
                <option value="bkash">bKash Mobile Banking</option>
                <option value="card">Credit / Debit Card</option>
              </select>
            </div>

            <div>
              <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">Payment Notes / Reference</label>
              <input v-model="payDueForm.notes" type="text" placeholder="e.g. Received cash at main outlet counter" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 focus:outline-none focus:border-emerald-500" />
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-3">
              <button type="button" @click="showPayDueModal = false" class="px-4 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl font-semibold">Cancel</button>
              <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-bold rounded-xl shadow-lg shadow-emerald-500/20">Record Due Settlement</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Create / Edit Customer Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl space-y-5">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <h3 class="text-lg font-black text-slate-900 dark:text-white font-heading flex items-center gap-2">
              <Users class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
              <span>{{ isEditing ? 'Edit Customer Profile' : 'Register Customer' }}</span>
            </h3>
            <button @click="showModal = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-xl font-bold">&times;</button>
          </div>

          <form @submit.prevent="submitForm" class="space-y-4 text-xs">
            <div>
              <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">Customer Full Name</label>
              <input v-model="form.name" required type="text" placeholder="e.g. Rahat Chowdhury" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">Phone Number</label>
                <input v-model="form.phone" type="text" placeholder="+880 1711-000000" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">Email Address</label>
                <input v-model="form.email" type="email" placeholder="rahat@gmail.com" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">Credit Due (৳)</label>
                <input v-model.number="form.due" type="number" step="0.01" min="0" placeholder="0.00" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">Loyalty Points</label>
                <input v-model.number="form.points" type="number" min="0" placeholder="0" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
              </div>
            </div>

            <div>
              <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">Address Note</label>
              <textarea v-model="form.address" rows="2" placeholder="House 12, Road 4, Dhanmondi, Dhaka" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"></textarea>
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-3">
              <button type="button" @click="showModal = false" class="px-4 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl font-semibold">Cancel</button>
              <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-500/20">{{ isEditing ? 'Update Profile' : 'Save Customer' }}</button>
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
import { Users, Plus, DollarSign, Award, Search, Edit3, Trash2, Download } from 'lucide-vue-next';
import { exportToCSV } from '@/Utils/csvExport';

const props = defineProps({
  customers: { type: Array, default: () => [] },
  stats: { type: Object, default: () => ({ total_customers: 0, total_due: 0, total_points: 0 }) },
});

const searchQuery = ref('');
const showModal = ref(false);
const isEditing = ref(false);

const showPayDueModal = ref(false);
const activeCustomer = ref(null);
const payDueForm = ref({
  amount: 0,
  payment_method: 'cash',
  notes: '',
});

const openPayDueModal = (cust) => {
  activeCustomer.value = cust;
  payDueForm.value = {
    amount: (cust.due_balance || cust.due || 0),
    payment_method: 'cash',
    notes: `Settlement payment for ${cust.name}`,
  };
  showPayDueModal.value = true;
};

const submitPayDue = () => {
  if (!activeCustomer.value) return;
  const url = window.safeRoute ? window.safeRoute('merchant.customers.pay-due', activeCustomer.value.id) : `/merchant/customers/${activeCustomer.value.id}/pay-due`;
  router.post(url, payDueForm.value, {
    onSuccess: () => {
      showPayDueModal.value = false;
      activeCustomer.value = null;
    }
  });
};

const form = ref({
  id: null,
  name: '',
  phone: '',
  email: '',
  due_balance: 0,
  points: 0,
  address: '',
});

const filteredCustomers = computed(() => {
  if (!searchQuery.value) return props.customers;
  const q = searchQuery.value.toLowerCase();
  return props.customers.filter(c => 
    (c.name && c.name.toLowerCase().includes(q)) ||
    (c.phone && c.phone.toLowerCase().includes(q))
  );
});

const openCreateModal = () => {
  isEditing.value = false;
  form.value = { id: null, name: '', phone: '', email: '', due_balance: 0, points: 0, address: '' };
  showModal.value = true;
};

const openEditModal = (cust) => {
  isEditing.value = true;
  form.value = { ...cust, due_balance: cust.due_balance ?? cust.due ?? 0 };
  showModal.value = true;
};

const submitForm = () => {
  const payload = {
    ...form.value,
    due_balance: form.value.due_balance ?? 0,
  };
  if (isEditing.value) {
    const url = window.safeRoute ? window.safeRoute('merchant.customers.update', form.value.id) : `/merchant/customers/${form.value.id}/update`;
    router.post(url, payload, { onSuccess: () => { showModal.value = false; } });
  } else {
    const url = window.safeRoute ? window.safeRoute('merchant.customers.store') : '/merchant/customers';
    router.post(url, payload, { onSuccess: () => { showModal.value = false; } });
  }
};

const deleteCustomer = (cust) => {
  if (confirm(`Are you sure you want to delete customer ${cust.name}?`)) {
    const url = window.safeRoute ? window.safeRoute('merchant.customers.delete', cust.id) : `/merchant/customers/${cust.id}`;
    router.delete(url);
  }
};

const sendSms = (cust) => {
  if (confirm(`Send SMS Due Payment Reminder to ${cust.name} (${cust.phone || 'No Phone'})?`)) {
    router.post(`/customers/${cust.id}/send-sms`, {}, {
      onSuccess: () => alert(`SMS Due Reminder dispatched for ${cust.name}!`),
    });
  }
};

const handleExport = () => {
  const columns = [
    { label: 'Customer ID', key: 'id' },
    { label: 'Customer Name', key: 'name' },
    { label: 'Phone', key: 'phone' },
    { label: 'Email', key: 'email' },
    { label: 'Credit Due (৳)', key: 'due_balance' },
    { label: 'Loyalty Points', key: 'points' },
    { label: 'Address', key: 'address' },
  ];
  exportToCSV('customers_credit_loyalty_ledger', columns, filteredCustomers.value);
};
</script>
