<template>
  <!-- Full Screen Container (Dual Light & Dark Theme) -->
  <div class="min-h-screen bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex font-sans antialiased selection:bg-emerald-500 selection:text-white transition-colors duration-200">
    
    <!-- Left Theme-Adaptive Sidebar -->
    <aside 
      v-if="isSidebarLayout"
      :class="[
        'bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 flex flex-col transition-all duration-300 z-40 fixed lg:static inset-y-0 left-0 border-r border-slate-200 dark:border-slate-800/80 shadow-xs',
        sidebarCollapsed ? 'w-20' : 'w-72',
        mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
      ]"
    >
      <!-- Sidebar Header / Branding -->
      <div class="h-16 px-4 flex items-center justify-between border-b border-slate-200 dark:border-slate-800 shrink-0 bg-white dark:bg-slate-900">
        <Link :href="route('landing')" class="flex items-center gap-3 overflow-hidden">
          <ApplicationLogo :show-text="!sidebarCollapsed" :src="$page.props.auth?.user?.tenant?.logo_url" />
        </Link>

        <button 
          @click="sidebarCollapsed = !sidebarCollapsed"
          class="hidden lg:flex p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors border border-slate-200 dark:border-slate-700"
          :title="sidebarCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar'"
        >
          <ChevronLeft v-if="!sidebarCollapsed" class="w-4 h-4" />
          <ChevronRight v-else class="w-4 h-4" />
        </button>

        <button 
          @click="mobileSidebarOpen = false"
          class="lg:hidden p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- Current Role & Security Banner in Sidebar -->
      <div v-if="!sidebarCollapsed" class="px-4 py-3 bg-slate-50 dark:bg-slate-950/60 border-b border-slate-200 dark:border-slate-800">
        <div class="flex items-center gap-2">
          <ShieldCheck class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
          <span class="text-xs font-extrabold text-slate-800 dark:text-slate-200 truncate">{{ roleLabel }}</span>
        </div>
        <div class="flex items-center gap-1.5 mt-1 text-[10px] text-slate-600 dark:text-slate-400 font-mono font-bold">
          <Lock class="w-3 h-3 text-emerald-600 dark:text-emerald-400 shrink-0" />
          <span>256-BIT SSL ENCRYPTED</span>
        </div>
      </div>

      <!-- Sidebar Navigation Link Groups (Strictly Role-Specific & Tailored) -->
      <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6 custom-scrollbar">

        <!-- 👑 SUPER ADMIN SAAS PORTAL MENU -->
        <div v-if="userRole === 'super_admin'">
          <div v-if="!sidebarCollapsed" class="px-3 mb-2 text-[10px] font-extrabold uppercase tracking-widest text-rose-600">
            SaaS Platform Governance
          </div>
          <div class="space-y-1">
            <Link 
              href="/super-admin/dashboard"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'SuperAdmin/Dashboard' ? 'bg-rose-50 text-rose-800 border-rose-300 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              ]"
            >
              <Crown class="w-4 h-4 text-rose-600 shrink-0" />
              <span v-if="!sidebarCollapsed">Command Center</span>
            </Link>
            <Link 
              href="/super-admin/stores"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'SuperAdmin/Stores' ? 'bg-rose-50 text-rose-800 border-rose-300 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              ]"
            >
              <Store class="w-4 h-4 text-rose-600 shrink-0" />
              <span v-if="!sidebarCollapsed">Global Store Outlets</span>
            </Link>
            <Link 
              href="/super-admin/users"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'SuperAdmin/Users' ? 'bg-rose-50 text-rose-800 border-rose-300 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              ]"
            >
              <UserCheck class="w-4 h-4 text-rose-600 shrink-0" />
              <span v-if="!sidebarCollapsed">Platform Users & Access</span>
            </Link>
            <Link 
              href="/super-admin/transactions"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'SuperAdmin/Transactions' ? 'bg-rose-50 text-rose-800 border-rose-300 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              ]"
            >
              <CreditCard class="w-4 h-4 text-rose-600 shrink-0" />
              <span v-if="!sidebarCollapsed">Subscription Payment Ledger</span>
            </Link>
            <Link 
              href="/super-admin/plans"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'SuperAdmin/Plans' ? 'bg-rose-50 text-rose-800 border-rose-300 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              ]"
            >
              <Zap class="w-4 h-4 text-rose-600 shrink-0" />
              <span v-if="!sidebarCollapsed">SaaS Pricing Plans</span>
            </Link>
            <Link 
              href="/super-admin/analytics"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'SuperAdmin/Analytics' ? 'bg-rose-50 text-rose-800 border-rose-300 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              ]"
            >
              <BarChart3 class="w-4 h-4 text-rose-600 shrink-0" />
              <span v-if="!sidebarCollapsed">Platform Analytics</span>
            </Link>
            <Link 
              href="/super-admin/audit-logs"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'SuperAdmin/AuditLogs' ? 'bg-rose-50 text-rose-800 border-rose-300 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              ]"
            >
              <ShieldCheck class="w-4 h-4 text-rose-600 shrink-0" />
              <span v-if="!sidebarCollapsed">Security Audit Trail</span>
            </Link>
            <Link 
              href="/super-admin/recycle-bin"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'SuperAdmin/RecycleBin' ? 'bg-rose-50 text-rose-800 border-rose-300 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              ]"
            >
              <Trash2 class="w-4 h-4 text-rose-600 shrink-0" />
              <span v-if="!sidebarCollapsed">SaaS Recycle Bin</span>
            </Link>
            <Link 
              href="/super-admin/system-health"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'SuperAdmin/SystemHealth' ? 'bg-rose-50 text-rose-800 border-rose-300 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              ]"
            >
              <Activity class="w-4 h-4 text-rose-600 shrink-0" />
              <span v-if="!sidebarCollapsed">System Telemetry</span>
            </Link>
            <Link 
              href="/super-admin/announcements"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'SuperAdmin/Announcements' ? 'bg-rose-50 text-rose-800 border-rose-300 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              ]"
            >
              <Megaphone class="w-4 h-4 text-rose-600 shrink-0" />
              <span v-if="!sidebarCollapsed">Announcements</span>
            </Link>
            <Link 
              href="/super-admin/settings"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'SuperAdmin/Settings' ? 'bg-rose-50 text-rose-800 border-rose-300 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              ]"
            >
              <Settings class="w-4 h-4 text-rose-600 shrink-0" />
              <span v-if="!sidebarCollapsed">Master Settings</span>
            </Link>
          </div>
        </div>


        <!-- 🏢 MERCHANT HQ PORTAL MENU -->
        <div v-if="userRole === 'merchant'">
          <div v-if="!sidebarCollapsed" class="px-3 mb-2 text-[10px] font-extrabold uppercase tracking-widest text-indigo-600 dark:text-indigo-400">
            Merchant HQ & Chain
          </div>
          <div class="space-y-1">
            <Link 
              href="/merchant/dashboard"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'Merchant/Dashboard' ? 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-800 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100'
              ]"
            >
              <Building2 class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
              <span v-if="!sidebarCollapsed">Executive BI Overview</span>
            </Link>
            <Link 
              href="/merchant/stores"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'Merchant/Stores' ? 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-800 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100'
              ]"
            >
              <Store class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
              <span v-if="!sidebarCollapsed">Store Outlets Manager</span>
            </Link>
            <Link 
              href="/merchant/users"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'Merchant/Users' ? 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-800 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100'
              ]"
            >
              <UserCheck class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
              <span v-if="!sidebarCollapsed">Staff & Role Access</span>
            </Link>
            <Link 
              href="/merchant/customers"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'Merchant/Customers' ? 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-800 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100'
              ]"
            >
              <Users class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
              <span v-if="!sidebarCollapsed">Customer CRM & Loyalty</span>
            </Link>
            <Link 
              href="/merchant/orders"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'Merchant/Orders' ? 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-800 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100'
              ]"
            >
              <Receipt class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
              <span v-if="!sidebarCollapsed">Chain Sales Orders</span>
            </Link>
            <Link 
              href="/products"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'Products/Index' ? 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-800 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100'
              ]"
            >
              <Package class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
              <span v-if="!sidebarCollapsed">Products Catalog</span>
            </Link>
            <Link 
              href="/products/barcodes"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'Products/Barcodes' ? 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-800 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100'
              ]"
            >
              <Barcode class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
              <span v-if="!sidebarCollapsed">Barcode Label Generator</span>
            </Link>
            <Link 
              href="/inventory/adjustments"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'Inventory/Adjustments' ? 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-800 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100'
              ]"
            >
              <Boxes class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
              <span v-if="!sidebarCollapsed">Stock Adjustments</span>
            </Link>
            <Link 
              href="/merchant/suppliers"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'Merchant/Suppliers' ? 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-800 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100'
              ]"
            >
              <Truck class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
              <span v-if="!sidebarCollapsed">Supplier Directory</span>
            </Link>
            <Link 
              href="/merchant/purchases"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'Merchant/Purchases' ? 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-800 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100'
              ]"
            >
              <ShoppingBag class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
              <span v-if="!sidebarCollapsed">Inventory Purchases</span>
            </Link>
            <Link 
              href="/sales/quotations"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'Sales/Quotations' ? 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-800 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100'
              ]"
            >
              <FileText class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
              <span v-if="!sidebarCollapsed">Sales Quotations</span>
            </Link>
            <Link 
              href="/expenses"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'Expenses/Index' ? 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-800 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100'
              ]"
            >
              <DollarSign class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
              <span v-if="!sidebarCollapsed">Store Expenses</span>
            </Link>
            <Link 
              href="/hrm/attendance"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'HRM/Attendance' ? 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-800 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100'
              ]"
            >
              <Clock class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
              <span v-if="!sidebarCollapsed">Staff Attendance & Clock</span>
            </Link>
            <Link 
              href="/reports/profit-loss"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'Reports/ProfitLoss' ? 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-800 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100'
              ]"
            >
              <TrendingUp class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
              <span v-if="!sidebarCollapsed">Profit & Loss Accounting</span>
            </Link>
            <Link 
              href="/merchant/settings"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'Merchant/Settings' ? 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-800 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100'
              ]"
            >
              <Settings class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
              <span v-if="!sidebarCollapsed">Merchant HQ Settings</span>
            </Link>
            <Link 
              href="/merchant/subscription"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'Merchant/Subscription' ? 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-800 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-100'
              ]"
            >
              <CreditCard class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0" />
              <span v-if="!sidebarCollapsed">Billing & SaaS Plan</span>
            </Link>
          </div>
        </div>

        <!-- ⚙️ STORE MANAGER PORTAL MENU -->
        <div v-if="userRole === 'store_manager'">
          <div v-if="!sidebarCollapsed" class="px-3 mb-2 text-[10px] font-extrabold uppercase tracking-widest text-cyan-700">
            Branch Operations
          </div>
          <div class="space-y-1">
            <Link 
              href="/manager/dashboard"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'Manager/Dashboard' ? 'bg-cyan-50 text-cyan-900 border-cyan-300 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              ]"
            >
              <Store class="w-4 h-4 text-cyan-600 shrink-0" />
              <span v-if="!sidebarCollapsed">Branch Overview</span>
            </Link>
            <Link 
              href="/manager/shifts"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'Manager/Shifts' ? 'bg-cyan-50 text-cyan-900 border-cyan-300 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              ]"
            >
              <Clock class="w-4 h-4 text-cyan-600 shrink-0" />
              <span v-if="!sidebarCollapsed">Register Shift Audits</span>
            </Link>
            <Link 
              href="/manager/transfers"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'Manager/Transfers' ? 'bg-cyan-50 text-cyan-900 border-cyan-300 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              ]"
            >
              <Truck class="w-4 h-4 text-cyan-600 shrink-0" />
              <span v-if="!sidebarCollapsed">Stock Transfers</span>
            </Link>
            <Link 
              href="/manager/returns"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'Manager/Returns' ? 'bg-cyan-50 text-cyan-900 border-cyan-300 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              ]"
            >
              <ShoppingBag class="w-4 h-4 text-cyan-600 shrink-0" />
              <span v-if="!sidebarCollapsed">Returns & Refunds</span>
            </Link>
            <Link 
              :href="route('sales.quotations')"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'Sales/Quotations' ? 'bg-cyan-50 text-cyan-900 border-cyan-300 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              ]"
            >
              <FileText class="w-4 h-4 text-cyan-600 shrink-0" />
              <span v-if="!sidebarCollapsed">Sales Quotations</span>
            </Link>
            <Link 
              :href="route('expenses.index')"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'Expenses/Index' ? 'bg-cyan-50 text-cyan-900 border-cyan-300 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              ]"
            >
              <DollarSign class="w-4 h-4 text-cyan-600 shrink-0" />
              <span v-if="!sidebarCollapsed">Store Expenses</span>
            </Link>
            <Link 
              :href="route('hrm.attendance')"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'HRM/Attendance' ? 'bg-cyan-50 text-cyan-900 border-cyan-300 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              ]"
            >
              <Clock class="w-4 h-4 text-cyan-600 shrink-0" />
              <span v-if="!sidebarCollapsed">Staff Clock & HRM</span>
            </Link>
          </div>
        </div>

        <!-- ⚡ CASHIER PORTAL MENU -->
        <div v-if="userRole === 'cashier'">
          <div v-if="!sidebarCollapsed" class="px-3 mb-2 text-[10px] font-extrabold uppercase tracking-widest text-emerald-700">
            Cashier Workstation
          </div>
          <div class="space-y-1">
            <Link 
              :href="route('pos.index')"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border bg-emerald-50 text-emerald-900 border-emerald-300 font-extrabold shadow-2xs'
              ]"
            >
              <Zap class="w-4 h-4 text-emerald-600 shrink-0" />
              <span v-if="!sidebarCollapsed">POS Terminal Workstation</span>
            </Link>
            <Link 
              :href="route('hrm.attendance')"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold text-xs transition-all border',
                $page.component === 'HRM/Attendance' ? 'bg-emerald-50 text-emerald-900 border-emerald-300 font-extrabold shadow-2xs' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              ]"
            >
              <Clock class="w-4 h-4 text-emerald-600 shrink-0" />
              <span v-if="!sidebarCollapsed">Shift Clock In/Out</span>
            </Link>
          </div>
        </div>

      </nav>


      <!-- Sidebar Bottom Action (POS Workstation Launcher) -->
      <div v-if="userRole !== 'super_admin'" class="p-3 border-t border-slate-200 shrink-0 bg-white">
        <Link 
          :href="route('pos.index')" 
          class="flex items-center justify-center gap-2 w-full py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-extrabold text-xs shadow-md shadow-emerald-600/20 active:scale-95 transition-all"
        >
          <Zap class="w-4 h-4 text-amber-300 fill-amber-300 shrink-0" />
          <span v-if="!sidebarCollapsed">Open POS Counter</span>
        </Link>
      </div>
    </aside>

    <!-- Overlay Backdrop for Mobile Sidebar -->
    <div 
      v-if="mobileSidebarOpen" 
      @click="mobileSidebarOpen = false" 
      class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs z-30 lg:hidden"
    ></div>

    <!-- Right Container (Header + Main Body) -->
    <div class="flex-1 flex flex-col min-w-0 min-h-screen">
      
      <!-- Top Navigation Bar -->
      <header class="h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 sm:px-6 flex items-center justify-between sticky top-0 z-30 shadow-xs transition-colors">
        
        <!-- Left: Mobile Menu Toggle & Page Context -->
        <div class="flex items-center gap-3">
          <button 
            v-if="isSidebarLayout"
            @click="mobileSidebarOpen = !mobileSidebarOpen"
            class="lg:hidden p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
          >
            <Menu class="w-5 h-5" />
          </button>

          <!-- Top Brand Header for non-sidebar mode (POS Terminal) -->
          <Link v-if="!isSidebarLayout" :href="route('landing')" class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-blue-500 flex items-center justify-center font-bold text-lg text-white shadow-md">
              I
            </div>
            <div>
              <div class="font-bold text-base leading-none font-heading text-slate-900 dark:text-slate-100">
                IOT POS
              </div>
              <div class="text-[9px] text-indigo-600 dark:text-indigo-400 uppercase tracking-wider font-extrabold">
                International Office Technology
              </div>
            </div>
          </Link>

          <!-- Page Title Breadcrumb -->
          <div v-else class="flex items-center gap-2">
            <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider hidden sm:inline">Portal /</span>
            <span class="text-sm font-extrabold text-slate-900 dark:text-slate-100">{{ currentTabTitle }}</span>
          </div>
        </div>

        <!-- Right Header Utility Tools -->
        <div class="flex items-center gap-2 sm:gap-3">
          
          <!-- ☀️ / 🌙 Theme Switcher Toggle Button -->
          <button 
            @click="toggleTheme" 
            class="flex items-center justify-center p-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition-all active:scale-95 shadow-2xs"
            :title="isDarkMode ? 'Switch to Light Theme' : 'Switch to Dark Theme'"
          >
            <Sun v-if="isDarkMode" class="w-4 h-4 text-amber-400 fill-amber-400/20" />
            <Moon v-else class="w-4 h-4 text-indigo-600" />
          </button>

          <!-- Posify Language Selector -->
          <div class="relative">
            <select 
              v-model="selectedLanguage"
              class="px-2.5 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold text-xs border border-slate-200 dark:border-slate-700 focus:outline-none cursor-pointer pr-6 appearance-none"
            >
              <option value="en">🇺🇸 EN</option>
              <option value="bn">🇧🇩 BN</option>
              <option value="es">🇪🇸 ES</option>
              <option value="ar">🇦🇪 AR</option>
              <option value="fr">🇫🇷 FR</option>
            </select>
            <Globe class="w-3 h-3 text-slate-500 dark:text-slate-400 absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" />
          </div>

          <!-- Posify Quick Calculator Modal Button -->
          <button 
            @click="showCalcModal = !showCalcModal" 
            class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs border border-slate-200 dark:border-slate-700"
            title="Quick POS Calculator"
          >
            <Calculator class="w-3.5 h-3.5 text-amber-500" />
            <span class="hidden xl:inline">Calc</span>
          </button>

          <!-- Role Persona Switcher Dropdown -->
          <div class="relative">
            <button 
              @click="showRoleDropdown = !showRoleDropdown"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 text-emerald-800 dark:text-emerald-300 font-extrabold text-xs border border-emerald-300 dark:border-emerald-800 transition-colors shadow-2xs"
              title="Switch Persona Role"
            >
              <UserCheck class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
              <span>{{ roleLabel }}</span>
              <span class="text-[10px] text-emerald-600 dark:text-emerald-400">▼</span>
            </button>

            <!-- User Profile Dropdown Popover -->
            <div v-if="showRoleDropdown" class="absolute right-0 mt-2 w-56 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl p-3 z-50 space-y-2 text-slate-900 dark:text-slate-100">
              <div class="px-1 py-1">
                <div class="font-extrabold text-xs text-slate-900 dark:text-slate-100 truncate">{{ userName }}</div>
                <div class="text-[10px] text-slate-500 dark:text-slate-400 truncate">{{ userEmail }}</div>
              </div>
              <div class="border-t border-slate-100 dark:border-slate-800 my-1"></div>
              <Link 
                :href="route('logout')" 
                method="post" 
                as="button"
                @click="showRoleDropdown = false"
                class="flex items-center gap-2 w-full px-3 py-2 rounded-xl text-xs font-bold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors text-left"
              >
                <X class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400" />
                <span>Sign Out</span>
              </Link>
            </div>
          </div>

          <!-- Direct POS Counter Shortcut (if in non-POS layout) -->
          <Link 
            v-if="isSidebarLayout && userRole !== 'super_admin'"
            :href="route('pos.index')" 
            class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md shadow-emerald-600/20 active:scale-95 transition-all"
          >
            <Zap class="w-3.5 h-3.5 text-amber-300" />
            <span>POS Counter</span>
          </Link>
        </div>
      </header>

      <!-- Quick Calculator Modal Pop-over (Posify Feature) -->
      <div v-if="showCalcModal" class="fixed bottom-6 right-6 z-50 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 rounded-3xl p-5 shadow-2xl w-72 space-y-3 font-mono text-slate-900 dark:text-slate-100">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2">
          <span class="font-bold text-xs text-slate-900 dark:text-slate-100 font-sans flex items-center gap-1.5">
            <Calculator class="w-4 h-4 text-amber-500" /> Quick POS Calc
          </span>
          <button @click="showCalcModal = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 font-sans">
            ✕
          </button>
        </div>
        <div class="bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-3 rounded-xl text-right text-lg font-black text-slate-900 dark:text-slate-100 overflow-x-auto">
          {{ calcDisplay || '0' }}
        </div>
        <div class="grid grid-cols-4 gap-1.5 text-xs font-bold text-slate-800 dark:text-slate-200">
          <button @click="calcInput('C')" class="p-2.5 rounded-lg bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 hover:bg-rose-200 dark:hover:bg-rose-900">C</button>
          <button @click="calcInput('/')" class="p-2.5 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700">÷</button>
          <button @click="calcInput('*')" class="p-2.5 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700">×</button>
          <button @click="calcInput('-')" class="p-2.5 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700">-</button>

          <button @click="calcInput('7')" class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700">7</button>
          <button @click="calcInput('8')" class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700">8</button>
          <button @click="calcInput('9')" class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700">9</button>
          <button @click="calcInput('+')" class="p-2.5 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700">+</button>

          <button @click="calcInput('4')" class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700">4</button>
          <button @click="calcInput('5')" class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700">5</button>
          <button @click="calcInput('6')" class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700">6</button>
          <button @click="calcInput('=')" class="row-span-2 p-2.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 flex items-center justify-center font-black">=</button>

          <button @click="calcInput('1')" class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700">1</button>
          <button @click="calcInput('2')" class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700">2</button>
          <button @click="calcInput('3')" class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700">3</button>

          <button @click="calcInput('0')" class="col-span-2 p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700">0</button>
          <button @click="calcInput('.')" class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700">.</button>
        </div>
      </div>

      <!-- Main Page Content Body -->
      <main class="flex-1 flex flex-col min-w-0 bg-slate-100 dark:bg-slate-950 transition-colors">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { 
  ShieldCheck, 
  Crown, 
  Building2, 
  Store, 
  Package, 
  UserCheck, 
  Zap,
  Menu,
  X,
  Globe,
  Calculator,
  Lock,
  ChevronLeft,
  ChevronRight,
  Barcode,
  FileText,
  Clock,
  Truck,
  ShoppingBag,
  DollarSign,
  TrendingUp,
  CreditCard,
  BarChart3,
  Trash2,
  Settings,
  Activity,
  Megaphone,
  Sun,
  Moon,
  Users,
  Receipt,
  Boxes
} from 'lucide-vue-next';


const page = usePage();
const sidebarCollapsed = ref(false);
const mobileSidebarOpen = ref(false);
const selectedLanguage = ref('en');
const showCalcModal = ref(false);
const showRoleDropdown = ref(false);
const calcDisplay = ref('');
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

onMounted(() => {
  initTheme();
});

// Sidebar layout is enabled for all management portal pages except POS/Terminal workstation
const isSidebarLayout = computed(() => page.component !== 'POS/Terminal');

const calcInput = (val) => {
  if (val === 'C') {
    calcDisplay.value = '';
  } else if (val === '=') {
    try {
      const sanitized = (calcDisplay.value || '0').replace(/[^0-9+\-*/.]/g, '');
      calcDisplay.value = String(Function('"use strict"; return (' + sanitized + ')')());
    } catch {
      calcDisplay.value = 'Error';
    }
  } else {
    calcDisplay.value += val;
  }
};

const authUser = computed(() => page.props.auth?.user || {});
const userName = computed(() => authUser.value?.name || 'Authorized User');
const userEmail = computed(() => authUser.value?.email || 'user@iotpos.com');
const userRole = computed(() => authUser.value?.role || '');

const roleLabel = computed(() => {
  const role = userRole.value;
  if (role === 'super_admin') return 'Super Admin SaaS';
  if (role === 'merchant') return 'Merchant HQ';
  if (role === 'store_manager') return 'Store Manager';
  if (role === 'cashier') return 'Cashier Terminal';
  return 'Authenticated User';
});

const currentTabTitle = computed(() => {
  const comp = page.component || '';
  if (comp === 'SuperAdmin/Dashboard') return 'Super Admin Command Center';
  if (comp === 'SuperAdmin/Plans') return 'SaaS Pricing Plans';
  if (comp === 'Merchant/Dashboard') return 'Merchant HQ Analytics';
  if (comp === 'Merchant/Stores') return 'Store Outlets Directory';
  if (comp === 'Merchant/Users') return 'Staff & RBAC Accounts';
  if (comp === 'Merchant/Customers') return 'Customer CRM & Loyalty';
  if (comp === 'Merchant/Orders') return 'Chain Sales Orders';
  if (comp === 'Merchant/Settings') return 'Merchant HQ Settings';
  if (comp === 'Merchant/Subscription') return 'Billing & SaaS Subscription';
  if (comp === 'Manager/Dashboard') return 'Branch Operations';
  if (comp === 'Manager/Shifts') return 'Register Shift Audits';
  if (comp === 'Manager/Transfers') return 'Stock Transfers';
  if (comp === 'Manager/Returns') return 'Returns & Refunds';
  if (comp === 'Products/Index') return 'Products & Catalog';
  if (comp === 'Products/Barcodes') return 'Barcode Label Generator';
  if (comp === 'Sales/Quotations') return 'Sales Quotations';
  if (comp === 'Expenses/Index') return 'Store Expense Ledger';
  if (comp === 'HRM/Attendance') return 'Staff Attendance & Clock';
  if (comp === 'Reports/ProfitLoss') return 'Profit & Loss Accounting';
  return 'Dashboard';
});
</script>


