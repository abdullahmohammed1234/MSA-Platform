<script setup lang="ts">
import { onMounted } from 'vue';
import { useCmsStore } from '@/stores/cms';
import { 
  FileText, 
  Calendar, 
  Users, 
  BookOpen, 
  ArrowRight, 
  Clock, 
  Plus,
  Image
} from 'lucide-vue-next';

const cmsStore = useCmsStore();

onMounted(async () => {
  try {
    await cmsStore.fetchDashboard();
  } catch (err) {
    console.error('Failed to load CMS dashboard statistics:', err);
  }
});

const formatTime = (timeStr: string) => {
  return new Date(timeStr).toLocaleString();
};

const quickActions = [
  { label: 'Edit Homepage Section', path: '/admin/cms/homepage', color: 'text-primary bg-primary/5 border-primary/20', icon: FileText },
  { label: 'Create Announcement', path: '/admin/cms/announcements', color: 'text-secondary bg-secondary/5 border-secondary/20', icon: Plus },
  { label: 'Schedule New Event', path: '/admin/cms/events', color: 'text-accent-gold bg-accent-gold/5 border-accent-gold/20', icon: Calendar },
  { label: 'Upload Media File', path: '/admin/cms/media', color: 'text-primary-light bg-primary-light/5 border-primary-light/20', icon: Image },
];
</script>

<template>
  <div class="space-y-8 text-neutral-black">
    <!-- Header -->
    <div class="flex justify-between items-center">
      <div>
        <h1 class="text-4xl font-display font-extrabold text-primary">CMS Dashboard</h1>
        <p class="text-neutral-black/45 text-sm">Manage Simon Fraser University Muslim Students Association web content without code changes.</p>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="cmsStore.isLoading && !cmsStore.stats" class="flex items-center justify-center py-20">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="cmsStore.error" class="p-6 bg-secondary/5 border border-secondary/20 rounded-3xl text-secondary text-sm">
      {{ cmsStore.error }}
    </div>

    <template v-else-if="cmsStore.stats">
      <!-- Stats Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Announcements -->
        <div class="p-6 bg-white border border-neutral-gray/10 rounded-3xl shadow-soft flex items-center justify-between group hover:border-primary/25 hover:shadow-premium transition-all duration-300">
          <div class="space-y-2">
            <span class="text-[10px] font-black uppercase tracking-widest text-neutral-black/35">Announcements</span>
            <div class="text-3xl font-display font-black text-primary">{{ cmsStore.stats.announcements.total }}</div>
            <div class="text-[10px] font-bold text-neutral-black/55">
              <span class="text-primary font-black">{{ cmsStore.stats.announcements.published }}</span> Published &bull;
              <span>{{ cmsStore.stats.announcements.drafts }}</span> Drafts
            </div>
          </div>
          <div class="p-4 bg-primary/5 rounded-2xl text-primary group-hover:bg-primary group-hover:text-white transition-all duration-500">
            <FileText :size="24" />
          </div>
        </div>

        <!-- Events -->
        <div class="p-6 bg-white border border-neutral-gray/10 rounded-3xl shadow-soft flex items-center justify-between group hover:border-primary/25 hover:shadow-premium transition-all duration-300">
          <div class="space-y-2">
            <span class="text-[10px] font-black uppercase tracking-widest text-neutral-black/35">Events Calendar</span>
            <div class="text-3xl font-display font-black text-primary">{{ cmsStore.stats.events.total }}</div>
            <div class="text-[10px] font-bold text-neutral-black/55">
              <span class="text-accent-gold font-black">{{ cmsStore.stats.events.upcoming }}</span> Upcoming &bull;
              <span>{{ cmsStore.stats.events.past }}</span> Past
            </div>
          </div>
          <div class="p-4 bg-accent-gold/5 rounded-2xl text-accent-gold group-hover:bg-accent-gold group-hover:text-white transition-all duration-500">
            <Calendar :size="24" />
          </div>
        </div>

        <!-- Team -->
        <div class="p-6 bg-white border border-neutral-gray/10 rounded-3xl shadow-soft flex items-center justify-between group hover:border-primary/25 hover:shadow-premium transition-all duration-300">
          <div class="space-y-2">
            <span class="text-[10px] font-black uppercase tracking-widest text-neutral-black/35">Team Members</span>
            <div class="text-3xl font-display font-black text-primary">{{ cmsStore.stats.team.total }}</div>
            <div class="text-[10px] font-bold text-neutral-black/55">Active Board Representatives</div>
          </div>
          <div class="p-4 bg-primary-light/5 rounded-2xl text-primary-light group-hover:bg-primary-light group-hover:text-white transition-all duration-500">
            <Users :size="24" />
          </div>
        </div>

        <!-- Resources -->
        <div class="p-6 bg-white border border-neutral-gray/10 rounded-3xl shadow-soft flex items-center justify-between group hover:border-primary/25 hover:shadow-premium transition-all duration-300">
          <div class="space-y-2">
            <span class="text-[10px] font-black uppercase tracking-widest text-neutral-black/35">Resources</span>
            <div class="text-3xl font-display font-black text-primary">{{ cmsStore.stats.resources.total }}</div>
            <div class="text-[10px] font-bold text-neutral-black/55">Guides & Student Accommodations</div>
          </div>
          <div class="p-4 bg-secondary/5 rounded-2xl text-secondary group-hover:bg-secondary group-hover:text-white transition-all duration-500">
            <BookOpen :size="24" />
          </div>
        </div>
      </div>

      <!-- Quick Actions and Recent Logs -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Quick Actions -->
        <div class="space-y-5 lg:col-span-1">
          <h3 class="text-lg font-display font-extrabold text-primary uppercase tracking-tight">Quick Actions</h3>
          <div class="flex flex-col gap-4">
            <router-link 
              v-for="action in quickActions" 
              :key="action.label" 
              :to="action.path"
              class="p-5 bg-white border border-neutral-gray/10 rounded-3xl flex items-center justify-between shadow-soft hover:shadow-premium hover:-translate-y-0.5 transition-all duration-300 group cursor-pointer"
            >
              <div class="flex items-center gap-4">
                <div :class="['p-3 rounded-xl border shrink-0', action.color]">
                  <component :is="action.icon" :size="18" />
                </div>
                <span class="text-xs font-bold uppercase tracking-wider text-neutral-black/75">{{ action.label }}</span>
              </div>
              <ArrowRight :size="16" class="text-neutral-black/30 group-hover:translate-x-1 group-hover:text-primary transition-all" />
            </router-link>
          </div>
        </div>

        <!-- Audit Activity Feed -->
        <div class="space-y-5 lg:col-span-2">
          <h3 class="text-lg font-display font-extrabold text-primary uppercase tracking-tight">Recent Activity Log</h3>
          <div class="bg-white border border-neutral-gray/10 rounded-[2rem] shadow-soft overflow-hidden">
            <div class="p-6 border-b border-neutral-gray/10 bg-neutral-background/50 flex justify-between items-center">
              <span class="text-[10px] font-black uppercase tracking-widest text-neutral-black/40">Audit Trail</span>
              <span class="text-[9px] font-bold text-neutral-black/30">Auto-tracking CMS changes</span>
            </div>
            
            <div v-if="cmsStore.recentActivity.length === 0" class="p-12 text-center text-neutral-black/30 text-xs">
              No recent changes found. Updates will appear here.
            </div>

            <div v-else class="divide-y divide-neutral-gray/10 max-h-[440px] overflow-y-auto">
              <div 
                v-for="log in cmsStore.recentActivity" 
                :key="log.id"
                class="p-5 flex gap-4 hover:bg-neutral-background/30 transition-colors"
              >
                <div class="p-2.5 bg-primary/5 text-primary rounded-xl h-fit border border-primary/10">
                  <Clock :size="14" />
                </div>
                <div class="space-y-1.5 flex-grow">
                  <div class="flex justify-between items-start">
                    <span class="text-[10px] font-bold text-neutral-black/85">{{ log.description }}</span>
                    <span class="text-[9px] text-neutral-black/30 font-semibold">{{ formatTime(log.created_at) }}</span>
                  </div>
                  <div class="text-[10px] text-neutral-black/45 font-medium">
                    By: <b class="text-primary">{{ log.user?.name || 'System / Seed' }}</b> &bull; Action: <span class="font-mono text-[9px] bg-neutral-background px-1.5 py-0.5 rounded border border-neutral-gray/20">{{ log.action }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
