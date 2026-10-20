<template>
  <div class="space-y-6">
    <!-- Page Hero -->
    <PageHero
      label="Leadership & Triage"
      title="Staff Triage & Operations"
      description="Authorized inbox to review, assign, and respond to member inquiries, spiritual connections, and accommodation requests."
    >
      <template #badge>
        <span v-if="stats?.unread_by_staff" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-rose-50 border border-rose-200 text-[#640c0e] text-[10.5px] font-bold">
          <span class="w-1.5 h-1.5 rounded-full bg-[#b02e32] animate-pulse"></span>
          <span>{{ stats.unread_by_staff }} Unread by Staff</span>
        </span>
      </template>

      <template #actions>
        <Button
          variant="outline"
          size="sm"
          :is-loading="supportStore.isLoading"
          @click="loadData"
          title="Refresh triage data"
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

    <!-- Stats KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
      <div class="p-4 rounded-2xl bg-white border border-[#ebe8de] shadow-2xs space-y-1">
        <div class="flex items-center justify-between text-xs text-neutral-500">
          <span>Open</span>
          <Inbox class="w-4 h-4 text-amber-600" />
        </div>
        <div class="text-xl sm:text-2xl font-extrabold text-[#640c0e]">
          {{ stats?.open ?? 0 }}
        </div>
      </div>

      <div class="community-card p-4 bg-white border border-[#ebe8de] space-y-1">
        <div class="flex items-center justify-between text-xs text-neutral-500">
          <span>In Progress</span>
          <Clock class="w-4 h-4 text-sky-600" />
        </div>
        <div class="text-xl sm:text-2xl font-extrabold text-sky-700">
          {{ stats?.in_progress ?? 0 }}
        </div>
      </div>

      <div class="community-card p-4 bg-white border border-[#ebe8de] space-y-1">
        <div class="flex items-center justify-between text-xs text-neutral-500">
          <span>Resolved</span>
          <CheckCircle2 class="w-4 h-4 text-emerald-600" />
        </div>
        <div class="text-xl sm:text-2xl font-extrabold text-emerald-700">
          {{ stats?.resolved ?? 0 }}
        </div>
      </div>

      <div class="community-card p-4 bg-white border border-[#ebe8de] space-y-1">
        <div class="flex items-center justify-between text-xs text-neutral-500">
          <span>Unread Staff</span>
          <AlertCircle class="w-4 h-4 text-rose-600" />
        </div>
        <div class="text-xl sm:text-2xl font-extrabold text-[#b02e32]">
          {{ stats?.unread_by_staff ?? 0 }}
        </div>
      </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="community-card p-4 sm:p-5 bg-white border border-[#ebe8de] space-y-3">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <!-- Search Input -->
        <div class="relative">
          <Search class="w-4 h-4 text-neutral-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search inquiries, member name, code..."
            class="w-full pl-9 pr-3 py-2 rounded-xl border border-[#ebe8de] bg-[#fffbf4]/40 text-xs text-neutral-800 focus:outline-none focus:ring-2 focus:ring-[#640c0e]/20 focus:border-[#640c0e]"
            @keyup.enter="handleFilterChange"
          />
        </div>

        <!-- Status Filter -->
        <div>
          <select
            v-model="statusFilter"
            class="w-full px-3 py-2 rounded-xl border border-[#ebe8de] bg-[#fffbf4]/40 text-xs text-neutral-800 focus:outline-none focus:ring-2 focus:ring-[#640c0e]/20 focus:border-[#640c0e]"
            @change="handleFilterChange"
          >
            <option value="">All Statuses</option>
            <option value="open">Open</option>
            <option value="in_progress">In Progress</option>
            <option value="resolved">Resolved</option>
            <option value="closed">Closed</option>
          </select>
        </div>

        <!-- Category Filter -->
        <div>
          <select
            v-model="categoryFilter"
            class="w-full px-3 py-2 rounded-xl border border-[#ebe8de] bg-[#fffbf4]/40 text-xs text-neutral-800 focus:outline-none focus:ring-2 focus:ring-[#640c0e]/20 focus:border-[#640c0e]"
            @change="handleFilterChange"
          >
            <option value="">All Categories</option>
            <option value="new_muslim">New Muslim Support</option>
            <option value="faith_questions">Faith & Spiritual Questions</option>
            <option value="campus_prayer">Campus & Prayer Resources</option>
            <option value="academic">Academic & Exam Adjustments</option>
            <option value="event_support">Event & Registration Support</option>
            <option value="general">General Support</option>
            <option value="feedback">Feedback</option>
            <option value="other">Other</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Inquiries List / Triage Table -->
    <div v-if="supportStore.staffInquiries.length > 0" class="space-y-3">
      <article
        v-for="item in supportStore.staffInquiries"
        :key="item.id"
        class="community-card p-4 sm:p-5 bg-white border transition-all flex flex-col lg:flex-row lg:items-center justify-between gap-4"
        :class="hasStaffUnread(item) ? 'border-l-4 border-l-[#b02e32] bg-gradient-to-r from-rose-50/30 to-white' : 'border-[#ebe8de]'"
      >
        <div class="space-y-2 flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap text-xs">
            <span
              v-if="hasStaffUnread(item)"
              class="w-2 h-2 rounded-full bg-[#b02e32] shrink-0 ring-2 ring-rose-200"
              title="Unread by staff"
            ></span>

            <span class="font-mono font-semibold text-neutral-500 text-[11px]">
              #{{ item.reference_code }}
            </span>

            <Badge :variant="getCategoryBadgeVariant(item.category)" size="sm">
              {{ formatCategory(item.category) }}
            </Badge>

            <Badge :variant="getStatusBadgeVariant(item.status)" size="sm">
              {{ formatStatus(item.status) }}
            </Badge>

            <span class="text-[11px] text-neutral-500 font-medium">
              From: <strong class="text-neutral-800">{{ item.user?.name || 'Member' }}</strong> ({{ item.user?.email || 'N/A' }})
            </span>
          </div>

          <h2 class="text-sm sm:text-base font-bold text-[#640c0e] truncate">
            {{ item.subject }}
          </h2>

          <p class="text-xs text-neutral-600 line-clamp-2 leading-relaxed">
            <span v-if="item.last_message?.is_staff_reply" class="font-semibold text-neutral-500">
              Staff:
            </span>
            <span v-else class="font-semibold text-[#b02e32]">
              Member:
            </span>
            {{ item.last_message?.body || 'No messages' }}
          </p>

          <div class="flex items-center gap-4 text-[11px] text-neutral-400">
            <span>Last activity: {{ formatDateTime(item.last_activity_at || item.created_at) }}</span>
            <span v-if="item.assigned_staff" class="text-neutral-600">
              Assigned to: <strong>{{ item.assigned_staff.name }}</strong>
            </span>
          </div>
        </div>

        <!-- Triage Action Controls -->
        <div class="flex items-center gap-2 shrink-0 pt-3 lg:pt-0 border-t lg:border-t-0 border-[#ebe8de]/60 flex-wrap">
          <!-- Status Dropdown Quick Change -->
          <select
            :value="item.status"
            @change="(e) => handleStatusChange(item.reference_code, (e.target as HTMLSelectElement).value)"
            class="px-2.5 py-1.5 rounded-lg border border-[#ebe8de] text-xs bg-white text-neutral-700 font-medium focus:ring-2 focus:ring-[#640c0e]/20"
          >
            <option value="open">Open</option>
            <option value="in_review">In Review</option>
            <option value="awaiting_member">Awaiting Member</option>
            <option value="awaiting_staff">Awaiting Staff</option>
            <option value="resolved">Resolved</option>
            <option value="closed">Closed</option>
          </select>

          <!-- Open Thread Button -->
          <Button
            variant="primary"
            size="sm"
            @click="openTriageDetail(item.reference_code)"
          >
            <template #left-icon>
              <MessageSquare class="w-3.5 h-3.5" />
            </template>
            <span>Respond</span>
          </Button>
        </div>
      </article>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else-if="!supportStore.isLoading"
      title="No Inquiries in Triage"
      description="There are currently no member support inquiries matching your selected filters."
    >
      <template #icon>
        <CheckCircle2 class="w-8 h-8 text-emerald-600" />
      </template>
    </EmptyState>

    <!-- Staff Triage Modal / Drawer -->
    <Modal
      v-if="selectedInquiry"
      :model-value="!!selectedInquiry"
      title="Staff Inquiry Triage"
      @close="closeTriageModal"
    >
      <div class="space-y-4 max-h-[70vh] overflow-y-auto p-1">
        <!-- Thread Meta -->
        <div class="p-3.5 rounded-xl bg-[#fffbf4] border border-[#ebe8de] space-y-1.5 text-xs">
          <div class="flex items-center justify-between">
            <span class="font-mono font-bold text-neutral-600">#{{ selectedInquiry.reference_code }}</span>
            <Badge :variant="getStatusBadgeVariant(selectedInquiry.status)" size="sm">
              {{ formatStatus(selectedInquiry.status) }}
            </Badge>
          </div>
          <h3 class="font-bold text-[#640c0e] text-sm">{{ selectedInquiry.subject }}</h3>
          <p class="text-neutral-500">
            Member: <strong>{{ selectedInquiry.user?.name }}</strong> ({{ selectedInquiry.user?.email }})
          </p>
        </div>

        <!-- Message History -->
        <div class="space-y-3 p-2 bg-neutral-50 rounded-xl border border-neutral-200/80 max-h-60 overflow-y-auto">
          <div
            v-for="msg in selectedInquiry.messages"
            :key="msg.id"
            class="p-3 rounded-lg text-xs space-y-1"
            :class="msg.is_staff_reply ? 'bg-white border border-neutral-200' : 'bg-rose-50 border border-rose-200/80'"
          >
            <div class="flex items-center justify-between font-semibold"
              :class="msg.is_staff_reply ? 'text-[#b02e32]' : 'text-neutral-700'"
            >
              <span>{{ msg.is_staff_reply ? (msg.sender_name || (msg as any).user?.name || 'Staff') : (selectedInquiry.user?.name || msg.sender_name || (msg as any).user?.name || 'Member') }}</span>
              <span class="text-[10px] text-neutral-400 font-normal">{{ formatDateTime(msg.created_at) }}</span>
            </div>
            <p class="text-neutral-800 whitespace-pre-wrap">{{ msg.body || (msg as any).message }}</p>
          </div>
        </div>

        <!-- Staff Reply Form -->
        <div class="space-y-2 pt-2">
          <label class="block text-xs font-bold text-neutral-700">
            Send Official Staff Reply
          </label>
          <textarea
            v-model="staffReplyText"
            rows="3"
            placeholder="Type your response to the member... (they will be notified via in-app notification)"
            class="w-full px-3 py-2 rounded-xl border border-[#ebe8de] text-xs text-neutral-800 focus:outline-none focus:ring-2 focus:ring-[#640c0e]/20"
          ></textarea>
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-between w-full">
          <router-link
            :to="`/app/support/${selectedInquiry.reference_code}`"
            class="text-xs text-[#b02e32] hover:underline font-semibold"
          >
            Open in Full Conversation View
          </router-link>

          <div class="flex items-center gap-2">
            <Button variant="outline" size="sm" @click="closeTriageModal">
              Close
            </Button>
            <Button
              variant="primary"
              size="sm"
              :disabled="!staffReplyText.trim() || isSendingStaffReply"
              :is-loading="isSendingStaffReply"
              @click="submitStaffReply"
            >
              Send Staff Reply
            </Button>
          </div>
        </div>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useSupportStore } from '@/stores/support';
import { useMessagingStore } from '@/stores/messaging';
import PageHero from '@/components/ui/PageHero.vue';
import RouteTabs, { type RouteTabItem } from '@/components/ui/RouteTabs.vue';
import { formatEventDateTime } from '@/utils/formatters';
import type { SupportInquiry, InquiryStatus } from '@/types/support';
import Button from '@/components/ui/Button.vue';
import Badge from '@/components/ui/Badge.vue';
import Modal from '@/components/ui/Modal.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import {
  Inbox,
  Clock,
  CheckCircle2,
  AlertCircle,
  Search,
  MessageSquare,
  MessageSquareText,
  MessageSquarePlus,
  ShieldAlert,
  RefreshCw,
} from 'lucide-vue-next';

import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const authStore = useAuthStore();
const supportStore = useSupportStore();
const messagingStore = useMessagingStore();

const navTabs = computed<RouteTabItem[]>(() => [
  {
    to: '/app/support',
    label: 'Support Inquiries',
    icon: MessageSquareText,
    badge: supportStore.memberUnreadCount,
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
  {
    to: '/app/support/staff',
    label: 'Staff Triage',
    icon: ShieldAlert,
  },
]);

const statusFilter = ref('');
const categoryFilter = ref('');
const searchQuery = ref('');

const selectedInquiry = ref<SupportInquiry | null>(null);
const staffReplyText = ref('');
const isSendingStaffReply = ref(false);

const stats = computed(() => supportStore.staffStats);

function hasStaffUnread(item: SupportInquiry): boolean {
  return (item.unread_staff_messages_count ?? 0) > 0 || !item.is_read_by_staff;
}

function formatCategory(category: string): string {
  if (!category) return 'General';
  const clean = category.replace(/_/g, ' ');
  return clean.charAt(0).toUpperCase() + clean.slice(1);
}

function formatStatus(status: string): string {
  if (status === 'in_progress' || status === 'in_review') return 'In Progress';
  if (status === 'awaiting_member') return 'Awaiting Member';
  if (status === 'awaiting_staff') return 'Awaiting Staff';
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
    case 'in_review':
      return 'blue';
    case 'awaiting_member':
      return 'burgundy';
    case 'awaiting_staff':
      return 'gold';
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

async function loadData() {
  await Promise.all([
    supportStore.fetchStaffInquiries({
      status: statusFilter.value || undefined,
      category: categoryFilter.value || undefined,
      search: searchQuery.value || undefined,
    }),
    supportStore.fetchStaffStats(),
  ]);
}

function handleFilterChange() {
  loadData();
}

async function handleStatusChange(referenceCode: string, newStatus: string) {
  await supportStore.updateStaffStatus(referenceCode, newStatus as InquiryStatus);
  await supportStore.fetchStaffStats();
}

async function openTriageDetail(referenceCode: string) {
  const inq = await supportStore.fetchStaffInquiry(referenceCode);
  if (inq) {
    selectedInquiry.value = inq;
    staffReplyText.value = '';
  }
}

function closeTriageModal() {
  selectedInquiry.value = null;
  staffReplyText.value = '';
}

async function submitStaffReply() {
  if (!selectedInquiry.value || !staffReplyText.value.trim()) return;

  isSendingStaffReply.value = true;
  try {
    const updated = await supportStore.sendStaffReply(
      selectedInquiry.value.reference_code,
      staffReplyText.value.trim()
    );
    selectedInquiry.value = updated;
    staffReplyText.value = '';
    await loadData();
  } finally {
    isSendingStaffReply.value = false;
  }
}

onMounted(() => {
  if (!authStore.isAdmin) {
    router.replace('/app/support');
    return;
  }
  loadData();
});
</script>
