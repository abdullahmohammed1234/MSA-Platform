import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import academyRoutes from '../../router/academy';
import { useCoursesStore } from '../../stores/academy/courses';
import { useProgressStore } from '../../stores/academy/progress';
import { useQuizStore } from '../../stores/academy/quiz';
import { useDashboardStore } from '../../stores/academy/dashboard';

vi.mock('@/services/academy/coursesService', () => ({
  coursesService: {
    getCourses: vi.fn(),
    getCourseDetails: vi.fn(),
    enroll: vi.fn(),
    getLearningPaths: vi.fn(),
  },
}));

vi.mock('@/services/academy/lessonsService', () => ({
  lessonsService: {
    completeLesson: vi.fn(),
  },
}));

import { coursesService } from '../../services/academy/coursesService';
import { lessonsService } from '../../services/academy/lessonsService';

describe('Dawah Academy Routing and SEO Configuration', () => {
  it('should define all expected student experience sub-routes under /academy', () => {
    const parentRoute = academyRoutes.find(r => r.path === '/academy');
    expect(parentRoute).toBeDefined();

    const children = parentRoute?.children || [];
    const paths = children.map(c => c.path);

    expect(paths).toContain('');
    expect(paths).toContain('dashboard');
    expect(paths).toContain('courses');
    expect(paths).toContain('courses/:idOrSlug');
    expect(paths).toContain('courses/:courseId/lessons/:lessonId');
    expect(paths).toContain('courses/:courseId/quizzes/:quizId');
    expect(paths).toContain('modules');
    expect(paths).toContain('quizzes');
    expect(paths).toContain('settings');
    expect(paths).toContain('badges');
    expect(paths).toContain('progress');
  });

  it('should specify title and description meta for SEO validation', () => {
    const parentRoute = academyRoutes.find(r => r.path === '/academy');
    const children = parentRoute?.children || [];

    const dashboard = children.find(c => c.path === 'dashboard');
    expect(dashboard?.meta?.title).toBe('Dashboard | Dawah Academy');

    const courses = children.find(c => c.path === 'courses');
    expect(courses?.meta?.title).toBe('Course Catalog | Dawah Academy');
  });
});

describe('Dawah Academy Courses Service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should return courses from the API response', async () => {
    vi.mocked(coursesService.getCourses).mockResolvedValue([
      { id: 1, title: 'Introduction to Dawah', slug: 'introduction-to-dawah' } as any,
    ]);

    const courses = await coursesService.getCourses();
    expect(courses).toHaveLength(1);
    expect(courses[0].title).toBe('Introduction to Dawah');
  });
});

describe('Academy Stores Integration', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  it('should handle course enrollments and initialize progress to 0%', async () => {
    const coursesStore = useCoursesStore();
    const progressStore = useProgressStore();

    vi.mocked(coursesService.enroll).mockResolvedValue({
      id: 1,
      user_id: 1,
      course_id: 1,
      status: 'active',
      enrolled_at: new Date().toISOString(),
      completed_at: null,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    });

    expect(progressStore.courseProgress[1]).toBeUndefined();

    await coursesStore.enrollInCourse(1);
    expect(progressStore.courseProgress[1]).toBe(0);
  });

  it('should complete lessons and recalculate progress percentage', async () => {
    const progressStore = useProgressStore();

    vi.mocked(lessonsService.completeLesson).mockResolvedValue({
      success: true,
      message: 'Lesson completed successfully.',
      completion_percentage: 25,
      course_completed: false,
      progress: {
        id: 1,
        user_id: 1,
        course_id: 1,
        lesson_id: 1001,
        completion_percentage: 100,
        completed: true,
        completed_at: new Date().toISOString(),
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      },
    });

    expect(progressStore.isLessonCompleted(1, 1001)).toBe(false);

    await progressStore.completeLesson(1, 1001);

    expect(progressStore.isLessonCompleted(1, 1001)).toBe(true);
    expect(progressStore.courseProgress[1]).toBe(25);
  });

  it('should manage quiz timer and answer mapping', () => {
    const quizStore = useQuizStore();
    const mockQuiz: any = {
      id: 501,
      course_id: 1,
      title: 'Intro Quiz',
      time_limit: 10,
      questions: []
    };

    quizStore.startQuiz(mockQuiz);

    expect(quizStore.activeQuiz).toBeDefined();
    expect(quizStore.timeRemaining).toBe(600);

    quizStore.setAnswer(5001, ['To invite/summon']);
    expect(quizStore.getAnswer(5001)).toContain('To invite/summon');
  });

  it('should compile statistics for dashboard metrics correctly', async () => {
    const progressStore = useProgressStore();
    const dashboardStore = useDashboardStore();

    progressStore.courseProgress[1] = 50;
    progressStore.courseProgress[2] = 100;

    await dashboardStore.fetchDashboardData();

    expect(dashboardStore.stats.coursesEnrolled).toBe(2);
    expect(dashboardStore.stats.coursesCompleted).toBe(1);
    expect(dashboardStore.stats.overallProgress).toBe(75);
  });
});
