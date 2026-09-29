<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-6">
      <!-- Title & Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl sm:text-3xl font-black font-heading text-slate-900">
            Store Expenses & Outgoings
          </h1>
          <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">
            Record operational overheads (Rent, Utilities, Salary, Supplies) for real-time P&L deduction
          </p>
        </div>

        <button 
          @click="openAddModal" 
          class="px-5 py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-sm shadow-md flex items-center justify-center gap-2 transition-all active:scale-95"
        >
          <Plus class="w-4 h-4" />
          <span>Record New Expense</span>
        </button>
      </div>

      <!-- Expense Summary Cards Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-xs">
          <div class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Total Expenses Recorded</div>
          <div class="text-2xl font-black font-heading text-rose-700 font-mono">
            ৳{{ formatMoney(totalExpenses) }}
          </div>
          <div class="text-[11px] text-slate-500 mt-1">Deducted from store net profit</div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-xs">
          <div class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Expense Category Breakdown</div>
          <div class="text-lg font-bold font-heading text-slate-900">
            Rent, Salary, Utilities, Maintenance
          </div>
          <div class="text-[11px] text-slate-500 mt-1">Operational overhead categories</div>
        </div>

        <div class="p-6 rounded-3xl bg-emerald-50 border border-emerald-200 shadow-xs flex items-center justify-between">
          <div>
            <div class="text-xs font-bold text-emerald-800 uppercase tracking-wider">Accounting Status</div>
            <div class="text-sm font-extrabold text-emerald-900 mt-1">Automated P&L Sync Active</div>
          </div>
          <CheckCircle2 class="w-8 h-8 text-emerald-600" />
        </div>
      </div>

      <!-- Expenses Table Card -->
      <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-xs space-y-4">
        <h3 class="font-bold text-base font-heading text-slate-900">Expense Audit Ledger</h3>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] font-bold">
                <th class="py-3 px-3">Expense Description</th>
                <th class="py-3 px-3">Category</th>
                <th class="py-3 px-3">Date</th>
                <th class="py-3 px-3 text-right">Amount</th>
                <th class="py-3 px-3">Notes</th>
                <th class="py-3 px-3 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="exp in (expenses.data || expenses)" :key="exp.id" class="hover:bg-slate-50 transition-colors">
                <td class="py-3.5 px-3 font-bold text-slate-900 font-heading">{{ exp.title }}</td>
                <td class="py-3.5 px-3">
                  <span class="px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 border border-slate-200 text-[10px] font-bold uppercase">
                    {{ exp.category }}
                  </span>
                </td>
                <td class="py-3.5 px-3 font-mono text-slate-600">{{ exp.date }}</td>
                <td class="py-3.5 px-3 text-right font-mono font-bold text-rose-700">
                  -৳{{ formatMoney(exp.amount) }}
                </td>
                <td class="py-3.5 px-3 text-slate-500 text-[11px]">{{ exp.notes || 'N/A' }}</td>
                <td class="py-3.5 px-3 text-center">
                  <div class="flex items-center justify-center gap-2">
                    <button @click="openEditModal(exp)" class="p-1 rounded text-slate-400 hover:text-indigo-600 transition-colors" title="Edit Expense">
                      <Edit3 class="w-4 h-4" />
                    </button>
                    <button @click="deleteExpense(exp)" class="p-1 rounded text-slate-400 hover:text-rose-600 transition-colors" title="Delete Expense">
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!expenses || (expenses.data && expenses.data.length === 0)">
                <td colspan="6" class="py-8 text-center text-slate-400">No store expenses recorded. Click 'Record New Expense' above.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ADD/EDIT EXPENSE MODAL -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
      <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full overflow-hidden shadow-2xl">
        <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50">
          <h3 class="font-bold text-lg font-heading text-slate-900">{{ isEditing ? 'Edit Store Expense' : 'Record Store Expense' }}</h3>
          <button @click="showModal = false" class="text-slate-400 hover:text-slate-700">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submitExpense" class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Expense Title *</label>
            <input type="text" v-model="form.title" placeholder="e.g. Electricity Bill July" required class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:border-emerald-600 focus:bg-white" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Category *</label>
              <select v-model="form.category" class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs font-bold text-slate-900 focus:outline-none focus:border-emerald-600 focus:bg-white">
                <option value="Rent">Store Rent</option>
                <option value="Utilities">Utilities & Power</option>
                <option value="Salary">Staff Salary</option>
                <option value="Maintenance">Maintenance & Repairs</option>
                <option value="Supplies">Store Supplies & Stationary</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Amount (৳) *</label>
              <input type="number" step="0.01" v-model.number="form.amount" required class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 font-mono font-bold focus:outline-none focus:border-emerald-600 focus:bg-white" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Expense Date *</label>
            <input type="date" v-model="form.date" required class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:border-emerald-600 focus:bg-white" />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Notes (Optional)</label>
            <input type="text" v-model="form.notes" placeholder="e.g. Paid via bkash" class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:border-emerald-600 focus:bg-white" />
          </div>

          <div class="pt-2 flex gap-3">
            <button type="submit" class="flex-1 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md">
              {{ isEditing ? 'Update Expense' : 'Save Expense Entry' }}
            </button>
            <button type="button" @click="showModal = false" class="px-5 py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs">
              Cancel
            </button>
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
import { Plus, CheckCircle2, X, Edit3, Trash2 } from 'lucide-vue-next';

const props = defineProps({
  expenses: Object,
  totalExpenses: Number,
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = ref({
  title: '',
  category: 'Utilities',
  amount: 0,
  date: new Date().toISOString().substr(0, 10),
  notes: '',
});

const openAddModal = () => {
  isEditing.value = false;
  editingId.value = null;
  form.value = {
    title: '',
    category: 'Utilities',
    amount: 0,
    date: new Date().toISOString().substr(0, 10),
    notes: '',
  };
  showModal.value = true;
};

const openEditModal = (exp) => {
  isEditing.value = true;
  editingId.value = exp.id;
  form.value = {
    title: exp.title,
    category: exp.category,
    amount: exp.amount,
    date: exp.date,
    notes: exp.notes || '',
  };
  showModal.value = true;
};

const formatMoney = (val) => {
  return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const submitExpense = () => {
  if (isEditing.value) {
    router.post(`/expenses/${editingId.value}/update`, form.value, {
      onSuccess: () => {
        showModal.value = false;
      }
    });
  } else {
    router.post('/expenses', form.value, {
      onSuccess: () => {
        showModal.value = false;
      }
    });
  }
};

const deleteExpense = (exp) => {
  if (confirm(`Are you sure you want to delete expense '${exp.title}'?`)) {
    router.delete(`/expenses/${exp.id}`);
  }
};
</script>
