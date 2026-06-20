/**
 * SFU MSA Dawah Academy Analytics Helper
 * Tracks learning events and engagement metrics.
 */

export interface AnalyticsEvent {
  eventName: string;
  courseId?: number;
  courseTitle?: string;
  moduleId?: number;
  lessonId?: number;
  lessonTitle?: string;
  quizId?: number;
  score?: number;
  passed?: boolean;
  certificateCode?: string;
  engagementType?: string;
  timestamp: string;
}

const logEvent = (event: AnalyticsEvent) => {
  // Console logging for verification and local debugging
  console.log(
    `%c[Analytics] ${event.eventName.toUpperCase()}`,
    'color: #ffd053; background: #640c0e; padding: 2px 6px; border-radius: 4px; font-weight: bold;',
    event
  );

  // In production, this would send a POST request to an analytics logging endpoint:
  // client.post('/academy/analytics/track', event).catch(err => console.error('Analytics tracking failed', err));
};

export const academyAnalytics = {
  trackLessonCompletion(courseId: number, lessonId: number, lessonTitle: string) {
    logEvent({
      eventName: 'lesson_completed',
      courseId,
      lessonId,
      lessonTitle,
      timestamp: new Date().toISOString()
    });
  },

  trackQuizAttempt(courseId: number, quizId: number, score: number, passed: boolean) {
    logEvent({
      eventName: 'quiz_attempted',
      courseId,
      quizId,
      score,
      passed,
      timestamp: new Date().toISOString()
    });
  },

  trackCourseCompletion(courseId: number, courseTitle: string) {
    logEvent({
      eventName: 'course_completed',
      courseId,
      courseTitle,
      timestamp: new Date().toISOString()
    });
  },

  trackCertificateAward(courseId: number, certificateCode: string) {
    logEvent({
      eventName: 'certificate_awarded',
      courseId,
      certificateCode,
      timestamp: new Date().toISOString()
    });
  },

  trackStudentEngagement(engagementType: 'login' | 'open_syllabus' | 'resume_course' | 'download_attachment') {
    logEvent({
      eventName: 'engagement_tracked',
      engagementType,
      timestamp: new Date().toISOString()
    });
  }
};
