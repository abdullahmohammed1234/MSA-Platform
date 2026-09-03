import type { RouteRecordRaw } from 'vue-router';

const sponsorshipRoutes: Array<RouteRecordRaw> = [
  {
    path: '/sponsorship/admin',
    alias: '/spms',
    component: () => import('@/layouts/spms/SpmsLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'spms-dashboard',
        component: () => import('@/pages/spms/SpmsDashboardPage.vue'),
        meta: { title: 'SPMS Dashboard | SFU MSA', requiresAuth: true, spmsPermissions: ['sponsorship.view'] },
      },
      {
        path: 'organizations',
        name: 'spms-organizations',
        component: () => import('@/pages/spms/SpmsOrganizationsPage.vue'),
        meta: { title: 'Sponsors CRM | SFU MSA SPMS', requiresAuth: true, spmsPermissions: ['sponsorship.view'] },
      },
      {
        path: 'organizations/:uuid',
        name: 'spms-organization-detail',
        component: () => import('@/pages/spms/SpmsOrganizationDetailPage.vue'),
        meta: { title: 'Sponsor Profile | SFU MSA SPMS', requiresAuth: true, spmsPermissions: ['sponsorship.view'] },
      },
      {
        path: 'opportunities',
        name: 'spms-opportunities',
        component: () => import('@/pages/spms/SpmsOpportunitiesPage.vue'),
        meta: { title: 'Sponsorship Opportunities | SFU MSA SPMS', requiresAuth: true, spmsPermissions: ['sponsorship.view'] },
      },
      {
        path: 'sponsorships',
        name: 'spms-sponsorships',
        component: () => import('@/pages/spms/SpmsSponsorshipsPage.vue'),
        meta: { title: 'Sponsorship Deals | SFU MSA SPMS', requiresAuth: true, spmsPermissions: ['sponsorship.view'] },
      },
      {
        path: 'sponsorships/:uuid',
        name: 'spms-sponsorship-detail',
        component: () => import('@/pages/spms/SpmsSponsorshipDetailPage.vue'),
        meta: { title: 'Sponsorship Deal | SFU MSA SPMS', requiresAuth: true, spmsPermissions: ['sponsorship.view'] },
      },
      {
        path: 'payments',
        name: 'spms-payments',
        component: () => import('@/pages/spms/SpmsPaymentsPage.vue'),
        meta: { title: 'Payments Console | SFU MSA SPMS', requiresAuth: true, spmsPermissions: ['sponsorship.payments'] },
      },
      {
        path: 'fulfillment',
        name: 'spms-fulfillment',
        component: () => import('@/pages/spms/SpmsFulfillmentPage.vue'),
        meta: { title: 'Fulfillment Console | SFU MSA SPMS', requiresAuth: true, spmsPermissions: ['sponsorship.fulfillment'] },
      },
      {
        path: 'reports',
        name: 'spms-reports',
        component: () => import('@/pages/spms/SpmsReportsPage.vue'),
        meta: { title: 'Reports & Analytics | SFU MSA SPMS', requiresAuth: true, spmsPermissions: ['sponsorship.reports'] },
      },
      {
        path: 'unauthorized',
        name: 'spms-unauthorized',
        component: () => import('@/pages/spms/SpmsUnauthorizedPage.vue'),
        meta: { title: 'Access Denied | SFU MSA SPMS', spmsPublic: true },
      },
    ],
  },
];

export default sponsorshipRoutes;
