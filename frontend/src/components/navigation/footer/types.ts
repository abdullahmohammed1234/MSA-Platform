export interface FooterLink {
  label: string;
  path: string;
}

export interface FooterColumn {
  title: string;
  links: FooterLink[];
}

export interface FooterProps {
  columns?: FooterColumn[];
  socials?: {
    platform: string;
    url: string;
    icon?: string;
  }[];
  copyright?: string;
}
