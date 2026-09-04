import type { RouteRecordRaw } from 'vue-router'

/**
 * MSA Platform Admin routes.
 * CMS → /cms (Phase 4). DAMS Academy admin → /dams (Phase 5).
 * Legacy /admin/academy/* and desks redirect to DAMS equivalents.
 */
const adminRoutes: Array<RouteRecordRaw> = [
  {
    path: '/admin/login',
    redirect: '/admin'
  },
  {
    path: '/admin',
    component: () => import('@/layouts/AdminGateLayout.vue'),
    children: [
      {
        path: '',
        name: 'admin-dashboard',
        component: () => import('@/pages/admin/AdminRootPage.vue'),
        meta: { adminLogin: true }
      },
      {
        path: 'roles',
        name: 'admin-roles',
        component: () => import('@/pages/admin/Roles.vue'),
        meta: { permissions: 'manage_roles' }
      },
      {
        path: 'permissions',
        name: 'admin-permissions',
        component: () => import('@/pages/admin/Permissions.vue'),
        meta: { permissions: 'manage_permissions' }
      },
      {
        path: 'volunteering-registrars',
        name: 'admin-volunteering-registrars',
        component: () => import('@/pages/admin/volunteers/VolunteeringRegistrarsPage.vue'),
        meta: { title: 'Volunteering Registrars', adminLogin: true }
      },
      {
        path: 'volunteering-registrars/:uuid',
        name: 'admin-volunteering-registrar-detail',
        component: () => import('@/pages/admin/volunteers/VolunteeringRegistrarDetailPage.vue'),
        meta: { title: 'Volunteer Registration Detail', adminLogin: true }
      },
      // CMS Engine — extracted to /cms
      { path: 'cms', redirect: '/cms' },
      { path: 'cms/homepage', redirect: '/cms/homepage' },
      { path: 'cms/announcements', redirect: '/cms/announcements' },
      { path: 'cms/team', redirect: '/cms/team' },
      { path: 'cms/resources', redirect: '/cms/resources' },
      { path: 'cms/media', redirect: '/cms/media' },

      // Systems control plane (Phase 7–8)
      {
        path: 'systems',
        name: 'admin-systems',
        component: () => import('@/pages/admin/system/SystemsPage.vue'),
        meta: { permissions: 'system.view' }
      },
      {
        path: 'systems/services/:serviceId',
        name: 'admin-systems-service',
        component: () => import('@/pages/admin/system/SystemPlatformServiceDetailPage.vue'),
        meta: { permissions: 'system.view' }
      },
      // Unified application detail (registry-driven)
      {
        path: 'systems/cms',
        name: 'admin-systems-cms',
        component: () => import('@/pages/admin/system/SystemApplicationDetailPage.vue'),
        meta: { permissions: 'system.view', systemId: 'cms' }
      },
      {
        path: 'systems/dams',
        name: 'admin-systems-dams',
        component: () => import('@/pages/admin/system/SystemApplicationDetailPage.vue'),
        meta: { permissions: 'system.view', systemId: 'dams' }
      },
      {
        path: 'systems/main-website',
        name: 'admin-systems-main-website',
        component: () => import('@/pages/admin/system/SystemApplicationDetailPage.vue'),
        meta: { permissions: 'system.view', systemId: 'main-website' }
      },
      {
        path: 'systems/dawah-academy',
        name: 'admin-systems-dawah-academy',
        component: () => import('@/pages/admin/system/SystemApplicationDetailPage.vue'),
        meta: { permissions: 'system.view', systemId: 'dawah-academy' }
      },
      {
        path: 'systems/ems',
        name: 'admin-systems-ems',
        component: () => import('@/pages/admin/system/SystemApplicationDetailPage.vue'),
        meta: { permissions: 'system.view', systemId: 'ems' }
      },
      {
        path: 'systems/store',
        name: 'admin-systems-store',
        component: () => import('@/pages/admin/system/SystemApplicationDetailPage.vue'),
        meta: { permissions: 'system.view', systemId: 'store' }
      },
      {
        path: 'systems/donations',
        name: 'admin-systems-donations',
        component: () => import('@/pages/admin/system/SystemApplicationDetailPage.vue'),
        meta: { permissions: 'system.view', systemId: 'donations' }
      },
      {
        path: 'systems/sponsorship',
        name: 'admin-systems-sponsorship',
        component: () => import('@/pages/admin/system/SystemApplicationDetailPage.vue'),
        meta: { permissions: 'system.view', systemId: 'sponsorship' }
      },
      {
        path: 'systems/donations/console',
        name: 'admin-systems-donations-console',
        component: () => import('@/pages/admin/system/DonationsSystemPage.vue'),
        meta: { permissions: 'system.view' }
      },
      { path: 'donations', redirect: '/donations/admin' },
      { path: 'donations/dashboard', redirect: '/donations/admin' },
      { path: 'sponsorship', redirect: '/sponsorship/admin' },
      { path: 'sponsorship/dashboard', redirect: '/sponsorship/admin' },
      { path: 'store', redirect: '/store/admin' },
      { path: 'store/dashboard', redirect: '/store/admin' },
      { path: 'store/products', redirect: '/store/admin/products' },
      { path: 'store/inventory', redirect: '/store/admin/inventory' },
      { path: 'store/orders', redirect: '/store/admin/orders' },
      // Legacy operations consoles (advanced) — not the primary Systems detail
      {
        path: 'systems/ems/console',
        name: 'admin-systems-ems-console',
        component: () => import('@/pages/admin/system/EmsSystemPage.vue'),
        meta: { permissions: 'system.view' }
      },
      {
        path: 'systems/main-website/console',
        name: 'admin-systems-main-website-console',
        component: () => import('@/pages/admin/system/MainWebsiteSystemPage.vue'),
        meta: { permissions: 'system.view' }
      },
      {
        path: 'systems/dawah-academy/console',
        name: 'admin-systems-dawah-academy-console',
        component: () => import('@/pages/admin/system/DawahAcademySystemPage.vue'),
        meta: { permissions: 'system.view' }
      },

      // Platform user management (NOT DAMS)
      {
        path: 'academy/user-management',
        name: 'admin-academy-user-management',
        component: () => import('@/pages/admin/academy/UserManagement.vue'),
        meta: { permissions: 'manage_users' }
      },
      {
        path: 'application-access',
        name: 'admin-application-access',
        component: () => import('@/pages/admin/ApplicationAccess.vue'),
        meta: { permissions: 'manage_users' }
      },
      // CMS announcements — Phase 4 rehome
      { path: 'academy/announcements', redirect: '/cms/announcements' },

      // DAMS redirects (Academy administration extracted)
      { path: 'achievements', redirect: '/dams/achievements' },
      { path: 'badges', redirect: '/dams/badges' },
      { path: 'learning-paths', redirect: '/dams/learning-paths' },
      { path: 'academy/dashboard', redirect: '/dams' },
      { path: 'academy/reports', redirect: '/dams/reports' },
      { path: 'academy/volunteer-analytics', redirect: '/dams/volunteer-analytics' },
      { path: 'academy/live-admin', redirect: '/dams/live-admin' },
      { path: 'academy/quiz-management', redirect: '/dams/quiz-management' },
      { path: 'academy/mentor-management', redirect: '/dams/mentor-management' },
      { path: 'academy/courses', redirect: '/dams/courses' },
      { path: 'academy/courses/create', redirect: '/dams/courses/create' },
      {
        path: 'academy/courses/:id/edit',
        redirect: (to) => `/dams/courses/${to.params.id}/edit`,
      },
      { path: 'academy/modules', redirect: '/dams/modules' },
      { path: 'academy/lessons', redirect: '/dams/lessons' },
      { path: 'academy/quizzes', redirect: '/dams/quizzes' },
      { path: 'academy/question-bank', redirect: '/dams/question-bank' },
      { path: 'academy/quiz-builder', redirect: '/dams/quiz-builder' },
      { path: 'academy/students', redirect: '/dams/students' },
      { path: 'academy/mentors', redirect: '/dams/mentors' },
      { path: 'academy/assignments', redirect: '/dams/assignments' },
      { path: 'academy/progress', redirect: '/dams/progress' },
      { path: 'academy/analytics', redirect: '/dams/analytics' },
      { path: 'academy/audit', redirect: '/dams/audit' },
      { path: 'academy/moderation', redirect: '/dams/moderation' },
      { path: 'academy/settings', redirect: '/dams/settings' },

      {
        path: 'analytics',
        name: 'admin-analytics',
        component: () => import('@/pages/admin/Analytics.vue'),
        meta: { permissions: 'view_analytics' }
      },
      {
        path: 'notifications',
        name: 'admin-notifications',
        component: () => import('@/pages/admin/Notifications.vue'),
        meta: { permissions: 'manage_notifications' }
      },
      {
        path: 'system/queues',
        name: 'admin-system-queues',
        component: () => import('@/pages/admin/system/Queues.vue'),
        meta: { permissions: 'view_queue_status' }
      },
      {
        path: 'system/performance',
        name: 'admin-system-performance',
        component: () => import('@/pages/admin/system/Performance.vue'),
        meta: { permissions: 'view_queue_status' }
      },
      {
        path: 'security',
        name: 'admin-security',
        component: () => import('@/pages/admin/SecurityDashboard.vue'),
        meta: { permissions: 'view_security' }
      },
      {
        path: 'qa-hub',
        name: 'admin-qa-hub',
        component: () => import('@/pages/admin/QaHubPage.vue'),
        meta: { permissions: 'view_analytics' }
      },
      {
        path: 'device-showcase',
        name: 'admin-device-showcase',
        component: () => import('@/pages/admin/DeviceShowcasePage.vue'),
        meta: { permissions: 'view_analytics' }
      }
    ]
  },
  {
    path: '/store/admin',
    component: () => import('@/layouts/store/StoreLayout.vue'),
    meta: { appAccess: 'store' },
    children: [
      {
        path: '',
        name: 'store-admin-dashboard',
        component: () => import('@/pages/store/admin/StoreDashboardPage.vue'),
        meta: { title: 'Store Dashboard | MSA Store Admin' },
      },
      {
        path: 'products',
        name: 'store-admin-products',
        component: () => import('@/pages/store/admin/StoreProductsPage.vue'),
        meta: { title: 'Products | MSA Store Admin' },
      },
      {
        path: 'inventory',
        name: 'store-admin-inventory',
        component: () => import('@/pages/store/admin/StoreInventoryPage.vue'),
        meta: { title: 'Inventory | MSA Store Admin' },
      },
      {
        path: 'orders',
        name: 'store-admin-orders',
        component: () => import('@/pages/store/admin/StoreOrdersPage.vue'),
        meta: { title: 'Orders | MSA Store Admin' },
      },
    ],
  }
]

export default adminRoutes
