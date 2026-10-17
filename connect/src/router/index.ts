import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { resourcesService } from '@/services/resources.service';
import LandingView from '@/views/LandingView.vue';
import LoginView from '@/views/LoginView.vue';
import AppShellView from '@/views/AppShellView.vue';

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'landing',
    component: LandingView,
    meta: {
      title: 'MSA Connect — SFU Muslim Students Association',
    },
  },
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: {
      title: 'Sign In — MSA Connect',
      guestOnly: true,
    },
  },
  {
    path: '/events',
    redirect: '/app/events',
  },
  {
    path: '/tickets',
    redirect: '/app/tickets',
  },
  {
    path: '/notifications',
    redirect: '/app/notifications',
  },
  {
    path: '/dashboard',
    redirect: '/app/dashboard',
  },
  {
    path: '/support',
    redirect: '/app/support',
  },
  {
    path: '/messages',
    redirect: '/app/messages',
  },
  {
    path: '/resources',
    redirect: '/app/resources',
  },
  {
    path: '/help',
    redirect: '/app/help',
  },
  {
    path: '/app',
    component: AppShellView,
    meta: {
      requiresAuth: true,
    },
    children: [
      {
        path: '',
        redirect: '/app/dashboard',
      },
      {
        path: 'dashboard',
        name: 'app-dashboard',
        component: () => import('@/views/dashboard/DashboardView.vue'),
        meta: {
          title: 'Dashboard — MSA Connect',
          requiresAuth: true,
        },
      },
      {
        path: 'events',
        name: 'app-events',
        component: () => import('@/views/events/EventsView.vue'),
        meta: {
          title: 'Campus Events — MSA Connect',
          requiresAuth: true,
        },
      },
      {
        path: 'events/:slug',
        name: 'app-event-detail',
        component: () => import('@/views/events/EventDetailView.vue'),
        meta: {
          title: 'Event Details — MSA Connect',
          requiresAuth: true,
        },
      },
      {
        path: 'tickets',
        name: 'app-tickets',
        component: () => import('@/views/tickets/MyTicketsView.vue'),
        meta: {
          title: 'My Tickets — MSA Connect',
          requiresAuth: true,
        },
      },
      {
        path: 'notifications',
        name: 'app-notifications',
        component: () => import('@/views/notifications/NotificationsView.vue'),
        meta: {
          title: 'Notifications — MSA Connect',
          requiresAuth: true,
        },
      },
      {
        path: 'notifications/preferences',
        name: 'app-notifications-preferences',
        component: () => import('@/views/notifications/NotificationPreferencesView.vue'),
        meta: {
          title: 'Notification Preferences — MSA Connect',
          requiresAuth: true,
        },
      },
      {
        path: 'support',
        name: 'app-support',
        component: () => import('@/views/support/SupportListView.vue'),
        meta: {
          title: 'Support & Inquiries — MSA Connect',
          requiresAuth: true,
        },
      },
      {
        path: 'support/new',
        name: 'app-support-new',
        component: () => import('@/views/support/NewInquiryView.vue'),
        meta: {
          title: 'New Support Inquiry — MSA Connect',
          requiresAuth: true,
        },
      },
      {
        path: 'support/staff',
        name: 'app-support-staff',
        component: () => import('@/views/support/StaffSupportInboxView.vue'),
        meta: {
          title: 'Staff Support Triage — MSA Connect',
          requiresAuth: true,
          requiresStaff: true,
        },
      },
      {
        path: 'support/:referenceCode',
        name: 'app-support-detail',
        component: () => import('@/views/support/InquiryDetailView.vue'),
        meta: {
          title: 'Inquiry Conversation — MSA Connect',
          requiresAuth: true,
        },
      },
      {
        path: 'messages',
        name: 'app-messages',
        component: () => import('@/views/messages/MessagesInboxView.vue'),
        meta: {
          title: 'Direct Messages — MSA Connect',
          requiresAuth: true,
        },
      },
      {
        path: 'messages/:id',
        name: 'app-conversation',
        component: () => import('@/views/messages/ConversationView.vue'),
        meta: {
          title: 'Conversation — MSA Connect',
          requiresAuth: true,
        },
      },
      {
        path: 'resources',
        name: 'app-resources',
        component: () => import('@/views/resources/ResourcesView.vue'),
        meta: {
          title: 'Community Resources — MSA Connect',
          requiresAuth: true,
          requiresResources: true,
        },
      },
      {
        path: 'profile',
        name: 'app-profile',
        component: () => import('@/views/profile/ProfileView.vue'),
        meta: {
          title: 'Profile — MSA Connect',
          requiresAuth: true,
        },
      },
      {
        path: 'profile/security',
        name: 'app-profile-security',
        component: () => import('@/views/profile/ProfileSecurityView.vue'),
        meta: {
          title: 'Security & Credentials — MSA Connect',
          requiresAuth: true,
        },
      },
      {
        path: 'help',
        name: 'app-help',
        component: () => import('@/views/help/HelpView.vue'),
        meta: {
          title: 'Help & Troubleshooting — MSA Connect',
          requiresAuth: true,
        },
      },
    ],
  },
  {
    path: '/profile',
    redirect: '/app/profile',
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/',
  },
];

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior(_to, _from, savedPosition) {
    if (savedPosition) {
      return savedPosition;
    }
    return { top: 0, behavior: 'smooth' };
  },
});
router.beforeEach(async (to) => {
  const authStore = useAuthStore();

  // Dynamic Document Title
  const baseTitle = 'MSA Connect';
  document.title = to.meta.title ? `${to.meta.title}` : baseTitle;

  // 1. Initial Session Restoration
  if (!authStore.isInitialized) {
    try {
      await authStore.restoreSession();
    } catch (e) {
      console.warn('Initial session restore failed:', e);
    }
  }

  // 2. Authentication-Aware Root / Landing Route Redirect
  if (to.name === 'landing' || to.path === '/') {
    if (authStore.isAuthenticated) {
      return { path: '/app/dashboard' };
    }
  }

  // 3. Authenticated Route Guard (Checks matched hierarchy for requiresAuth)
  const requiresAuth = to.matched.some((record) => record.meta?.requiresAuth);
  if (requiresAuth && !authStore.isAuthenticated) {
    return {
      path: '/login',
      query: { redirect: to.fullPath },
    };
  }

  // 4. Guest-Only Guard (prevent authenticated users from seeing login again)
  const isGuestOnly = to.matched.some((record) => record.meta?.guestOnly);
  if (isGuestOnly && authStore.isAuthenticated) {
    return { path: '/app/dashboard' };
  }

  // 4. Staff-Only Guard
  const requiresStaff = to.matched.some((record) => record.meta?.requiresStaff);
  if (requiresStaff) {
    const roles = authStore.roles;
    const permissions = authStore.permissions;
    const isStaff =
      roles.includes('super-admin') ||
      roles.includes('admin') ||
      roles.includes('director') ||
      roles.includes('dawah-coordinator') ||
      permissions.includes('manage_support');

    if (!isStaff) {
      return { path: '/app/support' };
    }
  }

  // 5. Hidden Resources Feature Gate
  const requiresResources = to.matched.some((record) => record.meta?.requiresResources);
  if (requiresResources) {
    const isEnabled = await resourcesService.isEnabled();
    if (!isEnabled) {
      return { path: '/app/dashboard' };
    }
  }
});

export default router;
