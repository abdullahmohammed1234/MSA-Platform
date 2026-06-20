import type { RouteRecordRaw } from 'vue-router';

const academyRoutes: Array<RouteRecordRaw> = [
  {
    path: '/academy',
    component: () => import('@/layouts/AcademyLayout.vue'),
    meta: { requiresAuth: true, requiresStudent: true },
    children: [
      {
        path: '',
        redirect: '/academy/dashboard'
      },
      {
        path: 'dashboard',
        name: 'academy-dashboard',
        component: () => import('@/pages/academy/AcademyDashboardPage.vue'),
        meta: { title: 'Dashboard | Dawah Academy', desc: 'Manage your Dawah learning journey and courses.', requiresStudent: true }
      },
      {
        path: 'courses',
        name: 'academy-courses',
        component: () => import('@/pages/academy/CourseCatalogPage.vue'),
        meta: { title: 'Course Catalog | Dawah Academy', desc: 'Browse available Dawah courses and programs.', requiresStudent: true }
      },
      {
        path: 'courses/:idOrSlug',
        name: 'academy-course-details',
        component: () => import('@/pages/academy/CourseDetailsPage.vue'),
        meta: { title: 'Course Details | Dawah Academy', desc: 'View modules, objectives, and enrollment status.', requiresStudent: true }
      },
      {
        path: 'courses/:courseId/lessons/:lessonId',
        name: 'academy-lesson',
        component: () => import('@/pages/academy/LessonPage.vue'),
        meta: { title: 'Lesson View | Dawah Academy', desc: 'Interactive lesson viewer and resources.', requiresStudent: true }
      },
      {
        path: 'courses/:courseId/quizzes/:quizId',
        name: 'academy-quiz',
        component: () => import('@/pages/academy/QuizPage.vue'),
        meta: { title: 'Quiz | Dawah Academy', desc: 'Test your knowledge and progress.', requiresStudent: true }
      },
      {
        path: 'courses/:courseId/quizzes/:quizId/results',
        name: 'academy-quiz-results',
        component: () => import('@/pages/academy/QuizResultsPage.vue'),
        meta: { title: 'Quiz Results | Dawah Academy', desc: 'View your quiz performance and answers.', requiresStudent: true }
      },

      {
        path: 'achievements',
        name: 'academy-achievements',
        component: () => import('@/pages/academy/AchievementsPage.vue'),
        meta: { title: 'Achievements | Dawah Academy', desc: 'View unlocked achievements and learner points.', requiresStudent: true }
      },
      {
        path: 'badges',
        name: 'academy-badges',
        component: () => import('@/pages/academy/BadgesPage.vue'),
        meta: { title: 'Badges | Dawah Academy', desc: 'View unlocked badges and criteria.', requiresStudent: true }
      },
      {
        path: 'progress',
        name: 'academy-progress',
        component: () => import('@/pages/academy/ProgressPage.vue'),
        meta: { title: 'Learning Progress | Dawah Academy', desc: 'Detailed view of your academic progress.', requiresStudent: true }
      },
      {
        path: 'modules',
        name: 'academy-modules',
        component: () => import('@/pages/academy/ModulesPage.vue'),
        meta: { title: 'Modules | Dawah Academy', desc: 'Browse and continue module syllabi across enrolled courses.', requiresStudent: true }
      },
      {
        path: 'modules/:moduleId',
        name: 'academy-module-detail',
        component: () => import('@/pages/academy/ModuleDetailPage.vue'),
        meta: { title: 'Module | Dawah Academy', requiresStudent: true }
      },
      {
        path: 'quizzes',
        name: 'academy-quizzes',
        component: () => import('@/pages/academy/QuizzesPage.vue'),
        meta: { title: 'Quizzes | Dawah Academy', desc: 'Access knowledge assessments for enrolled courses.', requiresStudent: true }
      },
      {
        path: 'quizzes/:quizId',
        name: 'academy-quiz-shortcut',
        component: () => import('@/pages/academy/QuizShortcutPage.vue'),
        meta: { title: 'Quiz | Dawah Academy', requiresStudent: true }
      },
      {
        path: 'settings',
        name: 'academy-settings',
        component: () => import('@/pages/academy/SettingsPage.vue'),
        meta: { title: 'Settings | Dawah Academy', desc: 'Manage your academy profile and preferences.', requiresStudent: true }
      },
      {
        path: 'notifications',
        name: 'notifications-center',
        component: () => import('@/pages/academy/NotificationCenterPage.vue'),
        meta: { title: 'Notification Center | SFU MSA', desc: 'View and manage all your notifications.', requiresStudent: true }
      },
      {
        path: 'settings/notifications',
        name: 'notifications-preferences',
        component: () => import('@/pages/academy/NotificationPreferencesPage.vue'),
        meta: { title: 'Notification Settings | SFU MSA', desc: 'Manage your notification preferences.', requiresStudent: true }
      },
      
      // Ported Dawah Academy routes
      {
        path: 'scenarios',
        name: 'academy-scenarios',
        component: () => import('@/pages/academy/ScenariosPage.vue'),
        meta: { title: 'Practice Lab | Dawah Academy', desc: 'Interactive branching street-dawah scenario exercises.', requiresStudent: true }
      },
      {
        path: 'discussions',
        name: 'academy-discussions',
        component: () => import('@/pages/academy/DiscussionsPage.vue'),
        meta: { title: 'Peer Discussions | Dawah Academy', desc: 'Collaborate with other outreach volunteers.', requiresStudent: true }
      },
      {
        path: 'resources',
        name: 'academy-resources',
        component: () => import('@/pages/academy/ResourcesPage.vue'),
        meta: { title: 'Storage Vault | Dawah Academy', desc: 'Scholastic dawah resources and files.', requiresStudent: true }
      },
      {
        path: 'islam-buffet',
        name: 'academy-islam-buffet',
        component: () => import('@/pages/academy/IslamBuffetPage.vue'),
        meta: { title: 'Islam Buffet | Dawah Academy', desc: 'Interactive stations and outreach checklist.', requiresStudent: true }
      },
      {
        path: 'dawah-schedule',
        name: 'academy-dawah-schedule',
        component: () => import('@/pages/academy/DawahSchedulePage.vue'),
        meta: { title: 'Dawah Schedule | Dawah Academy', desc: 'Shared calendars for upcoming outreach sessions.', requiresStudent: true }
      },
      {
        path: 'profile',
        name: 'academy-profile',
        component: () => import('@/pages/academy/ProfilePage.vue'),
        meta: { title: 'Profile | Dawah Academy', desc: 'View your course catalog progress and achievements.', requiresStudent: true }
      },
      
      // Mentor Only Routes
      {
        path: 'mentor',
        name: 'academy-mentor-dashboard',
        component: () => import('@/pages/academy/mentor/MentorDashboard.vue'),
        meta: { title: 'Mentor Studio | Dawah Academy', requiresMentor: true }
      },
      {
        path: 'mentor/courses',
        name: 'academy-mentor-courses',
        component: () => import('@/pages/academy/mentor/MentorCourses.vue'),
        meta: { title: 'Course Supervision | Dawah Academy', requiresMentor: true }
      },
      {
        path: 'mentor/exams',
        name: 'academy-mentor-exams',
        component: () => import('@/pages/academy/mentor/MentorExams.vue'),
        meta: { title: 'Exam Builder | Dawah Academy', requiresMentor: true }
      },
      {
        path: 'mentor/grading',
        name: 'academy-mentor-grading',
        component: () => import('@/pages/academy/mentor/MentorGrading.vue'),
        meta: { title: 'Corrections Queue | Dawah Academy', requiresMentor: true }
      },
      {
        path: 'mentor/learners',
        name: 'academy-mentor-learners',
        component: () => import('@/pages/academy/mentor/MentorLearners.vue'),
        meta: { title: 'Learner Coaching | Dawah Academy', requiresMentor: true }
      },
      {
        path: 'mentor-hub',
        redirect: '/academy/mentor'
      }
    ]
  }
];

export default academyRoutes;
