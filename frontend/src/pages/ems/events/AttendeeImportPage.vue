<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import * as XLSX from 'xlsx';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { EmsPageHeader } from '@/components/ems';
import { operationsService } from '@/services/ems/operationsService';
import { useEmsApiError } from '@/composables/ems/useEmsApiError';
import { useToastStore } from '@/components/feedback/toast';
import type { EmsImportPreview } from '@/types/ems/operations';

const route = useRoute();
const router = useRouter();
const { handle } = useEmsApiError();
const toast = useToastStore();

const uuid = computed(() => route.params.uuid as string);
const step = ref<'upload' | 'map' | 'preview' | 'done'>('upload');
const file = ref<File | null>(null);
const headers = ref<string[]>([]);
const mapping = ref<Record<string, string | null>>({
  name: null,
  email: null,
  phone: null,
  ticket_type: null,
  member_status: null,
  registration_status: null,
  payment_status: null,
});
const mappingName = ref('');
const preview = ref<EmsImportPreview | null>(null);
const busy = ref(false);

const fields = [
  { key: 'name', label: 'Attendee Name', required: true },
  { key: 'email', label: 'Email', required: true },
  { key: 'phone', label: 'Phone' },
  { key: 'ticket_type', label: 'Ticket Type' },
  { key: 'member_status', label: 'Member Status' },
  { key: 'registration_status', label: 'Registration Status' },
  { key: 'payment_status', label: 'Payment Status' },
] as const;

const headerOptions = computed(() => [
  { value: '', label: '— Not mapped —' },
  ...headers.value.map((h) => ({ value: h, label: h })),
]);

const guessMapping = (cols: string[]) => {
  const lower = Object.fromEntries(cols.map((c) => [c.toLowerCase(), c]));
  const pick = (...names: string[]) => {
    for (const name of names) {
      if (lower[name]) return lower[name];
    }
    return null;
  };
  mapping.value = {
    name: pick('full name', 'name', 'attendee name'),
    email: pick('email', 'e-mail', 'email address'),
    phone: pick('phone', 'phone number', 'mobile'),
    ticket_type: pick('ticket type', 'ticket', 'type'),
    member_status: pick('member', 'member status', 'is member'),
    registration_status: pick('registration status', 'status'),
    payment_status: pick('payment status', 'payment'),
  };
};

const onFile = async (event: Event) => {
  const input = event.target as HTMLInputElement;
  const chosen = input.files?.[0];
  if (!chosen) return;
  file.value = chosen;

  try {
    const buffer = await chosen.arrayBuffer();
    const workbook = XLSX.read(buffer, { type: 'array' });
    const sheet = workbook.Sheets[workbook.SheetNames[0]];
    const rows = XLSX.utils.sheet_to_json<Record<string, unknown>>(sheet, { defval: '' });
    headers.value = rows[0] ? Object.keys(rows[0]) : [];
    guessMapping(headers.value);
    step.value = 'map';
  } catch (error) {
    handle(error);
  }
};

const runPreview = async () => {
  if (!file.value) return;
  busy.value = true;
  try {
    preview.value = await operationsService.previewImport(uuid.value, file.value, mapping.value);
    step.value = 'preview';
  } catch (error) {
    handle(error);
  } finally {
    busy.value = false;
  }
};

const commit = async () => {
  if (!preview.value) return;
  busy.value = true;
  try {
    await operationsService.commitImport(uuid.value, preview.value.import_uuid);
    toast.success('Attendees imported.');
    step.value = 'done';
  } catch (error) {
    handle(error);
  } finally {
    busy.value = false;
  }
};

const saveMapping = async () => {
  if (!mappingName.value.trim()) return;
  try {
    await operationsService.saveMapping(uuid.value, mappingName.value.trim(), mapping.value);
    toast.success('Column mapping saved.');
  } catch (error) {
    handle(error);
  }
};

const downloadReport = () => {
  if (!preview.value) return;
  const rows = [
    ...preview.value.invalid_rows.map((r) => ({ ...r, kind: 'invalid' })),
    ...preview.value.duplicate_rows.map((r) => ({ ...r, kind: 'duplicate' })),
  ];
  const sheet = XLSX.utils.json_to_sheet(rows);
  const book = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(book, sheet, 'Validation');
  XLSX.writeFile(book, 'import-validation-report.csv');
};
</script>

<template>
  <div>
    <EmsPageHeader
      title="Import attendees"
      description="Upload Excel or CSV, map columns, preview, then import"
      :back-to="`/ems/events/${uuid}/attendees`"
      back-label="Attendees"
    />

    <ol class="mb-6 flex flex-wrap gap-2 text-xs font-medium uppercase tracking-wider text-neutral-muted">
      <li :class="step === 'upload' ? 'text-primary' : ''">1. Upload</li>
      <li :class="step === 'map' ? 'text-primary' : ''">2. Map</li>
      <li :class="step === 'preview' ? 'text-primary' : ''">3. Preview</li>
      <li :class="step === 'done' ? 'text-primary' : ''">4. Done</li>
    </ol>

    <section v-if="step === 'upload'" class="rounded-2xl border border-neutral-ivory bg-white p-6 shadow-soft">
      <p class="text-sm text-neutral-muted">Supported: .xlsx and .csv</p>
      <input
        class="mt-4 block w-full rounded-xl border border-neutral-ivory bg-white px-4 py-3 text-sm text-neutral-black"
        type="file"
        accept=".csv,.xlsx,.xls"
        @change="onFile"
      />
    </section>

    <section v-else-if="step === 'map'" class="space-y-4 rounded-2xl border border-neutral-ivory bg-white p-6 shadow-soft">
      <p class="text-sm text-neutral-muted">Map spreadsheet columns to EMS fields.</p>
      <div v-for="field in fields" :key="field.key">
        <Select
          :model-value="mapping[field.key] ?? ''"
          :options="headerOptions"
          :label="field.label"
          :required="'required' in field && field.required"
          @update:model-value="(v) => (mapping[field.key] = v ? String(v) : null)"
        />
      </div>
      <div class="flex flex-wrap gap-2 pt-2">
        <Input v-model="mappingName" class="max-w-xs" placeholder="Save mapping as…" />
        <Button variant="outline" :disabled="!mappingName.trim()" @click="saveMapping">Save mapping</Button>
        <Button :disabled="busy || !mapping.name || !mapping.email" @click="runPreview">Validate & preview</Button>
        <Button variant="ghost" @click="step = 'upload'">Cancel</Button>
      </div>
    </section>

    <section v-else-if="step === 'preview' && preview" class="space-y-4">
      <div class="grid gap-3 sm:grid-cols-4">
        <div class="rounded-xl border border-neutral-ivory bg-white p-4">
          <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Total</p>
          <p class="mt-1 text-2xl font-semibold">{{ preview.total }}</p>
        </div>
        <div class="rounded-xl border border-neutral-ivory bg-white p-4">
          <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Valid</p>
          <p class="mt-1 text-2xl font-semibold text-emerald-700">{{ preview.valid }}</p>
        </div>
        <div class="rounded-xl border border-neutral-ivory bg-white p-4">
          <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Invalid</p>
          <p class="mt-1 text-2xl font-semibold text-red-700">{{ preview.invalid }}</p>
        </div>
        <div class="rounded-xl border border-neutral-ivory bg-white p-4">
          <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Duplicates</p>
          <p class="mt-1 text-2xl font-semibold text-amber-700">{{ preview.duplicates }}</p>
        </div>
      </div>

      <div v-if="preview.invalid_rows.length" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm">
        <p class="font-medium text-red-900">Invalid records will not be imported.</p>
        <ul class="mt-2 max-h-40 overflow-auto text-red-800">
          <li v-for="(row, idx) in preview.invalid_rows.slice(0, 20)" :key="idx">
            Row {{ row.row_number }}: {{ Array.isArray(row.errors) ? row.errors.join(', ') : 'Invalid' }}
          </li>
        </ul>
      </div>

      <div class="flex flex-wrap gap-2">
        <Button :disabled="busy || preview.valid === 0" @click="commit">Import {{ preview.valid }} attendees</Button>
        <Button variant="outline" @click="downloadReport">Download validation report</Button>
        <Button variant="ghost" @click="step = 'map'">Back</Button>
        <Button variant="ghost" @click="router.push({ name: 'ems-event-attendees', params: { uuid } })">Cancel</Button>
      </div>
    </section>

    <section v-else-if="step === 'done'" class="rounded-2xl border border-neutral-ivory bg-white p-6 shadow-soft">
      <p class="text-lg font-semibold text-neutral-black">Import complete</p>
      <p class="mt-1 text-sm text-neutral-muted">
        Registrations, tickets, and QR codes were generated for valid rows.
      </p>
      <Button class="mt-4" @click="router.push({ name: 'ems-event-attendees', params: { uuid } })">
        View attendees
      </Button>
    </section>
  </div>
</template>
