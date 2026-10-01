<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-8 bg-slate-50/70 min-h-screen">
      <!-- 👑 Hero SaaS Command Center Header -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-rose-950 to-slate-900 text-white p-6 sm:p-8 shadow-xl border border-slate-800">
        <!-- Background Decorative Glows -->
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-rose-500/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div class="space-y-2">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 font-bold text-xs uppercase tracking-wider">
              <Crown class="w-3.5 h-3.5" />
              <span>SaaS Platform Global Command</span>
            </div>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black font-heading tracking-tight text-white">
              Platform Health & Merchant Operations
            </h1>
            <p class="text-xs sm:text-sm text-slate-300 max-w-2xl">
              Global MRR analytics, merchant account onboarding, subscription controls, and multi-tenant infrastructure supervision
            </p>
          </div>

          <div class="flex items-center gap-3 shrink-0">
            <button 
              @click="showOnboardModal = true" 
              class="px-5 py-3 rounded-2xl bg-gradient-to-r from-rose-600 via-rose-500 to-amber-500 hover:from-rose-500 hover:to-amber-400 text-white font-black text-xs shadow-lg shadow-rose-600/30 flex items-center gap-2 transform hover:-translate-y-0.5 transition-all cursor-pointer"
            >
              <Plus class="w-4 h-4 stroke-[3]" />
              <span>+ Onboard New Merchant</span>
            </button>
          </div>
        </div>

        <!-- Real-time Status Strip -->
        <div class="mt-6 pt-6 border-t border-white/10 grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
            <span class="text-slate-300">API Status: <strong class="text-white">Operational (14ms)</strong></span>
          </div>
          <div class="flex items-center gap-2">
            <ShieldCheck class="w-4 h-4 text-emerald-400" />
            <span class="text-slate-300">Security SLA: <strong class="text-white">99.99% Uptime</strong></span>
          </div>
          <div class="flex items-center gap-2">
            <Building2 class="w-4 h-4 text-rose-400" />
            <span class="text-slate-300">Active Merchants: <strong class="text-white">{{ activeTenants }} Chains</strong></span>
          </div>
          <div class="flex items-center gap-2">
            <Clock class="w-4 h-4 text-amber-400" />
            <span class="text-slate-300">Pending Verification: <strong class="text-white">{{ pendingTenantsCount || 0 }} Queue</strong></span>
          </div>
        </div>
      </div>

      <!-- 📊 KPI Metrics Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- MRR Card -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm hover:shadow-md transition-all space-y-3 relative overflow-hidden group">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Monthly Recurring Revenue</span>
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
              <DollarSign class="w-5 h-5" />
            </div>
          </div>
          <div>
            <div class="text-3xl font-black font-heading text-slate-900">৳{{ formatMoney(totalMrr) }}</div>
            <div class="flex items-center gap-1 text-[11px] font-bold text-emerald-600 mt-1">
              <TrendingUp class="w-3.5 h-3.5" />
              <span>Active subscriptions MRR</span>
            </div>
          </div>
        </div>

        <!-- Pending Approval Card -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm hover:shadow-md transition-all space-y-3 relative overflow-hidden group">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Pending Approvals</span>
            <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
              <Clock class="w-5 h-5" />
            </div>
          </div>
          <div>
            <div class="text-3xl font-black font-heading text-amber-600">{{ pendingTenantsCount || 0 }} Merchants</div>
            <div class="text-[11px] font-semibold text-amber-700 mt-1">Awaiting bKash / Nagad verification</div>
          </div>
        </div>

        <!-- Onboarded Businesses Card -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm hover:shadow-md transition-all space-y-3 relative overflow-hidden group">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Onboarded Merchants</span>
            <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
              <Building2 class="w-5 h-5" />
            </div>
          </div>
          <div>
            <div class="text-3xl font-black font-heading text-slate-900">{{ totalTenants }} Businesses</div>
            <div class="text-[11px] font-medium text-slate-500 mt-1">
              <span class="text-emerald-600 font-bold">{{ activeTenants }} Active</span> • <span class="text-rose-600 font-bold">{{ suspendedTenants }} Suspended</span>
            </div>
          </div>
        </div>

        <!-- Gross Sales Card -->
        <div class="p-6 rounded-3xl bg-white border border-slate-200/80 shadow-sm hover:shadow-md transition-all space-y-3 relative overflow-hidden group">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Platform Gross GMV</span>
            <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold">
              <Activity class="w-5 h-5" />
            </div>
          </div>
          <div>
            <div class="text-3xl font-black font-heading text-slate-900">৳{{ formatMoney(totalPlatformSales) }}</div>
            <div class="text-[11px] font-medium text-slate-500 mt-1">Processed across registers</div>
          </div>
        </div>
      </div>

      <!-- ⚠️ PENDING MERCHANT APPROVALS QUEUE ROW -->
      <div v-if="pendingTenants && pendingTenants.length > 0" class="p-6 sm:p-8 rounded-3xl bg-gradient-to-br from-amber-500/10 via-white to-amber-500/5 border-2 border-amber-300 shadow-md space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-amber-500 text-slate-950 flex items-center justify-center font-black shadow-md shadow-amber-500/20 shrink-0">
              <ShieldAlert class="w-6 h-6" />
            </div>
            <div>
              <h3 class="font-black text-lg font-heading text-slate-900">Pending Merchant Manual Payment Approvals</h3>
              <p class="text-xs text-slate-600">Verify bKash / Nagad TrxID with manual ledger before activating merchant accounts.</p>
            </div>
          </div>
          <span class="px-4 py-1.5 rounded-full bg-amber-500 text-slate-950 font-black text-xs uppercase tracking-wider animate-pulse shrink-0">
            {{ pendingTenants.length }} Action Required
          </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-for="tenant in pendingTenants" :key="tenant.id" class="p-5 rounded-2xl bg-white border border-amber-200 shadow-sm hover:shadow-md transition-all space-y-4">
            <div class="flex items-start justify-between border-b border-slate-100 pb-3">
              <div>
                <span class="font-black text-slate-900 text-base font-heading block">{{ tenant.name }}</span>
                <span class="text-xs text-slate-500 font-mono">{{ tenant.email }} • {{ tenant.phone || 'N/A' }}</span>
              </div>
              <span class="px-3 py-1 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-700 font-extrabold text-xs">
                {{ tenant.plan_name }} (৳{{ tenant.mrr_amount }}/mo)
              </span>
            </div>

            <!-- Payment Metadata Grid -->
            <div class="grid grid-cols-3 gap-2 text-xs bg-slate-50 p-3 rounded-xl border border-slate-200">
              <div>
                <span class="text-[10px] text-slate-500 font-bold uppercase block">Method</span>
                <span class="font-black text-pink-600 uppercase">{{ tenant.payment_method || 'bKash' }}</span>
              </div>
              <div>
                <span class="text-[10px] text-slate-500 font-bold uppercase block">Sender Phone</span>
                <span class="font-mono font-bold text-slate-800">{{ tenant.sender_number || 'N/A' }}</span>
              </div>
              <div>
                <span class="text-[10px] text-slate-500 font-bold uppercase block">TrxID</span>
                <span class="font-mono font-black text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">{{ tenant.transaction_id || 'N/A' }}</span>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2 pt-1">
              <button 
                @click="approveTenant(tenant)" 
                class="flex-1 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-md flex items-center justify-center gap-2 transition-all cursor-pointer"
              >
                <CheckCircle2 class="w-4 h-4" />
                <span>Approve & Activate Merchant</span>
              </button>

              <button 
                @click="rejectTenant(tenant)" 
                class="px-4 py-2.5 rounded-xl bg-rose-50 hover:bg-rose-600 text-rose-700 hover:text-white font-extrabold text-xs border border-rose-200 transition-all cursor-pointer"
              >
                Reject
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- 🏬 MERCHANT BUSINESSES DIRECTORY TABLE -->
      <div class="p-6 sm:p-8 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h3 class="font-black text-xl font-heading text-slate-900">Merchant Businesses Directory & Control</h3>
            <p class="text-xs text-slate-500">Impersonate CEOs, suspend accounts, and manage subscriptions across all tenants</p>
          </div>
          
          <div class="w-full sm:w-80 relative">
            <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input 
              type="text" 
              v-model="searchQuery" 
              placeholder="Search by business name, email, code..." 
              class="w-full pl-10 pr-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-xs text-slate-900 focus:outline-none focus:border-rose-500 transition-all"
            />
          </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200/80">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] bg-slate-50/80 font-bold tracking-wider">
                <th class="py-4 px-4">Business & Tenant Code</th>
                <th class="py-4 px-4">Subscription Tier</th>
                <th class="py-4 px-4 text-right">MRR (৳)</th>
                <th class="py-4 px-4 text-center">Stores / Users</th>
                <th class="py-4 px-4 text-center">Status</th>
                <th class="py-4 px-4 text-right">Administrative Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-sans">
              <tr v-for="tenant in filteredTenants" :key="tenant.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="py-4 px-4">
                  <div class="font-bold text-slate-900 text-sm font-heading">{{ tenant.name }}</div>
                  <div class="text-[10px] text-slate-500 font-mono">{{ tenant.code }} | {{ tenant.email }}</div>
                  <div v-if="tenant.transaction_id" class="text-[10px] text-amber-600 font-mono">TrxID: {{ tenant.transaction_id }}</div>
                </td>
                <td class="py-4 px-4">
                  <span class="px-3 py-1 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-700 font-extrabold text-[11px]">
                    {{ tenant.plan_name }}
                  </span>
                </td>
                <td class="py-4 px-4 text-right font-mono font-bold text-emerald-700 text-sm">
                  ৳{{ formatMoney(tenant.mrr_amount) }}
                </td>
                <td class="py-4 px-4 text-center font-bold text-slate-700">
                  <span class="px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200">
                    {{ tenant.stores_count || 1 }} Stores / {{ tenant.users_count || 1 }} Users
                  </span>
                </td>
                <td class="py-4 px-4 text-center">
                  <span :class="['px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border', 
                    tenant.subscription_status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 
                    (tenant.subscription_status === 'pending_approval' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-rose-50 text-rose-700 border-rose-200')]">
                    {{ tenant.subscription_status }}
                  </span>
                </td>
                <td class="py-4 px-4 text-right space-x-1 whitespace-nowrap">
                  <!-- Approve button if pending -->
                  <button 
                    v-if="tenant.subscription_status === 'pending_approval'"
                    @click="approveTenant(tenant)"
                    class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition-all cursor-pointer"
                  >
                    Approve
                  </button>

                  <!-- Impersonate Button -->
                  <button 
                    v-else
                    @click="impersonate(tenant)" 
                    class="px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white font-extrabold text-xs border border-indigo-200 transition-all cursor-pointer"
                    title="Impersonate Merchant CEO"
                  >
                    Inspect HQ
                  </button>

                  <!-- Edit Button -->
                  <button 
                    @click="openEditModal(tenant)" 
                    class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-200 transition-all cursor-pointer"
                  >
                    Edit
                  </button>

                  <!-- Suspend/Activate Button -->
                  <button 
                    @click="toggleStatus(tenant)" 
                    :class="['px-3 py-1.5 rounded-xl font-bold text-xs border transition-all cursor-pointer', tenant.subscription_status === 'active' ? 'bg-amber-50 text-amber-800 border-amber-200 hover:bg-amber-600 hover:text-white' : 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-600 hover:text-white']"
                  >
                    {{ tenant.subscription_status === 'active' ? 'Suspend' : 'Activate' }}
                  </button>

                  <!-- Terminate Button -->
                  <button 
                    @click="deleteTenant(tenant)" 
                    class="p-1.5 rounded-xl bg-rose-50 hover:bg-rose-600 text-rose-700 hover:text-white font-bold border border-rose-200 transition-all cursor-pointer inline-flex items-center"
                    title="Terminate Tenant"
                  >
                    <Trash2 class="w-4 h-4" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ONBOARD NEW MERCHANT MODAL -->
    <div v-if="showOnboardModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
      <div class="bg-white border border-slate-200 rounded-3xl max-w-lg w-full p-6 sm:p-8 space-y-6 shadow-2xl animate-in fade-in zoom-in duration-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200">
          <div>
            <h3 class="font-black text-xl font-heading text-slate-900">Onboard New Merchant Business</h3>
            <p class="text-xs text-slate-500">Create merchant tenant profile and CEO login credentials</p>
          </div>
          <button @click="showOnboardModal = false" class="text-slate-400 hover:text-slate-700">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submitOnboard" class="space-y-4 text-xs">
          <div>
            <label class="block text-slate-700 font-bold mb-1">Business / Brand Name</label>
            <input type="text" v-model="onboardForm.name" required placeholder="e.g. Apex Footwear Ltd" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500 font-bold" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-700 font-bold mb-1">Business Email</label>
              <input type="email" v-model="onboardForm.email" required placeholder="contact@apex.com" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500" />
            </div>
            <div>
              <label class="block text-slate-700 font-bold mb-1">Phone</label>
              <input type="text" v-model="onboardForm.phone" placeholder="+880 1700..." class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3 pt-3 border-t border-slate-200">
            <div>
              <label class="block text-slate-700 font-bold mb-1">Merchant CEO Name</label>
              <input type="text" v-model="onboardForm.owner_name" required placeholder="Syed Nasim" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500 font-bold" />
            </div>
            <div>
              <label class="block text-slate-700 font-bold mb-1">CEO Account Email</label>
              <input type="email" v-model="onboardForm.owner_email" required placeholder="nasim@apex.com" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500" />
            </div>
          </div>

          <div>
            <label class="block text-slate-700 font-bold mb-1">Initial CEO Password</label>
            <input type="password" v-model="onboardForm.password" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500 font-mono" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-700 font-bold mb-1">SaaS Plan Tier</label>
              <select v-model="onboardForm.plan_name" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-bold focus:outline-none focus:border-rose-500">
                <option value="Starter POS">Starter POS (৳1,499/mo)</option>
                <option value="Growth Multi-Store">Growth Multi-Store (৳3,999/mo)</option>
                <option value="Enterprise ERP">Enterprise ERP (৳9,999/mo)</option>
              </select>
            </div>
            <div>
              <label class="block text-slate-700 font-bold mb-1">Monthly MRR Fee (৳)</label>
              <input type="number" step="0.01" v-model.number="onboardForm.mrr_amount" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-mono font-bold focus:outline-none focus:border-rose-500" />
            </div>
          </div>

          <div class="pt-4 flex gap-3">
            <button type="submit" class="flex-1 py-3.5 rounded-2xl bg-gradient-to-r from-rose-600 to-amber-500 hover:from-rose-500 hover:to-amber-400 text-white font-extrabold text-xs shadow-lg shadow-rose-600/20 cursor-pointer">
              Create Merchant Account
            </button>
            <button type="button" @click="showOnboardModal = false" class="px-6 py-3.5 rounded-2xl bg-slate-100 text-slate-700 font-bold text-xs hover:bg-slate-200 cursor-pointer">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- EDIT MERCHANT MODAL -->
    <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
      <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 sm:p-8 space-y-6 shadow-2xl animate-in fade-in zoom-in duration-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200">
          <h3 class="font-black text-xl font-heading text-slate-900">Edit Merchant Tenant Details</h3>
          <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-700">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submitEdit" class="space-y-4 text-xs">
          <div>
            <label class="block text-slate-700 font-bold mb-1">Business Name</label>
            <input type="text" v-model="editForm.name" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-bold focus:outline-none focus:border-rose-500" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-700 font-bold mb-1">Email</label>
              <input type="email" v-model="editForm.email" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500" />
            </div>
            <div>
              <label class="block text-slate-700 font-bold mb-1">Phone</label>
              <input type="text" v-model="editForm.phone" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-700 font-bold mb-1">Subscription Plan</label>
              <select v-model="editForm.plan_name" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-bold focus:outline-none focus:border-rose-500">
                <option value="Starter POS">Starter POS</option>
                <option value="Growth Multi-Store">Growth Multi-Store</option>
                <option value="Enterprise ERP">Enterprise ERP</option>
              </select>
            </div>
            <div>
              <label class="block text-slate-700 font-bold mb-1">MRR Amount (৳)</label>
              <input type="number" step="0.01" v-model.number="editForm.mrr_amount" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-mono font-bold focus:outline-none focus:border-rose-500" />
            </div>
          </div>

          <div>
            <label class="block text-slate-700 font-bold mb-1">Subscription Status</label>
            <select v-model="editForm.subscription_status" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-extrabold focus:outline-none focus:border-rose-500">
              <option value="active">Active</option>
              <option value="pending_approval">Pending Approval</option>
              <option value="past_due">Past Due</option>
              <option value="suspended">Suspended</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>

          <div class="pt-4 flex gap-3">
            <button type="submit" class="flex-1 py-3.5 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs shadow-md cursor-pointer">
              Save Changes
            </button>
            <button type="button" @click="showEditModal = false" class="px-6 py-3.5 rounded-2xl bg-slate-100 text-slate-700 font-bold text-xs hover:bg-slate-200 cursor-pointer">
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
import { useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { 
  Crown,
  Plus, 
  DollarSign, 
  Building2, 
  TrendingUp, 
  Activity, 
  Search, 
  Trash2, 
  X,
  Clock,
  ShieldAlert,
  ShieldCheck,
  CheckCircle2
} from 'lucide-vue-next';

const props = defineProps({
  totalTenants: Number,
  activeTenants: Number,
  pendingTenantsCount: Number,
  suspendedTenants: Number,
  totalMrr: Number,
  totalPlatformSales: Number,
  totalStores: Number,
  totalProducts: Number,
  tenants: Array,
  pendingTenants: Array,
  plans: Array,
  auditLogs: Array,
  systemHealth: Object,
});

const searchQuery = ref('');
const showOnboardModal = ref(false);
const showEditModal = ref(false);
const editingTenantId = ref(null);

const onboardForm = useForm({
  name: '',
  email: '',
  phone: '',
  owner_name: '',
  owner_email: '',
  password: 'password123',
  plan_name: 'Growth Multi-Store',
  mrr_amount: 3999,
});

const editForm = useForm({
  name: '',
  email: '',
  phone: '',
  plan_name: '',
  mrr_amount: 0,
  subscription_status: 'active',
});

const filteredTenants = computed(() => {
  const tenantsList = props.tenants || [];
  if (!searchQuery.value) return tenantsList;
  const q = searchQuery.value.toLowerCase();
  return tenantsList.filter(t => 
    (t.name || '').toLowerCase().includes(q) || 
    (t.code || '').toLowerCase().includes(q) || 
    (t.email || '').toLowerCase().includes(q)
  );
});

const formatMoney = (val) => {
  return (parseFloat(val) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const approveTenant = (tenant) => {
  if (confirm(`Approve payment and activate merchant account '${tenant.name}'?`)) {
    router.post(`/super-admin/tenants/${tenant.id}/approve`);
  }
};

const rejectTenant = (tenant) => {
  if (confirm(`Reject payment verification for merchant '${tenant.name}'?`)) {
    router.post(`/super-admin/tenants/${tenant.id}/reject`);
  }
};

const submitOnboard = () => {
  onboardForm.post('/super-admin/tenants', {
    onSuccess: () => {
      showOnboardModal.value = false;
      onboardForm.reset();
    }
  });
};

const openEditModal = (tenant) => {
  editingTenantId.value = tenant.id;
  editForm.name = tenant.name;
  editForm.email = tenant.email;
  editForm.phone = tenant.phone || '';
  editForm.plan_name = tenant.plan_name;
  editForm.mrr_amount = tenant.mrr_amount;
  editForm.subscription_status = tenant.subscription_status;
  showEditModal.value = true;
};

const submitEdit = () => {
  editForm.post(`/super-admin/tenants/${editingTenantId.value}/update`, {
    onSuccess: () => {
      showEditModal.value = false;
    }
  });
};

const toggleStatus = (tenant) => {
  const newStatus = tenant.subscription_status === 'active' ? 'suspended' : 'active';
  router.post(`/super-admin/tenants/${tenant.id}/update`, {
    name: tenant.name,
    email: tenant.email,
    phone: tenant.phone,
    plan_name: tenant.plan_name,
    mrr_amount: tenant.mrr_amount,
    subscription_status: newStatus,
  });
};

const deleteTenant = (tenant) => {
  if (confirm(`Are you sure you want to terminate merchant business '${tenant.name}'?`)) {
    router.delete(`/super-admin/tenants/${tenant.id}`);
  }
};

const impersonate = (tenant) => {
  router.post(`/super-admin/tenants/${tenant.id}/impersonate`);
};
</script>

