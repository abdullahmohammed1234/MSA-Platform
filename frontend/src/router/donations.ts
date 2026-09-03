import type { RouteRecordRaw } from 'vue-router';

const donationsRoutes: Array<RouteRecordRaw> = [
  {
    path: '/donations/admin',
    component: () => import('@/layouts/dms/DmsLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'dms-dashboard',
        component: () => import('@/pages/dms/DmsDashboardPage.vue'),
        meta: { title: 'DMS Dashboard | SFU MSA', requiresAuth: true },
      },
      {
        path: 'donations',
        name: 'dms-donations',
        component: () => import('@/pages/dms/DmsDonationsPage.vue'),
        meta: { title: 'Donations | SFU MSA DMS', requiresAuth: true },
      },
      {
        path: 'donors',
        name: 'dms-donors',
        component: () => import('@/pages/dms/DmsDonorsPage.vue'),
        meta: { title: 'Donors Roster | SFU MSA DMS', requiresAuth: true },
      },
      {
        path: 'refunds',
        name: 'dms-refunds',
        component: () => import('@/pages/dms/DmsRefundsPage.vue'),
        meta: { title: 'Refunds Console | SFU MSA DMS', requiresAuth: true },
      },
      {
        path: 'reports',
        name: 'dms-reports',
        component: () => import('@/pages/dms/DmsReportsPage.vue'),
        meta: { title: 'Financial Reports | SFU MSA DMS', requiresAuth: true },
      },
    ],
  },
];

export default donationsRoutes;
