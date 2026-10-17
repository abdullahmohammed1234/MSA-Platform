import type { RouteRecordRaw } from 'vue-router';

const connectAdminRoutes: Array<RouteRecordRaw> = [
  {
    path: '/connect/admin',
    component: () => import('@/layouts/connect/ConnectAdminLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'connect-admin-dashboard',
        component: () => import('@/pages/admin/connect/ConnectDashboardPage.vue'),
        meta: { title: 'MSA Connect Management | SFU MSA', requiresAuth: true, connectPermissions: ['connect.view', 'system.view'] },
      },
      {
        path: 'config',
        name: 'connect-admin-config',
        component: () => import('@/pages/admin/connect/ConnectConfigPage.vue'),
        meta: { title: 'Configuration | MSA Connect Management', requiresAuth: true, connectPermissions: ['connect.view', 'connect.manage', 'system.view'] },
      },
      {
        path: 'events',
        name: 'connect-admin-events',
        component: () => import('@/pages/admin/connect/ConnectEventsPage.vue'),
        meta: { title: 'EMS Event Integration | MSA Connect Management', requiresAuth: true, connectPermissions: ['connect.view', 'system.view'] },
      },
      {
        path: 'communications',
        name: 'connect-admin-communications',
        component: () => import('@/pages/admin/connect/ConnectCommunicationsPage.vue'),
        meta: { title: 'Communications Integration | MSA Connect Management', requiresAuth: true, connectPermissions: ['connect.view', 'system.view'] },
      },
      {
        path: 'audit',
        name: 'connect-admin-audit',
        component: () => import('@/pages/admin/connect/ConnectAuditPage.vue'),
        meta: { title: 'System Audit | MSA Connect Management', requiresAuth: true, connectPermissions: ['connect.view', 'system.view'] },
      },
      {
        path: 'unauthorized',
        name: 'connect-admin-unauthorized',
        component: () => import('@/pages/admin/connect/ConnectUnauthorizedPage.vue'),
        meta: { title: 'Access Denied | MSA Connect Management', connectPublic: true },
      },
    ],
  },
];

export default connectAdminRoutes;
