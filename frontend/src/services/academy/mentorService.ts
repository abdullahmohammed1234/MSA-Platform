import client from '@/services/api';

export interface MentorCourse {
  id: number;
  title: string;
  slug: string;
  category: string;
  description: string;
  mentorId: string | number;
  mentorName: string;
  mentorAvatar: string;
  published: boolean;
  enrollmentCount: number;
  completionRate: number;
  quizAverage: number;
  xpReward: number;
  modulesCount: number;
  lessons: Array<{
    id: number;
    title: string;
    duration: string;
    order: number;
    contentType: string;
    published: boolean;
  }>;
  createdAt: string;
}

export interface MentorSubmission {
  id: string | number;
  type: 'reflection' | 'outreach_log';
  volunteerName: string;
  volunteerAvatar: string;
  itemTitle: string;
  courseTitle: string;
  submittedAt: string;
  score?: number;
  status: 'graded' | 'needs_review';
  details: string;
}

export interface MentorLearner {
  id: number;
  name: string;
  email: string;
  avatar: string;
  enrolledCourses: number;
  completedCourses: number;
  quizAverage: number;
  activeStreak: number;
  lastActive: string;
  progressPercent: number;
  riskStatus?: string;
}

export interface MentorExam {
  id: number;
  title: string;
  category: string;
  courseTitle: string;
  passingScore: number;
  timeLimit: number;
  totalAttempts: number;
  uniqueVolunteers: number;
  averageScore: number;
  passRate: number;
  status: string;
  questionsAnalysis: Array<{
    id: number;
    text: string;
    type: string;
    correctPercent: number;
    difficulty: string;
  }>;
}

export interface MentorWorkspaceState {
  courses: MentorCourse[];
  submissions: MentorSubmission[];
  volunteers: MentorLearner[];
  quizAnalytics: MentorExam[];
  certifications: unknown[];
  discussions: unknown[];
}

export const mentorService = {
  async getDashboardState(): Promise<MentorWorkspaceState> {
    const response = await client.get('/mentor/dashboard');
    const data = response.data;

    if (!data?.success) {
      throw new Error(data?.message || 'Failed to load mentor dashboard.');
    }

    return {
      courses: data.courses || [],
      submissions: data.submissions || [],
      volunteers: data.volunteers || [],
      quizAnalytics: data.quizAnalytics || [],
      certifications: data.certifications || [],
      discussions: data.discussions || [],
    };
  },

  async gradeSubmission(submissionId: string | number, score: number): Promise<void> {
    const response = await client.post(`/mentor/submissions/${submissionId}/grade`, { score });
    if (!response.data?.success) {
      throw new Error(response.data?.message || 'Failed to grade submission.');
    }
  },
};
