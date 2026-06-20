export interface SidebarItem {
  label: string;
  path: string;
  icon?: string;
  children?: SidebarItem[];
}

export interface SidebarProps {
  items: SidebarItem[];
  collapsed?: boolean;
  title?: string;
  subtitle?: string;
  logoSrc?: string;
  logoAlt?: string;
}
