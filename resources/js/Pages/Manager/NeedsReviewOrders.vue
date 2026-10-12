<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-900 min-h-screen text-slate-100 selection:bg-amber-500 selection:text-white">
      <!-- Dark Hero Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-amber-950 to-slate-900 border border-amber-500/20 p-6 sm:p-8 shadow-2xl">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 font-mono text-xs font-bold uppercase tracking-wider mb-3">
              <AlertTriangle class="w-4 h-4" />
              <span>Offline Stock Deficit Audit</span>
            </div>
            <h1 class="text-3xl font-black font-heading tracking-tight text-white">Orders Requiring Review</h1>
            <p class="text-slate-400 text-xs sm:text-sm mt-1 max-w-2xl">
              Sales completed offline where server-side stock was reduced before synchronization. Inspect and resolve discrepancies.
            </p>
          </div>
          <div class="flex items-center gap-4">
            <div class="px-5 py-3 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-right">
              <div class="text-[11px] font-bold text-amber-400 uppercase tracking-wider">Unresolved Discrepancies</div>
              <div class="text-2xl font-black text-amber-300 font-mono">{{ unresolvedCount }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters & Orders Table -->
      <div class="bg-slate-800/90 border border-slate-700/80 rounded-3xl p-6 shadow-2xl space-y-4 backdrop-blur-md">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-700/60">
          <div class="flex items-center gap-2">
            <button 
              @click="setFilter('unresolved')"
              :class="activeFilter === 'unresolved' ? 'bg-amber-500 text-slate-900 font-bold' : 'bg-slate-700/60 text-slate-300 hover:bg-slate-700'"
              class="px-4 py-1.5 rounded-xl text-xs transition"
            >
              Unresolved ({{ unresolvedCount }})
            </button>
            <button 
              @click="setFilter('resolved')"
              :class="activeFilter === 'resolved' ? 'bg-amber-500 text-slate-900 font-bold' : 'bg-slate-700/60 text-slate-300 hover:bg-slate-700'"
              class="px-4 py-1.5 rounded-xl text-xs transition"
            >
              Resolved
            </button>
            <button 
              @click="setFilter('all')"
              :class="activeFilter === 'all' ? 'bg-amber-500 text-slate-900 font-bold' : 'bg-slate-700/60 text-slate-300 hover:bg-slate-700'"
              class="px-4 py-1.5 rounded-xl text-xs transition"
            >
              All Flagged
            </button>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="text-slate-400 border-b border-slate-700/60 uppercase text-[10px] bg-slate-900/60 font-semibold tracking-wider">
                <th class="py-3.5 px-4">Invoice No</th>
                <th class="py-3.5 px-4">Outlet</th>
                <th class="py-3.5 px-4">Cashier</th>
                <th class="py-3.5 px-4">Review Reason</th>
                <th class="py-3.5 px-4">Total</th>
                <th class="py-3.5 px-4">Client Date</th>
                <th class="py-3.5 px-4">Status</th>
                <th class="py-3.5 px-4 text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
              <tr v-if="!ordersList.length">
                <td colspan="8" class="text-center py-8 text-slate-400">
                  Zero orders requiring review under this filter.
                </td>
              </tr>
              <tr v-for="order in ordersList" :key="order.id" class="hover:bg-slate-700/30 transition">
                <td class="py-3.5 px-4 font-mono font-bold text-cyan-400">{{ order.invoice_no }}</td>
                <td class="py-3.5 px-4">{{ order.store?.name || 'Store #' + order.store_id }}</td>
                <td class="py-3.5 px-4">{{ order.user?.name || 'Staff #' + order.user_id }}</td>
                <td class="py-3.5 px-4 max-w-xs text-amber-300 truncate" :title="order.review_reason">
                  {{ order.review_reason }}
                </td>
                <td class="py-3.5 px-4 font-mono font-bold">৳{{ Number(order.grand_total).toFixed(2) }}</td>
                <td class="py-3.5 px-4 font-mono text-slate-400">{{ formatDate(order.created_at) }}</td>
                <td class="py-3.5 px-4">
                  <span 
                    v-if="order.resolved_at" 
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 border border-emerald-500/30 text-emerald-400"
                  >
                    Resolved ({{ formatDate(order.resolved_at) }})
                  </span>
                  <span 
                    v-else 
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 border border-amber-500/30 text-amber-400"
                  >
                    Needs Action
                  </span>
                </td>
                <td class="py-3.5 px-4 text-right">
                  <button 
                    v-if="!order.resolved_at"
                    @click="openResolveModal(order)"
                    class="px-3 py-1 rounded-lg text-xs font-bold bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-500/40 text-emerald-300 transition"
                  >
                    Resolve
                  </button>
                  <span v-else class="text-slate-500 text-[11px]">
                    by {{ order.resolver?.name || 'Manager' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Resolve Modal -->
      <div v-if="selectedOrder" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
        <div class="bg-slate-900 border border-slate-700 rounded-3xl p-6 max-w-md w-full shadow-2xl space-y-4">
          <h3 class="text-lg font-bold text-white flex items-center gap-2">
            <CheckCircle class="w-5 h-5 text-emerald-400" />
            Resolve Flagged Order
          </h3>
          <p class="text-xs text-slate-300">
            Resolving invoice <span class="font-mono text-cyan-400 font-bold">{{ selectedOrder.invoice_no }}</span> confirms that you have inspected the stock deficit and accepted the discrepancy into inventory adjustments.
          </p>
          <div class="space-y-2">
            <label class="text-xs font-semibold text-slate-400">Resolution Notes</label>
            <textarea 
              v-model="resolutionNotes"
              rows="3"
              class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-cyan-500"
              placeholder="e.g. Physical inventory verified; restock adjustment logged."
            ></textarea>
          </div>
          <div class="flex items-center justify-end gap-3 pt-2">
            <button 
              @click="selectedOrder = null"
              class="px-4 py-2 rounded-xl text-xs bg-slate-800 text-slate-300 hover:bg-slate-700 transition"
            >
              Cancel
            </button>
            <button 
              @click="submitResolve"
              :disabled="isResolving"
              class="px-5 py-2 rounded-xl text-xs font-bold bg-emerald-500 text-slate-950 hover:bg-emerald-400 transition"
            >
              Confirm & Resolve
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { AlertTriangle, CheckCircle } from 'lucide-vue-next';

const props = defineProps({
  orders: Object,
  unresolvedCount: Number,
  filters: Object,
});

const activeFilter = ref(props.filters?.status || 'unresolved');
const selectedOrder = ref(null);
const resolutionNotes = ref('');
const isResolving = ref(false);

const ordersList = computed(() => {
  if (Array.isArray(props.orders)) return props.orders;
  return props.orders?.data || [];
});

const setFilter = (status) => {
  activeFilter.value = status;
  router.get(route('manager.orders.needs-review'), { status }, { preserveState: true, replace: true });
};

const openResolveModal = (order) => {
  selectedOrder.value = order;
  resolutionNotes.value = 'Stock deficit verified and discrepancy accepted.';
};

const submitResolve = () => {
  if (!selectedOrder.value || isResolving.value) return;
  isResolving.value = true;

  router.post(route('manager.orders.resolve', { id: selectedOrder.value.id }), {
    notes: resolutionNotes.value,
  }, {
    onSuccess: () => {
      selectedOrder.value = null;
      isResolving.value = false;
    },
    onError: () => {
      isResolving.value = false;
    },
  });
};

const formatDate = (dateStr) => {
  if (!dateStr) return '—';
  try {
    const d = new Date(dateStr);
    return d.toLocaleString('en-GB', { dateStyle: 'short', timeStyle: 'short' });
  } catch {
    return dateStr;
  }
};
</script>
