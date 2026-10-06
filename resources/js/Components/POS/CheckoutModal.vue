<template>
  <div v-if="show" @keydown.enter.prevent="submitCheckout" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-2xl w-full overflow-hidden shadow-2xl flex flex-col max-h-[90vh] text-slate-900 dark:text-slate-100">
      <!-- Modal Header -->
      <div class="p-5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50 dark:bg-slate-800/80">
        <div>
          <h2 class="text-xl font-extrabold font-heading text-slate-900 dark:text-white">Order Checkout & Payment</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400">Select payment method or split across multiple tenders</p>
        </div>
        <button @click="$emit('close')" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700">
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- Modal Body -->
      <div class="p-6 overflow-y-auto space-y-6 flex-1 bg-white dark:bg-slate-900">
        <!-- Summary Cards Row -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
          <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
            <div class="text-[10px] text-slate-500 dark:text-slate-400 uppercase font-bold">Subtotal</div>
            <div class="text-base font-bold text-slate-900 dark:text-white">৳{{ formatMoney(subtotal) }}</div>
          </div>
          <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
            <div class="text-[10px] text-rose-600 dark:text-rose-400 uppercase font-bold">Discount</div>
            <div class="text-base font-bold text-rose-600 dark:text-rose-400">-৳{{ formatMoney(discountAmount) }}</div>
          </div>
          <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
            <div class="text-[10px] text-emerald-700 dark:text-emerald-400 uppercase font-bold">Tax / VAT</div>
            <div class="text-base font-bold text-emerald-700 dark:text-emerald-400">+৳{{ formatMoney(taxAmount) }}</div>
          </div>
          <div class="p-3 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800">
            <div class="text-[10px] text-emerald-800 dark:text-emerald-300 uppercase font-extrabold">Grand Total</div>
            <div class="text-lg font-extrabold font-heading text-emerald-700 dark:text-emerald-400">৳{{ formatMoney(grandTotal) }}</div>
          </div>
        </div>

        <!-- Mode Toggle: Single vs Split Payment -->
        <div class="flex items-center justify-between p-2 rounded-2xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-xs font-bold">
          <span class="text-slate-700 dark:text-slate-300 px-2">Payment Split Tender Mode</span>
          <div class="flex items-center gap-1 bg-white dark:bg-slate-900 p-1 rounded-xl shadow-xs">
            <button 
              type="button" 
              @click="isSplitPayment = false"
              :class="['px-3 py-1 rounded-lg transition-all', !isSplitPayment ? 'bg-emerald-600 text-white font-extrabold' : 'text-slate-500 hover:text-slate-900 dark:hover:text-slate-100']"
            >
              Single Tender
            </button>
            <button 
              type="button" 
              @click="enableSplitMode"
              :class="['px-3 py-1 rounded-lg transition-all', isSplitPayment ? 'bg-indigo-600 text-white font-extrabold' : 'text-slate-500 hover:text-slate-900 dark:hover:text-slate-100']"
            >
              ⚡ Split Multiple Tenders
            </button>
          </div>
        </div>

        <!-- Single Payment Mode View -->
        <div v-if="!isSplitPayment" class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wider">Select Payment Method</label>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
              <button 
                type="button" 
                @click="selectedMethod = 'cash'; paidAmount = grandTotal"
                :class="['p-3 rounded-xl border flex flex-col items-center gap-1.5 transition-all text-xs font-bold', selectedMethod === 'cash' ? 'bg-emerald-600 border-emerald-700 text-white shadow-md' : 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700']"
              >
                <Banknote class="w-5 h-5" />
                <span>Cash</span>
              </button>
              <button 
                type="button" 
                @click="selectedMethod = 'card'; paidAmount = grandTotal"
                :class="['p-3 rounded-xl border flex flex-col items-center gap-1.5 transition-all text-xs font-bold', selectedMethod === 'card' ? 'bg-emerald-600 border-emerald-700 text-white shadow-md' : 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700']"
              >
                <CreditCard class="w-5 h-5" />
                <span>Card</span>
              </button>
              <button 
                type="button" 
                @click="selectedMethod = 'mobile_wallet'; paidAmount = grandTotal"
                :class="['p-3 rounded-xl border flex flex-col items-center gap-1.5 transition-all text-xs font-bold', selectedMethod === 'mobile_wallet' ? 'bg-emerald-600 border-emerald-700 text-white shadow-md' : 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700']"
              >
                <QrCode class="w-5 h-5" />
                <span>bKash / Nagad</span>
              </button>
              <button 
                type="button" 
                :disabled="isGuestCustomer"
                @click="!isGuestCustomer && (selectedMethod = 'credit', paidAmount = 0)"
                :title="isGuestCustomer ? 'Select a registered customer (F2) to enable Customer Credit' : 'Customer Credit Ledger'"
                :class="['p-3 rounded-xl border flex flex-col items-center gap-1.5 transition-all text-xs font-bold relative', isGuestCustomer ? 'opacity-40 cursor-not-allowed bg-slate-100 dark:bg-slate-800 text-slate-400 border-slate-300 dark:border-slate-700' : (selectedMethod === 'credit' ? 'bg-emerald-600 border-emerald-700 text-white shadow-md' : 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700')]"
              >
                <UserCheck class="w-5 h-5" />
                <span>Customer Credit</span>
                <span v-if="isGuestCustomer" class="text-[9px] text-amber-500 font-extrabold font-mono">Reg. Required (F2)</span>
              </button>
            </div>
          </div>

          <!-- Single Tender Paid Amount & Change Calculator -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Amount Tendered / Paid (৳)</label>
              <input 
                type="number" 
                step="0.01" 
                v-model.number="paidAmount" 
                class="w-full px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono text-lg font-bold focus:outline-none focus:border-emerald-600"
              />
              <div class="flex gap-1.5 mt-2 overflow-x-auto pb-1">
                <button 
                  v-for="bill in [grandTotal, 100, 500, 1000, 2000, 5000]" 
                  :key="bill" 
                  type="button"
                  @click="paidAmount = bill"
                  class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-[11px] font-bold text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 shrink-0"
                >
                  ৳{{ bill === grandTotal ? 'Exact' : bill }}
                </button>
              </div>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Change Due / Return (৳)</label>
              <div :class="['w-full px-4 py-3 rounded-xl font-mono text-lg font-bold border flex items-center justify-between', changeReturn >= 0 ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300' : 'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300']">
                <span>৳{{ formatMoney(Math.abs(changeReturn)) }}</span>
                <span class="text-xs font-sans uppercase font-bold">
                  {{ changeReturn >= 0 ? 'Change to Return' : 'Due Balance' }}
                </span>
              </div>
            </div>
          </div>

          <!-- MFS (bKash / Nagad / Rocket) Dynamic QR Code & TrxID Verification -->
          <div v-if="selectedMethod === 'mobile_wallet'" class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-extrabold uppercase tracking-wider text-pink-600 dark:text-pink-400 flex items-center gap-1.5">
                <QrCode class="w-4 h-4" /> bKash / Nagad / Rocket Dynamic Merchant QR
              </span>
              <span class="text-[10px] font-mono font-bold bg-pink-500/10 text-pink-600 dark:text-pink-400 px-2 py-0.5 rounded-full border border-pink-500/20">
                Scan & Pay ৳{{ formatMoney(grandTotal) }}
              </span>
            </div>

            <div class="flex flex-col sm:flex-row items-center gap-4 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800">
              <div class="w-28 h-28 bg-white p-2 rounded-xl border border-slate-300 flex flex-col items-center justify-center shadow-xs shrink-0">
                <img :src="`https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=bKash-Merchant-01700000000-Amt-${grandTotal}`" alt="MFS QR Code" class="w-24 h-24" />
              </div>
              <div class="space-y-2 text-xs flex-1">
                <div class="font-bold text-slate-800 dark:text-slate-200">Merchant bKash/Nagad Number: <span class="font-mono text-emerald-600 dark:text-emerald-400 font-extrabold">+8801700000000</span></div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Ask customer to scan QR code or send ৳{{ formatMoney(grandTotal) }} and enter TrxID below.</p>
                <div>
                  <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">bKash/Nagad TrxID (Transaction ID)</label>
                  <input 
                    type="text" 
                    v-model="trxIdInput"
                    placeholder="e.g. 9J87K6L5M"
                    class="w-full px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-xs font-mono font-extrabold uppercase text-slate-900 dark:text-slate-100 focus:outline-none focus:border-pink-500"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Bank Card POS Terminal Sync & Semi-Integrated Fallback Modal -->
          <div v-if="selectedMethod === 'card'" class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-extrabold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 flex items-center gap-1.5">
                <CreditCard class="w-4 h-4" /> Bank POS Card Terminal Sync
              </span>
              <button 
                type="button" 
                @click="simulatePosTerminalSync"
                :disabled="isPushingToTerminal"
                class="px-3 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-[11px] shadow-xs flex items-center gap-1 transition-all"
              >
                <span>{{ isPushingToTerminal ? 'Pushing ৳' + grandTotal + '...' : '⚡ Push ৳' + grandTotal + ' to Terminal' }}</span>
              </button>
            </div>

            <div class="grid grid-cols-3 gap-2">
              <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Card Type</label>
                <select v-model="cardType" class="w-full px-2 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold">
                  <option value="visa">VISA</option>
                  <option value="mastercard">MasterCard</option>
                  <option value="amex">AMEX</option>
                  <option value="unionpay">UnionPay</option>
                </select>
              </div>
              <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Auth Code</label>
                <input type="text" v-model="cardAuthCode" placeholder="AUTH-9821" class="w-full px-2 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-mono font-bold" />
              </div>
              <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Last 4 Digits</label>
                <input type="text" maxlength="4" v-model="cardLast4" placeholder="4321" class="w-full px-2 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-mono font-bold" />
              </div>
            </div>
          </div>
        </div>

        <!-- Split Payment Lines Mode View -->
        <div v-else class="space-y-4">
          <div class="flex items-center justify-between">
            <span class="text-xs font-extrabold uppercase tracking-wider text-slate-700 dark:text-slate-300">Split Payment Lines</span>
            <button 
              type="button" 
              @click="addPaymentLine" 
              class="px-3 py-1 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs flex items-center gap-1 shadow-sm"
            >
              + Add Tender Line
            </button>
          </div>

          <div class="space-y-2.5">
            <div 
              v-for="(pLine, idx) in paymentLines" 
              :key="idx" 
              class="flex items-center gap-2 p-3 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700"
            >
              <select 
                v-model="pLine.method" 
                class="px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-900 dark:text-white"
              >
                <option value="cash">Cash</option>
                <option value="card">Card</option>
                <option value="mobile_wallet">bKash / Nagad</option>
                <option value="credit">Customer Credit</option>
              </select>

              <input 
                type="number" 
                step="0.01" 
                v-model.number="pLine.amount" 
                placeholder="Amount (৳)" 
                class="flex-1 px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold text-slate-900 dark:text-white focus:outline-none"
              />

              <input 
                type="text" 
                v-model="pLine.reference_no" 
                placeholder="TrxID / Card #" 
                class="w-32 px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-mono text-slate-900 dark:text-white focus:outline-none"
              />

              <button 
                v-if="paymentLines.length > 1" 
                @click="removePaymentLine(idx)" 
                type="button" 
                class="p-2 rounded-xl text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors"
              >
                <X class="w-4 h-4" />
              </button>
            </div>
          </div>

          <div class="p-3 rounded-2xl bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-800 text-xs flex justify-between font-bold">
            <span class="text-indigo-900 dark:text-indigo-200">Total Tendered Across Lines:</span>
            <span class="font-mono text-indigo-700 dark:text-indigo-400">৳{{ formatMoney(totalSplitTendered) }} / ৳{{ formatMoney(grandTotal) }}</span>
          </div>
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Transaction Notes (Optional)</label>
          <input 
            type="text" 
            v-model="notes" 
            placeholder="e.g. Serial # checked / Card transaction ID" 
            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-emerald-600"
          />
        </div>
      </div>

      <!-- Action Footer -->
      <div class="p-5 border-t border-slate-200 dark:border-slate-800 flex gap-3 bg-slate-50 dark:bg-slate-800/80">
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
          class="px-6 py-3.5 rounded-2xl bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-bold text-sm"
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
  presetTender: { type: Number, default: 0 },
  isSubmitting: Boolean,
  isGuestCustomer: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'confirm']);

const selectedMethod = ref('cash');
const paidAmount = ref(0);
const notes = ref('');
const isSplitPayment = ref(false);
const trxIdInput = ref('');
const cardType = ref('visa');
const cardAuthCode = ref('');
const cardLast4 = ref('');
const isPushingToTerminal = ref(false);

const simulatePosTerminalSync = () => {
  isPushingToTerminal.value = true;
  setTimeout(() => {
    isPushingToTerminal.value = false;
    cardAuthCode.value = 'AUTH-' + Math.floor(1000 + Math.random() * 9000);
    cardLast4.value = String(Math.floor(1000 + Math.random() * 9000));
  }, 1200);
};

const paymentLines = ref([
  { method: 'cash', amount: 0, reference_no: '' }
]);

watch(() => [props.grandTotal, props.presetTender, props.show], () => {
  if (props.show) {
    const targetAmt = props.presetTender > 0 ? props.presetTender : (props.grandTotal || 0);
    paidAmount.value = targetAmt;
    if (paymentLines.value.length === 1) {
      paymentLines.value[0].amount = targetAmt;
    }
  }
}, { immediate: true });

const enableSplitMode = () => {
  isSplitPayment.value = true;
  if (paymentLines.value.length === 1) {
    paymentLines.value = [
      { method: 'cash', amount: Math.round(props.grandTotal / 2), reference_no: '' },
      { method: 'card', amount: Math.round(props.grandTotal / 2), reference_no: '' }
    ];
  }
};

const addPaymentLine = () => {
  paymentLines.value.push({ method: 'mobile_wallet', amount: 0, reference_no: '' });
};

const removePaymentLine = (index) => {
  if (paymentLines.value.length > 1) {
    paymentLines.value.splice(index, 1);
  }
};

const totalSplitTendered = computed(() => {
  return paymentLines.value.reduce((sum, line) => sum + (parseFloat(line.amount) || 0), 0);
});

const changeReturn = computed(() => {
  const tender = isSplitPayment.value ? totalSplitTendered.value : paidAmount.value;
  return tender - props.grandTotal;
});

const formatMoney = (val) => {
  return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const submitCheckout = () => {
  const finalPaid = isSplitPayment.value ? totalSplitTendered.value : paidAmount.value;
  const finalMethod = isSplitPayment.value ? 'split' : selectedMethod.value;

  let refNo = null;
  if (selectedMethod.value === 'mobile_wallet') {
    refNo = trxIdInput.value.trim();
  } else if (selectedMethod.value === 'card') {
    refNo = cardAuthCode.value ? `${cardType.value.toUpperCase()}-${cardAuthCode.value}` : null;
  }

  emit('confirm', {
    payment_method: finalMethod,
    paid_amount: finalPaid,
    change_return: changeReturn.value > 0 ? changeReturn.value : 0,
    payments: isSplitPayment.value ? paymentLines.value : [{ method: selectedMethod.value, amount: paidAmount.value, reference_no: refNo }],
    notes: notes.value,
  });
};
</script>
