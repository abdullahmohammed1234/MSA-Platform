<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { notificationsService, type NotificationLog, type NotificationStats } from '@/services/notifications';
import { useToastStore } from '@/components/feedback/toast';

const toast = useToastStore();

// State
const stats = ref<NotificationStats | null>(null);
const logs = ref<NotificationLog[]>([]);
const pagination = ref({ currentPage: 1, lastPage: 1, total: 0 });
const selectedStatus = ref('');
const selectedChannel = ref('');
const searchQuery = ref('');
const loadingStats = ref(false);
const loadingLogs = ref(false);
const loadingSubmit = ref(false);
const loadingResend = ref<Record<number, boolean>>({});

// Broadcast Form State
const form = ref({
  title: '',
  message: '',
  audience: 'All'
});

const loadStats = async () => {
  loadingStats.value = true;
  try {
    stats.value = await notificationsService.getStats();
  } catch (err: any) {
    console.error('Failed to load stats:', err);
  } finally {
    loadingStats.value = false;
  }
};

const loadLogs = async (page = 1) => {
  loadingLogs.value = true;
  try {
    const data = await notificationsService.getLogs({
      page,
      status: selectedStatus.value || undefined,
      channel: selectedChannel.value || undefined,
      search: searchQuery.value || undefined
    });
    logs.value = data.data;
    pagination.value = {
      currentPage: data.current_page,
      lastPage: data.last_page,
      total: data.total
    };
  } catch (err: any) {
    console.error('Failed to load logs:', err);
  } finally {
    loadingLogs.value = false;
  }
};

const handleBroadcast = async () => {
  if (!form.value.title || !form.value.message) {
    toast.warning('Please fill in all announcement fields.');
    return;
  }

  loadingSubmit.value = true;
  try {
    await notificationsService.broadcast(form.value);
    toast.success(`Announcement broadcast successfully queued for ${form.value.audience}.`);
    form.value.title = '';
    form.value.message = '';
    form.value.audience = 'All';
    // Reload data
    loadStats();
    loadLogs(1);
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to dispatch broadcast.');
  } finally {
    loadingSubmit.value = false;
  }
};

const handleResend = async (logId: number) => {
  loadingResend.value[logId] = true;
  try {
    await notificationsService.resend(logId);
    toast.success('Notification successfully re-queued for dispatch.');
    loadStats();
    loadLogs(pagination.value.currentPage);
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to resend notification.');
  } finally {
    loadingResend.value[logId] = false;
  }
};

const filterLogs = () => {
  loadLogs(1);
};

const handlePageChange = (page: number) => {
  loadLogs(page);
};

const cleanNotificationTypeName = (type: string) => {
  const parts = type.split('\\');
  return parts[parts.length - 1].replace('Notification', '');
};

onMounted(() => {
  loadStats();
  loadLogs(1);
});
</script>

<template>
  <div class="space-y-8 text-left">
    <!-- Header -->
    <div>
      <h1 class="text-3xl font-extrabold tracking-tight text-neutral-black">Notification Management</h1>
      <p class="text-sm text-neutral-muted mt-1">Send announcements, preview systems, and track delivery health metrics.</p>
    </div>

    <!-- Analytics Dashboard Cards -->
    <div v-if="loadingStats" class="grid grid-cols-1 md:grid-cols-4 gap-5">
      <div v-for="i in 4" :key="i" class="h-24 bg-white rounded-2xl border border-neutral-ivory animate-pulse"></div>
    </div>
    
    <div v-else-if="stats" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <!-- Total Sent -->
      <div class="bg-white p-5 rounded-2xl border border-neutral-ivory shadow-soft flex items-center gap-4">
        <div class="h-10 w-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
          </svg>
        </div>
        <div>
          <span class="text-xs text-neutral-muted block font-medium">Total Dispatched</span>
          <span class="font-extrabold text-2xl text-neutral-black">{{ stats.total_sent }}</span>
        </div>
      </div>

      <!-- In-App Sent -->
      <div class="bg-white p-5 rounded-2xl border border-neutral-ivory shadow-soft flex items-center gap-4">
        <div class="h-10 w-10 rounded-xl bg-secondary/10 text-secondary flex items-center justify-center">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
        </div>
        <div>
          <span class="text-xs text-neutral-muted block font-medium">In-App Sent</span>
          <span class="font-extrabold text-2xl text-neutral-black">{{ stats.in_app_sent }}</span>
        </div>
      </div>

      <!-- Email Sent -->
      <div class="bg-white p-5 rounded-2xl border border-neutral-ivory shadow-soft flex items-center gap-4">
        <div class="h-10 w-10 rounded-xl bg-primary/5 text-primary flex items-center justify-center">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
        </div>
        <div>
          <span class="text-xs text-neutral-muted block font-medium">Emails Dispatched</span>
          <span class="font-extrabold text-2xl text-neutral-black">{{ stats.email_sent }}</span>
        </div>
      </div>

      <!-- Failure Rate -->
      <div class="bg-white p-5 rounded-2xl border border-neutral-ivory shadow-soft flex items-center gap-4">
        <div 
          class="h-10 w-10 rounded-xl flex items-center justify-center"
          :class="stats.failure_rate > 5 ? 'bg-red-50 text-red-600' : 'bg-accent-gold/20 text-amber-600'"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <div>
          <span class="text-xs text-neutral-muted block font-medium">Failure Rate</span>
          <span class="font-extrabold text-2xl text-neutral-black">{{ stats.failure_rate }}%</span>
        </div>
      </div>
    </div>

    <!-- Main grid: Compose & Logs split -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
      <!-- Broadcast Composition Form -->
      <div class="bg-white p-6 rounded-2xl border border-neutral-ivory shadow-soft space-y-6 lg:col-span-1">
        <h3 class="font-extrabold text-sm text-neutral-black uppercase tracking-wider border-b border-neutral-background pb-3">
          Compose Broadcast
        </h3>

        <form @submit.prevent="handleBroadcast" class="space-y-4">
          <!-- Title -->
          <div>
            <label for="bc-title" class="block text-xs font-bold text-neutral-black mb-1.5">Announcement Title</label>
            <input
              id="bc-title"
              v-model="form.title"
              type="text"
              placeholder="e.g. Dawah Workshop Rescheduled"
              class="w-full px-3.5 py-2.5 border border-neutral-ivory rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20"
              required
            />
          </div>

          <!-- Message -->
          <div>
            <label for="bc-message" class="block text-xs font-bold text-neutral-black mb-1.5">Message Content</label>
            <textarea
              id="bc-message"
              v-model="form.message"
              rows="5"
              placeholder="Provide a detailed message body..."
              class="w-full px-3.5 py-2.5 border border-neutral-ivory rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20"
              required
            ></textarea>
          </div>

          <!-- Audience -->
          <div>
            <label for="bc-audience" class="block text-xs font-bold text-neutral-black mb-1.5">Target Audience</label>
            <select
              id="bc-audience"
              v-model="form.audience"
              class="w-full px-3.5 py-2.5 border border-neutral-ivory rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white"
            >
              <option value="All">All Registered Users</option>
              <option value="Volunteers">Volunteers Only</option>
              <option value="Mentors">Mentors Only</option>
              <option value="Coordinators">Coordinators Only</option>
            </select>
          </div>

          <!-- Submit -->
          <button
            type="submit"
            :disabled="loadingSubmit"
            class="w-full py-2.5 bg-primary hover:bg-secondary text-white font-bold text-sm rounded-xl shadow-soft hover:shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer flex items-center justify-center gap-2"
          >
            <span v-if="loadingSubmit" class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></span>
            Send Broadcast Announcement
          </button>
        </form>
      </div>

      <!-- Logs Streams -->
      <div class="bg-white p-6 rounded-2xl border border-neutral-ivory shadow-soft space-y-6 lg:col-span-2">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-neutral-background pb-3">
          <h3 class="font-extrabold text-sm text-neutral-black uppercase tracking-wider">
            Delivery Log Streams
          </h3>

          <!-- Search & Filter inline inputs -->
          <div class="flex flex-wrap gap-2">
            <input
              v-model="searchQuery"
              @input="filterLogs"
              type="text"
              placeholder="Search user..."
              class="px-3 py-1.5 border border-neutral-ivory rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 w-32 sm:w-40"
            />
            <select
              v-model="selectedStatus"
              @change="filterLogs"
              class="px-2 py-1.5 border border-neutral-ivory rounded-lg text-xs bg-white w-24"
            >
              <option value="">All Status</option>
              <option value="sent">Sent</option>
              <option value="failed">Failed</option>
            </select>
          </div>
        </div>

        <div v-if="loadingLogs && logs.length === 0" class="py-12 flex justify-center">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
        </div>

        <div v-else-if="logs.length === 0" class="py-12 text-center text-sm text-neutral-muted">
          No logs found.
        </div>

        <div v-else class="space-y-4">
          <!-- Logs Table Responsive -->
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-neutral-black whitespace-nowrap">
              <thead>
                <tr class="bg-neutral-background border-b border-neutral-ivory">
                  <th class="p-3 font-extrabold text-neutral-muted uppercase tracking-wider">Recipient</th>
                  <th class="p-3 font-extrabold text-neutral-muted uppercase tracking-wider">Notification</th>
                  <th class="p-3 font-extrabold text-neutral-muted uppercase tracking-wider">Channel</th>
                  <th class="p-3 font-extrabold text-neutral-muted uppercase tracking-wider">Status</th>
                  <th class="p-3 font-extrabold text-neutral-muted uppercase tracking-wider text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-ivory">
                <tr v-for="log in logs" :key="log.id" class="hover:bg-neutral-background/30 transition-colors">
                  <!-- User -->
                  <td class="p-3">
                    <span class="font-bold block">{{ log.user?.name }}</span>
                    <span class="text-[10px] text-neutral-muted block">{{ log.user?.email }}</span>
                  </td>
                  <!-- Type -->
                  <td class="p-3">
                    <span class="font-medium bg-neutral-background border border-neutral-ivory px-2 py-0.5 rounded text-[10px] font-mono">
                      {{ cleanNotificationTypeName(log.notification_type) }}
                    </span>
                    <span class="text-[10px] text-neutral-muted block mt-1">
                      {{ new Date(log.created_at).toLocaleString() }}
                    </span>
                  </td>
                  <!-- Channel -->
                  <td class="p-3">
                    <span 
                      class="px-2 py-0.5 rounded text-[10px] font-bold"
                      :class="log.channel === 'in_app' ? 'bg-secondary/10 text-secondary border border-secondary/20' : 'bg-primary/5 text-primary border border-primary/15'"
                    >
                      {{ log.channel === 'in_app' ? 'In-App' : 'Email' }}
                    </span>
                  </td>
                  <!-- Status -->
                  <td class="p-3">
                    <span 
                      v-if="log.status === 'sent'"
                      class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-secondary/15 text-primary"
                    >
                      Sent
                    </span>
                    <div v-else-if="log.status === 'failed'" class="flex flex-col gap-1 items-start">
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800">
                        Failed
                      </span>
                      <span class="text-[9px] text-red-600 font-mono block max-w-[150px] truncate" :title="log.error_message || ''">
                        {{ log.error_message }}
                      </span>
                    </div>
                  </td>
                  <!-- Actions -->
                  <td class="p-3 text-right">
                    <button
                      v-if="log.status === 'failed'"
                      @click="handleResend(log.id)"
                      :disabled="loadingResend[log.id]"
                      class="px-2.5 py-1 bg-primary hover:bg-secondary text-white text-[10px] font-bold rounded-lg transition-colors cursor-pointer disabled:opacity-50"
                    >
                      <span v-if="loadingResend[log.id]">Resending...</span>
                      <span v-else>Resend</span>
                    </button>
                    <span v-else class="text-neutral-muted text-[10px]">&mdash;</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div
            v-if="pagination.lastPage > 1"
            class="px-4 py-3 bg-neutral-background rounded-xl border border-neutral-ivory flex items-center justify-between gap-4"
          >
            <span class="text-[10px] text-neutral-muted">
              Page {{ pagination.currentPage }} of {{ pagination.lastPage }}
            </span>
            <div class="flex gap-1">
              <button
                @click="handlePageChange(pagination.currentPage - 1)"
                :disabled="pagination.currentPage === 1"
                class="px-2.5 py-1 text-[10px] border border-neutral-ivory rounded bg-white hover:bg-neutral-background disabled:opacity-50 disabled:cursor-not-allowed font-bold cursor-pointer"
              >
                Prev
              </button>
              <button
                @click="handlePageChange(pagination.currentPage + 1)"
                :disabled="pagination.currentPage === pagination.lastPage"
                class="px-2.5 py-1 text-[10px] border border-neutral-ivory rounded bg-white hover:bg-neutral-background disabled:opacity-50 disabled:cursor-not-allowed font-bold cursor-pointer"
              >
                Next
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
