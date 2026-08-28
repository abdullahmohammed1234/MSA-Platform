<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { useToastStore } from '@/components/feedback/toast';
import { EmsErrorState, EmsPageHeader, EmsSummaryCard, EmsStatusBadge } from '@/components/ems';
import { notificationsService } from '@/services/ems/notificationsService';
import { useEmsEventsStore } from '@/stores/ems/emsEvents';
import { useEmsPermissions } from '@/composables/ems/useEmsPermissions';
import { useEmsApiError } from '@/composables/ems/useEmsApiError';
import type {
  EmsEventNotification,
  EmsEventReminder,
  EmsNotificationSummary,
  ReminderPayload,
} from '@/types/ems/notifications';
import type { EventStatusTone } from '@/types/ems';

const route = useRoute();
const toast = useToastStore();
const events = useEmsEventsStore();
const { canSendNotifications, canManageNotifications } = useEmsPermissions();
const { handle } = useEmsApiError();

const uuid = computed(() => route.params.uuid as string);
const tab = ref<'history' | 'reminders'>('history');
const summary = ref<EmsNotificationSummary | null>(null);
const notifications = ref<EmsEventNotification[]>([]);
const reminders = ref<EmsEventReminder[]>([]);
const error = ref<string | null>(null);
const isLoading = ref(true);
const statusFilter = ref('');
const typeFilter = ref('');
const search = ref('');

const resendType = ref('registration_confirmed');
const resendRegistrationUuid = ref('');
const isResending = ref(false);

const reminderForm = ref<ReminderPayload>({
  label: '',
  offset_value: 1,
  offset_unit: 'days',
  enabled: true,
  audience: 'confirmed',
  template_key: 'event_reminder',
});
const isSavingReminder = ref(false);

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

const resendTypeOptions = computed(() =>
  (summary.value?.types.filter((t) => t.resendable) ?? []).map((t) => ({
    value: t.value,
    label: t.label,
  }))
);

const statusOptions = [
  { value: '', label: 'All statuses' },
  { value: 'pending', label: 'Pending' },
  { value: 'scheduled', label: 'Scheduled' },
  { value: 'sent', label: 'Sent' },
  { value: 'failed', label: 'Failed' },
  { value: 'cancelled', label: 'Cancelled' },
];

const typeOptions = computed(() => [
  { value: '', label: 'All types' },
  ...(summary.value?.types ?? []).map((t) => ({ value: t.value, label: t.label })),
]);

const unitOptions = [
  { value: 'minutes', label: 'Minutes' },
  { value: 'hours', label: 'Hours' },
  { value: 'days', label: 'Days' },
];

const audienceOptions = [
  { value: 'confirmed', label: 'Confirmed' },
  { value: 'ticket_holders', label: 'Ticket holders' },
  { value: 'all', label: 'All registrants' },
];

const load = async () => {
  try {
    error.value = null;
    if (!events.current || events.current.uuid !== uuid.value) {
      await events.fetchOne(uuid.value);
    }
    summary.value = await notificationsService.getSummary(uuid.value);
    const result = await notificationsService.list(uuid.value, {
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

    reminders.value = await notificationsService.listReminders(uuid.value);
  } catch (caught) {
    const err = handle(caught, { silent: true });
    error.value = err.message;
  } finally {
    isLoading.value = false;
  }
};

const resend = async () => {
  if (!resendRegistrationUuid.value) {
    toast.error('Enter a registration UUID to resend.');
    return;
  }
  isResending.value = true;
  try {
    await notificationsService.resend(uuid.value, {
      type: resendType.value,
      registration_uuid: resendRegistrationUuid.value.trim(),
    });
    toast.success('Notification queued for resend.');
    await load();
  } catch (caught) {
    handle(caught);
  } finally {
    isResending.value = false;
  }
};

const retry = async (notification: EmsEventNotification) => {
  try {
    await notificationsService.retry(uuid.value, notification.uuid);
    toast.success('Retry queued.');
    await load();
  } catch (caught) {
    handle(caught);
  }
};

const createReminder = async () => {
  isSavingReminder.value = true;
  try {
    await notificationsService.createReminder(uuid.value, {
      ...reminderForm.value,
      label: reminderForm.value.label || null,
    });
    toast.success('Reminder created.');
    reminderForm.value.label = '';
    await load();
  } catch (caught) {
    handle(caught);
  } finally {
    isSavingReminder.value = false;
  }
};

const toggleReminder = async (reminder: EmsEventReminder) => {
  try {
    await notificationsService.updateReminder(uuid.value, reminder.uuid, {
      enabled: !reminder.enabled,
    });
    toast.success(reminder.enabled ? 'Reminder disabled.' : 'Reminder enabled.');
    await load();
  } catch (caught) {
    handle(caught);
  }
};

const removeReminder = async (reminder: EmsEventReminder) => {
  try {
    await notificationsService.deleteReminder(uuid.value, reminder.uuid);
    toast.success('Reminder deleted.');
    await load();
  } catch (caught) {
    handle(caught);
  }
};

onMounted(load);
watch(uuid, () => {
  page.value = 1;
  void load();
});
watch([statusFilter, typeFilter], () => {
  page.value = 1;
  void load();
});
</script>

<template>
  <div>
    <EmsPageHeader
      title="Communications"
      :description="events.current?.name"
      :back-to="`/ems/events/${uuid}`"
      back-label="Event detail"
    >
      <template #actions>
        <div class="flex gap-2">
          <Button :variant="tab === 'history' ? 'primary' : 'outline'" @click="tab = 'history'">
            History
          </Button>
          <Button :variant="tab === 'reminders' ? 'primary' : 'outline'" @click="tab = 'reminders'">
            Reminders
          </Button>
        </div>
      </template>
    </EmsPageHeader>

    <div v-if="isLoading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div v-for="n in 4" :key="n" class="h-24 animate-pulse rounded-2xl bg-neutral-ivory/50" />
    </div>

    <EmsErrorState
      v-else-if="error"
      title="Unable to load notifications"
      :message="error"
      can-retry
      @retry="load"
    />

    <template v-else-if="summary">
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <EmsSummaryCard label="Total" :value="summary.total" />
        <EmsSummaryCard label="Queued" :value="summary.queued" />
        <EmsSummaryCard label="Sent" :value="summary.sent" />
        <EmsSummaryCard label="Failed" :value="summary.failed" />
        <EmsSummaryCard label="Pending reminders" :value="summary.pending_reminders" />
      </div>

      <section v-if="tab === 'history'" class="mt-8 space-y-6">
        <div
          v-if="canSendNotifications"
          class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft"
        >
          <h2 class="text-sm font-bold text-primary">Manual resend</h2>
          <p class="mt-1 text-sm text-neutral-muted">
            Queue a transactional email for a specific registration. Delivery is asynchronous.
          </p>
          <div class="mt-4 grid gap-3 md:grid-cols-3">
            <Select v-model="resendType" :options="resendTypeOptions" label="Type" />
            <Input v-model="resendRegistrationUuid" label="Registration UUID" placeholder="Registration UUID" />
            <div class="flex items-end">
              <Button class="w-full" :disabled="isResending" @click="resend">
                {{ isResending ? 'Queueing…' : 'Resend' }}
              </Button>
            </div>
          </div>
        </div>

        <div class="grid gap-3 md:grid-cols-4">
          <Input
            v-model="search"
            label="Search"
            placeholder="Search email or subject"
            @keyup.enter="load"
          />
          <Select v-model="statusFilter" :options="statusOptions" label="Status" />
          <Select v-model="typeFilter" :options="typeOptions" label="Type" />
          <div class="flex items-end">
            <Button variant="outline" class="w-full" @click="load">Apply</Button>
          </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-neutral-ivory bg-white shadow-soft">
          <table class="min-w-full text-left text-sm">
            <thead class="border-b border-neutral-ivory text-[11px] uppercase tracking-wider text-neutral-muted">
              <tr>
                <th class="px-4 py-3">Recipient</th>
                <th class="px-4 py-3">Type</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Subject</th>
                <th class="px-4 py-3">Sent</th>
                <th class="px-4 py-3" />
              </tr>
            </thead>
            <tbody>
              <tr v-if="notifications.length === 0">
                <td colspan="6" class="px-4 py-10 text-center text-neutral-muted">
                  No notifications yet.
                </td>
              </tr>
               <tr
                v-for="row in notifications"
                :key="row.uuid"
                class="border-b border-neutral-ivory/70 last:border-0"
              >
                <td class="px-4 py-3">
                  <p class="font-medium text-primary">{{ row.recipient_email }}</p>
                  <p class="text-xs text-neutral-muted">
                    {{ row.registration?.reference || row.registration?.attendee_name || '—' }}
                  </p>
                  <p v-if="row.provider_message_id" class="mt-1 text-[10px] text-neutral-muted font-mono truncate" :title="row.provider_message_id">
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
                    Retry attempts: {{ row.retry_count }}
                  </div>
                  <div v-if="row.alert_sent_at" class="text-[10px] text-red-600 font-medium">
                    Admin alerted: Yes
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
      </section>

      <section v-else class="mt-8 space-y-6">
        <div
          v-if="canManageNotifications"
          class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft"
        >
          <h2 class="text-sm font-bold text-primary">Add reminder</h2>
          <p class="mt-1 text-sm text-neutral-muted">
            Offsets are relative to the event start time. Defaults are seeded when registration opens.
          </p>
          <div class="mt-4 grid gap-3 md:grid-cols-5">
            <Input
              :model-value="reminderForm.label || ''"
              @update:model-value="reminderForm.label = String($event)"
              label="Label"
              placeholder="Optional label"
            />
            <Input
              v-model.number="reminderForm.offset_value"
              type="number"
              min="1"
              label="Offset"
            />
            <Select v-model="reminderForm.offset_unit" :options="unitOptions" label="Unit" />
            <Select
              :model-value="reminderForm.audience || 'confirmed'"
              @update:model-value="reminderForm.audience = $event as any"
              :options="audienceOptions"
              label="Audience"
            />
            <div class="flex items-end">
              <Button class="w-full" :disabled="isSavingReminder" @click="createReminder">
                {{ isSavingReminder ? 'Saving…' : 'Add' }}
              </Button>
            </div>
          </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-neutral-ivory bg-white shadow-soft">
          <table class="min-w-full text-left text-sm">
            <thead class="border-b border-neutral-ivory text-[11px] uppercase tracking-wider text-neutral-muted">
              <tr>
                <th class="px-4 py-3">Label</th>
                <th class="px-4 py-3">Offset</th>
                <th class="px-4 py-3">Audience</th>
                <th class="px-4 py-3">Next run</th>
                <th class="px-4 py-3">Enabled</th>
                <th class="px-4 py-3" />
              </tr>
            </thead>
            <tbody>
              <tr v-if="reminders.length === 0">
                <td colspan="6" class="px-4 py-10 text-center text-neutral-muted">
                  No reminders configured. Open registration to seed defaults, or add one above.
                </td>
              </tr>
              <tr
                v-for="reminder in reminders"
                :key="reminder.uuid"
                class="border-b border-neutral-ivory/70 last:border-0"
              >
                <td class="px-4 py-3 font-medium text-primary">{{ reminder.label }}</td>
                <td class="px-4 py-3 text-neutral-muted">
                  {{ reminder.offset_value }} {{ reminder.offset_unit }}
                </td>
                <td class="px-4 py-3 text-neutral-muted">{{ reminder.audience }}</td>
                <td class="px-4 py-3 text-xs text-neutral-muted">
                  {{ reminder.next_run_at ? new Date(reminder.next_run_at).toLocaleString() : '—' }}
                </td>
                <td class="px-4 py-3">
                  <EmsStatusBadge
                    :label="reminder.enabled ? 'Enabled' : 'Disabled'"
                    :tone="reminder.enabled ? 'success' : 'muted'"
                  />
                </td>
                <td class="px-4 py-3 text-right space-x-2">
                  <Button
                    v-if="canManageNotifications"
                    size="sm"
                    variant="outline"
                    @click="toggleReminder(reminder)"
                  >
                    {{ reminder.enabled ? 'Disable' : 'Enable' }}
                  </Button>
                  <Button
                    v-if="canManageNotifications"
                    size="sm"
                    variant="outline"
                    @click="removeReminder(reminder)"
                  >
                    Delete
                  </Button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>
