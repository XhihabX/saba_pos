<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-8 bg-slate-50/70 min-h-screen">
      <!-- 👑 Hero Header Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-rose-950 to-slate-900 text-white p-6 sm:p-8 shadow-xl border border-slate-800">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-rose-500/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div class="space-y-2">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 font-bold text-xs uppercase tracking-wider">
              <Zap class="w-3.5 h-3.5" />
              <span>SaaS Platform Monitization</span>
            </div>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black font-heading tracking-tight text-white">
              Subscription Pricing & Tier Configurations
            </h1>
            <p class="text-xs sm:text-sm text-slate-300 max-w-2xl">
              Configure pricing tiers, store outlet quotas, staff seat limits, POS feature flags, and subscription plan visibility
            </p>
          </div>

          <button 
            @click="openAddPlanModal"
            class="px-5 py-3 rounded-2xl bg-gradient-to-r from-rose-600 to-amber-500 hover:from-rose-500 hover:to-amber-400 text-white font-black text-xs shadow-lg shadow-rose-600/30 flex items-center gap-2 cursor-pointer shrink-0"
          >
            <Plus class="w-4 h-4 stroke-[3]" />
            <span>+ Create SaaS Plan Tier</span>
          </button>
        </div>
      </div>

      <!-- ⚡ Plans Pricing Cards Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div 
          v-for="plan in plans" 
          :key="plan.id" 
          class="p-8 rounded-3xl bg-white border border-slate-200/80 shadow-sm hover:shadow-xl hover:border-rose-400 transition-all duration-300 flex flex-col justify-between relative group overflow-hidden"
        >
          <!-- Accent Top Bar -->
          <div class="absolute top-0 inset-x-0 h-2 bg-gradient-to-r from-rose-500 to-amber-500"></div>

          <div>
            <div class="flex items-center justify-between mb-3">
              <h3 class="text-xl font-black font-heading text-slate-900">{{ plan.name }}</h3>
              <span :class="['px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border', plan.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200']">
                {{ plan.is_active ? 'Active' : 'Disabled' }}
              </span>
            </div>

            <div class="text-4xl font-black font-heading text-slate-900 mb-6">
              ৳{{ formatMoney(plan.monthly_price) }} <span class="text-xs text-slate-500 font-bold">/ month</span>
            </div>

            <!-- Features Checklist -->
            <ul class="space-y-3.5 text-xs text-slate-700 mb-8 border-t border-slate-100 pt-6">
              <li class="flex items-center gap-3">
                <CheckCircle2 class="w-4 h-4 text-emerald-600 shrink-0" />
                <span>Max Outlets: <strong class="text-slate-900 font-extrabold">{{ plan.max_stores }} Store Outlets</strong></span>
              </li>
              <li class="flex items-center gap-3">
                <CheckCircle2 class="w-4 h-4 text-emerald-600 shrink-0" />
                <span>Max Staff Seats: <strong class="text-slate-900 font-extrabold">{{ plan.max_users }} Staff Users</strong></span>
              </li>
              <li class="flex items-center gap-3">
                <CheckCircle2 class="w-4 h-4 text-emerald-600 shrink-0" />
                <span>Cashier POS Hotkeys & Barcode Scanner</span>
              </li>
              <li class="flex items-center gap-3">
                <CheckCircle2 class="w-4 h-4 text-emerald-600 shrink-0" />
                <span>Real-time Profit & Loss COGS Analytics</span>
              </li>
              <li class="flex items-center gap-3">
                <CheckCircle2 class="w-4 h-4 text-emerald-600 shrink-0" />
                <span>Multi-channel SMS/Email Invoice Dispatch</span>
              </li>
            </ul>
          </div>

          <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
            <button 
              @click="openEditPlanModal(plan)" 
              class="flex-1 py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-extrabold text-xs border border-slate-200 transition-colors cursor-pointer"
            >
              Edit Tier Config
            </button>
            <button 
              @click="deletePlan(plan)" 
              class="p-3 rounded-2xl bg-slate-100 hover:bg-rose-100 hover:text-rose-600 text-slate-500 font-bold border border-slate-200 transition-colors cursor-pointer" 
              title="Delete Plan"
            >
              <Trash2 class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- CREATE/EDIT PLAN MODAL -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
      <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 sm:p-8 space-y-6 shadow-2xl animate-in fade-in zoom-in duration-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200">
          <h3 class="font-black text-xl font-heading text-slate-900">{{ isEditing ? 'Edit SaaS Plan Tier' : 'Create SaaS Plan Tier' }}</h3>
          <button @click="showModal = false" class="text-slate-400 hover:text-slate-700">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submitPlan" class="space-y-4 text-xs">
          <div>
            <label class="block text-slate-700 font-bold mb-1">Plan Name</label>
            <input type="text" v-model="planForm.name" required placeholder="e.g. Enterprise ERP" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-bold focus:outline-none focus:border-rose-500" />
          </div>

          <div>
            <label class="block text-slate-700 font-bold mb-1">Monthly Subscription Price (৳)</label>
            <input type="number" step="0.01" v-model.number="planForm.monthly_price" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-mono font-bold focus:outline-none focus:border-rose-500" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-700 font-bold mb-1">Max Stores Limit</label>
              <input type="number" v-model.number="planForm.max_stores" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-mono font-bold focus:outline-none focus:border-rose-500" />
            </div>
            <div>
              <label class="block text-slate-700 font-bold mb-1">Max Staff Users</label>
              <input type="number" v-model.number="planForm.max_users" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-mono font-bold focus:outline-none focus:border-rose-500" />
            </div>
          </div>

          <div v-if="isEditing">
            <label class="block text-slate-700 font-bold mb-1">Plan Status</label>
            <select v-model="planForm.is_active" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-bold focus:outline-none focus:border-rose-500">
              <option :value="true">Active (Visible to Merchants)</option>
              <option :value="false">Disabled / Inactive</option>
            </select>
          </div>

          <div class="pt-4 flex gap-3">
            <button type="submit" class="flex-1 py-3.5 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs shadow-md cursor-pointer">
              {{ isEditing ? 'Update Plan' : 'Save SaaS Plan' }}
            </button>
            <button type="button" @click="showModal = false" class="px-6 py-3.5 rounded-2xl bg-slate-100 text-slate-700 font-bold text-xs hover:bg-slate-200 cursor-pointer">
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
import { Zap, Plus, Trash2, CheckCircle2, X } from 'lucide-vue-next';

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

