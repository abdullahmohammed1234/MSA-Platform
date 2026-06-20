import type { NavItem } from '../navbar/types';

export interface MobileNavProps {
  isOpen: boolean;
  navItems: NavItem[];
  brandName?: string;
  isAuthenticated?: boolean;
}
