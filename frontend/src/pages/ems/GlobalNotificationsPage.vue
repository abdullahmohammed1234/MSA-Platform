<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { useToastStore } from '@/components/feedback/toast';
import { EmsErrorState, EmsPageHeader, EmsStatusBadge } from '@/components/ems';
import { notificationsService } from '@/services/ems/notificationsService';
import { useEmsPermissions } from '@/composables/ems/useEmsPermissions';
import { useEmsApiError } from '@/composables/ems/useEmsApiError';
import type { EmsEventNotification } from '@/types/ems/notifications';
import type { EventStatusTone } from '@/types/ems';

const toast = useToastStore();
const { canSendNotifications } = useEmsPermissions();
const { handle } = useEmsApiError();

const notifications = ref<EmsEventNotification[]>([]);
const error = ref<string | null>(null);
const isLoading = ref(true);
const statusFilter = ref('');
const typeFilter = ref('');
const search = ref('');

const page = ref(1);
const lastPage = ref(1);
const total = ref(0);

const toneForStatus = (status: string): EventStatusTone => {
  if (status === 'sent') return 'success';
  if (status === 'failed') return 'danger';
  if (status === 'cancelled') return 'muted';
  if (status === 'scheduled') return 'info';
  return 'warning';
};

const statusOptions = [
  { value: '', label: 'All statuses' },
  { value: 'pending', label: 'Pending' },
  { value: 'scheduled', label: 'Scheduled' },
  { value: 'sent', label: 'Sent' },
  { value: 'failed', label: 'Failed' },
  { value: 'cancelled', label: 'Cancelled' },
];

const typeOptions = [
  { value: '', label: 'All types' },
  { value: 'registration_confirmed', label: 'Registration Confirmation' },
  { value: 'payment_confirmation', label: 'Payment Confirmation' },
  { value: 'payment_failed', label: 'Payment Failed' },
  { value: 'waitlist_entry', label: 'Waitlist Entry' },
  { value: 'cancellation', label: 'Cancellation' },
  { value: 'event_reminder', label: 'Event Reminder' },
];

const load = async () => {
  try {
    error.value = null;
    isLoading.value = true;
    const result = await notificationsService.listAll({
      status: statusFilter.value || undefined,
      type: typeFilter.value || undefined,
      search: search.value || undefined,
      page: page.value,
      per_page: 25,
    });
    notifications.value = result.items;
    page.value = result.pagination.current_page;
    lastPage.value = result.pagination.last_page;
    total.value = result.pagination.total;
  } catch (caught) {
    const err = handle(caught, { silent: true });
    error.value = err.message;
  } finally {
    isLoading.value = false;
  }
};

const retry = async (notification: EmsEventNotification) => {
  try {
    await notificationsService.retryGlobal(notification.uuid);
    toast.success('Retry queued successfully.');
    await load();
  } catch (caught) {
    handle(caught);
  }
};

onMounted(load);

watch([statusFilter, typeFilter], () => {
  page.value = 1;
  void load();
});
</script>

<template>
  <div>
    <EmsPageHeader
      title="Global Communications"
      description="Unified ledger tracking notifications and delivery status across all events."
    />

    <EmsErrorState
      v-if="error"
      title="Unable to load notifications"
      :message="error"
      can-retry
      @retry="load"
    />

    <template v-else>
      <div class="mb-6 grid gap-4 sm:grid-cols-2 md:grid-cols-4">
        <Input
          v-model="search"
          label="Search"
          placeholder="Search email or subject"
          @keyup.enter="load"
        />
        <Select v-model="statusFilter" :options="statusOptions" label="Status" />
        <Select v-model="typeFilter" :options="typeOptions" label="Type" />
        <div class="flex items-end">
          <Button variant="outline" class="w-full" @click="load">Apply Filters</Button>
        </div>
      </div>

      <div class="overflow-hidden rounded-2xl border border-neutral-ivory bg-white shadow-soft">
        <table class="min-w-full text-left text-sm" aria-label="Notifications history ledger">
          <thead class="border-b border-neutral-ivory text-[11px] uppercase tracking-wider text-neutral-muted">
            <tr>
              <th class="px-4 py-3">Recipient & Event</th>
              <th class="px-4 py-3">Type</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3">Subject</th>
              <th class="px-4 py-3">Sent / Attempted</th>
              <th class="px-4 py-3" />
            </tr>
          </thead>
          <tbody>
            <tr v-if="isLoading">
              <td colspan="6" class="px-4 py-10 text-center text-neutral-muted animate-pulse">
                Loading communications history...
              </td>
            </tr>
            <tr v-else-if="notifications.length === 0">
              <td colspan="6" class="px-4 py-10 text-center text-neutral-muted">
                No notifications found.
              </td>
            </tr>
            <tr
              v-else
              v-for="row in notifications"
              :key="row.uuid"
              class="border-b border-neutral-ivory/70 last:border-0"
            >
              <td class="px-4 py-3">
                <p class="font-medium text-primary">{{ row.recipient_email }}</p>
                <p class="text-xs text-neutral-muted">
                  Event: {{ row.event?.name || '—' }}
                </p>
                <p v-if="row.provider_message_id" class="mt-1 text-[10px] text-neutral-muted font-mono truncate max-w-[200px]" :title="row.provider_message_id">
                  Msg ID: {{ row.provider_message_id }}
                </p>
                <p v-if="row.error" class="mt-1 text-[11px] text-red-600 max-w-[200px] truncate" :title="row.error">
                  Error: {{ row.error }}
                </p>
              </td>
              <td class="px-4 py-3 text-neutral-muted">{{ row.type }}</td>
              <td class="px-4 py-3">
                <EmsStatusBadge :label="row.status" :tone="toneForStatus(row.status)" />
                <div v-if="row.queue_status" class="mt-1 text-[10px] text-neutral-muted uppercase tracking-wider">
                  Queue: {{ row.queue_status }}
                </div>
              </td>
              <td class="px-4 py-3 max-w-[220px] truncate">{{ row.subject }}</td>
              <td class="px-4 py-3 text-xs text-neutral-muted">
                <div>{{ row.sent_at ? new Date(row.sent_at).toLocaleString() : (row.last_attempt_at ? new Date(row.last_attempt_at).toLocaleString() : '—') }}</div>
                <div v-if="row.retry_count > 0" class="text-[10px] text-neutral-muted">
                  Retries: {{ row.retry_count }}
                </div>
              </td>
              <td class="px-4 py-3 text-right">
                <Button
                  v-if="canSendNotifications && row.status === 'failed'"
                  size="sm"
                  variant="outline"
                  @click="retry(row)"
                >
                  Retry
                </Button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="lastPage > 1" class="mt-4 flex items-center justify-between">
        <Button variant="outline" size="sm" :disabled="page <= 1" @click="page -= 1; load()">Previous</Button>
        <span class="text-xs text-neutral-muted">Page {{ page }} of {{ lastPage }}</span>
        <Button variant="outline" size="sm" :disabled="page >= lastPage" @click="page += 1; load()">Next</Button>
      </div>
    </template>
  </div>
</template>
