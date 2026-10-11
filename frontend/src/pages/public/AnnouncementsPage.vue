<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { RouterLink } from 'vue-router';
import websiteService, { type AnnouncementItem } from '@/services/website/websiteService';
import { 
  Megaphone, 
  Search, 
  Calendar, 
  ChevronRight, 
  ArrowRight,
  Filter,
  RefreshCw,
  AlertCircle
} from 'lucide-vue-next';

const announcements = ref<AnnouncementItem[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

const searchQuery = ref('');
const selectedCategory = ref('All');

const categories = ref<string[]>(['All', 'Prayer', 'Board', 'Events', 'Education', 'General']);

const fetchAnnouncements = async () => {
  loading.value = true;
  error.value = null;
  try {
    const params: { search?: string; category?: string } = {};
    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim();
    }
    if (selectedCategory.value !== 'All') {
      params.category = selectedCategory.value;
    }
    const data = await websiteService.getAnnouncements(params);
    announcements.value = data;

    // Dynamically expand categories if DB contains unique categories
    const dbCategories = new Set(data.map(item => item.category || item.summary).filter(Boolean) as string[]);
    const combined = Array.from(new Set(['All', 'Prayer', 'Board', 'Events', 'Education', 'General', ...Array.from(dbCategories)]));
    categories.value = combined;
  } catch (err: any) {
    error.value = err?.message || 'Failed to load announcements. Please try again.';
  } finally {
    loading.value = false;
  }
};

let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;
watch([searchQuery, selectedCategory], () => {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    fetchAnnouncements();
  }, 300);
});

onMounted(() => {
  fetchAnnouncements();
});

const featuredAnnouncement = computed(() => {
  if (announcements.value.length === 0) return null;
  return announcements.value[0];
});

const feedAnnouncements = computed(() => {
  if (announcements.value.length === 0) return [];
  // If featured is displayed at top and no search/category filter, skip first item
  if (!searchQuery.value && selectedCategory.value === 'All' && featuredAnnouncement.value) {
    return announcements.value.slice(1);
  }
  return announcements.value;
});

const formatDate = (dateStr: string) => {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  if (isNaN(date.getTime())) return dateStr;
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};
</script>

<template>
  <div class="min-h-screen bg-neutral-background pt-24 pb-20">
    <!-- Hero Header -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-12">
      <div class="text-center max-w-3xl mx-auto">
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-primary/10 border border-primary/20 text-primary text-xs font-extrabold uppercase tracking-widest mb-4">
          <Megaphone class="w-4 h-4 text-primary" />
          <span>SFU MSA Communications</span>
        </div>
        <h1 class="text-3xl sm:text-5xl font-display font-extrabold text-neutral-black tracking-tight mb-4">
          Official Announcements & News
        </h1>
        <p class="text-base sm:text-lg text-neutral-black/70 font-sans leading-relaxed">
          Stay informed with official updates, community notices, prayer space changes, committee applications, and executive announcements from the SFU Muslim Students' Association.
        </p>
      </div>

      <!-- Search & Category Filters -->
      <div class="mt-10 max-w-4xl mx-auto space-y-4">
        <div class="flex flex-col sm:flex-row items-center gap-3">
          <!-- Search Bar -->
          <div class="relative w-full flex-1">
            <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-black/40" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search announcements by keyword, title, or topic..."
              class="w-full pl-11 pr-4 py-3 bg-white border border-neutral-ivory/80 rounded-2xl text-sm font-sans text-neutral-black placeholder-neutral-black/40 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all shadow-soft"
              aria-label="Search announcements"
            />
            <button
              v-if="searchQuery"
              @click="searchQuery = ''"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-neutral-black/40 hover:text-neutral-black font-bold uppercase tracking-wider px-2 py-1 rounded"
            >
              Clear
            </button>
          </div>
        </div>

        <!-- Category Chips -->
        <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none pt-1">
          <span class="text-xs font-extrabold uppercase tracking-widest text-neutral-black/50 flex items-center gap-1.5 shrink-0 mr-1">
            <Filter class="w-3.5 h-3.5" />
            Category:
          </span>
          <button
            v-for="cat in categories"
            :key="cat"
            @click="selectedCategory = cat"
            :class="[
              'px-4 py-2 rounded-xl text-xs font-extrabold uppercase tracking-wider transition-all whitespace-nowrap cursor-pointer shrink-0',
              selectedCategory === cat
                ? 'bg-primary text-white shadow-md'
                : 'bg-white text-neutral-black/70 hover:bg-neutral-ivory/50 border border-neutral-ivory/80'
            ]"
          >
            {{ cat }}
          </button>
        </div>
      </div>
    </div>

    <!-- Main Content Section -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Loading State -->
      <div v-if="loading" class="space-y-6">
        <div class="h-64 bg-white/60 animate-pulse rounded-3xl border border-neutral-ivory/60" />
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div v-for="i in 6" :key="i" class="h-80 bg-white/60 animate-pulse rounded-3xl border border-neutral-ivory/60" />
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-50/80 border border-red-200 text-red-700 p-8 rounded-3xl text-center max-w-xl mx-auto">
        <AlertCircle class="w-10 h-10 text-red-500 mx-auto mb-3" />
        <h3 class="text-lg font-bold font-display mb-1">Could Not Load Announcements</h3>
        <p class="text-sm font-sans text-red-600 mb-4">{{ error }}</p>
        <button
          @click="fetchAnnouncements"
          class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 text-white text-xs font-extrabold uppercase tracking-wider rounded-xl hover:bg-red-700 transition-all"
        >
          <RefreshCw class="w-4 h-4" />
          Retry
        </button>
      </div>

      <!-- Empty State -->
      <div v-else-if="announcements.length === 0" class="bg-white border border-neutral-ivory/80 rounded-3xl p-12 text-center max-w-xl mx-auto shadow-soft">
        <Megaphone class="w-12 h-12 text-neutral-black/30 mx-auto mb-4" />
        <h3 class="text-xl font-display font-extrabold text-neutral-black mb-2">No Announcements Found</h3>
        <p class="text-sm font-sans text-neutral-black/60 mb-6">
          We couldn't find any published announcements matching your search criteria.
        </p>
        <button
          @click="searchQuery = ''; selectedCategory = 'All';"
          class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white text-xs font-extrabold uppercase tracking-wider rounded-xl hover:bg-secondary transition-all"
        >
          Reset Filters
        </button>
      </div>

      <!-- Announcements Grid & Featured -->
      <div v-else class="space-y-12">
        <!-- Featured Banner Card (Only when no search/category filter active) -->
        <div v-if="!searchQuery && selectedCategory === 'All' && featuredAnnouncement">
          <div class="relative bg-white border border-neutral-ivory rounded-3xl overflow-hidden shadow-premium hover:shadow-2xl transition-all duration-300 group">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-0">
              <div v-if="featuredAnnouncement.featured_image" class="lg:col-span-5 h-64 lg:h-auto relative overflow-hidden bg-neutral-100">
                <img
                  :src="featuredAnnouncement.featured_image"
                  :alt="featuredAnnouncement.title"
                  class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent lg:hidden" />
              </div>
              <div :class="['p-8 sm:p-10 flex flex-col justify-between', featuredAnnouncement.featured_image ? 'lg:col-span-7' : 'lg:col-span-12']">
                <div>
                  <div class="flex flex-wrap items-center gap-3 mb-4">
                    <span class="px-3 py-1 bg-primary text-white text-[10px] font-extrabold uppercase tracking-widest rounded-full">
                      Featured Notice
                    </span>
                    <span class="px-3 py-1 bg-neutral-background text-neutral-black/70 text-[10px] font-bold uppercase tracking-widest rounded-full border border-neutral-ivory">
                      {{ featuredAnnouncement.category || featuredAnnouncement.summary || 'General' }}
                    </span>
                    <span v-if="featuredAnnouncement.date" class="text-xs text-neutral-black/50 font-sans flex items-center gap-1 ml-auto">
                      <Calendar class="w-3.5 h-3.5 text-primary" />
                      {{ formatDate(featuredAnnouncement.date) }}
                    </span>
                  </div>

                  <h2 class="text-2xl sm:text-3xl font-display font-extrabold text-neutral-black group-hover:text-primary transition-colors leading-tight mb-4">
                    {{ featuredAnnouncement.title }}
                  </h2>

                  <p class="text-neutral-black/70 font-sans text-sm sm:text-base line-clamp-3 leading-relaxed mb-6">
                    {{ featuredAnnouncement.content }}
                  </p>
                </div>

                <div class="pt-4 border-t border-neutral-ivory/60 flex items-center justify-between">
                  <div v-if="featuredAnnouncement.author" class="text-xs text-neutral-black/50 font-sans">
                    By <span class="font-bold text-neutral-black/80">{{ featuredAnnouncement.author.name }}</span>
                  </div>
                  <RouterLink
                    :to="`/announcements/${featuredAnnouncement.slug || featuredAnnouncement.id}`"
                    class="inline-flex items-center gap-2 text-xs font-extrabold uppercase tracking-wider text-primary group-hover:text-secondary transition-colors ml-auto"
                  >
                    <span>Read Full Announcement</span>
                    <ArrowRight class="w-4 h-4 transition-transform group-hover:translate-x-1" />
                  </RouterLink>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Chronological Announcements Feed Grid -->
        <div>
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-display font-extrabold text-neutral-black flex items-center gap-2">
              <Megaphone class="w-5 h-5 text-primary" />
              <span>Latest Communications</span>
            </h3>
            <span class="text-xs font-sans text-neutral-black/50">
              Showing {{ feedAnnouncements.length }} {{ feedAnnouncements.length === 1 ? 'notice' : 'notices' }}
            </span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
            <div
              v-for="item in feedAnnouncements"
              :key="item.id"
            >
              <div class="bg-white border border-neutral-ivory rounded-3xl overflow-hidden shadow-soft hover:shadow-premium transition-all duration-300 flex flex-col h-full group">
                <div v-if="item.featured_image" class="h-48 overflow-hidden relative bg-neutral-100">
                  <img
                    :src="item.featured_image"
                    :alt="item.title"
                    class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                  />
                </div>
                
                <div class="p-6 flex-1 flex flex-col justify-between">
                  <div>
                    <div class="flex items-center justify-between gap-2 mb-3">
                      <span class="px-2.5 py-0.5 bg-neutral-background text-neutral-black/70 text-[10px] font-bold uppercase tracking-widest rounded-full border border-neutral-ivory">
                        {{ item.category || item.summary || 'General' }}
                      </span>
                      <span v-if="item.date" class="text-[11px] text-neutral-black/50 font-sans flex items-center gap-1">
                        <Calendar class="w-3 h-3 text-primary" />
                        {{ formatDate(item.date) }}
                      </span>
                    </div>

                    <h4 class="text-lg font-display font-bold text-neutral-black group-hover:text-primary transition-colors leading-snug mb-2 line-clamp-2">
                      {{ item.title }}
                    </h4>

                    <p class="text-xs font-sans text-neutral-black/70 line-clamp-3 leading-relaxed mb-4">
                      {{ item.content }}
                    </p>
                  </div>

                  <div class="pt-4 border-t border-neutral-ivory/60 flex items-center justify-between mt-auto">
                    <span v-if="item.author" class="text-[11px] text-neutral-black/50 font-sans">
                      {{ item.author.name }}
                    </span>
                    <RouterLink
                      :to="`/announcements/${item.slug || item.id}`"
                      class="inline-flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wider text-primary group-hover:text-secondary transition-colors ml-auto"
                    >
                      <span>Read More</span>
                      <ChevronRight class="w-3.5 h-3.5 transition-transform group-hover:translate-x-0.5" />
                    </RouterLink>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
