<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-8 bg-slate-50/70 min-h-screen">
      <!-- ⚙️ Hero Header Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-rose-950 to-slate-900 text-white p-6 sm:p-8 shadow-xl border border-slate-800">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-rose-500/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 space-y-2">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 font-bold text-xs uppercase tracking-wider">
            <Settings class="w-3.5 h-3.5" />
            <span>SaaS Master Control</span>
          </div>
          <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black font-heading tracking-tight text-white">
            Platform Master Configurations
          </h1>
          <p class="text-xs sm:text-sm text-slate-300 max-w-2xl">
            Configure bKash, Nagad, and Bank payment receive numbers, support hotlines, currency defaults, and system maintenance switches
          </p>
        </div>
      </div>

      <!-- 🛠️ Settings Form Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Manual Mobile Payment Configuration (6 cols) -->
        <div class="lg:col-span-6 p-6 sm:p-8 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-6">
          <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
            <div class="w-10 h-10 rounded-2xl bg-pink-50 text-pink-600 flex items-center justify-center font-bold">
              <Smartphone class="w-5 h-5" />
            </div>
            <div>
              <h3 class="font-black text-xl font-heading text-slate-900">Manual Payment Gateways</h3>
              <p class="text-xs text-slate-500">Merchant subscription receive accounts</p>
            </div>
          </div>

          <form @submit.prevent="saveSettings" class="space-y-4 text-xs">
            <div>
              <label class="block text-slate-700 font-bold mb-1">bKash Merchant / Personal Receive Number</label>
              <input type="text" v-model="form.bkash_number" placeholder="01711000111" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 font-mono text-slate-900 font-bold focus:outline-none focus:border-rose-500" />
              <p class="text-[10px] text-slate-500 mt-1">Displayed to merchants during step 4 manual bKash registration.</p>
            </div>

            <div>
              <label class="block text-slate-700 font-bold mb-1">Nagad Merchant / Personal Receive Number</label>
              <input type="text" v-model="form.nagad_number" placeholder="01811000222" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 font-mono text-slate-900 font-bold focus:outline-none focus:border-rose-500" />
            </div>

            <div>
              <label class="block text-slate-700 font-bold mb-1">Bank Transfer Wire Instructions</label>
              <textarea v-model="form.bank_details" rows="3" placeholder="Bank: DBBL Banani&#10;A/C: 101-120-99882" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 font-mono text-slate-900 focus:outline-none focus:border-rose-500"></textarea>
            </div>

            <div class="pt-2">
              <button type="submit" class="w-full py-3.5 rounded-2xl bg-gradient-to-r from-rose-600 to-rose-700 hover:from-rose-500 hover:to-rose-600 text-white font-extrabold text-xs shadow-md shadow-rose-600/20 flex items-center justify-center gap-2 cursor-pointer">
                <Save class="w-4 h-4" />
                <span>Save Payment Configurations</span>
              </button>
            </div>
          </form>
        </div>

        <!-- Platform General & Support Info (6 cols) -->
        <div class="lg:col-span-6 p-6 sm:p-8 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-6">
          <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
            <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
              <Globe class="w-5 h-5" />
            </div>
            <div>
              <h3 class="font-black text-xl font-heading text-slate-900">General Platform & Support Setup</h3>
              <p class="text-xs text-slate-500">System branding and hotline support</p>
            </div>
          </div>

          <form @submit.prevent="saveSettings" class="space-y-4 text-xs">
            <div>
              <label class="block text-slate-700 font-bold mb-1">SaaS Platform System Name</label>
              <input type="text" v-model="form.app_name" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-bold focus:outline-none focus:border-rose-500" />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-slate-700 font-bold mb-1">Support Phone Hotline</label>
                <input type="text" v-model="form.support_phone" placeholder="+880 1700 000000" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-mono focus:outline-none focus:border-rose-500" />
              </div>
              <div>
                <label class="block text-slate-700 font-bold mb-1">Support Email</label>
                <input type="email" v-model="form.support_email" placeholder="support@iotpos.com" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500" />
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

            <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 flex items-center justify-between">
              <div>
                <span class="font-extrabold text-amber-900 text-xs block">System Maintenance Mode</span>
                <span class="text-[10px] text-amber-700">Temporarily block non-admin logins during system upgrades</span>
              </div>
              <input type="checkbox" v-model="form.maintenance_mode" class="w-5 h-5 accent-rose-600 rounded cursor-pointer" />
            </div>

            <div class="pt-2">
              <button type="submit" class="w-full py-3.5 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs shadow-md flex items-center justify-center gap-2 cursor-pointer">
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
  app_name: props.settings?.app_name || 'IOT POS - International Office Technology',
  bkash_number: props.settings?.bkash_number || '01711000111',
  nagad_number: props.settings?.nagad_number || '01811000222',
  bank_details: props.settings?.bank_details || 'Bank: DBBL Banani\nA/C: 101-120-99882',
  support_phone: props.settings?.support_phone || '+880 1700 000000',
  support_email: props.settings?.support_email || 'support@iotpos.com',
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

