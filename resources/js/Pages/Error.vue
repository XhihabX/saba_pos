<template>
  <AuthenticatedLayout>
    <div class="p-6 sm:p-12 w-full min-h-[80vh] flex flex-col items-center justify-center text-center bg-slate-100">
      <div class="max-w-lg w-full bg-white border border-slate-200 rounded-3xl p-8 sm:p-10 shadow-xl space-y-6">
        <!-- Status Icon -->
        <div class="w-20 h-20 rounded-3xl mx-auto flex items-center justify-center font-black text-3xl shadow-lg"
          :class="[
            status === 404 ? 'bg-amber-500 text-white' : 
            (status === 403 ? 'bg-rose-600 text-white' : 'bg-indigo-600 text-white')
          ]"
        >
          <AlertTriangle v-if="status === 404" class="w-10 h-10" />
          <ShieldAlert v-else-if="status === 403" class="w-10 h-10" />
          <ServerCrash v-else class="w-10 h-10" />
        </div>

        <!-- Title & Message -->
        <div class="space-y-2">
          <span class="text-xs font-black font-mono uppercase tracking-widest px-3 py-1 rounded-full"
            :class="[
              status === 404 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 
              (status === 403 ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-indigo-50 text-indigo-700 border border-indigo-200')
            ]"
          >
            HTTP {{ status || 404 }} Error
          </span>
          <h1 class="text-2xl font-black font-heading text-slate-900 mt-2">{{ title }}</h1>
          <p class="text-xs text-slate-600 leading-relaxed max-w-sm mx-auto font-medium">{{ description }}</p>
        </div>

        <!-- Action Button -->
        <div class="pt-2">
          <Link 
            :href="dashboardUrl" 
            class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-md transition-all active:scale-95"
          >
            <ArrowLeft class="w-4 h-4" />
            <span>Return to Portal Dashboard</span>
          </Link>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { AlertTriangle, ShieldAlert, ServerCrash, ArrowLeft } from 'lucide-vue-next';

const props = defineProps({
  status: {
    type: Number,
    default: 404,
  },
  message: String,
});

const page = usePage();

const title = computed(() => {
  if (props.status === 404) return 'Page or Resource Not Found';
  if (props.status === 403) return 'Access Restricted';
  if (props.status === 500) return 'Internal System Error';
  return 'Unexpected Operational Response';
});

const description = computed(() => {
  if (props.message) return props.message;
  if (props.status === 404) return 'The page, store outlet, or record you requested does not exist or has been moved.';
  if (props.status === 403) return 'Your account permissions do not grant access to this portal area.';
  return 'Our servers encountered an temporary issue processing this action. Please refresh or navigate back.';
});

const dashboardUrl = computed(() => {
  const role = page.props.auth?.user?.role;
  if (role === 'super_admin') return '/super-admin/dashboard';
  if (role === 'merchant') return '/merchant/dashboard';
  if (role === 'store_manager') return '/manager/dashboard';
  return '/dashboard';
});
</script>
