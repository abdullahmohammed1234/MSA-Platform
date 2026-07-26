import type { RouteRecordRaw } from 'vue-router';
import { EMS_PERMISSIONS } from '@/constants/ems';

/**
 * MSA Event Management System routes.
 *
 * `meta.emsPermissions` is read by emsGuard, which owns /ems in the guard
 * pipeline. A route lists the permissions that make it *reachable*; holding
 * any one of them is enough, and the actions inside are gated separately.
 */
const emsRoutes: Array<RouteRecordRaw> = [
  {
    path: '/ems',
    component: () => import('@/layouts/ems/EmsLayout.vue'),
    children: [
      {
        path: '',
        name: 'ems-dashboard',
        component: () => import('@/pages/ems/EmsDashboardPage.vue'),
        meta: { title: 'EMS Dashboard', emsPermissions: EMS_PERMISSIONS.EVENTS_VIEW },
      },
      {
        path: 'events',
        name: 'ems-events',
        component: () => import('@/pages/ems/events/EventsListPage.vue'),
        meta: { title: 'Events', emsPermissions: EMS_PERMISSIONS.EVENTS_VIEW },
      },
      {
        path: 'events/create',
        name: 'ems-event-create',
        component: () => import('@/pages/ems/events/EventFormPage.vue'),
        meta: { title: 'Create Event', emsPermissions: EMS_PERMISSIONS.EVENTS_CREATE },
      },
      {
        path: 'events/:uuid',
        name: 'ems-event-detail',
        component: () => import('@/pages/ems/events/EventDetailPage.vue'),
        meta: { title: 'Event Detail', emsPermissions: EMS_PERMISSIONS.EVENTS_VIEW },
      },
      {
        path: 'events/:uuid/operations',
        name: 'ems-event-operations',
        component: () => import('@/pages/ems/events/EventOperationsPage.vue'),
        meta: {
          title: 'Event Operations',
          emsPermissions: [EMS_PERMISSIONS.REGISTRATIONS_VIEW, EMS_PERMISSIONS.CHECK_INS_VIEW],
        },
      },
      {
        path: 'events/:uuid/attendees',
        name: 'ems-event-attendees',
        component: () => import('@/pages/ems/events/AttendeesPage.vue'),
        meta: { title: 'Attendees', emsPermissions: EMS_PERMISSIONS.REGISTRATIONS_VIEW },
      },
      {
        path: 'events/:uuid/import',
        name: 'ems-event-import',
        component: () => import('@/pages/ems/events/AttendeeImportPage.vue'),
        meta: { title: 'Import Attendees', emsPermissions: EMS_PERMISSIONS.IMPORTS_CREATE },
      },
      {
        path: 'events/:uuid/notifications',
        name: 'ems-event-notifications',
        component: () => import('@/pages/ems/events/EventNotificationsPage.vue'),
        meta: {
          title: 'Event Communications',
          emsPermissions: EMS_PERMISSIONS.NOTIFICATIONS_VIEW,
        },
      },
      {
        path: 'events/:uuid/check-in',
        name: 'ems-event-check-in',
        component: () => import('@/pages/ems/events/CheckInScannerPage.vue'),
        meta: { title: 'Check-in', emsPermissions: EMS_PERMISSIONS.CHECK_INS_PERFORM },
      },
      {
        path: 'events/:uuid/staff',
        name: 'ems-event-staff',
        component: () => import('@/pages/ems/events/EventStaffPage.vue'),
        meta: {
          title: 'Event Staff',
          emsPermissions: EMS_PERMISSIONS.CHECK_INS_PERFORM,
          emsStaffMode: true,
        },
      },
      {
        path: 'events/:uuid/edit',
        name: 'ems-event-edit',
        component: () => import('@/pages/ems/events/EventFormPage.vue'),
        meta: { title: 'Edit Event', emsPermissions: EMS_PERMISSIONS.EVENTS_UPDATE },
      },
      {
        path: 'categories',
        name: 'ems-categories',
        component: () => import('@/pages/ems/categories/CategoriesPage.vue'),
        meta: { title: 'Event Categories', emsPermissions: EMS_PERMISSIONS.CATEGORIES_VIEW },
      },
      {
        path: 'analytics',
        name: 'ems-analytics',
        component: () => import('@/pages/ems/AnalyticsDashboardPage.vue'),
        meta: { title: 'Analytics Dashboard', emsPermissions: EMS_PERMISSIONS.ANALYTICS_VIEW },
      },
      {
        path: 'analytics/compare',
        name: 'ems-analytics-compare',
        component: () => import('@/pages/ems/EventComparisonPage.vue'),
        meta: { title: 'Event Comparison', emsPermissions: EMS_PERMISSIONS.ANALYTICS_VIEW },
      },
      {
        path: 'events/:uuid/analytics',
        name: 'ems-event-analytics',
        component: () => import('@/pages/ems/events/EventAnalyticsPage.vue'),
        meta: { title: 'Event Analytics', emsPermissions: EMS_PERMISSIONS.ANALYTICS_VIEW },
      },
      {
        path: 'access',
        name: 'ems-access',
        component: () => import('@/pages/ems/AccessPage.vue'),
        meta: { title: 'Roles & Permissions', emsPermissions: EMS_PERMISSIONS.SYSTEM_VIEW },
      },
      {
        path: 'promo-codes',
        name: 'ems-promo-codes',
        component: () => import('@/pages/ems/PromoCodesPage.vue'),
        meta: { title: 'Promo Codes', emsPermissions: EMS_PERMISSIONS.EVENTS_VIEW },
      },
      {
        path: 'templates',
        name: 'ems-templates',
        component: () => import('@/pages/ems/TemplatesPage.vue'),
        meta: { title: 'Event Templates', emsPermissions: EMS_PERMISSIONS.EVENTS_VIEW },
      },
      {
        path: 'feedback',
        name: 'ems-feedback',
        component: () => import('@/pages/ems/FeedbackPage.vue'),
        meta: { title: 'Feedback & Surveys', emsPermissions: EMS_PERMISSIONS.EVENTS_VIEW },
      },
      {
        // Reachable without an EMS grant, otherwise the guard's redirect loops.
        path: 'unauthorized',
        name: 'ems-unauthorized',
        component: () => import('@/pages/ems/EmsUnauthorizedPage.vue'),
        meta: { title: 'Access Denied', emsPublic: true },
      },
      {
        path: ':pathMatch(.*)*',
        name: 'ems-not-found',
        component: () => import('@/pages/ems/EmsNotFoundPage.vue'),
        meta: { title: 'Page Not Found', emsPublic: true },
      },
    ],
  },
];

export default emsRoutes;
