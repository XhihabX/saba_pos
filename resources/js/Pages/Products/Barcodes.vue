<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-6 print:hidden">
      <!-- Title & Print Barcode Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl sm:text-3xl font-black font-heading text-slate-900">
            Barcode Label Printer Workstation
          </h1>
          <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">
            Select inventory items, label counts, sticker sizes, and print custom barcode labels
          </p>
        </div>

        <div class="flex items-center gap-3">
          <select v-model="gridColumns" class="px-3 py-2.5 rounded-xl bg-white border border-slate-300 text-xs font-bold text-slate-700 focus:outline-none focus:border-emerald-600">
            <option :value="2">2 Labels per Row (50mm Thermal)</option>
            <option :value="3">3 Labels per Row (38mm A4 Sheet)</option>
            <option :value="4">4 Labels per Row (Compact Sheet)</option>
          </select>
          <button 
            @click="printLabels" 
            class="px-6 py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-sm shadow-md flex items-center justify-center gap-2 transition-all active:scale-95 shrink-0"
          >
            <Printer class="w-4 h-4" />
            <span>Print Label Sheet 🖨️</span>
          </button>
        </div>
      </div>

      <!-- Selected Item Configuration Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Item Selection Panel (7 cols) -->
        <div class="lg:col-span-7 p-6 rounded-3xl bg-white border border-slate-200 shadow-xs space-y-4">
          <h3 class="font-bold text-base font-heading text-slate-900 flex items-center gap-2">
            <Barcode class="w-5 h-5 text-emerald-600" />
            <span>Select Product Inventory for Label Batch</span>
          </h3>

          <div class="space-y-3 max-h-[450px] overflow-y-auto pr-1">
            <div 
              v-for="product in products" 
              :key="product.id" 
              class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-between"
            >
              <div>
                <div class="font-bold text-xs text-slate-900 font-heading">{{ product.name }}</div>
                <div class="text-[10px] font-mono text-slate-500">SKU: {{ product.sku }} | Barcode: {{ product.barcode || 'N/A' }}</div>
              </div>

              <div class="flex items-center gap-3">
                <span class="font-bold text-xs text-emerald-700 font-mono">৳{{ formatMoney(product.selling_price) }}</span>
                <input 
                  type="number" 
                  min="0" 
                  max="100" 
                  v-model.number="labelCounts[product.id]" 
                  class="w-16 px-2.5 py-1.5 rounded-xl bg-white border border-slate-300 text-xs font-mono font-bold text-center text-slate-900 focus:outline-none focus:border-emerald-600"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Live Barcode Sheet Preview (5 cols) -->
        <div class="lg:col-span-5 p-6 rounded-3xl bg-white border border-slate-200 shadow-xs flex flex-col justify-between">
          <div>
            <h3 class="font-bold text-base font-heading text-slate-900 mb-4">
              Barcode Sticker Sheet Preview
            </h3>

            <div :class="['grid gap-2.5 p-4 bg-slate-50 border border-slate-200 rounded-2xl max-h-[420px] overflow-y-auto', gridColumns === 2 ? 'grid-cols-2' : (gridColumns === 3 ? 'grid-cols-3' : 'grid-cols-4')]">
              <template v-for="product in products" :key="'sticker-' + product.id">
                <div 
                  v-for="n in (labelCounts[product.id] || 0)" 
                  :key="'item-' + product.id + '-' + n" 
                  class="p-2 bg-white border border-slate-300 rounded-xl text-center space-y-1 shadow-xs font-sans"
                >
                  <div class="text-[9px] font-extrabold text-slate-900 truncate">{{ product.name }}</div>
                  <div class="flex justify-center my-0.5">
                    <BarcodeGenerator :value="product.barcode || product.sku" :height="25" :width="1.2" />
                  </div>
                  <div class="text-[8px] font-mono text-slate-600">{{ product.barcode || product.sku }}</div>
                  <div class="text-[9px] font-bold text-emerald-700 font-mono">৳{{ formatMoney(product.selling_price) }}</div>
                </div>
              </template>
            </div>
          </div>

          <div class="pt-4 border-t border-slate-200 text-center text-xs text-slate-500">
            Thermal Label Roll & Multi-up Sticker Sheet Compatible Layout
          </div>
        </div>
      </div>
    </div>

    <!-- PRINT ONLY CONTENT -->
    <div class="hidden print:block p-4">
      <div :class="['grid gap-3', gridColumns === 2 ? 'grid-cols-2' : (gridColumns === 3 ? 'grid-cols-3' : 'grid-cols-4')]">
        <template v-for="product in products" :key="'print-' + product.id">
          <div 
            v-for="n in (labelCounts[product.id] || 0)" 
            :key="'print-item-' + product.id + '-' + n" 
            class="p-3 border border-slate-900 rounded-lg text-center space-y-1 font-sans break-inside-avoid"
          >
            <div class="text-xs font-bold text-black truncate">{{ product.name }}</div>
            <div class="flex justify-center my-1">
              <BarcodeGenerator :value="product.barcode || product.sku" :height="32" :width="1.4" />
            </div>
            <div class="text-[10px] font-mono text-black">{{ product.barcode || product.sku }}</div>
            <div class="text-xs font-bold text-black font-mono">৳{{ formatMoney(product.selling_price) }}</div>
          </div>
        </template>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BarcodeGenerator from '@/Components/BarcodeGenerator.vue';
import { Printer, Barcode } from 'lucide-vue-next';

const props = defineProps({
  products: Array,
});

const gridColumns = ref(3);
const labelCounts = reactive({});

if (props.products) {
  props.products.forEach(p => {
    labelCounts[p.id] = 2;
  });
}

const formatMoney = (val) => {
  return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const printLabels = () => {
  window.print();
};
</script>
