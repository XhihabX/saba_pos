<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 w-full space-y-6 bg-slate-100 min-h-screen">
      <!-- Title & Operational Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <div>
          <div class="flex items-center gap-2 text-rose-600 font-bold text-xs uppercase tracking-wider">
            <Crown class="w-4 h-4" />
            <span>SaaS Platform Command Center</span>
          </div>
          <h1 class="text-2xl font-black font-heading text-slate-900 mt-1">Platform Health & Merchant Operations</h1>
          <p class="text-xs text-slate-500 mt-1">Global MRR analytics, merchant account onboarding, subscription controls, and tenant impersonation</p>
        </div>
        <button @click="showOnboardModal = true" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-rose-600 to-amber-500 hover:from-rose-500 hover:to-amber-400 text-white font-extrabold text-xs shadow-md flex items-center gap-1.5 shrink-0">
          <Plus class="w-4 h-4" />
          <span>+ Onboard New Merchant</span>
        </button>
      </div>

      <!-- Metrics Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs flex flex-col justify-between">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Monthly Recurring Revenue</span>
            <DollarSign class="w-5 h-5 text-emerald-600" />
          </div>
          <div>
            <div class="text-3xl font-extrabold font-heading text-emerald-700">৳{{ formatMoney(totalMrr) }}</div>
            <div class="text-[11px] text-emerald-700 font-semibold mt-1">Active subscriber MRR</div>
          </div>
        </div>

        <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs flex flex-col justify-between">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Pending Approvals Queue</span>
            <Clock class="w-5 h-5 text-amber-600" />
          </div>
          <div>
            <div class="text-3xl font-extrabold font-heading text-amber-700">{{ pendingTenantsCount || 0 }} Merchants</div>
            <div class="text-[11px] text-amber-800 font-semibold mt-1">Awaiting bKash/Nagad verification</div>
          </div>
        </div>

        <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs flex flex-col justify-between">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Onboarded Merchants</span>
            <Building2 class="w-5 h-5 text-indigo-600" />
          </div>
          <div>
            <div class="text-3xl font-extrabold font-heading text-indigo-700">{{ totalTenants }} Businesses</div>
            <div class="text-[11px] text-slate-500 font-medium mt-1">{{ activeTenants }} Active | {{ suspendedTenants }} Suspended</div>
          </div>
        </div>

        <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs flex flex-col justify-between">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Platform Gross Sales (GMV)</span>
            <TrendingUp class="w-5 h-5 text-emerald-600" />
          </div>
          <div>
            <div class="text-3xl font-extrabold font-heading text-slate-900">৳{{ formatMoney(totalPlatformSales) }}</div>
            <div class="text-[11px] text-slate-500 font-medium mt-1">Across all merchant registers</div>
          </div>
        </div>
      </div>

      <!-- PENDING MERCHANT APPROVALS QUEUE CARD -->
      <div v-if="pendingTenants && pendingTenants.length > 0" class="p-6 rounded-2xl bg-gradient-to-r from-amber-50 via-white to-orange-50 border-2 border-amber-300 shadow-md space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-xl bg-amber-500 flex items-center justify-center text-slate-950 font-black">
              <ShieldAlert class="w-5 h-5" />
            </div>
            <div>
              <h3 class="font-black text-base font-heading text-slate-900">Pending Merchant Manual Payment Approvals</h3>
              <p class="text-xs text-slate-600">Verify bKash / Nagad TrxID with transaction records and approve activation.</p>
            </div>
          </div>
          <span class="px-3 py-1 rounded-full bg-amber-500 text-slate-950 font-black text-xs uppercase tracking-wider animate-pulse">
            {{ pendingTenants.length }} Action Required
          </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-for="tenant in pendingTenants" :key="tenant.id" class="p-4 rounded-2xl bg-white border border-amber-200 shadow-xs space-y-3">
            <div class="flex items-start justify-between border-b border-slate-100 pb-2">
              <div>
                <span class="font-extrabold text-slate-900 text-sm font-heading block">{{ tenant.name }}</span>
                <span class="text-[11px] text-slate-500">{{ tenant.email }} • {{ tenant.phone || 'N/A' }}</span>
              </div>
              <span class="px-2.5 py-1 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-700 font-extrabold text-xs">
                {{ tenant.plan_name }} (৳{{ tenant.mrr_amount }}/mo)
              </span>
            </div>

            <!-- Payment Metadata Badges -->
            <div class="grid grid-cols-3 gap-2 text-xs bg-slate-50 p-2.5 rounded-xl border border-slate-200">
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
                <span class="font-mono font-black text-amber-600 bg-amber-50 px-1 rounded border border-amber-200">{{ tenant.transaction_id || 'N/A' }}</span>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2 pt-1">
              <button 
                @click="approveTenant(tenant)" 
                class="flex-1 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs shadow-xs flex items-center justify-center gap-1.5 transition-all"
              >
                <CheckCircle2 class="w-4 h-4" />
                <span>Approve & Activate Merchant</span>
              </button>

              <button 
                @click="rejectTenant(tenant)" 
                class="px-4 py-2 rounded-xl bg-rose-50 hover:bg-rose-600 text-rose-700 hover:text-white font-extrabold text-xs border border-rose-200 transition-all"
              >
                Reject
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- System Health Monitor Bar -->
      <div class="p-4 rounded-2xl bg-white border border-slate-200 shadow-xs">
        <div class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 flex items-center gap-2">
          <Activity class="w-4 h-4 text-emerald-600" />
          <span>SaaS Platform Infrastructure Status</span>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 text-xs">
          <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
            <span class="text-slate-500 text-[10px] uppercase block font-bold">API Latency</span>
            <span class="font-extrabold text-emerald-700 font-mono">{{ systemHealth?.api_latency || '14ms' }}</span>
          </div>
          <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
            <span class="text-slate-500 text-[10px] uppercase block font-bold">Database Health</span>
            <span class="font-extrabold text-slate-900 font-mono">{{ systemHealth?.db_status || 'Optimal' }}</span>
          </div>
          <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
            <span class="text-slate-500 text-[10px] uppercase block font-bold">Queue Workers</span>
            <span class="font-extrabold text-indigo-700 font-mono">{{ systemHealth?.queue_workers || '4 Processes' }}</span>
          </div>
          <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
            <span class="text-slate-500 text-[10px] uppercase block font-bold">Storage Usage</span>
            <span class="font-extrabold text-slate-900 font-mono">{{ systemHealth?.storage_used || '18.4 GB' }}</span>
          </div>
          <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
            <span class="text-slate-500 text-[10px] uppercase block font-bold">SLA Uptime</span>
            <span class="font-extrabold text-emerald-700 font-mono">{{ systemHealth?.uptime || '99.99%' }}</span>
          </div>
        </div>
      </div>

      <!-- Tenants Control Table -->
      <div class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <h3 class="font-bold text-base font-heading text-slate-900">Merchant Businesses Directory & Control</h3>
          
          <div class="w-full sm:w-72 relative">
            <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input 
              type="text" 
              v-model="searchQuery" 
              placeholder="Search merchant business..." 
              class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-900 focus:outline-none focus:border-rose-500"
            />
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] bg-slate-50">
                <th class="py-3 px-3">Business & Tenant Code</th>
                <th class="py-3 px-3">Subscription Tier</th>
                <th class="py-3 px-3 text-right">MRR (৳)</th>
                <th class="py-3 px-3 text-center">Stores / Users</th>
                <th class="py-3 px-3 text-center">Status</th>
                <th class="py-3 px-3 text-right">Administrative Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr v-for="tenant in filteredTenants" :key="tenant.id" class="hover:bg-slate-50 transition-colors">
                <td class="py-3.5 px-3">
                  <div class="font-bold text-slate-900 text-sm font-heading">{{ tenant.name }}</div>
                  <div class="text-[10px] text-slate-500 font-mono">{{ tenant.code }} | {{ tenant.email }}</div>
                  <div v-if="tenant.transaction_id" class="text-[10px] text-slate-400 font-mono">TrxID: {{ tenant.transaction_id }}</div>
                </td>
                <td class="py-3.5 px-3 text-indigo-700 font-bold">{{ tenant.plan_name }}</td>
                <td class="py-3.5 px-3 text-right font-mono font-bold text-emerald-700">৳{{ formatMoney(tenant.mrr_amount) }}</td>
                <td class="py-3.5 px-3 text-center font-bold text-slate-700">
                  {{ tenant.stores_count || 1 }} Stores / {{ tenant.users_count || 1 }} Users
                </td>
                <td class="py-3.5 px-3 text-center">
                  <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase', 
                    tenant.subscription_status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 
                    (tenant.subscription_status === 'pending_approval' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-rose-50 text-rose-700 border border-rose-200')]">
                    {{ tenant.subscription_status }}
                  </span>
                </td>
                <td class="py-3.5 px-3 text-right space-x-1">
                  <!-- Approve button if pending -->
                  <button 
                    v-if="tenant.subscription_status === 'pending_approval'"
                    @click="approveTenant(tenant)"
                    class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] shadow-xs"
                  >
                    Approve
                  </button>

                  <!-- Impersonate Button -->
                  <button 
                    v-else
                    @click="impersonate(tenant)" 
                    class="px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white font-bold text-[11px] border border-indigo-200 transition-all"
                    title="Impersonate Merchant CEO"
                  >
                    Inspect HQ
                  </button>

                  <!-- Edit Button -->
                  <button 
                    @click="openEditModal(tenant)" 
                    class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[11px] border border-slate-200"
                  >
                    Edit
                  </button>

                  <!-- Suspend/Activate Button -->
                  <button 
                    @click="toggleStatus(tenant)" 
                    :class="['px-2.5 py-1 rounded-lg font-bold text-[11px] border', tenant.subscription_status === 'active' ? 'bg-amber-50 text-amber-800 border-amber-200 hover:bg-amber-600 hover:text-white' : 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-600 hover:text-white']"
                  >
                    {{ tenant.subscription_status === 'active' ? 'Suspend' : 'Activate' }}
                  </button>

                  <!-- Terminate Button -->
                  <button 
                    @click="deleteTenant(tenant)" 
                    class="px-2 py-1 rounded-lg bg-rose-50 hover:bg-rose-600 text-rose-700 hover:text-white font-bold text-[11px] border border-rose-200"
                    title="Terminate Tenant"
                  >
                    <Trash2 class="w-3.5 h-3.5 inline" />
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
      <div class="bg-white border border-slate-200 rounded-3xl max-w-lg w-full p-6 space-y-4 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200">
          <h3 class="font-bold text-lg font-heading text-slate-900">Onboard New Merchant Business</h3>
          <button @click="showOnboardModal = false" class="text-slate-400 hover:text-slate-700">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submitOnboard" class="space-y-3 text-xs">
          <div>
            <label class="block text-slate-700 font-semibold mb-1">Business / Brand Name</label>
            <input type="text" v-model="onboardForm.name" required placeholder="e.g. Apex Footwear Ltd" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-700 font-semibold mb-1">Business Email</label>
              <input type="email" v-model="onboardForm.email" required placeholder="contact@apex.com" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500" />
            </div>
            <div>
              <label class="block text-slate-700 font-semibold mb-1">Phone</label>
              <input type="text" v-model="onboardForm.phone" placeholder="+880 1700..." class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-200">
            <div>
              <label class="block text-slate-700 font-semibold mb-1">Merchant Owner Name</label>
              <input type="text" v-model="onboardForm.owner_name" required placeholder="Syed Nasim" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500" />
            </div>
            <div>
              <label class="block text-slate-700 font-semibold mb-1">Owner Account Email</label>
              <input type="email" v-model="onboardForm.owner_email" required placeholder="nasim@apex.com" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500" />
            </div>
          </div>

          <div>
            <label class="block text-slate-700 font-semibold mb-1">Owner Password</label>
            <input type="password" v-model="onboardForm.password" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-700 font-semibold mb-1">SaaS Plan Tier</label>
              <select v-model="onboardForm.plan_name" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500">
                <option value="Starter POS">Starter POS (৳1,499/mo)</option>
                <option value="Growth Multi-Store">Growth Multi-Store (৳3,999/mo)</option>
                <option value="Enterprise ERP">Enterprise ERP (৳9,999/mo)</option>
              </select>
            </div>
            <div>
              <label class="block text-slate-700 font-semibold mb-1">Monthly MRR Fee (৳)</label>
              <input type="number" step="0.01" v-model.number="onboardForm.mrr_amount" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-mono focus:outline-none focus:border-rose-500" />
            </div>
          </div>

          <div class="pt-3 flex gap-3">
            <button type="submit" class="flex-1 py-3 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold shadow-md">
              Create Merchant Account
            </button>
            <button type="button" @click="showOnboardModal = false" class="px-5 py-3 rounded-xl bg-slate-100 text-slate-700 font-bold">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- EDIT MERCHANT MODAL -->
    <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
      <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200">
          <h3 class="font-bold text-lg font-heading text-slate-900">Edit Merchant Tenant Details</h3>
          <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-700">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submitEdit" class="space-y-3 text-xs">
          <div>
            <label class="block text-slate-700 font-semibold mb-1">Business Name</label>
            <input type="text" v-model="editForm.name" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-700 font-semibold mb-1">Email</label>
              <input type="email" v-model="editForm.email" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500" />
            </div>
            <div>
              <label class="block text-slate-700 font-semibold mb-1">Phone</label>
              <input type="text" v-model="editForm.phone" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-700 font-semibold mb-1">Subscription Plan</label>
              <select v-model="editForm.plan_name" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500">
                <option value="Starter POS">Starter POS</option>
                <option value="Growth Multi-Store">Growth Multi-Store</option>
                <option value="Enterprise ERP">Enterprise ERP</option>
              </select>
            </div>
            <div>
              <label class="block text-slate-700 font-semibold mb-1">MRR Amount (৳)</label>
              <input type="number" step="0.01" v-model.number="editForm.mrr_amount" required class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-mono focus:outline-none focus:border-rose-500" />
            </div>
          </div>

          <div>
            <label class="block text-slate-700 font-semibold mb-1">Subscription Status</label>
            <select v-model="editForm.subscription_status" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-bold focus:outline-none focus:border-rose-500">
              <option value="active">Active</option>
              <option value="pending_approval">Pending Approval</option>
              <option value="past_due">Past Due</option>
              <option value="suspended">Suspended</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>

          <div class="pt-3 flex gap-3">
            <button type="submit" class="flex-1 py-3 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold shadow-md">
              Save Changes
            </button>
            <button type="button" @click="showEditModal = false" class="px-5 py-3 rounded-xl bg-slate-100 text-slate-700 font-bold">
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
  Store, 
  Activity, 
  Search, 
  Trash2, 
  X,
  Clock,
  ShieldAlert,
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
