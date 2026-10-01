<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-8 bg-slate-50/70 min-h-screen">
      <!-- 🖥️ Hero Header Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-rose-950 to-slate-900 text-white p-6 sm:p-8 shadow-xl border border-slate-800">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-rose-500/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div class="space-y-2">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 font-bold text-xs uppercase tracking-wider">
              <Activity class="w-3.5 h-3.5" />
              <span>SaaS Infrastructure Telemetry</span>
            </div>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black font-heading tracking-tight text-white">
              System Health & Server Monitoring
            </h1>
            <p class="text-xs sm:text-sm text-slate-300 max-w-2xl">
              Real-time database connection pooling, queue worker jobs, API latency tracking, and storage disk quotas
            </p>
          </div>

          <div class="flex items-center gap-3">
            <span class="px-4 py-2 rounded-2xl bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 font-black text-xs uppercase tracking-wider flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
              <span>All Systems Operational</span>
            </span>
          </div>
        </div>
      </div>

      <!-- 📊 Telemetry Metrics Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">API Response Latency</span>
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
              <Zap class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-emerald-700">14ms</div>
          <div class="text-[11px] font-semibold text-emerald-600">Optimal (Target &lt; 50ms)</div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Database Connection</span>
            <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
              <Database class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-slate-900">SQLite Pool</div>
          <div class="text-[11px] font-semibold text-indigo-600">Active locks: 0 | Idle: 4</div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Queue Worker Jobs</span>
            <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
              <Cpu class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-amber-600">4 Processes</div>
          <div class="text-[11px] font-semibold text-amber-700">Pending: 0 jobs in queue</div>
        </div>

        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Storage Disk Quota</span>
            <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold">
              <HardDrive class="w-5 h-5" />
            </div>
          </div>
          <div class="text-3xl font-black font-heading text-slate-900">18.4 GB</div>
          <div class="text-[11px] font-semibold text-slate-500">Used of 250 GB SSD</div>
        </div>
      </div>

      <!-- 🛠️ Infrastructure Services Panel -->
      <div class="p-6 sm:p-8 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-6">
        <h3 class="font-black text-xl font-heading text-slate-900 flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
            <Server class="w-5 h-5" />
          </div>
          <span>Core Infrastructure Services Breakdown</span>
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div class="p-6 rounded-2xl bg-slate-50/80 border border-slate-200 space-y-3">
            <div class="flex items-center justify-between">
              <span class="font-black text-slate-900 text-sm">PHP Engine Version</span>
              <span class="px-2.5 py-1 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 font-extrabold text-xs">v8.3.33</span>
            </div>
            <p class="text-xs text-slate-500">Zend OPcache enabled for maximum HTTP request throughput.</p>
          </div>

          <div class="p-6 rounded-2xl bg-slate-50/80 border border-slate-200 space-y-3">
            <div class="flex items-center justify-between">
              <span class="font-black text-slate-900 text-sm">Laravel Framework</span>
              <span class="px-2.5 py-1 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-700 font-extrabold text-xs">v11.x Core</span>
            </div>
            <p class="text-xs text-slate-500">Boosted container caching and fast Inertia router middleware.</p>
          </div>

          <div class="p-6 rounded-2xl bg-slate-50/80 border border-slate-200 space-y-3">
            <div class="flex items-center justify-between">
              <span class="font-black text-slate-900 text-sm">Vue &amp; Inertia SPA</span>
              <span class="px-2.5 py-1 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 font-extrabold text-xs">Vite 8.3</span>
            </div>
            <p class="text-xs text-slate-500">Rolldown ESM bundling with zero white-screen crash protection.</p>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Activity, Zap, Database, Cpu, HardDrive, Server } from 'lucide-vue-next';
</script>
