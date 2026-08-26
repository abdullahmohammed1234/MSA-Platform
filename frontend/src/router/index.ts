import { createRouter, createWebHistory } from 'vue-router';
import publicRoutes from './public';
import academyRoutes from './academy';
import adminRoutes from './admin';
import authRoutes from './auth';
import emsRoutes from './ems';
import cmsRoutes from './cms';
import damsRoutes from './dams';
import { useAuthStore } from '@/stores/auth';
import { authGuard } from './guards/authGuard';
import { guestGuard } from './guards/guestGuard';
import { roleGuard } from './guards/roleGuard';
import { permissionGuard } from './guards/permissionGuard';
import { verificationGuard } from './guards/verificationGuard';
import { academyGuard } from './guards/academyGuard';
import { publicAuthGuard } from './guards/publicAuthGuard';
import { emsGuard } from './guards/emsGuard';
import { cmsGuard } from './guards/cmsGuard';
import { damsGuard } from './guards/damsGuard';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    ...publicRoutes,
    ...academyRoutes,
    ...cmsRoutes,
    ...damsRoutes,
    ...adminRoutes,
    ...emsRoutes,
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
  // The EMS owns /ems outright: its guard resolves the module's own access
  // model and redirects to EMS screens rather than the academy fallbacks the
  // generic guards use.
  const emsResult = await emsGuard(to);
  if (emsResult !== true) return emsResult;

  const cmsResult = await cmsGuard(to);
  if (cmsResult !== true) return cmsResult;

  const damsResult = await damsGuard(to);
  if (damsResult !== true) return damsResult;

  const academyResult = academyGuard(to);
  if (academyResult !== true) return academyResult;

  const publicAuthResult = publicAuthGuard(to);
  if (publicAuthResult !== true) return publicAuthResult;

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
