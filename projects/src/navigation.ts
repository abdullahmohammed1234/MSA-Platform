import {
  LayoutDashboard,
  FolderKanban,
  CheckSquare,
  Calendar,
  Building2,
  Users,
  type LucideIcon,
} from 'lucide-vue-next';

export interface PmNavItem {
  name: string;
  path: string;
  icon: LucideIcon;
  shortName?: string;
}

export const PM_NAV_ITEMS: PmNavItem[] = [
  { name: 'Dashboard', path: '/', icon: LayoutDashboard },
  { name: 'Projects', path: '/projects', icon: FolderKanban },
  { name: 'My Tasks', path: '/my-tasks', icon: CheckSquare, shortName: 'Tasks' },
  { name: 'Calendar', path: '/calendar', icon: Calendar },
  { name: 'Resources', path: '/resources', icon: Users },
  { name: 'Departments', path: '/departments', icon: Building2, shortName: 'Depts' },
];

export function isPmNavActive(currentPath: string, itemPath: string): boolean {
  if (itemPath === '/') return currentPath === '/';
  return currentPath.startsWith(itemPath);
}
