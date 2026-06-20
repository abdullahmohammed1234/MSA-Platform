export interface CmsBlock {
  id: number;
  homepage_section_id: number;
  key: string;
  value: string | null;
  type: 'text' | 'textarea' | 'image' | 'url' | 'list';
  display_order: number;
}

export interface CmsSection {
  id: number;
  name: string;
  key: string;
  display_order: number;
  is_visible: boolean;
  status: 'draft' | 'published' | 'archived';
  blocks: CmsBlock[];
}

export interface Announcement {
  id?: number;
  uuid: string;
  title: string;
  slug: string;
  content: string;
  summary: string | null;
  featured_image: string | null;
  status: 'draft' | 'published' | 'archived';
  published_at: string | null;
  author_id: number | null;
  author?: {
    id: number;
    name: string;
  } | null;
  created_at?: string;
  updated_at?: string;
}

export interface Event {
  id?: number;
  uuid: string;
  title: string;
  description: string;
  location: string;
  date: string;
  time: string;
  start_date: string;
  end_date: string | null;
  registration_url: string | null;
  image: string | null;
  category: string;
  status: 'draft' | 'published' | 'archived';
  spots_left: number;
  featured: boolean;
  registration_deadline: string | null;
  registrations_count?: number;
  created_at?: string;
  updated_at?: string;
}

export interface EventRegistration {
  uuid: string;
  first_name: string;
  last_name: string;
  full_name: string;
  email: string;
  student_id: string;
  status: string;
  registered_at: string;
}

export interface TeamMember {
  id?: number;
  uuid: string;
  name: string;
  role: string;
  dept: string;
  img: string | null;
  bio: string | null;
  email: string | null;
  linkedin: string | null;
  display_order: number;
  status: 'draft' | 'published' | 'archived';
  created_at?: string;
  updated_at?: string;
}

export interface Resource {
  id?: number;
  uuid: string;
  title: string;
  description: string;
  category: string;
  icon_name: string;
  link: string;
  is_external: boolean;
  tags: string[] | null;
  file: string | null;
  thumbnail: string | null;
  status: 'draft' | 'published' | 'archived';
  created_at?: string;
  updated_at?: string;
}

export interface Media {
  id: number;
  uuid: string;
  filename: string;
  filepath: string;
  url: string;
  mime_type: string;
  size: number;
  uploaded_by: number | null;
  uploader?: {
    id: number;
    name: string;
  } | null;
  created_at: string;
}

export interface CmsRevision {
  id: number;
  revisable_type: string;
  revisable_id: number;
  user_id: number | null;
  content: any;
  version: number;
  created_at: string;
  user?: {
    id: number;
    name: string;
  } | null;
}

export interface CmsDashboardStats {
  announcements: {
    total: number;
    drafts: number;
    published: number;
  };
  events: {
    total: number;
    upcoming: number;
    past: number;
  };
  team: {
    total: number;
  };
  resources: {
    total: number;
  };
}

export interface CmsDashboardActivity {
  id: number;
  user_id: number | null;
  action: string;
  target_type: string | null;
  target_id: number | null;
  description: string | null;
  payload: any | null;
  created_at: string;
  user?: {
    id: number;
    name: string;
  } | null;
}
