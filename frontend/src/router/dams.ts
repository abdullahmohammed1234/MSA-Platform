import type { RouteRecordRaw } from 'vue-router'

/**
 * DAMS — Dawah Academy Management System.
 * Distinct from MSA Admin (/admin) and learner Academy (/academy).
 * Uses Platform Sanctum + existing Academy management permissions.
 * Pages remain under pages/admin/academy/* (ownership via /dams routes).
 */
const damsRoutes: Array<RouteRecordRaw> = [
  {
    path: '/dams',
    component: () => import('@/layouts/DamsGateLayout.vue'),
    children: [
      {
        path: '',
        name: 'dams-dashboard',
        component: () => import('@/pages/admin/academy/Dashboard.vue'),
        meta: {
          title: 'DAMS Dashboard',
          permissions: [
            'view_analytics',
            'manage_courses',
            'manage_modules',
            'manage_lessons',
            'manage_quizzes',
            'manage_learning_paths',
            'manage_mentors',
            'manage_students',
            'manage_volunteers',
            'view_progress',
            'manage_discussions',
            'manage_achievements',
            'manage_badges',
            'manage_settings',
            'manage_notifications',
          ],
        },
      },
      {
        path: 'courses',
        name: 'dams-courses',
        component: () => import('@/pages/admin/academy/Courses.vue'),
        meta: { permissions: 'manage_courses', title: 'Courses' },
      },
      {
        path: 'courses/create',
        name: 'dams-courses-create',
        component: () => import('@/pages/admin/academy/CourseCreate.vue'),
        meta: { permissions: 'manage_courses', title: 'Create Course' },
      },
      {
        path: 'courses/:id/edit',
        name: 'dams-courses-edit',
        component: () => import('@/pages/admin/academy/CourseEdit.vue'),
        meta: { permissions: 'manage_courses', title: 'Edit Course' },
      },
      {
        path: 'modules',
        name: 'dams-modules',
        component: () => import('@/pages/admin/academy/Modules.vue'),
        meta: { permissions: 'manage_modules', title: 'Modules' },
      },
      {
        path: 'lessons',
        name: 'dams-lessons',
        component: () => import('@/pages/admin/academy/Lessons.vue'),
        meta: { permissions: 'manage_lessons', title: 'Lessons' },
      },
      {
        path: 'quizzes',
        name: 'dams-quizzes',
        component: () => import('@/pages/admin/academy/Quizzes.vue'),
        meta: { permissions: 'manage_quizzes', title: 'Quizzes' },
      },
      {
        path: 'quiz-management',
        name: 'dams-quiz-management',
        component: () => import('@/pages/admin/academy/QuizManagement.vue'),
        meta: { permissions: 'manage_quizzes', title: 'Quiz Management' },
      },
      {
        path: 'question-bank',
        name: 'dams-question-bank',
        component: () => import('@/pages/admin/academy/QuestionBank.vue'),
        meta: { permissions: 'manage_quizzes', title: 'Question Bank' },
      },
      {
        path: 'quiz-builder',
        name: 'dams-quiz-builder',
        component: () => import('@/pages/admin/academy/QuizBuilder.vue'),
        meta: { permissions: 'manage_quizzes', title: 'Quiz Builder' },
      },
      {
        path: 'students',
        name: 'dams-students',
        component: () => import('@/pages/admin/academy/Students.vue'),
        meta: { permissions: 'manage_students', title: 'Students' },
      },
      {
        path: 'mentors',
        name: 'dams-mentors',
        component: () => import('@/pages/admin/academy/Mentors.vue'),
        meta: { permissions: 'manage_mentors', title: 'Mentors' },
      },
      {
        path: 'mentor-management',
        name: 'dams-mentor-management',
        component: () => import('@/pages/admin/academy/MentorManagement.vue'),
        meta: { permissions: 'manage_mentors', title: 'Mentor Management' },
      },
      {
        path: 'assignments',
        name: 'dams-assignments',
        component: () => import('@/pages/admin/academy/Assignments.vue'),
        meta: { permissions: 'manage_mentors', title: 'Assignments' },
      },
      {
        path: 'progress',
        name: 'dams-progress',
        component: () => import('@/pages/admin/academy/Progress.vue'),
        meta: { permissions: 'view_progress', title: 'Progress' },
      },
      {
        path: 'moderation',
        name: 'dams-moderation',
        // Aligned with API middleware permission:manage_discussions (Phase 6)
        component: () => import('@/pages/admin/academy/DiscussionModeration.vue'),
        meta: { permissions: 'manage_discussions', title: 'Moderation' },
      },
      {
        path: 'learning-paths',
        name: 'dams-learning-paths',
        component: () => import('@/pages/admin/academy/LearningPathsAdmin.vue'),
        meta: { permissions: 'manage_learning_paths', title: 'Learning Paths' },
      },
      {
        path: 'achievements',
        name: 'dams-achievements',
        component: () => import('@/pages/admin/academy/AchievementsAdmin.vue'),
        meta: { permissions: 'manage_achievements', title: 'Achievements' },
      },
      {
        path: 'badges',
        name: 'dams-badges',
        component: () => import('@/pages/admin/academy/BadgesAdmin.vue'),
        meta: { permissions: 'manage_badges', title: 'Badges' },
      },
      {
        path: 'analytics',
        name: 'dams-analytics',
        component: () => import('@/pages/admin/academy/Analytics.vue'),
        meta: { permissions: 'view_analytics', title: 'Analytics' },
      },
      {
        path: 'reports',
        name: 'dams-reports',
        component: () => import('@/pages/admin/academy/AdminReports.vue'),
        meta: { permissions: 'view_analytics', title: 'Reports' },
      },
      {
        path: 'volunteer-analytics',
        name: 'dams-volunteer-analytics',
        component: () => import('@/pages/admin/academy/VolunteerAnalytics.vue'),
        meta: { permissions: 'view_analytics', title: 'Volunteer Analytics' },
      },
      {
        path: 'audit',
        name: 'dams-audit',
        component: () => import('@/pages/admin/academy/ActivityLogsAudit.vue'),
        meta: { permissions: 'view_analytics', title: 'Audit' },
      },
      {
        path: 'settings',
        name: 'dams-settings',
        component: () => import('@/pages/admin/academy/AdminSettings.vue'),
        meta: { permissions: 'manage_settings', title: 'Settings' },
      },
      {
        // Academy command center UI; uses Platform notification + queue APIs
        path: 'live-admin',
        name: 'dams-live-admin',
        component: () => import('@/pages/admin/academy/LiveAdminSection.vue'),
        meta: { permissions: 'manage_notifications', title: 'Live Admin' },
      },
    ],
  },
]

export default damsRoutes
