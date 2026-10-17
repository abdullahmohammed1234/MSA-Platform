<template>
  <div class="space-y-6">
    <!-- Page Hero -->
    <PageHero
      label="Member Portal"
      :title="`As-salamu alaykum, ${userDisplayName}`"
      description="Your personal hub for upcoming campus gatherings, official MSA announcements, and member passes."
    >
      <template #badge>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-800 text-[10.5px] font-semibold">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
          <span>Live Session</span>
        </span>
      </template>

      <template #actions>
        <Button
          variant="outline"
          size="sm"
          :is-loading="dashboardStore.isLoading"
          @click="refreshData"
          title="Refresh dashboard content"
          class="bg-white hover:bg-[#fffbf4] border-[#ebe8de] text-xs font-semibold shadow-2xs"
        >
          <template #left-icon>
            <RefreshCw class="w-3.5 h-3.5 text-[#b02e32]" :class="{ 'animate-spin': dashboardStore.isLoading }" />
          </template>
          <span>Refresh Updates</span>
        </Button>
      </template>
    </PageHero>

    <!-- Error State -->
    <ErrorState
      v-if="dashboardStore.error && !dashboardStore.data"
      title="Unable to Load Dashboard"
      :message="dashboardStore.error"
      @retry="refreshData"
    />

    <!-- Initial Loading State -->
    <div
      v-else-if="dashboardStore.isLoading && !dashboardStore.data"
      class="py-20 flex flex-col items-center justify-center space-y-3"
    >
      <Spinner size="lg" />
      <p class="text-xs text-neutral-500 font-medium">Gathering your community updates...</p>
    </div>

    <!-- Main Dashboard Body -->
    <div v-else class="space-y-6">
      <!-- 1. Urgent Action Items / Unread Notification Strip -->
      <section v-if="actionItems.length > 0 || unreadCount > 0" class="space-y-2.5" aria-label="Action Items">
        <div
          v-for="item in actionItems"
          :key="item.id"
          class="p-4 rounded-xl bg-white border border-rose-200/90 shadow-2xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3"
        >
          <div class="flex items-center gap-2.5">
            <span class="px-2 py-0.5 rounded-md bg-rose-100 text-rose-800 text-[10.5px] font-bold uppercase tracking-wider">
              Action Required
            </span>
            <h3 class="text-xs sm:text-sm font-bold text-neutral-900">{{ item.title }}</h3>
          </div>
          <p class="text-xs text-neutral-600 sm:max-w-md">{{ item.description }}</p>

          <div v-if="item.action_path" class="shrink-0 w-full sm:w-auto">
            <a
              v-if="isExternalUrl(item.action_path)"
              :href="item.action_path"
              target="_blank"
              rel="noopener noreferrer"
            >
              <Button variant="outline" size="sm" block>
                <span>{{ item.action_label || 'Review' }}</span>
                <template #right-icon>
                  <ExternalLink class="w-3 h-3" />
                </template>
              </Button>
            </a>
            <router-link v-else :to="item.action_path">
              <Button variant="primary" size="sm" block>
                <span>{{ item.action_label || 'Complete' }}</span>
              </Button>
            </router-link>
          </div>
        </div>

        <div
          v-if="unreadCount > 0"
          class="p-3.5 rounded-xl bg-gradient-to-r from-[#ffdc83]/20 via-white to-white border border-[#ffdc83]/80 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 text-xs text-amber-950"
        >
          <div class="flex items-center gap-2">
            <Bell class="w-4 h-4 text-amber-800 shrink-0" />
            <span>You have <strong>{{ unreadCount }}</strong> unread community reminder{{ unreadCount === 1 ? '' : 's' }} or announcement{{ unreadCount === 1 ? '' : 's' }}.</span>
          </div>
          <router-link to="/app/notifications" class="shrink-0 font-semibold text-[#640c0e] hover:text-[#b02e32] inline-flex items-center gap-1">
            <span>View Alerts</span>
            <ArrowRight class="w-3.5 h-3.5" />
          </router-link>
        </div>
      </section>

      <!-- 2. Next Confirmed Event or Discover Bar -->
      <section aria-label="Event Status">
        <!-- If Confirmed Event Exists -->
        <div
          v-if="dashboardData?.next_event"
          class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#640c0e] via-[#7d1316] to-[#911e22] text-white p-5 sm:p-7 shadow-sm border border-[#640c0e]"
        >
          <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-2.5 max-w-2xl">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-[#ffdc83] text-[#640c0e] tracking-wider uppercase">
                  Your Next Confirmed Event
                </span>
                <span
                  v-if="dashboardData.next_event.category"
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-medium bg-white/15 text-white/90"
                >
                  {{ dashboardData.next_event.category }}
                </span>
                <span
                  v-if="dashboardData.next_event.reference"
                  class="text-[11px] font-mono text-white/70"
                >
                  Pass #{{ dashboardData.next_event.reference }}
                </span>
              </div>

              <h2 class="text-xl sm:text-2xl font-bold text-white tracking-tight leading-snug">
                {{ dashboardData.next_event.event_title }}
              </h2>

              <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-white/90">
                <div class="flex items-center gap-1.5">
                  <Calendar class="w-4 h-4 text-[#ffdc83] shrink-0" />
                  <span>{{ formatEventDateTime(dashboardData.next_event.start_at) }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                  <MapPin class="w-4 h-4 text-[#ffdc83] shrink-0" />
                  <span>{{ dashboardData.next_event.location || 'Burnaby Campus' }}</span>
                </div>
                <div v-if="dashboardData.next_event.ticket_code" class="flex items-center gap-1.5">
                  <Ticket class="w-4 h-4 text-[#ffdc83] shrink-0" />
                  <span class="font-mono bg-white/10 px-2 py-0.5 rounded text-[11px]">Code: {{ dashboardData.next_event.ticket_code }}</span>
                </div>
              </div>
            </div>

            <div class="flex sm:flex-row md:flex-col items-stretch gap-2 shrink-0">
              <router-link :to="`/app/events/${dashboardData.next_event.event_slug}`" class="w-full">
                <Button variant="gold" size="sm" block>
                  <span>Event Details</span>
                  <template #right-icon>
                    <ArrowRight class="w-3.5 h-3.5" />
                  </template>
                </Button>
              </router-link>
              <router-link to="/app/tickets" class="w-full">
                <Button variant="outline" size="sm" block class="bg-white/10 text-white border-white/30 hover:bg-white/20">
                  <span>Open in Wallet</span>
                </Button>
              </router-link>
            </div>
          </div>
        </div>

        <!-- If No Event: Clean Welcome Strip -->
        <div
          v-else
          class="p-5 sm:p-6 rounded-2xl bg-white border border-[#ebe8de] shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-4"
        >
          <div class="flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-xl bg-[#fffbf4] border border-[#ebe8de] text-[#640c0e] flex items-center justify-center shrink-0">
              <Calendar class="w-5 h-5 text-[#b02e32]" />
            </div>
            <div>
              <h2 class="text-sm sm:text-base font-bold text-[#640c0e]">Explore Upcoming Campus Gatherings</h2>
              <p class="text-xs text-neutral-600 mt-0.5">
                Discover weekly Friday Jummah prayers, academic halaqahs, and campus socials.
              </p>
            </div>
          </div>

          <router-link to="/app/events" class="shrink-0">
            <Button variant="primary" size="sm">
              <span>Browse Events</span>
              <template #right-icon>
                <ArrowRight class="w-3.5 h-3.5" />
              </template>
            </Button>
          </router-link>
        </div>
      </section>

      <!-- 3. Official Announcements Section -->
      <section class="space-y-3.5" aria-labelledby="announcements-heading">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg bg-rose-50 text-[#b02e32] flex items-center justify-center">
              <Megaphone class="w-3.5 h-3.5" />
            </div>
            <div>
              <h2 id="announcements-heading" class="text-base font-bold text-[#640c0e]">
                Official Announcements
              </h2>
            </div>
          </div>

          <a
            href="https://sfumsa.ca"
            target="_blank"
            rel="noopener noreferrer"
            class="text-xs font-semibold text-[#b02e32] hover:text-[#640c0e] flex items-center gap-1 transition-colors"
          >
            <span>sfumsa.ca</span>
            <ExternalLink class="w-3 h-3" />
          </a>
        </div>

        <!-- Announcements Grid -->
        <div v-if="announcements.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <article
            v-for="announcement in announcements.slice(0, 3)"
            :key="announcement.id"
            class="p-4 sm:p-5 rounded-2xl bg-white border border-[#ebe8de] shadow-2xs flex flex-col justify-between space-y-3 hover:border-[#640c0e]/30 transition-all"
          >
            <div class="space-y-2">
              <div class="flex items-center justify-between gap-2 flex-wrap">
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#fffbf4] border border-[#ebe8de] text-[#640c0e]">
                  {{ announcement.category || 'Announcement' }}
                </span>
                <span class="text-[11px] text-neutral-500 font-medium">
                  {{ formatEventDate(announcement.date) }}
                </span>
              </div>

              <h3 class="font-bold text-[#640c0e] text-sm sm:text-base leading-snug">
                {{ announcement.title }}
              </h3>

              <p class="text-xs text-neutral-600 leading-relaxed line-clamp-3">
                {{ announcement.summary || announcement.content }}
              </p>
            </div>

            <div class="pt-2 border-t border-[#ebe8de]/70 flex items-center justify-between text-[10.5px] text-neutral-500">
              <span class="font-medium">SFU MSA Official</span>
              <span class="text-emerald-700 font-semibold">Verified Notice</span>
            </div>
          </article>
        </div>

        <!-- Empty Announcements -->
        <div
          v-else
          class="p-6 rounded-2xl bg-white border border-[#ebe8de] text-center space-y-1.5"
        >
          <Bell class="w-5 h-5 text-neutral-400 mx-auto" />
          <h3 class="text-xs font-bold text-neutral-800">No New Announcements</h3>
          <p class="text-xs text-neutral-500 max-w-sm mx-auto">
            There are no active notices posted at this moment. Friday prayer details and study halaqah announcements will appear here.
          </p>
        </div>
      </section>

      <!-- 4. Cohesive Member Launchpad (3 Balanced Cards) -->
      <section class="space-y-3" aria-labelledby="launchpad-heading">
        <h2 id="launchpad-heading" class="text-base font-bold text-[#640c0e]">
          Quick Portal Destinations
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- 1. Passes Wallet -->
          <router-link
            to="/app/tickets"
            class="p-5 rounded-2xl bg-white border border-[#ebe8de] shadow-2xs hover:border-[#640c0e]/40 transition-all flex flex-col justify-between space-y-3 group"
          >
            <div class="space-y-2">
              <div class="w-9 h-9 rounded-xl bg-rose-50 text-[#b02e32] flex items-center justify-center group-hover:scale-105 transition-transform">
                <Ticket class="w-4 h-4" />
              </div>
              <h3 class="text-sm font-bold text-[#640c0e] group-hover:text-[#b02e32] transition-colors">
                My Passes Wallet
              </h3>
              <p class="text-xs text-neutral-500 leading-relaxed">
                Access your check-in QR codes, confirmed event reservations, and entry passes.
              </p>
            </div>
            <div class="text-xs font-semibold text-[#b02e32] flex items-center gap-1">
              <span>Open Wallet</span>
              <ArrowRight class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" />
            </div>
          </router-link>

          <!-- 2. Events Directory -->
          <router-link
            to="/app/events"
            class="p-5 rounded-2xl bg-white border border-[#ebe8de] shadow-2xs hover:border-[#640c0e]/40 transition-all flex flex-col justify-between space-y-3 group"
          >
            <div class="space-y-2">
              <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-800 flex items-center justify-center group-hover:scale-105 transition-transform">
                <Calendar class="w-4 h-4" />
              </div>
              <h3 class="text-sm font-bold text-[#640c0e] group-hover:text-[#b02e32] transition-colors">
                Campus Events & Halaqahs
              </h3>
              <p class="text-xs text-neutral-500 leading-relaxed">
                Browse upcoming lectures, study circles, socials, and Friday congregational prayers.
              </p>
            </div>
            <div class="text-xs font-semibold text-[#b02e32] flex items-center gap-1">
              <span>Browse Events</span>
              <ArrowRight class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" />
            </div>
          </router-link>

          <!-- 3. Campus Support & Prayer Space -->
          <router-link
            to="/app/support"
            class="p-5 rounded-2xl bg-white border border-[#ebe8de] shadow-2xs hover:border-[#640c0e]/40 transition-all flex flex-col justify-between space-y-3 group"
          >
            <div class="space-y-2">
              <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-800 flex items-center justify-center group-hover:scale-105 transition-transform">
                <HeartHandshake class="w-4 h-4" />
              </div>
              <h3 class="text-sm font-bold text-[#640c0e] group-hover:text-[#b02e32] transition-colors">
                Member Support & Inquiries
              </h3>
              <p class="text-xs text-neutral-500 leading-relaxed">
                Connect privately with MSA coordinators, request peer support, or find prayer facilities.
              </p>
            </div>
            <div class="text-xs font-semibold text-[#b02e32] flex items-center gap-1">
              <span>Get Support</span>
              <ArrowRight class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" />
            </div>
          </router-link>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useDashboardStore } from '@/stores/dashboard';
import { useAuthStore } from '@/stores/auth';
import { formatEventDate, formatEventDateTime } from '@/utils/formatters';
import PageHero from '@/components/ui/PageHero.vue';
import Button from '@/components/ui/Button.vue';
import Spinner from '@/components/ui/Spinner.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import {
  Calendar,
  MapPin,
  Ticket,
  Bell,
  Megaphone,
  HeartHandshake,
  ArrowRight,
  ExternalLink,
  RefreshCw,
} from 'lucide-vue-next';

const dashboardStore = useDashboardStore();
const authStore = useAuthStore();

const userDisplayName = computed(() => {
  return authStore.user?.name?.split(' ')[0] || authStore.user?.name || 'Member';
});

const dashboardData = computed(() => dashboardStore.data);
const actionItems = computed(() => dashboardData.value?.action_items || []);
const announcements = computed(() => dashboardStore.announcements);
const unreadCount = computed(() => dashboardData.value?.notifications?.unread_count ?? 0);

function isExternalUrl(url: string): boolean {
  return url.startsWith('http://') || url.startsWith('https://');
}

async function refreshData() {
  await Promise.all([
    dashboardStore.fetchDashboard(true),
    dashboardStore.fetchAnnouncements(true),
  ]);
}

onMounted(async () => {
  await Promise.all([
    dashboardStore.fetchDashboard(),
    dashboardStore.fetchAnnouncements(),
  ]);
});
</script>
