<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-50 dark:bg-slate-950 min-h-screen text-slate-900 dark:text-slate-100 selection:bg-indigo-500 selection:text-white transition-colors">
      <!-- Dark Hero Banner (Fixed Anchor Element) -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 border border-indigo-500/20 p-6 sm:p-8 shadow-2xl text-white">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 font-mono text-xs font-bold uppercase tracking-wider mb-3">
              <ShoppingBag class="w-4 h-4" />
              <span>Merchant HQ Procurement</span>
            </div>
            <h1 class="text-3xl font-black font-heading tracking-tight text-white">Wholesale Purchase Orders (PO)</h1>
            <p class="text-slate-400 text-xs sm:text-sm mt-1 max-w-2xl">
              Record bulk product inventory inflows, supplier invoices, line-item unit costs, and branch outlet restocks.
            </p>
          </div>
          <button 
            @click="showModal = true"
            class="px-5 py-3 rounded-2xl bg-gradient-to-r from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 text-white font-extrabold text-xs shadow-lg shadow-indigo-500/20 flex items-center gap-2 shrink-0 transition-all hover:scale-105 active:scale-95"
          >
            <Plus class="w-4 h-4" />
            <span>New Purchase Order</span>
          </button>
        </div>
      </div>

      <!-- Purchases Table Card -->
      <div class="bg-white dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800/80 rounded-3xl p-6 shadow-xs dark:shadow-2xl space-y-4 backdrop-blur-md">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-100 dark:border-slate-800/60">
          <div class="relative w-full sm:w-80">
            <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input 
              v-model="searchQuery" 
              type="text" 
              placeholder="Search PO reference, store, or supplier..." 
              class="w-full pl-10 pr-4 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-indigo-500"
            />
          </div>
          <span class="text-xs text-slate-500 dark:text-slate-400 font-mono">Total PO Records: {{ purchasesData.length }}</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 uppercase text-[10px] bg-slate-50 dark:bg-slate-800/60 font-semibold tracking-wider">
                <th class="py-3.5 px-4">PO Reference</th>
                <th class="py-3.5 px-4">Destination Store</th>
                <th class="py-3.5 px-4">Supplier Vendor</th>
                <th class="py-3.5 px-4">Status</th>
                <th class="py-3.5 px-4 text-right">Total Invoice Cost</th>
                <th class="py-3.5 px-4 text-right">Paid Amount</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
              <tr v-for="po in filteredPurchases" :key="po.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                <td class="py-4 px-4 font-mono font-bold text-indigo-600 dark:text-indigo-400">{{ po.purchase_no }}</td>
                <td class="py-4 px-4 font-bold text-slate-900 dark:text-white text-sm font-heading">{{ po.store?.name || 'Central Store' }}</td>
                <td class="py-4 px-4 text-slate-700 dark:text-slate-300 font-medium">{{ po.supplier?.company_name || 'Vendor' }}</td>
                <td class="py-4 px-4">
                  <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30">
                    {{ po.status || 'Received' }}
                  </span>
                </td>
                <td class="py-4 px-4 text-right font-bold text-slate-900 dark:text-white font-mono text-sm">৳{{ parseFloat(po.total_amount).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</td>
                <td class="py-4 px-4 text-right font-bold text-emerald-600 dark:text-emerald-400 font-mono text-sm">৳{{ parseFloat(po.paid_amount).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</td>
              </tr>
              <tr v-if="filteredPurchases.length === 0">
                <td colspan="6" class="py-12 text-center text-slate-400 font-medium">No purchase orders recorded yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add PO Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-md p-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 w-full max-w-2xl shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto text-slate-900 dark:text-slate-100">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <h3 class="text-lg font-black text-slate-900 dark:text-white font-heading flex items-center gap-2">
              <ShoppingBag class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
              <span>Create Wholesale Purchase Order</span>
            </h3>
            <button @click="showModal = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-xl font-bold">&times;</button>
          </div>

          <form @submit.prevent="submitForm" class="space-y-4 text-xs">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">Destination Store Outlet</label>
                <select v-model="form.store_id" required class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500">
                  <option value="" disabled>Select Outlet</option>
                  <option v-for="st in stores" :key="st.id" :value="st.id">{{ st.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">Supplier Company</label>
                <select v-model="form.supplier_id" required class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500">
                  <option value="" disabled>Select Supplier</option>
                  <option v-for="sup in suppliers" :key="sup.id" :value="sup.id">{{ sup.company_name }}</option>
                </select>
              </div>
            </div>

            <!-- Line Items -->
            <div>
              <label class="block text-slate-800 dark:text-slate-200 font-bold mb-2">Purchase Line Items</label>
              <div v-for="(item, index) in form.items" :key="index" class="grid grid-cols-12 gap-2 mb-2 items-center bg-slate-50 dark:bg-slate-800/60 p-2.5 rounded-xl border border-slate-200 dark:border-slate-700">
                <div class="col-span-5">
                  <select v-model="item.product_id" required class="w-full px-2 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 text-xs focus:outline-none focus:border-indigo-500">
                    <option value="" disabled>Select Product</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} (Cost: ৳{{ p.purchase_cost }})</option>
                  </select>
                </div>
                <div class="col-span-3">
                  <input v-model="item.quantity" type="number" step="1" min="1" placeholder="Qty" class="w-full px-2 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 text-xs focus:outline-none focus:border-indigo-500 font-mono" />
                </div>
                <div class="col-span-3">
                  <input v-model="item.unit_cost" type="number" step="0.01" placeholder="Unit Cost ৳" class="w-full px-2 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 text-xs focus:outline-none focus:border-indigo-500 font-mono" />
                </div>
                <div class="col-span-1 text-right">
                  <button type="button" @click="removeItem(index)" class="text-rose-600 dark:text-rose-400 hover:text-rose-500 font-bold text-lg">&times;</button>
                </div>
              </div>
              <button type="button" @click="addItem" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-bold flex items-center gap-1 mt-2">
                + Add Item Line
              </button>
            </div>

            <div class="grid grid-cols-2 gap-4 pt-3 border-t border-slate-100 dark:border-slate-800">
              <div>
                <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">Paid Amount (৳)</label>
                <input v-model="form.paid_amount" type="number" step="0.01" min="0" required class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500 font-mono font-bold text-emerald-600 dark:text-emerald-400" />
              </div>
              <div>
                <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">Notes / Internal Ref</label>
                <input v-model="form.notes" type="text" placeholder="e.g. Batch Shipment #104" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
              </div>
            </div>

            <div class="pt-4 border-t border-slate-800 flex justify-end gap-3">
              <button type="button" @click="showModal = false" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl font-semibold">Cancel</button>
              <button type="submit" :disabled="form.processing" class="px-5 py-2.5 bg-gradient-to-r from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-500/20">Submit Purchase Order</button>
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
import { ShoppingBag, Plus, Search } from 'lucide-vue-next';

const props = defineProps({
  purchases: Object,
  stores: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
  products: { type: Array, default: () => [] },
});

const searchQuery = ref('');
const showModal = ref(false);

const purchasesData = computed(() => props.purchases?.data || props.purchases || []);

const filteredPurchases = computed(() => {
  if (!searchQuery.value.trim()) return purchasesData.value;
  const q = searchQuery.value.toLowerCase().trim();
  return purchasesData.value.filter(po => 
    (po.purchase_no && po.purchase_no.toLowerCase().includes(q)) ||
    (po.store?.name && po.store.name.toLowerCase().includes(q)) ||
    (po.supplier?.company_name && po.supplier.company_name.toLowerCase().includes(q))
  );
});

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
