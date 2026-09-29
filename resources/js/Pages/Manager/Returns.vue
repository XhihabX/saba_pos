<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-100 min-h-screen">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div class="flex items-center gap-2 text-cyan-700 font-bold text-xs uppercase tracking-wider">
            <RotateCcw class="w-4 h-4" />
            <span>Store Manager Portal</span>
          </div>
          <h1 class="text-2xl font-black font-heading text-slate-900 mt-1">Branch Returns & Refund Approvals</h1>
          <p class="text-slate-500 text-xs mt-1">Audit customer product returns, approve cash refunds, and restore store inventory balance.</p>
        </div>
        <button 
          @click="showModal = true"
          class="px-4 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs shadow-md flex items-center gap-2 shrink-0"
        >
          <Plus class="w-4 h-4" />
          <span>Process Return & Refund</span>
        </button>
      </div>

      <!-- Returns Table -->
      <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 font-semibold uppercase tracking-wider">
                <th class="py-3.5 px-4">Order Invoice</th>
                <th class="py-3.5 px-4">Product Returned</th>
                <th class="py-3.5 px-4 text-center">Returned Qty</th>
                <th class="py-3.5 px-4 text-right">Refund Amount</th>
                <th class="py-3.5 px-4">Reason</th>
                <th class="py-3.5 px-4">Approved By</th>
                <th class="py-3.5 px-4 text-right">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr v-for="ret in returns.data" :key="ret.id" class="hover:bg-slate-50 transition-colors">
                <td class="py-3.5 px-4 font-mono font-bold text-indigo-700">{{ ret.order?.invoice_no }}</td>
                <td class="py-3.5 px-4 font-semibold text-slate-900">{{ ret.product?.name }}</td>
                <td class="py-3.5 px-4 text-center font-bold text-slate-900">{{ ret.quantity }} pcs</td>
                <td class="py-3.5 px-4 text-right font-bold text-rose-600">৳{{ parseFloat(ret.refund_amount).toLocaleString() }}</td>
                <td class="py-3.5 px-4 text-slate-700">{{ ret.reason }}</td>
                <td class="py-3.5 px-4 text-slate-800 font-medium">{{ ret.approved_by }}</td>
                <td class="py-3.5 px-4 text-right text-slate-500">{{ new Date(ret.created_at).toLocaleDateString() }}</td>
              </tr>
              <tr v-if="returns.data?.length === 0">
                <td colspan="7" class="py-8 text-center text-slate-500 font-medium">No sales returns recorded yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add Return Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div class="bg-white border border-slate-200 rounded-3xl p-6 w-full max-w-md shadow-2xl space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-slate-200">
            <h3 class="text-lg font-bold text-slate-900">Process Return & Refund</h3>
            <button @click="showModal = false" class="text-slate-400 hover:text-slate-700">&times;</button>
          </div>

          <form @submit.prevent="submitForm" class="space-y-4 text-xs">
            <div>
              <label class="block text-slate-700 font-semibold mb-1">Invoice Number</label>
              <input v-model="form.invoice_no" required type="text" placeholder="INV-20260920-XXXX" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-mono" />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-slate-700 font-semibold mb-1">Product ID</label>
                <input v-model="form.product_id" required type="number" placeholder="Product ID" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900" />
              </div>
              <div>
                <label class="block text-slate-700 font-semibold mb-1">Return Qty</label>
                <input v-model="form.quantity" required type="number" min="1" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900" />
              </div>
            </div>

            <div>
              <label class="block text-slate-700 font-semibold mb-1">Cash Refund Amount (৳)</label>
              <input v-model="form.refund_amount" required type="number" step="0.01" min="0" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-bold text-rose-600" />
            </div>

            <div>
              <label class="block text-slate-700 font-semibold mb-1">Reason for Return</label>
              <select v-model="form.reason" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900">
                <option value="Damaged / Defective Item">Damaged / Defective Item</option>
                <option value="Wrong Size / Specification">Wrong Size / Specification</option>
                <option value="Customer Mind Change">Customer Mind Change</option>
                <option value="Expired Item">Expired Item</option>
              </select>
            </div>

            <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
              <button type="button" @click="showModal = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold">Cancel</button>
              <button type="submit" :disabled="form.processing" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl shadow-md">Approve Refund & Re-stock</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { RotateCcw, Plus } from 'lucide-vue-next';

defineProps({
  returns: Object,
});

const showModal = ref(false);
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
