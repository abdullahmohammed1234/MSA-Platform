<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Sidebar } from '@/components/navigation/sidebar';
import { useToastStore, ToastContainer } from '@/components/feedback/toast';
import { useAuthStore } from '@/stores/auth';
import { useDamsAccessStore } from '@/stores/dams/damsAccess';
import NotificationBell from '@/components/notifications/NotificationBell.vue';

const toast = useToastStore();
const authStore = useAuthStore();
const damsAccess = useDamsAccessStore();
const isSidebarCollapsed = ref(false);
const adminName = ref('DAMS Operator');

onMounted(async () => {
  const storedName = localStorage.getItem('user_name');
  if (storedName) {
    adminName.value = storedName;
  }
  await damsAccess.resolve();
});

const damsItems = computed(() => {
  const isSuper = typeof authStore.isPrivilegedAdmin === 'boolean'
    ? authStore.isPrivilegedAdmin
    : authStore.roles.includes('admin') || authStore.roles.includes('super-admin');

  const children: Array<{ label: string; path: string; icon: string }> = [];

  if (isSuper || damsAccess.permissions.includes('view_analytics')) {
    children.push({ label: 'Dashboard', path: '/dams', icon: 'dashboard' });
    children.push({ label: 'Analytics', path: '/dams/analytics', icon: 'trending-up' });
    children.push({ label: 'Volunteer Analytics', path: '/dams/volunteer-analytics', icon: 'trending-up' });
    children.push({ label: 'Reports', path: '/dams/reports', icon: 'file-text' });
    children.push({ label: 'Activity Logs', path: '/dams/audit', icon: 'file-text' });
  }
  if (isSuper || damsAccess.permissions.includes('manage_discussions')) {
    children.push({ label: 'Moderation', path: '/dams/moderation', icon: 'message-square' });
  }
  if (isSuper || damsAccess.permissions.includes('manage_learning_paths')) {
    children.push({ label: 'Learning Paths', path: '/dams/learning-paths', icon: 'layers' });
  }
  if (isSuper || damsAccess.permissions.includes('manage_courses')) {
    children.push({ label: 'Courses', path: '/dams/courses', icon: 'book' });
  }
  if (isSuper || damsAccess.permissions.includes('manage_modules')) {
    children.push({ label: 'Modules', path: '/dams/modules', icon: 'layers' });
  }
  if (isSuper || damsAccess.permissions.includes('manage_lessons')) {
    children.push({ label: 'Lessons', path: '/dams/lessons', icon: 'file' });
  }
  if (isSuper || damsAccess.permissions.includes('manage_quizzes')) {
    children.push({ label: 'Quiz Management', path: '/dams/quiz-management', icon: 'quiz' });
    children.push({ label: 'Quizzes', path: '/dams/quizzes', icon: 'quiz' });
    children.push({ label: 'Question Bank', path: '/dams/question-bank', icon: 'server' });
  }
  if (isSuper || damsAccess.permissions.includes('manage_students')) {
    children.push({ label: 'Students', path: '/dams/students', icon: 'users' });
  }
  if (isSuper || damsAccess.permissions.includes('manage_mentors')) {
    children.push({ label: 'Mentor Management', path: '/dams/mentor-management', icon: 'users' });
    children.push({ label: 'Mentors', path: '/dams/mentors', icon: 'users' });
    children.push({ label: 'Assignments', path: '/dams/assignments', icon: 'key' });
  }
  if (isSuper || damsAccess.permissions.includes('view_progress')) {
    children.push({ label: 'Progress', path: '/dams/progress', icon: 'trending-up' });
  }
  if (isSuper || damsAccess.permissions.includes('manage_achievements')) {
    children.push({ label: 'Achievements', path: '/dams/achievements', icon: 'star' });
  }
  if (isSuper || damsAccess.permissions.includes('manage_badges')) {
    children.push({ label: 'Badges', path: '/dams/badges', icon: 'award' });
  }
  if (isSuper || damsAccess.permissions.includes('manage_settings')) {
    children.push({ label: 'Settings', path: '/dams/settings', icon: 'settings' });
  }
  if (isSuper || damsAccess.permissions.includes('manage_notifications')) {
    children.push({ label: 'Live Admin', path: '/dams/live-admin', icon: 'bell' });
  }

  const platformChildren = [];
  const hasPlatformAdmin = isSuper ||
    authStore.permissions.includes('manage_users') ||
    authStore.permissions.includes('manage_roles') ||
    authStore.permissions.includes('manage_permissions') ||
    authStore.permissions.includes('system.view');

  if (hasPlatformAdmin) {
    platformChildren.push({ label: 'MSA Admin', path: '/admin', icon: 'dashboard' });
  }

  const hasCmsAccess = isSuper ||
    authStore.permissions.includes('manage_homepage') ||
    authStore.permissions.includes('manage_announcements') ||
    authStore.permissions.includes('manage_team') ||
    authStore.permissions.includes('manage_resources') ||
    authStore.permissions.includes('manage_media') ||
    authStore.permissions.includes('view_analytics');

  if (hasCmsAccess) {
    platformChildren.push({ label: 'Open CMS', path: '/cms', icon: 'book' });
  }

  platformChildren.push({ label: 'Main Website', path: '/', icon: 'home' });

  return [
    {
      label: 'Academy Management',
      path: '#',
      children,
    },
    {
      label: 'Platform',
      path: '#',
      children: platformChildren,
    },
  ];
});

const handleLogout = async () => {
  try {
    await authStore.logout();
    toast.success('Logged out successfully.');
    setTimeout(() => {
      window.location.href = '/';
    }, 1000);
  } catch {
    localStorage.removeItem('auth_token');
    window.location.href = '/';
  }
};
</script>

<template>
  <div class="min-h-screen flex bg-neutral-background">
    <ToastContainer />

    <Sidebar
      title="DAMS"
      :items="damsItems"
      :collapsed="isSidebarCollapsed"
      @collapse="(val) => (isSidebarCollapsed = val)"
    >
      <template #dashboard>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
        </svg>
      </template>
      <template #trending-up>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        </svg>
      </template>
      <template #book>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
        </svg>
      </template>
      <template #layers>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
        </svg>
      </template>
      <template #file>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
      </template>
      <template #file-text>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
      </template>
      <template #quiz>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </template>
      <template #users>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
      </template>
      <template #key>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
        </svg>
      </template>
      <template #star>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
        </svg>
      </template>
      <template #award>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
        </svg>
      </template>
      <template #settings>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
      </template>
      <template #bell>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
      </template>
      <template #message-square>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
      </template>
      <template #server>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
        </svg>
      </template>
      <template #home>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z" />
        </svg>
      </template>
    </Sidebar>

    <div class="flex-1 flex flex-col min-w-0">
      <header class="h-16 border-b border-neutral-ivory bg-white flex items-center justify-between px-6 shrink-0">
        <div>
          <p class="text-[10px] font-black uppercase tracking-widest text-primary/60">Dawah Academy Management System</p>
          <p class="text-sm font-semibold text-neutral-black">{{ adminName }}</p>
        </div>
        <div class="flex items-center gap-3">
          <NotificationBell />
          <button
            type="button"
            class="text-xs font-bold uppercase tracking-wider text-secondary hover:underline"
            @click="handleLogout"
          >
            Log out
          </button>
        </div>
      </header>
      <main class="flex-1 overflow-auto p-6">
        <router-view />
      </main>
    </div>
  </div>
</template>
