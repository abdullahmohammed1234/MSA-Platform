<template>
  <div class="space-y-6">
    <!-- Page Hero -->
    <PageHero
      label="Member Communications"
      title="Direct Messages"
      description="Connect 1-on-1 with SFU MSA coordinators, executives, and mentors for confidential guidance and campus questions."
    >
      <template #badge>
        <span v-if="messagingStore.unreadCount > 0" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-rose-50 border border-rose-200 text-[#640c0e] text-[10.5px] font-bold">
          <span class="w-1.5 h-1.5 rounded-full bg-[#b02e32] animate-pulse"></span>
          <span>{{ messagingStore.unreadCount }} New</span>
        </span>
      </template>

      <!-- Action Toolbar -->
      <template #actions>
        <Button
          variant="primary"
          size="sm"
          class="shadow-2xs font-bold text-xs"
          @click="openNewConversationModal"
        >
          <template #left-icon>
            <Plus class="w-3.5 h-3.5" />
          </template>
          <span>New Message</span>
        </Button>

        <Button
          variant="outline"
          size="sm"
          :is-loading="messagingStore.isLoadingConversations"
          @click="() => messagingStore.fetchConversations()"
          title="Refresh messages"
          class="bg-white hover:bg-[#fffbf4] border-[#ebe8de] text-xs font-semibold shadow-2xs"
        >
          <template #left-icon>
            <RefreshCw class="w-3.5 h-3.5 text-[#b02e32]" :class="{ 'animate-spin': messagingStore.isLoadingConversations }" />
          </template>
          <span>Refresh</span>
        </Button>
      </template>
    </PageHero>

    <!-- Route-Based Tabs -->
    <RouteTabs :tabs="navTabs" aria-label="Communications and Support Navigation" />

    <!-- Error State -->
    <ErrorState
      v-if="messagingStore.error && !messagingStore.isLoadingConversations"
      title="Failed to Load Messages"
      :message="messagingStore.error"
      @retry="messagingStore.fetchConversations"
    />

    <!-- Loading State -->
    <div
      v-else-if="messagingStore.isLoadingConversations && messagingStore.conversations.length === 0"
      class="py-24 flex flex-col items-center justify-center space-y-3"
    >
      <Spinner size="lg" />
      <p class="text-xs text-neutral-500 font-medium">Loading conversation inbox...</p>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else-if="messagingStore.conversations.length === 0"
      title="No Conversations Yet"
      description="You don't have any active direct message conversations. Need help or have questions? Reach out directly to an MSA administrator."
    >
      <template #icon>
        <MessageSquare class="w-8 h-8 text-[#640c0e]" />
      </template>
      <template #action>
        <Button
          variant="primary"
          size="sm"
          class="bg-[#640c0e] text-[#ffdc83]"
          @click="openNewConversationModal"
        >
          <template #left-icon>
            <Plus class="w-3.5 h-3.5" />
          </template>
          <span>Start a Conversation</span>
        </Button>
      </template>
    </EmptyState>

    <!-- Conversation List -->
    <div v-else class="space-y-3">
      <router-link
        v-for="conv in messagingStore.conversations"
        :key="conv.uuid || conv.id"
        :to="`/app/messages/${conv.uuid || conv.id}`"
        class="block group"
      >
        <article
          class="community-card p-4 sm:p-5 bg-white border border-[#ebe8de] group-hover:border-[#640c0e]/30 group-hover:shadow-md transition-all duration-200 flex items-center justify-between gap-4"
          :class="conv.unread_count > 0 ? 'border-l-4 border-l-[#640c0e] bg-gradient-to-r from-rose-50/20 to-white' : ''"
        >
          <div class="flex items-center gap-3.5 min-w-0 flex-1">
            <!-- Avatar -->
            <div
              class="w-11 h-11 rounded-xl bg-[#640c0e] text-[#ffdc83] font-bold text-sm flex items-center justify-center shrink-0 shadow-xs ring-1 ring-[#ffdc83]/40"
            >
              {{ getInitials(conv.other_participant?.name || 'Admin') }}
            </div>

            <!-- Participant & Snippet -->
            <div class="space-y-1 min-w-0 flex-1">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm font-bold text-neutral-900 group-hover:text-[#640c0e] transition-colors truncate">
                  {{ conv.other_participant?.name || 'MSA Administrator' }}
                </span>
                <Badge
                  :variant="conv.other_participant?.is_admin ? 'gold' : 'neutral'"
                  size="sm"
                >
                  {{ conv.other_participant?.role_label || 'Administrator' }}
                </Badge>
              </div>

              <p class="text-xs text-neutral-500 truncate max-w-xl">
                {{ conv.last_message_preview || 'No messages yet in this conversation.' }}
              </p>
            </div>
          </div>

          <!-- Metadata & Unread Count -->
          <div class="flex flex-col items-end gap-1.5 shrink-0">
            <span class="text-[11px] text-neutral-400 font-medium">
              {{ formatRelativeTime(conv.last_message_at || conv.created_at) }}
            </span>
            <div class="flex items-center gap-2">
              <span
                v-if="conv.unread_count > 0"
                class="px-2 py-0.5 rounded-full bg-[#b02e32] text-white text-[11px] font-bold"
              >
                {{ conv.unread_count }}
              </span>
              <ChevronRight class="w-4 h-4 text-neutral-400 group-hover:text-[#640c0e] group-hover:translate-x-0.5 transition-all" />
            </div>
          </div>
        </article>
      </router-link>

      <!-- Pagination -->
      <div
        v-if="messagingStore.pagination && messagingStore.pagination.last_page > 1"
        class="flex items-center justify-between pt-4"
      >
        <Button
          variant="outline"
          size="sm"
          :disabled="messagingStore.pagination.current_page <= 1"
          @click="messagingStore.fetchConversations(messagingStore.pagination.current_page - 1)"
        >
          Previous
        </Button>
        <span class="text-xs text-neutral-500">
          Page {{ messagingStore.pagination.current_page }} of {{ messagingStore.pagination.last_page }}
        </span>
        <Button
          variant="outline"
          size="sm"
          :disabled="messagingStore.pagination.current_page >= messagingStore.pagination.last_page"
          @click="messagingStore.fetchConversations(messagingStore.pagination.current_page + 1)"
        >
          Next
        </Button>
      </div>
    </div>

    <!-- Start New Conversation Modal -->
    <Modal
      v-model="isModalOpen"
      title="Contact MSA Administration"
      subtitle="Select an authorized MSA administrator to start a private conversation."
    >
      <div class="space-y-4">
        <!-- Search filter for administrators -->
        <div>
          <label class="block text-xs font-bold text-neutral-700 mb-1">
            Search Administrators
          </label>
          <input
            v-model="adminSearch"
            type="text"
            placeholder="Search by administrator name or role..."
            class="w-full px-3.5 py-2 rounded-xl border border-[#ebe8de] text-xs focus:outline-none focus:ring-2 focus:ring-[#640c0e]/20"
          />
        </div>

        <!-- Administrator Selector List -->
        <div>
          <label class="block text-xs font-bold text-neutral-700 mb-1.5">
            Available Administrators ({{ filteredAdmins.length }})
          </label>

          <div
            v-if="messagingStore.isLoadingAdmins"
            class="py-8 flex justify-center items-center"
          >
            <Spinner size="md" />
          </div>

          <div
            v-else-if="filteredAdmins.length === 0"
            class="py-6 text-center text-xs text-neutral-500"
          >
            No administrators found matching your query.
          </div>

          <div
            v-else
            class="max-h-56 overflow-y-auto space-y-2 pr-1"
          >
            <button
              v-for="admin in filteredAdmins"
              :key="admin.id"
              type="button"
              @click="selectedAdminId = admin.id"
              class="w-full text-left p-3 rounded-xl border transition-all duration-150 flex items-center justify-between gap-3 cursor-pointer"
              :class="selectedAdminId === admin.id
                ? 'border-[#640c0e] bg-[#fffbf4] ring-1 ring-[#640c0e]'
                : 'border-[#ebe8de] hover:border-neutral-300 bg-white'"
            >
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-[#640c0e] text-[#ffdc83] font-bold text-xs flex items-center justify-center shrink-0">
                  {{ getInitials(admin.name) }}
                </div>
                <div class="truncate">
                  <div class="text-xs font-bold text-neutral-900 truncate">
                    {{ admin.name }}
                  </div>
                  <div class="text-[11px] text-neutral-500 truncate">
                    {{ admin.role_label }}
                  </div>
                </div>
              </div>
              <Check
                v-if="selectedAdminId === admin.id"
                class="w-4 h-4 text-[#640c0e] shrink-0"
              />
            </button>
          </div>
        </div>

        <!-- Initial Message (Optional) -->
        <div>
          <label class="block text-xs font-bold text-neutral-700 mb-1">
            Initial Message (Optional)
          </label>
          <textarea
            v-model="initialMessage"
            rows="3"
            maxlength="5000"
            placeholder="Assalamu alaikum, I would like to ask about..."
            class="w-full px-3.5 py-2 rounded-xl border border-[#ebe8de] text-xs focus:outline-none focus:ring-2 focus:ring-[#640c0e]/20 resize-none"
          ></textarea>
        </div>

        <!-- Modal Error Message -->
        <div
          v-if="modalError"
          class="p-3 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700"
        >
          {{ modalError }}
        </div>
      </div>

      <template #footer>
        <Button
          variant="outline"
          size="sm"
          @click="isModalOpen = false"
        >
          Cancel
        </Button>
        <Button
          variant="primary"
          size="sm"
          class="bg-[#640c0e] text-[#ffdc83]"
          :disabled="!selectedAdminId || messagingStore.isCreating"
          @click="handleStartConversation"
        >
          <Spinner v-if="messagingStore.isCreating" size="sm" class="mr-1.5" />
          <span>{{ messagingStore.isCreating ? 'Starting...' : 'Start Conversation' }}</span>
        </Button>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useMessagingStore } from '@/stores/messaging';
import { useSupportStore } from '@/stores/support';
import PageHero from '@/components/ui/PageHero.vue';
import RouteTabs, { type RouteTabItem } from '@/components/ui/RouteTabs.vue';
import Button from '@/components/ui/Button.vue';
import Badge from '@/components/ui/Badge.vue';
import Spinner from '@/components/ui/Spinner.vue';
import Modal from '@/components/ui/Modal.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import {
  MessageSquare,
  MessageSquareText,
  MessageSquarePlus,
  Plus,
  ChevronRight,
  Check,
  ShieldAlert,
  RefreshCw,
} from 'lucide-vue-next';

const router = useRouter();
const authStore = useAuthStore();
const messagingStore = useMessagingStore();
const supportStore = useSupportStore();

const canAccessStaffTriage = computed(() => {
  return authStore.isAdmin;
});

const navTabs = computed<RouteTabItem[]>(() => {
  const tabs: RouteTabItem[] = [
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

const isModalOpen = ref(false);
const selectedAdminId = ref<number | null>(null);
const initialMessage = ref('');
const adminSearch = ref('');
const modalError = ref<string | null>(null);

const filteredAdmins = computed(() => {
  const query = adminSearch.value.trim().toLowerCase();
  const currentUserId = authStore.user?.id;
  const currentUuid = authStore.user?.uuid;
  const list = messagingStore.availableAdmins.filter(
    (a) => a.id !== currentUserId && a.uuid !== currentUuid
  );
  if (!query) return list;
  return list.filter(
    (a) => a.name.toLowerCase().includes(query) || a.role_label.toLowerCase().includes(query)
  );
});

onMounted(async () => {
  await messagingStore.fetchConversations();
  await messagingStore.fetchUnreadCount();
});

async function openNewConversationModal() {
  selectedAdminId.value = null;
  initialMessage.value = '';
  modalError.value = null;
  adminSearch.value = '';
  isModalOpen.value = true;
  await messagingStore.fetchAvailableAdmins();
}

async function handleStartConversation() {
  if (!selectedAdminId.value) {
    modalError.value = 'Please select an administrator to contact.';
    return;
  }
  if (authStore.user?.id && selectedAdminId.value === authStore.user.id) {
    modalError.value = 'You cannot start a conversation with yourself.';
    return;
  }
  modalError.value = null;

  const conv = await messagingStore.createConversation({
    recipient_id: selectedAdminId.value,
    message: initialMessage.value.trim() || undefined,
  });

  if (conv) {
    isModalOpen.value = false;
    router.push(`/app/messages/${conv.uuid || conv.id}`);
  } else {
    modalError.value = messagingStore.error || 'Failed to start conversation.';
  }
}

function getInitials(name: string): string {
  if (!name) return 'U';
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join('');
}

function formatRelativeTime(dateString?: string | null): string {
  if (!dateString) return '';
  const date = new Date(dateString);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMinutes = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMinutes / 60);
  const diffDays = Math.floor(diffHours / 24);

  if (diffMinutes < 1) return 'Just now';
  if (diffMinutes < 60) return `${diffMinutes}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays === 1) return 'Yesterday';
  if (diffDays < 7) return `${diffDays}d ago`;

  return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}
</script>
