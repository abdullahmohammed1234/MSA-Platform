import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'dashboard',
    component: () => import('../views/DashboardView.vue'),
    meta: { requiresAuth: true, title: 'Dashboard' },
  },
  {
    path: '/dashboard',
    redirect: '/',
  },
  {
    path: '/admin',
    redirect: '/projects',
  },
  {
    path: '/projects',
    name: 'projects',
    component: () => import('../views/ProjectsListView.vue'),
    meta: { requiresAuth: true, title: 'Projects' },
  },
  {
    path: '/projects/:uuid',
    name: 'project-detail',
    component: () => import('../views/ProjectDetailView.vue'),
    meta: { requiresAuth: true, title: 'Project Workspace' },
  },
  {
    path: '/my-tasks',
    name: 'my-tasks',
    component: () => import('../views/MyTasksView.vue'),
    meta: { requiresAuth: true, title: 'My Tasks' },
  },
  {
    path: '/calendar',
    name: 'calendar',
    component: () => import('../views/CalendarView.vue'),
    meta: { requiresAuth: true, title: 'Calendar' },
  },
  {
    path: '/departments',
    name: 'departments',
    component: () => import('../views/DepartmentsView.vue'),
    meta: { requiresAuth: true, title: 'Departments' },
  },
  {
    path: '/resources',
    name: 'resources',
    component: () => import('../views/ResourceOverviewView.vue'),
    meta: { requiresAuth: true, title: 'Resources & Capacity' },
  },
  {
    path: '/notifications',
    name: 'notifications',
    component: () => import('../views/NotificationsView.vue'),
    meta: { requiresAuth: true, title: 'Notifications' },
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('../views/LoginView.vue'),
    meta: { guestOnly: true, title: 'Sign In' },
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/',
  },
];

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior() {
    return { top: 0 };
  },
});

let authInitialized = false;

router.beforeEach(async (to) => {
  const authStore = useAuthStore();

  // DEV visual QA: ?preview=layout seeds a local shell without calling production APIs.
  if (import.meta.env.DEV && to.query.preview === 'layout') {
    const previewUser = {
      id: 0,
      name: 'Layout Preview',
      email: 'preview@sfumsa.ca',
      is_admin: false,
    };
    localStorage.setItem('msa_pm_token', 'layout-preview');
    localStorage.setItem('msa_pm_user', JSON.stringify(previewUser));
    authStore.$patch({
      token: 'layout-preview',
      user: previewUser as any,
      departments: [
        { id: 1, name: 'IT', role: 'Member' } as any,
        { id: 2, name: 'Events', role: 'Lead' } as any,
      ],
    });
  }

  // Initialize auth once on first navigation
  if (!authInitialized) {
    if (!(import.meta.env.DEV && to.query.preview === 'layout')) {
      await authStore.initAuth();
    }
    authInitialized = true;
  }

  // Update page title
  const title = to.meta.title as string | undefined;
  document.title = title
    ? `${title} — SFU MSA Project Management System`
    : 'SFU MSA Project Management System';

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return {
      name: 'login',
      query: { redirect: to.fullPath },
    };
  }

  if (to.meta.guestOnly && authStore.isAuthenticated) {
    return { name: 'dashboard' };
  }
});

export default router;
