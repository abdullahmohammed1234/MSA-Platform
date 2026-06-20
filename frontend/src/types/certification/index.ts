import type { Course, LearningPath } from '../academy';

export interface CertificateTemplate {
  id: number;
  uuid: string;
  name: string;
  title_template: string;
  description_template: string | null;
  layout: 'landscape' | 'portrait';
  branding: {
    primary_color?: string;
    secondary_color?: string;
    logo_url?: string;
  } | null;
  signatures: Array<{
    name: string;
    title: string;
    image_path: string | null;
  }> | null;
  background_asset: string | null;
  status: 'active' | 'inactive';
  created_at: string;
  updated_at: string;
}

export interface Certificate {
  id: number;
  uuid: string;
  certificate_template_id: number;
  template?: CertificateTemplate;
  title: string;
  description: string | null;
  type: 'course' | 'learning_path' | 'manual';
  course_id: number | null;
  course?: Course;
  learning_path_id: number | null;
  learning_path?: LearningPath;
  created_at: string;
  updated_at: string;
}

export interface CertificateAward {
  id: number;
  uuid: string;
  user_id: number;
  user?: {
    id: number;
    name: string;
    email: string;
  };
  certificate_id: number;
  certificate?: Certificate;
  title: string;
  description: string | null;
  code: string;
  verification_token: string;
  pdf_path: string | null;
  issued_by: number | null;
  issuer?: {
    id: number;
    name: string;
  };
  issued_at: string;
  created_at: string;
  updated_at: string;
}

export interface CertificateVerification {
  id: number;
  certificate_award_id: number;
  ip_address: string | null;
  user_agent: string | null;
  verified_at: string;
  created_at: string;
}

export interface Achievement {
  id: number;
  uuid: string;
  title: string;
  slug: string;
  description: string | null;
  type: 'completion' | 'performance' | 'participation' | 'special_recognition';
  points: number;
  criteria_type: string;
  criteria_value: any;
  unlocked: boolean; // computed attribute for user
  unlocked_at: string | null; // computed attribute for user
}

export interface AchievementAward {
  id: number;
  uuid: string;
  user_id: number;
  achievement_id: number;
  achievement?: Achievement;
  unlocked_at: string;
}

export interface Badge {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  description: string | null;
  image_path: string | null;
  criteria_type: string;
  criteria_value: any;
  unlocked: boolean; // computed attribute for user
  unlocked_at: string | null; // computed attribute for user
}

export interface BadgeAward {
  id: number;
  uuid: string;
  user_id: number;
  badge_id: number;
  badge?: Badge;
  awarded_at: string;
}

export interface Milestone {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  description: string | null;
  type: 'courses_completed' | 'lessons_completed' | 'paths_completed';
  threshold: number;
  unlocked: boolean; // computed attribute for user
  unlocked_at: string | null; // computed attribute for user
}

export interface MilestoneAward {
  id: number;
  uuid: string;
  user_id: number;
  milestone_id: number;
  milestone?: Milestone;
  awarded_at: string;
}
