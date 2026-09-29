<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-2xl w-full overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
      <!-- Modal Header -->
      <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50">
        <div>
          <h2 class="text-xl font-extrabold font-heading text-slate-900">Order Checkout & Payment</h2>
          <p class="text-xs text-slate-500">Select payment method & complete transaction</p>
        </div>
        <button @click="$emit('close')" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-200">
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- Modal Body -->
      <div class="p-6 overflow-y-auto space-y-6 flex-1 bg-white">
        <!-- Summary Cards Row -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
          <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
            <div class="text-[10px] text-slate-500 uppercase font-bold">Subtotal</div>
            <div class="text-base font-bold text-slate-900">৳{{ formatMoney(subtotal) }}</div>
          </div>
          <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
            <div class="text-[10px] text-rose-600 uppercase font-bold">Discount</div>
            <div class="text-base font-bold text-rose-600">-৳{{ formatMoney(discountAmount) }}</div>
          </div>
          <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
            <div class="text-[10px] text-emerald-700 uppercase font-bold">Tax / VAT</div>
            <div class="text-base font-bold text-emerald-700">+৳{{ formatMoney(taxAmount) }}</div>
          </div>
          <div class="p-3 rounded-2xl bg-emerald-50 border border-emerald-200">
            <div class="text-[10px] text-emerald-800 uppercase font-extrabold">Grand Total</div>
            <div class="text-lg font-extrabold font-heading text-emerald-700">৳{{ formatMoney(grandTotal) }}</div>
          </div>
        </div>

        <!-- Payment Method Tabs -->
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-2 uppercase tracking-wider">Select Payment Method</label>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <button 
              type="button" 
              @click="selectedMethod = 'cash'; paidAmount = grandTotal"
              :class="['p-3 rounded-xl border flex flex-col items-center gap-1.5 transition-all text-xs font-bold', selectedMethod === 'cash' ? 'bg-emerald-600 border-emerald-700 text-white shadow-md' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100']"
            >
              <Banknote class="w-5 h-5" />
              <span>Cash (F2)</span>
            </button>
            <button 
              type="button" 
              @click="selectedMethod = 'card'; paidAmount = grandTotal"
              :class="['p-3 rounded-xl border flex flex-col items-center gap-1.5 transition-all text-xs font-bold', selectedMethod === 'card' ? 'bg-emerald-600 border-emerald-700 text-white shadow-md' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100']"
            >
              <CreditCard class="w-5 h-5" />
              <span>Card</span>
            </button>
            <button 
              type="button" 
              @click="selectedMethod = 'mobile_wallet'; paidAmount = grandTotal"
              :class="['p-3 rounded-xl border flex flex-col items-center gap-1.5 transition-all text-xs font-bold', selectedMethod === 'mobile_wallet' ? 'bg-emerald-600 border-emerald-700 text-white shadow-md' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100']"
            >
              <QrCode class="w-5 h-5" />
              <span>Bkash / QR</span>
            </button>
            <button 
              type="button" 
              @click="selectedMethod = 'credit'; paidAmount = 0"
              :class="['p-3 rounded-xl border flex flex-col items-center gap-1.5 transition-all text-xs font-bold', selectedMethod === 'credit' ? 'bg-emerald-600 border-emerald-700 text-white shadow-md' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100']"
            >
              <UserCheck class="w-5 h-5" />
              <span>Customer Credit</span>
            </button>
          </div>
        </div>

        <!-- Paid Amount & Change Calculator -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5">Amount Tendered / Paid (৳)</label>
            <input 
              type="number" 
              step="0.01" 
              v-model.number="paidAmount" 
              class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-mono text-lg font-bold focus:outline-none focus:border-emerald-600"
            />
            <!-- Quick Tender Buttons -->
            <div class="flex gap-1.5 mt-2">
              <button 
                v-for="bill in [grandTotal, 100, 500, 1000, 2000]" 
                :key="bill" 
                type="button"
                @click="paidAmount = bill"
                class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-[11px] font-bold text-slate-700 border border-slate-200"
              >
                ৳{{ bill === grandTotal ? 'Exact' : bill }}
              </button>
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5">Change Due / Return (৳)</label>
            <div :class="['w-full px-4 py-3 rounded-xl font-mono text-lg font-bold border flex items-center justify-between', changeReturn >= 0 ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-700']">
              <span>৳{{ formatMoney(Math.abs(changeReturn)) }}</span>
              <span class="text-xs font-sans uppercase font-bold">
                {{ changeReturn >= 0 ? 'Change to Return' : 'Due Balance' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Transaction Notes (Optional)</label>
          <input 
            type="text" 
            v-model="notes" 
            placeholder="e.g. Serial # checked / Card transaction ID" 
            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-900 focus:outline-none focus:border-emerald-600"
          />
        </div>
      </div>

      <!-- Action Footer -->
      <div class="p-5 border-t border-slate-200 flex gap-3 bg-slate-50">
        <button 
          @click="submitCheckout" 
          :disabled="isSubmitting"
          class="flex-1 py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-base shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2 active:scale-95 transition-all disabled:opacity-50"
        >
          <CheckCircle2 class="w-5 h-5" />
          <span>{{ isSubmitting ? 'Processing Payment...' : 'Complete & Print Receipt (Enter)' }}</span>
        </button>
        <button 
          @click="$emit('close')" 
          class="px-6 py-3.5 rounded-2xl bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-sm"
        >
          Cancel
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { X, Banknote, CreditCard, QrCode, UserCheck, CheckCircle2 } from 'lucide-vue-next';

const props = defineProps({
  show: Boolean,
  subtotal: Number,
  discountAmount: Number,
  taxAmount: Number,
  grandTotal: Number,
  isSubmitting: Boolean,
});

const emit = defineEmits(['close', 'confirm']);

const selectedMethod = ref('cash');
const paidAmount = ref(0);
const notes = ref('');

watch(() => props.grandTotal, (newVal) => {
  paidAmount.value = newVal;
}, { immediate: true });

const changeReturn = computed(() => {
  return paidAmount.value - props.grandTotal;
});

const formatMoney = (val) => {
  return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const submitCheckout = () => {
  emit('confirm', {
    payment_method: selectedMethod.value,
    paid_amount: paidAmount.value,
    change_return: changeReturn.value > 0 ? changeReturn.value : 0,
    notes: notes.value,
  });
};
</script>
