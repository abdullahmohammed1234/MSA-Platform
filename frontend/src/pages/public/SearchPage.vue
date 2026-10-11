<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import searchService, {
  type SearchResultItem,
  type SearchResponse,
  type SearchTypeCounts
} from '@/services/search/searchService';
import {
  Search,
  Filter,
  Calendar,
  ChevronRight,
  Megaphone,
  Sparkles,
  ArrowRight,
  AlertCircle,
  RefreshCw,
  SlidersHorizontal,
  ChevronLeft
} from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();

const query = ref('');
const selectedType = ref('all');
const sortBy = ref<'relevance' | 'date'>('relevance');
const currentPage = ref(1);

const results = ref<SearchResultItem[]>([]);
const totalCount = ref(0);
const lastPage = ref(1);
const typeCounts = ref<SearchTypeCounts>({
  all: 0,
  announcement: 0,
  event: 0,
  program: 0,
  resource: 0,
  volunteer: 0,
  store: 0,
});

const loading = ref(true);
const error = ref<string | null>(null);

const typeFilterTabs = [
  { key: 'all', label: 'All Content' },
  { key: 'event', label: 'Events' },
  { key: 'announcement', label: 'Announcements' },
  { key: 'program', label: 'Programs' },
  { key: 'resource', label: 'Resources' },
  { key: 'volunteer', label: 'Volunteering' },
  { key: 'store', label: 'Store Products' },
];

const syncFromUrl = () => {
  query.value = (route.query.q as string) || '';
  selectedType.value = (route.query.type as string) || 'all';
  sortBy.value = (route.query.sort as 'relevance' | 'date') || 'relevance';
  currentPage.value = parseInt((route.query.page as string) || '1', 10);
};

const updateUrl = () => {
  const queryParams: Record<string, string | number> = {};
  if (query.value.trim()) {
    queryParams.q = query.value.trim();
  }
  if (selectedType.value !== 'all') {
    queryParams.type = selectedType.value;
  }
  if (sortBy.value !== 'relevance') {
    queryParams.sort = sortBy.value;
  }
  if (currentPage.value > 1) {
    queryParams.page = currentPage.value;
  }

  router.replace({ path: '/search', query: queryParams });
};

const executeSearch = async () => {
  const q = query.value.trim();
  if (!q) {
    results.value = [];
    totalCount.value = 0;
    lastPage.value = 1;
    loading.value = false;
    typeCounts.value = { all: 0, announcement: 0, event: 0, program: 0, resource: 0, volunteer: 0, store: 0 };
    return;
  }

  loading.value = true;
  error.value = null;

  try {
    const data: SearchResponse = await searchService.search({
      q,
      type: selectedType.value,
      sort_by: sortBy.value,
      page: currentPage.value,
      per_page: 10,
    });

    results.value = data.items || [];
    totalCount.value = data.pagination?.total || 0;
    lastPage.value = data.pagination?.last_page || 1;
    typeCounts.value = data.type_counts || { all: 0, announcement: 0, event: 0, program: 0, resource: 0, volunteer: 0, store: 0 };
  } catch (err: any) {
    error.value = err?.message || 'Failed to complete search. Please try again.';
    results.value = [];
  } finally {
    loading.value = false;
  }
};

let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

watch([query, selectedType, sortBy, currentPage], () => {
  updateUrl();
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    executeSearch();
  }, 300);
});

watch(() => route.query, () => {
  syncFromUrl();
  executeSearch();
});

onMounted(() => {
  syncFromUrl();
  executeSearch();
});

const onSelectType = (typeKey: string) => {
  selectedType.value = typeKey;
  currentPage.value = 1;
};

const onSelectSort = (event: Event) => {
  const val = (event.target as HTMLSelectElement).value as 'relevance' | 'date';
  sortBy.value = val;
  currentPage.value = 1;
};

const getTypeBadgeColor = (type: string) => {
  switch (type) {
    case 'announcement':
      return 'bg-amber-100 text-amber-800 border-amber-200';
    case 'event':
      return 'bg-emerald-100 text-emerald-800 border-emerald-200';
    case 'program':
      return 'bg-blue-100 text-blue-800 border-blue-200';
    case 'resource':
      return 'bg-purple-100 text-purple-800 border-purple-200';
    case 'volunteer':
      return 'bg-rose-100 text-rose-800 border-rose-200';
    case 'store':
      return 'bg-indigo-100 text-indigo-800 border-indigo-200';
    default:
      return 'bg-neutral-100 text-neutral-800 border-neutral-200';
  }
};
</script>

<template>
  <div class="min-h-screen bg-neutral-background pt-24 pb-20">
    <!-- Search Header Section -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-10">
      <div class="text-center max-w-3xl mx-auto mb-8">
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-primary/10 border border-primary/20 text-primary text-xs font-extrabold uppercase tracking-widest mb-4">
          <Search class="w-4 h-4 text-primary" />
          <span>SFU MSA Unified Discovery</span>
        </div>
        <h1 class="text-3xl sm:text-5xl font-display font-extrabold text-neutral-black tracking-tight mb-4">
          Platform Search & Discovery
        </h1>
        <p class="text-base sm:text-lg text-neutral-black/70 font-sans leading-relaxed">
          Discover upcoming community events, official MSA announcements, learning programs, volunteer roles, and campus resources in one unified hub.
        </p>
      </div>

      <!-- Main Search Bar -->
      <div class="max-w-3xl mx-auto space-y-4">
        <div class="relative">
          <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-neutral-black/40" />
          <input
            v-model="query"
            type="text"
            placeholder="Search events, announcements, programs, guides & merchandise..."
            class="w-full pl-12 pr-24 py-4 bg-white border border-neutral-ivory/80 rounded-2xl text-base font-sans text-neutral-black placeholder-neutral-black/40 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all shadow-soft"
            aria-label="Search platform"
          />
          <button
            v-if="query"
            @click="query = ''; currentPage = 1;"
            class="absolute right-4 top-1/2 -translate-y-1/2 text-xs text-neutral-black/40 hover:text-neutral-black font-bold uppercase tracking-wider px-3 py-1.5 bg-neutral-background hover:bg-neutral-ivory/60 rounded-lg transition-colors cursor-pointer"
          >
            Clear
          </button>
        </div>

        <!-- Category Filter Tabs -->
        <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none pt-2">
          <span class="text-xs font-extrabold uppercase tracking-widest text-neutral-black/50 flex items-center gap-1.5 shrink-0 mr-1">
            <Filter class="w-3.5 h-3.5" />
            Filter:
          </span>
          <button
            v-for="tab in typeFilterTabs"
            :key="tab.key"
            @click="onSelectType(tab.key)"
            :class="[
              'px-4 py-2 rounded-xl text-xs font-extrabold uppercase tracking-wider transition-all whitespace-nowrap cursor-pointer shrink-0 flex items-center gap-1.5',
              selectedType === tab.key
                ? 'bg-primary text-white shadow-md'
                : 'bg-white text-neutral-black/70 hover:bg-neutral-ivory/50 border border-neutral-ivory/80'
            ]"
          >
            <span>{{ tab.label }}</span>
            <span
              v-if="typeCounts[tab.key as keyof SearchTypeCounts] !== undefined"
              :class="[
                'px-1.5 py-0.5 rounded-full text-[10px] font-mono',
                selectedType === tab.key ? 'bg-white/20 text-white' : 'bg-neutral-background text-neutral-black/60'
              ]"
            >
              {{ typeCounts[tab.key as keyof SearchTypeCounts] }}
            </span>
          </button>
        </div>
      </div>
    </div>

    <!-- Results Section Container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Search Meta & Sort Toolbar -->
      <div v-if="query.trim().length >= 2 && !loading && !error" class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6 pb-4 border-b border-neutral-ivory">
        <div class="text-sm font-sans text-neutral-black/70">
          Showing <span class="font-bold text-neutral-black">{{ totalCount }}</span> {{ totalCount === 1 ? 'result' : 'results' }} for "<span class="font-semibold text-neutral-black">{{ query }}</span>"
        </div>

        <div class="flex items-center gap-2">
          <SlidersHorizontal class="w-4 h-4 text-neutral-black/50" />
          <label for="sort-select" class="text-xs font-bold text-neutral-black/60 uppercase tracking-wider">Sort by:</label>
          <select
            id="sort-select"
            :value="sortBy"
            @change="onSelectSort"
            class="bg-white border border-neutral-ivory rounded-xl px-3 py-1.5 text-xs font-sans font-semibold text-neutral-black focus:outline-none focus:ring-2 focus:ring-primary/20 shadow-sm cursor-pointer"
          >
            <option value="relevance">Relevance</option>
            <option value="date">Date (Newest First)</option>
          </select>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="space-y-4 max-w-4xl mx-auto">
        <div v-for="i in 5" :key="i" class="h-32 bg-white/60 animate-pulse rounded-3xl border border-neutral-ivory/60" />
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-50/80 border border-red-200 text-red-700 p-8 rounded-3xl text-center max-w-xl mx-auto">
        <AlertCircle class="w-10 h-10 text-red-500 mx-auto mb-3" />
        <h3 class="text-lg font-bold font-display mb-1">Search Error</h3>
        <p class="text-sm font-sans text-red-600 mb-4">{{ error }}</p>
        <button
          @click="executeSearch"
          class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 text-white text-xs font-extrabold uppercase tracking-wider rounded-xl hover:bg-red-700 transition-all cursor-pointer"
        >
          <RefreshCw class="w-4 h-4" />
          Retry Search
        </button>
      </div>

      <!-- Prompt for Short / Empty Query -->
      <div v-else-if="!query.trim()" class="bg-white border border-neutral-ivory/80 rounded-3xl p-12 text-center max-w-2xl mx-auto shadow-soft">
        <Search class="w-12 h-12 text-neutral-black/30 mx-auto mb-4" />
        <h3 class="text-xl font-display font-extrabold text-neutral-black mb-2">Explore the SFU MSA Platform</h3>
        <p class="text-sm font-sans text-neutral-black/60 mb-8">
          Type keywords in the search bar above to discover events, announcements, volunteer opportunities, and guides.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-left">
          <RouterLink
            to="/events"
            class="p-4 bg-neutral-background hover:bg-primary/5 rounded-2xl border border-neutral-ivory hover:border-primary/40 transition-all group"
          >
            <Calendar class="w-5 h-5 text-primary mb-2" />
            <h4 class="text-xs font-bold font-display text-neutral-black group-hover:text-primary transition-colors">Campus Events</h4>
            <p class="text-[11px] text-neutral-black/60 mt-0.5">Jumu'ah & social halaqahs</p>
          </RouterLink>

          <RouterLink
            to="/announcements"
            class="p-4 bg-neutral-background hover:bg-primary/5 rounded-2xl border border-neutral-ivory hover:border-primary/40 transition-all group"
          >
            <Megaphone class="w-5 h-5 text-primary mb-2" />
            <h4 class="text-xs font-bold font-display text-neutral-black group-hover:text-primary transition-colors">Announcements</h4>
            <p class="text-[11px] text-neutral-black/60 mt-0.5">Official MSA news & updates</p>
          </RouterLink>

          <RouterLink
            to="/featured-opportunities"
            class="p-4 bg-neutral-background hover:bg-primary/5 rounded-2xl border border-neutral-ivory hover:border-primary/40 transition-all group"
          >
            <Sparkles class="w-5 h-5 text-primary mb-2" />
            <h4 class="text-xs font-bold font-display text-neutral-black group-hover:text-primary transition-colors">Programs</h4>
            <p class="text-[11px] text-neutral-black/60 mt-0.5">Learning & starter kits</p>
          </RouterLink>
        </div>
      </div>

      <!-- No Results Found State -->
      <div v-else-if="results.length === 0" class="bg-white border border-neutral-ivory/80 rounded-3xl p-12 text-center max-w-xl mx-auto shadow-soft">
        <Search class="w-12 h-12 text-neutral-black/30 mx-auto mb-4" />
        <h3 class="text-xl font-display font-extrabold text-neutral-black mb-2">No Results Found</h3>
        <p class="text-sm font-sans text-neutral-black/60 mb-6">
          We couldn't find any published content matching "<span class="font-semibold text-neutral-black">{{ query }}</span>"
          <span v-if="selectedType !== 'all'"> in category "<span class="font-semibold text-neutral-black">{{ selectedType }}</span>"</span>.
        </p>
        <button
          @click="selectedType = 'all'; query = '';"
          class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white text-xs font-extrabold uppercase tracking-wider rounded-xl hover:bg-secondary transition-all cursor-pointer"
        >
          Reset Filters & Search
        </button>
      </div>

      <!-- Populated Search Results Cards -->
      <div v-else class="space-y-4 max-w-4xl mx-auto">
        <div
          v-for="item in results"
          :key="item.id"
          class="bg-white border border-neutral-ivory rounded-3xl p-6 shadow-soft hover:shadow-premium transition-all duration-300 group"
        >
          <div class="flex flex-col sm:flex-row items-start gap-4">
            <!-- Thumbnail preview if present -->
            <div
              v-if="item.thumbnail"
              class="w-full sm:w-28 h-28 rounded-2xl overflow-hidden bg-neutral-100 border border-neutral-ivory shrink-0"
            >
              <img
                :src="item.thumbnail"
                :alt="item.title"
                class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
              />
            </div>

            <!-- Content details -->
            <div class="flex-1 min-w-0">
              <div class="flex flex-wrap items-center gap-2 mb-2">
                <span
                  :class="[
                    'px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wider rounded-md border',
                    getTypeBadgeColor(item.content_type)
                  ]"
                >
                  {{ item.type_label }}
                </span>
                <span v-if="item.category" class="px-2.5 py-0.5 bg-neutral-background text-neutral-black/70 text-[10px] font-bold uppercase tracking-widest rounded-md border border-neutral-ivory">
                  {{ item.category }}
                </span>
                <span v-if="item.date" class="text-xs text-neutral-black/50 font-sans flex items-center gap-1 ml-auto">
                  <Calendar class="w-3.5 h-3.5 text-primary" />
                  {{ item.date }}
                </span>
              </div>

              <h3 class="text-lg font-display font-extrabold text-neutral-black group-hover:text-primary transition-colors leading-snug mb-2">
                {{ item.title }}
              </h3>

              <p class="text-xs sm:text-sm font-sans text-neutral-black/70 line-clamp-2 leading-relaxed mb-4">
                {{ item.excerpt }}
              </p>

              <div class="flex items-center justify-end border-t border-neutral-ivory/60 pt-3">
                <RouterLink
                  v-if="!item.destination.startsWith('http')"
                  :to="item.destination"
                  class="inline-flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wider text-primary group-hover:text-secondary transition-colors cursor-pointer"
                >
                  <span>View Details</span>
                  <ChevronRight class="w-4 h-4 transition-transform group-hover:translate-x-0.5" />
                </RouterLink>
                <a
                  v-else
                  :href="item.destination"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wider text-primary group-hover:text-secondary transition-colors cursor-pointer"
                >
                  <span>Open Resource</span>
                  <ArrowRight class="w-4 h-4 transition-transform group-hover:translate-x-0.5" />
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination Controls Bar -->
        <div v-if="lastPage > 1" class="flex items-center justify-between pt-6 border-t border-neutral-ivory mt-8">
          <button
            @click="currentPage = Math.max(1, currentPage - 1)"
            :disabled="currentPage <= 1"
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-extrabold uppercase tracking-wider bg-white border border-neutral-ivory shadow-soft hover:bg-neutral-background disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer"
          >
            <ChevronLeft class="w-4 h-4" />
            <span>Previous</span>
          </button>

          <span class="text-xs font-sans text-neutral-black/60">
            Page <span class="font-bold text-neutral-black">{{ currentPage }}</span> of <span class="font-bold text-neutral-black">{{ lastPage }}</span>
          </span>

          <button
            @click="currentPage = Math.min(lastPage, currentPage + 1)"
            :disabled="currentPage >= lastPage"
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-extrabold uppercase tracking-wider bg-white border border-neutral-ivory shadow-soft hover:bg-neutral-background disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer"
          >
            <span>Next</span>
            <ChevronRight class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
