import client from '@/services/api';

export interface ConnectConfig {
  connect_resources_enabled: boolean;
  member_directory_enabled: boolean;
  direct_messaging_enabled: boolean;
  events_feed_enabled: boolean;
  announcements_feed_enabled: boolean;
  webpush_enabled: boolean;
  max_devices_per_user: number;
  delivery_timeout_seconds: number;
  support_inquiries_enabled: boolean;
  maintenance_mode: boolean;
  maintenance_message: string | null;
}

export interface ConnectHealth {
  status: string;
  database: {
    status: string;
    latency_ms: number;
    driver: string;
  };
  web_push: {
    status: string;
    vapid_configured: boolean;
    delivery_mode: string;
    timeout_seconds: number;
  };
  cache: {
    status: string;
    driver: string;
  };
  devices_store: {
    status: string;
    total_registered_devices: number;
  };
  checked_at: string;
}

export interface ConnectMetrics {
  registered_devices: number;
  active_devices: number;
  unique_device_users: number;
  total_conversations: number;
  total_messages: number;
  support_inquiries: number;
  open_inquiries: number;
  resolved_inquiries: number;
  updated_at: string;
}

export interface ConnectSystemInfo {
  name: string;
  slug: string;
  version: string;
  status: string;
  health_status: string;
  status_reason: string | null;
  frontend_url: string;
  admin_path: string;
  owns: string[];
  does_not_own: string[];
  updated_at: string;
}

export interface ConnectEventItem {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  short_description?: string;
  start_at: string;
  end_at?: string;
  location?: string;
  capacity?: number;
  is_public: boolean;
  is_featured: boolean;
  status: string;
}

export interface ConnectAnnouncementItem {
  id: number;
  uuid: string;
  title: string;
  slug: string;
  summary?: string;
  published_at?: string;
  status: string;
  author?: {
    id: number;
    name: string;
    email: string;
  };
}

export interface ConnectAuditItem {
  id: number;
  action: string;
  severity: string;
  description?: string;
  payload?: Record<string, unknown>;
  ip_address?: string;
  created_at: string;
  user?: {
    id: number;
    name: string;
    email: string;
  };
}

export const connectAdminService = {
  async getSystemInfo(): Promise<ConnectSystemInfo> {
    const response = await client.get('/admin/systems/connect');
    return response.data.system;
  },

  async getHealth(): Promise<ConnectHealth> {
    const response = await client.get('/admin/systems/connect/health');
    return response.data.health;
  },

  async getMetrics(): Promise<ConnectMetrics> {
    const response = await client.get('/admin/systems/connect/metrics');
    return response.data.metrics;
  },

  async getConfig(): Promise<ConnectConfig> {
    const response = await client.get('/admin/systems/connect/config');
    return response.data.config;
  },

  async updateConfig(payload: ConnectConfig): Promise<{ success: boolean; message: string; config: ConnectConfig }> {
    const response = await client.put('/admin/systems/connect/config', payload);
    return response.data;
  },

  async getEvents(params?: { search?: string; page?: number; per_page?: number }): Promise<{
    authoritative_source: string;
    ownership_notice: string;
    events: {
      data: ConnectEventItem[];
      total: number;
      current_page: number;
      last_page: number;
    };
  }> {
    const response = await client.get('/admin/systems/connect/events', { params });
    return response.data;
  },

  async getAnnouncements(params?: { search?: string; page?: number; per_page?: number }): Promise<{
    authoritative_source: string;
    ownership_notice: string;
    announcements: {
      data: ConnectAnnouncementItem[];
      total: number;
      current_page: number;
      last_page: number;
    };
  }> {
    const response = await client.get('/admin/systems/connect/announcements', { params });
    return response.data;
  },

  async getAuditLogs(params?: { page?: number; per_page?: number }): Promise<{
    logs: {
      data: ConnectAuditItem[];
      total: number;
      current_page: number;
      last_page: number;
    };
  }> {
    const response = await client.get('/admin/systems/connect/audit', { params });
    return response.data;
  },
};

export default connectAdminService;
