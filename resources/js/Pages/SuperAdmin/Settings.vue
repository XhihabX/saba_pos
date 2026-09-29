<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-100 min-h-screen">
      <!-- Title & Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div class="flex items-center gap-2 text-rose-600 font-bold text-xs uppercase tracking-wider">
            <Settings class="w-4 h-4" />
            <span>SaaS Platform Master Settings</span>
          </div>
          <h1 class="text-2xl font-black font-heading text-slate-900 mt-1">Platform Setup & Payment Configurations</h1>
          <p class="text-xs text-slate-500 mt-1">Configure merchant manual payment bKash/Nagad accounts, SaaS support hotline, currency defaults, and maintenance mode</p>
        </div>
      </div>

      <!-- Settings Form Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Manual Mobile Payment Configuration (6 cols) -->
        <div class="lg:col-span-6 p-6 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-4">
          <h3 class="font-bold text-base font-heading text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3">
            <Smartphone class="w-5 h-5 text-pink-600" />
            <span>Manual Payment Receive Numbers</span>
          </h3>

          <form @submit.prevent="saveSettings" class="space-y-4 text-xs">
            <div>
              <label class="block text-slate-700 font-bold mb-1">bKash Merchant / Personal Number</label>
              <input type="text" v-model="form.bkash_number" placeholder="01700000000" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 font-mono text-slate-900 font-bold focus:outline-none focus:border-rose-500" />
              <p class="text-[10px] text-slate-500 mt-1">Displayed to new merchants on step 4 of registration for manual bKash payment transfer.</p>
            </div>

            <div>
              <label class="block text-slate-700 font-bold mb-1">Nagad Merchant / Personal Number</label>
              <input type="text" v-model="form.nagad_number" placeholder="01800000000" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 font-mono text-slate-900 font-bold focus:outline-none focus:border-rose-500" />
            </div>

            <div>
              <label class="block text-slate-700 font-bold mb-1">Bank Transfer Details</label>
              <textarea v-model="form.bank_details" rows="3" placeholder="Bank: Dutch Bangla Bank Ltd&#10;A/C: 101-120-99882&#10;Branch: Banani, Dhaka" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 font-mono text-slate-900 focus:outline-none focus:border-rose-500"></textarea>
            </div>

            <div class="pt-2">
              <button type="submit" class="w-full py-3 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold shadow-md flex items-center justify-center gap-2">
                <Save class="w-4 h-4" />
                <span>Save Payment Configurations</span>
              </button>
            </div>
          </form>
        </div>

        <!-- Platform General & Support Info (6 cols) -->
        <div class="lg:col-span-6 p-6 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-4">
          <h3 class="font-bold text-base font-heading text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3">
            <Globe class="w-5 h-5 text-indigo-600" />
            <span>General Platform & Support Setup</span>
          </h3>

          <form @submit.prevent="saveSettings" class="space-y-4 text-xs">
            <div>
              <label class="block text-slate-700 font-bold mb-1">SaaS Platform Name</label>
              <input type="text" v-model="form.app_name" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-bold focus:outline-none focus:border-rose-500" />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-slate-700 font-bold mb-1">Support Phone Hotline</label>
                <input type="text" v-model="form.support_phone" placeholder="+880 1700 000000" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-mono focus:outline-none focus:border-rose-500" />
              </div>
              <div>
                <label class="block text-slate-700 font-bold mb-1">Support Email</label>
                <input type="email" v-model="form.support_email" placeholder="support@sabapos.com" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-slate-700 font-bold mb-1">Default Currency Symbol</label>
                <input type="text" v-model="form.currency_symbol" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-mono font-bold focus:outline-none focus:border-rose-500" />
              </div>
              <div>
                <label class="block text-slate-700 font-bold mb-1">Default VAT / Tax Rate (%)</label>
                <input type="number" step="0.01" v-model.number="form.default_tax_rate" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-mono font-bold focus:outline-none focus:border-rose-500" />
              </div>
            </div>

            <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 flex items-center justify-between">
              <div>
                <span class="font-extrabold text-amber-900 text-xs block">System Maintenance Mode</span>
                <span class="text-[10px] text-amber-700">Temporarily block non-admin logins for server maintenance</span>
              </div>
              <input type="checkbox" v-model="form.maintenance_mode" class="w-5 h-5 accent-rose-600 rounded cursor-pointer" />
            </div>

            <div class="pt-2">
              <button type="submit" class="w-full py-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-extrabold shadow-md flex items-center justify-center gap-2">
                <Save class="w-4 h-4" />
                <span>Save Platform Settings</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Settings, Smartphone, Globe, Save } from 'lucide-vue-next';

const props = defineProps({
  settings: Object,
});

const form = ref({
  app_name: props.settings?.app_name || 'Saba POS',
  bkash_number: props.settings?.bkash_number || '01711000111',
  nagad_number: props.settings?.nagad_number || '01811000222',
  bank_details: props.settings?.bank_details || 'Bank: DBBL Banani\nA/C: 101-120-99882',
  support_phone: props.settings?.support_phone || '+880 1700 000000',
  support_email: props.settings?.support_email || 'support@sabapos.com',
  currency_symbol: props.settings?.currency_symbol || '৳',
  default_tax_rate: props.settings?.default_tax_rate || 5.0,
  maintenance_mode: Boolean(props.settings?.maintenance_mode || false),
});

const saveSettings = () => {
  router.post('/super-admin/settings', form.value, {
    preserveScroll: true,
  });
};
</script>
