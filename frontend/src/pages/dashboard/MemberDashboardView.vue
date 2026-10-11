<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { 
  memberDashboardService, 
  type DashboardDataResponse, 
  type DashboardNotificationItem 
} from '@/services/memberDashboardService';
import websiteService, { type AnnouncementItem } from '@/services/website/websiteService';
import { 
  Calendar, 
  Ticket, 
  Heart, 
  BookOpen, 
  ShoppingBag, 
  Bell, 
  Sparkles, 
  ShieldCheck, 
  ArrowRight, 
  CheckCircle2, 
  AlertTriangle, 
  ExternalLink, 
  Clock, 
  MapPin, 
  RefreshCw,
  Megaphone,
  CheckCircle,
  UserCheck
} from 'lucide-vue-next';

const dashboardData = ref<DashboardDataResponse | null>(null);
const announcements = ref<AnnouncementItem[]>([]);
const isLoading = ref<boolean>(true);
const errorMessage = ref<string | null>(null);
const markingNotificationId = ref<string | number | null>(null);

const fetchDashboard = async () => {
  isLoading.value = true;
  errorMessage.value = null;
  try {
    dashboardData.value = await memberDashboardService.getDashboardData();
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to load your member dashboard.';
  } finally {
    isLoading.value = false;
  }
};

const markRead = async (notif: DashboardNotificationItem) => {
  markingNotificationId.value = notif.id || notif.uuid;
  try {
    await memberDashboardService.markNotificationRead(notif.uuid || notif.id);
    if (dashboardData.value) {
      dashboardData.value.notifications.latest = dashboardData.value.notifications.latest.filter(
        (n) => n.id !== notif.id && n.uuid !== notif.uuid
      );
      dashboardData.value.notifications.unread_count = Math.max(
        0,
        dashboardData.value.notifications.unread_count - 1
      );
    }
  } catch (err) {
    console.error('Failed to mark notification as read:', err);
  } finally {
    markingNotificationId.value = null;
  }
};

const formatDate = (dateStr?: string | null) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
};

const memberApps = computed(() => {
  if (!dashboardData.value) return [];
  return dashboardData.value.applications.filter((app) => !app.is_admin);
});

const adminApps = computed(() => {
  if (!dashboardData.value) return [];
  return dashboardData.value.applications.filter((app) => app.is_admin);
});

onMounted(async () => {
  fetchDashboard();
  try {
    const list = await websiteService.getAnnouncements();
    announcements.value = list.slice(0, 3);
  } catch {
    // optional fallback
  }
});
</script>

<template>
  <div class="space-y-8">
    <!-- Loading Skeleton -->
    <div v-if="isLoading" class="space-y-6" aria-busy="true">
      <div class="h-44 rounded-3xl bg-white border border-neutral-ivory animate-pulse p-6" />
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="h-64 rounded-3xl bg-white border border-neutral-ivory animate-pulse col-span-2" />
        <div class="h-64 rounded-3xl bg-white border border-neutral-ivory animate-pulse" />
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="errorMessage" class="bg-red-50 border border-red-200 rounded-3xl p-8 text-center text-red-700 max-w-2xl mx-auto space-y-4">
      <AlertTriangle class="h-10 w-10 text-red-600 mx-auto" />
      <h2 class="text-xl font-black">Unable to Load Dashboard</h2>
      <p class="text-sm font-medium text-red-800/80">{{ errorMessage }}</p>
      <button 
        @click="fetchDashboard" 
        class="px-6 py-3 bg-primary text-white rounded-xl text-xs font-extrabold uppercase tracking-widest hover:brightness-110 transition-all inline-flex items-center gap-2 cursor-pointer shadow-soft"
      >
        <RefreshCw class="h-4 w-4" />
        Try Again
      </button>
    </div>

    <!-- Main Dashboard View -->
    <div v-else-if="dashboardData" class="space-y-8">
      <!-- 1. Quick Member Stat Cards -->
      <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <router-link to="/account/activity" class="bg-white p-5 rounded-2xl border border-neutral-ivory shadow-soft hover:shadow-premium transition-all group">
          <div class="flex items-center justify-between text-primary mb-2">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/60 group-hover:text-primary transition-colors">Events</span>
            <Calendar class="h-4 w-4" />
          </div>
          <div class="text-2xl font-black text-primary">{{ dashboardData.upcoming_events_count ?? 0 }}</div>
          <p class="text-[10px] text-neutral-black/50 mt-1">Upcoming registered</p>
        </router-link>

        <router-link to="/store/my-orders" class="bg-white p-5 rounded-2xl border border-neutral-ivory shadow-soft hover:shadow-premium transition-all group">
          <div class="flex items-center justify-between text-primary mb-2">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/60 group-hover:text-primary transition-colors">Orders</span>
            <ShoppingBag class="h-4 w-4" />
          </div>
          <div class="text-2xl font-black text-primary">{{ dashboardData.recent_order ? 1 : 0 }}</div>
          <p class="text-[10px] text-neutral-black/50 mt-1">Store purchases</p>
        </router-link>

        <router-link to="/account/volunteer" class="bg-white p-5 rounded-2xl border border-neutral-ivory shadow-soft hover:shadow-premium transition-all group">
          <div class="flex items-center justify-between text-primary mb-2">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/60 group-hover:text-primary transition-colors">Volunteer</span>
            <Heart class="h-4 w-4" />
          </div>
          <div class="text-2xl font-black text-primary">{{ dashboardData.volunteer?.completed_shifts ?? 0 }}</div>
          <p class="text-[10px] text-neutral-black/50 mt-1">Shifts completed</p>
        </router-link>

        <router-link to="/academy" class="bg-white p-5 rounded-2xl border border-neutral-ivory shadow-soft hover:shadow-premium transition-all group">
          <div class="flex items-center justify-between text-primary mb-2">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/60 group-hover:text-primary transition-colors">Courses</span>
            <BookOpen class="h-4 w-4" />
          </div>
          <div class="text-2xl font-black text-primary">{{ dashboardData.learning?.completed_courses ?? 0 }}</div>
          <p class="text-[10px] text-neutral-black/50 mt-1">Completed courses</p>
        </router-link>

        <router-link to="/notifications" class="bg-white p-5 rounded-2xl border border-neutral-ivory shadow-soft hover:shadow-premium transition-all group col-span-2 md:col-span-1">
          <div class="flex items-center justify-between text-primary mb-2">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/60 group-hover:text-primary transition-colors">Unread</span>
            <Bell class="h-4 w-4" />
          </div>
          <div class="text-2xl font-black text-primary">{{ dashboardData.notifications?.unread_count ?? 0 }}</div>
          <p class="text-[10px] text-neutral-black/50 mt-1">Unread notifications</p>
        </router-link>
      </div>

        <!-- 2. Action Center / Dynamic Tasks (Scope A) -->
        <section class="space-y-3">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Sparkles class="h-4 w-4 text-primary" />
              <h2 class="text-xs font-extrabold uppercase tracking-widest text-primary">Your Next Steps & Action Center</h2>
            </div>
            <span v-if="dashboardData.action_items.length > 0" class="text-xs font-bold text-neutral-black/50">
              {{ dashboardData.action_items.length }} Pending {{ dashboardData.action_items.length === 1 ? 'Action' : 'Actions' }}
            </span>
          </div>

          <div v-if="dashboardData.action_items.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div 
              v-for="item in dashboardData.action_items" 
              :key="item.id"
              :class="[
                'p-5 rounded-2xl border transition-all flex flex-col justify-between space-y-3',
                item.type === 'urgent' ? 'bg-amber-50 border-amber-200 text-amber-950' : 
                item.type === 'warning' ? 'bg-orange-50 border-orange-200 text-orange-950' : 
                'bg-blue-50 border-blue-200 text-blue-950'
              ]"
            >
              <div class="space-y-1">
                <div class="flex items-center justify-between">
                  <span class="px-2 py-0.5 rounded text-[9px] font-extrabold uppercase tracking-wider bg-white/70 border border-current/20">
                    {{ item.type }}
                  </span>
                </div>
                <h3 class="font-extrabold text-sm flex items-center gap-2 mt-1">
                  <AlertTriangle v-if="item.type === 'urgent' || item.type === 'warning'" class="h-4 w-4 shrink-0" />
                  <CheckCircle2 v-else class="h-4 w-4 shrink-0" />
                  {{ item.title }}
                </h3>
                <p class="text-xs opacity-90 leading-relaxed font-medium">
                  {{ item.description }}
                </p>
              </div>

              <div>
                <router-link 
                  v-if="item.action_path.startsWith('/')" 
                  :to="item.action_path" 
                  class="px-4 py-2 bg-primary text-white rounded-xl text-xs font-extrabold uppercase tracking-wider hover:bg-secondary transition-all inline-flex items-center gap-1.5 shadow-soft"
                >
                  {{ item.action_label }}
                  <ArrowRight class="h-3 w-3" />
                </router-link>
                <a 
                  v-else 
                  :href="item.action_path" 
                  class="px-4 py-2 bg-primary text-white rounded-xl text-xs font-extrabold uppercase tracking-wider hover:bg-secondary transition-all inline-flex items-center gap-1.5 shadow-soft"
                >
                  {{ item.action_label }}
                  <ArrowRight class="h-3 w-3" />
                </a>
              </div>
            </div>
          </div>

          <!-- Empty Action Center State -->
          <div v-else class="p-6 rounded-2xl bg-emerald-50/60 border border-emerald-200 flex items-center gap-4 text-emerald-900">
            <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center shrink-0">
              <CheckCircle class="h-5 w-5 text-emerald-600" />
            </div>
            <div>
              <h3 class="font-extrabold text-sm">You're all caught up!</h3>
              <p class="text-xs text-emerald-700/80 font-medium">There are no urgent actions or pending requirements on your account right now.</p>
            </div>
          </div>
        </section>

        <!-- 3. Spotlight Section: Next Upcoming Event + Unread Notifications -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <!-- Next Event Spotlight (2 Cols) -->
          <div class="lg:col-span-2 bg-white p-6 sm:p-8 rounded-3xl border border-neutral-ivory shadow-soft flex flex-col justify-between space-y-6">
            <div class="flex items-center justify-between border-b border-neutral-ivory pb-4">
              <div class="flex items-center gap-2">
                <Calendar class="h-5 w-5 text-primary" />
                <h2 class="text-sm font-extrabold uppercase tracking-wider text-primary">Your Next Registered Event</h2>
              </div>
              <span class="text-xs font-bold text-neutral-black/50">
                {{ dashboardData.upcoming_events_count }} Upcoming {{ dashboardData.upcoming_events_count === 1 ? 'Event' : 'Events' }}
              </span>
            </div>

            <!-- If Next Event Exists -->
            <div v-if="dashboardData.next_event" class="space-y-4">
              <div class="flex flex-wrap gap-2">
                <span v-if="dashboardData.next_event.category" class="px-2.5 py-0.5 rounded-full text-[9px] font-extrabold uppercase tracking-widest bg-primary/10 text-primary border border-primary/20">
                  {{ dashboardData.next_event.category }}
                </span>
                <span class="px-2.5 py-0.5 rounded-full text-[9px] font-extrabold uppercase tracking-widest bg-emerald-50 text-emerald-700 border border-emerald-200">
                  {{ dashboardData.next_event.status }}
                </span>
              </div>

              <div>
                <h3 class="font-display font-black text-2xl text-neutral-black">
                  {{ dashboardData.next_event.event_title }}
                </h3>
                <p class="text-xs text-neutral-black/50 font-mono mt-1">
                  Registration Ref: {{ dashboardData.next_event.reference }}
                </p>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs font-semibold text-neutral-black/80 pt-2">
                <div class="flex items-center gap-2">
                  <Clock class="h-4 w-4 text-primary shrink-0" />
                  <span>{{ formatDate(dashboardData.next_event.start_at) }}</span>
                </div>
                <div v-if="dashboardData.next_event.location" class="flex items-center gap-2">
                  <MapPin class="h-4 w-4 text-primary shrink-0" />
                  <span>{{ dashboardData.next_event.location }}</span>
                </div>
              </div>

              <div class="pt-4 border-t border-neutral-ivory flex flex-wrap gap-3 items-center">
                <router-link 
                  v-if="dashboardData.next_event.ticket_code" 
                  :to="`/tickets/${dashboardData.next_event.ticket_code}`" 
                  class="px-5 py-2.5 bg-primary text-white rounded-xl text-xs font-extrabold uppercase tracking-wider hover:bg-secondary transition-all inline-flex items-center gap-2 shadow-soft"
                >
                  <Ticket class="h-4 w-4" />
                  View Ticket & QR
                </router-link>
                <router-link 
                  :to="`/events/${dashboardData.next_event.event_slug}`" 
                  class="px-5 py-2.5 bg-neutral-background text-neutral-black hover:bg-neutral-ivory rounded-xl text-xs font-extrabold uppercase tracking-wider transition-all inline-flex items-center gap-1 border border-neutral-ivory"
                >
                  Event Details
                </router-link>
              </div>
            </div>

            <!-- Empty Event State -->
            <div v-else class="text-center py-10 space-y-4">
              <Calendar class="h-10 w-10 text-neutral-black/30 mx-auto" />
              <div>
                <h3 class="font-bold text-sm text-neutral-black">No Upcoming Registered Events</h3>
                <p class="text-xs text-neutral-black/60 mt-1 max-w-sm mx-auto">
                  Browse SFU MSA community events, halaqas, and workshops to register for free.
                </p>
              </div>
              <router-link 
                to="/events" 
                class="mt-2 inline-flex items-center gap-1.5 px-5 py-2.5 bg-primary text-white rounded-xl text-xs font-extrabold uppercase tracking-wider hover:bg-secondary transition-all shadow-soft"
              >
                Browse Community Events
                <ArrowRight class="h-3.5 w-3.5" />
              </router-link>
            </div>
          </div>

          <!-- Unread Notifications Section (1 Col) -->
          <div id="notifications" class="bg-white p-6 sm:p-8 rounded-3xl border border-neutral-ivory shadow-soft flex flex-col justify-between space-y-4">
            <div>
              <div class="flex items-center justify-between border-b border-neutral-ivory pb-4 mb-4">
                <div class="flex items-center gap-2">
                  <Bell class="h-5 w-5 text-primary" />
                  <h2 class="text-sm font-extrabold uppercase tracking-wider text-primary">Notifications</h2>
                </div>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-primary/10 text-primary border border-primary/20">
                  {{ dashboardData.notifications.unread_count }} Unread
                </span>
              </div>

              <!-- Notifications List -->
              <div v-if="dashboardData.notifications.latest.length > 0" class="space-y-3">
                <div 
                  v-for="notif in dashboardData.notifications.latest" 
                  :key="notif.id || notif.uuid"
                  class="p-3.5 rounded-2xl border border-neutral-ivory bg-neutral-background/50 hover:bg-white transition-all space-y-1 group"
                >
                  <div class="flex items-center justify-between">
                    <h4 class="font-extrabold text-xs text-neutral-black group-hover:text-primary transition-colors line-clamp-1">
                      {{ notif.title }}
                    </h4>
                    <button 
                      @click="markRead(notif)" 
                      class="text-[10px] text-neutral-black/40 hover:text-primary font-bold uppercase transition-colors shrink-0"
                      :disabled="markingNotificationId === (notif.id || notif.uuid)"
                    >
                      {{ markingNotificationId === (notif.id || notif.uuid) ? '...' : 'Mark Read' }}
                    </button>
                  </div>
                  <p class="text-[11px] text-neutral-black/70 font-medium leading-normal line-clamp-2">
                    {{ notif.message }}
                  </p>
                  <span class="text-[9px] text-neutral-black/40 font-mono block">
                    {{ formatDate(notif.created_at) }}
                  </span>
                </div>
              </div>

              <div v-else class="text-center py-10 text-neutral-black/50 space-y-2">
                <CheckCircle2 class="h-8 w-8 text-emerald-500 mx-auto" />
                <p class="text-xs font-bold">You're all caught up!</p>
                <p class="text-[11px] text-neutral-black/60">No unread notifications right now.</p>
              </div>
            </div>

            <div class="pt-3 border-t border-neutral-ivory text-center">
              <router-link to="/account/activity" class="text-xs font-bold text-primary hover:underline">
                View Full Activity History &rarr;
              </router-link>
            </div>
          </div>
        </div>

        <!-- 3b. Scope B: Relevant Volunteer Opportunity Discovery -->
        <section class="bg-white p-6 sm:p-8 rounded-3xl border border-neutral-ivory shadow-soft space-y-4">
          <div class="flex items-center justify-between border-b border-neutral-ivory pb-4">
            <div class="flex items-center gap-2">
              <Heart class="h-5 w-5 text-primary" />
              <h2 class="text-sm font-extrabold uppercase tracking-wider text-primary">Recommended Volunteer Opportunities</h2>
            </div>
            <router-link to="/volunteer" class="text-xs font-bold text-primary hover:underline flex items-center gap-1">
              Browse All Opportunities &rarr;
            </router-link>
          </div>

          <div v-if="dashboardData.recommended_opportunities && dashboardData.recommended_opportunities.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div
              v-for="opp in dashboardData.recommended_opportunities"
              :key="opp.id"
              class="p-4 rounded-2xl border border-neutral-ivory bg-neutral-background/40 hover:bg-white hover:border-primary/40 transition-all flex flex-col justify-between space-y-3 group"
            >
              <div class="space-y-2">
                <div class="flex items-center justify-between gap-2 flex-wrap">
                  <!-- Explainable Reason Label -->
                  <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase bg-accent-gold/20 text-accent-gold border border-accent-gold/30">
                    {{ opp.reason_label }}
                  </span>
                  <span v-if="opp.category" class="text-[10px] text-neutral-black/50 font-bold uppercase">
                    {{ opp.category }}
                  </span>
                </div>

                <h3 class="font-extrabold text-sm text-neutral-black group-hover:text-primary transition-colors line-clamp-2">
                  {{ opp.title }}
                </h3>

                <div class="space-y-1 text-xs text-neutral-black/70 font-medium">
                  <div v-if="opp.start_at" class="flex items-center gap-1.5">
                    <Clock class="h-3.5 w-3.5 text-primary shrink-0" />
                    <span>{{ formatDate(opp.start_at) }}</span>
                  </div>
                  <div v-if="opp.location" class="flex items-center gap-1.5">
                    <MapPin class="h-3.5 w-3.5 text-primary shrink-0" />
                    <span>{{ opp.location }}</span>
                  </div>
                </div>

                <div v-if="opp.required_skills && opp.required_skills.length > 0" class="pt-1 flex flex-wrap gap-1">
                  <span 
                    v-for="sk in opp.required_skills" 
                    :key="sk"
                    class="px-2 py-0.5 bg-neutral-100 text-neutral-700 rounded text-[9px] font-semibold"
                  >
                    {{ sk }}
                  </span>
                </div>
              </div>

              <div class="pt-2 border-t border-neutral-ivory flex justify-end">
                <router-link
                  :to="`/volunteer/${opp.slug}`"
                  class="px-3.5 py-1.5 bg-primary text-white rounded-xl text-[10px] font-extrabold uppercase tracking-wider hover:bg-secondary transition-all inline-flex items-center gap-1 shadow-soft"
                >
                  View Details & Apply
                  <ArrowRight class="h-3 w-3" />
                </router-link>
              </div>
            </div>
          </div>

          <div v-else class="text-center py-8 text-neutral-black/50 space-y-2">
            <Heart class="h-8 w-8 text-neutral-black/30 mx-auto" />
            <p class="text-xs font-bold">No active volunteer recommendations available right now.</p>
            <router-link to="/volunteer" class="inline-block text-xs font-bold text-primary hover:underline">
              Explore All Volunteer Opportunities &rarr;
            </router-link>
          </div>
        </section>

        <!-- 3c. Official MSA Announcements Section -->
        <section class="bg-white p-6 sm:p-8 rounded-3xl border border-neutral-ivory shadow-soft space-y-4">
          <div class="flex items-center justify-between border-b border-neutral-ivory pb-4">
            <div class="flex items-center gap-2">
              <Megaphone class="h-5 w-5 text-primary" />
              <h2 class="text-sm font-extrabold uppercase tracking-wider text-primary">Official MSA Announcements</h2>
            </div>
            <router-link to="/announcements" class="text-xs font-bold text-primary hover:underline flex items-center gap-1">
              View All Communications &rarr;
            </router-link>
          </div>

          <div v-if="announcements.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div
              v-for="item in announcements"
              :key="item.id"
              class="p-4 rounded-2xl border border-neutral-ivory bg-neutral-background/40 hover:bg-white hover:border-primary/40 transition-all flex flex-col justify-between space-y-3 group"
            >
              <div class="space-y-1.5">
                <div class="flex items-center justify-between gap-2">
                  <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase bg-primary/10 text-primary border border-primary/20">
                    {{ item.category || item.summary || 'General' }}
                  </span>
                  <span v-if="item.date" class="text-[10px] text-neutral-black/50 font-mono">
                    {{ item.date }}
                  </span>
                </div>
                <h3 class="font-extrabold text-xs text-neutral-black group-hover:text-primary transition-colors line-clamp-2">
                  {{ item.title }}
                </h3>
                <p class="text-[11px] text-neutral-black/70 line-clamp-2 leading-relaxed font-medium">
                  {{ item.content }}
                </p>
              </div>

              <div class="pt-2 border-t border-neutral-ivory/60 flex justify-end">
                <router-link
                  :to="`/announcements/${item.slug || item.id}`"
                  class="px-3 py-1.5 bg-primary text-white rounded-xl text-[10px] font-extrabold uppercase tracking-wider hover:bg-secondary transition-all inline-flex items-center gap-1 shadow-soft"
                >
                  Read Notice
                  <ArrowRight class="h-3 w-3" />
                </router-link>
              </div>
            </div>
          </div>

          <div v-else class="text-center py-6 text-neutral-black/50">
            <p class="text-xs font-medium">No recent announcements right now.</p>
          </div>
        </section>

        <!-- 4. Application Access Launchpad -->
        <section class="bg-white p-6 sm:p-8 rounded-3xl border border-neutral-ivory shadow-soft space-y-6">
          <div class="flex items-center justify-between border-b border-neutral-ivory pb-4">
            <div class="flex items-center gap-2">
              <Sparkles class="h-5 w-5 text-primary" />
              <h2 class="text-sm font-extrabold uppercase tracking-wider text-primary">Your MSA Application Launchpad</h2>
            </div>
            <span class="text-xs font-bold text-neutral-black/50">{{ dashboardData.applications.length }} Applications Accessible</span>
          </div>

          <!-- Member Subsystems Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div 
              v-for="app in memberApps" 
              :key="app.slug"
              class="p-4 rounded-2xl border border-neutral-ivory bg-neutral-background/40 hover:bg-white hover:border-primary/40 hover:shadow-soft transition-all duration-300 flex flex-col justify-between group"
            >
              <div class="space-y-1">
                <h3 class="font-black text-xs text-neutral-black group-hover:text-primary transition-colors">
                  {{ app.title }}
                </h3>
                <p class="text-[11px] text-neutral-black/60 leading-relaxed font-medium">
                  {{ app.description }}
                </p>
              </div>

              <div class="mt-4 pt-2 border-t border-neutral-ivory/60 flex justify-end">
                <router-link 
                  :to="app.path" 
                  class="px-3.5 py-1.5 bg-primary text-white rounded-xl text-[10px] font-extrabold uppercase tracking-wider hover:bg-secondary transition-all inline-flex items-center gap-1 shadow-soft"
                >
                  Launch
                  <ExternalLink class="h-3 w-3" />
                </router-link>
              </div>
            </div>
          </div>

          <!-- Administrative Systems Banner (If Admin) -->
          <div v-if="adminApps.length > 0" class="pt-4 border-t border-neutral-ivory space-y-4">
            <div class="flex items-center gap-2 text-purple-900">
              <ShieldCheck class="h-4 w-4" />
              <span class="text-xs font-extrabold uppercase tracking-wider">Platform Administration Portals</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <div 
                v-for="app in adminApps" 
                :key="app.slug"
                class="p-4 rounded-2xl border border-purple-100 bg-purple-50/20 hover:bg-white hover:border-purple-300 transition-all flex flex-col justify-between group"
              >
                <div class="space-y-1">
                  <h4 class="font-black text-xs text-neutral-black group-hover:text-purple-900 transition-colors">
                    {{ app.title }}
                  </h4>
                  <p class="text-[11px] text-neutral-black/60 leading-relaxed font-medium">
                    {{ app.description }}
                  </p>
                </div>

                <div class="mt-4 pt-2 border-t border-neutral-ivory/60 flex justify-end">
                  <router-link 
                    :to="app.path" 
                    class="px-3.5 py-1.5 bg-neutral-black text-white hover:bg-purple-950 rounded-xl text-[10px] font-extrabold uppercase tracking-wider transition-all inline-flex items-center gap-1 shadow-soft"
                  >
                    Open Portal
                    <ExternalLink class="h-3 w-3" />
                  </router-link>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- 5. Scope C: Volunteer Activity, Commitments & Learning Snapshots Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <!-- Scope C: Personal Volunteer Activity & Commitments Card -->
          <div class="bg-white p-6 sm:p-8 rounded-3xl border border-neutral-ivory shadow-soft flex flex-col justify-between space-y-4">
            <div class="flex items-center justify-between border-b border-neutral-ivory pb-4">
              <div class="flex items-center gap-2">
                <Heart class="h-5 w-5 text-primary" />
                <h2 class="text-sm font-extrabold uppercase tracking-wider text-primary">Your Volunteer Activity & Commitments</h2>
              </div>
              <router-link to="/account/volunteer" class="text-xs font-bold text-primary hover:underline">
                Volunteer Portal &rarr;
              </router-link>
            </div>

            <div v-if="dashboardData.volunteer" class="space-y-5">
              <!-- KPI Summary Box -->
              <div class="grid grid-cols-3 gap-2 bg-primary/5 border border-primary/15 rounded-2xl p-3 text-center">
                <div>
                  <span class="text-[9px] font-extrabold uppercase text-primary tracking-wider block">Status</span>
                  <span class="text-xs font-black text-primary capitalize">{{ dashboardData.volunteer.status }}</span>
                </div>
                <div>
                  <span class="text-[9px] font-extrabold uppercase text-primary tracking-wider block">Profile</span>
                  <span class="text-xs font-black text-primary">{{ dashboardData.volunteer.completion_percentage }}%</span>
                </div>
                <div>
                  <span class="text-[9px] font-extrabold uppercase text-primary tracking-wider block">Shifts Done</span>
                  <span class="text-xs font-black text-primary">{{ dashboardData.volunteer.completed_shifts }}</span>
                </div>
              </div>

              <!-- Upcoming Confirmed Commitments -->
              <div class="space-y-2">
                <h4 class="text-xs font-extrabold uppercase tracking-wider text-neutral-black flex items-center gap-1.5">
                  <UserCheck class="h-3.5 w-3.5 text-emerald-600" />
                  Upcoming Commitments
                </h4>

                <div v-if="dashboardData.volunteer.commitments && dashboardData.volunteer.commitments.length > 0" class="space-y-2">
                  <div 
                    v-for="comm in dashboardData.volunteer.commitments" 
                    :key="comm.id"
                    class="p-3 rounded-xl border border-emerald-200 bg-emerald-50/50 flex flex-col space-y-1"
                  >
                    <div class="flex items-center justify-between">
                      <span class="font-bold text-xs text-neutral-black">{{ comm.opportunity_title }}</span>
                      <span class="px-2 py-0.5 rounded text-[9px] font-extrabold uppercase bg-emerald-200 text-emerald-800">
                        {{ comm.status }}
                      </span>
                    </div>
                    <div class="text-[11px] text-neutral-black/70 flex items-center gap-3">
                      <span v-if="comm.shift_name" class="font-medium">{{ comm.shift_name }}</span>
                      <span v-if="comm.start_at" class="font-mono">{{ formatDate(comm.start_at) }}</span>
                    </div>
                  </div>
                </div>

                <p v-else class="text-xs text-neutral-black/50 italic">No upcoming confirmed shifts.</p>
              </div>

              <!-- Pending Applications -->
              <div v-if="dashboardData.volunteer.pending_applications && dashboardData.volunteer.pending_applications.length > 0" class="space-y-2">
                <h4 class="text-xs font-extrabold uppercase tracking-wider text-neutral-black flex items-center gap-1.5">
                  <Clock class="h-3.5 w-3.5 text-amber-600" />
                  Applications Under Review
                </h4>
                <div class="space-y-1.5">
                  <div 
                    v-for="app in dashboardData.volunteer.pending_applications" 
                    :key="app.id"
                    class="p-2.5 rounded-xl border border-amber-200 bg-amber-50/40 flex items-center justify-between text-xs"
                  >
                    <span class="font-bold text-neutral-black">{{ app.opportunity_title }}</span>
                    <span class="px-2 py-0.5 rounded text-[9px] font-extrabold uppercase bg-amber-200 text-amber-900">
                      {{ app.status }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Verified Attendance Summary -->
              <div v-if="dashboardData.volunteer.attendance_summary" class="p-3.5 rounded-2xl bg-neutral-background border border-neutral-ivory space-y-2">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/60 block">Verified Attendance History</span>
                <div class="grid grid-cols-2 gap-2 text-center text-xs">
                  <div class="p-2 bg-white rounded-xl border border-emerald-100">
                    <span class="text-lg font-black text-emerald-700 block">{{ dashboardData.volunteer.attendance_summary.attended_count }}</span>
                    <span class="text-[9px] font-extrabold text-neutral-black/60 uppercase">Attended</span>
                  </div>
                  <div class="p-2 bg-white rounded-xl border border-neutral-200">
                    <span class="text-lg font-black text-neutral-600 block">{{ dashboardData.volunteer.attendance_summary.absent_count }}</span>
                    <span class="text-[9px] font-extrabold text-neutral-black/60 uppercase">Absent / Missed</span>
                  </div>
                </div>
                <p class="text-[10px] text-neutral-black/50 italic text-center pt-1">
                  {{ dashboardData.volunteer.attendance_summary.attendance_note }}
                </p>
              </div>

              <!-- Skills tags -->
              <div v-if="dashboardData.volunteer.skills && dashboardData.volunteer.skills.length > 0" class="space-y-1.5">
                <span class="text-[10px] font-extrabold uppercase text-neutral-black/50 block">Your Profile Skills</span>
                <div class="flex flex-wrap gap-1.5">
                  <span 
                    v-for="skill in dashboardData.volunteer.skills" 
                    :key="skill"
                    class="px-2.5 py-1 bg-neutral-background rounded-xl text-[10px] font-bold text-neutral-black/80 border border-neutral-ivory"
                  >
                    {{ skill }}
                  </span>
                </div>
              </div>
            </div>

            <div v-else class="text-center py-8 text-neutral-black/50 space-y-3">
              <Heart class="h-8 w-8 text-neutral-black/30 mx-auto" />
              <p class="text-xs font-bold">Join the SFU MSA Volunteer Team</p>
              <router-link to="/volunteer" class="inline-flex items-center gap-1 px-4 py-2 bg-primary text-white rounded-xl text-xs font-bold hover:bg-secondary transition-all">
                Become a Volunteer
              </router-link>
            </div>
          </div>

          <!-- Learning Card (Academy) -->
          <div class="bg-white p-6 sm:p-8 rounded-3xl border border-neutral-ivory shadow-soft flex flex-col justify-between space-y-4">
            <div class="flex items-center justify-between border-b border-neutral-ivory pb-4">
              <div class="flex items-center gap-2">
                <BookOpen class="h-5 w-5 text-primary" />
                <h2 class="text-sm font-extrabold uppercase tracking-wider text-primary">Learning Progress</h2>
              </div>
              <router-link to="/academy" class="text-xs font-bold text-primary hover:underline">
                Course Player &rarr;
              </router-link>
            </div>

            <div class="space-y-4">
              <div class="grid grid-cols-2 gap-3 text-center">
                <div class="p-3 bg-neutral-background rounded-2xl border border-neutral-ivory">
                  <div class="text-xl font-black text-primary">{{ dashboardData.learning.completed_courses }}</div>
                  <span class="text-[9px] font-extrabold uppercase text-neutral-black/60">Completed Courses</span>
                </div>
                <div class="p-3 bg-neutral-background rounded-2xl border border-neutral-ivory">
                  <div class="text-xl font-black text-primary">{{ dashboardData.learning.certificates_count }}</div>
                  <span class="text-[9px] font-extrabold uppercase text-neutral-black/60">Certificates Earned</span>
                </div>
              </div>

              <div v-if="dashboardData.learning.active_course" class="p-4 rounded-2xl border border-primary/20 bg-primary/5 space-y-2">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-primary">Continue Learning</span>
                <h4 class="font-extrabold text-xs text-neutral-black">{{ dashboardData.learning.active_course.title }}</h4>
                <router-link 
                  to="/academy" 
                  class="inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline pt-1"
                >
                  Resume Course &rarr;
                </router-link>
              </div>

              <div v-else class="text-center py-4 text-neutral-black/50 space-y-2">
                <p class="text-xs font-bold">No course in progress</p>
                <router-link to="/featured-opportunities" class="inline-block text-xs font-bold text-primary hover:underline">
                  Explore Learning Courses &rarr;
                </router-link>
              </div>
            </div>
          </div>
        </div>

        <!-- 6. Recent Store Order Snapshot -->
        <section v-if="dashboardData.recent_order" class="bg-white p-6 sm:p-8 rounded-3xl border border-neutral-ivory shadow-soft space-y-4">
          <div class="flex items-center justify-between border-b border-neutral-ivory pb-4">
            <div class="flex items-center gap-2">
              <ShoppingBag class="h-5 w-5 text-primary" />
              <h2 class="text-sm font-extrabold uppercase tracking-wider text-primary">Recent Store Purchase</h2>
            </div>
            <router-link to="/store/my-orders" class="text-xs font-bold text-primary hover:underline">
              View All Orders &rarr;
            </router-link>
          </div>

          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-2xl border border-neutral-ivory bg-neutral-background/40">
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="font-black text-sm text-neutral-black">{{ dashboardData.recent_order.order_number }}</span>
                <span class="px-2.5 py-0.5 rounded-full text-[9px] font-extrabold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                  {{ dashboardData.recent_order.payment_status }}
                </span>
                <span class="px-2.5 py-0.5 rounded-full text-[9px] font-extrabold uppercase bg-purple-50 text-purple-700 border border-purple-200">
                  {{ dashboardData.recent_order.fulfillment_status }}
                </span>
              </div>
              <p class="text-xs text-neutral-black/60 font-mono">
                Order Total: {{ dashboardData.recent_order.formatted_total }} · {{ formatDate(dashboardData.recent_order.created_at) }}
              </p>
            </div>

            <router-link 
              to="/store/my-orders" 
              class="px-4 py-2 bg-primary text-white rounded-xl text-xs font-extrabold uppercase tracking-wider hover:bg-secondary transition-all inline-flex items-center gap-1 shrink-0 shadow-soft"
            >
              Order Details
            </router-link>
          </div>
        </section>
    </div>
  </div>
</template>

