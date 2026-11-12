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

  // Initialize auth once on first navigation
  if (!authInitialized) {
    await authStore.initAuth();
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
