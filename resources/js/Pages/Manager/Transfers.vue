<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-900 min-h-screen text-slate-100 selection:bg-cyan-500 selection:text-white">
      <!-- Dark Hero Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-cyan-950 to-slate-900 border border-cyan-500/20 p-6 sm:p-8 shadow-2xl">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 font-mono text-xs font-bold uppercase tracking-wider mb-3">
              <ArrowLeftRight class="w-4 h-4" />
              <span>Store Manager Inventory Hub</span>
            </div>
            <h1 class="text-3xl font-black font-heading tracking-tight text-white">Inter-Outlet Stock Transfers</h1>
            <p class="text-slate-400 text-xs sm:text-sm mt-1 max-w-2xl">
              Transfer product inventories safely between store outlets with automated stock movement reconciliation.
            </p>
          </div>
          <button 
            @click="showModal = true"
            class="px-5 py-3 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-slate-950 font-black text-xs shadow-lg shadow-cyan-500/20 flex items-center gap-2 shrink-0 transition-all hover:scale-105 active:scale-95"
          >
            <Plus class="w-4 h-4 text-slate-950 stroke-[3]" />
            <span>New Stock Transfer Request</span>
          </button>
        </div>
      </div>

      <!-- Transfers List Card -->
      <div class="bg-slate-800/90 border border-slate-700/80 rounded-3xl p-6 shadow-2xl space-y-4 backdrop-blur-md">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-700/60">
          <div class="relative w-full sm:w-80">
            <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input 
              v-model="searchQuery" 
              type="text" 
              placeholder="Search transfer ref, source, or target outlet..." 
              class="w-full pl-10 pr-4 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs text-slate-100 placeholder-slate-400 focus:outline-none focus:border-cyan-500"
            />
          </div>
          <span class="text-xs text-slate-400 font-mono">Transfer Requests: {{ transfersList.length }}</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="text-slate-400 border-b border-slate-700/60 uppercase text-[10px] bg-slate-900/60 font-semibold tracking-wider">
                <th class="py-3.5 px-4">Transfer Ref</th>
                <th class="py-3.5 px-4">Source Outlet (From)</th>
                <th class="py-3.5 px-4">Target Outlet (To)</th>
                <th class="py-3.5 px-4">Transferred Line Items</th>
                <th class="py-3.5 px-4">Status</th>
                <th class="py-3.5 px-4 text-right">Date Executed</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/60">
              <tr v-for="tr in filteredTransfers" :key="tr.id" class="hover:bg-slate-700/30 transition-colors">
                <td class="py-4 px-4 font-mono font-bold text-cyan-400">{{ tr.transfer_no }}</td>
                <td class="py-4 px-4 font-bold text-white text-sm font-heading">{{ tr.from_store?.name || 'Main Outlet' }}</td>
                <td class="py-4 px-4 font-bold text-indigo-400 text-sm font-heading">{{ tr.to_store?.name || 'Branch Outlet' }}</td>
                <td class="py-4 px-4 text-slate-300">
                  <span v-for="item in tr.items" :key="item.id" class="inline-block bg-slate-900 border border-slate-700 px-2.5 py-1 rounded-xl text-[11px] mr-1.5 mb-1 text-slate-200 font-mono">
                    {{ item.product?.name }}: <strong class="text-cyan-400">{{ item.quantity }} pcs</strong>
                  </span>
                </td>
                <td class="py-4 px-4">
                  <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                    {{ tr.status || 'COMPLETED' }}
                  </span>
                </td>
                <td class="py-4 px-4 text-right text-slate-400 font-mono">{{ new Date(tr.created_at).toLocaleDateString() }}</td>
              </tr>
              <tr v-if="filteredTransfers.length === 0">
                <td colspan="6" class="py-12 text-center text-slate-400 font-medium">No stock transfer requests recorded yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add Transfer Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-md p-4">
        <div class="bg-slate-900 border border-slate-700 rounded-3xl p-6 w-full max-w-xl shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <h3 class="text-lg font-black text-white font-heading flex items-center gap-2">
              <ArrowLeftRight class="w-5 h-5 text-cyan-400" />
              <span>Create Inter-Outlet Stock Transfer</span>
            </h3>
            <button @click="showModal = false" class="text-slate-400 hover:text-white text-xl font-bold">&times;</button>
          </div>

          <form @submit.prevent="submitForm" class="space-y-4 text-xs">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-slate-300 font-semibold mb-1">Source Store (From)</label>
                <select v-model="form.from_store_id" required class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-slate-100 focus:outline-none focus:border-cyan-500">
                  <option value="" disabled>Select Source Store</option>
                  <option v-for="st in stores" :key="st.id" :value="st.id">{{ st.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-slate-300 font-semibold mb-1">Target Store (To)</label>
                <select v-model="form.to_store_id" required class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-slate-100 focus:outline-none focus:border-cyan-500">
                  <option value="" disabled>Select Target Store</option>
                  <option v-for="st in stores" :key="st.id" :value="st.id">{{ st.name }}</option>
                </select>
              </div>
            </div>

            <!-- Items -->
            <div>
              <label class="block text-slate-200 font-bold mb-2">Transfer Products & Quantities</label>
              <div v-for="(item, index) in form.items" :key="index" class="grid grid-cols-12 gap-2 mb-2 items-center bg-slate-800/60 p-2.5 rounded-xl border border-slate-700">
                <div class="col-span-7">
                  <select v-model="item.product_id" required class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-xl text-slate-100 text-xs focus:outline-none focus:border-cyan-500">
                    <option value="" disabled>Select Product</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} (SKU: {{ p.sku }})</option>
                  </select>
                </div>
                <div class="col-span-4">
                  <input v-model="item.quantity" type="number" step="1" min="1" placeholder="Quantity" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-xl text-slate-100 text-xs focus:outline-none focus:border-cyan-500 font-mono" />
                </div>
                <div class="col-span-1 text-right">
                  <button type="button" @click="removeItem(index)" class="text-rose-400 hover:text-rose-300 font-bold text-lg">&times;</button>
                </div>
              </div>
              <button type="button" @click="addItem" class="text-xs text-cyan-400 hover:text-cyan-300 font-bold flex items-center gap-1 mt-2">
                + Add Transfer Product
              </button>
            </div>

            <div>
              <label class="block text-slate-300 font-semibold mb-1">Transfer Notes</label>
              <textarea v-model="form.notes" rows="2" placeholder="e.g. Stock replenishment for weekend campaign" class="w-full px-3 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-slate-100 focus:outline-none focus:border-cyan-500"></textarea>
            </div>

            <div class="pt-4 border-t border-slate-800 flex justify-end gap-3">
              <button type="button" @click="showModal = false" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl font-semibold">Cancel</button>
              <button type="submit" :disabled="form.processing" class="px-5 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-slate-950 font-black rounded-xl shadow-lg shadow-cyan-500/20">Execute Stock Transfer</button>
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
import { ArrowLeftRight, Plus, Search } from 'lucide-vue-next';

const props = defineProps({
  transfers: Object,
  stores: { type: Array, default: () => [] },
  products: { type: Array, default: () => [] },
});

const searchQuery = ref('');
const showModal = ref(false);

const transfersList = computed(() => props.transfers?.data || props.transfers || []);

const filteredTransfers = computed(() => {
  if (!searchQuery.value.trim()) return transfersList.value;
  const q = searchQuery.value.toLowerCase().trim();
  return transfersList.value.filter(tr => 
    (tr.transfer_no && tr.transfer_no.toLowerCase().includes(q)) ||
    (tr.from_store?.name && tr.from_store.name.toLowerCase().includes(q)) ||
    (tr.to_store?.name && tr.to_store.name.toLowerCase().includes(q))
  );
});

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
