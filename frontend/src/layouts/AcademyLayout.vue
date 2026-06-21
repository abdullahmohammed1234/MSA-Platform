<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { Sidebar } from '@/components/navigation/sidebar';
import { Navbar } from '@/components/navigation/navbar';
import { useToastStore, ToastContainer } from '@/components/feedback/toast';
import { useAuthStore } from '@/stores/auth';
import NotificationBell from '@/components/notifications/NotificationBell.vue';

const router = useRouter();
const toast = useToastStore();
const authStore = useAuthStore();
const isSidebarCollapsed = ref(false);

const navItems = [
  { label: 'Main Site', path: '/' },
  { label: 'Academy', path: '/academy/dashboard' },
];

const sidebarItems = computed(() => {
  const learning = [
    { label: 'Dashboard', path: '/academy/dashboard', icon: 'dashboard' },
    { label: 'Courses', path: '/academy/courses', icon: 'book' },
    { label: 'Modules', path: '/academy/modules', icon: 'layers' },
    { label: 'Quizzes', path: '/academy/quizzes', icon: 'quiz' },
    { label: 'Progress', path: '/academy/progress', icon: 'trending-up' },
    { label: 'Achievements', path: '/academy/achievements', icon: 'star' },
    { label: 'Badges', path: '/academy/badges', icon: 'award' },
  ];

  const outreach = [
    { label: 'Practice Lab', path: '/academy/scenarios', icon: 'message-square' },
    { label: 'Discussions', path: '/academy/discussions', icon: 'message-square' },
    { label: 'Resources', path: '/academy/resources', icon: 'file' },
    { label: 'Islam Buffet', path: '/academy/islam-buffet', icon: 'book' },
    { label: 'Dawah Schedule', path: '/academy/dawah-schedule', icon: 'calendar' },
  ];

  const account = [
    { label: 'Profile', path: '/academy/profile', icon: 'users' },
    { label: 'Settings', path: '/academy/settings', icon: 'settings' },
    { label: 'Notifications', path: '/academy/notifications', icon: 'bell' },
  ];

  const items = [
    { label: 'Learning', path: '#', children: learning },
    { label: 'Outreach', path: '#', children: outreach },
    { label: 'Account', path: '#', children: account },
  ];

  if (authStore.isMentor) {
    items.splice(2, 0, {
      label: 'Mentor Studio',
      path: '#',
      children: [
        { label: 'Mentor Dashboard', path: '/academy/mentor', icon: 'dashboard' },
        { label: 'Courses', path: '/academy/mentor/courses', icon: 'book' },
        { label: 'Exams', path: '/academy/mentor/exams', icon: 'quiz' },
        { label: 'Grading', path: '/academy/mentor/grading', icon: 'file-text' },
        { label: 'Learners', path: '/academy/mentor/learners', icon: 'users' },
      ],
    });
  }

  return items;
});

const navbarUser = computed(() => {
  if (!authStore.user) {
    return null;
  }

  return {
    name: authStore.user.name,
    email: authStore.user.email,
    roles: authStore.user.roles,
  };
});

const handleLogout = async () => {
  try {
    await authStore.logout();
    toast.success('Signed out successfully.');
    router.push('/');
  } catch {
    router.push('/');
  }
};
</script>

<template>
  <div class="min-h-screen flex bg-neutral-background">
    <ToastContainer />

    <Sidebar
      title="Dawah Academy"
      :items="sidebarItems"
      :collapsed="isSidebarCollapsed"
      @collapse="(value) => (isSidebarCollapsed = value)"
    />

    <div class="flex-grow flex flex-col min-w-0">
      <header class="sticky top-0 z-20 border-b border-neutral-ivory bg-white/85 backdrop-blur-md">
        <div class="flex items-center justify-between gap-4 px-4 sm:px-6 lg:px-8 h-16">
          <Navbar
            brand-name="Dawah Academy"
            :nav-items="navItems"
            :is-authenticated="authStore.isAuthenticated"
            :user="navbarUser"
            @logout="handleLogout"
          />
          <NotificationBell />
        </div>
      </header>

      <main class="flex-grow p-6 sm:p-8 max-w-7xl w-full mx-auto">
        <router-view />
      </main>
    </div>
  </div>
</template>
