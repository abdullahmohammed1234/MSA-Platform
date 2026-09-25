import type { RouteRecordRaw } from 'vue-router';

/**
 * MSA Volunteer Management System (VMS) routes.
 * Elevates VMS into a standalone application workspace at /vms.
 */
const vmsRoutes: Array<RouteRecordRaw> = [
  {
    path: '/vms',
    component: () => import('@/layouts/VmsGateLayout.vue'),
    children: [
      {
        path: '',
        name: 'vms-dashboard',
        redirect: '/vms/opportunities'
      },
      {
        path: 'opportunities',
        name: 'vms-opportunities',
        component: () => import('@/pages/admin/volunteering/AdminVolunteerOpportunitiesPage.vue'),
        meta: { title: 'Volunteer Opportunities | VMS', vmsPermissions: ['view_opportunities', 'manage_opportunities'] }
      },
      {
        path: 'opportunities/:id',
        name: 'vms-opportunity-detail',
        component: () => import('@/pages/admin/volunteering/AdminVolunteerDetailPage.vue'),
        meta: { title: 'Manage Position | VMS', vmsPermissions: ['view_opportunities', 'manage_opportunities'] }
      },
      {
        path: 'registrars',
        name: 'vms-registrars',
        component: () => import('@/pages/admin/volunteers/VolunteeringRegistrarsPage.vue'),
        meta: { title: 'Volunteer Registrars | VMS', vmsPermissions: ['view_opportunities', 'manage_opportunities'] }
      },
      {
        path: 'registrars/:uuid',
        name: 'vms-registrar-detail',
        component: () => import('@/pages/admin/volunteers/VolunteeringRegistrarDetailPage.vue'),
        meta: { title: 'Volunteer Registrar Detail | VMS', vmsPermissions: ['view_opportunities', 'manage_opportunities'] }
      },
      {
        path: 'unauthorized',
        name: 'vms-unauthorized',
        component: () => import('@/pages/vms/VmsUnauthorizedPage.vue'),
        meta: { title: 'Access Denied | VMS', vmsPublic: true }
      }
    ]
  }
];

export default vmsRoutes;
