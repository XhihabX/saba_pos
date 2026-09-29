<template>
  <div class="min-h-screen bg-slate-100 text-slate-900 flex items-center justify-center p-4 lg:p-8 selection:bg-emerald-500 selection:text-white">
    <div class="max-w-5xl w-full bg-white border border-slate-200 rounded-3xl shadow-2xl overflow-hidden grid grid-cols-1 lg:grid-cols-12 min-h-[660px]">
      
      <!-- Left Branding & Feature Sidebar -->
      <div class="lg:col-span-5 bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-950 p-8 lg:p-10 text-white flex flex-col justify-between relative overflow-hidden">
        <!-- Floating Ambient Halo -->
        <div class="absolute -bottom-10 -left-10 w-64 h-64 bg-emerald-500/20 blur-3xl rounded-full"></div>

        <div>
          <div class="flex items-center gap-3 mb-8">
            <div class="w-12 h-12 rounded-2xl bg-emerald-600 flex items-center justify-center font-black text-2xl text-white shadow-lg">
              S
            </div>
            <div>
              <span class="font-black text-2xl font-heading tracking-tight text-white block">Saba POS</span>
              <span class="text-[10px] text-emerald-400 font-bold uppercase tracking-widest">SaaS Cloud & ERP</span>
            </div>
          </div>

          <h2 class="text-2xl lg:text-3xl font-black font-heading leading-tight mb-4 text-white">
            Sell. Track. Grow. <br />
            <span class="text-emerald-400">Power Your Store.</span>
          </h2>

          <p class="text-xs text-slate-300 leading-relaxed mb-8">
            Join thousands of retail shops, restaurants, and chain stores using Saba POS for sub-10ms checkout and offline counter resilience.
          </p>

          <!-- Feature Checkmark Badges -->
          <div class="space-y-3.5 text-xs font-semibold text-slate-200">
            <div class="flex items-center gap-3 bg-white/5 border border-white/10 p-3 rounded-2xl">
              <CheckCircle2 class="w-4 h-4 text-emerald-400 shrink-0" />
              <span>Zero-Downtime Offline Counter Selling</span>
            </div>
            <div class="flex items-center gap-3 bg-white/5 border border-white/10 p-3 rounded-2xl">
              <CheckCircle2 class="w-4 h-4 text-emerald-400 shrink-0" />
              <span>Multi-Outlet Inventory & Stock Transfers</span>
            </div>
            <div class="flex items-center gap-3 bg-white/5 border border-white/10 p-3 rounded-2xl">
              <CheckCircle2 class="w-4 h-4 text-emerald-400 shrink-0" />
              <span>Manual bKash / Nagad TrxID Instant Activation</span>
            </div>
          </div>
        </div>

        <div class="pt-8 border-t border-white/10 text-[11px] text-slate-400">
          <span>✓ Instant Merchant Verification • 24/7 Super Admin Support</span>
        </div>
      </div>

      <!-- Right Registration Multi-Step Wizard Container -->
      <div class="lg:col-span-7 p-6 sm:p-8 lg:p-10 flex flex-col justify-between bg-white">
        <div>
          <!-- Wizard Step Indicator Header -->
          <div class="mb-6">
            <div class="flex items-center justify-between text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">
              <span>Merchant Onboarding Wizard</span>
              <span class="text-emerald-700">Step {{ currentStep }} of 4</span>
            </div>

            <div class="grid grid-cols-4 gap-1.5">
              <button 
                type="button" 
                @click="currentStep = 1" 
                :class="['py-2 px-2 rounded-xl text-[10px] sm:text-[11px] font-extrabold flex items-center justify-center gap-1 transition-all', currentStep === 1 ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
              >
                <Building class="w-3 h-3" />
                <span class="hidden sm:inline">1. Business</span>
              </button>

              <button 
                type="button" 
                @click="currentStep = 2" 
                :class="['py-2 px-2 rounded-xl text-[10px] sm:text-[11px] font-extrabold flex items-center justify-center gap-1 transition-all', currentStep === 2 ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
              >
                <MapPin class="w-3 h-3" />
                <span class="hidden sm:inline">2. Outlet</span>
              </button>

              <button 
                type="button" 
                @click="currentStep = 3" 
                :class="['py-2 px-2 rounded-xl text-[10px] sm:text-[11px] font-extrabold flex items-center justify-center gap-1 transition-all', currentStep === 3 ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
              >
                <UserCheck class="w-3 h-3" />
                <span class="hidden sm:inline">3. Account</span>
              </button>

              <button 
                type="button" 
                @click="currentStep = 4" 
                :class="['py-2 px-2 rounded-xl text-[10px] sm:text-[11px] font-extrabold flex items-center justify-center gap-1 transition-all', currentStep === 4 ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
              >
                <Wallet class="w-3 h-3" />
                <span class="hidden sm:inline">4. Payment</span>
              </button>
            </div>
          </div>

          <form @submit.prevent="submitRegister" class="space-y-4">
            
            <!-- STEP 1: BUSINESS DETAILS -->
            <div v-if="currentStep === 1" class="space-y-4 animate-fade-in">
              <h3 class="text-base sm:text-lg font-black font-heading text-slate-900 border-b border-slate-100 pb-2">
                1. Business Overview
              </h3>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Business / Brand Name *</label>
                <div class="relative">
                  <Building class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                  <input 
                    type="text" 
                    v-model="form.business_name" 
                    placeholder="e.g. Apex Footwear Ltd / Star Cafe"
                    required 
                    class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 font-medium focus:outline-none focus:border-emerald-600 focus:bg-white"
                  />
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold text-slate-700 mb-1">Business Contact Phone *</label>
                  <div class="relative">
                    <Phone class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                    <input 
                      type="text" 
                      v-model="form.phone" 
                      placeholder="+880 1700 000000"
                      required
                      class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 font-medium focus:outline-none focus:border-emerald-600 focus:bg-white"
                    />
                  </div>
                </div>

                <div>
                  <label class="block text-xs font-bold text-slate-700 mb-1">Currency Symbol</label>
                  <div class="relative">
                    <DollarSign class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                    <input 
                      type="text" 
                      v-model="form.currency_symbol" 
                      placeholder="৳ / $ / €"
                      class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 font-bold focus:outline-none focus:border-emerald-600 focus:bg-white"
                    />
                  </div>
                </div>
              </div>

              <div class="flex justify-end pt-4">
                <button 
                  type="button" 
                  @click="currentStep = 2" 
                  class="px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-md flex items-center gap-2"
                >
                  <span>Next: Outlet Setup</span>
                  <ArrowRight class="w-4 h-4" />
                </button>
              </div>
            </div>

            <!-- STEP 2: STORE & LOCATION -->
            <div v-if="currentStep === 2" class="space-y-4 animate-fade-in">
              <h3 class="text-base sm:text-lg font-black font-heading text-slate-900 border-b border-slate-100 pb-2">
                2. Main Outlet & Branch Setup
              </h3>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Flagship Outlet Name</label>
                <div class="relative">
                  <Store class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                  <input 
                    type="text" 
                    v-model="form.store_name" 
                    placeholder="e.g. Main Branch, Gulshan-2"
                    class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 font-medium focus:outline-none focus:border-emerald-600 focus:bg-white"
                  />
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold text-slate-700 mb-1">City / Region</label>
                  <div class="relative">
                    <MapPin class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                    <input 
                      type="text" 
                      v-model="form.city" 
                      placeholder="Dhaka / Chittagong"
                      class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 font-medium focus:outline-none focus:border-emerald-600 focus:bg-white"
                    />
                  </div>
                </div>

                <div>
                  <label class="block text-xs font-bold text-slate-700 mb-1">VAT / Tax Rate (%)</label>
                  <div class="relative">
                    <Percent class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                    <input 
                      type="number" 
                      v-model.number="form.default_tax_rate" 
                      placeholder="5.00"
                      class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 font-bold focus:outline-none focus:border-emerald-600 focus:bg-white"
                    />
                  </div>
                </div>
              </div>

              <div class="flex items-center justify-between pt-4">
                <button 
                  type="button" 
                  @click="currentStep = 1" 
                  class="px-5 py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs"
                >
                  Back
                </button>
                <button 
                  type="button" 
                  @click="currentStep = 3" 
                  class="px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-md flex items-center gap-2"
                >
                  <span>Next: Owner Account</span>
                  <ArrowRight class="w-4 h-4" />
                </button>
              </div>
            </div>

            <!-- STEP 3: OWNER CREDENTIALS & PLAN -->
            <div v-if="currentStep === 3" class="space-y-4 animate-fade-in">
              <h3 class="text-base sm:text-lg font-black font-heading text-slate-900 border-b border-slate-100 pb-2">
                3. Owner Account & SaaS Plan
              </h3>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold text-slate-700 mb-1">Owner / CEO Full Name *</label>
                  <div class="relative">
                    <User class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                    <input 
                      type="text" 
                      v-model="form.owner_name" 
                      placeholder="e.g. Rahul Chowdhury"
                      required 
                      class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 font-medium focus:outline-none focus:border-emerald-600 focus:bg-white"
                    />
                  </div>
                </div>

                <div>
                  <label class="block text-xs font-bold text-slate-700 mb-1">Work Email Address *</label>
                  <div class="relative">
                    <Mail class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                    <input 
                      type="email" 
                      v-model="form.email" 
                      placeholder="owner@yourstore.com"
                      required 
                      class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 font-medium focus:outline-none focus:border-emerald-600 focus:bg-white"
                    />
                  </div>
                </div>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Select SaaS Plan Tier</label>
                <div class="relative">
                  <Crown class="w-4 h-4 text-emerald-600 absolute left-3.5 top-1/2 -translate-y-1/2" />
                  <select 
                    v-model="form.plan_name" 
                    class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs font-bold text-slate-900 focus:outline-none focus:border-emerald-600 focus:bg-white"
                  >
                    <option value="Starter POS">Starter POS (৳1,499 / mo - 1 Outlet, 2 Terminals)</option>
                    <option value="Growth Multi-Store">Growth Multi-Store (৳3,999 / mo - 5 Outlets, Unlimited Terminals)</option>
                    <option value="Enterprise ERP">Enterprise ERP (৳9,999 / mo - Custom SLA & Unlimited Outlets)</option>
                  </select>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold text-slate-700 mb-1">Password *</label>
                  <div class="relative">
                    <Lock class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                    <input 
                      :type="showPassword ? 'text' : 'password'" 
                      v-model="form.password" 
                      required 
                      class="w-full pl-10 pr-10 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:border-emerald-600 focus:bg-white"
                    />
                    <button 
                      type="button" 
                      @click="showPassword = !showPassword" 
                      class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                    >
                      <Eye v-if="!showPassword" class="w-4 h-4" />
                      <EyeOff v-else class="w-4 h-4" />
                    </button>
                  </div>
                </div>

                <div>
                  <label class="block text-xs font-bold text-slate-700 mb-1">Confirm Password *</label>
                  <div class="relative">
                    <Lock class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                    <input 
                      :type="showPassword ? 'text' : 'password'" 
                      v-model="form.password_confirmation" 
                      required 
                      class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:border-emerald-600 focus:bg-white"
                    />
                  </div>
                </div>
              </div>

              <div class="flex items-center justify-between pt-4">
                <button 
                  type="button" 
                  @click="currentStep = 2" 
                  class="px-5 py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs"
                >
                  Back
                </button>
                <button 
                  type="button" 
                  @click="currentStep = 4" 
                  class="px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-md flex items-center gap-2"
                >
                  <span>Next: Payment Verification</span>
                  <ArrowRight class="w-4 h-4" />
                </button>
              </div>
            </div>

            <!-- STEP 4: MANUAL BKASH/NAGAD PAYMENT & TRXID VERIFICATION -->
            <div v-if="currentStep === 4" class="space-y-4 animate-fade-in">
              <h3 class="text-base sm:text-lg font-black font-heading text-slate-900 border-b border-slate-100 pb-2 flex items-center justify-between">
                <span>4. Manual Payment Verification</span>
                <span class="text-xs font-bold text-pink-600 bg-pink-50 px-2.5 py-1 rounded-lg border border-pink-200">bKash / Nagad / Rocket</span>
              </h3>

              <!-- Payment Instruction Card -->
              <div class="bg-gradient-to-r from-pink-50 via-slate-50 to-emerald-50 border border-pink-200 rounded-2xl p-4 text-xs space-y-2 text-slate-800">
                <div class="flex items-center justify-between">
                  <span class="font-extrabold text-pink-700">bKash / Nagad Merchant Number:</span>
                  <span class="font-mono font-black text-sm text-slate-950 bg-white px-2 py-0.5 rounded border border-slate-300">01700-000000</span>
                </div>
                <p class="text-[11px] text-slate-600">
                  Please send <strong>{{ getPlanAmount() }}</strong> for your selected plan ({{ form.plan_name }}) to the bKash/Nagad number above, then submit your Sender Phone Number and Transaction ID below.
                </p>
              </div>

              <!-- Payment Method Selection -->
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Select Payment Channel *</label>
                <div class="grid grid-cols-4 gap-2">
                  <button 
                    type="button"
                    @click="form.payment_method = 'bkash'"
                    :class="['p-2.5 rounded-xl border font-black text-xs transition-all text-center', form.payment_method === 'bkash' ? 'border-pink-500 bg-pink-500 text-white shadow-xs' : 'border-slate-300 bg-slate-50 text-slate-700 hover:bg-slate-100']"
                  >
                    bKash
                  </button>
                  <button 
                    type="button"
                    @click="form.payment_method = 'nagad'"
                    :class="['p-2.5 rounded-xl border font-black text-xs transition-all text-center', form.payment_method === 'nagad' ? 'border-orange-500 bg-orange-500 text-white shadow-xs' : 'border-slate-300 bg-slate-50 text-slate-700 hover:bg-slate-100']"
                  >
                    Nagad
                  </button>
                  <button 
                    type="button"
                    @click="form.payment_method = 'rocket'"
                    :class="['p-2.5 rounded-xl border font-black text-xs transition-all text-center', form.payment_method === 'rocket' ? 'border-purple-600 bg-purple-600 text-white shadow-xs' : 'border-slate-300 bg-slate-50 text-slate-700 hover:bg-slate-100']"
                  >
                    Rocket
                  </button>
                  <button 
                    type="button"
                    @click="form.payment_method = 'bank'"
                    :class="['p-2.5 rounded-xl border font-black text-xs transition-all text-center', form.payment_method === 'bank' ? 'border-emerald-600 bg-emerald-600 text-white shadow-xs' : 'border-slate-300 bg-slate-50 text-slate-700 hover:bg-slate-100']"
                  >
                    Bank
                  </button>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold text-slate-700 mb-1">Sender Mobile / Phone Number *</label>
                  <div class="relative">
                    <Phone class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                    <input 
                      type="text" 
                      v-model="form.sender_number" 
                      placeholder="017XXXXXXXX"
                      required 
                      class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs font-mono font-bold text-slate-900 focus:outline-none focus:border-pink-500 focus:bg-white"
                    />
                  </div>
                </div>

                <div>
                  <label class="block text-xs font-bold text-slate-700 mb-1">Transaction ID (TrxID) *</label>
                  <div class="relative">
                    <CreditCard class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                    <input 
                      type="text" 
                      v-model="form.transaction_id" 
                      placeholder="e.g. BK89X72901"
                      required 
                      class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs font-mono font-bold text-slate-900 uppercase focus:outline-none focus:border-pink-500 focus:bg-white"
                    />
                  </div>
                </div>
              </div>

              <div class="flex items-center justify-between pt-4">
                <button 
                  type="button" 
                  @click="currentStep = 3" 
                  class="px-5 py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs"
                >
                  Back
                </button>
                <button 
                  type="submit" 
                  :disabled="isSubmitting"
                  class="px-8 py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-sm shadow-lg transition-all active:scale-95 disabled:opacity-50 flex items-center gap-2"
                >
                  <span>{{ isSubmitting ? 'Submitting Verification...' : 'Submit & Await Super Admin Approval 🚀' }}</span>
                </button>
              </div>
            </div>

          </form>
        </div>

        <div class="text-center pt-4 border-t border-slate-100 text-xs text-slate-600 mt-6">
          Already registered? 
          <Link href="/login" class="font-extrabold text-emerald-700 hover:underline">Sign In to Merchant Portal →</Link>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { 
  Building, 
  Phone, 
  DollarSign, 
  Store, 
  MapPin, 
  Percent, 
  User, 
  Mail, 
  Lock, 
  Crown, 
  CheckCircle2, 
  ArrowRight, 
  UserCheck, 
  Wallet,
  CreditCard,
  Eye, 
  EyeOff 
} from 'lucide-vue-next';

const props = defineProps({
  selectedPlan: String,
  plans: Array,
});

const currentStep = ref(1);
const showPassword = ref(false);

const form = ref({
  business_name: '',
  owner_name: '',
  email: '',
  phone: '',
  currency_symbol: '৳',
  store_name: '',
  city: 'Dhaka',
  default_tax_rate: 5.00,
  plan_name: props.selectedPlan === 'starter' ? 'Starter POS' : (props.selectedPlan === 'enterprise' ? 'Enterprise ERP' : 'Growth Multi-Store'),
  payment_method: 'bkash',
  sender_number: '',
  transaction_id: '',
  password: '',
  password_confirmation: '',
});

const isSubmitting = ref(false);

const getPlanAmount = () => {
  if (form.value.plan_name === 'Starter POS') return '৳1,499.00';
  if (form.value.plan_name === 'Enterprise ERP') return '৳9,999.00';
  return '৳3,999.00';
};

const submitRegister = () => {
  if (currentStep.value < 4) {
    currentStep.value++;
    return;
  }

  isSubmitting.value = true;
  router.post('/register', form.value, {
    onFinish: () => {
      isSubmitting.value = false;
    }
  });
};
</script>
