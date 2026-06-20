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
  { label: 'Announcements', value: 'announcement' },
  { label: 'Course Progress', value: 'course_completion' },
  { label: 'Credentials', value: 'certificate' },
  { label: 'Training & Events', value: 'upcoming_training' },
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
  }, 3000);
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
  <div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-3xl font-extrabold tracking-tight text-neutral-black">Notification Center</h1>
        <p class="text-sm text-neutral-muted mt-1">Manage announcements, certifications, and academy updates.</p>
      </div>

      <div class="flex items-center gap-2">
        <button
          v-if="notificationsStore.unreadCount > 0"
          @click="handleMarkAllRead"
          class="px-4 py-2 border border-neutral-ivory hover:bg-neutral-background rounded-xl text-sm font-semibold text-neutral-black shadow-soft transition-colors cursor-pointer"
        >
          Mark all as read
        </button>
        <router-link
          to="/settings/notifications"
          class="px-4 py-2 bg-primary hover:bg-secondary rounded-xl text-sm font-bold text-white shadow-soft transition-colors text-center"
        >
          Settings
        </router-link>
      </div>
    </div>

    <!-- Filters & Tabs Grid -->
    <div class="bg-white p-4 rounded-2xl border border-neutral-ivory shadow-soft flex flex-col md:flex-row md:items-center justify-between gap-4">
      <!-- Tabs -->
      <div class="flex p-1 bg-neutral-background rounded-xl border border-neutral-ivory self-start">
        <button
          @click="activeTab = 'all'"
          class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer"
          :class="activeTab === 'all' ? 'bg-white text-neutral-black shadow-sm' : 'text-neutral-muted hover:text-neutral-black'"
        >
          All
        </button>
        <button
          @click="activeTab = 'unread'"
          class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
          :class="activeTab === 'unread' ? 'bg-white text-neutral-black shadow-sm' : 'text-neutral-muted hover:text-neutral-black'"
        >
          Unread
          <span
            v-if="notificationsStore.unreadCount > 0"
            class="bg-primary text-white text-[10px] px-1.5 py-0.5 rounded-full font-mono font-bold"
          >
            {{ notificationsStore.unreadCount }}
          </span>
        </button>
        <button
          @click="activeTab = 'read'"
          class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer"
          :class="activeTab === 'read' ? 'bg-white text-neutral-black shadow-sm' : 'text-neutral-muted hover:text-neutral-black'"
        >
          Read
        </button>
      </div>

      <!-- Search & Category Filters -->
      <div class="flex flex-col sm:flex-row gap-3 md:w-auto w-full">
        <!-- Search -->
        <div class="relative flex-grow sm:w-64">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-neutral-muted">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search notifications..."
            class="w-full pl-9 pr-4 py-2 border border-neutral-ivory rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20"
          />
        </div>

        <!-- Category Dropdown -->
        <select
          v-model="selectedCategory"
          class="px-3 py-2 border border-neutral-ivory rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white"
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

    <!-- Notification List Area -->
    <div v-if="notificationsStore.loading && notificationsStore.notifications.length === 0" class="py-12 flex justify-center">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
    </div>

    <div v-else-if="notificationsStore.notifications.length === 0" class="bg-white rounded-2xl border border-neutral-ivory py-16 text-center shadow-soft">
      <svg class="h-12 w-12 mx-auto text-neutral-muted/40 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
      </svg>
      <h3 class="font-bold text-neutral-black text-base">No Notifications</h3>
      <p class="text-sm text-neutral-muted mt-1">We couldn't find any notifications matching your filters.</p>
    </div>

    <div v-else class="space-y-6">
      <NotificationList
        :items="notificationsStore.notifications"
        @view-details="handleViewDetails"
      />

      <!-- Pagination Footer -->
      <div
        v-if="notificationsStore.pagination.lastPage > 1"
        class="bg-white px-4 py-3 rounded-2xl border border-neutral-ivory shadow-soft flex items-center justify-between gap-4"
      >
        <span class="text-xs text-neutral-muted">
          Showing page {{ notificationsStore.pagination.currentPage }} of {{ notificationsStore.pagination.lastPage }}
        </span>
        <div class="flex gap-2">
          <button
            @click="handlePageChange(notificationsStore.pagination.currentPage - 1)"
            :disabled="notificationsStore.pagination.currentPage === 1"
            class="px-3 py-1.5 text-xs border border-neutral-ivory rounded-lg font-bold text-neutral-black hover:bg-neutral-background disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer"
          >
            Previous
          </button>
          <button
            @click="handlePageChange(notificationsStore.pagination.currentPage + 1)"
            :disabled="notificationsStore.pagination.currentPage === notificationsStore.pagination.lastPage"
            class="px-3 py-1.5 text-xs border border-neutral-ivory rounded-lg font-bold text-neutral-black hover:bg-neutral-background disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer"
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
</template>
