<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-100 min-h-screen">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div class="flex items-center gap-2 text-cyan-700 font-bold text-xs uppercase tracking-wider">
            <ArrowLeftRight class="w-4 h-4" />
            <span>Store Manager Portal</span>
          </div>
          <h1 class="text-2xl font-black font-heading text-slate-900 mt-1">Inter-Outlet Stock Transfers</h1>
          <p class="text-slate-500 text-xs mt-1">Transfer stock items safely between branch outlets with automatic balance adjustments.</p>
        </div>
        <button 
          @click="showModal = true"
          class="px-4 py-2.5 rounded-xl bg-cyan-600 hover:bg-cyan-700 text-white font-extrabold text-xs shadow-md flex items-center gap-2 shrink-0"
        >
          <Plus class="w-4 h-4" />
          <span>New Stock Transfer Request</span>
        </button>
      </div>

      <!-- Transfers List -->
      <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 font-semibold uppercase tracking-wider">
                <th class="py-3.5 px-4">Transfer Reference</th>
                <th class="py-3.5 px-4">Source Outlet</th>
                <th class="py-3.5 px-4">Target Outlet</th>
                <th class="py-3.5 px-4">Transfer Items</th>
                <th class="py-3.5 px-4">Status</th>
                <th class="py-3.5 px-4 text-right">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr v-for="tr in transfers.data" :key="tr.id" class="hover:bg-slate-50 transition-colors">
                <td class="py-3.5 px-4 font-mono font-bold text-cyan-700">{{ tr.transfer_no }}</td>
                <td class="py-3.5 px-4 font-semibold text-slate-900">{{ tr.from_store?.name }}</td>
                <td class="py-3.5 px-4 font-semibold text-indigo-700">{{ tr.to_store?.name }}</td>
                <td class="py-3.5 px-4 text-slate-700">
                  <span v-for="item in tr.items" :key="item.id" class="inline-block bg-slate-100 px-2 py-0.5 rounded text-[11px] mr-1 mb-1 border border-slate-200 font-semibold">
                    {{ item.product?.name }}: {{ item.quantity }} pcs
                  </span>
                </td>
                <td class="py-3.5 px-4">
                  <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                    {{ tr.status }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-right text-slate-500">{{ new Date(tr.created_at).toLocaleDateString() }}</td>
              </tr>
              <tr v-if="transfers.data?.length === 0">
                <td colspan="6" class="py-8 text-center text-slate-500 font-medium">No stock transfers recorded yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add Transfer Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div class="bg-white border border-slate-200 rounded-3xl p-6 w-full max-w-xl shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between pb-3 border-b border-slate-200">
            <h3 class="text-lg font-bold text-slate-900">Create Inter-Outlet Stock Transfer</h3>
            <button @click="showModal = false" class="text-slate-400 hover:text-slate-700">&times;</button>
          </div>

          <form @submit.prevent="submitForm" class="space-y-4 text-xs">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-slate-700 font-semibold mb-1">Source Store (From)</label>
                <select v-model="form.from_store_id" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900">
                  <option value="" disabled>Select Source Store</option>
                  <option v-for="st in stores" :key="st.id" :value="st.id">{{ st.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-slate-700 font-semibold mb-1">Target Store (To)</label>
                <select v-model="form.to_store_id" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900">
                  <option value="" disabled>Select Target Store</option>
                  <option v-for="st in stores" :key="st.id" :value="st.id">{{ st.name }}</option>
                </select>
              </div>
            </div>

            <!-- Items -->
            <div>
              <label class="block text-slate-700 font-semibold mb-2">Transfer Products & Quantities</label>
              <div v-for="(item, index) in form.items" :key="index" class="grid grid-cols-12 gap-2 mb-2 items-center">
                <div class="col-span-7">
                  <select v-model="item.product_id" required class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs">
                    <option value="" disabled>Select Product</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} (SKU: {{ p.sku }})</option>
                  </select>
                </div>
                <div class="col-span-4">
                  <input v-model="item.quantity" type="number" step="1" min="1" placeholder="Quantity" class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs" />
                </div>
                <div class="col-span-1 text-right">
                  <button type="button" @click="removeItem(index)" class="text-rose-600 hover:text-rose-800 font-bold">&times;</button>
                </div>
              </div>
              <button type="button" @click="addItem" class="text-xs text-cyan-700 hover:text-cyan-900 font-semibold flex items-center gap-1 mt-2">
                + Add Transfer Product
              </button>
            </div>

            <div>
              <label class="block text-slate-700 font-semibold mb-1">Transfer Notes</label>
              <textarea v-model="form.notes" rows="2" placeholder="e.g. Stock replenishment for weekend campaign" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900"></textarea>
            </div>

            <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
              <button type="button" @click="showModal = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold">Cancel</button>
              <button type="submit" :disabled="form.processing" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-700 text-white font-bold rounded-xl shadow-md">Execute Stock Transfer</button>
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
import { ArrowLeftRight, Plus } from 'lucide-vue-next';

const props = defineProps({
  transfers: Object,
  stores: Array,
  products: Array,
});

const showModal = ref(false);
const form = useForm({
  from_store_id: props.stores?.[0]?.id || '',
  to_store_id: props.stores?.[1]?.id || props.stores?.[0]?.id || '',
  notes: '',
  items: [
    { product_id: props.products?.[0]?.id || '', quantity: 5 }
  ]
});

const addItem = () => {
  form.items.push({ product_id: props.products?.[0]?.id || '', quantity: 1 });
};

const removeItem = (index) => {
  if (form.items.length > 1) {
    form.items.splice(index, 1);
  }
};

const submitForm = () => {
  form.post('/manager/transfers', {
    onSuccess: () => {
      showModal.value = false;
      form.reset();
    }
  });
};
</script>
