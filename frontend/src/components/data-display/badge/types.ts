export interface BadgeProps {
  variant?: 'primary' | 'secondary' | 'success' | 'warning' | 'error' | 'info' | 'gold' | 'outline';
  size?: 'sm' | 'md' | 'lg';
  isShimmer?: boolean;
  isPulse?: boolean;
}

export interface AchievementBadgeProps {
  title: string;
  description: string;
  xp?: number;
  unlockedAt?: string;
  isLocked?: boolean;
}

export interface RoleBadgeProps {
  role: 'mujtahid' | 'alim' | 'murshid' | 'mutallim';
  title?: string;
  description?: string;
}
