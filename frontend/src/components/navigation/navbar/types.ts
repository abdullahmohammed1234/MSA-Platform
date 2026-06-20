export interface NavItem {
  label: string;
  path: string;
  requiresAuth?: boolean;
  requiresAdmin?: boolean;
}

export interface NavbarProps {
  brandName?: string;
  navItems: NavItem[];
  isAuthenticated?: boolean;
  user?: {
    name: string;
    email: string;
    avatar?: string;
    role?: string;
    roles?: string[];
  } | null;
}
