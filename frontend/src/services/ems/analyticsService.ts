import { emsHttp } from './emsClient';

export interface AnalyticsKpis {
  total_registrations: number;
  confirmed_registrations: number;
  cancelled_registrations: number;
  tickets_issued: number;
  tickets_sold: number;
  checked_in: number;
  no_shows: number;
  attendance_rate: number;
  no_show_rate: number;
  gross_revenue: number;
  refunds: number;
  net_revenue: number;
  waitlist_size: number;
  waitlist_conversions: number;
  total_capacity: number | null;
  capacity_utilization: number;
}

export interface RegistrationTrend {
  date: string;
  count: number;
}

export interface MemberBreakdown {
  counts: {
    members: number;
    volunteers: number;
    students: number;
    guests: number;
    others: number;
  };
  percentages: {
    members: number;
    volunteers: number;
    students: number;
    guests: number;
    others: number;
  };
  total: number;
}

export interface TicketPerformance {
  id: number;
  name: string;
  price: number;
  capacity: number | null;
  sold: number;
  remaining: number | null;
  sell_through: number | null;
  revenue: number;
}

export interface EarlyBirdComparison {
  comparison: {
    early_bird: { sold: number; revenue: number };
    standard: { sold: number; revenue: number };
    vip: { sold: number; revenue: number };
  };
  remaining_inventory: number;
}

export interface NoShowBreakdown {
  total: number;
  paid: number;
  free: number;
  rate: number;
}

export interface AnalyticsPayload {
  kpis: AnalyticsKpis;
  charts: {
    registrations_over_time: RegistrationTrend[];
    member_breakdown: MemberBreakdown;
    ticket_performance: TicketPerformance[];
    early_bird: EarlyBirdComparison;
    no_shows: NoShowBreakdown;
  };
}

export interface EventComparisonItem {
  uuid: string;
  name: string;
  start_at: string | null;
  capacity: number | null;
  registrations: number;
  checked_in: number;
  no_shows: number;
  attendance_rate: number;
  no_show_rate: number;
  gross_revenue: number;
  refunds: number;
  net_revenue: number;
  waitlist_size: number;
}

export interface AnalyticsReportItem {
  id: number;
  uuid: string;
  title: string;
  type: string;
  filters: {
    format?: 'pdf' | 'xlsx' | 'csv';
    [key: string]: any;
  } | null;
  generated_by: number | null;
  generated_at: string | null;
  file_path: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface ExportReportPayload {
  title: string;
  format: 'csv' | 'xlsx' | 'pdf';
  start_date?: string;
  end_date?: string;
  sections: {
    registrations: boolean;
    revenue: boolean;
    attendance: boolean;
    ticket_sales: boolean;
    payments: boolean;
    waitlist: boolean;
    check_ins: boolean;
  };
}

export const analyticsService = {
  /** GET /ems/analytics/dashboard */
  dashboard(filters: {
    event_uuid?: string;
    category_id?: number | null;
    start_date?: string;
    end_date?: string;
  } = {}): Promise<AnalyticsPayload> {
    const query = new URLSearchParams();
    if (filters.event_uuid) query.append('event_uuid', filters.event_uuid);
    if (filters.category_id) query.append('category_id', String(filters.category_id));
    if (filters.start_date) query.append('start_date', filters.start_date);
    if (filters.end_date) query.append('end_date', filters.end_date);

    const queryString = query.toString();
    return emsHttp.get<AnalyticsPayload>(`/analytics/dashboard${queryString ? '?' + queryString : ''}`);
  },

  /** GET /ems/events/{uuid}/analytics */
  eventAnalytics(uuid: string): Promise<AnalyticsPayload> {
    return emsHttp.get<AnalyticsPayload>(`/events/${uuid}/analytics`);
  },

  /** GET /ems/events/{uuid}/attendance */
  eventAttendance(uuid: string): Promise<Record<string, number>> {
    return emsHttp.get<Record<string, number>>(`/events/${uuid}/attendance`);
  },

  /** GET /ems/events/{uuid}/revenue */
  eventRevenue(uuid: string): Promise<Record<string, unknown>> {
    return emsHttp.get<Record<string, unknown>>(`/events/${uuid}/revenue`);
  },

  /** GET /ems/analytics/compare */
  compare(eventUuids: string[]): Promise<EventComparisonItem[]> {
    const query = new URLSearchParams();
    eventUuids.forEach((id) => query.append('event_uuids[]', id));
    return emsHttp.get<EventComparisonItem[]>(`/analytics/compare?${query.toString()}`);
  },

  /** GET /ems/events/{uuid}/reports */
  eventReports(uuid: string): Promise<AnalyticsReportItem[]> {
    return emsHttp.get<AnalyticsReportItem[]>(`/events/${uuid}/reports`);
  },

  /** POST /ems/events/{uuid}/reports/export */
  exportReport(uuid: string, payload: ExportReportPayload): Promise<AnalyticsReportItem> {
    return emsHttp.post<AnalyticsReportItem>(`/events/${uuid}/reports/export`, payload);
  },

  /** GET /ems/analytics/advanced-report */
  advancedReport(filters: {
    event_uuid?: string;
    category_id?: number | null;
    start_date?: string;
    end_date?: string;
    organizer_id?: number | null;
    series_id?: number | null;
  } = {}): Promise<any> {
    const query = new URLSearchParams();
    if (filters.event_uuid) query.append('event_uuid', filters.event_uuid);
    if (filters.category_id) query.append('category_id', String(filters.category_id));
    if (filters.start_date) query.append('start_date', filters.start_date);
    if (filters.end_date) query.append('end_date', filters.end_date);
    if (filters.organizer_id) query.append('organizer_id', String(filters.organizer_id));
    if (filters.series_id) query.append('series_id', String(filters.series_id));

    const queryString = query.toString();
    return emsHttp.get<any>(`/analytics/advanced-report${queryString ? '?' + queryString : ''}`);
  },

  /** Returns absolute URL for report downloading */
  getDownloadUrl(uuid: string): string {
    const apiBase = (window as any).EMS_CONFIG?.API_BASE_URL || '/api/v1/ems';
    return `${apiBase}/reports/${uuid}/download`;
  },
};
