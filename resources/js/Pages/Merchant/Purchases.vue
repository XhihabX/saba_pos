<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-100 min-h-screen">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div class="flex items-center gap-2 text-indigo-600 font-bold text-xs uppercase tracking-wider">
            <ShoppingBag class="w-4 h-4" />
            <span>Merchant HQ Portal</span>
          </div>
          <h1 class="text-2xl font-black font-heading text-slate-900 mt-1">Purchase Orders & Inventory Inflow</h1>
          <p class="text-slate-500 text-xs mt-1">Record wholesale purchases, track supplier inventory receiving, and update stock balance.</p>
        </div>
        <button 
          @click="showModal = true"
          class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-md flex items-center gap-2 shrink-0"
        >
          <Plus class="w-4 h-4" />
          <span>New Purchase Order</span>
        </button>
      </div>

      <!-- Purchases List -->
      <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 font-semibold uppercase tracking-wider">
                <th class="py-3.5 px-4">PO Reference</th>
                <th class="py-3.5 px-4">Destination Store</th>
                <th class="py-3.5 px-4">Supplier</th>
                <th class="py-3.5 px-4">Status</th>
                <th class="py-3.5 px-4 text-right">Total Cost</th>
                <th class="py-3.5 px-4 text-right">Paid Amount</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr v-for="po in purchases.data" :key="po.id" class="hover:bg-slate-50 transition-colors">
                <td class="py-3.5 px-4 font-mono font-bold text-indigo-700">{{ po.purchase_no }}</td>
                <td class="py-3.5 px-4 font-semibold text-slate-900">{{ po.store?.name }}</td>
                <td class="py-3.5 px-4 text-slate-700">{{ po.supplier?.company_name }}</td>
                <td class="py-3.5 px-4">
                  <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                    {{ po.status }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-right font-bold text-slate-900">৳{{ parseFloat(po.total_amount).toLocaleString() }}</td>
                <td class="py-3.5 px-4 text-right font-bold text-emerald-700">৳{{ parseFloat(po.paid_amount).toLocaleString() }}</td>
              </tr>
              <tr v-if="purchases.data?.length === 0">
                <td colspan="6" class="py-8 text-center text-slate-500 font-medium">No purchase orders recorded yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add PO Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div class="bg-white border border-slate-200 rounded-3xl p-6 w-full max-w-2xl shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between pb-3 border-b border-slate-200">
            <h3 class="text-lg font-bold text-slate-900">Create Purchase Order</h3>
            <button @click="showModal = false" class="text-slate-400 hover:text-slate-700">&times;</button>
          </div>

          <form @submit.prevent="submitForm" class="space-y-4 text-xs">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-slate-700 font-semibold mb-1">Destination Store Outlet</label>
                <select v-model="form.store_id" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900">
                  <option value="" disabled>Select Outlet</option>
                  <option v-for="st in stores" :key="st.id" :value="st.id">{{ st.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-slate-700 font-semibold mb-1">Supplier Company</label>
                <select v-model="form.supplier_id" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900">
                  <option value="" disabled>Select Supplier</option>
                  <option v-for="sup in suppliers" :key="sup.id" :value="sup.id">{{ sup.company_name }}</option>
                </select>
              </div>
            </div>

            <!-- Items list -->
            <div>
              <label class="block text-slate-700 font-semibold mb-2">Purchase Line Items</label>
              <div v-for="(item, index) in form.items" :key="index" class="grid grid-cols-12 gap-2 mb-2 items-center">
                <div class="col-span-5">
                  <select v-model="item.product_id" required class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs">
                    <option value="" disabled>Select Product</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} (Cost: ৳{{ p.purchase_cost }})</option>
                  </select>
                </div>
                <div class="col-span-3">
                  <input v-model="item.quantity" type="number" step="1" min="1" placeholder="Qty" class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs" />
                </div>
                <div class="col-span-3">
                  <input v-model="item.unit_cost" type="number" step="0.01" placeholder="Unit Cost ৳" class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-xs" />
                </div>
                <div class="col-span-1 text-right">
                  <button type="button" @click="removeItem(index)" class="text-rose-600 hover:text-rose-800 font-bold">&times;</button>
                </div>
              </div>
              <button type="button" @click="addItem" class="text-xs text-indigo-700 hover:text-indigo-900 font-semibold flex items-center gap-1 mt-2">
                + Add Item Line
              </button>
            </div>

            <div class="grid grid-cols-2 gap-4 pt-2 border-t border-slate-200">
              <div>
                <label class="block text-slate-700 font-semibold mb-1">Paid Amount (৳)</label>
                <input v-model="form.paid_amount" type="number" step="0.01" min="0" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900" />
              </div>
              <div>
                <label class="block text-slate-700 font-semibold mb-1">Notes / Internal Ref</label>
                <input v-model="form.notes" type="text" placeholder="e.g. Batch Shipment #104" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-900" />
              </div>
            </div>

            <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
              <button type="button" @click="showModal = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold">Cancel</button>
              <button type="submit" :disabled="form.processing" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-md">Submit Purchase Order</button>
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
import { ShoppingBag, Plus } from 'lucide-vue-next';

const props = defineProps({
  purchases: Object,
  stores: Array,
  suppliers: Array,
  products: Array,
});

const showModal = ref(false);
const form = useForm({
  store_id: props.stores?.[0]?.id || '',
  supplier_id: props.suppliers?.[0]?.id || '',
  paid_amount: 0,
  notes: '',
  items: [
    { product_id: props.products?.[0]?.id || '', quantity: 10, unit_cost: props.products?.[0]?.purchase_cost || 100 }
  ]
});

const addItem = () => {
  form.items.push({ product_id: props.products?.[0]?.id || '', quantity: 1, unit_cost: 0 });
};

const removeItem = (index) => {
  if (form.items.length > 1) {
    form.items.splice(index, 1);
  }
};

const submitForm = () => {
  form.post('/merchant/purchases', {
    onSuccess: () => {
      showModal.value = false;
      form.reset();
    }
  });
};
</script>
