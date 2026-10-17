<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { connectAdminService } from '@/services/admin/connectAdminService';
import type { ConnectAnnouncementItem } from '@/services/admin/connectAdminService';
import {
  Megaphone,
  Search,
  ExternalLink,
  Info,
  Clock,
  User,
} from 'lucide-vue-next';

const isLoading = ref(true);
const error = ref<string | null>(null);
const announcements = ref<ConnectAnnouncementItem[]>([]);
const total = ref(0);
const currentPage = ref(1);
const lastPage = ref(1);
const searchQuery = ref('');
const ownershipNotice = ref('');

const fetchAnnouncements = async (page = 1) => {
  try {
    isLoading.value = true;
    error.value = null;
    const res = await connectAdminService.getAnnouncements({
      search: searchQuery.value.trim() || undefined,
      page,
      per_page: 15,
    });
    announcements.value = res.announcements.data;
    total.value = res.announcements.total;
    currentPage.value = res.announcements.current_page;
    lastPage.value = res.announcements.last_page;
    ownershipNotice.value = res.ownership_notice;
  } catch (err: any) {
    error.value = err?.response?.data?.message || err?.message || 'Failed to load announcements from CMS.';
  } finally {
    isLoading.value = false;
  }
};

const handleSearch = () => {
  void fetchAnnouncements(1);
};

const formatDate = (dateStr?: string) => {
  if (!dateStr) return '—';
  try {
    return new Date(dateStr).toLocaleString(undefined, {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    });
  } catch {
    return dateStr;
  }
};

onMounted(() => {
  void fetchAnnouncements(1);
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl font-bold text-neutral-900">Communications & Announcements Integration</h1>
        <p class="text-xs text-neutral-500 mt-1">
          Inspect authoritative CMS announcements and community bulletins delivered into member-facing Connect feeds.
        </p>
      </div>
      <router-link
        to="/cms/announcements"
        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white border border-[#ebe8de] text-xs font-semibold text-neutral-700 hover:text-[#640c0e] hover:bg-[#faf8f5] transition-colors shadow-xs self-start sm:self-center"
      >
        <span>Open CMS Announcements</span>
        <ExternalLink class="w-3.5 h-3.5" />
      </router-link>
    </div>

    <!-- CMS Source of Truth Notice Banner -->
    <div class="bg-blue-50/70 border border-blue-200/80 rounded-2xl p-4 text-xs text-blue-900 flex items-start gap-3">
      <Info class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" />
      <div class="space-y-1">
        <div class="font-bold">Authoritative Source of Truth: Content Management System (CMS)</div>
        <p class="text-blue-800 leading-relaxed">
          {{ ownershipNotice || 'CMS is the authoritative owner of platform communications, bulletins, and public announcements. MSA Connect does not duplicate content storage or replace editorial approval workflows.' }}
        </p>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white rounded-2xl border border-[#ebe8de] p-4 shadow-xs flex items-center gap-3">
      <div class="relative flex-1">
        <Search class="w-4 h-4 text-neutral-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
        <input
          type="text"
          v-model="searchQuery"
          @keyup.enter="handleSearch"
          placeholder="Filter announcements by title or summary..."
          class="w-full text-xs pl-10 pr-4 py-2 rounded-xl border border-[#ebe8de] bg-[#faf8f5] focus:outline-none focus:border-[#640c0e]"
        />
      </div>
      <button
        type="button"
        @click="handleSearch"
        class="px-4 py-2 bg-[#640c0e] text-white text-xs font-semibold rounded-xl hover:bg-[#500a0b] transition-colors cursor-pointer"
      >
        Search
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="bg-white rounded-2xl border border-[#ebe8de] p-8 text-center text-xs text-neutral-400">
      Loading announcements from CMS...
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-2xl p-4 text-xs text-red-700">
      {{ error }}
    </div>

    <!-- Empty State -->
    <div v-else-if="announcements.length === 0" class="bg-white rounded-2xl border border-[#ebe8de] p-12 text-center space-y-2">
      <Megaphone class="w-8 h-8 text-neutral-300 mx-auto" />
      <div class="text-sm font-bold text-neutral-700">No Announcements Found</div>
      <p class="text-xs text-neutral-500 max-w-sm mx-auto">
        No announcements currently match your search criteria. Announcements published in CMS will appear here.
      </p>
    </div>

    <!-- Announcements Cards List -->
    <div v-else class="space-y-3">
      <div
        v-for="ann in announcements"
        :key="ann.id"
        class="bg-white rounded-2xl border border-[#ebe8de] p-5 shadow-xs space-y-2 hover:border-[#640c0e]/30 transition-colors"
      >
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
          <div class="font-bold text-sm text-neutral-900">{{ ann.title }}</div>
          <div class="flex items-center gap-2 shrink-0">
            <span
              class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold"
              :class="ann.status === 'published' ? 'bg-emerald-50 text-emerald-700' : 'bg-neutral-100 text-neutral-600'"
            >
              {{ ann.status }}
            </span>
            <span class="text-neutral-400 text-xs flex items-center gap-1">
              <Clock class="w-3.5 h-3.5" />
              {{ formatDate(ann.published_at) }}
            </span>
          </div>
        </div>

        <p v-if="ann.summary" class="text-xs text-neutral-600 leading-relaxed">
          {{ ann.summary }}
        </p>

        <div class="flex items-center justify-between pt-2 border-t border-[#ebe8de]/60 text-[11px] text-neutral-400">
          <div class="flex items-center gap-1.5 font-mono">
            <span>Slug: {{ ann.slug }}</span>
          </div>
          <div v-if="ann.author" class="flex items-center gap-1 text-neutral-500">
            <User class="w-3 h-3" />
            <span>{{ ann.author.name }}</span>
          </div>
        </div>
      </div>

      <!-- Pagination Footer -->
      <div v-if="lastPage > 1" class="p-4 bg-white rounded-2xl border border-[#ebe8de] flex items-center justify-between text-xs text-neutral-500">
        <div>Showing page {{ currentPage }} of {{ lastPage }} ({{ total }} total announcements)</div>
        <div class="flex items-center gap-2">
          <button
            type="button"
            :disabled="currentPage <= 1"
            @click="fetchAnnouncements(currentPage - 1)"
            class="px-2.5 py-1 rounded-lg border border-[#ebe8de] disabled:opacity-40 cursor-pointer"
          >
            Previous
          </button>
          <button
            type="button"
            :disabled="currentPage >= lastPage"
            @click="fetchAnnouncements(currentPage + 1)"
            class="px-2.5 py-1 rounded-lg border border-[#ebe8de] disabled:opacity-40 cursor-pointer"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
