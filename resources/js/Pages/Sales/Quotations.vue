<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-6">
      <!-- Title Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl sm:text-3xl font-black font-heading text-slate-900">
            Sales Price Quotes & Proformas
          </h1>
          <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">
            Generate formal quotations, email price estimates, and convert quotes into live POS sales
          </p>
        </div>

        <button 
          @click="showAddModal = true" 
          class="px-5 py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-sm shadow-md flex items-center justify-center gap-2 transition-all active:scale-95"
        >
          <Plus class="w-4 h-4" />
          <span>Create New Quotation</span>
        </button>
      </div>

      <!-- Quotations Audit Table Card -->
      <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-xs space-y-4">
        <h3 class="font-bold text-base font-heading text-slate-900">Recent Price Quotations</h3>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] font-bold">
                <th class="py-3 px-3">Quotation #</th>
                <th class="py-3 px-3">Customer</th>
                <th class="py-3 px-3">Valid Until</th>
                <th class="py-3 px-3 text-right">Grand Total</th>
                <th class="py-3 px-3 text-center">Status</th>
                <th class="py-3 px-3 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="q in (quotations.data || quotations)" :key="q.id" class="hover:bg-slate-50 transition-colors">
                <td class="py-3.5 px-3 font-bold font-mono text-slate-900">{{ q.quotation_no }}</td>
                <td class="py-3.5 px-3 font-semibold text-slate-700">{{ q.customer?.name || 'Walk-in Client' }}</td>
                <td class="py-3.5 px-3 font-mono text-slate-600">{{ q.valid_until || '14 Days' }}</td>
                <td class="py-3.5 px-3 text-right font-mono font-extrabold text-emerald-700">
                  ৳{{ formatMoney(q.grand_total) }}
                </td>
                <td class="py-3.5 px-3 text-center">
                  <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase border', q.status === 'converted' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-indigo-50 text-indigo-700 border-indigo-200']">
                    {{ q.status }}
                  </span>
                </td>
                <td class="py-3.5 px-3 text-center">
                  <div class="flex items-center justify-center gap-2">
                    <button 
                      v-if="q.status !== 'converted'" 
                      @click="convertQuotation(q)" 
                      class="px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 text-[10px] font-extrabold flex items-center gap-1 transition-colors"
                      title="Convert into completed POS Order"
                    >
                      <ArrowRightCircle class="w-3.5 h-3.5" />
                      <span>Convert 🚀</span>
                    </button>
                    <button 
                      @click="deleteQuotation(q)" 
                      class="p-1 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-slate-100 transition-colors"
                      title="Delete Quotation"
                    >
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!quotations || (quotations.data && quotations.data.length === 0)">
                <td colspan="6" class="py-8 text-center text-slate-400">No sales quotations created yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- CREATE QUOTATION MODAL -->
    <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
      <div class="bg-white border border-slate-200 rounded-3xl max-w-lg w-full overflow-hidden shadow-2xl">
        <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50">
          <h3 class="font-bold text-lg font-heading text-slate-900">Create Sales Quotation</h3>
          <button @click="showAddModal = false" class="text-slate-400 hover:text-slate-700">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submitQuotation" class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Select Customer</label>
            <select v-model="form.customer_id" class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs font-bold text-slate-900 focus:outline-none focus:border-emerald-600 focus:bg-white">
              <option :value="null">Walk-in Client (No Account)</option>
              <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }} ({{ c.phone }})</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Quotation Amount Total (৳) *</label>
            <input type="number" step="0.01" v-model.number="form.grand_total" required class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 font-mono font-bold focus:outline-none focus:border-emerald-600 focus:bg-white" />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Valid Until Date</label>
            <input type="date" v-model="form.valid_until" class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:border-emerald-600 focus:bg-white" />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Quotation Terms / Notes</label>
            <textarea v-model="form.notes" rows="2" placeholder="e.g. Valid for 14 days. 50% advance on order confirmation." class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:border-emerald-600 focus:bg-white"></textarea>
          </div>

          <div class="pt-2 flex gap-3">
            <button type="submit" class="flex-1 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md">
              Generate Price Quote
            </button>
            <button type="button" @click="showAddModal = false" class="px-5 py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs">
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
import { Plus, X, ArrowRightCircle, Trash2 } from 'lucide-vue-next';

const props = defineProps({
  quotations: Object,
  customers: Array,
  products: Array,
});

const showAddModal = ref(false);

const form = ref({
  customer_id: null,
  subtotal: 5000,
  tax_amount: 250,
  discount_amount: 0,
  grand_total: 5250,
  items_json: [{ name: 'Sample Item', qty: 1, price: 5000 }],
  valid_until: new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().substr(0, 10),
  notes: 'Valid for 14 days.',
});

const formatMoney = (val) => {
  return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const submitQuotation = () => {
  router.post('/sales/quotations', form.value, {
    onSuccess: () => {
      showAddModal.value = false;
    }
  });
};

const convertQuotation = (q) => {
  if (confirm(`Convert Quotation ${q.quotation_no} (৳${formatMoney(q.grand_total)}) into an active POS Order? Inventory will be updated.`)) {
    router.post(`/sales/quotations/${q.id}/convert`);
  }
};

const deleteQuotation = (q) => {
  if (confirm(`Are you sure you want to delete quotation ${q.quotation_no}?`)) {
    router.delete(`/sales/quotations/${q.id}`);
  }
};
</script>
