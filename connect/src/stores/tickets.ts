import { defineStore } from 'pinia';
import { ref } from 'vue';
import { emsService } from '@/services/ems.service';
import { useDashboardStore } from './dashboard';
import type { Registration } from '@/types/ems';
import type { ApiError } from '@/types/api';

export const useTicketsStore = defineStore('connect_tickets', () => {
  const registrations = ref<Registration[]>([]);
  const isLoading = ref<boolean>(false);
  const error = ref<string | null>(null);

  const isCancelling = ref<boolean>(false);
  const cancelError = ref<string | null>(null);

  /**
   * Fetch member's registered tickets.
   */
  async function fetchMyTickets(activeOnly = false): Promise<void> {
    isLoading.value = true;
    error.value = null;

    try {
      const response = await emsService.getMyTickets(activeOnly);
      registrations.value = response.data || [];
    } catch (err: unknown) {
      const apiErr = err as ApiError;
      error.value = apiErr.message || 'Unable to retrieve tickets.';
      registrations.value = [];
    } finally {
      isLoading.value = false;
    }
  }

  /**
   * Cancel an active registration by UUID.
   * On success, refreshes tickets and invalidates dashboard cache.
   */
  async function cancelRegistration(registrationUuid: string): Promise<boolean> {
    isCancelling.value = true;
    cancelError.value = null;

    try {
      const response = await emsService.cancelRegistration(registrationUuid);
      const updatedReg = response.data;

      // Update in-place in local list
      const index = registrations.value.findIndex((r) => r.uuid === registrationUuid);
      if (index !== -1 && updatedReg) {
        registrations.value[index] = {
          ...registrations.value[index],
          status: updatedReg.status,
          status_label: updatedReg.status_label,
          is_active: false,
        };
      } else {
        // Re-fetch to ensure sync
        await fetchMyTickets();
      }

      // Invalidate dashboard cache so upcoming spotlight reflects cancellation
      const dashboardStore = useDashboardStore();
      dashboardStore.invalidateCache();

      return true;
    } catch (err: unknown) {
      const apiErr = err as ApiError;
      cancelError.value = apiErr.message || 'Unable to cancel this reservation.';
      return false;
    } finally {
      isCancelling.value = false;
    }
  }

  /**
   * Clear state completely upon logout to prevent data leakage between users.
   */
  function clearState() {
    registrations.value = [];
    error.value = null;
    cancelError.value = null;
  }

  return {
    registrations,
    isLoading,
    error,
    isCancelling,
    cancelError,
    fetchMyTickets,
    cancelRegistration,
    clearState,
  };
});
