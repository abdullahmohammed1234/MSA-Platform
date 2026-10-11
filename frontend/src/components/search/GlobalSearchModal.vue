<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import searchService, { type SearchResultItem, type SearchResponse } from '@/services/search/searchService';
import {
  Search,
  X,
  Loader2,
  Calendar,
  ChevronRight,
  Megaphone,
  BookOpen,
  UserCheck,
  ShoppingBag,
  Sparkles,
  Command,
  ArrowRight
} from 'lucide-vue-next';

const props = defineProps<{
  isOpen: boolean;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'open'): void;
}>();

const router = useRouter();

const query = ref('');
const results = ref<SearchResultItem[]>([]);
const totalCount = ref(0);
const loading = ref(false);
const error = ref<string | null>(null);
const selectedIndex = ref(-1);
const inputRef = ref<HTMLInputElement | null>(null);

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
let reqSequence = 0;

const hasQuery = computed(() => query.value.trim().length > 0);
const isQueryTooShort = computed(() => hasQuery.value && query.value.trim().length < 2);

const fetchSuggestions = async () => {
  const q = query.value.trim();
  if (q.length < 2) {
    results.value = [];
    totalCount.value = 0;
    loading.value = false;
    selectedIndex.value = -1;
    return;
  }

  const thisSeq = ++reqSequence;
  loading.value = true;
  error.value = null;

  try {
    const data: SearchResponse = await searchService.search({ q, per_page: 6 });
    // Guard against stale response
    if (thisSeq === reqSequence) {
      results.value = data.items || [];
      totalCount.value = data.pagination?.total || 0;
      selectedIndex.value = -1;
    }
  } catch (err: any) {
    if (thisSeq === reqSequence) {
      error.value = err?.message || 'Failed to fetch search results.';
      results.value = [];
    }
  } finally {
    if (thisSeq === reqSequence) {
      loading.value = false;
    }
  }
};

watch(query, () => {
  if (debounceTimer) clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchSuggestions();
  }, 300);
});

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    query.value = '';
    results.value = [];
    totalCount.value = 0;
    selectedIndex.value = -1;
    nextTick(() => {
      inputRef.value?.focus();
    });
  }
});

const handleKeyDown = (e: KeyboardEvent) => {
  if (!props.isOpen) return;

  if (e.key === 'Escape') {
    closeModal();
    return;
  }

  if (e.key === 'ArrowDown') {
    e.preventDefault();
    if (results.value.length > 0) {
      selectedIndex.value = (selectedIndex.value + 1) % results.value.length;
    }
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    if (results.value.length > 0) {
      selectedIndex.value = selectedIndex.value <= 0 ? results.value.length - 1 : selectedIndex.value - 1;
    }
  } else if (e.key === 'Enter') {
    e.preventDefault();
    if (selectedIndex.value >= 0 && selectedIndex.value < results.value.length) {
      navigateToItem(results.value[selectedIndex.value]);
    } else if (query.value.trim().length >= 2) {
      goToFullSearchPage();
    }
  }
};

const openModal = () => {
  emit('open');
};

const closeModal = () => {
  emit('close');
};

const globalShortcutListener = (e: KeyboardEvent) => {
  if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) {
    e.preventDefault();
    if (props.isOpen) {
      closeModal();
    } else {
      openModal();
    }
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown);
  window.addEventListener('keydown', globalShortcutListener);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown);
  window.removeEventListener('keydown', globalShortcutListener);
});



const navigateToItem = (item: SearchResultItem) => {
  closeModal();
  if (item.destination.startsWith('http://') || item.destination.startsWith('https://')) {
    window.open(item.destination, '_blank');
  } else {
    router.push(item.destination);
  }
};

const goToFullSearchPage = () => {
  const q = query.value.trim();
  closeModal();
  if (q.length >= 2) {
    router.push({ path: '/search', query: { q } });
  } else {
    router.push({ path: '/search' });
  }
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

const getTypeIcon = (type: string) => {
  switch (type) {
    case 'announcement':
      return Megaphone;
    case 'event':
      return Calendar;
    case 'program':
      return Sparkles;
    case 'resource':
      return BookOpen;
    case 'volunteer':
      return UserCheck;
    case 'store':
      return ShoppingBag;
    default:
      return Search;
  }
};
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-50 overflow-y-auto p-4 sm:p-6 md:p-20"
      role="dialog"
      aria-modal="true"
    >
      <!-- Backdrop -->
      <div
        @click="closeModal"
        class="fixed inset-0 bg-neutral-black/60 backdrop-blur-sm transition-opacity"
        aria-hidden="true"
      />

      <!-- Modal Dialog Container -->
      <div class="relative mx-auto max-w-2xl transform overflow-hidden rounded-3xl bg-white shadow-2xl transition-all border border-neutral-ivory/80">
        <!-- Search Input Bar -->
        <div class="relative flex items-center border-b border-neutral-ivory px-4 py-3.5">
          <Search class="w-5 h-5 text-neutral-black/40 mr-3 shrink-0" />
          <input
            ref="inputRef"
            v-model="query"
            type="text"
            placeholder="Search announcements, events, programs, guides & resources..."
            class="w-full bg-transparent text-sm font-sans text-neutral-black placeholder-neutral-black/40 focus:outline-none"
            aria-label="Global search query"
          />
          <button
            v-if="query"
            @click="query = ''"
            class="p-1 rounded-full text-neutral-black/40 hover:text-neutral-black hover:bg-neutral-background transition-colors mr-2 cursor-pointer"
            aria-label="Clear search text"
          >
            <X class="w-4 h-4" />
          </button>
          <kbd class="hidden sm:inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-mono text-neutral-black/50 bg-neutral-background rounded border border-neutral-ivory shrink-0">
            <Command class="w-3 h-3" /> K
          </kbd>
        </div>

        <!-- Search Results / States Body -->
        <div class="max-h-[60vh] overflow-y-auto p-4 sm:p-6 space-y-4">
          <!-- Loading State -->
          <div v-if="loading" class="py-8 text-center space-y-3">
            <Loader2 class="w-6 h-6 text-primary animate-spin mx-auto" />
            <p class="text-xs font-sans text-neutral-black/60">Searching across SFU MSA platform...</p>
          </div>

          <!-- Query too short prompt -->
          <div v-else-if="isQueryTooShort" class="py-6 text-center">
            <p class="text-xs font-sans text-neutral-black/50">Type at least 2 characters to search.</p>
          </div>

          <!-- Empty Query Default Recommendations -->
          <div v-else-if="!hasQuery" class="space-y-4 py-2">
            <div class="text-[11px] font-extrabold uppercase tracking-widest text-neutral-black/40 px-2">
              Popular Quick Searches
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
              <button
                @click="query = 'Jumuah'"
                class="text-left p-3 bg-neutral-background hover:bg-primary/5 rounded-2xl border border-neutral-ivory hover:border-primary/30 transition-all cursor-pointer group"
              >
                <div class="text-xs font-bold text-neutral-black group-hover:text-primary transition-colors">Jumu'ah Prayer</div>
                <div class="text-[10px] text-neutral-black/50 mt-0.5">Location & times</div>
              </button>
              <button
                @click="query = 'Volunteer'"
                class="text-left p-3 bg-neutral-background hover:bg-primary/5 rounded-2xl border border-neutral-ivory hover:border-primary/30 transition-all cursor-pointer group"
              >
                <div class="text-xs font-bold text-neutral-black group-hover:text-primary transition-colors">Volunteering</div>
                <div class="text-[10px] text-neutral-black/50 mt-0.5">Committees & teams</div>
              </button>
              <button
                @click="query = 'Halaqah'"
                class="text-left p-3 bg-neutral-background hover:bg-primary/5 rounded-2xl border border-neutral-ivory hover:border-primary/30 transition-all cursor-pointer group"
              >
                <div class="text-xs font-bold text-neutral-black group-hover:text-primary transition-colors">Social Halaqah</div>
                <div class="text-[10px] text-neutral-black/50 mt-0.5">Weekly gatherings</div>
              </button>
            </div>
          </div>

          <!-- No Results Found State -->
          <div v-else-if="results.length === 0" class="py-8 text-center space-y-3">
            <Search class="w-10 h-10 text-neutral-black/30 mx-auto" />
            <h3 class="text-sm font-bold font-display text-neutral-black">No matching content found</h3>
            <p class="text-xs font-sans text-neutral-black/60 max-w-sm mx-auto">
              We couldn't find any published announcements, events, or resources for "<span class="font-semibold text-neutral-black">{{ query }}</span>".
            </p>
          </div>

          <!-- Populated Live Suggestions List -->
          <div v-else class="space-y-2">
            <div class="flex items-center justify-between px-2 mb-1">
              <span class="text-[11px] font-extrabold uppercase tracking-widest text-neutral-black/40">
                Top Suggestions ({{ results.length }})
              </span>
              <button
                @click="goToFullSearchPage"
                class="text-xs font-extrabold text-primary hover:text-secondary uppercase tracking-wider inline-flex items-center gap-1 cursor-pointer"
              >
                <span>View All {{ totalCount }} Results</span>
                <ArrowRight class="w-3.5 h-3.5" />
              </button>
            </div>

            <div
              v-for="(item, index) in results"
              :key="item.id"
              @click="navigateToItem(item)"
              @mouseenter="selectedIndex = index"
              :class="[
                'p-3.5 rounded-2xl border transition-all cursor-pointer flex items-start gap-3.5',
                selectedIndex === index
                  ? 'bg-primary/5 border-primary/40 shadow-sm'
                  : 'bg-white border-neutral-ivory hover:border-neutral-black/20'
              ]"
            >
              <!-- Icon / Thumbnail Badge -->
              <div class="w-10 h-10 rounded-xl bg-neutral-background border border-neutral-ivory flex items-center justify-center shrink-0 overflow-hidden">
                <img
                  v-if="item.thumbnail"
                  :src="item.thumbnail"
                  :alt="item.title"
                  class="w-full h-full object-cover"
                />
                <component
                  v-else
                  :is="getTypeIcon(item.content_type)"
                  class="w-5 h-5 text-primary"
                />
              </div>

              <!-- Item Content Details -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                  <span
                    :class="[
                      'px-2 py-0.5 text-[9px] font-extrabold uppercase tracking-wider rounded-md border',
                      getTypeBadgeColor(item.content_type)
                    ]"
                  >
                    {{ item.type_label }}
                  </span>
                  <span v-if="item.category" class="text-[10px] font-bold text-neutral-black/50">
                    {{ item.category }}
                  </span>
                  <span v-if="item.date" class="text-[10px] text-neutral-black/40 ml-auto flex items-center gap-1">
                    <Calendar class="w-3 h-3 text-neutral-black/40" />
                    {{ item.date }}
                  </span>
                </div>

                <h4 class="text-sm font-bold font-display text-neutral-black truncate leading-snug">
                  {{ item.title }}
                </h4>
                <p class="text-xs text-neutral-black/60 font-sans line-clamp-1 mt-0.5">
                  {{ item.excerpt }}
                </p>
              </div>

              <ChevronRight class="w-4 h-4 text-neutral-black/30 self-center shrink-0" />
            </div>
          </div>
        </div>

        <!-- Footer Bar with Keyboard Controls -->
        <div class="bg-neutral-background px-4 py-3 border-t border-neutral-ivory flex items-center justify-between text-[11px] text-neutral-black/50">
          <div class="flex items-center gap-3">
            <span class="flex items-center gap-1">
              <kbd class="px-1.5 py-0.5 bg-white rounded border border-neutral-ivory text-[10px] font-mono">↑</kbd>
              <kbd class="px-1.5 py-0.5 bg-white rounded border border-neutral-ivory text-[10px] font-mono">↓</kbd> Navigate
            </span>
            <span class="flex items-center gap-1">
              <kbd class="px-1.5 py-0.5 bg-white rounded border border-neutral-ivory text-[10px] font-mono">↵</kbd> Select
            </span>
            <span class="flex items-center gap-1">
              <kbd class="px-1.5 py-0.5 bg-white rounded border border-neutral-ivory text-[10px] font-mono">ESC</kbd> Close
            </span>
          </div>

          <button
            @click="goToFullSearchPage"
            class="text-xs font-bold text-primary hover:underline cursor-pointer"
          >
            Advanced Search & Filters
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
