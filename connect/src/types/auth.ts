/**
 * User identity contract aligned with Central Laravel UserResource.
 */
export interface User {
  id: number;
  uuid: string;
  name: string;
  email: string;
  gender?: 'male' | 'female' | null;
  avatar: string | null;
  is_active: boolean;
  is_verified: boolean;
  requires_email_verification: boolean;
  email_verified_at: string | null;
  roles: string[];
  permissions: string[];
  application_access?: Record<string, {
    access: boolean;
    source: string;
  }>;
  created_at: string;
  academy_onboarding_completed_at?: string | null;
}

export interface LoginPayload {
  email: string;
  password: string;
  remember?: boolean;
}

export interface LoginResponse {
  message: string;
  user: User;
  token: string;
}

export interface MeResponse {
  user: User;
}

export interface LogoutResponse {
  message: string;
}
