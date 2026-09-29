<template>
  <div class="inline-block barcode-container">
    <svg ref="svgRef" class="w-full h-auto max-h-12 mx-auto"></svg>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import JsBarcode from 'jsbarcode';

const props = defineProps({
  value: {
    type: String,
    required: true,
  },
  format: {
    type: String,
    default: 'CODE128',
  },
  height: {
    type: Number,
    default: 35,
  },
  width: {
    type: Number,
    default: 1.5,
  },
  displayValue: {
    type: Boolean,
    default: false,
  },
  fontSize: {
    type: Number,
    default: 10,
  }
});

const svgRef = ref(null);

const renderBarcode = () => {
  if (!svgRef.value || !props.value) return;
  try {
    JsBarcode(svgRef.value, String(props.value), {
      format: props.format,
      height: props.height,
      width: props.width,
      displayValue: props.displayValue,
      fontSize: props.fontSize,
      margin: 2,
      background: 'transparent',
      lineColor: '#000000',
    });
  } catch (err) {
    // Fallback if value format is invalid for CODE128
    console.warn('JsBarcode render error:', err);
  }
};

onMounted(renderBarcode);
watch(() => props.value, renderBarcode);
</script>
