<script setup lang="ts">
import { ref, watch } from 'vue';
import { Dialog } from '@/components/feedback/dialog';
import { Button } from '@/components/ui/button';
import { documentsService } from '@/services/ems/documentsService';
import type { EventDocument, EventDocumentType } from '@/types/ems';

const props = defineProps<{
  isOpen: boolean;
  eventUuid: string;
  documentToEdit?: EventDocument | null;
  mode?: 'create' | 'edit' | 'replace';
}>();

const emit = defineEmits<{
  (e: 'saved', doc: EventDocument): void;
  (e: 'cancel'): void;
}>();

const name = ref('');
const documentType = ref<EventDocumentType>('itinerary');
const description = ref('');
const sortOrder = ref(0);
const selectedFile = ref<File | null>(null);
const fileError = ref<string | null>(null);
const errorMessage = ref<string | null>(null);
const isSubmitting = ref(false);

const documentTypes: Array<{ value: EventDocumentType; label: string }> = [
  { value: 'itinerary', label: 'Itinerary' },
  { value: 'menu', label: 'Menu' },
  { value: 'schedule', label: 'Event Schedule' },
  { value: 'map', label: 'Venue Map' },
  { value: 'program', label: 'Program Information' },
  { value: 'information', label: 'Travel / Info' },
  { value: 'other', label: 'Other' },
];

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      errorMessage.value = null;
      fileError.value = null;
      selectedFile.value = null;
      if (props.documentToEdit) {
        name.value = props.documentToEdit.name;
        documentType.value = props.documentToEdit.document_type;
        description.value = props.documentToEdit.description || '';
        sortOrder.value = props.documentToEdit.sort_order ?? 0;
      } else {
        name.value = '';
        documentType.value = 'itinerary';
        description.value = '';
        sortOrder.value = 0;
      }
    }
  },
  { immediate: true }
);

function handleFileChange(e: Event) {
  const input = e.target as HTMLInputElement;
  fileError.value = null;
  if (!input.files || input.files.length === 0) {
    selectedFile.value = null;
    return;
  }
  const file = input.files[0];
  if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
    fileError.value = 'Only PDF documents (.pdf) are allowed.';
    selectedFile.value = null;
    return;
  }
  if (file.size > 10 * 1024 * 1024) {
    fileError.value = 'File size exceeds the 10 MB maximum limit.';
    selectedFile.value = null;
    return;
  }
  selectedFile.value = file;
}

async function handleSubmit() {
  errorMessage.value = null;
  fileError.value = null;

  if (props.mode === 'create' && !selectedFile.value) {
    fileError.value = 'Please select a PDF document to upload.';
    return;
  }

  if (props.mode === 'replace' && !selectedFile.value) {
    fileError.value = 'Please select a new PDF document.';
    return;
  }

  isSubmitting.value = true;
  try {
    let savedDoc: EventDocument;
    const finalSort = Number.isFinite(Number(sortOrder.value)) ? Number(sortOrder.value) : 0;

    if (props.mode === 'create') {
      const formData = new FormData();
      formData.append('file', selectedFile.value!);
      formData.append('name', name.value.trim());
      formData.append('document_type', documentType.value);
      if (description.value.trim()) {
        formData.append('description', description.value.trim());
      }
      formData.append('sort_order', String(finalSort));

      const res = await documentsService.upload(props.eventUuid, formData);
      savedDoc = res.document;
    } else if (props.mode === 'replace' && props.documentToEdit) {
      const formData = new FormData();
      formData.append('file', selectedFile.value!);

      savedDoc = await documentsService.replacePdf(
        props.eventUuid,
        props.documentToEdit.uuid,
        formData
      );
    } else if (props.documentToEdit) {
      savedDoc = await documentsService.update(props.eventUuid, props.documentToEdit.uuid, {
        name: name.value.trim(),
        document_type: documentType.value,
        description: description.value.trim() || null,
        sort_order: finalSort,
      });
    } else {
      throw new Error('Invalid modal configuration.');
    }

    emit('saved', savedDoc);
  } catch (err: any) {
    if (err?.errors && typeof err.errors === 'object' && Object.keys(err.errors).length > 0) {
      errorMessage.value = Object.values(err.errors).flat().join(' ');
    } else {
      errorMessage.value = err?.message || 'Failed to save document. Please check inputs and try again.';
    }
  } finally {
    isSubmitting.value = false;
  }
}
</script>

<template>
  <Dialog
    :is-open="isOpen"
    :title="
      mode === 'replace'
        ? 'Replace PDF Document'
        : mode === 'edit'
        ? 'Edit Document Details'
        : 'Add Event Document'
    "
    size="md"
    @close="$emit('cancel')"
  >
    <form @submit.prevent="handleSubmit" class="space-y-4">
      <div v-if="errorMessage" class="p-3.5 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs font-medium leading-relaxed">
        {{ errorMessage }}
      </div>

      <template v-if="mode !== 'replace'">
        <div>
          <label class="block text-xs font-semibold text-neutral-black mb-1">
            Document Name <span class="text-brand-burgundy">*</span>
          </label>
          <input
            v-model="name"
            type="text"
            required
            placeholder="e.g. Event Itinerary & Schedule"
            class="w-full px-3 py-2 bg-white border border-neutral-ivory rounded-lg text-neutral-black text-sm focus:outline-none focus:border-brand-burgundy focus:ring-1 focus:ring-brand-burgundy placeholder-neutral-muted"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-neutral-black mb-1">
            Document Type <span class="text-brand-burgundy">*</span>
          </label>
          <select
            v-model="documentType"
            class="w-full px-3 py-2 bg-white border border-neutral-ivory rounded-lg text-neutral-black text-sm focus:outline-none focus:border-brand-burgundy focus:ring-1 focus:ring-brand-burgundy"
          >
            <option v-for="t in documentTypes" :key="t.value" :value="t.value">
              {{ t.label }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold text-neutral-black mb-1">
            Description
          </label>
          <textarea
            v-model="description"
            rows="2"
            placeholder="Optional summary or notes for attendees"
            class="w-full px-3 py-2 bg-white border border-neutral-ivory rounded-lg text-neutral-black text-sm focus:outline-none focus:border-brand-burgundy focus:ring-1 focus:ring-brand-burgundy placeholder-neutral-muted"
          ></textarea>
        </div>

        <div>
          <label class="block text-xs font-semibold text-neutral-black mb-1">
            Display Order
          </label>
          <input
            v-model.number="sortOrder"
            type="number"
            min="0"
            class="w-full px-3 py-2 bg-white border border-neutral-ivory rounded-lg text-neutral-black text-sm focus:outline-none focus:border-brand-burgundy focus:ring-1 focus:ring-brand-burgundy"
          />
        </div>
      </template>

      <template v-if="mode === 'create' || mode === 'replace'">
        <div>
          <label class="block text-xs font-semibold text-neutral-black mb-1">
            PDF File <span class="text-brand-burgundy">*</span>
          </label>
          <div
            class="border-2 border-dashed border-neutral-ivory hover:border-brand-burgundy/50 rounded-xl p-5 text-center transition-colors bg-neutral-50/60"
          >
            <input
              id="document-pdf-input"
              type="file"
              accept="application/pdf"
              class="hidden"
              @change="handleFileChange"
            />
            <label for="document-pdf-input" class="cursor-pointer flex flex-col items-center justify-center gap-1.5">
              <div class="w-10 h-10 rounded-full bg-brand-burgundy/10 text-brand-burgundy flex items-center justify-center text-lg font-bold">
                📄
              </div>
              <span class="text-sm font-semibold text-neutral-black">
                {{ selectedFile ? selectedFile.name : 'Click to choose a PDF file' }}
              </span>
              <span class="text-xs text-neutral-muted">PDF documents only (max 10 MB)</span>
            </label>
          </div>
          <p v-if="fileError" class="mt-1 text-xs text-red-600 font-medium">{{ fileError }}</p>
        </div>
      </template>
    </form>

    <template #footer>
      <Button variant="ghost" :disabled="isSubmitting" @click="$emit('cancel')">
        Cancel
      </Button>
      <Button variant="primary" :is-loading="isSubmitting" @click="handleSubmit">
        {{ mode === 'replace' ? 'Replace PDF' : mode === 'edit' ? 'Save Changes' : 'Upload Document' }}
      </Button>
    </template>
  </Dialog>
</template>
