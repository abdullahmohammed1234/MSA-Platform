<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { projectsApi } from '../../api/projects';
import type { CalendarFeed } from '../../types/pm';
import {
  X,
  Loader2,
  Calendar,
  Rss,
  Download,
  Copy,
  Check,
  Trash2,
  AlertCircle,
} from 'lucide-vue-next';

const props = withDefaults(
  defineProps<{
    isOpen: boolean;
    projectUuid?: string | null;
    projectName?: string | null;
  }>(),
  {
    projectUuid: null,
    projectName: null,
  }
);

const emit = defineEmits<{
  (e: 'close'): void;
}>();

const activeTab = ref<'webcal' | 'export'>('webcal');
const feeds = ref<CalendarFeed[]>([]);
const isLoadingFeeds = ref(false);
const isCreatingFeed = ref(false);
const revokingUuid = ref<string | null>(null);
const errorMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);

// New feed form
const feedName = ref('');
const includeTasks = ref(true);
const includeMilestones = ref(true);
const includeProjectDeadlines = ref(true);
const onlyAssignedToMe = ref(false);

// Newly created feed credentials (one-time disclosure)
const newlyCreatedFeed = ref<{
  uuid: string;
  name: string;
  webcalUrl: string;
  httpsUrl: string;
} | null>(null);

const copiedType = ref<'webcal' | 'https' | null>(null);

const loadFeeds = async () => {
  isLoadingFeeds.value = true;
  errorMessage.value = null;
  try {
    feeds.value = await projectsApi.getCalendarFeeds();
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to load feeds.';
  } finally {
    isLoadingFeeds.value = false;
  }
};

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      feedName.value = props.projectName
        ? `${props.projectName} Calendar`
        : 'SFU MSA PMS Calendar';
      newlyCreatedFeed.value = null;
      errorMessage.value = null;
      successMessage.value = null;
      loadFeeds();
    }
  },
  { immediate: true }
);

const handleCreateFeed = async () => {
  if (!feedName.value.trim()) {
    errorMessage.value = 'Please provide a feed name.';
    return;
  }

  isCreatingFeed.value = true;
  errorMessage.value = null;
  successMessage.value = null;

  try {
    const created = await projectsApi.createCalendarFeed({
      name: feedName.value.trim(),
      project_uuid: props.projectUuid || undefined,
      include_tasks: includeTasks.value,
      include_milestones: includeMilestones.value,
      include_project_deadlines: includeProjectDeadlines.value,
      only_assigned_to_me: onlyAssignedToMe.value,
    });

    newlyCreatedFeed.value = {
      uuid: created.uuid,
      name: created.name,
      webcalUrl: created.webcal_url || '',
      httpsUrl: created.https_url || '',
    };

    successMessage.value = 'Subscription link generated successfully.';
    await loadFeeds();
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to create calendar feed.';
  } finally {
    isCreatingFeed.value = false;
  }
};

const handleRevokeFeed = async (uuid: string) => {
  if (!confirm('Are you sure you want to revoke this calendar subscription? Existing calendar apps will immediately stop receiving updates.')) {
    return;
  }

  revokingUuid.value = uuid;
  try {
    await projectsApi.revokeCalendarFeed(uuid);
    if (newlyCreatedFeed.value?.uuid === uuid) {
      newlyCreatedFeed.value = null;
    }
    await loadFeeds();
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to revoke feed.');
  } finally {
    revokingUuid.value = null;
  }
};

const copyToClipboard = async (text: string, type: 'webcal' | 'https') => {
  try {
    await navigator.clipboard.writeText(text);
    copiedType.value = type;
    setTimeout(() => {
      if (copiedType.value === type) {
        copiedType.value = null;
      }
    }, 2500);
  } catch (err) {
    console.error('Failed to copy to clipboard', err);
  }
};

const exportUrl = computed(() => {
  return projectsApi.getCalendarExportUrl({
    project_uuid: props.projectUuid || undefined,
  });
});

const handleDirectDownload = () => {
  window.open(exportUrl.value, '_blank');
};
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs animate-in fade-in duration-150"
  >
    <div
      class="bg-white rounded-3xl border border-[#ebe8de] shadow-2xl max-w-xl w-full max-h-[90vh] flex flex-col overflow-hidden"
    >
      <!-- Header -->
      <div class="px-6 py-4 border-b border-[#ebe8de] flex items-center justify-between bg-[#fbfbf9]">
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-xl bg-[#640c0e]/10 flex items-center justify-center text-[#640c0e]">
            <Calendar class="w-5 h-5" />
          </div>
          <div>
            <h2 class="text-base font-extrabold text-[#1e1e1e] font-display">
              Calendar Sync & Subscriptions
            </h2>
            <p class="text-[11px] text-[#6b7280]">
              {{ projectName ? `Scoped to ${projectName}` : 'Sync deadlines to Apple, Google, or Outlook calendar' }}
            </p>
          </div>
        </div>
        <button
          type="button"
          @click="emit('close')"
          class="p-1.5 rounded-lg text-[#6b7280] hover:text-[#1e1e1e] hover:bg-[#ebe8de]/60 transition-colors"
        >
          <X class="w-4 h-4" />
        </button>
      </div>

      <!-- Navigation Tabs -->
      <div class="flex border-b border-[#ebe8de] bg-white px-6">
        <button
          type="button"
          @click="activeTab = 'webcal'"
          class="px-4 py-2.5 text-xs font-bold border-b-2 transition-colors flex items-center gap-2"
          :class="
            activeTab === 'webcal'
              ? 'border-[#640c0e] text-[#640c0e]'
              : 'border-transparent text-[#6b7280] hover:text-[#1e1e1e]'
          "
        >
          <Rss class="w-3.5 h-3.5" />
          Live Webcal Subscription
        </button>
        <button
          type="button"
          @click="activeTab = 'export'"
          class="px-4 py-2.5 text-xs font-bold border-b-2 transition-colors flex items-center gap-2"
          :class="
            activeTab === 'export'
              ? 'border-[#640c0e] text-[#640c0e]'
              : 'border-transparent text-[#6b7280] hover:text-[#1e1e1e]'
          "
        >
          <Download class="w-3.5 h-3.5" />
          Direct .ics Export
        </button>
      </div>

      <!-- Body -->
      <div class="p-6 overflow-y-auto space-y-5 text-xs">
        <!-- Error Banner -->
        <div
          v-if="errorMessage"
          class="p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 flex items-center gap-2"
        >
          <AlertCircle class="w-4 h-4 shrink-0" />
          <span>{{ errorMessage }}</span>
        </div>

        <!-- WEBCAL TAB -->
        <div v-if="activeTab === 'webcal'" class="space-y-5">
          <!-- Newly Created Feed Disclosure Banner -->
          <div
            v-if="newlyCreatedFeed"
            class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl space-y-3"
          >
            <div class="flex items-center gap-2 text-emerald-800 font-bold">
              <Check class="w-4 h-4" />
              <span>Subscription Feed Ready</span>
            </div>
            <p class="text-[11px] text-emerald-700">
              Copy the URL below and add it to your calendar application (Apple Calendar, Google Calendar, Outlook) as a <strong>Subscribed / Network Calendar</strong>.
            </p>

            <!-- Webcal URL -->
            <div class="space-y-1">
              <label class="text-[10px] font-bold uppercase tracking-wider text-emerald-800">
                Webcal URL (One-Click Subscribe)
              </label>
              <div class="flex items-center gap-1.5">
                <input
                  type="text"
                  readonly
                  :value="newlyCreatedFeed.webcalUrl"
                  class="flex-1 px-2.5 py-1.5 bg-white border border-emerald-300 rounded-lg text-[11px] font-mono text-[#1e1e1e] select-all"
                />
                <button
                  type="button"
                  @click="copyToClipboard(newlyCreatedFeed.webcalUrl, 'webcal')"
                  class="px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg flex items-center gap-1 transition-colors"
                >
                  <Check v-if="copiedType === 'webcal'" class="w-3.5 h-3.5" />
                  <Copy v-else class="w-3.5 h-3.5" />
                  <span>{{ copiedType === 'webcal' ? 'Copied' : 'Copy' }}</span>
                </button>
              </div>
            </div>

            <!-- HTTPS URL -->
            <div class="space-y-1">
              <label class="text-[10px] font-bold uppercase tracking-wider text-emerald-800">
                HTTPS URL (For Google Calendar / Web Import)
              </label>
              <div class="flex items-center gap-1.5">
                <input
                  type="text"
                  readonly
                  :value="newlyCreatedFeed.httpsUrl"
                  class="flex-1 px-2.5 py-1.5 bg-white border border-emerald-300 rounded-lg text-[11px] font-mono text-[#1e1e1e] select-all"
                />
                <button
                  type="button"
                  @click="copyToClipboard(newlyCreatedFeed.httpsUrl, 'https')"
                  class="px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg flex items-center gap-1 transition-colors"
                >
                  <Check v-if="copiedType === 'https'" class="w-3.5 h-3.5" />
                  <Copy v-else class="w-3.5 h-3.5" />
                  <span>{{ copiedType === 'https' ? 'Copied' : 'Copy' }}</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Create Feed Form -->
          <div class="p-4 bg-[#fbfbf9] rounded-2xl border border-[#ebe8de] space-y-3">
            <h3 class="font-extrabold text-[#1e1e1e] font-display text-sm">
              Generate New Subscription Feed
            </h3>

            <div>
              <label class="block text-[11px] font-bold text-[#6b7280] mb-1">
                Feed Name
              </label>
              <input
                type="text"
                v-model="feedName"
                placeholder="e.g. My SFU MSA Deadlines"
                class="w-full px-3 py-2 bg-white border border-[#ebe8de] rounded-xl text-xs text-[#1e1e1e] focus:outline-none focus:ring-2 focus:ring-[#640c0e]/20 focus:border-[#640c0e]"
              />
            </div>

            <div class="grid grid-cols-2 gap-2 pt-1">
              <label class="flex items-center gap-2 cursor-pointer text-[#1e1e1e]">
                <input
                  type="checkbox"
                  v-model="includeTasks"
                  class="rounded text-[#640c0e] focus:ring-[#640c0e]"
                />
                <span>Include Tasks</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer text-[#1e1e1e]">
                <input
                  type="checkbox"
                  v-model="includeMilestones"
                  class="rounded text-[#640c0e] focus:ring-[#640c0e]"
                />
                <span>Include Milestones</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer text-[#1e1e1e]">
                <input
                  type="checkbox"
                  v-model="includeProjectDeadlines"
                  class="rounded text-[#640c0e] focus:ring-[#640c0e]"
                />
                <span>Include Project Deadlines</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer text-[#1e1e1e]">
                <input
                  type="checkbox"
                  v-model="onlyAssignedToMe"
                  class="rounded text-[#640c0e] focus:ring-[#640c0e]"
                />
                <span>Only Tasks Assigned To Me</span>
              </label>
            </div>

            <div class="pt-2 flex justify-end">
              <button
                type="button"
                @click="handleCreateFeed"
                :disabled="isCreatingFeed"
                class="px-4 py-2 bg-[#640c0e] hover:bg-[#7d1012] text-white font-bold rounded-xl flex items-center gap-1.5 transition-colors disabled:opacity-50"
              >
                <Loader2 v-if="isCreatingFeed" class="w-3.5 h-3.5 animate-spin" />
                <Rss v-else class="w-3.5 h-3.5" />
                <span>{{ isCreatingFeed ? 'Generating...' : 'Create Subscription Feed' }}</span>
              </button>
            </div>
          </div>

          <!-- Active Feeds List -->
          <div class="space-y-2">
            <h4 class="font-extrabold text-[#6b7280] uppercase tracking-wider text-[10px]">
              Active Subscriptions ({{ feeds.length }})
            </h4>

            <div v-if="isLoadingFeeds" class="py-6 flex justify-center text-[#6b7280]">
              <Loader2 class="w-5 h-5 animate-spin text-[#640c0e]" />
            </div>

            <div
              v-else-if="feeds.length === 0"
              class="p-4 text-center text-[#6b7280] bg-[#fbfbf9] rounded-xl border border-dashed border-[#ebe8de]"
            >
              No active subscription feeds found.
            </div>

            <div v-else class="space-y-2">
              <div
                v-for="f in feeds"
                :key="f.uuid"
                class="p-3 bg-white border border-[#ebe8de] rounded-xl flex items-center justify-between gap-3 shadow-2xs"
              >
                <div class="min-w-0">
                  <div class="flex items-center gap-2">
                    <span class="font-bold text-[#1e1e1e] truncate">{{ f.name }}</span>
                    <span
                      v-if="f.project"
                      class="px-1.5 py-0.5 bg-indigo-50 text-indigo-700 rounded text-[9px] font-bold"
                    >
                      {{ f.project.code }}
                    </span>
                    <span
                      v-else
                      class="px-1.5 py-0.5 bg-gray-100 text-gray-700 rounded text-[9px] font-bold"
                    >
                      Global
                    </span>
                  </div>
                  <p class="text-[10px] text-[#6b7280] mt-0.5">
                    {{ f.only_assigned_to_me ? 'Only My Tasks' : 'All Accessible' }} ·
                    Last accessed: {{ f.last_accessed_at ? new Date(f.last_accessed_at).toLocaleDateString() : 'Never' }}
                  </p>
                </div>

                <button
                  type="button"
                  @click="handleRevokeFeed(f.uuid)"
                  :disabled="revokingUuid === f.uuid"
                  class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                  title="Revoke subscription"
                >
                  <Loader2 v-if="revokingUuid === f.uuid" class="w-4 h-4 animate-spin" />
                  <Trash2 v-else class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- DIRECT EXPORT TAB -->
        <div v-else class="space-y-4">
          <div class="p-4 bg-[#fbfbf9] rounded-2xl border border-[#ebe8de] space-y-3">
            <h3 class="font-extrabold text-[#1e1e1e] font-display text-sm">
              Snapshot Calendar Export (.ics)
            </h3>
            <p class="text-[11px] text-[#6b7280]">
              Download an RFC 5545 standardized iCalendar file for manual import into calendar software. Note that direct downloads are static snapshots and do not update automatically when tasks change.
            </p>

            <div class="pt-2">
              <button
                type="button"
                @click="handleDirectDownload"
                class="px-4 py-2 bg-[#640c0e] hover:bg-[#7d1012] text-white font-bold rounded-xl flex items-center gap-1.5 transition-colors shadow-2xs"
              >
                <Download class="w-3.5 h-3.5" />
                <span>Download pms-calendar.ics</span>
              </button>
            </div>
          </div>

          <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl text-[11px] text-amber-800 space-y-1.5">
            <div class="font-bold flex items-center gap-1.5">
              <AlertCircle class="w-3.5 h-3.5" />
              <span>Recommendation</span>
            </div>
            <p>
              For continuous real-time sync that updates automatically whenever deadlines or assignments change, use the <strong>Live Webcal Subscription</strong> tab instead.
            </p>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-6 py-3 border-t border-[#ebe8de] bg-[#fbfbf9] flex justify-end">
        <button
          type="button"
          @click="emit('close')"
          class="px-4 py-1.5 rounded-xl border border-[#ebe8de] text-xs font-bold text-[#1e1e1e] bg-white hover:bg-[#fbfbf9]"
        >
          Close
        </button>
      </div>
    </div>
  </div>
</template>
