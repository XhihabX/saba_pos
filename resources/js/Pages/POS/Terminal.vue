<template>
  <AuthenticatedLayout>
    <!-- Kinetic POS Bilingual Dual-Theme Container (Light & Soft Dark Mode) -->
    <div class="flex-1 flex flex-col h-[calc(100vh-4rem)] overflow-hidden bg-slate-100 dark:bg-slate-900 text-slate-900 dark:text-slate-100 font-sans selection:bg-emerald-500 selection:text-white transition-colors duration-200">
      

      <!-- Top Status Header & Offline Sync Bar -->
      <div class="px-4 sm:px-6 py-2.5 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between text-xs shadow-xs shrink-0">
        <div class="flex items-center gap-3">
          <!-- Mobile Tab Switcher (< lg screens) -->
          <div class="flex lg:hidden items-center bg-slate-100 dark:bg-slate-900 p-1 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold">
            <button 
              @click="mobileActiveTab = 'catalog'" 
              :class="['px-3 py-1 rounded-lg font-bold transition-all', mobileActiveTab === 'catalog' ? 'bg-emerald-500 text-slate-950 font-black shadow' : 'text-slate-600 dark:text-slate-400']"
            >
              🛍️ Catalog
            </button>
            <button 
              @click="mobileActiveTab = 'cart'" 
              :class="['px-3 py-1 rounded-lg font-bold transition-all flex items-center gap-1', mobileActiveTab === 'cart' ? 'bg-emerald-500 text-slate-950 font-black shadow' : 'text-slate-600 dark:text-slate-400']"
            >
              🛒 Cart ({{ totalCartQty }})
            </button>
          </div>

          <!-- Network Connection Status Indicator -->
          <div class="flex items-center gap-2">
            <span v-if="isOnline" class="flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 font-bold text-[11px]">
              <span class="w-2 h-2 rounded-full bg-emerald-500 dark:bg-emerald-400 animate-pulse"></span>
              {{ t('online_mode') }}
            </span>
            <span v-else class="flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold text-[11px]">
              <WifiOff class="w-3.5 h-3.5 text-amber-500 dark:text-amber-400" />
              {{ t('offline_mode') }}
            </span>
          </div>
        </div>

        <!-- Terminal Quick Action Tools -->
        <div class="flex items-center gap-2">
          <!-- ☀️ / 🌙 Theme Switcher Toggle -->
          <button 
            @click="toggleTheme" 
            class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200 font-bold text-[11px] border border-slate-200 dark:border-slate-600 flex items-center gap-1.5 transition-all active:scale-95 shadow-2xs"
            :title="isDarkMode ? 'Switch to Light Theme' : 'Switch to Soft Dark Theme'"
          >
            <Sun v-if="isDarkMode" class="w-3.5 h-3.5 text-amber-500 fill-amber-500/20" />
            <Moon v-else class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" />
            <span class="hidden sm:inline">{{ isDarkMode ? 'Light' : 'Dark' }}</span>
          </button>

          <!-- Store Outlet Selector (Multi-Store Support) -->
          <div v-if="stores.length > 1" class="px-2.5 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-700/80 border border-slate-200 dark:border-slate-600 flex items-center gap-1.5 text-[11px] font-bold text-slate-800 dark:text-slate-100">
            <StoreIcon class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
            <select 
              v-model="selectedStoreId" 
              @change="switchStoreOutlet"
              class="bg-transparent text-slate-900 dark:text-slate-100 font-bold focus:outline-none cursor-pointer"
            >
              <option v-for="s in stores" :key="s.id" :value="s.id" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">
                {{ s.name }}
              </option>
            </select>
          </div>

          <!-- Register Shift Drawer Button -->
          <button 
            @click="openShiftModal"
            class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200 font-bold text-[11px] border border-slate-200 dark:border-slate-600 flex items-center gap-1.5 transition-all"
            title="Register Shift Drawer Management"
          >
            <span class="w-2 h-2 rounded-full" :class="activeShift ? 'bg-emerald-500 dark:bg-emerald-400 animate-pulse' : 'bg-rose-500 dark:bg-rose-400'"></span>
            <span class="hidden sm:inline">{{ activeShift ? `Shift #${activeShift.id}` : t('open_shift') }}</span>
          </button>

          <!-- Terminal Lock Button -->
          <button 
            @click="showLockModal = true"
            class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200 font-bold text-[11px] border border-slate-200 dark:border-slate-600 flex items-center gap-1.5 transition-all"
            title="Lock POS Terminal Workstation"
          >
            <Lock class="w-3.5 h-3.5 text-amber-500 dark:text-amber-400" />
            <span class="hidden sm:inline">Lock Station</span>
          </button>

          <Link 
            href="/hrm/attendance" 
            class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200 font-bold text-[11px] border border-slate-200 dark:border-slate-600 flex items-center gap-1.5 transition-all"
          >
            <Clock class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
            <span class="hidden sm:inline">{{ t('attendance') }}</span>
          </Link>

          <Link 
            href="/products/barcodes" 
            class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200 font-bold text-[11px] border border-slate-200 dark:border-slate-600 flex items-center gap-1.5 transition-all"
          >
            <Barcode class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" />
            <span class="hidden sm:inline">{{ t('barcodes') }}</span>
          </Link>

          <button 
            v-if="offlineQueueCount > 0"
            @click="syncOfflineQueue"
            :disabled="isSyncing"
            class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs shadow-md flex items-center gap-1.5 transition-all"
          >
            <RefreshCw :class="['w-3.5 h-3.5', isSyncing ? 'animate-spin' : '']" />
            <span>Sync ({{ offlineQueueCount }})</span>
          </button>
        </div>
      </div>

      <!-- Sync Notification Toast Banner -->
      <transition name="slide-down">
        <div
          v-if="syncNotification"
          :class="[
            'px-4 py-2.5 text-xs font-bold text-center shrink-0 transition-all',
            syncNotification.type === 'success' ? 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border-b border-emerald-500/30' :
            syncNotification.type === 'queued'  ? 'bg-amber-500/20 text-amber-700 dark:text-amber-300 border-b border-amber-500/30' :
                                                  'bg-rose-500/20 text-rose-700 dark:text-rose-300 border-b border-rose-500/30',
          ]"
        >
          {{ syncNotification.message }}
        </div>
      </transition>

      <!-- Main Layout Body (Split Screen Register) -->
      <div class="flex-1 flex flex-col lg:flex-row overflow-hidden">
        
        <!-- LEFT COLUMN: Cart Register & Order Summary Drawer -->
        <div 
          :class="[
            'w-full lg:w-[480px] xl:w-[540px] border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 flex-col h-full shadow-xl',
            mobileActiveTab === 'cart' ? 'flex' : 'hidden lg:flex'
          ]"
        >
          <!-- Customer & Parked Orders Bar -->
          <div class="p-3.5 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/90 flex items-center justify-between gap-2">
            <div class="flex-1 flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs">
              <User class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
              <select 
                v-model="selectedCustomerId" 
                class="w-full bg-transparent text-slate-900 dark:text-slate-100 font-bold focus:outline-none cursor-pointer"
              >
                <option v-for="c in customersList" :key="c.id" :value="c.id" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">
                  {{ c.name }} {{ c.phone ? `(${c.phone})` : '' }}
                </option>
              </select>
            </div>

            <button 
              @click="showAddCustomerModal = true" 
              class="px-2.5 py-2 rounded-xl bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-xs font-bold text-emerald-600 dark:text-emerald-400 border border-slate-200 dark:border-slate-700 flex items-center gap-1 shrink-0"
              title="Add New Customer (F2)"
            >
              <span>+ New (F2)</span>
            </button>

            <button 
              @click="openParkedOrdersModal" 
              class="px-3.5 py-2 rounded-xl bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-xs font-bold text-amber-600 dark:text-amber-400 border border-slate-200 dark:border-slate-700 flex items-center gap-1.5 shrink-0"
            >
              <Clock class="w-4 h-4 text-amber-500 dark:text-amber-400" />
              <span>Held ({{ parkedOrders.length }})</span>
            </button>
          </div>

          <!-- Cart Line Items List (Scrollable) -->
          <div class="flex-1 overflow-y-auto p-3.5 space-y-2.5 custom-scrollbar">
            <div v-if="cart.length === 0" class="h-full flex flex-col items-center justify-center text-slate-400 dark:text-slate-500 py-16">
              <ShoppingCart class="w-14 h-14 text-slate-300 dark:text-slate-700 mb-3 animate-bounce" />
              <p class="font-extrabold text-sm text-slate-700 dark:text-slate-300">Terminal Cart is Empty</p>
              <p class="text-xs text-slate-500">Scan barcode or tap catalog items to build register order</p>
            </div>

            <div 
              v-for="(item, index) in cart" 
              :key="item.product_id" 
              class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700/80 hover:border-emerald-500/50 transition-all flex flex-col gap-2 shadow-xs"
            >
              <!-- Top Row: Product Title & Price -->
              <div class="flex items-start justify-between gap-2">
                <div>
                  <div class="font-bold text-xs text-slate-900 dark:text-slate-100 font-heading leading-snug">
                    {{ item.name }}
                  </div>
                  <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">
                    SKU: {{ item.sku }} | ৳{{ formatMoney(item.unit_price) }}
                  </div>
                </div>
                <button @click="removeFromCart(index)" class="p-1 rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-500/10 transition-colors">
                  <Trash2 class="w-4 h-4" />
                </button>
              </div>

              <!-- Serial / IMEI Input -->
              <div v-if="item.has_serial" class="flex items-center gap-2 bg-slate-100 dark:bg-slate-900/60 p-2 rounded-xl border border-slate-200 dark:border-slate-700">
                <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 font-mono">S/N:</span>
                <input 
                  type="text" 
                  v-model="item.serial_number" 
                  placeholder="Enter Serial / IMEI #" 
                  class="flex-1 px-2 py-0.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 text-[11px] text-slate-900 dark:text-slate-100 focus:outline-none focus:border-emerald-500 font-mono"
                />
              </div>

              <!-- Line Discount Input -->
              <div class="flex items-center justify-between gap-2 bg-slate-100 dark:bg-slate-900/40 px-2.5 py-1 rounded-xl border border-slate-200 dark:border-slate-700/80 text-[10px]">
                <span class="font-bold text-rose-600 dark:text-rose-400 font-mono">Item Discount (৳):</span>
                <input 
                  type="number" 
                  v-model.number="item.discount" 
                  min="0"
                  step="1"
                  placeholder="0.00" 
                  class="w-24 px-2 py-0.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 text-[11px] text-slate-900 dark:text-slate-100 text-right focus:outline-none focus:border-emerald-500 font-mono"
                />
              </div>

              <!-- Stepper & Line Total -->
              <div class="flex items-center justify-between pt-1 border-t border-slate-200 dark:border-slate-700/60">
                <div class="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-900 p-1 rounded-xl border border-slate-200 dark:border-slate-700">
                  <button @click="updateQty(index, -1)" class="w-6 h-6 rounded-lg bg-white dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-extrabold flex items-center justify-center text-xs">
                    -
                  </button>
                  <span class="px-2 font-mono text-xs font-black text-emerald-600 dark:text-emerald-400 min-w-[20px] text-center">
                    {{ item.quantity }}
                  </span>
                  <button @click="updateQty(index, 1)" class="w-6 h-6 rounded-lg bg-white dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-extrabold flex items-center justify-center text-xs">
                    +
                  </button>
                </div>

                <div class="text-right">
                  <span class="text-xs font-black font-mono text-emerald-600 dark:text-emerald-400">
                    ৳{{ formatMoney(Math.max(0, (item.quantity * item.unit_price) - (item.discount || 0))) }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Order Summary & Checkout Drawer -->
          <div class="p-4 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-3 shrink-0">
            <!-- Quick Cash Tendering Presets -->
            <div>
              <div class="text-[10px] font-extrabold uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1.5 flex items-center justify-between">
                <span>Quick Cash Tendering</span>
                <span class="text-emerald-600 dark:text-emerald-400 font-mono">1-TAP TENDER</span>
              </div>
              <div class="grid grid-cols-6 gap-1.5">
                <button 
                  v-for="amt in [50, 100, 500, 1000, 2000, 5000]" 
                  :key="amt"
                  @click="applyQuickTender(amt)"
                  class="py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-emerald-500 hover:text-slate-950 text-slate-800 dark:text-slate-200 font-mono font-bold text-xs border border-slate-200 dark:border-slate-700 transition-all text-center"
                >
                  ৳{{ amt }}
                </button>
              </div>
            </div>

            <!-- Discount & Tax Inputs -->
            <div class="grid grid-cols-2 gap-3 text-xs">
              <div>
                <label class="block text-[10px] text-slate-500 dark:text-slate-400 font-bold mb-1 uppercase">{{ t('discount') }} (৳)</label>
                <input 
                  type="number" 
                  v-model.number="orderDiscount" 
                  placeholder="0.00" 
                  class="w-full px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100 font-mono text-xs focus:outline-none focus:border-emerald-500"
                />
              </div>
              <div>
                <label class="block text-[10px] text-slate-500 dark:text-slate-400 font-bold mb-1 uppercase">{{ t('tax_vat') }} ({{ taxRate }}%)</label>
                <div class="w-full px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-emerald-600 dark:text-emerald-400 font-mono text-xs font-bold">
                  ৳{{ formatMoney(taxAmount) }}
                </div>
              </div>
            </div>

            <!-- Total Bar -->
            <div class="p-3.5 rounded-2xl bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-slate-800 dark:to-slate-900 border border-emerald-300 dark:border-emerald-500/40 flex items-center justify-between shadow-xs">
              <div>
                <div class="text-[10px] text-emerald-700 dark:text-emerald-400 font-extrabold uppercase tracking-widest">{{ t('grand_total') }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 font-mono">Items: {{ totalCartQty }}</div>
              </div>
              <div class="text-2xl font-black font-heading text-emerald-700 dark:text-emerald-400">
                ৳{{ formatMoney(grandTotal) }}
              </div>
            </div>

            <!-- Primary Action CTAs -->
            <div class="grid grid-cols-3 gap-2">
              <button 
                @click="parkActiveOrder" 
                :disabled="cart.length === 0"
                class="py-3 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-amber-600 dark:text-amber-400 font-bold text-xs border border-slate-200 dark:border-slate-700 flex flex-col items-center justify-center gap-0.5 disabled:opacity-40 transition-all"
              >
                <PauseCircle class="w-4 h-4 text-amber-500 dark:text-amber-400" />
                <span>{{ t('park_order') }} (F7)</span>
              </button>

              <button 
                @click="quickCashCheckout" 
                :disabled="cart.length === 0"
                class="col-span-2 py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-sm shadow-lg shadow-emerald-600/20 flex items-center justify-center gap-2 active:scale-95 disabled:opacity-40 transition-all"
              >
                <Zap class="w-5 h-5 text-white fill-white" />
                <span>{{ t('checkout') }} | ৳{{ formatMoney(grandTotal) }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- RIGHT COLUMN: Product Catalog Grid & Search -->
        <div 
          :class="[
            'flex-1 flex-col h-full bg-slate-100 dark:bg-slate-900',
            mobileActiveTab === 'catalog' ? 'flex' : 'hidden lg:flex'
          ]"
        >
          <!-- Search & Category Filters Bar -->
          <div class="p-4 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-3 shadow-2xs">
            <div class="flex items-center gap-3">
              <div class="flex-1 relative">
                <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                <input 
                  ref="searchInputRef"
                  type="text" 
                  v-model="searchQuery" 
                  @keydown.enter.prevent="handleSearchEnter"
                  :placeholder="t('search_placeholder')" 
                  class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-emerald-500"
                />
              </div>

              <button 
                @click="openCameraScanner" 
                class="px-3.5 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-emerald-600 dark:text-emerald-400 font-bold text-xs border border-slate-200 dark:border-slate-700 flex items-center gap-1.5 shrink-0"
                title="Live Camera & Laser Barcode Scanner"
              >
                <Barcode class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                <span class="hidden sm:inline">Scan Barcode</span>
              </button>
            </div>

            <!-- Categories Pills Bar -->
            <div class="flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar">
              <button 
                @click="selectedCategoryId = null"
                :class="['px-4 py-1.5 rounded-xl text-xs font-extrabold whitespace-nowrap transition-all', selectedCategoryId === null ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/20 font-black' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 shadow-2xs']"
              >
                All Items
              </button>
              <button 
                v-for="cat in categories" 
                :key="cat.id" 
                @click="selectedCategoryId = cat.id"
                :class="['px-4 py-1.5 rounded-xl text-xs font-extrabold whitespace-nowrap transition-all', selectedCategoryId === cat.id ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/20 font-black' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 shadow-2xs']"
              >
                {{ cat.name }}
              </button>
            </div>
          </div>

          <!-- Product Cards Grid -->
          <div class="flex-1 overflow-y-auto p-4 sm:p-6 custom-scrollbar">
            <div v-if="filteredProducts.length === 0" class="h-full flex flex-col items-center justify-center text-slate-400 dark:text-slate-500 py-16">
              <PackageSearch class="w-14 h-14 text-slate-300 dark:text-slate-700 mb-3" />
              <p class="font-extrabold text-sm text-slate-700 dark:text-slate-300">No products found</p>
              <p class="text-xs text-slate-500">Try changing search query or category filter</p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-3 sm:gap-4">
              <div 
                v-for="product in filteredProducts" 
                :key="product.id" 
                @click="addToCart(product)"
                class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200/90 dark:border-slate-700/80 hover:border-emerald-500/80 transition-all cursor-pointer group flex flex-col justify-between shadow-xs hover:-translate-y-0.5 hover:shadow-xl hover:shadow-emerald-500/10"
              >
                <div>
                  <div class="flex items-center justify-between text-[10px] mb-2">
                    <span class="text-emerald-600 dark:text-emerald-400 font-bold truncate max-w-[60%]">
                      {{ product.category?.name || 'General' }}
                    </span>
                    <span :class="['px-2 py-0.5 rounded-full font-bold font-mono', product.current_stock > 5 ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30' : (product.current_stock > 0 ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30' : 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30')]">
                      {{ product.current_stock }} left
                    </span>
                  </div>

                  <h4 class="font-bold text-xs text-slate-900 dark:text-slate-100 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors line-clamp-2 mb-1 font-heading">
                    {{ product.name }}
                  </h4>
                  <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">
                    SKU: {{ product.sku }}
                  </div>
                </div>

                <div class="flex items-center justify-between mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-700/60">
                  <span class="font-black text-sm font-heading text-emerald-700 dark:text-emerald-400">
                    ৳{{ formatMoney(product.selling_price) }}
                  </span>
                  <span class="w-7 h-7 rounded-xl bg-emerald-50 dark:bg-slate-700/60 group-hover:bg-emerald-600 text-emerald-700 dark:text-emerald-300 group-hover:text-white flex items-center justify-center transition-all font-black text-xs border border-emerald-200 dark:border-slate-600 group-hover:border-emerald-600">
                    +
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Modals -->
    <CheckoutModal 
      :show="showCheckoutModal" 
      :subtotal="subtotal" 
      :discountAmount="orderDiscount" 
      :taxAmount="taxAmount" 
      :grandTotal="grandTotal" 
      :presetTender="presetTender"
      :isGuestCustomer="isGuestCustomer"
      :isSubmitting="isSubmitting" 
      @close="showCheckoutModal = false" 
      @confirm="processCheckout" 
    />

    <ReceiptModal 
      :show="showReceiptModal" 
      :receipt="latestReceipt" 
      @close="showReceiptModal = false" 
    />

    <!-- Live Camera & Hardware Barcode Scanner Modal -->
    <div v-if="showCameraScannerModal" class="fixed inset-0 z-50 bg-slate-950/90 backdrop-blur-md flex items-center justify-center p-4">
      <div class="bg-slate-900 border border-slate-700 rounded-3xl p-6 max-w-md w-full space-y-4 shadow-2xl text-slate-100 text-center">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <Camera class="w-5 h-5 text-emerald-400" />
            <h3 class="text-base font-black font-heading">Live Camera Barcode Scanner</h3>
          </div>
          <button @click="closeCameraScanner" class="text-slate-400 hover:text-white font-bold text-lg">✕</button>
        </div>

        <div class="relative bg-slate-950 rounded-2xl overflow-hidden border border-slate-800 aspect-video flex items-center justify-center">
          <video ref="cameraVideoRef" autoplay playsinline class="w-full h-full object-cover"></video>
          
          <!-- Scanner Crosshair Overlay -->
          <div class="absolute inset-0 pointer-events-none flex items-center justify-center">
            <div class="w-48 h-28 border-2 border-emerald-400/80 rounded-xl relative animate-pulse shadow-[0_0_20px_rgba(52,211,153,0.3)]">
              <div class="absolute inset-x-0 top-1/2 h-0.5 bg-rose-500 shadow-[0_0_8px_#f43f5e]"></div>
            </div>
          </div>

          <div v-if="cameraError" class="absolute inset-0 bg-slate-950/90 flex flex-col items-center justify-center p-4 text-xs">
            <p class="text-amber-400 font-bold mb-1">⚠️ Camera Scanner Notice</p>
            <p class="text-slate-400 text-center text-[11px] mb-3">{{ cameraError }}</p>
            <button @click="focusSearchInput" class="px-4 py-2 rounded-xl bg-emerald-500 text-slate-950 font-black text-xs">
              Focus Search Input (F1)
            </button>
          </div>
        </div>

        <div class="space-y-2 text-xs">
          <p class="text-slate-400">Aim camera at barcode line or use USB laser scan gun</p>
          <div class="flex items-center gap-2">
            <input 
              type="text" 
              v-model="manualBarcodeInput" 
              @keydown.enter.prevent="submitManualBarcode"
              placeholder="Or type SKU / Barcode & press Enter" 
              class="flex-1 px-3.5 py-2 rounded-xl bg-slate-800 border border-slate-700 text-slate-100 font-mono text-xs focus:outline-none focus:border-emerald-400"
            />
            <button @click="submitManualBarcode" class="px-3.5 py-2 rounded-xl bg-emerald-500 text-slate-950 font-black text-xs">
              Add Item
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Google Stitch Terminal Lock PIN Modal -->
    <div v-if="showLockModal" class="fixed inset-0 z-50 bg-slate-950/90 backdrop-blur-md flex items-center justify-center p-4">
      <div class="bg-slate-900 border border-slate-700 rounded-3xl p-6 max-w-sm w-full space-y-4 shadow-2xl text-center">
        <div class="w-12 h-12 rounded-2xl bg-amber-500/20 border border-amber-500/40 text-amber-400 flex items-center justify-center mx-auto font-black">
          <Lock class="w-6 h-6" />
        </div>
        <div>
          <h3 class="text-lg font-black text-slate-100 font-heading">Terminal Workstation Locked</h3>
          <p class="text-xs text-slate-400">Enter your 4-digit cashier PIN to resume register session</p>
        </div>

        <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 font-mono text-2xl font-black text-emerald-400 tracking-widest">
          {{ pinEntered.replace(/./g, '•') || 'ENTER PIN' }}
        </div>

        <!-- Touch Keypad -->
        <div class="grid grid-cols-3 gap-2 font-mono text-base font-bold">
          <button v-for="num in [1,2,3,4,5,6,7,8,9]" :key="num" @click="pressPin(num)" class="p-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-100 border border-slate-700 active:scale-95">
            {{ num }}
          </button>
          <button @click="pinEntered = ''" class="p-3 rounded-xl bg-rose-500/20 text-rose-400 border border-rose-500/40 hover:bg-rose-500/30">C</button>
          <button @click="pressPin(0)" class="p-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-100 border border-slate-700 active:scale-95">0</button>
          <button @click="unlockTerminal" class="p-3 rounded-xl bg-emerald-500 text-slate-950 font-black hover:bg-emerald-400">✓</button>
        </div>

        <button @click="showLockModal = false" class="text-xs text-slate-500 hover:text-slate-300 font-bold underline">
          Cancel & Exit Lock
        </button>
      </div>
    </div>

    <!-- Parked / Suspended Orders Modal -->
    <div v-if="showParkedOrdersModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4">
      <div class="bg-slate-900 border border-slate-700 rounded-3xl p-6 max-w-lg w-full space-y-4 shadow-2xl text-slate-100">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <Clock class="w-5 h-5 text-amber-400" />
            <h3 class="text-base font-black font-heading">Suspended / Parked Orders ({{ parkedOrders.length }})</h3>
          </div>
          <button @click="showParkedOrdersModal = false" class="text-slate-400 hover:text-white font-bold text-lg">✕</button>
        </div>

        <div class="max-h-96 overflow-y-auto space-y-3 custom-scrollbar">
          <div v-if="parkedOrders.length === 0" class="text-center py-8 text-slate-500 text-xs">
            No parked orders suspended in register drawer.
          </div>

          <div 
            v-for="order in parkedOrders" 
            :key="order.id" 
            class="p-4 rounded-2xl bg-slate-800 border border-slate-700 space-y-2"
          >
            <div class="flex items-center justify-between text-xs font-bold">
              <span class="text-amber-400 font-mono">{{ order.reference_no }}</span>
              <span class="text-slate-400 text-[10px]">{{ new Date(order.created_at).toLocaleTimeString() }}</span>
            </div>
            <div class="text-xs text-slate-300">
              Customer: <span class="font-bold text-white">{{ order.customer_name || 'Walk-in Customer' }}</span>
            </div>
            <div class="text-[11px] text-slate-400 font-mono">
              Items: {{ order.cart_data?.length || 0 }} products
            </div>
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-700">
              <button 
                @click="discardParkedOrder(order.id)" 
                class="px-3 py-1.5 rounded-xl bg-rose-500/20 text-rose-400 hover:bg-rose-500/30 font-bold text-xs border border-rose-500/40"
              >
                Discard
              </button>
              <button 
                @click="resumeParkedOrder(order)" 
                class="px-4 py-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs shadow-md"
              >
                Resume Cart ➔
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Shift Register Drawer Modal -->
    <div v-if="showShiftModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4">
      <div class="bg-slate-900 border border-slate-700 rounded-3xl p-6 max-w-md w-full space-y-4 shadow-2xl text-slate-100">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full" :class="activeShift ? 'bg-emerald-400' : 'bg-rose-400'"></span>
            <h3 class="text-base font-black font-heading">{{ activeShift ? `Close Shift #${activeShift.id}` : 'Open Register Shift' }}</h3>
          </div>
          <button @click="showShiftModal = false" class="text-slate-400 hover:text-white font-bold text-lg">✕</button>
        </div>

        <div v-if="!activeShift" class="space-y-4">
          <p class="text-xs text-slate-400">Enter starting cash float amount in till to activate register shift</p>
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">Starting Cash Float (৳)</label>
            <input 
              type="number" 
              v-model.number="openingCashInput" 
              placeholder="5000.00" 
              class="w-full px-4 py-2.5 rounded-xl bg-slate-800 border border-slate-700 text-slate-100 font-mono text-sm focus:outline-none focus:border-emerald-400"
            />
          </div>
          <button 
            @click="handleOpenShift" 
            :disabled="isShiftProcessing"
            class="w-full py-3 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs shadow-md"
          >
            {{ isShiftProcessing ? 'Opening...' : 'Start Cashier Register Shift' }}
          </button>
        </div>

        <div v-else class="space-y-4 text-xs">
          <div class="p-3 rounded-2xl bg-slate-800 border border-slate-700 space-y-1">
            <div class="flex justify-between text-slate-400">
              <span>Shift Started At:</span>
              <span class="font-mono text-slate-200">{{ new Date(activeShift.opened_at).toLocaleTimeString() }}</span>
            </div>
            <div class="flex justify-between text-slate-400">
              <span>Opening Float:</span>
              <span class="font-mono text-emerald-400">৳{{ formatMoney(activeShift.opening_cash) }}</span>
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">Physical Cash Count in Drawer (৳)</label>
            <input 
              type="number" 
              v-model.number="closingCashInput" 
              placeholder="0.00" 
              class="w-full px-4 py-2.5 rounded-xl bg-slate-800 border border-slate-700 text-slate-100 font-mono text-sm focus:outline-none focus:border-emerald-400"
            />
          </div>

          <button 
            @click="handleCloseShift" 
            :disabled="isShiftProcessing"
            class="w-full py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs shadow-md"
          >
            {{ isShiftProcessing ? 'Reconciling...' : 'Close Shift & Audit Cash Drawer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Quick Add Customer Modal -->
    <div v-if="showAddCustomerModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4">
      <div class="bg-slate-900 border border-slate-700 rounded-3xl p-6 max-w-md w-full space-y-4 shadow-2xl text-slate-100">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <User class="w-5 h-5 text-emerald-400" />
            <h3 class="text-base font-black font-heading">Register New Customer (F2)</h3>
          </div>
          <button @click="showAddCustomerModal = false" class="text-slate-400 hover:text-white font-bold text-lg">✕</button>
        </div>

        <form @submit.prevent="submitNewCustomer" class="space-y-3 text-xs">
          <div>
            <label class="block font-bold text-slate-300 mb-1">Customer Full Name *</label>
            <input 
              type="text" 
              v-model="newCustomerForm.name" 
              required 
              placeholder="e.g. Tanvir Ahmed" 
              class="w-full px-3.5 py-2 rounded-xl bg-slate-800 border border-slate-700 text-slate-100 focus:outline-none focus:border-emerald-400"
            />
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Phone Number *</label>
            <input 
              type="text" 
              v-model="newCustomerForm.phone" 
              required 
              placeholder="01700000000" 
              class="w-full px-3.5 py-2 rounded-xl bg-slate-800 border border-slate-700 text-slate-100 font-mono focus:outline-none focus:border-emerald-400"
            />
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Email Address</label>
            <input 
              type="email" 
              v-model="newCustomerForm.email" 
              placeholder="customer@example.com" 
              class="w-full px-3.5 py-2 rounded-xl bg-slate-800 border border-slate-700 text-slate-100 focus:outline-none focus:border-emerald-400"
            />
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Delivery / Billing Address</label>
            <textarea 
              v-model="newCustomerForm.address" 
              rows="2" 
              placeholder="House, Road, Area, City" 
              class="w-full px-3.5 py-2 rounded-xl bg-slate-800 border border-slate-700 text-slate-100 focus:outline-none focus:border-emerald-400"
            ></textarea>
          </div>

          <button 
            type="submit" 
            :disabled="isAddingCustomer"
            class="w-full py-3 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs shadow-md mt-2"
          >
            {{ isAddingCustomer ? 'Registering...' : 'Save & Select Customer' }}
          </button>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import { t } from '@/i18n/messages';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CheckoutModal from '@/Components/POS/CheckoutModal.vue';
import ReceiptModal from '@/Components/POS/ReceiptModal.vue';
import { 
  User, 
  Clock, 
  ShoppingCart, 
  Trash2, 
  PauseCircle, 
  Zap, 
  CreditCard, 
  Search, 
  Barcode, 
  Camera,
  PackageSearch, 
  WifiOff, 
  RefreshCw,
  Lock,
  Sun,
  Moon,
  Store as StoreIcon
} from 'lucide-vue-next';

const props = defineProps({
  stores: { type: Array, default: () => [] },
  currentStoreId: { type: Number, default: 1 },
  categories: { type: Array, default: () => [] },
  customers: { type: Array, default: () => [] },
  products: { type: Array, default: () => [] },
  parkedCount: { type: Number, default: 0 },
  isDemoMode: { type: Boolean, default: false },
  initialActiveShift: { type: Object, default: null },
});

const page = usePage();
const mobileActiveTab = ref('catalog');
const selectedStoreId = ref(props.currentStoreId || 1);
const selectedCustomerId = ref(props.customers?.[0]?.id || null);
const selectedCategoryId = ref(null);
const searchQuery = ref('');
const presetTender = ref(0);
const cart = ref([]);
const orderDiscount = ref(0);
const isDarkMode = ref(false);

const initTheme = () => {
  if (typeof window === 'undefined') return;
  const saved = localStorage.getItem('theme');
  if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    isDarkMode.value = true;
    document.documentElement.classList.add('dark');
  } else {
    isDarkMode.value = false;
    document.documentElement.classList.remove('dark');
  }
};

const toggleTheme = () => {
  isDarkMode.value = !isDarkMode.value;
  if (isDarkMode.value) {
    document.documentElement.classList.add('dark');
    localStorage.setItem('theme', 'dark');
  } else {
    document.documentElement.classList.remove('dark');
    localStorage.setItem('theme', 'light');
  }
};

const isGuestCustomer = computed(() => {
  return !selectedCustomerId.value || selectedCustomerId.value === 1 || selectedCustomerId.value === '1';
});

const switchStoreOutlet = () => {
  if (cart.value.length > 0) {
    if (!confirm('Switching store outlet will reset current cart. Continue?')) {
      selectedStoreId.value = props.currentStoreId;
      return;
    }
    cart.value = [];
  }
  router.get('/pos', { store_id: selectedStoreId.value }, { preserveState: false });
};

const currentStore = computed(() => {
  const storeList = Array.isArray(props.stores) ? props.stores : [];
  return storeList.find(s => s.id === selectedStoreId.value) || storeList[0] || null;
});

const taxRate = computed(() => {
  return currentStore.value?.default_tax_rate !== undefined ? Number(currentStore.value.default_tax_rate) : 5.0;
});

const searchInputRef = ref(null);
const showCheckoutModal = ref(false);
const showReceiptModal = ref(false);
const showLockModal = ref(false);
const showParkedOrdersModal = ref(false);
const showShiftModal = ref(false);
const showAddCustomerModal = ref(false);

const showCameraScannerModal = ref(false);
const cameraVideoRef = ref(null);
const cameraError = ref('');
const cameraStream = ref(null);
const manualBarcodeInput = ref('');
let barcodeScanAnimFrame = null;

const pinEntered = ref('');
const latestReceipt = ref(null);
const isSubmitting = ref(false);

const activeShift = ref(props.initialActiveShift || null);
const openingCashInput = ref(5000);
const closingCashInput = ref(0);
const isShiftProcessing = ref(false);

const localCustomers = ref([]);
const newCustomerForm = ref({ name: '', phone: '', email: '', address: '' });
const isAddingCustomer = ref(false);

const isOnline = ref(navigator.onLine);
const offlineQueue = ref([]);
const isSyncing = ref(false);
const swRegistration = ref(null);
const syncNotification = ref(null); // { type: 'success'|'error', message: string }
// Products from IndexedDB when page is loaded offline (props.products will be empty)
const cachedProducts = ref([]);

const parkedOrders = ref([]);

const customersList = computed(() => {
  const safePropCustomers = Array.isArray(props.customers) ? props.customers : [];
  const safeLocalCustomers = Array.isArray(localCustomers.value) ? localCustomers.value : [];
  const combined = [...safePropCustomers, ...safeLocalCustomers];
  return combined.length ? combined : [{ id: 1, name: 'Walk-in Customer', phone: '' }];
});

// Use live Inertia props when online, fall back to IndexedDB cache when offline
const allProducts = computed(() => {
  return (Array.isArray(props.products) && props.products.length > 0) ? props.products : (cachedProducts.value || []);
});

const filteredProducts = computed(() => {
  let list = allProducts.value;

  if (selectedCategoryId.value) {
    list = list.filter(p => p.category_id === selectedCategoryId.value);
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim();
    list = list.filter(p =>
      p.name.toLowerCase().includes(q) ||
      p.sku.toLowerCase().includes(q) ||
      (p.barcode && p.barcode.toLowerCase().includes(q))
    );
  }

  return list;
});

const subtotal = computed(() => {
  return cart.value.reduce((sum, item) => sum + (item.quantity * item.unit_price), 0);
});

const taxAmount = computed(() => {
  const taxable = Math.max(0, subtotal.value - orderDiscount.value);
  return (taxable * taxRate.value) / 100;
});

const grandTotal = computed(() => {
  return Math.max(0, subtotal.value - orderDiscount.value + taxAmount.value);
});

const totalCartQty = computed(() => {
  return cart.value.reduce((sum, item) => sum + item.quantity, 0);
});

const offlineQueueCount = computed(() => offlineQueue.value.length);

const formatMoney = (val) => {
  return Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const playScannerBeep = () => {
  try {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) return;
    const ctx = new AudioCtx();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'sine';
    osc.frequency.setValueAtTime(1200, ctx.currentTime);
    gain.gain.setValueAtTime(0.15, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.08);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + 0.08);
  } catch (_) {}
};

const addToCart = (product) => {
  const allowNegative = currentStore.value?.allow_negative_stock ?? false;
  const stockAvailable = product.current_stock ?? product.stock ?? 999;
  const existing = cart.value.find(i => i.product_id === product.id);
  const currentCartQty = existing ? existing.quantity : 0;

  if (!allowNegative && (currentCartQty + 1 > stockAvailable)) {
    syncNotification.value = {
      type: 'error',
      message: `⚠️ Stock limit reached for "${product.name}"! Available: ${stockAvailable}`
    };
    setTimeout(() => { syncNotification.value = null; }, 4000);
    return;
  }

  if (existing) {
    existing.quantity += 1;
  } else {
    cart.value.push({
      product_id: product.id,
      name: product.name,
      sku: product.sku,
      unit_price: Number(product.selling_price),
      quantity: 1,
      discount: 0,
      has_serial: !!product.has_serial,
      serial_number: '',
    });
  }

  playScannerBeep();
};

const updateQty = (index, delta) => {
  const item = cart.value[index];
  if (!item) return;

  if (delta > 0) {
    const allowNegative = currentStore.value?.allow_negative_stock ?? false;
    const product = allProducts.value.find(p => p.id === item.product_id);
    const stockAvailable = product ? (product.current_stock ?? 999) : 999;
    if (!allowNegative && (item.quantity + delta > stockAvailable)) {
      syncNotification.value = {
        type: 'error',
        message: `⚠️ Stock limit reached for "${item.name}"! Max available: ${stockAvailable}`
      };
      setTimeout(() => { syncNotification.value = null; }, 4000);
      return;
    }
  }

  item.quantity += delta;
  if (item.quantity <= 0) {
    cart.value.splice(index, 1);
  }
};

const removeFromCart = (index) => {
  cart.value.splice(index, 1);
};

const applyQuickTender = (amt) => {
  if (cart.value.length === 0) return;
  presetTender.value = amt;
  openCheckoutModal();
};

const pressPin = (num) => {
  if (pinEntered.value.length < 4) {
    pinEntered.value += String(num);
  }
};

const unlockTerminal = async () => {
  if (pinEntered.value.length < 4) {
      alert('PIN must be at least 4 digits.');
      return;
  }
  
  try {
      const res = await window.axios.post(route('pos.verify-pin'), { pin: pinEntered.value });
      if (res.data.success) {
          showLockModal.value = false;
          pinEntered.value = '';
      } else {
          alert('Incorrect Cashier PIN.');
          pinEntered.value = '';
      }
  } catch (e) {
      alert('Network error verifying PIN.');
      pinEntered.value = '';
  }
};

const quickCashCheckout = () => {
  if (cart.value.length === 0) return;
  processCheckout({
    payment_method: 'cash',
    paid_amount: grandTotal.value,
    change_return: 0,
    payments: [{ method: 'cash', amount: grandTotal.value }]
  });
};

const openCheckoutModal = () => {
  if (cart.value.length === 0) return;
  showCheckoutModal.value = true;
};

const processCheckout = async (paymentDetails) => {
  isSubmitting.value = true;

  const payload = {
    store_id: selectedStoreId.value,
    customer_id: selectedCustomerId.value,
    items: cart.value,
    subtotal: subtotal.value,
    discount_amount: orderDiscount.value,
    tax_amount: taxAmount.value,
    grand_total: grandTotal.value,
    paid_amount: paymentDetails.paid_amount,
    change_return: paymentDetails.change_return || 0,
    payment_method: paymentDetails.payment_method,
    payments: paymentDetails.payments || [],
  };

  if (isOnline.value) {
    router.post('/pos/checkout', payload, {
      preserveScroll: true,
      onSuccess: (page) => {
        isSubmitting.value = false;
        showCheckoutModal.value = false;
        if (page.props.flash?.receipt) {
          latestReceipt.value = page.props.flash.receipt;
          showReceiptModal.value = true;
        }
        cart.value = [];
        orderDiscount.value = 0;
      },
      onError: () => {
        isSubmitting.value = false;
      }
    });
  } else {
    // --- Save to IndexedDB offline queue (survives tab close) ---
    const offlineId = 'OFF-' + Date.now();
    const offlineOrder = {
      ...payload,
      id: offlineId,
      _csrf: getCsrfToken(),
      _queued_at: new Date().toISOString(),
    };

    try {
      await idbPut('offline_orders', offlineOrder);
      offlineQueue.value.push(offlineOrder);
    } catch (e) {
      // Fallback to localStorage if IndexedDB fails
      offlineQueue.value.push(offlineOrder);
      localStorage.setItem('iot_offline_orders', JSON.stringify(offlineQueue.value));
    }

    // Register Background Sync so order syncs even if tab closes
    if (swRegistration.value && 'sync' in swRegistration.value) {
      try {
        await swRegistration.value.sync.register('iot-pos-offline-sync');
        console.log('[POS] Background Sync registered for offline order.');
      } catch (e) {
        console.warn('[POS] Background Sync registration failed:', e);
      }
    }

    isSubmitting.value = false;
    showCheckoutModal.value = false;
    showSyncNotification('queued', `Order queued offline (${offlineQueue.value.length} pending). Will auto-sync when connected.`);
    cart.value = [];
    orderDiscount.value = 0;
  }
};

const parkActiveOrder = () => {
  if (cart.value.length === 0) return;
  const cust = customersList.value.find(c => c.id === selectedCustomerId.value);
  const custName = cust ? cust.name : 'Walk-in Customer';
  router.post('/pos/park', {
    store_id: selectedStoreId.value,
    customer_name: custName,
    cart_data: cart.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      cart.value = [];
      fetchParkedOrders();
    }
  });
};

const fetchParkedOrders = () => {
  fetch(`/pos/parked-orders?store_id=${selectedStoreId.value}`)
    .then(r => r.json())
    .then(data => { parkedOrders.value = data; })
    .catch(() => {});
};

const openParkedOrdersModal = () => {
  fetchParkedOrders();
  showParkedOrdersModal.value = true;
};

const resumeParkedOrder = (order) => {
  if (order.cart_data && Array.isArray(order.cart_data)) {
    cart.value = order.cart_data;
  }
  discardParkedOrder(order.id);
  showParkedOrdersModal.value = false;
};

const discardParkedOrder = (id) => {
  router.delete(`/pos/parked/${id}`, {
    preserveScroll: true,
    onSuccess: () => {
      fetchParkedOrders();
    }
  });
};

const fetchShiftStatus = () => {
  fetch(`/pos/shift/status?store_id=${selectedStoreId.value}`)
    .then(r => r.json())
    .then(data => {
      activeShift.value = data.shift || null;
    })
    .catch(() => {});
};

const openShiftModal = () => {
  fetchShiftStatus();
  showShiftModal.value = true;
};

const handleOpenShift = async () => {
  isShiftProcessing.value = true;
  try {
    const rawCash = parseFloat(openingCashInput.value);
    const openingCash = !isNaN(rawCash) && rawCash >= 0 ? rawCash : 0;
    const res = await window.axios.post('/pos/shift/open', {
      store_id: selectedStoreId.value || props.currentStoreId || 1,
      opening_cash: openingCash,
      notes: 'Opened via POS Counter',
    });
    if (res.data?.success || res.data?.shift) {
      activeShift.value = res.data.shift;
      showShiftModal.value = false;
      showSyncNotification('success', '✅ Register shift activated successfully!');
    } else {
      alert(res.data?.message || 'Failed to open shift.');
    }
  } catch (e) {
    const errorMsg = e.response?.data?.message || e.response?.data?.errors?.opening_cash?.[0] || e.response?.data?.errors?.store_id?.[0] || 'Failed to open shift. Please check store selection and float amount.';
    alert(errorMsg);
  } finally {
    isShiftProcessing.value = false;
  }
};

const handleCloseShift = async () => {
  if (!activeShift.value) return;
  isShiftProcessing.value = true;
  try {
    const rawCash = parseFloat(closingCashInput.value);
    const closingCash = !isNaN(rawCash) && rawCash >= 0 ? rawCash : 0;
    const res = await window.axios.post('/pos/shift/close', {
      shift_id: activeShift.value.id,
      closing_cash_counted: closingCash,
    });
    if (res.data?.success) {
      activeShift.value = null;
      showShiftModal.value = false;
      showSyncNotification('success', '✅ Register shift closed & cash drawer reconciled!');
    } else {
      alert(res.data?.message || 'Failed to close shift.');
    }
  } catch (e) {
    const errorMsg = e.response?.data?.message || e.response?.data?.errors?.closing_cash_counted?.[0] || 'Failed to close shift.';
    alert(errorMsg);
  } finally {
    isShiftProcessing.value = false;
  }
};

const submitNewCustomer = async () => {
  if (!newCustomerForm.value.name?.trim() || !newCustomerForm.value.phone?.trim()) {
    alert('Customer name and phone number are required.');
    return;
  }
  isAddingCustomer.value = true;
  try {
    const payload = {
      name: newCustomerForm.value.name.trim(),
      phone: newCustomerForm.value.phone.trim(),
      email: newCustomerForm.value.email?.trim() || null,
      address: newCustomerForm.value.address?.trim() || null,
    };
    const res = await window.axios.post('/customers', payload);
    if (res.data && res.data.customer) {
      const cust = res.data.customer;
      const existsIndex = localCustomers.value.findIndex(c => c.id === cust.id);
      if (existsIndex === -1) {
        localCustomers.value.push(cust);
      }
      selectedCustomerId.value = cust.id;
      newCustomerForm.value = { name: '', phone: '', email: '', address: '' };
      showAddCustomerModal.value = false;
      showSyncNotification('success', `✅ Customer "${cust.name}" selected!`);
    }
  } catch (e) {
    const errorMsg = e.response?.data?.message || e.response?.data?.errors?.phone?.[0] || e.response?.data?.errors?.email?.[0] || e.response?.data?.errors?.name?.[0] || 'Failed to add customer.';
    alert(errorMsg);
  } finally {
    isAddingCustomer.value = false;
  }
};

const focusSearchInput = () => {
  closeCameraScanner();
  setTimeout(() => {
    searchInputRef.value?.focus();
  }, 100);
};

const openCameraScanner = async () => {
  showCameraScannerModal.value = true;
  cameraError.value = '';
  manualBarcodeInput.value = '';

  try {
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } }
      });
      cameraStream.value = stream;
      if (cameraVideoRef.value) {
        cameraVideoRef.value.srcObject = stream;
      }
      startBarcodeDetectorLoop();
    } else {
      cameraError.value = 'Camera API not available. Use hardware laser scanner [F1].';
    }
  } catch (err) {
    cameraError.value = 'Camera permission denied or not detected. Use hardware laser scanner [F1].';
  }
};

const closeCameraScanner = () => {
  if (cameraStream.value) {
    cameraStream.value.getTracks().forEach(t => t.stop());
    cameraStream.value = null;
  }
  if (barcodeScanAnimFrame) {
    cancelAnimationFrame(barcodeScanAnimFrame);
    barcodeScanAnimFrame = null;
  }
  showCameraScannerModal.value = false;
};

const startBarcodeDetectorLoop = () => {
  if ('BarcodeDetector' in window) {
    const barcodeDetector = new window.BarcodeDetector({
      formats: ['qr_code', 'ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'upc_e']
    });

    const detectFrame = async () => {
      if (!showCameraScannerModal.value || !cameraVideoRef.value) return;
      try {
        const barcodes = await barcodeDetector.detect(cameraVideoRef.value);
        if (barcodes.length > 0) {
          const rawCode = barcodes[0].rawValue.trim().toLowerCase();
          const match = allProducts.value.find(p =>
            p.sku?.toLowerCase() === rawCode ||
            p.barcode?.toLowerCase() === rawCode
          );
          if (match) {
            addToCart(match);
            closeCameraScanner();
            return;
          }
        }
      } catch (e) {}
      if (showCameraScannerModal.value) {
        barcodeScanAnimFrame = requestAnimationFrame(detectFrame);
      }
    };
    detectFrame();
  }
};

const submitManualBarcode = () => {
  const code = manualBarcodeInput.value.trim().toLowerCase();
  if (!code) return;

  const match = allProducts.value.find(p =>
    p.sku?.toLowerCase() === code ||
    p.barcode?.toLowerCase() === code
  );

  if (match) {
    addToCart(match);
    manualBarcodeInput.value = '';
    closeCameraScanner();
  } else {
    alert(`No product found matching SKU/Barcode "${code}"`);
  }
};

const handleSearchEnter = () => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return;

  const match = allProducts.value.find(p => 
    p.sku?.toLowerCase() === q || 
    p.barcode?.toLowerCase() === q
  );

  if (match) {
    addToCart(match);
    searchQuery.value = '';
  } else if (filteredProducts.value.length === 1) {
    addToCart(filteredProducts.value[0]);
    searchQuery.value = '';
  }
};

let barcodeBuffer = '';
let lastKeyTime = 0;

const handleGlobalKeyDown = (e) => {
  if (e.key === 'F1') {
    e.preventDefault();
    searchInputRef.value?.focus();
    return;
  }

  if (e.key === 'F2') {
    e.preventDefault();
    showAddCustomerModal.value = true;
    return;
  }

  if (e.key === 'F4') {
    e.preventDefault();
    openCheckoutModal();
    return;
  }

  if (e.key === 'F7' || e.key === 'F8') {
    e.preventDefault();
    parkActiveOrder();
    return;
  }

  if (e.key === 'Escape') {
    showCheckoutModal.value = false;
    showReceiptModal.value = false;
    showLockModal.value = false;
    showParkedOrdersModal.value = false;
    showShiftModal.value = false;
    showAddCustomerModal.value = false;
    closeCameraScanner();
    return;
  }

  if (showCheckoutModal.value || showLockModal.value || showReceiptModal.value || showParkedOrdersModal.value || showShiftModal.value || showAddCustomerModal.value || showCameraScannerModal.value) return;

  const activeTag = document.activeElement?.tagName;
  const isInputTarget = ['INPUT', 'TEXTAREA', 'SELECT'].includes(activeTag);

  const currentTime = Date.now();

  if (e.key === 'Enter') {
    if (barcodeBuffer.length >= 3) {
      const scannedCode = barcodeBuffer.trim().toLowerCase();
      const match = allProducts.value.find(p =>
        p.sku?.toLowerCase() === scannedCode ||
        p.barcode?.toLowerCase() === scannedCode
      );

      if (match) {
        addToCart(match);
        barcodeBuffer = '';
        if (isInputTarget) searchQuery.value = '';
        e.preventDefault();
        return;
      }
    }
    barcodeBuffer = '';
    return;
  }

  if (currentTime - lastKeyTime > 80) {
    barcodeBuffer = '';
  }

  if (e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
    barcodeBuffer += e.key;
    lastKeyTime = currentTime;
  }
};

// =====================================================================
// IndexedDB Helpers (in-page, mirrors the SW helpers)
// =====================================================================
const DB_NAME = 'IotPosDB';
const DB_VERSION = 2;
let _idb = null;

function openIDB() {
  if (_idb) return Promise.resolve(_idb);
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onerror = () => reject(req.error);
    req.onsuccess = () => { _idb = req.result; resolve(_idb); };
    req.onupgradeneeded = (e) => {
      const db = e.target.result;
      if (!db.objectStoreNames.contains('offline_orders'))
        db.createObjectStore('offline_orders', { keyPath: 'id' });
      if (!db.objectStoreNames.contains('products_cache'))
        db.createObjectStore('products_cache', { keyPath: 'id' });
      if (!db.objectStoreNames.contains('app_config'))
        db.createObjectStore('app_config', { keyPath: 'key' });
    };
  });
}

async function idbPut(storeName, item) {
  const db = await openIDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(storeName, 'readwrite');
    const req = tx.objectStore(storeName).put(item);
    req.onsuccess = () => resolve();
    req.onerror = () => reject(req.error);
  });
}

async function idbGetAll(storeName) {
  const db = await openIDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(storeName, 'readonly');
    const req = tx.objectStore(storeName).getAll();
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

async function idbDelete(storeName, key) {
  const db = await openIDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(storeName, 'readwrite');
    const req = tx.objectStore(storeName).delete(key);
    req.onsuccess = () => resolve();
    req.onerror = () => reject(req.error);
  });
}

async function idbClear(storeName) {
  const db = await openIDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(storeName, 'readwrite');
    const req = tx.objectStore(storeName).clear();
    req.onsuccess = () => resolve();
    req.onerror = () => reject(req.error);
  });
}

// =====================================================================
// Utilities
// =====================================================================
const getCsrfToken = () =>
  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const showSyncNotification = (type, message) => {
  syncNotification.value = { type, message };
  setTimeout(() => { syncNotification.value = null; }, 6000);
};

// =====================================================================
// Cache product catalog to IndexedDB (called when online & products load)
// =====================================================================
const cacheProductsToDB = async (products) => {
  if (!products || products.length === 0) return;
  try {
    await idbClear('products_cache');
    for (const product of products) {
      await idbPut('products_cache', product);
    }
    await idbPut('app_config', { key: 'products_cached_at', value: new Date().toISOString() });
    console.log(`[POS] Cached ${products.length} products to IndexedDB for offline use.`);
  } catch (e) {
    console.warn('[POS] Failed to cache products to IndexedDB:', e);
  }
};

// =====================================================================
// Load products from IndexedDB when offline
// =====================================================================
const loadCachedProducts = async () => {
  try {
    const products = await idbGetAll('products_cache');
    if (products && products.length > 0) {
      cachedProducts.value = products;
      console.log(`[POS] Loaded ${products.length} products from IndexedDB cache (offline mode).`);
    }
  } catch (e) {
    console.warn('[POS] Could not load cached products from IndexedDB:', e);
  }
};

// =====================================================================
// Load offline orders queue from IndexedDB on page mount
// =====================================================================
const loadOfflineQueue = async () => {
  try {
    const orders = await idbGetAll('offline_orders');
    if (orders && orders.length > 0) {
      offlineQueue.value = orders;
      console.log(`[POS] Loaded ${orders.length} pending offline order(s) from IndexedDB.`);
    }
  } catch (e) {
    // Fallback to localStorage
    const saved = localStorage.getItem('iot_offline_orders');
    if (saved) {
      try { offlineQueue.value = JSON.parse(saved); } catch {}
    }
  }
};

// =====================================================================
// Manual sync: push offline queue to server
// =====================================================================
const syncOfflineQueue = async () => {
  if (offlineQueue.value.length === 0 || isSyncing.value) return;
  isSyncing.value = true;

  const token = getCsrfToken();
  const remaining = [];
  let syncedCount = 0;

  for (const item of [...offlineQueue.value]) {
    try {
      // Strip internal fields before sending
      const { id: offlineId, _csrf, _queued_at, ...payload } = item;

      const response = await fetch('/pos/checkout', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': token || _csrf || '',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
      });

      const contentType = response.headers.get('content-type') || '';
      if (response.ok && contentType.includes('application/json')) {
        const resData = await response.json();
        if (resData && resData.success) {
          await idbDelete('offline_orders', offlineId);
          syncedCount++;
        } else {
          console.warn(`[POS] Server rejected order ${offlineId}:`, resData);
          remaining.push(item);
        }
      } else {
        console.warn(`[POS] Order ${offlineId} rejected: HTTP ${response.status} or non-JSON response`);
        remaining.push(item);
      }
    } catch (networkErr) {
      console.warn('[POS] Network error during sync, order kept in queue:', networkErr);
      remaining.push(item);
    }
  }

  offlineQueue.value = remaining;
  // Keep localStorage as backup
  if (remaining.length === 0) {
    localStorage.removeItem('iot_offline_orders');
  } else {
    localStorage.setItem('iot_offline_orders', JSON.stringify(remaining));
  }

  isSyncing.value = false;

  if (syncedCount > 0) {
    showSyncNotification('success', `✅ Successfully synced ${syncedCount} offline order(s) to the cloud!`);
    router.reload({ only: ['products', 'parkedCount'] });
  } else if (remaining.length > 0) {
    showSyncNotification('error', `⚠️ ${remaining.length} order(s) still pending — server may be unreachable.`);
  }
};

// =====================================================================
// Service Worker Registration
// =====================================================================
const registerServiceWorker = async () => {
  if (!('serviceWorker' in navigator)) {
    console.warn('[POS] Service Workers not supported in this browser.');
    return;
  }
  try {
    const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
    swRegistration.value = reg;
    console.log('[POS] Service Worker registered:', reg.scope);

    // Listen for SW messages (Background Sync completed, order synced etc.)
    navigator.serviceWorker.addEventListener('message', (event) => {
      const { type, syncedCount, failedCount, invoiceNo } = event.data || {};

      if (type === 'ORDER_SYNCED') {
        // Remove this specific order from the in-memory queue
        offlineQueue.value = offlineQueue.value.filter(o => o.id !== event.data.orderId);
        console.log(`[POS] SW synced order ${event.data.orderId} → ${invoiceNo}`);
      }

      if (type === 'SYNC_COMPLETE') {
        if (syncedCount > 0) {
          showSyncNotification('success', `✅ Background sync: ${syncedCount} offline order(s) synced to cloud!`);
          router.reload({ only: ['products', 'parkedCount'] });
        }
        if (failedCount > 0) {
          showSyncNotification('error', `⚠️ ${failedCount} order(s) failed background sync and are retrying.`);
        }
      }
    });

    // Push CSRF token to app_config so the Service Worker can use it for background sync
    await idbPut('app_config', { key: 'csrf_token', value: getCsrfToken() });
  } catch (e) {
    console.error('[POS] Service Worker registration failed:', e);
  }
};

onMounted(async () => {
  // 0. Initialize theme mode from localStorage
  initTheme();

  // 1. Register Service Worker
  await registerServiceWorker();

  // 2. Load any pending offline orders from IndexedDB
  await loadOfflineQueue();

  // 3. If online: cache product catalog to IndexedDB for future offline use
  if (navigator.onLine && props.products && props.products.length > 0) {
    cacheProductsToDB(props.products);
  }

  // 4. If offline on mount: load products from IndexedDB cache
  if (!navigator.onLine) {
    await loadCachedProducts();
  }

  // 5. Network event listeners
  window.addEventListener('online', async () => {
    isOnline.value = true;
    // Auto-trigger sync when connection restores
    await syncOfflineQueue();
    // Also trigger SW Background Sync if registration is available
    if (swRegistration.value && 'sync' in swRegistration.value) {
      try { await swRegistration.value.sync.register('iot-pos-offline-sync'); } catch (_) {}
    }
  });
  window.addEventListener('offline', () => {
    isOnline.value = false;
  });

  // 6. Fetch parked orders
  fetchParkedOrders();

  // 7. Global hardware barcode scanner listener
  window.addEventListener('keydown', handleGlobalKeyDown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeyDown);
});

// Re-cache products when they update (e.g. after a page reload)
watch(() => props.products, (newProducts) => {
  if (newProducts && newProducts.length > 0 && navigator.onLine) {
    cacheProductsToDB(newProducts);
  }
});
</script>
