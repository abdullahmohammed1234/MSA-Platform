<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Sidebar } from '@/components/navigation/sidebar';
import { useToastStore, ToastContainer } from '@/components/feedback/toast';
import NotificationBell from '@/components/notifications/NotificationBell.vue';
import CelebrationOverlay from '@/components/gamification/CelebrationOverlay.vue';
import SessionLockscreen from '@/components/auth/SessionLockscreen.vue';
import OnboardingSanctuary from '@/components/auth/OnboardingSanctuary.vue';
import { useAuthStore } from '@/stores/auth';

const toast = useToastStore();
const authStore = useAuthStore();
const isSidebarCollapsed = ref(false);
const userName = ref('Scholar Student');
const showOnboarding = ref(false);

const onboardingStorageKey = computed(() => {
  const user = authStore.user;
  if (!user?.id) return null;
  return `academy_onboarding_done_${user.id}`;
});

const hasCompletedOnboarding = () => {
  const user = authStore.user;
  if (!user) return false;

  if (user.academy_onboarding_completed_at) {
    return true;
  }

  const legacyKeys = [
    onboardingStorageKey.value,
    user.uuid ? `academy_onboarding_done_${user.uuid}` : null,
    user.email ? `academy_onboarding_done_${user.email.toLowerCase()}` : null,
  ].filter((key): key is string => Boolean(key));

  return legacyKeys.some((key) => localStorage.getItem(key) === '1');
};

const syncOnboardingState = () => {
  if (authStore.user?.name) {
    userName.value = authStore.user.name;
  }

  if (authStore.user && !hasCompletedOnboarding()) {
    showOnboarding.value = true;
  } else {
    showOnboarding.value = false;
  }
};

watch(() => authStore.user?.id, syncOnboardingState, { immediate: true });

const completeOnboarding = async () => {
  const key = onboardingStorageKey.value;
  if (key) {
    localStorage.setItem(key, '1');
  }

  showOnboarding.value = false;

  try {
    await authStore.completeAcademyOnboarding();
  } catch {
    // Local storage fallback keeps onboarding dismissed for this browser.
  }
};

const academyItems = computed(() => {
  const children = [
    { label: 'Dashboard', path: '/academy/dashboard', icon: 'dashboard' },
    { label: 'Course Catalog', path: '/academy/courses', icon: 'courses' },
    { label: 'Modules', path: '/academy/modules', icon: 'courses' },
    { label: 'Quizzes', path: '/academy/quizzes', icon: 'scenarios' },
    { label: 'Practice Lab', path: '/academy/scenarios', icon: 'scenarios' },
    { label: 'Discussions', path: '/academy/discussions', icon: 'discussions' },
    { label: 'Storage Vault', path: '/academy/resources', icon: 'resources' },
    { label: 'Islam Buffet', path: '/academy/islam-buffet', icon: 'islam-buffet' },
    { label: 'Dawah Schedule', path: '/academy/dawah-schedule', icon: 'dawah-schedule' },
    { label: 'Notifications', path: '/academy/notifications', icon: 'profile' },
    { label: 'My Profile', path: '/academy/profile', icon: 'profile' },
    { label: 'My Progress', path: '/academy/progress', icon: 'progress' },
    { label: 'Badges', path: '/academy/badges', icon: 'badges' },
    { label: 'Achievements', path: '/academy/achievements', icon: 'badges' },
    { label: 'Settings', path: '/academy/settings', icon: 'profile' }
  ];

  const items = [
    {
      label: 'Academy',
      path: '#',
      children
    }
  ];

  if (authStore.isMentor || authStore.isAdmin) {
    items.push({
      label: 'Mentor Studio',
      path: '#',
      children: [
        { label: 'Mentor Studio', path: '/academy/mentor', icon: 'dashboard' },
        { label: 'Course Supervision', path: '/academy/mentor/courses', icon: 'courses' },
        { label: 'Exam Builder', path: '/academy/mentor/exams', icon: 'scenarios' },
        { label: 'Corrections Queue', path: '/academy/mentor/grading', icon: 'progress' },
        { label: 'Learner Coaching', path: '/academy/mentor/learners', icon: 'profile' }
      ]
    });
  }

  items.push({
    label: 'System',
    path: '#',
    children: [
      { label: 'Main Website', path: '/', icon: 'home' }
    ]
  });

  return items;
});

const handleLogout = async () => {
  await authStore.logout();
  toast.success('Logged out successfully.');
  window.location.href = '/';
};
</script>

<template>
  <div class="min-h-screen flex bg-neutral-background">
    <ToastContainer />
    <CelebrationOverlay />
    <SessionLockscreen />
    <OnboardingSanctuary v-if="showOnboarding" @complete="completeOnboarding" />
    
    <!-- Collapsible Sidebar from design system with custom icons -->
    <Sidebar
      title="Dawah Academy"
      subtitle="SFU MSA"
      logo-src="/logo.PNG"
      logo-alt="Dawah Academy logo"
      :items="academyItems"
      :collapsed="isSidebarCollapsed"
      @collapse="(val) => isSidebarCollapsed = val"
    >
      <!-- Dashboard Icon Slot -->
      <template #dashboard>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
        </svg>
      </template>

      <!-- Courses Icon Slot -->
      <template #courses>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
        </svg>
      </template>

      <!-- Mentor Hub Icon Slot -->
      <template #mentor-hub>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
      </template>

      <!-- Scenarios Icon Slot -->
      <template #scenarios>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
      </template>

      <!-- Discussions Icon Slot -->
      <template #discussions>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z" />
        </svg>
      </template>

      <!-- Resources Icon Slot -->
      <template #resources>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
        </svg>
      </template>

      <!-- Islam Buffet Icon Slot -->
      <template #islam-buffet>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v3m4-3v3M8 2v3m-4 6h16a2 2 0 012 2v3a4 4 0 01-4 4H6a4 4 0 01-4-4v-3a2 2 0 01-2-2z" />
        </svg>
      </template>

      <!-- Dawah Schedule Icon Slot -->
      <template #dawah-schedule>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
      </template>

      <!-- Profile Icon Slot -->
      <template #profile>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
      </template>

      <!-- Progress Icon Slot -->
      <template #progress>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2z" />
        </svg>
      </template>

      <!-- Badges Icon Slot -->
      <template #badges>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v5m-3-3.04h6m-3-9.96A4.98 4.98 0 008 12c0 2.5 2 4.5 4 4.5s4-2 4-4.5a4.98 4.98 0 00-4-4.96zM12 2a5 5 0 015 5c0 2.5-3 5-5 5S7 9.5 7 7a5 5 0 015-5z" />
        </svg>
      </template>

      <!-- Home Icon Slot -->
      <template #home>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
      </template>

      <!-- User avatar slot in sidebar footer -->
      <template #footer-user>
        <div class="h-9 w-9 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary">
          {{ userName.charAt(0).toUpperCase() }}
        </div>
        <div class="flex flex-col min-w-0">
          <span class="text-sm font-semibold text-neutral-black truncate">{{ userName }}</span>
          <span class="text-[10px] text-neutral-muted uppercase tracking-wider font-mono">
            {{ authStore.isAdmin ? 'Admin' : (authStore.isMentor ? 'Mentor' : 'Volunteer') }}
          </span>
        </div>
      </template>
    </Sidebar>
    
    <!-- Content Area -->
    <div class="flex-grow flex flex-col min-w-0">
      <!-- Sticky header bar -->
      <header class="h-16 border-b border-neutral-ivory bg-white/85 backdrop-blur-md flex items-center justify-between px-8 shadow-soft sticky top-0 z-20">
        <span class="font-medium text-sm text-neutral-black">Welcome back, {{ userName }}</span>
        <div class="flex items-center gap-4">
          <NotificationBell />
          <button
            @click="handleLogout"
            class="text-sm font-semibold text-primary hover:text-secondary transition-colors cursor-pointer"
          >
            Logout
          </button>
        </div>
      </header>
      
      <!-- Main Content viewport -->
      <main class="flex-grow p-6 sm:p-8 max-w-7xl w-full mx-auto">
        <router-view />
      </main>
    </div>
  </div>
</template>
