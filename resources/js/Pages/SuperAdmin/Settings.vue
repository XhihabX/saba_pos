<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-8 bg-slate-50 dark:bg-slate-950 min-h-screen text-slate-900 dark:text-slate-100 transition-colors duration-200">
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
            Configure bKash, Nagad, Rocket, and Bank payment receive accounts, support hotlines, currency defaults, and system maintenance switches
          </p>
        </div>
      </div>

      <!-- Flash Notification / Errors -->
      <div v-if="$page.props.errors && Object.keys($page.props.errors).length > 0" class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400 text-xs space-y-1">
        <div class="font-bold flex items-center gap-2">
          <AlertCircle class="w-4 h-4" />
          <span>Please correct the following errors:</span>
        </div>
        <ul class="list-disc list-inside space-y-0.5 pl-2">
          <li v-for="(err, field) in $page.props.errors" :key="field">{{ err }}</li>
        </ul>
      </div>

      <form @submit.prevent="saveSettings" class="space-y-8">
        <!-- 🛠️ Settings Form Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
          <!-- Manual Mobile Payment Configuration (6 cols) -->
          <div class="lg:col-span-6 p-6 sm:p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-6">
            <div class="flex items-center gap-3 border-b border-slate-100 dark:border-slate-800 pb-4">
              <div class="w-10 h-10 rounded-2xl bg-pink-50 dark:bg-pink-950/50 text-pink-600 dark:text-pink-400 flex items-center justify-center font-bold">
                <Smartphone class="w-5 h-5" />
              </div>
              <div>
                <h3 class="font-black text-xl font-heading text-slate-900 dark:text-white">Mobile Financial Services (MFS)</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Receive subscription payments via bKash, Nagad, or Rocket</p>
              </div>
            </div>

            <div class="space-y-4 text-xs">
              <div>
                <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">bKash Merchant / Personal Receive Number (11 digits)</label>
                <input 
                  type="text" 
                  v-model="form.bkash_number" 
                  placeholder="e.g. 01711000111" 
                  class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 font-mono text-slate-900 dark:text-white font-bold focus:outline-none focus:border-rose-500" 
                />
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1">Must be an 11-digit Bangladeshi mobile number starting with 013-019.</p>
              </div>

              <div>
                <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">Nagad Merchant / Personal Receive Number (11 digits)</label>
                <input 
                  type="text" 
                  v-model="form.nagad_number" 
                  placeholder="e.g. 01811000222" 
                  class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 font-mono text-slate-900 dark:text-white font-bold focus:outline-none focus:border-rose-500" 
                />
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1">Must be an 11-digit Bangladeshi mobile number starting with 013-019.</p>
              </div>

              <div>
                <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">Rocket Merchant / Personal Receive Number (12 digits with check digit)</label>
                <input 
                  type="text" 
                  v-model="form.rocket_number" 
                  placeholder="e.g. 017110001110" 
                  class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 font-mono text-slate-900 dark:text-white font-bold focus:outline-none focus:border-rose-500" 
                />
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1">11-digit mobile number + 1 check digit (12 digits total).</p>
              </div>
            </div>

            <!-- Structured Bank Transfer Configuration -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-4 text-xs">
              <div class="flex items-center gap-2 text-slate-900 dark:text-white font-black text-sm">
                <Landmark class="w-4 h-4 text-rose-500" />
                <span>Structured Bank Wire Details (Platform Receiving Account)</span>
              </div>
              <p class="text-[10px] text-slate-500 dark:text-slate-400">
                Displayed to merchants choosing Bank Transfer during registration or renewal.
              </p>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">Bank Name</label>
                  <input 
                    type="text" 
                    v-model="form.bank_name" 
                    placeholder="e.g. Dutch-Bangla Bank PLC" 
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-rose-500" 
                  />
                </div>
                <div>
                  <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">Account Holder / Name</label>
                  <input 
                    type="text" 
                    v-model="form.bank_account_name" 
                    placeholder="e.g. Saba Technologies Ltd" 
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-rose-500" 
                  />
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">Account Number</label>
                  <input 
                    type="text" 
                    v-model="form.bank_account_number" 
                    placeholder="e.g. 101.120.99882" 
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 font-mono text-slate-900 dark:text-white font-bold focus:outline-none focus:border-rose-500" 
                  />
                </div>
                <div>
                  <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">Branch Name</label>
                  <input 
                    type="text" 
                    v-model="form.bank_branch_name" 
                    placeholder="e.g. Banani Branch, Dhaka" 
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-rose-500" 
                  />
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">Routing Number</label>
                  <input 
                    type="text" 
                    v-model="form.bank_routing_number" 
                    placeholder="e.g. 090271829" 
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 font-mono text-slate-900 dark:text-white focus:outline-none focus:border-rose-500" 
                  />
                </div>
              </div>

              <div>
                <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">Special Deposit Instructions / Notes</label>
                <textarea 
                  v-model="form.bank_instructions" 
                  rows="2" 
                  placeholder="e.g. Please specify your business name or tenant code in the deposit reference." 
                  class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-rose-500"
                ></textarea>
              </div>
            </div>
          </div>

          <!-- Platform General & Support Info (6 cols) -->
          <div class="lg:col-span-6 p-6 sm:p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-6">
            <div class="flex items-center gap-3 border-b border-slate-100 dark:border-slate-800 pb-4">
              <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
                <Globe class="w-5 h-5" />
              </div>
              <div>
                <h3 class="font-black text-xl font-heading text-slate-900 dark:text-white">General Platform & Support Setup</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">System branding and hotline support</p>
              </div>
            </div>

            <div class="space-y-4 text-xs">
              <div>
                <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">SaaS Platform System Name</label>
                <input 
                  type="text" 
                  v-model="form.app_name" 
                  class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-bold focus:outline-none focus:border-rose-500" 
                />
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">Support Phone Hotline</label>
                  <input 
                    type="text" 
                    v-model="form.support_phone" 
                    placeholder="e.g. +880 1700 000000" 
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono focus:outline-none focus:border-rose-500" 
                  />
                </div>
                <div>
                  <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">Support Email</label>
                  <input 
                    type="email" 
                    v-model="form.support_email" 
                    placeholder="e.g. support@sababilling.net" 
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-rose-500" 
                  />
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">Default Currency Symbol</label>
                  <input 
                    type="text" 
                    v-model="form.currency_symbol" 
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono font-bold focus:outline-none focus:border-rose-500" 
                  />
                </div>
                <div>
                  <label class="block text-slate-700 dark:text-slate-300 font-bold mb-1">Default VAT / Tax Rate (%)</label>
                  <input 
                    type="number" 
                    step="0.01" 
                    v-model.number="form.default_tax_rate" 
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono font-bold focus:outline-none focus:border-rose-500" 
                  />
                </div>
              </div>

              <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 flex items-center justify-between">
                <div>
                  <span class="font-extrabold text-amber-900 dark:text-amber-300 text-xs block">System Maintenance Mode</span>
                  <span class="text-[10px] text-amber-700 dark:text-amber-400">Temporarily block non-admin logins during system upgrades (Super Admin can still log in)</span>
                </div>
                <input 
                  type="checkbox" 
                  v-model="form.maintenance_mode" 
                  class="w-5 h-5 accent-rose-600 rounded cursor-pointer" 
                />
              </div>

              <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
                <button 
                  type="submit" 
                  :disabled="isSubmitting"
                  class="w-full py-3.5 rounded-2xl bg-gradient-to-r from-rose-600 to-rose-700 hover:from-rose-500 hover:to-rose-600 text-white font-extrabold text-xs shadow-md shadow-rose-600/20 flex items-center justify-center gap-2 cursor-pointer transition-all disabled:opacity-50"
                >
                  <Save class="w-4 h-4" />
                  <span>{{ isSubmitting ? 'Saving Configurations...' : 'Save All Platform Master Configurations' }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Settings, Smartphone, Globe, Landmark, Save, AlertCircle } from 'lucide-vue-next';

const props = defineProps({
  settings: Object,
});

const isSubmitting = ref(false);

const form = ref({
  app_name: props.settings?.app_name || 'Saba POS',
  bkash_number: props.settings?.bkash_number || '',
  nagad_number: props.settings?.nagad_number || '',
  rocket_number: props.settings?.rocket_number || '',
  bank_name: props.settings?.bank_name || '',
  bank_account_name: props.settings?.bank_account_name || '',
  bank_account_number: props.settings?.bank_account_number || '',
  bank_branch_name: props.settings?.bank_branch_name || '',
  bank_routing_number: props.settings?.bank_routing_number || '',
  bank_instructions: props.settings?.bank_instructions || '',
  support_phone: props.settings?.support_phone || '',
  support_email: props.settings?.support_email || '',
  currency_symbol: props.settings?.currency_symbol || '৳',
  default_tax_rate: props.settings?.default_tax_rate !== undefined ? Number(props.settings.default_tax_rate) : 15.0,
  maintenance_mode: Boolean(props.settings?.maintenance_mode || false),
});

const saveSettings = () => {
  isSubmitting.value = true;
  router.post('/super-admin/settings', form.value, {
    preserveScroll: true,
    onFinish: () => {
      isSubmitting.value = false;
    },
  });
};
</script>
