<template>
  <div class="min-h-screen bg-slate-100 text-slate-900 flex items-center justify-center p-6 selection:bg-emerald-500 selection:text-white">
    <div class="max-w-md w-full bg-white border border-slate-200 rounded-3xl p-8 shadow-xl space-y-6">
      <!-- Header -->
      <div class="text-center space-y-2">
        <div class="w-14 h-14 rounded-2xl bg-emerald-600 mx-auto flex items-center justify-center font-black text-3xl text-white shadow-md">
          S
        </div>
        <h1 class="text-2xl font-black font-heading text-slate-900">Saba POS SaaS Portal</h1>
        <p class="text-xs text-slate-500 font-medium">Select a role persona to log in & test portal authority</p>
      </div>

      <!-- Login Form -->
      <form @submit.prevent="submitLogin" class="space-y-4">
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

const form = ref({
  email: '',
  password: '',
});

const isSubmitting = ref(false);

const submitLogin = () => {
  isSubmitting.value = true;
  router.post('/login', form.value, {
    onFinish: () => {
      isSubmitting.value = false;
    }
  });
};
</script>
