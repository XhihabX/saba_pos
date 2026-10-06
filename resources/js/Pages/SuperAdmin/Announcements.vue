<template>
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 lg:p-8 w-full space-y-8 bg-slate-50/70 min-h-screen">
      <!-- 📢 Hero Header Banner -->
      <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-rose-950 to-slate-900 text-white p-6 sm:p-8 shadow-xl border border-slate-800">
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-rose-500/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div class="space-y-2">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 font-bold text-xs uppercase tracking-wider">
              <Megaphone class="w-3.5 h-3.5" />
              <span>SaaS Broadcast Center</span>
            </div>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black font-heading tracking-tight text-white">
              Global Platform Announcements
            </h1>
            <p class="text-xs sm:text-sm text-slate-300 max-w-2xl">
              Publish system-wide notice banners, maintenance alerts, and feature updates directly to merchant tenant dashboards
            </p>
          </div>

          <button 
            @click="showCreateModal = true"
            class="px-5 py-3 rounded-2xl bg-gradient-to-r from-rose-600 to-amber-500 hover:from-rose-500 hover:to-amber-400 text-white font-black text-xs shadow-lg shadow-rose-600/30 flex items-center gap-2 cursor-pointer shrink-0"
          >
            <Plus class="w-4 h-4 stroke-[3]" />
            <span>+ Broadcast Announcement</span>
          </button>
        </div>
      </div>

      <!-- 📣 Active Announcements Table -->
      <div class="p-6 sm:p-8 rounded-3xl bg-white border border-slate-200/80 shadow-sm space-y-6">
        <div class="flex items-center justify-between">
          <h3 class="font-black text-xl font-heading text-slate-900 flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center">
              <Megaphone class="w-5 h-5" />
            </div>
            <span>Published Broadcast History</span>
          </h3>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200/80">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="text-slate-500 border-b border-slate-200 uppercase text-[10px] bg-slate-50/80 font-bold tracking-wider">
                <th class="py-4 px-4">Title & Message</th>
                <th class="py-4 px-4">Target Tier</th>
                <th class="py-4 px-4">Priority Level</th>
                <th class="py-4 px-4 text-center">Status</th>
                <th class="py-4 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-sans">
              <tr v-for="item in announcements" :key="item.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="py-4 px-4">
                  <span class="font-bold text-slate-900 text-sm font-heading block">{{ item.title }}</span>
                  <span class="text-xs text-slate-500 line-clamp-1">{{ item.message }}</span>
                </td>
                <td class="py-4 px-4">
                  <span class="px-2.5 py-1 rounded-lg bg-indigo-50 border border-indigo-100 text-indigo-700 font-extrabold text-xs">
                    {{ item.target_tier || 'All Merchants' }}
                  </span>
                </td>
                <td class="py-4 px-4">
                  <span :class="['px-2.5 py-1 rounded-lg font-black text-xs uppercase border', 
                    item.priority === 'urgent' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-amber-50 text-amber-700 border-amber-200']">
                    {{ item.priority }}
                  </span>
                </td>
                <td class="py-4 px-4 text-center">
                  <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                    Active Banner
                  </span>
                </td>
                <td class="py-4 px-4 text-right">
                  <button @click="deleteAnnouncement(item)" class="p-2 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition-all cursor-pointer">
                    <Trash2 class="w-4 h-4" />
                  </button>
                </td>
              </tr>
              <tr v-if="!announcements || announcements.length === 0">
                <td colspan="5" class="py-12 text-center text-slate-400 font-bold text-xs">
                  No active broadcasts found. Click '+ Broadcast Announcement' to publish a new notice.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- CREATE ANNOUNCEMENT MODAL -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
      <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 sm:p-8 space-y-6 shadow-2xl animate-in fade-in zoom-in duration-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200">
          <h3 class="font-black text-xl font-heading text-slate-900">Broadcast System Announcement</h3>
          <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-700">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submitCreate" class="space-y-4 text-xs">
          <div>
            <label class="block text-slate-700 font-bold mb-1">Announcement Title</label>
            <input type="text" v-model="form.title" required placeholder="e.g. Scheduled System Maintenance Notice" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-bold focus:outline-none focus:border-rose-500" />
          </div>

          <div>
            <label class="block text-slate-700 font-bold mb-1">Announcement Message Body</label>
            <textarea v-model="form.message" rows="3" required placeholder="We will be undergoing scheduled server upgrades..." class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 focus:outline-none focus:border-rose-500"></textarea>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-700 font-bold mb-1">Target Merchant Tier</label>
              <select v-model="form.target_tier" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-bold focus:outline-none focus:border-rose-500">
                <option value="All">All Merchants</option>
                <option value="Starter POS">Starter POS</option>
                <option value="Growth Multi-Store">Growth Multi-Store</option>
                <option value="Enterprise ERP">Enterprise ERP</option>
              </select>
            </div>

            <div>
              <label class="block text-slate-700 font-bold mb-1">Priority Severity</label>
              <select v-model="form.priority" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 font-bold focus:outline-none focus:border-rose-500">
                <option value="info">Info / General Notice</option>
                <option value="urgent">Urgent / Maintenance Alert</option>
              </select>
            </div>
          </div>

          <div class="pt-4 flex gap-3">
            <button type="submit" class="flex-1 py-3.5 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs shadow-md cursor-pointer">
              Publish Broadcast
            </button>
            <button type="button" @click="showCreateModal = false" class="px-6 py-3.5 rounded-2xl bg-slate-100 text-slate-700 font-bold text-xs hover:bg-slate-200 cursor-pointer">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Megaphone, Plus, Trash2, X } from 'lucide-vue-next';

const showCreateModal = ref(false);
const announcements = ref([
  {
    id: 1,
    title: 'IOT POS v2.4 Platform Upgrade',
    message: 'New cashier barcode scanning hotkeys and bKash instant verification ledger active.',
    target_tier: 'All',
    priority: 'info',
  }
]);

const form = ref({
  title: '',
  message: '',
  target_tier: 'All',
  priority: 'info',
});

const submitCreate = () => {
  announcements.value.unshift({
    id: Date.now(),
    ...form.value,
  });
  showCreateModal.value = false;
  form.value = { title: '', message: '', target_tier: 'All', priority: 'info' };
};

const deleteAnnouncement = (item) => {
  announcements.value = announcements.value.filter(a => a.id !== item.id);
};
</script>
