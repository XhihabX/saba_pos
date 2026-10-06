<template>
  <div v-if="show && receipt" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
      <!-- Header Bar -->
      <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
        <div class="flex items-center gap-2">
          <Printer class="w-5 h-5 text-emerald-700" />
          <span class="font-bold text-sm text-slate-900 font-heading">Thermal Invoice Receipt</span>
        </div>
        <button @click="$emit('close')" class="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200">
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- Printable Receipt View -->
      <div class="p-6 overflow-y-auto flex-1 bg-white text-slate-900 font-mono text-xs shadow-inner" id="thermal-receipt">
        <div class="text-center mb-4 border-b border-dashed border-slate-400 pb-3">
          <div v-if="receipt?.is_offline" class="mb-2 py-1 px-2 bg-amber-100 border border-amber-400 rounded text-[10px] font-bold text-amber-900 uppercase">
            ⚡ Offline Counter Sale (Queued for Cloud Sync)
          </div>
          <img v-if="receipt?.store?.logo_url" :src="receipt.store.logo_url" alt="Store Logo" class="h-10 mx-auto mb-2 object-contain" />
          <h2 class="text-base font-extrabold uppercase tracking-wide">{{ receipt?.store?.name || receipt?.store_name || 'IOT POS Store' }}</h2>
          <p class="text-[11px] text-slate-600">{{ receipt?.store?.address || 'Main Branch' }}</p>
          <p class="text-[11px] text-slate-600 font-bold">BIN: {{ receipt?.store?.bin_number || receipt?.store?.vat_number || '123456789-0000' }} | MUSAK-6.3</p>
          <p class="text-[11px] text-slate-600">Tel: {{ receipt?.store?.phone || '+880 1700 000000' }}</p>
          <p class="text-[10px] text-slate-500 mt-1 font-bold">{{ receipt?.store?.receipt_header || 'NBR Statutory Tax Invoice / Receipt' }}</p>
        </div>

        <div class="mb-3 text-[11px] space-y-0.5 border-b border-dashed border-slate-400 pb-2">
          <div class="flex justify-between">
            <span>Invoice #:</span>
            <span class="font-bold">{{ receipt?.invoice_no }}</span>
          </div>
          <div class="flex justify-between">
            <span>Date & Time:</span>
            <span>{{ formatDate(receipt?.created_at) }}</span>
          </div>
          <div class="flex justify-between">
            <span>Customer:</span>
            <span class="font-semibold">{{ receipt?.customer?.name || 'Walk-in Customer' }}</span>
          </div>
          <div class="flex justify-between">
            <span>Cashier:</span>
            <span class="font-semibold">{{ receipt?.user?.name || receipt?.cashier_name || `Cashier #${receipt?.user_id || 1}` }}</span>
          </div>
        </div>

        <!-- Items Table -->
        <table class="w-full mb-3 text-left border-b border-dashed border-slate-400 pb-2">
          <thead>
            <tr class="border-b border-slate-300 text-[10px] uppercase">
              <th class="py-1">Item</th>
              <th class="py-1 text-center">Qty</th>
              <th class="py-1 text-right">Price</th>
              <th class="py-1 text-right">Total</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <tr v-for="item in (receipt?.items || [])" :key="item.id">
              <td class="py-1 pr-1">
                <div class="font-semibold">{{ item.product_name }}</div>
                <div v-if="item.serial_number" class="text-[9px] text-slate-500">S/N: {{ item.serial_number }}</div>
              </td>
              <td class="py-1 text-center font-bold">{{ item.quantity }}</td>
              <td class="py-1 text-right">৳{{ formatMoney(item.unit_price) }}</td>
              <td class="py-1 text-right font-bold">৳{{ formatMoney(item.total) }}</td>
            </tr>
          </tbody>
        </table>

        <!-- Totals -->
        <div class="space-y-1 text-xs border-b border-dashed border-slate-400 pb-3">
          <div class="flex justify-between">
            <span>Subtotal:</span>
            <span>৳{{ formatMoney(receipt?.subtotal) }}</span>
          </div>
          <div v-if="receipt?.discount_amount > 0" class="flex justify-between text-rose-700">
            <span>Discount:</span>
            <span>-৳{{ formatMoney(receipt?.discount_amount) }}</span>
          </div>
          <div class="flex justify-between">
            <span>VAT / Tax ({{ receipt?.store?.default_tax_rate || 5 }}%):</span>
            <span>৳{{ formatMoney(receipt?.tax_amount) }}</span>
          </div>
          <div class="flex justify-between text-sm font-extrabold border-t border-slate-900 pt-1 mt-1">
            <span>Grand Total:</span>
            <span>৳{{ formatMoney(receipt?.grand_total) }}</span>
          </div>
          <div class="flex justify-between font-semibold pt-1">
            <span>Paid ({{ receipt?.payment_method }}):</span>
            <span>৳{{ formatMoney(receipt?.paid_amount) }}</span>
          </div>
          <div v-if="receipt?.change_return > 0" class="flex justify-between text-emerald-700 font-bold">
            <span>Change Return:</span>
            <span>৳{{ formatMoney(receipt?.change_return) }}</span>
          </div>
        </div>

        <!-- Dynamic NBR QR Code & Receipt Footer Note -->
        <div class="text-center mt-4 space-y-2">
          <div class="flex justify-center">
            <img :src="`https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=BIN-${receipt.store?.bin_number || '123456789'}-INV-${receipt.invoice_no}-Amt-${receipt.grand_total}`" alt="NBR Statutory QR Code" class="w-20 h-20 border p-1 rounded bg-white" />
          </div>
          <p class="text-[10px] font-semibold">{{ receipt.store?.receipt_footer || 'Thank you for your visit!' }}</p>
          <div class="font-mono text-[9px] text-slate-400">NBR Musak 6.3 Compliant | Powered by IOT POS</div>
        </div>
      </div>

      <!-- Action Footer -->
      <div class="p-4 border-t border-slate-200 flex gap-3 bg-slate-50">
        <button 
          @click="printReceipt" 
          class="flex-1 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-md flex items-center justify-center gap-2 active:scale-95 transition-all"
        >
          <Printer class="w-4 h-4" />
          <span>Print Thermal Receipt</span>
        </button>

        <a 
          :href="`/pos/invoice/${receipt?.id || 1}/pdf`" 
          target="_blank" 
          class="px-3 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-md flex items-center justify-center gap-1 transition-all"
        >
          📄 View PDF
        </a>

        <a 
          :href="`/vat/mushak-6.3/${receipt?.id || 1}`" 
          target="_blank" 
          class="px-3 py-3 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-extrabold text-xs shadow-md flex items-center justify-center gap-1 transition-all"
        >
          🇧🇩 Mushak 6.3
        </a>

        <button 
          @click="$emit('close')" 
          class="px-5 py-3 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs"
        >
          Close
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Printer, X } from 'lucide-vue-next';

const props = defineProps({
  show: Boolean,
  receipt: Object,
});

defineEmits(['close']);

const formatMoney = (val) => {
  return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatDate = (dateStr) => {
  if (!dateStr) return new Date().toLocaleString();
  return new Date(dateStr).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
};

const printReceipt = () => {
  window.print();
};
</script>

<style scoped>
@media print {
  body * {
    visibility: hidden !important;
  }
  #thermal-receipt, #thermal-receipt * {
    visibility: visible !important;
  }
  #thermal-receipt {
    position: absolute !important;
    left: 0 !important;
    top: 0 !important;
    width: 80mm !important;
    padding: 2mm !important;
    font-size: 11px !important;
    background: white !important;
    color: black !important;
  }
}
</style>
