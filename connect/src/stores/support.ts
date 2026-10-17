import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { supportService } from '@/services/support.service';
import { resourcesService } from '@/services/resources.service';
import type {
  SupportCategory,
  SupportInquiry,
  CreateInquiryPayload,
  InquiryStatus,
  PaginatedInquiries,
  StaffSupportStats,
} from '@/types/support';

export const useSupportStore = defineStore('connect_support', () => {
  // State
  const categories = ref<SupportCategory[]>([]);
  const inquiries = ref<SupportInquiry[]>([]);
  const pagination = ref<PaginatedInquiries | null>(null);
  const activeInquiry = ref<SupportInquiry | null>(null);

  // Staff triage state
  const staffInquiries = ref<SupportInquiry[]>([]);
  const staffPagination = ref<PaginatedInquiries | null>(null);
  const staffStats = ref<StaffSupportStats | null>(null);

  // Status flags
  const isLoading = ref<boolean>(false);
  const isSending = ref<boolean>(false);
  const error = ref<string | null>(null);
  const resourcesEnabled = ref<boolean>(false);

  // Polling management
  let pollingIntervalId: ReturnType<typeof setInterval> | null = null;
  const isPollingActive = ref<boolean>(false);
  let activePollingReferenceCode: string | null = null;
  let consecutiveErrors = 0;
  const MAX_CONSECUTIVE_ERRORS = 5;

  // Computeds
  const memberUnreadCount = computed<number>(() => {
    return inquiries.value.reduce((acc, inq) => {
      const unread = inq.unread_member_messages_count ?? (!inq.is_read_by_member ? 1 : 0);
      return acc + (unread > 0 ? 1 : 0);
    }, 0);
  });

  const activeInquiryIsClosed = computed<boolean>(() => {
    return activeInquiry.value?.status === 'closed' || activeInquiry.value?.status === 'resolved';
  });

  /**
   * Fetch categories list.
   */
  async function fetchCategories(): Promise<void> {
    try {
      const rawCategories = await supportService.getCategories();
      categories.value = (rawCategories || []).map((cat: any) => ({
        value: cat.value || cat.key || '',
        label: cat.label || cat.name || cat.value || cat.key || '',
        description: cat.description || '',
      }));
    } catch (e: any) {
      console.warn('Failed to load support categories:', e);
    }
  }

  /**
   * Fetch paginated list of inquiries for the authenticated member.
   */
  async function fetchInquiries(page = 1, silent = false): Promise<void> {
    if (!silent) isLoading.value = true;
    error.value = null;
    try {
      const data = await supportService.getInquiries(page);
      inquiries.value = data.data;
      pagination.value = data;
      consecutiveErrors = 0;
    } catch (e: any) {
      consecutiveErrors++;
      if (!silent) {
        error.value = e.message || 'Unable to retrieve your inquiries.';
      }
    } finally {
      if (!silent) isLoading.value = false;
    }
  }

  /**
   * Fetch a single active inquiry thread.
   */
  async function fetchInquiry(referenceCode: string, silent = false): Promise<SupportInquiry | null> {
    if (!silent) isLoading.value = true;
    error.value = null;
    try {
      const inquiry = await supportService.getInquiry(referenceCode);
      activeInquiry.value = inquiry;

      // Update in inquiries list if present
      const index = inquiries.value.findIndex((i) => i.reference_code === referenceCode);
      if (index !== -1) {
        inquiries.value[index] = inquiry;
      }

      consecutiveErrors = 0;
      return inquiry;
    } catch (e: any) {
      consecutiveErrors++;
      if (!silent) {
        error.value = e.message || 'Unable to load inquiry conversation.';
      }
      return null;
    } finally {
      if (!silent) isLoading.value = false;
    }
  }

  /**
   * Create a new support inquiry.
   */
  async function createInquiry(payload: CreateInquiryPayload): Promise<SupportInquiry> {
    isSending.value = true;
    error.value = null;
    try {
      const created = await supportService.createInquiry(payload);
      inquiries.value.unshift(created);
      activeInquiry.value = created;
      return created;
    } catch (e: any) {
      error.value = e.message || 'Failed to submit inquiry.';
      throw e;
    } finally {
      isSending.value = false;
    }
  }

  /**
   * Send a member reply in an inquiry thread.
   */
  async function sendReply(referenceCode: string, message: string): Promise<SupportInquiry> {
    isSending.value = true;
    error.value = null;
    try {
      const updated = await supportService.sendReply(referenceCode, { message });
      activeInquiry.value = updated;

      const index = inquiries.value.findIndex((i) => i.reference_code === referenceCode);
      if (index !== -1) {
        inquiries.value[index] = updated;
      }

      return updated;
    } catch (e: any) {
      error.value = e.message || 'Failed to send message.';
      throw e;
    } finally {
      isSending.value = false;
    }
  }

  /**
   * Member voluntarily closes an inquiry.
   */
  async function closeInquiry(referenceCode: string): Promise<SupportInquiry> {
    isSending.value = true;
    error.value = null;
    try {
      const updated = await supportService.closeInquiry(referenceCode);
      activeInquiry.value = updated;

      const index = inquiries.value.findIndex((i) => i.reference_code === referenceCode);
      if (index !== -1) {
        inquiries.value[index] = updated;
      }

      return updated;
    } catch (e: any) {
      error.value = e.message || 'Failed to close inquiry.';
      throw e;
    } finally {
      isSending.value = false;
    }
  }

  /**
   * Check whether community resources are available.
   */
  async function checkResourcesEnabled(): Promise<boolean> {
    try {
      resourcesEnabled.value = await resourcesService.isEnabled();
      return resourcesEnabled.value;
    } catch {
      resourcesEnabled.value = false;
      return false;
    }
  }

  /* -------------------------------------------------------------
   * Staff Triage Methods
   * ------------------------------------------------------------- */

  async function fetchStaffInquiries(params: {
    status?: string;
    category?: string;
    search?: string;
    page?: number;
  } = {}, silent = false): Promise<void> {
    if (!silent) isLoading.value = true;
    error.value = null;
    try {
      const data = await supportService.getStaffInquiries(params);
      staffInquiries.value = data.data;
      staffPagination.value = data;
    } catch (e: any) {
      if (!silent) {
        error.value = e.message || 'Failed to load staff inbox.';
      }
    } finally {
      if (!silent) isLoading.value = false;
    }
  }

  async function fetchStaffInquiry(referenceCode: string, silent = false): Promise<SupportInquiry | null> {
    if (!silent) isLoading.value = true;
    error.value = null;
    try {
      const inquiry = await supportService.getStaffInquiry(referenceCode);
      activeInquiry.value = inquiry;
      return inquiry;
    } catch (e: any) {
      if (!silent) {
        error.value = e.message || 'Failed to load inquiry details.';
      }
      return null;
    } finally {
      if (!silent) isLoading.value = false;
    }
  }

  async function sendStaffReply(referenceCode: string, message: string): Promise<SupportInquiry> {
    isSending.value = true;
    error.value = null;
    try {
      const updated = await supportService.sendStaffReply(referenceCode, { message });
      activeInquiry.value = updated;
      return updated;
    } catch (e: any) {
      error.value = e.message || 'Failed to send staff response.';
      throw e;
    } finally {
      isSending.value = false;
    }
  }

  async function updateStaffStatus(referenceCode: string, status: InquiryStatus): Promise<SupportInquiry> {
    isLoading.value = true;
    error.value = null;
    try {
      const updated = await supportService.updateStaffInquiryStatus(referenceCode, status);
      activeInquiry.value = updated;
      const index = staffInquiries.value.findIndex((i) => i.reference_code === referenceCode);
      if (index !== -1) {
        staffInquiries.value[index] = updated;
      }
      return updated;
    } catch (e: any) {
      error.value = e.message || 'Failed to update status.';
      throw e;
    } finally {
      isLoading.value = false;
    }
  }

  async function assignStaff(referenceCode: string, staffId: number | null): Promise<SupportInquiry> {
    isLoading.value = true;
    error.value = null;
    try {
      const updated = await supportService.assignStaffInquiry(referenceCode, staffId);
      activeInquiry.value = updated;
      const index = staffInquiries.value.findIndex((i) => i.reference_code === referenceCode);
      if (index !== -1) {
        staffInquiries.value[index] = updated;
      }
      return updated;
    } catch (e: any) {
      error.value = e.message || 'Failed to assign inquiry.';
      throw e;
    } finally {
      isLoading.value = false;
    }
  }

  async function fetchStaffStats(): Promise<void> {
    try {
      staffStats.value = await supportService.getStaffStats();
    } catch (e: any) {
      console.warn('Failed to fetch staff stats:', e);
    }
  }

  /* -------------------------------------------------------------
   * Polling Lifecycle with Tab Visibility Listener
   * ------------------------------------------------------------- */

  function handleVisibilityChange() {
    if (typeof document === 'undefined') return;

    if (document.hidden) {
      // Tab backgrounded: pause polling
      if (pollingIntervalId) {
        clearInterval(pollingIntervalId);
        pollingIntervalId = null;
      }
    } else {
      // Tab resumed: run immediate sync and restore polling if active
      if (isPollingActive.value && !pollingIntervalId) {
        executePoll();
        pollingIntervalId = setInterval(executePoll, 7000);
      }
    }
  }

  async function executePoll() {
    if (consecutiveErrors >= MAX_CONSECUTIVE_ERRORS) {
      // Back off to prevent flooding failing endpoint
      stopPolling();
      return;
    }

    if (activePollingReferenceCode) {
      await fetchInquiry(activePollingReferenceCode, true);
    } else {
      await fetchInquiries(1, true);
    }
  }

  /**
   * Start polling active thread or inquiry hub every 7 seconds.
   * Automatically pauses when document is hidden and resumes when visible.
   */
  function startPolling(referenceCode?: string) {
    stopPolling();

    activePollingReferenceCode = referenceCode || null;
    isPollingActive.value = true;
    consecutiveErrors = 0;

    if (typeof window !== 'undefined') {
      document.addEventListener('visibilitychange', handleVisibilityChange);
    }

    // Schedule regular polling
    pollingIntervalId = setInterval(executePoll, 7000);
  }

  /**
   * Stop polling and remove visibility listener.
   */
  function stopPolling() {
    if (pollingIntervalId) {
      clearInterval(pollingIntervalId);
      pollingIntervalId = null;
    }
    isPollingActive.value = false;
    activePollingReferenceCode = null;

    if (typeof window !== 'undefined') {
      document.removeEventListener('visibilitychange', handleVisibilityChange);
    }
  }

  /**
   * Complete reset of support store to prevent cross-account leak on logout.
   */
  function clearState() {
    stopPolling();
    categories.value = [];
    inquiries.value = [];
    pagination.value = null;
    activeInquiry.value = null;
    staffInquiries.value = [];
    staffPagination.value = null;
    staffStats.value = null;
    isLoading.value = false;
    isSending.value = false;
    error.value = null;
    resourcesEnabled.value = false;
  }

  return {
    categories,
    inquiries,
    pagination,
    activeInquiry,
    staffInquiries,
    staffPagination,
    staffStats,
    isLoading,
    isSending,
    error,
    resourcesEnabled,
    isPollingActive,
    memberUnreadCount,
    activeInquiryIsClosed,
    fetchCategories,
    fetchInquiries,
    fetchInquiry,
    createInquiry,
    sendReply,
    closeInquiry,
    checkResourcesEnabled,
    fetchStaffInquiries,
    fetchStaffInquiry,
    sendStaffReply,
    updateStaffStatus,
    assignStaff,
    fetchStaffStats,
    startPolling,
    stopPolling,
    clearState,
  };
});
