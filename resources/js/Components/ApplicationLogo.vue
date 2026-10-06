<template>
  <div class="flex items-center gap-3 overflow-hidden">
    <div 
      :class="[
        'rounded-xl flex items-center justify-center shrink-0 overflow-hidden transition-transform duration-200 hover:scale-105 border border-slate-700/50 shadow-md shadow-emerald-500/10 bg-slate-900',
        sizeClasses.container
      ]"
    >
      <img 
        v-if="logoSrc && !imgFailed" 
        :src="logoSrc" 
        alt="IOT POS Logo"
        class="w-full h-full object-cover"
        @error="handleImgError"
      />
      <div v-else class="w-full h-full bg-gradient-to-br from-emerald-500 via-teal-600 to-indigo-600 flex items-center justify-center font-black text-white font-heading text-xs tracking-tighter shadow-inner">
        IOT
      </div>
    </div>

    <div v-if="showText" class="truncate">
      <div :class="['font-extrabold font-heading text-slate-900 dark:text-slate-100 tracking-wide leading-none', sizeClasses.text]">
        IOT <span class="text-indigo-500">POS</span>
      </div>
      <div v-if="subtitle" class="text-[10px] text-indigo-600 dark:text-indigo-400 uppercase tracking-widest font-black mt-1">
        {{ subtitle }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  size: {
    type: String,
    default: 'md'
  },
  src: {
    type: String,
    default: '/images/logo.png'
  },
  showText: {
    type: Boolean,
    default: true
  },
  subtitle: {
    type: String,
    default: 'International Office Technology'
  }
});

const defaultLogo = '/images/logo.png';
const imgFailed = ref(false);

const logoSrc = computed(() => {
  if (imgFailed.value) {
    return null;
  }
  return props.src || defaultLogo;
});

const handleImgError = () => {
  imgFailed.value = true;
};

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return { container: 'w-8 h-8', text: 'text-sm' };
    case 'lg':
      return { container: 'w-12 h-12', text: 'text-xl' };
    case 'xl':
      return { container: 'w-16 h-16', text: 'text-2xl' };
    case 'md':
    default:
      return { container: 'w-10 h-10', text: 'text-base' };
  }
});
</script>
