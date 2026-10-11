<script setup lang="ts">
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { 
  LayoutDashboard, 
  Activity, 
  Shield, 
  UserCheck, 
  LayoutGrid,
  Heart,
  Bell,
  LogOut, 
  ArrowLeft 
} from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const activeTab = computed(() => {
  if (route.name === 'account-activity') return 'activity';
  if (route.name === 'account-profile') return 'profile';
  if (route.name === 'account-security') return 'security';
  if (route.name === 'account-apps') return 'apps';
  if (route.name === 'account-volunteering') return 'volunteer';
  if (route.name === 'account-notifications-preferences' || route.name === 'notifications-preferences') return 'notifications';
  return 'overview';
});

const handleLogout = async () => {
  await authStore.logout();
  router.push({ name: 'home' });
};
</script>

<template>
  <div class="pt-24 sm:pt-32 pb-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full">
    <!-- Account Header Banner (MSA Deep Burgundy Brand Colors) -->
    <div class="mb-8 bg-gradient-to-br from-primary via-primary-dark to-secondary rounded-3xl p-6 sm:p-8 text-white shadow-premium relative overflow-hidden">
      <div class="absolute -right-12 -bottom-12 w-64 h-64 bg-white/10 rounded-full blur-2xl pointer-events-none" />
      
      <div class="relative z-10 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6">
        <div class="flex items-center gap-4 sm:gap-6">
          <div class="h-16 w-16 sm:h-20 sm:w-20 rounded-2xl bg-white/15 border border-white/30 flex items-center justify-center text-white text-2xl sm:text-3xl font-extrabold uppercase shadow-inner overflow-hidden shrink-0">
            <img v-if="authStore.user?.avatar" :src="authStore.user.avatar" :alt="authStore.user.name" class="h-full w-full object-cover" />
            <span v-else>{{ authStore.user?.name?.charAt(0) || 'M' }}</span>
          </div>

          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <h1 class="text-2xl sm:text-3xl font-display font-black !text-white tracking-tight">
                {{ authStore.user?.name || 'MSA Member' }}
              </h1>
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-white/20 text-white border border-white/30">
                {{ authStore.roles.includes('volunteer') ? 'Volunteer' : 'Active Member' }}
              </span>
            </div>
            <p class="text-xs sm:text-sm text-white/80 mt-1 font-medium">
              {{ authStore.user?.email }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <router-link
            to="/"
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-white/15 hover:bg-white/25 text-white transition-all border border-white/20"
          >
            <ArrowLeft class="h-3.5 w-3.5" />
            Main Site
          </router-link>

          <button
            @click="handleLogout"
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-white/20 hover:bg-white/30 text-white transition-all border border-white/25 cursor-pointer"
          >
            <LogOut class="h-3.5 w-3.5" />
            Logout
          </button>
        </div>
      </div>

      <!-- Navigation Tabs (7 Tabs - Clean Responsive Wrap) -->
      <div class="mt-8 pt-6 border-t border-white/15 flex flex-wrap items-center gap-2 sm:gap-2.5">
        <router-link
          to="/account"
          :class="[
            'px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl text-[11px] sm:text-xs font-extrabold uppercase tracking-wider transition-all whitespace-nowrap flex items-center gap-1.5 sm:gap-2 shrink-0',
            activeTab === 'overview'
              ? 'bg-white text-primary shadow-soft'
              : 'text-white/80 hover:bg-white/15 hover:text-white'
          ]"
        >
          <LayoutDashboard class="h-4 w-4 shrink-0" />
          Overview
        </router-link>

        <router-link
          to="/account/activity"
          :class="[
            'px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl text-[11px] sm:text-xs font-extrabold uppercase tracking-wider transition-all whitespace-nowrap flex items-center gap-1.5 sm:gap-2 shrink-0',
            activeTab === 'activity'
              ? 'bg-white text-primary shadow-soft'
              : 'text-white/80 hover:bg-white/15 hover:text-white'
          ]"
        >
          <Activity class="h-4 w-4 shrink-0" />
          Activity & History
        </router-link>

        <router-link
          to="/account/profile"
          :class="[
            'px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl text-[11px] sm:text-xs font-extrabold uppercase tracking-wider transition-all whitespace-nowrap flex items-center gap-1.5 sm:gap-2 shrink-0',
            activeTab === 'profile'
              ? 'bg-white text-primary shadow-soft'
              : 'text-white/80 hover:bg-white/15 hover:text-white'
          ]"
        >
          <UserCheck class="h-4 w-4 shrink-0" />
          Profile Details
        </router-link>

        <router-link
          to="/account/security"
          :class="[
            'px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl text-[11px] sm:text-xs font-extrabold uppercase tracking-wider transition-all whitespace-nowrap flex items-center gap-1.5 sm:gap-2 shrink-0',
            activeTab === 'security'
              ? 'bg-white text-primary shadow-soft'
              : 'text-white/80 hover:bg-white/15 hover:text-white'
          ]"
        >
          <Shield class="h-4 w-4 shrink-0" />
          Password & Security
        </router-link>

        <router-link
          to="/account/apps"
          :class="[
            'px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl text-[11px] sm:text-xs font-extrabold uppercase tracking-wider transition-all whitespace-nowrap flex items-center gap-1.5 sm:gap-2 shrink-0',
            activeTab === 'apps'
              ? 'bg-white text-primary shadow-soft'
              : 'text-white/80 hover:bg-white/15 hover:text-white'
          ]"
        >
          <LayoutGrid class="h-4 w-4 shrink-0" />
          My Applications
        </router-link>

        <router-link
          to="/account/volunteer"
          :class="[
            'px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl text-[11px] sm:text-xs font-extrabold uppercase tracking-wider transition-all whitespace-nowrap flex items-center gap-1.5 sm:gap-2 shrink-0',
            activeTab === 'volunteer'
              ? 'bg-white text-primary shadow-soft'
              : 'text-white/80 hover:bg-white/15 hover:text-white'
          ]"
        >
          <Heart class="h-4 w-4 shrink-0" />
          Volunteering
        </router-link>

        <router-link
          to="/account/notifications"
          :class="[
            'px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl text-[11px] sm:text-xs font-extrabold uppercase tracking-wider transition-all whitespace-nowrap flex items-center gap-1.5 sm:gap-2 shrink-0',
            activeTab === 'notifications'
              ? 'bg-white text-primary shadow-soft'
              : 'text-white/80 hover:bg-white/15 hover:text-white'
          ]"
        >
          <Bell class="h-4 w-4 shrink-0" />
          Notifications
        </router-link>
      </div>
    </div>

    <!-- Main Account View Slot -->
    <router-view />
  </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
