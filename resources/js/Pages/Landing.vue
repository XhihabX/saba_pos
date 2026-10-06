<template>
  <div class="min-h-screen bg-slate-50 text-slate-900 font-sans selection:bg-emerald-500 selection:text-white">
    
    <!-- SaaS Public Header Navbar -->
    <header class="h-20 border-b border-slate-200 bg-white/90 backdrop-blur-xl px-4 sm:px-6 lg:px-12 flex items-center justify-between sticky top-0 z-50 shadow-xs">
      <div class="flex items-center gap-3">
        <ApplicationLogo size="md" :show-text="true" subtitle="International Office Technology" />
      </div>

      <nav class="hidden md:flex items-center gap-8 text-xs font-extrabold text-slate-600 uppercase tracking-wider">
        <a href="#report" class="hover:text-emerald-600 transition-colors">System Capabilities</a>
        <a href="#modules" class="hover:text-emerald-600 transition-colors">Portals & Modules</a>
        <a href="#pricing" class="hover:text-emerald-600 transition-colors">SaaS Plans</a>
      </nav>

      <div class="hidden md:flex items-center gap-3">
        <Link 
          href="/login" 
          class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs border border-slate-200 transition-all"
        >
          Sign In
        </Link>
        <Link 
          href="/register" 
          class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-md shadow-emerald-600/20 transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center gap-2"
        >
          <Sparkles class="w-4 h-4 text-amber-300" />
          <span>Register Store</span>
        </Link>
      </div>

      <!-- Mobile Hamburger Button -->
      <button 
        @click="mobileNavOpen = !mobileNavOpen"
        class="md:hidden p-2 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors"
        aria-label="Toggle Navigation Menu"
      >
        <Menu v-if="!mobileNavOpen" class="w-6 h-6" />
        <X v-else class="w-6 h-6 text-emerald-600" />
      </button>

      <!-- Mobile Dropdown Navigation Menu -->
      <div 
        v-if="mobileNavOpen"
        class="absolute top-20 left-0 right-0 bg-white border-b border-slate-200 p-6 shadow-2xl md:hidden space-y-4 text-center z-50 animate-in slide-in-from-top-2 duration-200"
      >
        <nav class="flex flex-col gap-4 text-xs font-extrabold text-slate-700 uppercase tracking-wider">
          <a href="#report" @click="mobileNavOpen = false" class="py-2 hover:text-emerald-600 border-b border-slate-100">System Capabilities</a>
          <a href="#modules" @click="mobileNavOpen = false" class="py-2 hover:text-emerald-600 border-b border-slate-100">Portals & Modules</a>
          <a href="#pricing" @click="mobileNavOpen = false" class="py-2 hover:text-emerald-600 border-b border-slate-100">SaaS Plans</a>
        </nav>
        <div class="pt-2 flex flex-col gap-2">
          <Link 
            href="/login" 
            class="w-full py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs border border-slate-200 transition-all"
          >
            Sign In
          </Link>
          <Link 
            href="/register" 
            class="w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-md flex items-center justify-center gap-2"
          >
            <Sparkles class="w-4 h-4 text-amber-300" />
            <span>Register Store</span>
          </Link>
        </div>
      </div>
    </header>

    <!-- Executive SaaS Capabilities Hero Section -->
    <section class="relative pt-16 pb-20 px-6 lg:px-12 max-w-7xl mx-auto text-center">
      <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-50 border border-emerald-200 text-xs font-extrabold text-emerald-800 mb-8 shadow-xs">
        <ShieldCheck class="w-4 h-4 text-emerald-600" />
        <span>Enterprise Multi-Tenant SaaS Platform & Retail Infrastructure</span>
      </div>

      <h1 class="text-4xl sm:text-6xl lg:text-7xl font-black font-heading text-slate-900 tracking-tight leading-[1.1] mb-6">
        Next-Gen Multi-Outlet POS & <br />
        <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-teal-600">
          Cloud Retail ERP Infrastructure
        </span>
      </h1>

      <p class="text-base sm:text-lg text-slate-600 max-w-3xl mx-auto mb-10 font-medium leading-relaxed">
        Engineered for sub-10ms counter workstation responsiveness, zero-downtime offline sales resilience, multi-store chain inventory governance, automated P&L accounting, and instant thermal receipt generation.
      </p>

      <!-- Primary Action CTAs -->
      <div class="flex flex-wrap justify-center gap-4 mb-16">
        <Link 
          href="/pos" 
          class="px-7 py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-sm shadow-xl shadow-emerald-600/25 transition-all transform hover:-translate-y-0.5 flex items-center gap-2.5"
        >
          <Zap class="w-4 h-4 text-amber-300 fill-amber-300" />
          <span>⚡ Launch POS Workstation</span>
        </Link>
        <Link 
          href="/register?plan=growth" 
          class="px-7 py-3.5 rounded-2xl bg-white border border-slate-300 hover:bg-slate-100 text-slate-800 font-extrabold text-sm transition-all flex items-center gap-2.5 shadow-xs"
        >
          <Building2 class="w-4 h-4 text-indigo-600" />
          <span>Start 14-Day Free Merchant Trial</span>
        </Link>
      </div>

      <!-- SaaS Infrastructure Benchmark Report Card -->
      <div id="report" class="grid grid-cols-2 md:grid-cols-4 gap-4 text-left">
        <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs">
          <div class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest mb-1 flex items-center gap-1.5">
            <Activity class="w-3.5 h-3.5 text-emerald-600" /> Latency Benchmark
          </div>
          <div class="text-3xl font-black font-mono text-emerald-600">&lt; 10ms</div>
          <div class="text-xs text-slate-500 font-semibold mt-1">Instant hotkey responses</div>
        </div>

        <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs">
          <div class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest mb-1 flex items-center gap-1.5">
            <WifiOff class="w-3.5 h-3.5 text-amber-600" /> Offline Resilience
          </div>
          <div class="text-3xl font-black font-mono text-amber-600">100%</div>
          <div class="text-xs text-slate-500 font-semibold mt-1">Selling continues offline</div>
        </div>

        <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs">
          <div class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest mb-1 flex items-center gap-1.5">
            <Lock class="w-3.5 h-3.5 text-indigo-600" /> Data Protection
          </div>
          <div class="text-3xl font-black font-mono text-indigo-600">256-BIT</div>
          <div class="text-xs text-slate-500 font-semibold mt-1">SSL & Multi-tenant isolated</div>
        </div>

        <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs">
          <div class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest mb-1 flex items-center gap-1.5">
            <Printer class="w-3.5 h-3.5 text-cyan-600" /> Thermal Printing
          </div>
          <div class="text-3xl font-black font-mono text-cyan-600">ESC/POS</div>
          <div class="text-xs text-slate-500 font-semibold mt-1">80mm & 58mm receipts</div>
        </div>
      </div>
    </section>

    <!-- Modules & Capabilities Showcase -->
    <section id="modules" class="py-20 px-6 lg:px-12 max-w-7xl mx-auto border-t border-slate-200 bg-white rounded-3xl shadow-sm my-12">
      <div class="text-center mb-16">
        <h2 class="text-3xl sm:text-4xl font-black font-heading text-slate-900 mb-3">
          Complete Multi-Portal ERP Ecosystem
        </h2>
        <p class="text-slate-600 text-sm max-w-2xl mx-auto font-medium">
          Integrated modules providing total operational visibility across all management levels.
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Module 1 -->
        <div class="p-8 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col justify-between">
          <div>
            <div class="w-12 h-12 rounded-xl bg-emerald-100 border border-emerald-200 flex items-center justify-center text-emerald-700 mb-5">
              <ShoppingCart class="w-6 h-6" />
            </div>
            <h3 class="text-lg font-extrabold font-heading text-slate-900 mb-2">Counter POS Terminal</h3>
            <p class="text-xs text-slate-600 leading-relaxed mb-4">
              Barcode scanning, instant keyboard hotkeys (`F2`, `F6`, `F7`), local receipt generation, order parking, and zero-downtime offline sales queue.
            </p>
          </div>
          <div class="text-[11px] font-bold text-emerald-700 uppercase tracking-wider">Sub-10ms Execution</div>
        </div>

        <!-- Module 2 -->
        <div class="p-8 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col justify-between">
          <div>
            <div class="w-12 h-12 rounded-xl bg-indigo-100 border border-indigo-200 flex items-center justify-center text-indigo-700 mb-5">
              <Building2 class="w-6 h-6" />
            </div>
            <h3 class="text-lg font-extrabold font-heading text-slate-900 mb-2">Merchant HQ & Outlets</h3>
            <p class="text-xs text-slate-600 leading-relaxed mb-4">
              Multi-store branch network management, staff user role assignments, supplier purchases, inventory transfers, and billing self-service.
            </p>
          </div>
          <div class="text-[11px] font-bold text-indigo-700 uppercase tracking-wider">Chain Governance</div>
        </div>

        <!-- Module 3 -->
        <div class="p-8 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col justify-between">
          <div>
            <div class="w-12 h-12 rounded-xl bg-rose-100 border border-rose-200 flex items-center justify-center text-rose-700 mb-5">
              <Crown class="w-6 h-6" />
            </div>
            <h3 class="text-lg font-extrabold font-heading text-slate-900 mb-2">Super Admin SaaS</h3>
            <p class="text-xs text-slate-600 leading-relaxed mb-4">
              Multi-tenant merchant onboarding, subscription MRR/ARR analytics, tenant impersonation, custom plan pricing builder, and platform health audit.
            </p>
          </div>
          <div class="text-[11px] font-bold text-rose-700 uppercase tracking-wider">SaaS Owner Governance</div>
        </div>

        <!-- Module 4 -->
        <div class="p-8 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col justify-between">
          <div>
            <div class="w-12 h-12 rounded-xl bg-cyan-100 border border-cyan-200 flex items-center justify-center text-cyan-700 mb-5">
              <Barcode class="w-6 h-6" />
            </div>
            <h3 class="text-lg font-extrabold font-heading text-slate-900 mb-2">Barcode Sticker Generator</h3>
            <p class="text-xs text-slate-600 leading-relaxed mb-4">
              Custom printable barcode label sheet generator with live grid previews, product SKU mapping, and retail price tags.
            </p>
          </div>
          <div class="text-[11px] font-bold text-cyan-700 uppercase tracking-wider">Print Label Sheet</div>
        </div>

        <!-- Module 5 -->
        <div class="p-8 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col justify-between">
          <div>
            <div class="w-12 h-12 rounded-xl bg-amber-100 border border-amber-200 flex items-center justify-center text-amber-700 mb-5">
              <TrendingUp class="w-6 h-6" />
            </div>
            <h3 class="text-lg font-extrabold font-heading text-slate-900 mb-2">Profit & Loss Accounting</h3>
            <p class="text-xs text-slate-600 leading-relaxed mb-4">
              Automated financial reporting calculating Sales Revenue, COGS (Cost of Goods Sold), Gross Profit, Operating Expenses, and Net Profit.
            </p>
          </div>
          <div class="text-[11px] font-bold text-amber-700 uppercase tracking-wider">Automated Ledger</div>
        </div>

        <!-- Module 6 -->
        <div class="p-8 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col justify-between">
          <div>
            <div class="w-12 h-12 rounded-xl bg-teal-100 border border-teal-200 flex items-center justify-center text-teal-700 mb-5">
              <Clock class="w-6 h-6" />
            </div>
            <h3 class="text-lg font-extrabold font-heading text-slate-900 mb-2">Staff Clock & Shift Audit</h3>
            <p class="text-xs text-slate-600 leading-relaxed mb-4">
              HRM employee shift clock-in/out tracking, register opening cash, expected cash calculation, actual counted balance, and shift audit logs.
            </p>
          </div>
          <div class="text-[11px] font-bold text-teal-700 uppercase tracking-wider">Register Reconciliation</div>
        </div>
      </div>
    </section>



    <!-- Transparent SaaS Subscription Plans -->
    <section id="pricing" class="py-20 px-6 lg:px-12 max-w-7xl mx-auto border-t border-slate-200">
      <div class="text-center mb-16">
        <h2 class="text-3xl sm:text-4xl font-black font-heading text-slate-900 mb-3">
          Transparent SaaS Pricing Tiers
        </h2>
        <p class="text-slate-600 text-sm">Select the optimal plan tier for your store network.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Starter Plan -->
        <div class="p-8 rounded-3xl bg-white border border-slate-200 shadow-sm flex flex-col justify-between">
          <div>
            <h3 class="text-lg font-extrabold font-heading text-slate-900 mb-2">Starter POS</h3>
            <p class="text-xs text-slate-500 mb-6">For single retail shops & boutiques</p>
            <div class="text-3xl font-black font-heading text-slate-900 mb-6 font-mono">
              ৳ 1,499 <span class="text-xs font-normal text-slate-500 font-sans">/ month</span>
            </div>
            <ul class="space-y-3 text-xs text-slate-700 mb-8 font-semibold">
              <li class="flex items-center gap-2">✓ 1 Store Outlet</li>
              <li class="flex items-center gap-2">✓ 2 Cashier Terminals</li>
              <li class="flex items-center gap-2">✓ 1,000 Products Catalog</li>
              <li class="flex items-center gap-2">✓ ESC/POS Thermal Printing</li>
            </ul>
          </div>
          <Link href="/register?plan=starter" class="w-full py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-center font-bold text-xs text-slate-800 transition-colors">
            Register Starter Store
          </Link>
        </div>

        <!-- Growth Plan (Featured) -->
        <div class="p-8 rounded-3xl bg-emerald-50 border-2 border-emerald-600 relative flex flex-col justify-between shadow-xl">
          <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 px-3.5 py-1 rounded-full bg-emerald-600 text-[9px] font-black uppercase tracking-widest text-white shadow-md">
            Most Popular
          </div>
          <div>
            <h3 class="text-lg font-extrabold font-heading text-slate-900 mb-2">Growth Multi-Store</h3>
            <p class="text-xs text-emerald-800 mb-6 font-bold">For expanding chain stores</p>
            <div class="text-3xl font-black font-heading text-slate-900 mb-6 font-mono">
              ৳ 3,999 <span class="text-xs font-normal text-slate-500 font-sans">/ month</span>
            </div>
            <ul class="space-y-3 text-xs text-slate-800 mb-8 font-bold">
              <li class="flex items-center gap-2 text-emerald-900">✓ 5 Store Outlets</li>
              <li class="flex items-center gap-2">✓ Unlimited Cashier Terminals</li>
              <li class="flex items-center gap-2">✓ Unlimited Product Catalog</li>
              <li class="flex items-center gap-2">✓ Inter-Store Stock Transfers</li>
              <li class="flex items-center gap-2">✓ Real-Time COGS & P&L Reports</li>
            </ul>
          </div>
          <Link href="/register?plan=growth" class="w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-center font-extrabold text-xs text-white shadow-md transition-all">
            Register Growth Chain
          </Link>
        </div>

        <!-- Enterprise Plan -->
        <div class="p-8 rounded-3xl bg-white border border-slate-200 shadow-sm flex flex-col justify-between">
          <div>
            <h3 class="text-lg font-extrabold font-heading text-slate-900 mb-2">Enterprise SaaS</h3>
            <p class="text-xs text-slate-500 mb-6">Custom deployment & SLA</p>
            <div class="text-3xl font-black font-heading text-slate-900 mb-6 font-mono">
              Custom Tier
            </div>
            <ul class="space-y-3 text-xs text-slate-700 mb-8 font-semibold">
              <li class="flex items-center gap-2">✓ Unlimited Store Outlets</li>
              <li class="flex items-center gap-2">✓ Isolated cPanel Database</li>
              <li class="flex items-center gap-2">✓ Custom API & ERP Integrations</li>
              <li class="flex items-center gap-2">✓ 24/7 Priority Support SLA</li>
            </ul>
          </div>
          <Link href="/register?plan=enterprise" class="w-full py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-center font-bold text-xs text-slate-800 transition-colors">
            Contact Enterprise Sales
          </Link>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="py-10 border-t border-slate-200 bg-white px-6 lg:px-12 text-center text-xs text-slate-500 font-semibold">
      <p>© 2026 International Office Technology (IOT POS). Enterprise SaaS POS & Cloud ERP Platform.</p>
    </footer>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { 
  Sparkles, 
  Zap, 
  ShoppingCart, 
  Building2, 
  Crown, 
  Printer, 
  ShieldCheck, 
  Activity, 
  WifiOff, 
  Lock, 
  Barcode, 
  TrendingUp, 
  Clock,
  Menu,
  X
} from 'lucide-vue-next';

const mobileNavOpen = ref(false);
</script>

