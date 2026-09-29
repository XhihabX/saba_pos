<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-100 min-h-screen">
      <!-- Title & Operational Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div class="flex items-center gap-2 text-rose-600 font-bold text-xs uppercase tracking-wider">
            <Crown class="w-4 h-4" />
            <span>SaaS Platform Control</span>
          </div>
          <h1 class="text-2xl font-black font-heading text-slate-900 mt-1">Subscription Tier Configurations</h1>
          <p class="text-xs text-slate-500 mt-1">Configure pricing, store limits, user limits, and feature access for merchant subscription plans</p>
        </div>
        <button @click="openAddPlanModal" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-rose-600 to-amber-500 hover:from-rose-500 hover:to-amber-400 text-white font-extrabold text-xs shadow-md flex items-center gap-1.5 shrink-0">
          <Plus class="w-4 h-4" />
          <span>+ Create SaaS Plan</span>
        </button>
      </div>

      <!-- Plans Cards Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div v-for="plan in plans" :key="plan.id" class="p-8 rounded-2xl bg-white border border-slate-200 shadow-xs flex flex-col justify-between hover:border-rose-500 transition-all">
          <div>
            <div class="flex items-center justify-between mb-2">
              <h3 class="text-xl font-bold font-heading text-slate-900">{{ plan.name }}</h3>
              <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase border', plan.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200']">
                {{ plan.is_active ? 'Active' : 'Disabled' }}
              </span>
            </div>
            <div class="text-3xl font-black font-heading text-emerald-700 mb-6">
              ৳{{ formatMoney(plan.monthly_price) }} <span class="text-xs text-slate-500 font-normal">/ mo</span>
            </div>

            <ul class="space-y-3 text-xs text-slate-700 mb-8">
              <li class="flex items-center gap-2">✓ Max Outlets: <strong class="text-slate-900">{{ plan.max_stores }} Stores</strong></li>
              <li class="flex items-center gap-2">✓ Max Staff Users: <strong class="text-slate-900">{{ plan.max_users }} Users</strong></li>
              <li class="flex items-center gap-2">✓ Cashier POS Hotkeys & Barcode Scanner</li>
              <li class="flex items-center gap-2">✓ Real-time Profit & Loss COGS Reports</li>
            </ul>
          </div>

          <div class="flex items-center gap-2">
            <button @click="openEditPlanModal(plan)" class="flex-1 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs border border-slate-200 transition-colors">
              Edit Tier Config
            </button>
            <button @click="deletePlan(plan)" class="p-2 rounded-xl bg-slate-100 hover:bg-rose-100 hover:text-rose-600 text-slate-500 font-bold text-xs border border-slate-200 transition-colors" title="Delete Plan">
              <Trash2 class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- CREATE/EDIT PLAN MODAL -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
      <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200">
          <h3 class="font-bold text-lg font-heading text-slate-900">{{ isEditing ? 'Edit SaaS Plan Tier' : 'Create SaaS Plan Tier' }}</h3>
          <button @click="showModal = false" class="text-slate-400 hover:text-slate-700">✕</button>
        </div>

        <form @submit.prevent="submitPlan" class="space-y-3 text-xs">
          <div>
            <label class="block text-slate-700 font-semibold mb-1">Plan Name</label>
            <input type="text" v-model="planForm.name" required placeholder="e.g. Enterprise ERP" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500" />
          </div>

          <div>
            <label class="block text-slate-700 font-semibold mb-1">Monthly Price (৳)</label>
            <input type="number" step="0.01" v-model.number="planForm.monthly_price" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-mono focus:outline-none focus:border-rose-500" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-700 font-semibold mb-1">Max Stores Limit</label>
              <input type="number" v-model.number="planForm.max_stores" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-mono focus:outline-none focus:border-rose-500" />
            </div>
            <div>
              <label class="block text-slate-700 font-semibold mb-1">Max Staff Users</label>
              <input type="number" v-model.number="planForm.max_users" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-mono focus:outline-none focus:border-rose-500" />
            </div>
          </div>

          <div v-if="isEditing">
            <label class="block text-slate-700 font-semibold mb-1">Plan Status</label>
            <select v-model="planForm.is_active" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500">
              <option :value="true">Active (Visible to Merchants)</option>
              <option :value="false">Disabled / Inactive</option>
            </select>
          </div>

          <div class="pt-3 flex gap-3">
            <button type="submit" class="flex-1 py-3 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold shadow-md">{{ isEditing ? 'Update Plan' : 'Save SaaS Plan' }}</button>
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
import { Crown, Plus, Trash2 } from 'lucide-vue-next';

defineProps({
  plans: Array,
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const planForm = ref({
  name: '',
  monthly_price: 3999,
  max_stores: 5,
  max_users: 10,
  is_active: true,
});

const openAddPlanModal = () => {
  isEditing.value = false;
  editingId.value = null;
  planForm.value = {
    name: '',
    monthly_price: 3999,
    max_stores: 5,
    max_users: 10,
    is_active: true,
  };
  showModal.value = true;
};

const openEditPlanModal = (plan) => {
  isEditing.value = true;
  editingId.value = plan.id;
  planForm.value = {
    name: plan.name,
    monthly_price: parseFloat(plan.monthly_price) || 0,
    max_stores: plan.max_stores,
    max_users: plan.max_users,
    is_active: Boolean(plan.is_active),
  };
  showModal.value = true;
};

const formatMoney = (val) => {
  return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const submitPlan = () => {
  if (isEditing.value) {
    router.post(`/super-admin/plans/${editingId.value}/update`, planForm.value, {
      onSuccess: () => {
        showModal.value = false;
      }
    });
  } else {
    router.post('/super-admin/plans', planForm.value, {
      onSuccess: () => {
        showModal.value = false;
      }
    });
  }
};

const deletePlan = (plan) => {
  if (confirm(`Are you sure you want to delete SaaS Plan '${plan.name}'?`)) {
    router.delete(`/super-admin/plans/${plan.id}`);
  }
};
</script>
