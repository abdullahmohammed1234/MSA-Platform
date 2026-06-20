export interface ProgressBarProps {
  value: number;
  color?: string;
  showLabel?: boolean;
}

export interface CircularProgressProps {
  value: number;
  size?: number;
  strokeWidth?: number;
  color?: string;
}

export interface Milestone {
  id: string;
  label: string;
  description?: string;
  status: 'locked' | 'active' | 'completed';
}

export interface MilestoneTrackerProps {
  milestones: Milestone[];
}
