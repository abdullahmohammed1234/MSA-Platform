<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import EmsConfirmDialog from '@/components/ems/EmsConfirmDialog.vue';
import UploadDocumentModal from '@/components/ems/UploadDocumentModal.vue';
import QrDocumentModal from '@/components/ems/QrDocumentModal.vue';
import { documentsService } from '@/services/ems/documentsService';
import type { EventDocument, QrCodeResponse } from '@/types/ems';

const props = defineProps<{
  eventUuid: string;
  canManage?: boolean;
}>();

const documents = ref<EventDocument[]>([]);
const isLoading = ref(true);
const errorMessage = ref<string | null>(null);

// Modal states
const showUploadModal = ref(false);
const uploadModalMode = ref<'create' | 'edit' | 'replace'>('create');
const activeDocForModal = ref<EventDocument | null>(null);

const showQrModal = ref(false);
const activeDocForQr = ref<EventDocument | null>(null);
const activeRawToken = ref<string | null>(null);

const showDeleteConfirm = ref(false);
const docToDelete = ref<EventDocument | null>(null);
const isDeleting = ref(false);

const typeBadges: Record<string, { label: string; class: string }> = {
  itinerary: { label: 'Itinerary', class: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
  menu: { label: 'Menu', class: 'bg-purple-50 text-purple-700 border-purple-200' },
  schedule: { label: 'Schedule', class: 'bg-blue-50 text-blue-700 border-blue-200' },
  map: { label: 'Map', class: 'bg-amber-50 text-amber-800 border-amber-200' },
  program: { label: 'Program', class: 'bg-cyan-50 text-cyan-800 border-cyan-200' },
  information: { label: 'Info', class: 'bg-indigo-50 text-indigo-700 border-indigo-200' },
  other: { label: 'Other', class: 'bg-neutral-100 text-neutral-700 border-neutral-200' },
};

async function loadDocuments() {
  isLoading.value = true;
  errorMessage.value = null;
  try {
    documents.value = await documentsService.list(props.eventUuid);
  } catch (err: any) {
    errorMessage.value = err?.message || 'Failed to load event documents.';
  } finally {
    isLoading.value = false;
  }
}

onMounted(() => {
  loadDocuments();
});

function openCreateModal() {
  activeDocForModal.value = null;
  uploadModalMode.value = 'create';
  showUploadModal.value = true;
}

function openEditModal(doc: EventDocument) {
  activeDocForModal.value = doc;
  uploadModalMode.value = 'edit';
  showUploadModal.value = true;
}

function openReplaceModal(doc: EventDocument) {
  activeDocForModal.value = doc;
  uploadModalMode.value = 'replace';
  showUploadModal.value = true;
}

function openQrModal(doc: EventDocument, rawToken?: string) {
  activeDocForModal.value = doc;
  activeDocForQr.value = doc;
  activeRawToken.value = rawToken || null;
  showQrModal.value = true;
}

function handleSaved(_doc: EventDocument) {
  showUploadModal.value = false;
  loadDocuments();
}

async function toggleActive(doc: EventDocument) {
  try {
    const updated = await documentsService.update(props.eventUuid, doc.uuid, {
      is_active: !doc.is_active,
    });
    const index = documents.value.findIndex((d) => d.uuid === doc.uuid);
    if (index !== -1) {
      documents.value[index] = updated;
    }
  } catch (err: any) {
    alert(err?.message || 'Failed to update document status.');
  }
}

function confirmDelete(doc: EventDocument) {
  docToDelete.value = doc;
  showDeleteConfirm.value = true;
}

async function handleDelete() {
  if (!docToDelete.value) return;
  isDeleting.value = true;
  try {
    await documentsService.remove(props.eventUuid, docToDelete.value.uuid);
    documents.value = documents.value.filter((d) => d.uuid !== docToDelete.value?.uuid);
    showDeleteConfirm.value = false;
    docToDelete.value = null;
  } catch (err: any) {
    alert(err?.message || 'Failed to delete document.');
  } finally {
    isDeleting.value = false;
  }
}

function handleQrRotated(res: QrCodeResponse) {
  const index = documents.value.findIndex((d) => d.uuid === res.document.uuid);
  if (index !== -1) {
    documents.value[index] = res.document;
  }
}

function formatFileSize(bytes: number): string {
  if (!bytes) return '0 B';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
</script>

<template>
  <div class="space-y-6">
    <!-- Section Header -->
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-lg font-bold text-neutral-black flex items-center gap-2">
          <span>📄 Event Documents</span>
          <span
            v-if="documents.length > 0"
            class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200"
          >
            {{ documents.length }}
          </span>
        </h3>
        <p class="text-xs text-neutral-muted">
          Supplementary PDF documents (itineraries, menus, venue maps, schedules). Stored privately with secure QR access.
        </p>
      </div>

      <Button v-if="canManage" variant="primary" size="sm" @click="openCreateModal">
        + Add Document
      </Button>
    </div>

    <!-- Error State -->
    <div v-if="errorMessage" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm font-medium">
      {{ errorMessage }}
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="py-12 text-center text-neutral-muted">
      <div class="inline-block animate-spin w-8 h-8 border-2 border-emerald-600 border-t-transparent rounded-full mb-2"></div>
      <p class="text-sm font-medium">Loading event documents...</p>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="documents.length === 0"
      class="py-12 text-center border-2 border-dashed border-neutral-ivory rounded-2xl bg-neutral-50/50 p-6"
    >
      <div class="w-12 h-12 rounded-full bg-white border border-neutral-ivory text-2xl flex items-center justify-center mx-auto mb-3 shadow-xs">
        📄
      </div>
      <h4 class="text-sm font-bold text-neutral-black">No Event Documents Attached</h4>
      <p class="text-xs text-neutral-muted mt-1 max-w-sm mx-auto">
        Upload supplementary PDF itineraries, menus, venue maps, or schedule guides for attendees.
      </p>

      <Button v-if="canManage" variant="primary" size="sm" class="mt-4" @click="openCreateModal">
        + Add Document
      </Button>
    </div>

    <!-- Documents List -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div
        v-for="doc in documents"
        :key="doc.uuid"
        class="bg-white border border-neutral-ivory hover:border-neutral-300 rounded-xl p-4 transition-all shadow-xs flex flex-col justify-between"
      >
        <div class="space-y-2">
          <!-- Top Row: Name + Type Badge -->
          <div class="flex items-start justify-between gap-2">
            <div class="space-y-0.5">
              <h4 class="text-sm font-bold text-neutral-black flex items-center gap-2">
                {{ doc.name }}
              </h4>
              <p v-if="doc.description" class="text-xs text-neutral-muted line-clamp-2">
                {{ doc.description }}
              </p>
            </div>
            <span
              :class="[
                'px-2 py-0.5 text-xs font-semibold rounded-md border shrink-0',
                typeBadges[doc.document_type]?.class || typeBadges.other.class
              ]"
            >
              {{ typeBadges[doc.document_type]?.label || doc.document_type }}
            </span>
          </div>

          <!-- Metadata info -->
          <div class="flex items-center gap-3 text-xs text-neutral-muted pt-1">
            <span class="flex items-center gap-1 font-mono">
              📎 {{ doc.original_filename }}
            </span>
            <span>•</span>
            <span>{{ formatFileSize(doc.file_size) }}</span>
            <span v-if="!doc.is_active" class="px-1.5 py-0.5 text-[10px] uppercase tracking-wider font-bold bg-neutral-100 text-neutral-500 rounded border border-neutral-200">
              Hidden
            </span>
          </div>
        </div>

        <!-- Action Bar -->
        <div class="flex items-center justify-between pt-3 mt-3 border-t border-neutral-ivory">
          <Button variant="outline" size="sm" @click="openQrModal(doc)">
            <span>📱 QR Code</span>
          </Button>

          <div v-if="canManage" class="flex items-center gap-1">
            <button
              type="button"
              @click="toggleActive(doc)"
              :title="doc.is_active ? 'Deactivate Document' : 'Activate Document'"
              :class="[
                'px-2 py-1 text-xs font-medium rounded-md transition-colors border',
                doc.is_active
                  ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100'
                  : 'bg-neutral-100 text-neutral-600 border-neutral-200 hover:bg-neutral-200'
              ]"
            >
              {{ doc.is_active ? 'Active' : 'Hidden' }}
            </button>

            <button
              type="button"
              @click="openReplaceModal(doc)"
              title="Replace PDF File"
              class="px-2 py-1 text-xs font-medium text-neutral-700 hover:text-neutral-black bg-neutral-100 hover:bg-neutral-200 border border-neutral-200 rounded-md transition-colors"
            >
              Replace
            </button>

            <button
              type="button"
              @click="openEditModal(doc)"
              title="Edit Details"
              class="px-2 py-1 text-xs font-medium text-neutral-700 hover:text-neutral-black bg-neutral-100 hover:bg-neutral-200 border border-neutral-200 rounded-md transition-colors"
            >
              Edit
            </button>

            <button
              type="button"
              @click="confirmDelete(doc)"
              title="Delete Document"
              class="px-2 py-1 text-xs font-medium text-red-700 hover:text-red-800 bg-red-50 hover:bg-red-100 border border-red-200 rounded-md transition-colors"
            >
              Delete
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <UploadDocumentModal
      :is-open="showUploadModal"
      :event-uuid="eventUuid"
      :document-to-edit="activeDocForModal"
      :mode="uploadModalMode"
      @cancel="showUploadModal = false"
      @saved="handleSaved"
    />

    <QrDocumentModal
      :is-open="showQrModal"
      :event-uuid="eventUuid"
      :document="activeDocForQr"
      :raw-token="activeRawToken"
      @close="showQrModal = false"
      @rotated="handleQrRotated"
    />

    <EmsConfirmDialog
      :is-open="showDeleteConfirm"
      title="Delete Event Document"
      :message="`Are you sure you want to delete '${docToDelete?.name}'? The private PDF and associated QR access URL will be permanently removed.`"
      confirm-label="Delete Document"
      :is-destructive="true"
      :is-busy="isDeleting"
      @cancel="showDeleteConfirm = false"
      @confirm="handleDelete"
    />
  </div>
</template>
