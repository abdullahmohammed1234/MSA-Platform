export type Difficulty = 'beginner' | 'intermediate' | 'advanced';
export type CourseStatus = 'draft' | 'published' | 'archived';
export type QuizStatus = 'draft' | 'published';
export type QuestionType = 'multiple_choice' | 'multiple_select' | 'true_false' | 'short_answer';
export type EnrollmentStatus = 'active' | 'completed' | 'dropped';

export interface CreatorSummary {
  id: number;
  name: string;
}

export interface Course {
  id: number;
  uuid: string;
  title: string;
  slug: string;
  description: string | null;
  thumbnail: string | null;
  difficulty: Difficulty;
  estimated_duration: number | null; // in minutes
  status: CourseStatus;
  published_at: string | null;
  created_by: number | null;
  creator?: CreatorSummary;
  modules?: Module[];
  quizzes?: Quiz[];
  created_at: string;
  updated_at: string;
}

export interface Module {
  id: number;
  course_id: number;
  title: string;
  description: string | null;
  order: number;
  estimated_duration: number | null; // in minutes
  course?: Course;
  lessons?: Lesson[];
  created_at: string;
  updated_at: string;
}

export interface Lesson {
  id: number;
  module_id: number;
  title: string;
  slug: string;
  content: string | null;
  video_url: string | null;
  attachments: Array<{ name: string; url: string; size?: number }> | null;
  order: number;
  estimated_duration: number | null; // in minutes
  is_required: boolean;
  module?: Module;
  created_at: string;
  updated_at: string;
}

export interface Quiz {
  id: number;
  course_id: number;
  title: string;
  description: string | null;
  passing_score: number; // percentage (e.g. 70)
  time_limit: number | null; // in minutes
  attempt_limit: number | null;
  status: QuizStatus;
  course?: Course;
  course_title?: string | null;
  questions?: Question[];
  created_at: string;
  updated_at: string;
}

export interface Question {
  id: number;
  quiz_id: number;
  type: QuestionType;
  question: string;
  options: string[] | null;
  correct_answer?: string[]; // optionally returned for admins
  explanation?: string | null;
  points: number;
  order: number;
  quiz?: Quiz;
  created_at: string;
  updated_at: string;
}

export type CourseSummary = Pick<Course, 'id' | 'title' | 'slug' | 'difficulty'> &
  Partial<Pick<Course, 'description' | 'thumbnail' | 'estimated_duration'>>;

export interface Enrollment {
  id: number;
  user_id: number;
  course_id: number;
  status: EnrollmentStatus;
  enrolled_at: string;
  completed_at: string | null;
  course?: Course;
  created_at: string;
  updated_at: string;
}

export interface Progress {
  id: number;
  user_id: number;
  course_id: number;
  lesson_id: number;
  completion_percentage: number;
  completed: boolean;
  completed_at: string | null;
  lesson?: Lesson;
  created_at: string;
  updated_at: string;
}

export interface QuizAttempt {
  id: number;
  user_id: number;
  quiz_id: number;
  score: number; // calculated grade percentage
  passed: boolean;
  started_at: string;
  submitted_at: string | null;
  quiz?: Quiz;
  answers?: AnswerResponse[];
  created_at: string;
  updated_at: string;
}

export interface AnswerResponse {
  id: number;
  question_id: number;
  answer: string[];
  is_correct: boolean;
  points_awarded: number;
}

export interface Certificate {
  id: number;
  uuid: string;
  user_id: number;
  course_id: number;
  title: string;
  description: string | null;
  template: string | null;
  code: string;
  issued_at: string;
  user?: { id: number; name: string };
  course?: { id: number; title: string; slug: string };
  created_at: string;
  updated_at: string;
}

export interface LearningPath {
  id: number;
  uuid: string;
  title: string;
  slug: string;
  description: string | null;
  courses?: Course[];
  created_at: string;
  updated_at: string;
}

export interface LMSAnalytics {
  courses_count: number;
  enrolled_users_count: number;
  enrollments: {
    active: number;
    completed: number;
    dropped: number;
  };
  course_performance: Array<{
    course_id: number;
    title: string;
    total: number;
    completed: number;
    completion_rate: number;
  }>;
  quiz_performance: Array<{
    quiz_id: number;
    title: string;
    attempts: number;
    average_score: number;
    passed: number;
    passing_rate: number;
  }>;
}
