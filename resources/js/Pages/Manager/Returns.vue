<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-900 min-h-screen text-slate-100 selection:bg-rose-500 selection:text-white">
      <!-- Dark Hero Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-rose-950 to-slate-900 border border-rose-500/20 p-6 sm:p-8 shadow-2xl">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-rose-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/30 text-rose-400 font-mono text-xs font-bold uppercase tracking-wider mb-3">
              <RotateCcw class="w-4 h-4" />
              <span>Store Manager Audit Control</span>
            </div>
            <h1 class="text-3xl font-black font-heading tracking-tight text-white">Branch Returns & Refund Approvals</h1>
            <p class="text-slate-400 text-xs sm:text-sm mt-1 max-w-2xl">
              Audit customer item returns, authorize cash refunds, record defective reasons, and restore branch stock levels.
            </p>
          </div>
          <button 
            @click="showModal = true"
            class="px-5 py-3 rounded-2xl bg-gradient-to-r from-rose-500 to-red-600 hover:from-rose-600 hover:to-red-700 text-white font-extrabold text-xs shadow-lg shadow-rose-500/20 flex items-center gap-2 shrink-0 transition-all hover:scale-105 active:scale-95"
          >
            <Plus class="w-4 h-4" />
            <span>Process Return & Refund</span>
          </button>
        </div>
      </div>

      <!-- Returns Table Card -->
      <div class="bg-slate-800/90 border border-slate-700/80 rounded-3xl p-6 shadow-2xl space-y-4 backdrop-blur-md">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-700/60">
          <div class="relative w-full sm:w-80">
            <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input 
              v-model="searchQuery" 
              type="text" 
              placeholder="Search by order invoice no or reason..." 
              class="w-full pl-10 pr-4 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs text-slate-100 placeholder-slate-400 focus:outline-none focus:border-rose-500"
            />
          </div>
          <span class="text-xs text-slate-400 font-mono">Total Return Audits: {{ returnsList.length }}</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="text-slate-400 border-b border-slate-700/60 uppercase text-[10px] bg-slate-900/60 font-semibold tracking-wider">
                <th class="py-3.5 px-4">Order Invoice</th>
                <th class="py-3.5 px-4">Product Returned</th>
                <th class="py-3.5 px-4 text-center">Returned Qty</th>
                <th class="py-3.5 px-4 text-right">Cash Refund Amount</th>
                <th class="py-3.5 px-4">Reason for Return</th>
                <th class="py-3.5 px-4">Approved By</th>
                <th class="py-3.5 px-4 text-right">Date Executed</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/60">
              <tr v-for="ret in filteredReturns" :key="ret.id" class="hover:bg-slate-700/30 transition-colors">
                <td class="py-4 px-4 font-mono font-bold text-indigo-400">{{ ret.order?.invoice_no || 'INV-2026-XXXX' }}</td>
                <td class="py-4 px-4 font-bold text-white text-sm font-heading">{{ ret.product?.name || 'Item' }}</td>
                <td class="py-4 px-4 text-center font-bold text-white font-mono">{{ ret.quantity }} pcs</td>
                <td class="py-4 px-4 text-right font-bold text-rose-400 font-mono text-sm">৳{{ parseFloat(ret.refund_amount).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</td>
                <td class="py-4 px-4 text-slate-300">{{ ret.reason }}</td>
                <td class="py-4 px-4 text-slate-300 font-medium">{{ ret.approved_by || 'Store Manager' }}</td>
                <td class="py-4 px-4 text-right text-slate-400 font-mono">{{ new Date(ret.created_at).toLocaleDateString() }}</td>
              </tr>
              <tr v-if="filteredReturns.length === 0">
                <td colspan="7" class="py-12 text-center text-slate-400 font-medium">No sales returns recorded yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add Return Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-md p-4">
        <div class="bg-slate-900 border border-slate-700 rounded-3xl p-6 w-full max-w-md shadow-2xl space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <h3 class="text-lg font-black text-white font-heading flex items-center gap-2">
              <RotateCcw class="w-5 h-5 text-rose-400" />
              <span>Process Return & Refund</span>
            </h3>
            <button @click="showModal = false" class="text-slate-400 hover:text-white text-xl font-bold">&times;</button>
          </div>

          <form @submit.prevent="submitForm" class="space-y-4 text-xs">
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Invoice Number</label>
              <input v-model="form.invoice_no" required type="text" placeholder="INV-20260920-XXXX" class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-slate-100 font-mono focus:outline-none focus:border-rose-500" />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-slate-300 font-semibold mb-1">Product ID</label>
                <input v-model="form.product_id" required type="number" placeholder="Product ID" class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-slate-100 focus:outline-none focus:border-rose-500 font-mono" />
              </div>
              <div>
                <label class="block text-slate-300 font-semibold mb-1">Return Qty</label>
                <input v-model="form.quantity" required type="number" min="1" class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-slate-100 focus:outline-none focus:border-rose-500 font-mono" />
              </div>
            </div>

            <div>
              <label class="block text-slate-300 font-semibold mb-1">Cash Refund Amount (৳)</label>
              <input v-model="form.refund_amount" required type="number" step="0.01" min="0" class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-slate-100 font-bold text-rose-400 font-mono focus:outline-none focus:border-rose-500" />
            </div>

            <div>
              <label class="block text-slate-300 font-semibold mb-1">Reason for Return</label>
              <select v-model="form.reason" required class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-slate-100 focus:outline-none focus:border-rose-500">
                <option value="Damaged / Defective Item">Damaged / Defective Item</option>
                <option value="Wrong Size / Specification">Wrong Size / Specification</option>
                <option value="Customer Mind Change">Customer Mind Change</option>
                <option value="Expired Item">Expired Item</option>
              </select>
            </div>

            <div class="pt-4 border-t border-slate-800 flex justify-end gap-3">
              <button type="button" @click="showModal = false" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl font-semibold">Cancel</button>
              <button type="submit" :disabled="form.processing" class="px-5 py-2.5 bg-gradient-to-r from-rose-500 to-red-600 hover:from-rose-600 hover:to-red-700 text-white font-bold rounded-xl shadow-lg shadow-rose-500/20">Approve Refund & Restock</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { RotateCcw, Plus, Search } from 'lucide-vue-next';

const props = defineProps({
  returns: Object,
});

const searchQuery = ref('');
const showModal = ref(false);

const returnsList = computed(() => props.returns?.data || props.returns || []);

const filteredReturns = computed(() => {
  if (!searchQuery.value.trim()) return returnsList.value;
  const q = searchQuery.value.toLowerCase().trim();
  return returnsList.value.filter(ret => 
    (ret.order?.invoice_no && ret.order.invoice_no.toLowerCase().includes(q)) ||
    (ret.reason && ret.reason.toLowerCase().includes(q))
  );
});

const form = useForm({
  invoice_no: 'INV-20260920-0001',
  product_id: 1,
  quantity: 1,
  refund_amount: 500,
  reason: 'Damaged / Defective Item',
});

const submitForm = () => {
  form.post('/manager/returns', {
    onSuccess: () => {
      showModal.value = false;
      form.reset();
    }
  });
};
</script>
