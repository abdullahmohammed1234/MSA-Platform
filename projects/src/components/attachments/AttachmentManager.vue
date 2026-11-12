<script setup lang="ts">
import { ref } from 'vue';
import { projectsApi } from '../../api/projects';
import type { Attachment } from '../../types/pm';
import {
  Paperclip,
  Download,
  Trash2,
  UploadCloud,
  FileText,
  FileArchive,
  FileImage,
  File,
  Loader2,
  AlertCircle,
  CheckCircle2,
} from 'lucide-vue-next';

const props = withDefaults(
  defineProps<{
    attachments?: Attachment[];
    projectUuid: string;
    taskUuid?: string | null;
    canUpload?: boolean;
    currentUserId?: number | null;
    isProjectManagerOrOwner?: boolean;
  }>(),
  {
    attachments: () => [],
    taskUuid: null,
    canUpload: false,
    currentUserId: null,
    isProjectManagerOrOwner: false,
  }
);

const emit = defineEmits<{
  (e: 'attachment-uploaded', attachment: Attachment): void;
  (e: 'attachment-deleted', attachmentUuid: string): void;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const isUploading = ref(false);
const uploadError = ref<string | null>(null);
const uploadSuccess = ref<string | null>(null);
const isDeletingUuid = ref<string | null>(null);
const isDragging = ref(false);

const MAX_SIZE_BYTES = 25 * 1024 * 1024; // 25 MB

const formatFileSize = (bytes: number): string => {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

const getFileIcon = (mimeType: string, filename: string) => {
  if (mimeType.includes('pdf') || filename.endsWith('.pdf')) return FileText;
  if (mimeType.includes('image/') || /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(filename)) return FileImage;
  if (mimeType.includes('zip') || mimeType.includes('tar') || /\.(zip|rar|7z|tar|gz)$/i.test(filename)) return FileArchive;
  return File;
};

const canDelete = (att: Attachment): boolean => {
  if (props.isProjectManagerOrOwner) return true;
  if (props.currentUserId && att.uploader && att.uploader.id === props.currentUserId) return true;
  return false;
};

const handleFileSelect = (e: Event) => {
  const target = e.target as HTMLInputElement;
  if (target.files && target.files.length > 0) {
    processUpload(target.files[0]);
  }
};

const onDropFile = (e: DragEvent) => {
  isDragging.value = false;
  if (e.dataTransfer?.files && e.dataTransfer.files.length > 0) {
    processUpload(e.dataTransfer.files[0]);
  }
};

const processUpload = async (file: File) => {
  uploadError.value = null;
  uploadSuccess.value = null;

  if (file.size > MAX_SIZE_BYTES) {
    uploadError.value = `File is too large (${(file.size / 1024 / 1024).toFixed(1)} MB). Maximum allowed size is 25 MB.`;
    return;
  }

  // Block obvious dangerous executables on client
  const blockedExts = ['php', 'exe', 'sh', 'bat', 'cmd', 'bin', 'js', 'vbs', 'ps1', 'msi', 'dll', 'com'];
  const ext = file.name.split('.').pop()?.toLowerCase();
  if (ext && blockedExts.includes(ext)) {
    uploadError.value = `Files with extension '.${ext}' are prohibited for security reasons.`;
    return;
  }

  isUploading.value = true;
  try {
    let res: { message: string; attachment: Attachment };
    if (props.taskUuid) {
      res = await projectsApi.uploadTaskAttachment(props.taskUuid, file);
    } else {
      res = await projectsApi.uploadProjectAttachment(props.projectUuid, file);
    }
    uploadSuccess.value = 'Attachment uploaded successfully.';
    emit('attachment-uploaded', res.attachment);
    if (fileInput.value) fileInput.value.value = '';
  } catch (err: any) {
    uploadError.value = err.response?.data?.message || 'Failed to upload attachment.';
  } finally {
    isUploading.value = false;
  }
};

const downloadAttachment = (att: Attachment) => {
  const url = projectsApi.getAttachmentDownloadUrl(att.uuid);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.target = '_blank';
  anchor.download = att.original_name;
  document.body.appendChild(anchor);
  anchor.click();
  document.body.removeChild(anchor);
};

const deleteAttachment = async (att: Attachment) => {
  if (!confirm(`Delete attachment "${att.original_name}"?`)) return;
  isDeletingUuid.value = att.uuid;
  try {
    await projectsApi.deleteAttachment(att.uuid);
    emit('attachment-deleted', att.uuid);
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to delete attachment.');
  } finally {
    isDeletingUuid.value = null;
  }
};
</script>

<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between pb-2 border-b border-[#f0eee6]">
      <div class="flex items-center gap-2">
        <Paperclip class="w-4 h-4 text-[#640c0e]" />
        <h4 class="text-xs font-bold text-[#1e1e1e]">Attachments</h4>
        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-[#f4f2ea] text-[#6b7280]">
          {{ attachments.length }}
        </span>
      </div>

      <span class="text-[10px] text-[#6b7280]">Max 25 MB per file</span>
    </div>

    <!-- Feedback messages -->
    <div v-if="uploadError" class="p-2.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs flex items-center gap-2">
      <AlertCircle class="w-4 h-4 shrink-0" />
      <span>{{ uploadError }}</span>
    </div>

    <div v-if="uploadSuccess" class="p-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs flex items-center gap-2">
      <CheckCircle2 class="w-4 h-4 shrink-0" />
      <span>{{ uploadSuccess }}</span>
    </div>

    <!-- Upload Zone (if user can upload) -->
    <div
      v-if="canUpload"
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="onDropFile"
      class="border-2 border-dashed rounded-xl p-4 text-center transition-colors cursor-pointer"
      :class="[
        isDragging ? 'border-[#640c0e] bg-[#640c0e]/5' : 'border-[#ded9cb] hover:border-[#640c0e]/40 bg-white/60'
      ]"
      @click="fileInput?.click()"
    >
      <input
        ref="fileInput"
        type="file"
        class="hidden"
        @change="handleFileSelect"
      />

      <div v-if="isUploading" class="flex flex-col items-center justify-center py-2 space-y-1">
        <Loader2 class="w-5 h-5 text-[#640c0e] animate-spin" />
        <span class="text-xs font-medium text-[#1e1e1e]">Uploading private file...</span>
      </div>

      <div v-else class="flex flex-col items-center justify-center space-y-1">
        <UploadCloud class="w-6 h-6 text-[#6b7280]" />
        <p class="text-xs font-semibold text-[#1e1e1e]">
          <span class="text-[#640c0e] underline">Click to upload</span> or drag and drop
        </p>
        <p class="text-[10px] text-[#6b7280]">PDF, Office, images, archives (max 25 MB)</p>
      </div>
    </div>

    <!-- Attachments List -->
    <div v-if="attachments.length > 0" class="space-y-2 max-h-60 overflow-y-auto pr-1">
      <div
        v-for="att in attachments"
        :key="att.uuid"
        class="flex items-center justify-between p-2.5 rounded-xl bg-white border border-[#ebe8de] hover:border-[#d0ccc0] transition-colors group"
      >
        <div class="flex items-center gap-2.5 min-w-0 flex-1 mr-2">
          <div class="w-8 h-8 rounded-lg bg-[#f7f6f0] flex items-center justify-center text-[#640c0e] shrink-0">
            <component :is="getFileIcon(att.mime_type, att.original_name)" class="w-4 h-4" />
          </div>

          <div class="min-w-0 flex-1">
            <p class="text-xs font-bold text-[#1e1e1e] truncate" :title="att.original_name">
              {{ att.original_name }}
            </p>
            <p class="text-[10px] text-[#6b7280]">
              {{ formatFileSize(att.file_size) }}
              <span v-if="att.uploader">&bull; {{ att.uploader.name }}</span>
            </p>
          </div>
        </div>

        <div class="flex items-center gap-1 shrink-0">
          <button
            type="button"
            @click="downloadAttachment(att)"
            class="p-1.5 rounded-lg text-[#6b7280] hover:text-[#640c0e] hover:bg-[#f7f6f0] transition-colors"
            title="Download file"
          >
            <Download class="w-4 h-4" />
          </button>

          <button
            v-if="canDelete(att)"
            type="button"
            @click="deleteAttachment(att)"
            :disabled="isDeletingUuid === att.uuid"
            class="p-1.5 rounded-lg text-[#6b7280] hover:text-red-600 hover:bg-red-50 transition-colors"
            title="Delete attachment"
          >
            <Loader2 v-if="isDeletingUuid === att.uuid" class="w-4 h-4 animate-spin text-red-600" />
            <Trash2 v-else class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <div v-else class="py-4 text-center text-xs text-[#6b7280] italic">
      No attachments uploaded yet.
    </div>
  </div>
</template>

