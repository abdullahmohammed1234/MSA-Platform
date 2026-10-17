export type InquiryStatus = 'open' | 'in_progress' | 'resolved' | 'closed';
export type InquiryPriority = 'low' | 'normal' | 'high' | 'urgent';

export interface SupportCategory {
  value: string;
  label: string;
  description: string;
}

export interface SupportSender {
  id: number;
  name: string;
  email?: string;
}

export interface SupportMessage {
  id: number;
  inquiry_id?: number;
  user_id: number;
  sender_name: string;
  body: string;
  is_staff_reply: boolean;
  created_at: string;
  updated_at?: string;
  sender?: SupportSender;
}

export interface SupportInquiry {
  id: number;
  reference_code: string;
  subject: string;
  category: string;
  priority: InquiryPriority;
  status: InquiryStatus;
  is_read_by_member: boolean;
  is_read_by_staff: boolean;
  last_activity_at: string;
  resolved_at?: string | null;
  created_at: string;
  updated_at: string;
  messages_count?: number;
  unread_member_messages_count?: number;
  unread_staff_messages_count?: number;
  user?: SupportSender;
  assigned_staff?: SupportSender | null;
  last_message?: SupportMessage | null;
  messages?: SupportMessage[];
}

export interface CreateInquiryPayload {
  category: string;
  subject: string;
  message: string;
}

export interface SendReplyPayload {
  message: string;
}

export interface UpdateInquiryStatusPayload {
  status: InquiryStatus;
}

export interface AssignInquiryPayload {
  assigned_to: number | null;
}

export interface PaginatedInquiries {
  current_page: number;
  data: SupportInquiry[];
  total: number;
  per_page: number;
  last_page: number;
}

export interface StaffSupportStats {
  total: number;
  open: number;
  in_progress: number;
  resolved: number;
  closed: number;
  unread_by_staff: number;
}

export interface ResourcesStatusResponse {
  enabled: boolean;
  message?: string;
}

export interface CommunityResource {
  id: string | number;
  title: string;
  description: string;
  category: string;
  iconName?: string;
  link?: string;
  thumbnail?: string | null;
  file?: string | null;
  isExternal?: boolean;
  tags?: string[];
}
