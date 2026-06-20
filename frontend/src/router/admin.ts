import type { RouteRecordRaw } from 'vue-router'

const adminRoutes: Array<RouteRecordRaw> = [
  {
    path: '/admin',
    component: () => import('@/layouts/AdminLayout.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
    children: [
      {
        path: '',
        name: 'admin-dashboard',
        component: () => import('@/pages/admin/Dashboard.vue')
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
      // CMS Engine Routes
      {
        path: 'cms',
        name: 'admin-cms-dashboard',
        component: () => import('@/pages/admin/cms/Dashboard.vue'),
        meta: { permissions: 'view_analytics' }
      },
      {
        path: 'cms/homepage',
        name: 'admin-cms-homepage',
        component: () => import('@/pages/admin/cms/HomepageCms.vue'),
        meta: { permissions: 'manage_homepage' }
      },
      {
        path: 'cms/announcements',
        name: 'admin-cms-announcements',
        component: () => import('@/pages/admin/cms/AnnouncementsCms.vue'),
        meta: { permissions: 'manage_announcements' }
      },
      {
        path: 'cms/events',
        name: 'admin-cms-events',
        component: () => import('@/pages/admin/cms/EventsCms.vue'),
        meta: { permissions: 'manage_events' }
      },
      {
        path: 'cms/team',
        name: 'admin-cms-team',
        component: () => import('@/pages/admin/cms/TeamCms.vue'),
        meta: { permissions: 'manage_team' }
      },
      {
        path: 'cms/resources',
        name: 'admin-cms-resources',
        component: () => import('@/pages/admin/cms/ResourcesCms.vue'),
        meta: { permissions: 'manage_resources' }
      },
      {
        path: 'cms/media',
        name: 'admin-cms-media',
        component: () => import('@/pages/admin/cms/MediaCms.vue'),
        meta: { permissions: 'manage_media' }
      },
      // Academy Admin Routes
      {
        path: 'achievements',
        name: 'admin-achievements',
        component: () => import('@/pages/admin/academy/AchievementsAdmin.vue'),
        meta: { permissions: 'manage_achievements' }
      },
      {
        path: 'badges',
        name: 'admin-badges',
        component: () => import('@/pages/admin/academy/BadgesAdmin.vue'),
        meta: { permissions: 'manage_badges' }
      },
      {
        path: 'learning-paths',
        name: 'admin-learning-paths',
        component: () => import('@/pages/admin/academy/LearningPathsAdmin.vue'),
        meta: { permissions: 'manage_learning_paths' }
      },
      {
        path: 'academy/dashboard',
        name: 'admin-academy-dashboard',
        component: () => import('@/pages/admin/academy/Dashboard.vue'),
        meta: { permissions: 'view_analytics' }
      },
      {
        path: 'academy/user-management',
        name: 'admin-academy-user-management',
        component: () => import('@/pages/admin/academy/UserManagement.vue'),
        meta: { permissions: 'manage_users' }
      },
      {
        path: 'academy/announcements',
        name: 'admin-academy-announcements',
        component: () => import('@/pages/admin/academy/AnnouncementManagement.vue'),
        meta: { permissions: 'manage_announcements' }
      },
      {
        path: 'academy/reports',
        name: 'admin-academy-reports',
        component: () => import('@/pages/admin/academy/AdminReports.vue'),
        meta: { permissions: 'view_analytics' }
      },
      {
        path: 'academy/volunteer-analytics',
        name: 'admin-academy-volunteer-analytics',
        component: () => import('@/pages/admin/academy/VolunteerAnalytics.vue'),
        meta: { permissions: 'view_analytics' }
      },
      {
        path: 'academy/live-admin',
        name: 'admin-academy-live-admin',
        component: () => import('@/pages/admin/academy/LiveAdminSection.vue'),
        meta: { permissions: 'manage_notifications' }
      },
      {
        path: 'academy/quiz-management',
        name: 'admin-academy-quiz-management',
        component: () => import('@/pages/admin/academy/QuizManagement.vue'),
        meta: { permissions: 'manage_quizzes' }
      },
      {
        path: 'academy/mentor-management',
        name: 'admin-academy-mentor-management',
        component: () => import('@/pages/admin/academy/MentorManagement.vue'),
        meta: { permissions: 'manage_mentors' }
      },
      {
        path: 'academy/courses',
        name: 'admin-academy-courses',
        component: () => import('@/pages/admin/academy/Courses.vue'),
        meta: { permissions: 'manage_courses' }
      },
      {
        path: 'academy/courses/create',
        name: 'admin-academy-courses-create',
        component: () => import('@/pages/admin/academy/CourseCreate.vue'),
        meta: { permissions: 'manage_courses' }
      },
      {
        path: 'academy/courses/:id/edit',
        name: 'admin-academy-courses-edit',
        component: () => import('@/pages/admin/academy/CourseEdit.vue'),
        meta: { permissions: 'manage_courses' }
      },
      {
        path: 'academy/modules',
        name: 'admin-academy-modules',
        component: () => import('@/pages/admin/academy/Modules.vue'),
        meta: { permissions: 'manage_modules' }
      },
      {
        path: 'academy/lessons',
        name: 'admin-academy-lessons',
        component: () => import('@/pages/admin/academy/Lessons.vue'),
        meta: { permissions: 'manage_lessons' }
      },
      {
        path: 'academy/quizzes',
        name: 'admin-academy-quizzes',
        component: () => import('@/pages/admin/academy/Quizzes.vue'),
        meta: { permissions: 'manage_quizzes' }
      },
      {
        path: 'academy/question-bank',
        name: 'admin-academy-question-bank',
        component: () => import('@/pages/admin/academy/QuestionBank.vue'),
        meta: { permissions: 'manage_quizzes' }
      },
      {
        path: 'academy/quiz-builder',
        name: 'admin-academy-quiz-builder',
        component: () => import('@/pages/admin/academy/QuizBuilder.vue'),
        meta: { permissions: 'manage_quizzes' }
      },
      {
        path: 'academy/students',
        name: 'admin-academy-students',
        component: () => import('@/pages/admin/academy/Students.vue'),
        meta: { permissions: 'manage_students' }
      },
      {
        path: 'academy/mentors',
        name: 'admin-academy-mentors',
        component: () => import('@/pages/admin/academy/Mentors.vue'),
        meta: { permissions: 'manage_mentors' }
      },
      {
        path: 'academy/assignments',
        name: 'admin-academy-assignments',
        component: () => import('@/pages/admin/academy/Assignments.vue'),
        meta: { permissions: 'manage_mentors' }
      },
      {
        path: 'academy/progress',
        name: 'admin-academy-progress',
        component: () => import('@/pages/admin/academy/Progress.vue'),
        meta: { permissions: 'view_progress' }
      },
      {
        path: 'academy/analytics',
        name: 'admin-academy-analytics',
        component: () => import('@/pages/admin/academy/Analytics.vue'),
        meta: { permissions: 'view_analytics' }
      },
      {
        path: 'academy/audit',
        name: 'admin-academy-audit',
        component: () => import('@/pages/admin/academy/ActivityLogsAudit.vue'),
        meta: { permissions: 'view_analytics' }
      },
      {
        path: 'academy/moderation',
        name: 'admin-academy-moderation',
        component: () => import('@/pages/admin/academy/DiscussionModeration.vue'),
        meta: { permissions: 'view_analytics' }
      },
      {
        path: 'academy/settings',
        name: 'admin-academy-settings',
        component: () => import('@/pages/admin/academy/AdminSettings.vue'),
        meta: { permissions: 'manage_settings' }
      },
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
  }
]

export default adminRoutes
