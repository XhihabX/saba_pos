<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-3xl max-w-md w-full p-6 space-y-5 shadow-2xl">
      <!-- Header -->
      <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
        <div>
          <h3 class="font-bold text-lg font-heading text-slate-900 dark:text-slate-100">
            {{ isCloseMode ? 'Close Register Shift & Reconcile Cash' : 'Open Cashier Register Shift' }}
          </h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">
            {{ isCloseMode ? 'Count your cash drawer to close register shift' : 'Enter opening float cash balance' }}
          </p>
        </div>
        <button v-if="!isOpenRequired" @click="$emit('close')" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- OPEN SHIFT FORM -->
      <form v-if="!isCloseMode" @submit.prevent="submitOpenShift" class="space-y-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5 uppercase">Opening Float Cash Balance (৳)</label>
          <input 
            type="number" 
            step="0.01" 
            v-model.number="openCash" 
            required 
            class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 font-mono text-lg font-bold focus:outline-none focus:border-emerald-600 focus:bg-white dark:focus:bg-slate-800"
          />
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Opening Shift Notes (Optional)</label>
          <input 
            type="text" 
            v-model="notes" 
            placeholder="e.g. Counter float checked" 
            class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:border-emerald-600 focus:bg-white dark:focus:bg-slate-800"
          />
        </div>

        <button type="submit" class="w-full py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-sm shadow-md">
          Open Register Shift (Start Sales)
        </button>
      </form>

      <!-- CLOSE SHIFT FORM (Blind Cash Count) -->
      <form v-else @submit.prevent="submitCloseShift" class="space-y-4">
        <div class="p-3.5 rounded-2xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-200 leading-relaxed">
          <span class="font-bold">🔒 Blind Drawer Reconciliation:</span>
          Please count all physical currency notes and coins in the drawer and enter the total below. Expected balances and variances are audited securely on the manager Z-report.
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5 uppercase">Counted Physical Cash (৳)</label>
          <input 
            type="number" 
            step="0.01" 
            v-model.number="countedCash" 
            required 
            placeholder="0.00"
            class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 font-mono text-lg font-bold focus:outline-none focus:border-emerald-600 focus:bg-white dark:focus:bg-slate-800"
          />
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Closing Shift Notes (Optional)</label>
          <input 
            type="text" 
            v-model="notes" 
            placeholder="e.g. End of day till count completed" 
            class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:border-emerald-600 focus:bg-white dark:focus:bg-slate-800"
          />
        </div>

        <button type="submit" class="w-full py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-sm shadow-md">
          Submit Drawer Count & Close Shift
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';

const props = defineProps({
  show: Boolean,
  isCloseMode: Boolean,
  isOpenRequired: Boolean,
  activeShift: Object,
  currentStoreId: Number,
});

const emit = defineEmits(['close']);

const openCash = ref(1000.00);
const countedCash = ref(0);
const notes = ref('');

const expectedCash = computed(() => {
  const openFloat = parseFloat(props.activeShift?.opening_cash) || 0;
  const cashSales = parseFloat(props.activeShift?.total_cash_sales) || 0;
  return openFloat + cashSales;
});

const cashDifference = computed(() => {
  return countedCash.value - expectedCash.value;
});

const formatMoney = (val) => {
  return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const submitOpenShift = () => {
  router.post('/pos/shift/open', {
    store_id: props.currentStoreId || 1,
    opening_cash: openCash.value,
    notes: notes.value,
  }, {
    onSuccess: () => {
      emit('close');
    }
  });
};

const submitCloseShift = () => {
  router.post('/pos/shift/close', {
    shift_id: props.activeShift?.id,
    closing_cash_counted: countedCash.value,
    notes: notes.value,
  }, {
    onSuccess: () => {
      emit('close');
    }
  });
};
</script>
