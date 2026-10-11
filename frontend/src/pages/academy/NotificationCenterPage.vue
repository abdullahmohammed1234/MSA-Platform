<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useNotificationsStore } from '@/stores/notifications';
import { type Notification } from '@/services/notifications';
import NotificationList from '@/components/notifications/NotificationList.vue';
import NotificationDetails from '@/components/notifications/NotificationDetails.vue';

const notificationsStore = useNotificationsStore();

const activeTab = ref<'all' | 'unread' | 'read'>('all');
const searchQuery = ref('');
const selectedCategory = ref('');
const selectedNotification = ref<Notification | null>(null);
const isDetailsOpen = ref(false);

const categories = [
  { label: 'All Categories', value: '' },
  { label: 'Events & Tickets', value: 'event' },
  { label: 'Volunteering & Shifts', value: 'volunteer' },
  { label: 'Announcements', value: 'announcement' },
  { label: 'Store Orders', value: 'store' },
  { label: 'Academy & Courses', value: 'course' },
  { label: 'Certificates', value: 'certificate' },
];

const loadNotifications = (page = 1) => {
  const params: any = {
    page,
    search: searchQuery.value || undefined,
    category: selectedCategory.value || undefined,
  };

  if (activeTab.value === 'unread') {
    params.unread = true;
  } else if (activeTab.value === 'read') {
    params.read = true;
  }

  notificationsStore.fetchNotifications(params);
};

// Reload notifications on filter or tab change
watch([activeTab, selectedCategory], () => {
  loadNotifications(1);
});

// Debounce search query
let searchTimeout: any = null;
watch(searchQuery, () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadNotifications(1);
  }, 300);
});

onMounted(() => {
  loadNotifications();
  notificationsStore.fetchUnread();
});

const handleViewDetails = (item: Notification) => {
  selectedNotification.value = item;
  isDetailsOpen.value = true;
  if (!item.read_at) {
    notificationsStore.markAsRead(item.uuid);
  }
};

const handleMarkAllRead = async () => {
  await notificationsStore.markAllAsRead();
  loadNotifications(notificationsStore.pagination.currentPage);
};

const handlePageChange = (page: number) => {
  loadNotifications(page);
};
</script>

<template>
  <div class="min-h-screen bg-neutral-background pb-24 pt-28 sm:pt-32">
    <div class="container-custom max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
      <!-- 1. Hero Header Banner -->
      <section class="relative overflow-hidden bg-primary text-white p-6 sm:p-10 rounded-[2rem] shadow-premium">
        <div class="absolute -right-16 -bottom-16 w-80 h-80 bg-accent-gold/10 rounded-full blur-3xl pointer-events-none" />
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div class="space-y-2">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-accent-gold/20 text-accent-gold border border-accent-gold/30">
                Community Engagement
              </span>
              <span
                v-if="notificationsStore.unreadCount > 0"
                class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-red-500/20 text-red-300 border border-red-500/30 font-mono"
              >
                {{ notificationsStore.unreadCount }} Unread Notifications
              </span>
              <span
                v-else
                class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-emerald-500/20 text-emerald-300 border border-emerald-500/30"
              >
                All Caught Up
              </span>
            </div>
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-black font-display tracking-tight !text-white">
              Notification Center
            </h1>
            <p class="text-sm !text-white/80 font-light max-w-xl">
              Stay updated on your event tickets, volunteer shifts, store orders, announcements, and account activity.
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-3 shrink-0">
            <button
              v-if="notificationsStore.unreadCount > 0"
              @click="handleMarkAllRead"
              class="px-5 py-3 rounded-xl bg-white/10 hover:bg-white/20 text-white border border-white/20 text-xs font-extrabold uppercase tracking-wider transition-all inline-flex items-center gap-2 backdrop-blur-md cursor-pointer"
            >
              Mark All as Read
            </button>
            <router-link
              to="/account/notifications"
              class="px-5 py-3 rounded-xl bg-accent-gold text-primary hover:bg-white text-xs font-extrabold uppercase tracking-wider transition-all inline-flex items-center gap-2 shadow-soft font-black"
            >
              Preferences
            </router-link>
          </div>
        </div>
      </section>

      <!-- 2. Filters & Tabs Grid -->
      <div class="bg-white p-5 rounded-2xl border border-neutral-ivory shadow-soft flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Filter Tabs -->
        <div class="flex p-1.5 bg-neutral-background rounded-xl border border-neutral-ivory self-start">
          <button
            @click="activeTab = 'all'"
            class="px-5 py-2 rounded-lg text-xs font-extrabold uppercase tracking-wider transition-all cursor-pointer"
            :class="activeTab === 'all' ? 'bg-primary text-white shadow-sm' : 'text-neutral-black/60 hover:text-primary'"
          >
            All
          </button>
          <button
            @click="activeTab = 'unread'"
            class="px-5 py-2 rounded-lg text-xs font-extrabold uppercase tracking-wider transition-all flex items-center gap-2 cursor-pointer"
            :class="activeTab === 'unread' ? 'bg-primary text-white shadow-sm' : 'text-neutral-black/60 hover:text-primary'"
          >
            Unread
            <span
              v-if="notificationsStore.unreadCount > 0"
              class="bg-red-500 text-white text-[10px] px-2 py-0.5 rounded-full font-mono font-black"
            >
              {{ notificationsStore.unreadCount }}
            </span>
          </button>
          <button
            @click="activeTab = 'read'"
            class="px-5 py-2 rounded-lg text-xs font-extrabold uppercase tracking-wider transition-all cursor-pointer"
            :class="activeTab === 'read' ? 'bg-primary text-white shadow-sm' : 'text-neutral-black/60 hover:text-primary'"
          >
            Read
          </button>
        </div>

        <!-- Search & Category Filters -->
        <div class="flex flex-col sm:flex-row gap-3 md:w-auto w-full">
          <!-- Search Input -->
          <div class="relative flex-grow sm:w-64">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-neutral-black/40">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </span>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search notifications..."
              class="w-full pl-10 pr-4 py-2.5 border border-neutral-ivory rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-primary/20 bg-neutral-background/30"
            />
          </div>

          <!-- Category Selector -->
          <select
            v-model="selectedCategory"
            class="px-4 py-2.5 border border-neutral-ivory rounded-xl text-xs font-extrabold uppercase tracking-wider focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white text-neutral-black cursor-pointer"
          >
            <option
              v-for="cat in categories"
              :key="cat.value"
              :value="cat.value"
            >
              {{ cat.label }}
            </option>
          </select>
        </div>
      </div>

      <!-- 3. Notification List Area -->
      <div v-if="notificationsStore.loading && notificationsStore.notifications.length === 0" class="py-16 flex justify-center">
        <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-primary"></div>
      </div>

      <div v-else-if="notificationsStore.notifications.length === 0" class="bg-white rounded-3xl border border-neutral-ivory py-20 text-center shadow-soft px-6">
        <div class="h-16 w-16 mx-auto rounded-2xl bg-neutral-background flex items-center justify-center text-neutral-black/30 mb-4 border border-neutral-ivory">
          <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
        </div>
        <h3 class="font-black text-neutral-black text-lg font-display">No Notifications Found</h3>
        <p class="text-xs text-neutral-muted mt-1 max-w-md mx-auto">There are no notifications matching your active tab or filter criteria.</p>
      </div>

      <div v-else class="space-y-6">
        <NotificationList
          :items="notificationsStore.notifications"
          @view-details="handleViewDetails"
        />

        <!-- Pagination Footer -->
        <div
          v-if="notificationsStore.pagination.lastPage > 1"
          class="bg-white px-6 py-4 rounded-2xl border border-neutral-ivory shadow-soft flex items-center justify-between gap-4"
        >
          <span class="text-xs font-bold text-neutral-black/60">
            Page {{ notificationsStore.pagination.currentPage }} of {{ notificationsStore.pagination.lastPage }}
          </span>
          <div class="flex gap-2">
            <button
              @click="handlePageChange(notificationsStore.pagination.currentPage - 1)"
              :disabled="notificationsStore.pagination.currentPage === 1"
              class="px-4 py-2 text-xs border border-neutral-ivory rounded-xl font-extrabold uppercase tracking-wider text-neutral-black hover:bg-neutral-background disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer"
            >
              Previous
            </button>
            <button
              @click="handlePageChange(notificationsStore.pagination.currentPage + 1)"
              :disabled="notificationsStore.pagination.currentPage === notificationsStore.pagination.lastPage"
              class="px-4 py-2 text-xs border border-neutral-ivory rounded-xl font-extrabold uppercase tracking-wider text-neutral-black hover:bg-neutral-background disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer"
            >
              Next
            </button>
          </div>
        </div>
      </div>

      <!-- Side-Drawer Details Sheet -->
      <NotificationDetails
        :isOpen="isDetailsOpen"
        :notification="selectedNotification"
        @close="isDetailsOpen = false"
      />
    </div>
  </div>
</template>
