import type { RouteRecordRaw } from 'vue-router'

/**
 * CMS application routes — distinct from MSA Admin (/admin).
 * Uses the same Platform Sanctum session; authorization via existing CMS permissions.
 */
const cmsRoutes: Array<RouteRecordRaw> = [
  {
    path: '/cms',
    component: () => import('@/layouts/CmsGateLayout.vue'),
    children: [
      {
        path: '',
        name: 'cms-dashboard',
        component: () => import('@/pages/admin/cms/Dashboard.vue'),
        meta: { cmsPermissions: 'view_analytics', title: 'CMS Dashboard' }
      },
      {
        path: 'homepage',
        name: 'cms-homepage',
        component: () => import('@/pages/admin/cms/HomepageCms.vue'),
        meta: { cmsPermissions: 'manage_homepage', title: 'Homepage CMS' }
      },
      {
        path: 'announcements',
        name: 'cms-announcements',
        component: () => import('@/pages/admin/cms/AnnouncementsCms.vue'),
        meta: { cmsPermissions: 'manage_announcements', title: 'Announcements' }
      },
      {
        path: 'team',
        name: 'cms-team',
        component: () => import('@/pages/admin/cms/TeamCms.vue'),
        meta: { cmsPermissions: 'manage_team', title: 'Team' }
      },
      {
        path: 'resources',
        name: 'cms-resources',
        component: () => import('@/pages/admin/cms/ResourcesCms.vue'),
        meta: { cmsPermissions: 'manage_resources', title: 'Resources' }
      },
      {
        path: 'media',
        name: 'cms-media',
        component: () => import('@/pages/admin/cms/MediaCms.vue'),
        meta: { cmsPermissions: 'manage_media', title: 'Media Library' }
      },
      {
        path: 'unauthorized',
        name: 'cms-unauthorized',
        component: () => import('@/pages/admin/cms/CmsUnauthorizedPage.vue'),
        meta: { cmsPublic: true, title: 'Access Denied' }
      }
    ]
  }
]

export default cmsRoutes
