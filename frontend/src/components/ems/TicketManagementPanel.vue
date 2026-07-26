<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { useToastStore } from '@/components/feedback/toast';
import { useEmsApiError } from '@/composables/ems/useEmsApiError';
import { useEmsPermissions } from '@/composables/ems/useEmsPermissions';
import ticketTypesService from '@/services/ems/ticketTypesService';
import type { EventPaymentSummary, TicketType } from '@/types/ems/ticketing';

const props = defineProps<{
  eventUuid: string;
}>();

const toast = useToastStore();
const { handle } = useEmsApiError();
const { canUpdateEvents } = useEmsPermissions();

const ticketTypes = ref<TicketType[]>([]);
const summary = ref<EventPaymentSummary | null>(null);
const loading = ref(true);
const saving = ref(false);
const editingUuid = ref<string | null>(null);
const showForm = ref(false);

const form = reactive({
  name: '',
  description: '',
  price: 0,
  currency: 'CAD',
  quantity: '' as string | number,
  max_per_order: '' as string | number,
  is_active: true,
  is_visible: true,
});

const canManage = computed(() => canUpdateEvents.value);

async function load() {
  loading.value = true;
  try {
    const [types, paymentSummary] = await Promise.all([
      ticketTypesService.list(props.eventUuid),
      ticketTypesService.paymentSummary(props.eventUuid),
    ]);
    ticketTypes.value = types;
    summary.value = paymentSummary;
  } catch (caught) {
    handle(caught);
  } finally {
    loading.value = false;
  }
}

onMounted(load);
watch(() => props.eventUuid, load);

function resetForm() {
  editingUuid.value = null;
  showForm.value = false;
  form.name = '';
  form.description = '';
  form.price = 0;
  form.currency = 'CAD';
  form.quantity = '';
  form.max_per_order = '';
  form.is_active = true;
  form.is_visible = true;
}

function startCreate() {
  resetForm();
  showForm.value = true;
}

function startEdit(ticket: TicketType) {
  editingUuid.value = ticket.uuid;
  showForm.value = true;
  form.name = ticket.name;
  form.description = ticket.description ?? '';
  form.price = ticket.price;
  form.currency = ticket.currency;
  form.quantity = ticket.quantity ?? '';
  form.max_per_order = ticket.max_per_order ?? '';
  form.is_active = ticket.is_active;
  form.is_visible = ticket.is_visible;
}

function payload() {
  return {
    name: form.name.trim(),
    description: form.description.trim() || null,
    price: Number(form.price),
    currency: form.currency || 'CAD',
    quantity: form.quantity === '' ? null : Number(form.quantity),
    max_per_order: form.max_per_order === '' ? null : Number(form.max_per_order),
    is_active: form.is_active,
    is_visible: form.is_visible,
  };
}

async function save() {
  if (!canManage.value) return;
  saving.value = true;
  try {
    if (editingUuid.value) {
      await ticketTypesService.update(props.eventUuid, editingUuid.value, payload());
      toast.success('Ticket type updated.');
    } else {
      await ticketTypesService.create(props.eventUuid, payload());
      toast.success('Ticket type created.');
    }
    resetForm();
    await load();
  } catch (caught) {
    handle(caught);
  } finally {
    saving.value = false;
  }
}

async function disable(ticket: TicketType) {
  try {
    await ticketTypesService.disable(props.eventUuid, ticket.uuid);
    toast.success('Ticket type disabled.');
    await load();
  } catch (caught) {
    handle(caught);
  }
}

async function duplicate(ticket: TicketType) {
  try {
    await ticketTypesService.duplicate(props.eventUuid, ticket.uuid);
    toast.success('Ticket type duplicated.');
    await load();
  } catch (caught) {
    handle(caught);
  }
}

async function remove(ticket: TicketType) {
  if (!window.confirm(`Delete ${ticket.name}?`)) return;
  try {
    await ticketTypesService.remove(props.eventUuid, ticket.uuid);
    toast.success('Ticket type deleted.');
    await load();
  } catch (caught) {
    handle(caught);
  }
}

function formatMoney(amount: number, currency: string) {
  if (amount === 0) return 'Free';
  return new Intl.NumberFormat('en-CA', { style: 'currency', currency }).format(amount);
}
</script>

<template>
  <section class="space-y-6" aria-labelledby="ems-tickets-heading">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 id="ems-tickets-heading" class="text-sm font-bold uppercase tracking-wider text-neutral-muted">
          Tickets & payments
        </h2>
        <p class="mt-1 text-sm text-neutral-black/70">
          Manage ticket types, capacity and a lightweight payment summary.
        </p>
      </div>
      <Button v-if="canManage" size="sm" @click="startCreate">Add ticket type</Button>
    </div>

    <div v-if="summary" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-xl border border-neutral-ivory bg-white p-4">
        <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Revenue</p>
        <p class="mt-1 text-lg font-semibold">{{ formatMoney(summary.revenue, summary.currency) }}</p>
      </div>
      <div class="rounded-xl border border-neutral-ivory bg-white p-4">
        <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Paid orders</p>
        <p class="mt-1 text-lg font-semibold">{{ summary.paid_orders }}</p>
      </div>
      <div class="rounded-xl border border-neutral-ivory bg-white p-4">
        <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Pending</p>
        <p class="mt-1 text-lg font-semibold">{{ summary.pending_payments }}</p>
      </div>
      <div class="rounded-xl border border-neutral-ivory bg-white p-4">
        <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Waitlist</p>
        <p class="mt-1 text-lg font-semibold">{{ summary.waitlist_count }}</p>
      </div>
    </div>

    <div v-if="loading" class="h-32 animate-pulse rounded-2xl bg-neutral-ivory/50" />

    <div v-else class="overflow-x-auto rounded-2xl border border-neutral-ivory bg-white">
      <table class="min-w-full text-left text-sm">
        <thead class="border-b border-neutral-ivory text-[11px] uppercase tracking-wider text-neutral-muted">
          <tr>
            <th class="px-4 py-3 font-bold">Ticket</th>
            <th class="px-4 py-3 font-bold">Price</th>
            <th class="px-4 py-3 font-bold">Sold</th>
            <th class="px-4 py-3 font-bold">Remaining</th>
            <th class="px-4 py-3 font-bold">Status</th>
            <th v-if="canManage" class="px-4 py-3 font-bold">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="ticketTypes.length === 0">
            <td colspan="6" class="px-4 py-8 text-center text-neutral-muted">
              No ticket types yet. Add free or paid tiers for this event.
            </td>
          </tr>
          <tr
            v-for="ticket in ticketTypes"
            :key="ticket.uuid"
            class="border-b border-neutral-ivory/70 last:border-0"
          >
            <td class="px-4 py-3">
              <p class="font-semibold text-neutral-black">{{ ticket.name }}</p>
              <p v-if="ticket.description" class="text-xs text-neutral-muted">{{ ticket.description }}</p>
            </td>
            <td class="px-4 py-3">{{ formatMoney(ticket.price, ticket.currency) }}</td>
            <td class="px-4 py-3">{{ ticket.quantity_sold }}</td>
            <td class="px-4 py-3">
              {{ ticket.remaining_quantity === null ? 'Unlimited' : ticket.remaining_quantity }}
            </td>
            <td class="px-4 py-3">
              <span v-if="ticket.is_sold_out" class="text-amber-700">Sold out</span>
              <span v-else-if="!ticket.is_active" class="text-neutral-muted">Disabled</span>
              <span v-else class="text-emerald-700">Active</span>
            </td>
            <td v-if="canManage" class="px-4 py-3">
              <div class="flex flex-wrap gap-2">
                <button type="button" class="text-xs font-semibold text-primary" @click="startEdit(ticket)">
                  Edit
                </button>
                <button type="button" class="text-xs font-semibold text-neutral-muted" @click="duplicate(ticket)">
                  Duplicate
                </button>
                <button
                  v-if="ticket.is_active"
                  type="button"
                  class="text-xs font-semibold text-amber-700"
                  @click="disable(ticket)"
                >
                  Disable
                </button>
                <button type="button" class="text-xs font-semibold text-red-600" @click="remove(ticket)">
                  Delete
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <form
      v-if="showForm && canManage"
      class="space-y-4 rounded-2xl border border-neutral-ivory bg-white p-5"
      @submit.prevent="save"
    >
      <h3 class="text-sm font-bold">{{ editingUuid ? 'Edit ticket type' : 'New ticket type' }}</h3>
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="block text-sm">
          <span class="mb-1 block text-xs font-bold uppercase tracking-wider text-neutral-muted">Name</span>
          <input v-model="form.name" required class="w-full rounded-lg border border-neutral-ivory px-3 py-2" />
        </label>
        <label class="block text-sm">
          <span class="mb-1 block text-xs font-bold uppercase tracking-wider text-neutral-muted">Price</span>
          <input
            v-model.number="form.price"
            type="number"
            min="0"
            step="0.01"
            required
            class="w-full rounded-lg border border-neutral-ivory px-3 py-2"
          />
        </label>
        <label class="block text-sm sm:col-span-2">
          <span class="mb-1 block text-xs font-bold uppercase tracking-wider text-neutral-muted">Description</span>
          <input v-model="form.description" class="w-full rounded-lg border border-neutral-ivory px-3 py-2" />
        </label>
        <label class="block text-sm">
          <span class="mb-1 block text-xs font-bold uppercase tracking-wider text-neutral-muted">Quantity (blank = unlimited)</span>
          <input v-model="form.quantity" type="number" min="0" class="w-full rounded-lg border border-neutral-ivory px-3 py-2" />
        </label>
        <label class="block text-sm">
          <span class="mb-1 block text-xs font-bold uppercase tracking-wider text-neutral-muted">Max per order</span>
          <input v-model="form.max_per_order" type="number" min="1" class="w-full rounded-lg border border-neutral-ivory px-3 py-2" />
        </label>
      </div>
      <div class="flex flex-wrap gap-4 text-sm">
        <label class="inline-flex items-center gap-2">
          <input v-model="form.is_active" type="checkbox" /> Active
        </label>
        <label class="inline-flex items-center gap-2">
          <input v-model="form.is_visible" type="checkbox" /> Visible publicly
        </label>
      </div>
      <div class="flex gap-2">
        <Button type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save' }}</Button>
        <Button type="button" variant="ghost" @click="resetForm">Cancel</Button>
      </div>
    </form>
  </section>
</template>
