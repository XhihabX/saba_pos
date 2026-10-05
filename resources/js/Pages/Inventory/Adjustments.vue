<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 space-y-6 max-w-7xl mx-auto text-slate-900 dark:text-slate-100">
      <!-- Top Title Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div>
          <h1 class="text-2xl font-black font-heading tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <Boxes class="w-7 h-7 text-indigo-600 dark:text-indigo-400" />
            Inventory Stock Adjustments & Waste Write-Offs
          </h1>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Log damaged items, expired products, stock write-offs, and stock audit reconciliations.</p>
        </div>

        <button 
          @click="showCreateModal = true" 
          class="px-5 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-lg shadow-indigo-600/20 flex items-center justify-center gap-2 transition-all active:scale-95"
        >
          <Plus class="w-4 h-4" />
          <span>Record Stock Adjustment</span>
        </button>
      </div>

      <!-- Adjustments List Table -->
      <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-200 dark:border-slate-800 font-bold text-xs text-slate-700 dark:text-slate-300 uppercase tracking-wider flex justify-between items-center">
          <span>Adjustment Log History ({{ adjustments.total || 0 }})</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-500 dark:text-slate-400 uppercase font-extrabold text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800">
              <tr>
                <th class="p-4">Ref #</th>
                <th class="p-4">Store Outlet</th>
                <th class="p-4">Product Name</th>
                <th class="p-4">Type / Reason</th>
                <th class="p-4 text-center">Qty Adjusted</th>
                <th class="p-4">Recorded By</th>
                <th class="p-4">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800 font-medium">
              <tr v-if="!adjustments.data || adjustments.data.length === 0">
                <td colspan="7" class="p-8 text-center text-slate-400">
                  No stock adjustment records found. Click "+ Record Stock Adjustment" to log inventory write-offs.
                </td>
              </tr>
              <tr v-for="adj in adjustments.data" :key="adj.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                <td class="p-4 font-mono font-bold text-slate-900 dark:text-white">{{ adj.reference_no }}</td>
                <td class="p-4 font-bold text-slate-800 dark:text-slate-200">{{ adj.store?.name }}</td>
                <td class="p-4 font-bold text-slate-900 dark:text-white">{{ adj.product?.name }}</td>
                <td class="p-4">
                  <span :class="['px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider', typeColor(adj.type)]">
                    {{ formatType(adj.type) }}
                  </span>
                </td>
                <td class="p-4 text-center font-mono font-bold text-indigo-600 dark:text-indigo-400">{{ adj.quantity }}</td>
                <td class="p-4 text-slate-600 dark:text-slate-400">{{ adj.user?.name || 'Staff' }}</td>
                <td class="p-4 text-slate-500 dark:text-slate-400 font-mono text-[11px]">{{ formatDate(adj.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Create Adjustment Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm">
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl text-slate-900 dark:text-white">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
          <h3 class="text-lg font-extrabold font-heading">Record Stock Adjustment</h3>
          <button @click="showCreateModal = false" class="p-1.5 rounded-xl text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submitAdjustment" class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Store Outlet</label>
            <select v-model="form.store_id" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-bold">
              <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Product</label>
            <select v-model="form.product_id" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-bold">
              <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} (SKU: {{ p.sku }})</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Adjustment Reason / Type</label>
            <select v-model="form.type" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-bold">
              <option value="damage">Damaged Product (Deduct)</option>
              <option value="expired">Expired Goods (Deduct)</option>
              <option value="stolen">Stolen / Lost Inventory (Deduct)</option>
              <option value="write_off">General Store Write-off (Deduct)</option>
              <option value="audit_deduction">Audit Count Discrepancy (Deduct)</option>
              <option value="audit_addition">Audit Count Stock Addition (Add)</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Quantity Adjusted</label>
            <input type="number" step="0.01" min="0.01" v-model.number="form.quantity" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold" />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Notes / Internal Reason</label>
            <textarea v-model="form.notes" rows="2" placeholder="e.g. Water damage during transport" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs"></textarea>
          </div>

          <div class="pt-2 flex gap-3">
            <button type="submit" :disabled="form.processing" class="flex-1 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-md">
              Save Adjustment Record
            </button>
            <button type="button" @click="showCreateModal = false" class="px-4 py-3 rounded-xl bg-slate-200 dark:bg-slate-800 font-bold text-xs">
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
import { useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Boxes, Plus, X } from 'lucide-vue-next';

const props = defineProps({
  adjustments: Object,
  stores: Array,
  products: Array,
});

const showCreateModal = ref(false);

const form = useForm({
  store_id: props.stores[0]?.id || 1,
  product_id: props.products[0]?.id || 1,
  type: 'damage',
  quantity: 1,
  notes: '',
});

const submitAdjustment = () => {
  form.post('/inventory/adjustments', {
    onSuccess: () => {
      showCreateModal.value = false;
      form.reset('notes', 'quantity');
    }
  });
};

const formatType = (type) => {
  return String(type || '').replace(/_/g, ' ');
};

const typeColor = (type) => {
  switch (type) {
    case 'audit_addition':
      return 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/30';
    case 'expired':
    case 'damage':
      return 'bg-rose-500/10 text-rose-600 border border-rose-500/30';
    case 'stolen':
      return 'bg-amber-500/10 text-amber-600 border border-amber-500/30';
    default:
      return 'bg-indigo-500/10 text-indigo-600 border border-indigo-500/30';
  }
};

const formatDate = (d) => {
  if (!d) return 'N/A';
  return new Date(d).toLocaleString();
};
</script>
