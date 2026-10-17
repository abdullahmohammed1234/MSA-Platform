<template>
  <div class="space-y-6">
    <!-- Page Hero -->
    <PageHero
      label="Member Alerts"
      title="Alerts & Updates"
      description="Official campus announcements, event reminders, ticket confirmations, and verified MSA updates."
    >
      <template #badge>
        <span v-if="notificationsStore.unreadCount > 0" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-rose-50 border border-rose-200 text-[#640c0e] text-[10.5px] font-bold">
          <span class="w-1.5 h-1.5 rounded-full bg-[#b02e32] animate-pulse"></span>
          <span>{{ notificationsStore.unreadCount }} Unread</span>
        </span>
      </template>

      <!-- Action Toolbar -->
      <template #actions>
        <!-- Mark All As Read Button -->
        <Button
          v-if="notificationsStore.unreadCount > 0"
          variant="outline"
          size="sm"
          :is-loading="notificationsStore.isMarkingAllRead"
          :disabled="notificationsStore.isMarkingAllRead"
          @click="handleMarkAllAsRead"
          class="bg-white hover:bg-[#fffbf4] border-[#ebe8de] text-xs font-semibold shadow-2xs"
        >
          <template #left-icon>
            <CheckCheck class="w-3.5 h-3.5 text-emerald-700" />
          </template>
          <span>Mark All Read</span>
        </Button>

        <!-- Refresh Button -->
        <Button
          variant="outline"
          size="sm"
          :is-loading="notificationsStore.isLoading"
          @click="handleRefresh"
          title="Refresh notifications"
          class="bg-white hover:bg-[#fffbf4] border-[#ebe8de] text-xs font-semibold shadow-2xs"
        >
          <template #left-icon>
            <RefreshCw
              class="w-3.5 h-3.5 text-[#b02e32]"
              :class="{ 'animate-spin': notificationsStore.isLoading }"
            />
          </template>
          <span>Refresh</span>
        </Button>
      </template>
    </PageHero>

    <!-- Route-Based Tabs -->
    <RouteTabs :tabs="navTabs" aria-label="Notifications Navigation" />

    <!-- TAB CONTENT: Dedicated Alerts & Updates List Only -->
    <div class="space-y-6">
      <!-- Filters & Search Toolbar -->
      <div class="community-card p-4 sm:p-5 bg-white border border-[#ebe8de] space-y-4">
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
          <!-- Search Input -->
          <div class="relative flex-1">
            <Search class="w-4 h-4 text-neutral-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
            <input
              v-model="notificationsStore.searchQuery"
              type="text"
              placeholder="Search notification messages..."
              class="w-full pl-10 pr-9 py-2 rounded-xl border border-[#ebe8de] bg-[#fffbf4]/50 text-xs text-neutral-800 placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-[#640c0e]/20 focus:border-[#640c0e] transition-colors"
              @keyup.enter="handleSearch"
            />
            <button
              v-if="notificationsStore.searchQuery"
              type="button"
              @click="clearSearch"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-700 cursor-pointer"
              aria-label="Clear search"
            >
              <X class="w-3.5 h-3.5" />
            </button>
          </div>

          <!-- Unread / All Filter Toggle -->
          <div class="flex items-center gap-1.5 p-1 rounded-xl bg-[#fffbf4] border border-[#ebe8de] self-start sm:self-auto shrink-0">
            <button
              type="button"
              @click="setFilterMode('all')"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer"
              :class="notificationsStore.filterMode === 'all'
                ? 'bg-[#640c0e] text-white shadow-2xs'
                : 'text-neutral-600 hover:text-neutral-900'"
            >
              All Alerts
            </button>
            <button
              type="button"
              @click="setFilterMode('unread')"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5 cursor-pointer"
              :class="notificationsStore.filterMode === 'unread'
                ? 'bg-[#640c0e] text-white shadow-2xs'
                : 'text-neutral-600 hover:text-neutral-900'"
            >
              <span>Unread</span>
              <span
                v-if="notificationsStore.unreadCount > 0"
                class="px-1.5 py-0.2 rounded-full text-[10px] font-bold"
                :class="notificationsStore.filterMode === 'unread' ? 'bg-[#ffdc83] text-[#640c0e]' : 'bg-[#b02e32] text-white'"
              >
                {{ notificationsStore.unreadCount > 9 ? '9+' : notificationsStore.unreadCount }}
              </span>
            </button>
          </div>
        </div>
      </div>

      <!-- Error State -->
      <ErrorState
        v-if="notificationsStore.error"
        title="Failed to Load Notifications"
        :message="notificationsStore.error"
        @retry="handleRefresh"
      />

      <!-- Loading State -->
      <div
        v-else-if="notificationsStore.isLoading && notificationsStore.notifications.length === 0"
        class="py-16 flex flex-col items-center justify-center space-y-3"
      >
        <Spinner size="lg" />
        <p class="text-xs text-neutral-500 font-medium">Loading notifications...</p>
      </div>

      <!-- Empty State -->
      <EmptyState
        v-else-if="notificationsStore.notifications.length === 0"
        :title="notificationsStore.filterMode === 'unread' ? 'No Unread Notifications' : 'No Notifications Found'"
        :description="notificationsStore.filterMode === 'unread'
          ? 'You are all caught up! There are no unread notifications requiring your attention.'
          : (notificationsStore.searchQuery
              ? 'No notifications match your search query.'
              : 'You have not received any notifications yet. Updates about your event passes and campus notices will appear here.')"
      >
        <template #icon>
          <Bell class="w-8 h-8 text-[#640c0e]" />
        </template>
        <template #actions v-if="notificationsStore.filterMode === 'unread'">
          <Button variant="outline" size="sm" @click="setFilterMode('all')">
            <span>View All Notifications</span>
          </Button>
        </template>
      </EmptyState>

      <!-- Notifications List -->
      <div v-else class="space-y-3">
        <article
          v-for="item in notificationsStore.notifications"
          :key="item.id"
          class="community-card p-4 sm:p-5 bg-white border transition-all duration-150 hover:shadow-xs relative"
          :class="!item.read_at ? 'border-l-4 border-l-[#b02e32] border-[#ebe8de]' : 'border-[#ebe8de] opacity-85'"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-3 min-w-0 flex-1">
              <!-- Type Icon Indicator -->
              <div
                class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl flex items-center justify-center shrink-0 mt-0.5"
                :class="!item.read_at ? 'bg-rose-50 text-[#b02e32]' : 'bg-neutral-100 text-neutral-500'"
              >
                <Bell class="w-4 h-4" />
              </div>

              <div class="space-y-1 min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <Badge :variant="getNotificationBadgeVariant(item.type)" size="sm">
                    {{ formatNotificationType(item.type) }}
                  </Badge>
                  <span class="text-[11px] text-neutral-400">
                    {{ formatEventDateTime(item.created_at) }}
                  </span>
                  <span
                    v-if="!item.read_at"
                    class="inline-flex items-center gap-1 text-[10px] font-bold text-[#b02e32]"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-[#b02e32]"></span>
                    <span>New</span>
                  </span>
                </div>

                <h3 class="text-sm sm:text-base font-bold text-neutral-900 leading-snug">
                  {{ item.title }}
                </h3>

                <p class="text-xs sm:text-sm text-neutral-600 leading-relaxed whitespace-pre-line">
                  {{ item.message }}
                </p>

                <!-- Action Button if Notification contains safe target URL -->
                <div v-if="getSafeActionUrl(item.data?.action_url)" class="pt-2">
                  <router-link :to="getSafeActionUrl(item.data?.action_url)!">
                    <Button variant="outline" size="sm" class="text-xs">
                      <span>{{ item.data?.action_label || 'View Details' }}</span>
                      <template #right-icon>
                        <ArrowRight class="w-3 h-3" />
                      </template>
                    </Button>
                  </router-link>
                </div>
              </div>
            </div>

            <!-- Card Actions: Mark As Read / Delete -->
            <div class="flex items-center gap-1 shrink-0">
              <button
                v-if="!item.read_at"
                type="button"
                @click="handleMarkAsRead(item.id)"
                class="p-1.5 rounded-lg text-neutral-400 hover:text-emerald-700 hover:bg-emerald-50 transition-colors cursor-pointer"
                title="Mark as read"
                aria-label="Mark notification as read"
              >
                <Check class="w-4 h-4" />
              </button>

              <button
                type="button"
                @click="handleDelete(item.id)"
                class="p-1.5 rounded-lg text-neutral-400 hover:text-red-700 hover:bg-red-50 transition-colors cursor-pointer"
                title="Delete notification"
                aria-label="Delete notification"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </div>
          </div>
        </article>

        <!-- Pagination Controls -->
        <div
          v-if="notificationsStore.pagination && notificationsStore.pagination.last_page > 1"
          class="flex items-center justify-between pt-4 border-t border-[#ebe8de]"
        >
          <span class="text-xs text-neutral-500">
            Page {{ notificationsStore.pagination.current_page }} of {{ notificationsStore.pagination.last_page }}
          </span>

          <div class="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              :disabled="notificationsStore.pagination.current_page <= 1 || notificationsStore.isLoading"
              @click="changePage(notificationsStore.pagination.current_page - 1)"
            >
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              :disabled="notificationsStore.pagination.current_page >= notificationsStore.pagination.last_page || notificationsStore.isLoading"
              @click="changePage(notificationsStore.pagination.current_page + 1)"
            >
              Next
            </Button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useNotificationsStore } from '@/stores/notifications';
import { formatEventDateTime } from '@/utils/formatters';
import PageHero from '@/components/ui/PageHero.vue';
import RouteTabs, { type RouteTabItem } from '@/components/ui/RouteTabs.vue';
import Button from '@/components/ui/Button.vue';
import Badge from '@/components/ui/Badge.vue';
import Spinner from '@/components/ui/Spinner.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import {
  Search,
  X,
  Bell,
  Check,
  CheckCheck,
  Trash2,
  RefreshCw,
  ArrowRight,
  Sliders,
} from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();
const notificationsStore = useNotificationsStore();

const navTabs = computed<RouteTabItem[]>(() => [
  {
    to: '/app/notifications',
    label: 'Alerts & Updates',
    icon: Bell,
    badge: notificationsStore.unreadCount,
    exact: true,
  },
  {
    to: '/app/notifications/preferences',
    label: 'Delivery Preferences',
    icon: Sliders,
  },
]);

/**
 * Validate that action URLs point to safe internal application paths.
 * Defuses open redirects, external protocols, protocol-relative, and backslash evasions.
 */
function getSafeActionUrl(url: unknown): string | null {
  if (!url || typeof url !== 'string') return null;
  const trimmed = url.trim();

  // Defuse open redirect attempts
  if (
    trimmed.startsWith('//') ||
    trimmed.includes('\\') ||
    /^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(trimmed)
  ) {
    return null;
  }

  // Must begin with single forward slash
  if (!trimmed.startsWith('/')) {
    return null;
  }

  return trimmed;
}

function formatNotificationType(type: string): string {
  if (!type) return 'Update';
  const clean = type.replace(/_/g, ' ');
  return clean.charAt(0).toUpperCase() + clean.slice(1);
}

function getNotificationBadgeVariant(type: string): 'burgundy' | 'gold' | 'green' | 'neutral' | 'red' | 'blue' {
  const lower = (type || '').toLowerCase();
  if (lower.includes('event') || lower.includes('rsvp') || lower.includes('ticket')) return 'burgundy';
  if (lower.includes('announcement') || lower.includes('prayer')) return 'gold';
  if (lower.includes('course') || lower.includes('certificate')) return 'blue';
  if (lower.includes('support') || lower.includes('inquiry')) return 'gold';
  if (lower.includes('urgent') || lower.includes('warning') || lower.includes('cancel')) return 'red';
  return 'neutral';
}

function setFilterMode(mode: 'all' | 'unread') {
  notificationsStore.filterMode = mode;
  notificationsStore.fetchNotifications(1);
}

function handleSearch() {
  notificationsStore.fetchNotifications(1);
}

function clearSearch() {
  notificationsStore.searchQuery = '';
  notificationsStore.fetchNotifications(1);
}

function changePage(page: number) {
  notificationsStore.fetchNotifications(page);
}

async function handleMarkAsRead(id: string | number) {
  await notificationsStore.markAsRead(id);
}

async function handleMarkAllAsRead() {
  await notificationsStore.markAllAsRead();
}

async function handleDelete(id: string | number) {
  await notificationsStore.deleteNotification(id);
}

function handleRefresh() {
  notificationsStore.fetchNotifications();
  notificationsStore.fetchUnreadCount();
}

function handleVisibilityChange() {
  if (document.visibilityState === 'visible') {
    notificationsStore.fetchUnreadCount();
  }
}

onMounted(() => {
  // Backward compatibility: If accessed with ?tab=preferences, navigate to dedicated route
  if (route.query.tab === 'preferences') {
    router.replace('/app/notifications/preferences');
    return;
  }

  notificationsStore.fetchNotifications();
  notificationsStore.fetchUnreadCount();

  document.addEventListener('visibilitychange', handleVisibilityChange);
});

onUnmounted(() => {
  document.removeEventListener('visibilitychange', handleVisibilityChange);
});
</script>
