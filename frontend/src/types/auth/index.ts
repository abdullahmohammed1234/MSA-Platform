export interface User {
  id: number;
  uuid: string;
  name: string;
  email: string;
  avatar: string | null;
  is_active: boolean;
  is_verified: boolean;
  requires_email_verification: boolean;
  email_verified_at: string | null;
  roles: string[];
  permissions: string[];
  created_at: string;
  academy_onboarding_completed_at: string | null;
}

export interface LoginPayload {
  email: string;
  password: string;
  remember?: boolean;
}

export interface RegisterPayload {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  terms: boolean;
  role: 'member' | 'volunteer';
}

export interface ForgotPasswordPayload {
  email: string;
}

export interface ResetPasswordPayload {
  email: string;
  token: string;
  password: string;
  password_confirmation: string;
}

export interface AuthResponse {
  message: string;
  user: User;
  token?: string;
}

export interface Role {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  description?: string;
  permissions?: Permission[];
  created_at?: string;
  updated_at?: string;
}

export interface Permission {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  description?: string;
  module: string;
  created_at?: string;
  updated_at?: string;
}

export interface Ability {
  action: string;
  subject: string;
}

export interface RoleAssignment {
  roles: string[];
}

export interface PermissionAssignment {
  permissions: string[];
}

export interface AuditLog {
  id: number;
  user_id: number | null;
  action: string;
  target_type: string | null;
  target_id: number | null;
  description: string | null;
  payload: any;
  created_at: string;
  user?: User;
}
