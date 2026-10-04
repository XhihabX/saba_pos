<template>
  <div class="min-h-screen bg-slate-100 text-slate-900 flex items-center justify-center p-6 selection:bg-emerald-500 selection:text-white">
    <div class="max-w-md w-full bg-white border border-slate-200 rounded-3xl p-8 shadow-xl space-y-6">
      <!-- Header -->
      <div class="text-center space-y-2 flex flex-col items-center">
        <ApplicationLogo size="lg" :show-text="true" subtitle="SaaS Enterprise POS & ERP" />
        <p class="text-xs text-slate-500 font-medium mt-2">Log in to access your ERP dashboard and POS terminal</p>
      </div>

      <!-- Flash / Validation Errors -->
      <div v-if="errors && (errors.email || errors.password)" class="p-3.5 bg-rose-50 border border-rose-200 rounded-2xl text-xs text-rose-700 font-medium">
        {{ errors.email || errors.password }}
      </div>
      <div v-if="flash && flash.error" class="p-3.5 bg-amber-50 border border-amber-200 rounded-2xl text-xs text-amber-800 font-medium">
        {{ flash.error }}
      </div>
      <div v-if="flash && flash.success" class="p-3.5 bg-emerald-50 border border-emerald-200 rounded-2xl text-xs text-emerald-800 font-medium">
        {{ flash.success }}
      </div>

      <!-- Quick Persona Login Shortcuts -->
      <div class="space-y-2">
        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider text-center">Quick Persona Demo Login</div>
        <div class="grid grid-cols-2 gap-2">
          <button @click="fillCredentials('merchant@sabapos.com', 'password123')" type="button" class="px-3 py-2 bg-slate-100 hover:bg-emerald-50 hover:border-emerald-300 border border-slate-200 rounded-xl text-left transition">
            <div class="text-xs font-bold text-slate-900">Merchant CEO</div>
            <div class="text-[10px] text-slate-500">merchant@sabapos.com</div>
          </button>
          <button @click="fillCredentials('admin@sabapos.com', 'password123')" type="button" class="px-3 py-2 bg-slate-100 hover:bg-indigo-50 hover:border-indigo-300 border border-slate-200 rounded-xl text-left transition">
            <div class="text-xs font-bold text-slate-900">Super Admin</div>
            <div class="text-[10px] text-slate-500">admin@sabapos.com</div>
          </button>
          <button @click="fillCredentials('manager@sabapos.com', 'password123')" type="button" class="px-3 py-2 bg-slate-100 hover:bg-amber-50 hover:border-amber-300 border border-slate-200 rounded-xl text-left transition">
            <div class="text-xs font-bold text-slate-900">Store Manager</div>
            <div class="text-[10px] text-slate-500">manager@sabapos.com</div>
          </button>
          <button @click="fillCredentials('cashier@sabapos.com', 'password123')" type="button" class="px-3 py-2 bg-slate-100 hover:bg-teal-50 hover:border-teal-300 border border-slate-200 rounded-xl text-left transition">
            <div class="text-xs font-bold text-slate-900">Cashier POS</div>
            <div class="text-[10px] text-slate-500">cashier@sabapos.com</div>
          </button>
        </div>
      </div>

      <!-- Login Form -->
      <form @submit.prevent="submitLogin" class="space-y-4 pt-2">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Email Address</label>
          <input 
            type="email" 
            v-model="form.email" 
            required 
            placeholder="merchant@yourdomain.com"
            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:border-emerald-600 focus:bg-white"
          />
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Password</label>
          <input 
            type="password" 
            v-model="form.password" 
            required 
            placeholder="••••••••"
            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:border-emerald-600 focus:bg-white"
          />
        </div>

        <button 
          type="submit" 
          :disabled="isSubmitting"
          class="w-full py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-sm shadow-md transition-all active:scale-95 disabled:opacity-50"
        >
          {{ isSubmitting ? 'Authenticating...' : 'Sign In to Portal' }}
        </button>

        <div class="text-center pt-2 text-xs text-slate-600">
          Don't have a merchant account? 
          <Link href="/register" class="font-bold text-emerald-700 hover:underline">Register New Store</Link>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

const props = defineProps({
  errors: Object,
  flash: Object,
});

const form = ref({
  email: 'merchant@sabapos.com',
  password: 'password123',
});

const isSubmitting = ref(false);

const fillCredentials = (email, password) => {
  form.value.email = email;
  form.value.password = password;
};

const submitLogin = () => {
  isSubmitting.value = true;
  router.post('/login', form.value, {
    onFinish: () => {
      isSubmitting.value = false;
    }
  });
};
</script>

