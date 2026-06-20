import type { RouteRecordRaw } from 'vue-router';

const authRoutes: Array<RouteRecordRaw> = [
  {
    path: '/',
    component: () => import('@/layouts/AuthLayout.vue'),
    children: [
      {
        path: 'login',
        name: 'login',
        component: () => import('@/pages/auth/LoginPage.vue'),
        meta: { guestOnly: true }
      },
      {
        path: 'register',
        name: 'register',
        component: () => import('@/pages/auth/RegisterPage.vue'),
        meta: { guestOnly: true }
      },
      {
        path: 'forgot-password',
        name: 'forgot-password',
        component: () => import('@/pages/auth/ForgotPasswordPage.vue'),
        meta: { guestOnly: true }
      },
      {
        path: 'reset-password',
        name: 'reset-password',
        component: () => import('@/pages/auth/ResetPasswordPage.vue'),
        meta: { guestOnly: true }
      },
      {
        path: 'verify-email',
        name: 'verify-email',
        component: () => import('@/pages/auth/VerifyEmailPage.vue'),
        meta: { requiresAuth: true }
      }
    ]
  }
];

export default authRoutes;
