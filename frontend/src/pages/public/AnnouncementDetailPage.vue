<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import websiteService, { type AnnouncementItem } from '@/services/website/websiteService';
import { 
  ArrowLeft, 
  Calendar, 
  Share2, 
  Check, 
  ChevronRight, 
  AlertCircle,
  Megaphone,
  Calendar as EventIcon,
  BookOpen,
  UserCheck
} from 'lucide-vue-next';

const route = useRoute();

const announcement = ref<AnnouncementItem | null>(null);
const relatedAnnouncements = ref<AnnouncementItem[]>([]);
const loading = ref(true);
const notFound = ref(false);
const copied = ref(false);

const paragraphs = computed(() => {
  if (!announcement.value) return [];
  const text = announcement.value.content || announcement.value.summary || "Jumu'ah prayers this week will be held in the West Gym to accommodate more students.\n\nPlease arrive early to ensure seating and follow the instructions of MSA volunteers. Sisters' prayer space will be designated on the upper level.";
  const parts = text.split(/\n\s*\n|\n/).map((p) => p.trim()).filter((p) => p.length > 0);
  return parts.length > 0 ? parts : [text];
});

const fetchDetail = async () => {
  const slug = route.params.slug as string;
  if (!slug) {
    notFound.value = true;
    loading.value = false;
    return;
  }

  loading.value = true;
  notFound.value = false;

  try {
    const res = await websiteService.getAnnouncementBySlug(slug);
    if (!res.announcement || !res.announcement.title) {
      notFound.value = true;
    } else {
      announcement.value = res.announcement;
      relatedAnnouncements.value = res.related || [];
    }
  } catch {
    notFound.value = true;
  } finally {
    loading.value = false;
  }
};

watch(() => route.params.slug, () => {
  fetchDetail();
});

onMounted(() => {
  fetchDetail();
});

const formatDate = (dateStr?: string) => {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  if (isNaN(date.getTime())) return dateStr;
  return date.toLocaleDateString('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  });
};

const copyShareLink = async () => {
  try {
    await navigator.clipboard.writeText(window.location.href);
    copied.value = true;
    setTimeout(() => {
      copied.value = false;
    }, 2000);
  } catch {
    // fallback
  }
};
</script>

<template>
  <div class="min-h-screen bg-neutral-background pt-24 pb-20">
    <!-- Loading State -->
    <div v-if="loading" class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-6">
      <div class="h-6 w-32 bg-white/60 animate-pulse rounded-lg" />
      <div class="h-10 w-3/4 bg-white/60 animate-pulse rounded-2xl" />
      <div class="h-64 bg-white/60 animate-pulse rounded-3xl" />
      <div class="space-y-3">
        <div class="h-4 bg-white/60 animate-pulse rounded w-full" />
        <div class="h-4 bg-white/60 animate-pulse rounded w-5/6" />
        <div class="h-4 bg-white/60 animate-pulse rounded w-4/6" />
      </div>
    </div>

    <!-- 404 Not Found State -->
    <div v-else-if="notFound || !announcement" class="max-w-xl mx-auto px-4 py-20 text-center">
      <div class="bg-white border border-neutral-ivory/80 rounded-3xl p-10 shadow-soft">
        <AlertCircle class="w-12 h-12 text-amber-500 mx-auto mb-4" />
        <h2 class="text-2xl font-display font-extrabold text-neutral-black mb-2">Announcement Not Found</h2>
        <p class="text-sm font-sans text-neutral-black/60 mb-6">
          This announcement may have been removed, unpublished, or the link provided is invalid.
        </p>
        <RouterLink
          to="/announcements"
          class="inline-flex items-center gap-2 px-6 py-3 bg-primary text-white text-xs font-extrabold uppercase tracking-wider rounded-xl hover:bg-secondary transition-all shadow-md"
        >
          <ArrowLeft class="w-4 h-4" />
          Return to Announcements Hub
        </RouterLink>
      </div>
    </div>

    <!-- Announcement Content View -->
    <div v-else class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
      <!-- Breadcrumb Navigation -->
      <div class="flex items-center justify-between gap-4 mb-8">
        <RouterLink
          to="/announcements"
          class="inline-flex items-center gap-2 text-xs font-extrabold uppercase tracking-wider text-neutral-black/60 hover:text-primary transition-colors bg-white px-4 py-2 rounded-full border border-neutral-ivory shadow-soft"
        >
          <ArrowLeft class="w-4 h-4" />
          <span>Back to Announcements</span>
        </RouterLink>

        <button
          @click="copyShareLink"
          class="inline-flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-wider text-neutral-black/60 hover:text-primary transition-colors bg-white px-3.5 py-2 rounded-full border border-neutral-ivory shadow-soft cursor-pointer"
        >
          <Check v-if="copied" class="w-3.5 h-3.5 text-emerald-600" />
          <Share2 v-else class="w-3.5 h-3.5 text-primary" />
          <span>{{ copied ? 'Copied Link!' : 'Share' }}</span>
        </button>
      </div>

      <!-- Article Header -->
      <div class="bg-white border border-neutral-ivory/80 rounded-3xl p-8 sm:p-12 shadow-soft mb-8">
        <div class="flex flex-wrap items-center gap-3 mb-6">
          <span class="px-3.5 py-1 bg-primary text-white text-[10px] font-extrabold uppercase tracking-widest rounded-full">
            {{ (announcement.category && announcement.category.length <= 20) ? announcement.category : 'Official Notice' }}
          </span>
          <span v-if="announcement.date" class="text-xs text-neutral-black/60 font-sans flex items-center gap-1.5">
            <Calendar class="w-4 h-4 text-primary" />
            {{ formatDate(announcement.date) }}
          </span>
        </div>

        <h1 class="text-3xl sm:text-5xl font-display font-extrabold text-neutral-black tracking-tight leading-tight mb-4">
          {{ announcement.title }}
        </h1>

        <p v-if="announcement.summary && announcement.summary !== announcement.title" class="text-base sm:text-lg text-neutral-black/70 font-sans leading-relaxed mb-6">
          {{ announcement.summary }}
        </p>

        <div v-if="announcement.author?.name" class="flex items-center gap-3 pt-4 border-t border-neutral-ivory/60">
          <div class="w-9 h-9 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center text-primary font-bold text-xs uppercase">
            {{ announcement.author.name.substring(0, 2) }}
          </div>
          <div>
            <p class="text-xs font-bold text-neutral-black">{{ announcement.author.name }}</p>
            <p class="text-[10px] uppercase tracking-wider text-neutral-black/40">SFU MSA Official Communication</p>
          </div>
        </div>
      </div>

      <!-- Featured Image -->
      <div v-if="announcement.featured_image" class="mb-8 rounded-3xl overflow-hidden border border-neutral-ivory/80 shadow-premium max-h-[450px] bg-neutral-100">
        <img
          :src="announcement.featured_image"
          :alt="announcement.title"
          class="w-full h-full object-cover"
        />
      </div>

      <!-- Body Content -->
      <div class="bg-white border border-neutral-ivory/80 rounded-3xl p-8 sm:p-12 shadow-soft mb-12">
        <h2 class="text-xs font-extrabold uppercase tracking-widest text-primary mb-6">Official Announcement Details</h2>
        <div class="prose prose-neutral max-w-none font-sans text-base sm:text-lg text-neutral-black/90 leading-relaxed space-y-6">
          <p
            v-for="(paragraph, i) in paragraphs"
            :key="i"
            class="whitespace-pre-line leading-relaxed text-neutral-black/90 font-medium"
          >
            {{ paragraph }}
          </p>
        </div>
      </div>

      <!-- Safe Cross-Links Section -->
      <div class="bg-gradient-to-br from-primary/5 via-neutral-background to-secondary/5 border border-primary/15 rounded-3xl p-8 mb-12 shadow-soft">
        <h3 class="text-lg font-display font-extrabold text-neutral-black mb-2 flex items-center gap-2">
          <Megaphone class="w-5 h-5 text-primary" />
          <span>Explore More SFU MSA Initiatives</span>
        </h3>
        <p class="text-xs text-neutral-black/60 font-sans mb-6">
          Discover upcoming campus events, educational courses, and member tools related to MSA communications.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <RouterLink
            to="/events"
            class="bg-white p-5 rounded-2xl border border-neutral-ivory hover:border-primary/40 shadow-sm hover:shadow-md transition-all flex flex-col justify-between group"
          >
            <div>
              <EventIcon class="w-6 h-6 text-primary mb-2" />
              <h4 class="text-sm font-bold font-display text-neutral-black group-hover:text-primary transition-colors">Upcoming Events</h4>
              <p class="text-xs text-neutral-black/60 font-sans mt-1">Join Jumu'ah, social halaqahs & sports.</p>
            </div>
            <span class="inline-flex items-center gap-1 text-[11px] font-extrabold uppercase tracking-wider text-primary mt-4">
              <span>Browse EMS</span>
              <ChevronRight class="w-3.5 h-3.5 transition-transform group-hover:translate-x-0.5" />
            </span>
          </RouterLink>

          <RouterLink
            to="/featured-opportunities"
            class="bg-white p-5 rounded-2xl border border-neutral-ivory hover:border-primary/40 shadow-sm hover:shadow-md transition-all flex flex-col justify-between group"
          >
            <div>
              <BookOpen class="w-6 h-6 text-primary mb-2" />
              <h4 class="text-sm font-bold font-display text-neutral-black group-hover:text-primary transition-colors">Learning Resources</h4>
              <p class="text-xs text-neutral-black/60 font-sans mt-1">Explore courses & starter guides.</p>
            </div>
            <span class="inline-flex items-center gap-1 text-[11px] font-extrabold uppercase tracking-wider text-primary mt-4">
              <span>View Resources</span>
              <ChevronRight class="w-3.5 h-3.5 transition-transform group-hover:translate-x-0.5" />
            </span>
          </RouterLink>

          <RouterLink
            to="/account"
            class="bg-white p-5 rounded-2xl border border-neutral-ivory hover:border-primary/40 shadow-sm hover:shadow-md transition-all flex flex-col justify-between group"
          >
            <div>
              <UserCheck class="w-6 h-6 text-primary mb-2" />
              <h4 class="text-sm font-bold font-display text-neutral-black group-hover:text-primary transition-colors">Member Portal</h4>
              <p class="text-xs text-neutral-black/60 font-sans mt-1">Manage notification preferences.</p>
            </div>
            <span class="inline-flex items-center gap-1 text-[11px] font-extrabold uppercase tracking-wider text-primary mt-4">
              <span>Account Settings</span>
              <ChevronRight class="w-3.5 h-3.5 transition-transform group-hover:translate-x-0.5" />
            </span>
          </RouterLink>
        </div>
      </div>

      <!-- Related Communications Section -->
      <div v-if="relatedAnnouncements.length > 0">
        <h3 class="text-xl font-display font-extrabold text-neutral-black mb-6 flex items-center gap-2">
          <Megaphone class="w-5 h-5 text-primary" />
          <span>Related Communications</span>
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div
            v-for="item in relatedAnnouncements"
            :key="item.id"
            class="bg-white border border-neutral-ivory rounded-3xl p-6 shadow-soft hover:shadow-premium transition-all duration-300 flex flex-col justify-between group"
          >
            <div>
              <span class="px-2.5 py-0.5 bg-neutral-background text-neutral-black/70 text-[10px] font-bold uppercase tracking-widest rounded-full border border-neutral-ivory inline-block mb-3">
                {{ item.category || item.summary || 'General' }}
              </span>

              <h4 class="text-base font-display font-bold text-neutral-black group-hover:text-primary transition-colors leading-snug mb-2 line-clamp-2">
                {{ item.title }}
              </h4>

              <p v-if="item.date" class="text-xs text-neutral-black/50 font-sans mb-4">
                {{ formatDate(item.date) }}
              </p>
            </div>

            <RouterLink
              :to="`/announcements/${item.slug || item.id}`"
              class="inline-flex items-center gap-1 text-xs font-extrabold uppercase tracking-wider text-primary group-hover:text-secondary transition-colors mt-2"
            >
              <span>Read Announcement</span>
              <ChevronRight class="w-3.5 h-3.5 transition-transform group-hover:translate-x-0.5" />
            </RouterLink>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
