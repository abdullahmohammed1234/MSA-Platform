import type { RouteRecordRaw } from 'vue-router';
import MlibmsLayout from '@/layouts/mlibms/MlibmsLayout.vue';

const mlibmsRoutes: RouteRecordRaw[] = [
  // Public Library Portal Routes (wrapped in PublicLayout for header/footer)
  {
    path: '/library',
    component: () => import('@/layouts/PublicLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'library-catalog',
        component: () => import('@/pages/mlibms/PublicCatalogPage.vue'),
        meta: { title: 'MSA Library — Catalog', requiresAuth: true }
      },
      {
        path: 'book/:uuid',
        name: 'library-book-detail',
        component: () => import('@/pages/mlibms/PublicBookDetailPage.vue'),
        meta: { title: 'Book Details — MSA Library', requiresAuth: true }
      },
      {
        path: 'scan',
        name: 'library-self-service',
        component: () => import('@/pages/mlibms/SelfServiceScanPage.vue'),
        meta: { title: 'Self-Service Circulation Scanner — MSA Library', requiresAuth: true }
      },
      {
        path: 'my-loans',
        name: 'library-member-portal',
        component: () => import('@/pages/mlibms/MemberPortalPage.vue'),
        meta: { title: 'My Active Loans & Holds — MSA Library', requiresAuth: true }
      },
    ]
  },

  // Standalone MLibMS Admin Shell
  {
    path: '/library/admin',
    component: MlibmsLayout,
    children: [
      {
        path: '',
        name: 'mlibms-dashboard',
        component: () => import('@/pages/mlibms/admin/MlibmsDashboardPage.vue'),
        meta: { title: 'Dashboard — MLibMS Admin', mlibmsPermissions: ['library.view'] }
      },
      {
        path: 'unauthorized',
        name: 'mlibms-unauthorized',
        component: () => import('@/pages/mlibms/admin/MlibmsUnauthorizedPage.vue'),
        meta: { title: 'Access Denied — MLibMS Admin', mlibmsPublic: true }
      },
      {
        path: 'intake',
        name: 'mlibms-intake',
        component: () => import('@/pages/mlibms/admin/BookIntakeWorkbenchPage.vue'),
        meta: { title: 'Book Intake & Cataloging Workbench — MLibMS', mlibmsPermissions: ['library.catalog'] }
      },
      {
        path: 'books',
        name: 'mlibms-books',
        component: () => import('@/pages/mlibms/admin/MlibmsBooksPage.vue'),
        meta: { title: 'Catalog Books — MLibMS Admin', mlibmsPermissions: ['library.catalog'] }
      },
      {
        path: 'copies',
        name: 'mlibms-copies',
        component: () => import('@/pages/mlibms/admin/MlibmsCopiesPage.vue'),
        meta: { title: 'Inventory Copies — MLibMS Admin', mlibmsPermissions: ['library.copies'] }
      },
      {
        path: 'members',
        name: 'mlibms-members',
        component: () => import('@/pages/mlibms/admin/MlibmsMembersPage.vue'),
        meta: { title: 'Member Roster — MLibMS Admin', mlibmsPermissions: ['library.members'] }
      },
      {
        path: 'loans',
        name: 'mlibms-loans',
        component: () => import('@/pages/mlibms/admin/MlibmsLoansPage.vue'),
        meta: { title: 'Loans & Overrides — MLibMS Admin', mlibmsPermissions: ['library.loans'] }
      },
      {
        path: 'reservations',
        name: 'mlibms-reservations',
        component: () => import('@/pages/mlibms/admin/MlibmsReservationsPage.vue'),
        meta: { title: 'Hold Queue — MLibMS Admin', mlibmsPermissions: ['library.reservations'] }
      },
      {
        path: 'reports',
        name: 'mlibms-reports',
        component: () => import('@/pages/mlibms/admin/MlibmsReportsPage.vue'),
        meta: { title: 'Circulation Reports — MLibMS Admin', mlibmsPermissions: ['library.reports'] }
      },
      {
        path: 'settings',
        name: 'mlibms-settings',
        component: () => import('@/pages/mlibms/admin/MlibmsSettingsPage.vue'),
        meta: { title: 'Library Settings — MLibMS Admin', mlibmsPermissions: ['library.settings'] }
      }
    ]
  }
];

export default mlibmsRoutes;
