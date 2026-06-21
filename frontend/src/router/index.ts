import { createRouter, createWebHistory } from 'vue-router';
import publicRoutes from './public';
import academyRoutes from './academy';
import adminRoutes from './admin';
import authRoutes from './auth';
import { useAuthStore } from '@/stores/auth';
import { authGuard } from './guards/authGuard';
import { guestGuard } from './guards/guestGuard';
import { roleGuard } from './guards/roleGuard';
import { permissionGuard } from './guards/permissionGuard';
import { verificationGuard } from './guards/verificationGuard';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    ...publicRoutes,
    ...academyRoutes,
    ...adminRoutes,
    ...authRoutes,
    {
      path: '/:pathMatch(.*)*',
      redirect: '/'
    }
  ],
  scrollBehavior(_to, _from, savedPosition) {
    if (savedPosition) {
      return savedPosition;
    }
    return { top: 0, behavior: 'smooth' };
  }
});

let isSessionRestored = false;

router.beforeEach(async (to) => {
  const authStore = useAuthStore();

  // Dynamic SEO Metadata updates
  const baseTitle = 'SFU MSA';
  const pageTitle = to.meta.title ? `${to.meta.title}` : baseTitle;
  document.title = pageTitle as string;

  const pageDesc = to.meta.desc || 'SFU Muslim Students Association official portal.';
  let metaDesc = document.querySelector('meta[name="description"]');
  if (metaDesc) {
    metaDesc.setAttribute('content', pageDesc as string);
  } else {
    metaDesc = document.createElement('meta');
    metaDesc.setAttribute('name', 'description');
    metaDesc.setAttribute('content', pageDesc as string);
    document.head.appendChild(metaDesc);
  }

  // 1. Restore session on initial load if token exists
  if (!isSessionRestored) {
    if (authStore.token) {
      try {
        await authStore.restoreSession();
      } catch (error) {
        console.warn('Initial session restore failed:', error);
      }
    }
    isSessionRestored = true;
  }

  // 2. Sequential guard pipeline
  const guestResult = guestGuard(to);
  if (guestResult !== true) return guestResult;

  const authResult = authGuard(to);
  if (authResult !== true) return authResult;

  const verificationResult = verificationGuard(to);
  if (verificationResult !== true) return verificationResult;

  const roleResult = roleGuard(to);
  if (roleResult !== true) return roleResult;

  return permissionGuard(to);
});

export default router;
