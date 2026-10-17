<template>
  <div class="space-y-6">
    <!-- Page Hero -->
    <PageHero
      label="Community Guides"
      title="Campus Guides & Resources"
      description="Campus prayer spaces, student spiritual guides, halaqah materials, and university life advice."
    >
      <template #actions>
        <router-link to="/app/support/new">
          <Button variant="outline" size="sm" class="bg-white hover:bg-[#fffbf4] border-[#ebe8de] text-xs font-semibold shadow-2xs">
            <span>Request a Resource</span>
          </Button>
        </router-link>
      </template>
    </PageHero>

    <!-- Route-Based Tabs -->
    <RouteTabs :tabs="navTabs" aria-label="Resources and Help Navigation" />

    <!-- Filter & Search Toolbar -->
    <div class="p-3.5 sm:p-4 rounded-2xl bg-white border border-[#ebe8de] shadow-2xs space-y-3">
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <!-- Search Input -->
        <div class="relative flex-1">
          <Search class="w-4 h-4 text-neutral-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search guides, prayer spaces, halaqah materials..."
            class="w-full pl-9 pr-3 py-2 rounded-xl border border-[#ebe8de] bg-[#fffbf4]/40 text-xs text-neutral-800 focus:outline-none focus:ring-2 focus:ring-[#640c0e]/20 focus:border-[#640c0e]"
          />
        </div>

        <!-- Category Dropdown / Pills -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 text-xs shrink-0">
          <button
            type="button"
            class="px-3 py-1.5 rounded-lg font-medium transition-colors cursor-pointer"
            :class="selectedCategory === '' ? 'bg-[#640c0e] text-white font-semibold' : 'text-neutral-600 hover:bg-[#ebe8de]/60'"
            @click="selectedCategory = ''"
          >
            All Resources
          </button>
          <button
            v-for="cat in uniqueCategories"
            :key="cat"
            type="button"
            class="px-3 py-1.5 rounded-lg font-medium transition-colors cursor-pointer whitespace-nowrap"
            :class="selectedCategory === cat ? 'bg-[#640c0e] text-white font-semibold' : 'text-neutral-600 hover:bg-[#ebe8de]/60'"
            @click="selectedCategory = cat"
          >
            {{ cat }}
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="py-20 flex flex-col items-center justify-center space-y-3">
      <Spinner size="lg" />
      <p class="text-xs text-neutral-500 font-medium">Loading community resources...</p>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else-if="filteredResources.length === 0"
      title="No Resources Found"
      description="There are currently no community resources matching your search query or selected topic."
    >
      <template #icon>
        <BookOpen class="w-8 h-8 text-[#640c0e]" />
      </template>
    </EmptyState>

    <!-- Resource Cards Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <article
        v-for="res in filteredResources"
        :key="res.id"
        class="community-card p-5 bg-white border border-[#ebe8de] flex flex-col justify-between space-y-4 hover:border-[#640c0e]/30 transition-all hover:shadow-sm"
      >
        <div class="space-y-3">
          <div class="flex items-center justify-between gap-2">
            <Badge variant="gold" size="sm">
              {{ res.category || 'General' }}
            </Badge>
            <span v-if="res.isExternal" class="inline-flex items-center gap-1 text-[11px] text-neutral-400">
              <ExternalLink class="w-3 h-3" />
              <span>External Link</span>
            </span>
          </div>

          <h2 class="text-base font-bold text-[#640c0e] leading-snug">
            {{ res.title }}
          </h2>

          <p class="text-xs text-neutral-600 leading-relaxed line-clamp-3">
            {{ res.description }}
          </p>

          <!-- Tags -->
          <div v-if="res.tags && res.tags.length > 0" class="flex flex-wrap gap-1.5 pt-1">
            <span
              v-for="tag in res.tags"
              :key="tag"
              class="px-2 py-0.5 rounded-md bg-[#fffbf4] border border-[#ebe8de] text-[10px] text-neutral-600"
            >
              #{{ tag }}
            </span>
          </div>
        </div>

        <div class="pt-3 border-t border-[#ebe8de]/60 flex items-center justify-between">
          <span class="text-[11px] text-neutral-400 font-medium">SFU MSA Resource</span>

          <a
            v-if="res.link || res.file"
            :href="(res.link || res.file) ?? undefined"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-1 text-xs font-bold text-[#b02e32] hover:text-[#640c0e] transition-colors"
          >
            <span>Access Resource</span>
            <ExternalLink class="w-3.5 h-3.5" />
          </a>
        </div>
      </article>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { resourcesService } from '@/services/resources.service';
import type { CommunityResource } from '@/types/support';
import Button from '@/components/ui/Button.vue';
import Badge from '@/components/ui/Badge.vue';
import Spinner from '@/components/ui/Spinner.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PageHero from '@/components/ui/PageHero.vue';
import RouteTabs, { type RouteTabItem } from '@/components/ui/RouteTabs.vue';
import {
  Search,
  BookOpen,
  HelpCircle,
  ExternalLink,
} from 'lucide-vue-next';

const router = useRouter();

const navTabs = computed<RouteTabItem[]>(() => [
  {
    to: '/app/resources',
    label: 'Community Guides',
    icon: BookOpen,
  },
  {
    to: '/app/help',
    label: 'Help & FAQ',
    icon: HelpCircle,
  },
]);

const isLoading = ref(true);
const resources = ref<CommunityResource[]>([]);
const searchQuery = ref('');
const selectedCategory = ref('');

const uniqueCategories = computed(() => {
  const cats = new Set<string>();
  resources.value.forEach((r) => {
    if (r.category) cats.add(r.category);
  });
  return Array.from(cats);
});

const filteredResources = computed(() => {
  return resources.value.filter((r) => {
    if (selectedCategory.value && r.category !== selectedCategory.value) {
      return false;
    }
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase().trim();
      const matchTitle = r.title.toLowerCase().includes(q);
      const matchDesc = r.description.toLowerCase().includes(q);
      const matchCat = r.category?.toLowerCase().includes(q);
      const matchTag = r.tags?.some((t) => t.toLowerCase().includes(q));
      return matchTitle || matchDesc || matchCat || matchTag;
    }
    return true;
  });
});

onMounted(async () => {
  const enabled = await resourcesService.isEnabled();
  if (!enabled) {
    // If not enabled, gate this section and redirect member to dashboard
    router.replace('/app/dashboard');
    return;
  }

  try {
    resources.value = await resourcesService.getResources();
  } finally {
    isLoading.value = false;
  }
});
</script>
