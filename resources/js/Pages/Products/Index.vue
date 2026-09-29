<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-6">
      <!-- Title & Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl sm:text-3xl font-black font-heading text-slate-900">
            Product Inventory & Catalog
          </h1>
          <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">
            Manage SKUs, barcodes, cost margins, and stock levels across outlets
          </p>
        </div>

        <div class="flex items-center gap-2">
          <button 
            @click="showCategoryModal = true" 
            class="px-4 py-3 rounded-2xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 font-extrabold text-sm flex items-center justify-center gap-2 transition-all active:scale-95"
          >
            <FolderPlus class="w-4 h-4" />
            <span>Add Category</span>
          </button>
          <button 
            @click="openAddModal" 
            class="px-5 py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-sm shadow-md flex items-center justify-center gap-2 transition-all active:scale-95"
          >
            <Plus class="w-4 h-4" />
            <span>Add New Product</span>
          </button>
        </div>
      </div>

      <!-- Inventory Table Card -->
      <div class="p-6 rounded-3xl bg-white border border-slate-200 shadow-xs space-y-4">
        <!-- Search Filter -->
        <div class="flex items-center gap-3">
          <div class="flex-1 relative">
            <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input 
              type="text" 
              v-model="searchQuery" 
              placeholder="Search product inventory by name or SKU..." 
              class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-emerald-600 focus:bg-white"
            />
          </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] font-bold">
                <th class="py-3 px-3">Product Name</th>
                <th class="py-3 px-3">SKU / Barcode</th>
                <th class="py-3 px-3">Category</th>
                <th class="py-3 px-3 text-right">Cost Price</th>
                <th class="py-3 px-3 text-right">Selling Price</th>
                <th class="py-3 px-3 text-center">Stock Level</th>
                <th class="py-3 px-3 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="product in filteredProducts" :key="product.id" class="hover:bg-slate-50 transition-colors">
                <td class="py-3.5 px-3">
                  <div class="font-bold text-slate-900 text-sm font-heading">{{ product.name }}</div>
                  <div v-if="product.has_serial" class="text-[10px] text-cyan-700 font-mono font-bold">Serial / IMEI Tracked</div>
                </td>
                <td class="py-3.5 px-3 font-mono text-slate-700">
                  <div>{{ product.sku }}</div>
                  <div class="text-[10px] text-slate-400">{{ product.barcode || 'N/A' }}</div>
                </td>
                <td class="py-3.5 px-3 text-indigo-700 font-semibold">
                  {{ product.category?.name || 'General' }}
                </td>
                <td class="py-3.5 px-3 text-right font-mono text-slate-600">
                  ৳{{ formatMoney(product.purchase_cost) }}
                </td>
                <td class="py-3.5 px-3 text-right font-mono font-bold text-emerald-700">
                  ৳{{ formatMoney(product.selling_price) }}
                </td>
                <td class="py-3.5 px-3 text-center">
                  <span :class="['px-3 py-1 rounded-full text-[10px] font-bold font-mono', getStockQty(product) > 5 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200']">
                    {{ getStockQty(product) }} {{ product.unit?.short_name || 'Pc' }}
                  </span>
                </td>
                <td class="py-3.5 px-3 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <button @click="openEditModal(product)" class="p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-slate-100 transition-colors" title="Edit Product">
                      <Edit3 class="w-4 h-4" />
                    </button>
                    <button @click="deleteProduct(product)" class="p-1.5 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-slate-100 transition-colors" title="Delete Product">
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ADD/EDIT PRODUCT MODAL -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
      <div class="bg-white border border-slate-200 rounded-3xl max-w-lg w-full overflow-hidden shadow-2xl">
        <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50">
          <h3 class="font-bold text-lg font-heading text-slate-900">{{ isEditing ? 'Edit Inventory Product' : 'Add New Inventory Product' }}</h3>
          <button @click="showModal = false" class="text-slate-400 hover:text-slate-700">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="saveProduct" class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Product Title</label>
            <input type="text" v-model="form.name" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:border-emerald-600 focus:bg-white" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">SKU</label>
              <input type="text" v-model="form.sku" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 font-mono focus:outline-none focus:border-emerald-600 focus:bg-white" />
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Barcode #</label>
              <input type="text" v-model="form.barcode" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 font-mono focus:outline-none focus:border-emerald-600 focus:bg-white" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Category</label>
              <select v-model="form.category_id" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:border-emerald-600 focus:bg-white">
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div v-if="!isEditing">
              <label class="block text-xs font-bold text-slate-700 mb-1">Initial Stock Qty</label>
              <input type="number" v-model.number="form.initial_stock" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 font-mono focus:outline-none focus:border-emerald-600 focus:bg-white" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Cost Price (৳)</label>
              <input type="number" step="0.01" v-model.number="form.purchase_cost" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 font-mono focus:outline-none focus:border-emerald-600 focus:bg-white" />
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Selling Price (৳)</label>
              <input type="number" step="0.01" v-model.number="form.selling_price" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 font-mono focus:outline-none focus:border-emerald-600 focus:bg-white" />
            </div>
          </div>

          <div class="pt-3 flex gap-3">
            <button type="submit" class="flex-1 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md">
              {{ isEditing ? 'Update Product' : 'Save Product' }}
            </button>
            <button type="button" @click="showModal = false" class="px-5 py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ADD CATEGORY MODAL -->
    <div v-if="showCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
      <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full overflow-hidden shadow-2xl">
        <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50">
          <h3 class="font-bold text-lg font-heading text-slate-900">Add New Category</h3>
          <button @click="showCategoryModal = false" class="text-slate-400 hover:text-slate-700">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="saveCategory" class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Category Name</label>
            <input type="text" v-model="categoryForm.name" required placeholder="e.g. Electronics, Grocery" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:border-indigo-600 focus:bg-white" />
          </div>

          <div class="pt-3 flex gap-3">
            <button type="submit" class="flex-1 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md">
              Save Category
            </button>
            <button type="button" @click="showCategoryModal = false" class="px-5 py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Plus, Search, X, Edit3, Trash2, FolderPlus } from 'lucide-vue-next';

const props = defineProps({
  products: Object,
  categories: Array,
  brands: Array,
  units: Array,
});

const searchQuery = ref('');
const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const showCategoryModal = ref(false);

const form = ref({
  name: '',
  sku: '',
  barcode: '',
  category_id: null,
  purchase_cost: 0,
  selling_price: 0,
  initial_stock: 20,
});

const categoryForm = ref({
  name: '',
});

const openAddModal = () => {
  isEditing.value = false;
  editingId.value = null;
  form.value = {
    name: '',
    sku: 'SKU-' + Math.floor(1000 + Math.random() * 9000),
    barcode: '880' + Math.floor(100000000 + Math.random() * 900000000),
    category_id: props.categories?.[0]?.id || null,
    purchase_cost: 0,
    selling_price: 0,
    initial_stock: 20,
  };
  showModal.value = true;
};

const openEditModal = (product) => {
  isEditing.value = true;
  editingId.value = product.id;
  form.value = {
    name: product.name,
    sku: product.sku,
    barcode: product.barcode || '',
    category_id: product.category_id,
    purchase_cost: product.purchase_cost,
    selling_price: product.selling_price,
  };
  showModal.value = true;
};

const filteredProducts = computed(() => {
  const list = props.products?.data || props.products || [];
  const query = searchQuery.value.toLowerCase();
  return list.filter(p => !query || p.name.toLowerCase().includes(query) || p.sku.toLowerCase().includes(query));
});

const getStockQty = (product) => {
  if (product.stocks && product.stocks.length) {
    return product.stocks[0].quantity;
  }
  return 10;
};

const formatMoney = (val) => {
  return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const saveProduct = () => {
  if (isEditing.value) {
    router.post(`/products/${editingId.value}/update`, form.value, {
      onSuccess: () => {
        showModal.value = false;
      }
    });
  } else {
    router.post('/products', form.value, {
      onSuccess: () => {
        showModal.value = false;
      }
    });
  }
};

const deleteProduct = (product) => {
  if (confirm(`Are you sure you want to delete product '${product.name}'?`)) {
    router.delete(`/products/${product.id}`);
  }
};

const saveCategory = () => {
  router.post('/products/categories', categoryForm.value, {
    onSuccess: () => {
      showCategoryModal.value = false;
      categoryForm.value.name = '';
    }
  });
};
</script>
