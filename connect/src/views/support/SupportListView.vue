<template>
  <div class="space-y-6">
    <!-- Page Hero -->
    <PageHero
      label="Member Support"
      title="Support & Inquiries"
      description="Connect directly with SFU MSA coordinators or submit confidential inquiries for academic, spiritual, or campus support."
    >
      <template #badge>
        <span v-if="unreadCount > 0" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-rose-50 border border-rose-200 text-[#640c0e] text-[10.5px] font-bold">
          <span class="w-1.5 h-1.5 rounded-full bg-[#b02e32] animate-pulse"></span>
          <span>{{ unreadCount }} Unread Updates</span>
        </span>
      </template>

      <!-- Action Buttons -->
      <template #actions>
        <router-link to="/app/support/new">
          <Button variant="primary" size="sm" class="shadow-2xs">
            <template #left-icon>
              <Plus class="w-3.5 h-3.5" />
            </template>
            <span>New Inquiry</span>
          </Button>
        </router-link>

        <Button
          variant="outline"
          size="sm"
          :is-loading="supportStore.isLoading"
          @click="loadInquiries"
          title="Refresh inquiries"
          class="bg-white hover:bg-[#fffbf4] border-[#ebe8de] text-xs font-semibold shadow-2xs"
        >
          <template #left-icon>
            <RefreshCw class="w-3.5 h-3.5 text-[#b02e32]" :class="{ 'animate-spin': supportStore.isLoading }" />
          </template>
          <span>Refresh</span>
        </Button>
      </template>
    </PageHero>

    <!-- Route-Based Tabs -->
    <RouteTabs :tabs="navTabs" aria-label="Communications and Support Navigation" />

    <!-- Filter Tabs & Search Command Bar -->
    <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-[#ebe8de] shadow-2xs">
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <!-- Filter Tabs -->
        <div class="inline-flex rounded-xl bg-[#fffbf4] border border-[#ebe8de] p-1 text-xs">
          <button
            type="button"
            class="px-3.5 py-1.5 rounded-lg font-medium transition-colors cursor-pointer"
            :class="filterTab === 'all' ? 'bg-[#640c0e] text-white font-semibold shadow-xs' : 'text-neutral-600 hover:text-[#640c0e]'"
            @click="filterTab = 'all'"
          >
            All Threads ({{ allInquiries.length }})
          </button>
          <button
            type="button"
            class="px-3.5 py-1.5 rounded-lg font-medium transition-colors cursor-pointer"
            :class="filterTab === 'active' ? 'bg-[#640c0e] text-white font-semibold shadow-xs' : 'text-neutral-600 hover:text-[#640c0e]'"
            @click="filterTab = 'active'"
          >
            Active ({{ activeInquiriesCount }})
          </button>
          <button
            type="button"
            class="px-3.5 py-1.5 rounded-lg font-medium transition-colors cursor-pointer"
            :class="filterTab === 'resolved' ? 'bg-[#640c0e] text-white font-semibold shadow-xs' : 'text-neutral-600 hover:text-[#640c0e]'"
            @click="filterTab = 'resolved'"
          >
            Resolved ({{ resolvedInquiriesCount }})
          </button>
        </div>

        <!-- Search Bar -->
        <div class="relative w-full sm:w-64">
          <Search class="w-4 h-4 text-neutral-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search inquiries..."
            class="w-full pl-9 pr-3 py-1.5 rounded-xl border border-[#ebe8de] bg-[#fffbf4]/50 text-xs text-neutral-800 placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-[#640c0e]/20 focus:border-[#640c0e]"
          />
        </div>
      </div>
    </div>

    <!-- Error State -->
    <ErrorState
      v-if="supportStore.error && allInquiries.length === 0"
      title="Failed to Load Inquiries"
      :message="supportStore.error"
      @retry="loadInquiries"
    />

    <!-- Loading State -->
    <div
      v-else-if="supportStore.isLoading && allInquiries.length === 0"
      class="py-20 flex flex-col items-center justify-center space-y-3"
    >
      <Spinner size="lg" />
      <p class="text-xs text-neutral-500 font-medium">Loading your conversations...</p>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else-if="filteredInquiries.length === 0"
      :title="searchQuery ? 'No matching inquiries' : 'No Conversations Yet'"
      :description="searchQuery ? 'Try clearing your search term or filtering by a different status.' : 'Need help with campus prayer spaces, academic adjustments, spiritual guidance, or new Muslim support? Submit a confidential inquiry.'"
    >
      <template #icon>
        <MessageSquareText class="w-8 h-8 text-[#640c0e]" />
      </template>
      <template #action>
        <router-link to="/app/support/new">
          <Button variant="primary" size="sm">
            <span>Start a Conversation</span>
          </Button>
        </router-link>
      </template>
    </EmptyState>

    <!-- Inquiries List -->
    <div v-else class="space-y-3">
      <router-link
        v-for="item in filteredInquiries"
        :key="item.id"
        :to="`/app/support/${item.reference_code}`"
        class="block group"
      >
        <article
          class="community-card p-4 sm:p-5 bg-white border transition-all duration-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4 group-hover:border-[#640c0e]/40 group-hover:shadow-sm"
          :class="hasUnread(item) ? 'border-l-4 border-l-[#640c0e] bg-gradient-to-r from-rose-50/25 to-white' : 'border-[#ebe8de]'"
        >
          <!-- Left: Meta and Subject -->
          <div class="space-y-2 flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap text-xs">
              <!-- Unread Dot -->
              <span
                v-if="hasUnread(item)"
                class="w-2 h-2 rounded-full bg-[#b02e32] shrink-0 ring-2 ring-rose-200"
                title="Unread reply"
              ></span>

              <span class="font-mono text-[11px] font-semibold text-neutral-500">
                #{{ item.reference_code }}
              </span>

              <Badge :variant="getCategoryBadgeVariant(item.category)" size="sm">
                {{ formatCategory(item.category) }}
              </Badge>

              <Badge :variant="getStatusBadgeVariant(item.status)" size="sm">
                {{ formatStatus(item.status) }}
              </Badge>

              <span class="text-[11px] text-neutral-400 ml-auto sm:ml-0 font-medium">
                {{ formatDateTime(item.last_activity_at || item.created_at) }}
              </span>
            </div>

            <!-- Subject -->
            <h2 class="text-sm sm:text-base font-bold text-[#640c0e] group-hover:text-[#b02e32] transition-colors truncate">
              {{ item.subject }}
            </h2>

            <!-- Message Preview -->
            <p class="text-xs text-neutral-600 line-clamp-1 leading-relaxed">
              <span v-if="item.last_message?.is_staff_reply" class="font-semibold text-[#b02e32]">
                MSA Staff:
              </span>
              <span v-else class="font-semibold text-neutral-700">
                You:
              </span>
              {{ item.last_message?.body || 'No messages in this inquiry yet.' }}
            </p>
          </div>

          <!-- Right: Arrow & Messages Count -->
          <div class="flex items-center gap-3 shrink-0 sm:pl-4 sm:border-l sm:border-[#ebe8de]/60 text-xs text-neutral-500 justify-between sm:justify-start">
            <span class="inline-flex items-center gap-1 font-medium text-[11px]">
              <MessageCircle class="w-3.5 h-3.5 text-neutral-400" />
              <span>{{ item.messages_count ?? 1 }}</span>
            </span>

            <ArrowRight class="w-4 h-4 text-neutral-400 group-hover:text-[#640c0e] group-hover:translate-x-0.5 transition-transform" />
          </div>
        </article>
      </router-link>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useSupportStore } from '@/stores/support';
import { useAuthStore } from '@/stores/auth';
import { formatEventDateTime } from '@/utils/formatters';
import type { SupportInquiry } from '@/types/support';
import Button from '@/components/ui/Button.vue';
import Badge from '@/components/ui/Badge.vue';
import Spinner from '@/components/ui/Spinner.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import PageHero from '@/components/ui/PageHero.vue';
import RouteTabs, { type RouteTabItem } from '@/components/ui/RouteTabs.vue';
import { useMessagingStore } from '@/stores/messaging';
import {
  MessageSquareText,
  MessageSquare,
  MessageSquarePlus,
  Plus,
  Search,
  MessageCircle,
  ArrowRight,
  ShieldAlert,
  RefreshCw,
} from 'lucide-vue-next';

const supportStore = useSupportStore();
const authStore = useAuthStore();
const messagingStore = useMessagingStore();

const filterTab = ref<'all' | 'active' | 'resolved'>('all');
const searchQuery = ref('');

const allInquiries = computed(() => supportStore.inquiries);
const unreadCount = computed(() => supportStore.memberUnreadCount);

const activeInquiriesCount = computed(() => {
  return allInquiries.value.filter((i) => i.status === 'open' || i.status === 'in_progress').length;
});

const resolvedInquiriesCount = computed(() => {
  return allInquiries.value.filter((i) => i.status === 'resolved' || i.status === 'closed').length;
});

const canAccessStaffTriage = computed(() => {
  return authStore.isAdmin;
});

const navTabs = computed<RouteTabItem[]>(() => {
  const tabs: RouteTabItem[] = [
    {
      to: '/app/support',
      label: 'Support Inquiries',
      icon: MessageSquareText,
      badge: unreadCount.value,
      exact: true,
    },
    {
      to: '/app/messages',
      label: 'Direct Messages',
      icon: MessageSquare,
      badge: messagingStore.unreadCount,
    },
    {
      to: '/app/support/new',
      label: 'New Inquiry',
      icon: MessageSquarePlus,
    },
  ];

  if (canAccessStaffTriage.value) {
    tabs.push({
      to: '/app/support/staff',
      label: 'Staff Triage',
      icon: ShieldAlert,
    });
  }

  return tabs;
});

const filteredInquiries = computed(() => {
  return allInquiries.value.filter((item) => {
    // Status filter
    if (filterTab.value === 'active' && item.status !== 'open' && item.status !== 'in_progress') {
      return false;
    }
    if (filterTab.value === 'resolved' && item.status !== 'resolved' && item.status !== 'closed') {
      return false;
    }

    // Search query filter
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase().trim();
      const matchSubject = item.subject?.toLowerCase().includes(q);
      const matchRef = item.reference_code?.toLowerCase().includes(q);
      const matchCategory = item.category?.toLowerCase().includes(q);
      const matchBody = item.last_message?.body?.toLowerCase().includes(q);
      return matchSubject || matchRef || matchCategory || matchBody;
    }

    return true;
  });
});

function hasUnread(item: SupportInquiry): boolean {
  return (item.unread_member_messages_count ?? 0) > 0 || !item.is_read_by_member;
}

function formatCategory(category: string): string {
  if (!category) return 'General';
  const clean = category.replace(/_/g, ' ');
  return clean.charAt(0).toUpperCase() + clean.slice(1);
}

function formatStatus(status: string): string {
  if (status === 'in_progress') return 'In Progress';
  if (status === 'resolved') return 'Resolved';
  if (status === 'closed') return 'Closed';
  return 'Open';
}

function getCategoryBadgeVariant(category: string): 'burgundy' | 'gold' | 'green' | 'neutral' | 'blue' | 'red' {
  switch (category) {
    case 'new_muslim':
    case 'revert_support':
      return 'gold';
    case 'faith_questions':
    case 'spiritual':
      return 'burgundy';
    case 'academic':
      return 'blue';
    case 'campus_prayer':
      return 'green';
    case 'event_support':
    case 'events':
      return 'green';
    default:
      return 'neutral';
  }
}

function getStatusBadgeVariant(status: string): 'burgundy' | 'gold' | 'green' | 'neutral' | 'blue' | 'red' {
  switch (status) {
    case 'open':
      return 'gold';
    case 'in_progress':
      return 'blue';
    case 'resolved':
      return 'green';
    case 'closed':
    default:
      return 'neutral';
  }
}

function formatDateTime(dateStr: string): string {
  if (!dateStr) return '';
  return formatEventDateTime(dateStr);
}

async function loadInquiries() {
  await supportStore.fetchInquiries();
}

onMounted(() => {
  loadInquiries();
  supportStore.startPolling(); // poll member inquiries hub
});

onUnmounted(() => {
  supportStore.stopPolling();
});
</script>
