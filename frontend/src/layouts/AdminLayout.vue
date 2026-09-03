<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Sidebar } from '@/components/navigation/sidebar';
import { useToastStore, ToastContainer } from '@/components/feedback/toast';
import { useAuthStore } from '@/stores/auth';
import NotificationBell from '@/components/notifications/NotificationBell.vue';

import { useAppAccess } from '@/composables/auth/useAppAccess';

const toast = useToastStore();
const authStore = useAuthStore();
const { hasCmsAccess, hasDamsAccess, hasEmsAccess, hasStoreAccess, hasDonationsAccess } = useAppAccess();
const isSidebarCollapsed = ref(false);
const isMobileOpen = ref(false);
const adminName = ref('Platform Administrator');

onMounted(() => {
  const storedName = localStorage.getItem('user_name');
  if (storedName) {
    adminName.value = storedName;
  }
});

const adminItems = computed(() => {
  const items = [
    {
      label: 'Administration',
      path: '#',
      children: [
        { label: 'Admin Dashboard', path: '/admin', icon: 'dashboard' }
      ]
    }
  ];

  const adminGroup = items[0].children;
  const isSuper = typeof authStore.isPrivilegedAdmin === 'boolean'
    ? authStore.isPrivilegedAdmin
    : authStore.roles.includes('admin') || authStore.roles.includes('super-admin');

  if (authStore.permissions.includes('view_analytics') || isSuper) {
    adminGroup.push({ label: 'Platform Analytics', path: '/admin/analytics', icon: 'trending-up' });
  }

  if (authStore.isAdmin || isSuper) {
    if (authStore.permissions.includes('manage_roles') || isSuper) {
      adminGroup.push({ label: 'Manage Roles', path: '/admin/roles', icon: 'shield' });
    }
    
    if (authStore.permissions.includes('manage_permissions') || isSuper) {
      adminGroup.push({ label: 'Manage Permissions', path: '/admin/permissions', icon: 'key' });
    }
  }

  if (isSuper || authStore.permissions.includes('manage_users')) {
    adminGroup.push({ label: 'Volunteering Registrars', path: '/admin/volunteering-registrars', icon: 'users' });
    adminGroup.push({ label: 'User Management', path: '/admin/academy/user-management', icon: 'users' });
    adminGroup.push({ label: 'Application Access', path: '/admin/application-access', icon: 'key' });
  }

  // Applications: open shells — not embedded inside MSA Admin (Phase 4–5, Phase 10, Phase 17, Phase 18)
  const appChildren: Array<{ label: string; path: string; icon: string }> = [];
  if (hasCmsAccess.value) {
    appChildren.push({ label: 'Open CMS', path: '/cms', icon: 'book' });
  }
  if (hasDamsAccess.value) {
    appChildren.push({ label: 'Open DAMS', path: '/dams', icon: 'layers' });
  }
  if (hasEmsAccess.value) {
    appChildren.push({ label: 'Open EMS', path: '/ems', icon: 'calendar' });
  }
  if (hasStoreAccess.value) {
    appChildren.push({ label: 'Open Store Admin', path: '/store/admin', icon: 'server' });
  }
  if (hasDonationsAccess.value) {
    appChildren.push({ label: 'Open DMS Admin', path: '/donations/admin', icon: 'heart' });
  }
  if (appChildren.length > 0) {
    items.push({
      label: 'Applications',
      path: '#',
      children: appChildren,
    });
  }

  const systemChildren: Array<{ label: string; path: string; icon: string }> = [];

  if (authStore.permissions.includes('system.view') || isSuper) {
    systemChildren.push({ label: 'Systems Overview', path: '/admin/systems', icon: 'dashboard' });
    systemChildren.push({ label: 'Main Website', path: '/admin/systems/main-website', icon: 'home' });
    systemChildren.push({ label: 'Content Management System', path: '/admin/systems/cms', icon: 'file' });
    systemChildren.push({ label: 'Dawah Academy', path: '/admin/systems/dawah-academy', icon: 'book' });
    systemChildren.push({ label: 'Dawah Academy Management (DAMS)', path: '/admin/systems/dams', icon: 'layers' });
    systemChildren.push({ label: 'Event Management System', path: '/admin/systems/ems', icon: 'calendar' });
    systemChildren.push({ label: 'Store Management System', path: '/admin/systems/store', icon: 'server' });
    systemChildren.push({ label: 'Donations Management System', path: '/admin/systems/donations', icon: 'heart' });
  }

  if (authStore.permissions.includes('view_queue_status') || isSuper) {
    systemChildren.push({ label: 'Platform Queues', path: '/admin/system/queues', icon: 'server' });
  }

  if (authStore.permissions.includes('view_security') || isSuper) {
    systemChildren.push({ label: 'Security Center', path: '/admin/security', icon: 'shield' });
  }

  if (systemChildren.length > 0) {
    items.push({
      label: 'System',
      path: '#',
      children: systemChildren
    });
  }

  return items;
});

const handleLogout = async () => {
  try {
    await authStore.logout();
    toast.success('Admin logged out successfully.');
    setTimeout(() => {
      window.location.href = '/';
    }, 1000);
  } catch (error) {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user_role');
    localStorage.removeItem('user_name');
    localStorage.removeItem('user_email');
    window.location.href = '/';
  }
};
</script>

<template>
  <div class="min-h-screen flex bg-neutral-background overflow-x-hidden">
    <ToastContainer />
    
    <!-- Collapsible Sidebar from design system with custom icons -->
    <Sidebar
      title="MSA Admin"
      :items="adminItems"
      :collapsed="isSidebarCollapsed"
      :mobileOpen="isMobileOpen"
      @collapse="(val) => isSidebarCollapsed = val"
      @closeMobile="isMobileOpen = false"
    >
      <!-- Dashboard Icon Slot -->
      <template #dashboard>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
        </svg>
      </template>

      <!-- Trending Up Icon Slot -->
      <template #trending-up>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        </svg>
      </template>

      <!-- Shield Icon Slot -->
      <template #shield>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
      </template>

      <!-- Key Icon Slot -->
      <template #key>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m-5 8a5 5 0 1110 0a5 5 0 01-10 0zM17 14l2-2 2 2m-2-2l-2-2" />
        </svg>
      </template>

      <!-- Home Icon Slot -->
      <template #home>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
      </template>

      <!-- File Icon Slot -->
      <template #file>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
        </svg>
      </template>

      <!-- Calendar Icon Slot -->
      <template #calendar>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
      </template>

      <!-- QR / Check-in Icon Slot -->
      <template #qr>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 2h2v2h-2v-2zm4-2h2v2h-2v-2zm-4 4h2v2h-2v-2zm4 0h2v2h-2v-2z" />
        </svg>
      </template>

      <!-- Users Icon Slot -->
      <template #users>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
      </template>

      <!-- Book Icon Slot -->
      <template #book>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
        </svg>
      </template>

      <!-- Image Icon Slot -->
      <template #image>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
      </template>

      <!-- Server Icon Slot -->
      <template #server>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
        </svg>
      </template>

      <!-- Heart Icon Slot -->
      <template #heart>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 016.364 0L12 7.636l1.318-1.318a4.5 4.5 0 116.364 6.364L12 21.364l-7.682-7.682a4.5 4.5 0 010-6.364z" />
        </svg>
      </template>

      <!-- Layers Icon Slot -->
      <template #layers>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
        </svg>
      </template>

      <!-- Quiz Icon Slot -->
      <template #quiz>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </template>

      <!-- Award Icon Slot -->
      <template #award>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v5m-3-3h6m-3-10a3 3 0 110-6 3 3 0 010 6zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </template>

      <!-- Star Icon Slot -->
      <template #star>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
        </svg>
      </template>
    </Sidebar>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Admin Header Bar -->
      <header class="h-16 bg-white border-b border-neutral-ivory px-4 sm:px-8 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-3">
          <!-- Mobile Sidebar Toggle Button -->
          <button
            @click="isMobileOpen = true"
            class="lg:hidden rounded-lg p-2 hover:bg-neutral-background text-neutral-muted hover:text-primary transition-colors cursor-pointer"
            aria-label="Open navigation menu"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>

          <span class="text-xs uppercase tracking-widest font-bold text-neutral-muted hidden sm:inline-block">
            SFU MSA Unified Admin Console
          </span>
        </div>

        <div class="flex items-center gap-4">
          <!-- Real-time Notifications Bell -->
          <NotificationBell />

          <!-- Logged in Admin Badge & Quick Logout -->
          <div class="flex items-center gap-3 pl-4 border-l border-neutral-ivory/60">
            <div class="flex flex-col text-right hidden sm:flex">
              <span class="text-xs font-bold text-neutral-black truncate max-w-[140px]">
                {{ adminName }}
              </span>
              <span class="text-[10px] text-neutral-muted font-medium capitalize">
                {{ authStore.roles[0] || 'Administrator' }}
              </span>
            </div>
            
            <button
              @click="handleLogout"
              class="px-3 py-1.5 text-xs font-bold text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition-colors cursor-pointer"
              title="Logout from Admin"
            >
              Logout
            </button>
          </div>
        </div>
      </header>

      <!-- Dynamic Route Content Wrapper -->
      <main class="flex-1 p-4 sm:p-8 overflow-y-auto">
        <router-view />
      </main>
    </div>
  </div>
</template>
