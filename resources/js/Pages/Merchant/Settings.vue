<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-50 dark:bg-slate-950 min-h-screen text-slate-900 dark:text-slate-100 selection:bg-indigo-500 selection:text-white transition-colors duration-200">
      <!-- Dark Hero Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 border border-indigo-500/20 p-6 sm:p-8 shadow-2xl">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 font-mono text-xs font-bold uppercase tracking-wider mb-3">
              <Sliders class="w-4 h-4" />
              <span>SaaS Store Configuration</span>
            </div>
            <h1 class="text-3xl font-black font-heading tracking-tight text-white">Merchant General & POS Settings</h1>
            <p class="text-slate-400 text-xs sm:text-sm mt-1 max-w-2xl">
              Configure your store branding, default tax rate, currency symbol, invoice numbering format, and thermal receipt printouts across all cash registers.
            </p>
          </div>
        </div>
      </div>

      <!-- Settings Form Card -->
      <div class="bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl">
        <form @submit.prevent="submitForm" class="space-y-6 max-w-4xl">
          <!-- Profile Section -->
          <div>
            <h2 class="text-lg font-black font-heading text-slate-900 dark:text-white flex items-center gap-2 mb-4 pb-2 border-b border-slate-100 dark:border-slate-800">
              <Store class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
              <span>Business & Brand Profile</span>
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
              <div>
                <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">Company / Store Brand Name</label>
                <input v-model="form.name" required type="text" placeholder="e.g. Saba Super Shop" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
              </div>

              <div>
                <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">Support Phone Number</label>
                <input v-model="form.phone" type="text" placeholder="+880 1700-000000" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
              </div>

              <div class="md:col-span-2">
                <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">Support Email Address</label>
                <input v-model="form.email" type="email" placeholder="contact@saba.com" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
              </div>

              <div class="md:col-span-2">
                <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">Custom Brand Logo URL (For Receipts & Store Portal)</label>
                <div class="flex items-center gap-3">
                  <input v-model="form.logo_url" type="url" placeholder="https://example.com/logo.png" class="flex-1 px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
                  <div class="w-12 h-12 rounded-xl bg-slate-900 border border-slate-700 overflow-hidden flex items-center justify-center shrink-0">
                    <img :src="form.logo_url || '/images/logo.png'" alt="Logo Preview" class="w-full h-full object-cover" />
                  </div>
                </div>
                <span class="text-[10px] text-slate-500 mt-1 block">Paste an image URL (PNG, JPG, SVG). Defaults to platform logo (/images/logo.png) if left empty.</span>
              </div>
            </div>
          </div>

          <!-- Billing & Financial Section -->
          <div>
            <h2 class="text-lg font-black font-heading text-slate-900 dark:text-white flex items-center gap-2 mb-4 pb-2 border-b border-slate-100 dark:border-slate-800">
              <Receipt class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
              <span>Financials, Tax & Invoice Format</span>
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
              <div>
                <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">Currency Symbol</label>
                <select v-model="form.currency_symbol" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500">
                  <option value="৳">৳ (BDT - Bangladeshi Taka)</option>
                  <option value="$">$ (USD - US Dollar)</option>
                  <option value="€">€ (EUR - Euro)</option>
                  <option value="£">£ (GBP - British Pound)</option>
                  <option value="₹">₹ (INR - Indian Rupee)</option>
                  <option value="AED">AED (UAE Dirham)</option>
                </select>
              </div>

              <div>
                <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">Default Sales Tax Rate (%)</label>
                <input v-model.number="form.default_tax_rate" type="number" step="0.01" min="0" max="100" placeholder="5.00" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
              </div>

              <div>
                <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">Invoice Number Prefix</label>
                <input v-model="form.invoice_prefix" type="text" placeholder="INV-" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
          </div>

          <!-- Thermal Receipt Printing Customizer -->
          <div>
            <h2 class="text-lg font-black font-heading text-slate-900 dark:text-white flex items-center gap-2 mb-4 pb-2 border-b border-slate-100 dark:border-slate-800">
              <Printer class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
              <span>Thermal Receipt Header & Footer</span>
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
              <div>
                <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">Receipt Top Header Note</label>
                <textarea v-model="form.receipt_header" rows="3" placeholder="Welcome to Saba Super Store! Thank you for shopping with us." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"></textarea>
              </div>

              <div>
                <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">Receipt Bottom Footer Note</label>
                <textarea v-model="form.receipt_footer" rows="3" placeholder="Goods once sold are non-refundable. Please keep receipt for warranty." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"></textarea>
              </div>
            </div>
          </div>

          <!-- Submit Button -->
          <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
            <button type="submit" :disabled="form.processing" class="px-6 py-3 bg-gradient-to-r from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 text-white font-black rounded-xl shadow-lg shadow-indigo-500/25 flex items-center gap-2 transition-all">
              <Save class="w-4 h-4" />
              <span>{{ form.processing ? 'Saving Changes...' : 'Save Merchant Settings' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Sliders, Store, Receipt, Printer, Save } from 'lucide-vue-next';

const props = defineProps({
  tenant: { type: Object, default: () => ({}) },
});

const form = useForm({
  name: props.tenant?.name || '',
  email: props.tenant?.email || '',
  phone: props.tenant?.phone || '',
  logo_url: props.tenant?.logo_url || '',
  currency_symbol: props.tenant?.currency_symbol || '৳',
  default_tax_rate: props.tenant?.default_tax_rate ?? 5,
  invoice_prefix: props.tenant?.invoice_prefix || 'INV-',
  receipt_header: props.tenant?.receipt_header || 'Thank you for shopping with Saba POS!',
  receipt_footer: props.tenant?.receipt_footer || 'Please retain receipt for exchange within 7 days.',
});

const submitForm = () => {
  form.post(window.safeRoute ? window.safeRoute('merchant.settings.update') : '/merchant/settings');
};
</script>
