<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useCmsStore } from '@/stores/cms';
import { resolveCmsMediaUrl } from '@/constants/publicAssets';
import {
  CMS_MEDIA_MAX_DOCUMENT_KB,
  CMS_MEDIA_MAX_IMAGE_KB,
  CMS_MEDIA_MAX_VIDEO_KB,
  formatCmsMediaLimitMb,
  validateCmsMediaFileSize,
} from '@/constants/cmsMediaUpload';
import {
  UploadCloud,
  Search,
  Copy,
  Trash2,
  Check,
  FileText,
  FileArchive,
  Image as ImageIcon,
  Video,
  Plus,
  X,
  Loader2,
} from 'lucide-vue-next';

const cmsStore = useCmsStore();
const searchQuery = ref('');
const copiedUuid = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);

const selectedFile = ref<File | null>(null);
const customName = ref('');
const selectedCategoryId = ref<number | ''>('');
const formError = ref<string | null>(null);

const showCategoryModal = ref(false);
const newCategoryName = ref('');
const categoryModalError = ref<string | null>(null);
const isCreatingCategory = ref(false);

const maxImageMb = formatCmsMediaLimitMb(CMS_MEDIA_MAX_IMAGE_KB);
const maxVideoMb = formatCmsMediaLimitMb(CMS_MEDIA_MAX_VIDEO_KB);
const maxDocumentMb = formatCmsMediaLimitMb(CMS_MEDIA_MAX_DOCUMENT_KB);

onMounted(async () => {
  await Promise.all([
    cmsStore.fetchMedia(),
    cmsStore.fetchMediaCategories(),
  ]);
});

const handleSearch = () => {
  cmsStore.fetchMedia({ search: searchQuery.value });
};

const triggerFileSelect = () => {
  fileInput.value?.click();
};

const setSelectedFile = (file: File | null) => {
  selectedFile.value = file;
  formError.value = null;
  if (file && !customName.value.trim()) {
    customName.value = file.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ').trim();
  }
};

const handleFileChange = (e: Event) => {
  const target = e.target as HTMLInputElement;
  const file = target.files?.[0] ?? null;
  target.value = '';
  setSelectedFile(file);
};

const handleDrop = (e: DragEvent) => {
  isDragging.value = false;
  const file = e.dataTransfer?.files?.[0] ?? null;
  setSelectedFile(file);
};

const clearSelectedFile = () => {
  selectedFile.value = null;
  customName.value = '';
  formError.value = null;
};

const openCategoryModal = () => {
  newCategoryName.value = '';
  categoryModalError.value = null;
  showCategoryModal.value = true;
};

const closeCategoryModal = () => {
  showCategoryModal.value = false;
  newCategoryName.value = '';
  categoryModalError.value = null;
};

const createCategory = async () => {
  const name = newCategoryName.value.trim();
  if (!name) {
    categoryModalError.value = 'Please enter a category name.';
    return;
  }

  isCreatingCategory.value = true;
  categoryModalError.value = null;
  try {
    const category = await cmsStore.createMediaCategory(name);
    selectedCategoryId.value = category.id;
    closeCategoryModal();
  } catch {
    categoryModalError.value = cmsStore.error || 'Failed to create category.';
  } finally {
    isCreatingCategory.value = false;
  }
};

const handleUpload = async () => {
  if (!selectedFile.value) {
    formError.value = 'Please select a file to upload.';
    return;
  }

  const sizeError = validateCmsMediaFileSize(selectedFile.value);
  if (sizeError) {
    formError.value = sizeError;
    return;
  }

  formError.value = null;
  try {
    await cmsStore.uploadMedia(selectedFile.value, {
      display_name: customName.value.trim() || undefined,
      category_id: selectedCategoryId.value === '' ? null : Number(selectedCategoryId.value),
    });
    clearSelectedFile();
  } catch {
    formError.value = cmsStore.error || 'Failed to upload file.';
  }
};

const mediaLabel = (media: { display_name: string | null; filename: string }) =>
  media.display_name?.trim() || media.filename;

const handleCopyToClipboard = (url: string, uuid: string) => {
  navigator.clipboard.writeText(resolveCmsMediaUrl(url)).then(() => {
    copiedUuid.value = uuid;
    setTimeout(() => { copiedUuid.value = null; }, 2000);
  });
};

const handleDelete = async (uuid: string, label: string) => {
  if (!confirm(`Delete media asset "${label}"? Any pages using this URL will show broken links.`)) return;
  try {
    await cmsStore.deleteMedia(uuid);
  } catch (err) {
    console.error('File deletion failed:', err);
  }
};

const formatSize = (bytes: number) => {
  if (bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const isImage = (mime: string) => mime.startsWith('image/');
const isVideo = (mime: string) => mime.startsWith('video/');

const mediaTypeLabel = (media: { media_type?: string; mime_type: string }) => {
  if (media.media_type) return media.media_type;
  if (isImage(media.mime_type)) return 'image';
  if (isVideo(media.mime_type)) return 'video';
  return 'document';
};

const getIcon = (mime: string) => {
  if (isVideo(mime)) return Video;
  if (mime.includes('zip') || mime.includes('rar') || mime.includes('tar')) {
    return FileArchive;
  }
  if (mime.includes('pdf') || mime.includes('doc') || mime.includes('txt')) {
    return FileText;
  }
  return ImageIcon;
};

const selectedFileHint = computed(() => {
  if (!selectedFile.value) return '';
  return `${selectedFile.value.name} · ${formatSize(selectedFile.value.size)}`;
});
</script>

<template>
  <div class="space-y-8 text-neutral-black">
    <!-- Header -->
    <div>
      <h1 class="text-4xl font-display font-extrabold text-primary">Media Library</h1>
      <p class="text-neutral-black/45 text-sm">
        Upload and manage images, videos, and documents for CMS modules.
      </p>
    </div>

    <!-- Upload panel -->
    <div class="bg-white border border-neutral-gray/10 rounded-[2rem] shadow-soft p-6 sm:p-8 space-y-6">
      <div
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="handleDrop"
        :class="[
          'border-2 border-dashed rounded-[1.5rem] p-8 text-center transition-all duration-300 flex flex-col items-center justify-center gap-3 cursor-pointer',
          isDragging
            ? 'border-primary bg-primary/5 scale-[1.01]'
            : 'border-neutral-gray/20 hover:border-primary/50 hover:bg-neutral-background/50'
        ]"
        @click="triggerFileSelect"
      >
        <input
          type="file"
          ref="fileInput"
          class="hidden"
          @change="handleFileChange"
          accept="image/*,video/mp4,video/webm,video/quicktime,video/ogg,.ogv,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/zip"
        />

        <div class="p-4 bg-primary/5 text-primary rounded-full">
          <UploadCloud :size="28" />
        </div>
        <div>
          <h3 class="text-sm font-bold uppercase tracking-wider text-neutral-black/85">
            {{ selectedFile ? 'Change selected file' : 'Drag & drop or select a file' }}
          </h3>
          <p class="text-neutral-black/45 text-[11px] mt-1.5 font-medium leading-relaxed">
            Images up to {{ maxImageMb }}MB · Documents up to {{ maxDocumentMb }}MB · Videos (MP4/WebM/MOV/OGV) up to {{ maxVideoMb }}MB
          </p>
          <p v-if="selectedFileHint" class="text-primary text-[11px] mt-2 font-semibold">
            Selected: {{ selectedFileHint }}
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="space-y-1.5">
          <label class="text-[10px] font-black uppercase tracking-widest text-neutral-black/50">
            Custom media name
          </label>
          <input
            v-model="customName"
            type="text"
            placeholder="e.g. MSA Welcome Night 2026"
            class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3 px-4 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black"
          />
          <p class="text-[10px] text-neutral-black/35">
            Shown in the library. Original filename is kept separately.
          </p>
        </div>

        <div class="space-y-1.5">
          <label class="text-[10px] font-black uppercase tracking-widest text-neutral-black/50">
            Category
          </label>
          <div class="flex gap-2">
            <select
              v-model="selectedCategoryId"
              class="flex-1 bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3 px-4 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black"
            >
              <option value="">Select existing category</option>
              <option
                v-for="category in cmsStore.mediaCategories"
                :key="category.id"
                :value="category.id"
              >
                {{ category.name }}
              </option>
            </select>
            <button
              type="button"
              @click="openCategoryModal"
              class="shrink-0 px-4 py-3 bg-primary/5 text-primary border border-primary/15 rounded-2xl text-[9px] font-black uppercase tracking-widest hover:bg-primary hover:text-white transition-all flex items-center gap-1.5"
            >
              <Plus :size="12" />
              New
            </button>
          </div>
        </div>
      </div>

      <div v-if="formError || cmsStore.error" class="p-3 bg-secondary/10 border border-secondary/20 text-secondary rounded-2xl text-xs font-bold">
        {{ formError || cmsStore.error }}
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <button
          type="button"
          @click="handleUpload"
          :disabled="cmsStore.isUploading || !selectedFile"
          class="px-6 py-3 bg-primary text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:opacity-90 transition-all shadow-sm disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
        >
          <Loader2 v-if="cmsStore.isUploading" :size="14" class="animate-spin" />
          <UploadCloud v-else :size="14" />
          {{ cmsStore.isUploading ? 'Uploading…' : 'Upload' }}
        </button>
        <button
          v-if="selectedFile"
          type="button"
          @click="clearSelectedFile"
          class="px-5 py-3 bg-white border border-neutral-gray/20 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-neutral-background transition-all"
        >
          Clear
        </button>
      </div>
    </div>

    <!-- Toolbar Filters -->
    <div class="bg-white border border-neutral-gray/10 p-4 rounded-3xl shadow-soft flex gap-4 items-center">
      <div class="relative flex-grow">
        <Search class="absolute left-4.5 top-1/2 -translate-y-1/2 text-neutral-black/25" :size="16" />
        <input
          type="text"
          placeholder="Search by name or filename..."
          v-model="searchQuery"
          @input="handleSearch"
          class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3.5 pl-12 pr-4 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black"
        />
      </div>
    </div>

    <!-- Media Grid -->
    <div v-if="cmsStore.isLoading && cmsStore.mediaList.length === 0" class="flex items-center justify-center py-20">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
    </div>

    <div v-else-if="cmsStore.mediaList.length === 0" class="py-20 text-center text-neutral-black/30 text-xs">
      No media files uploaded yet. Select a file above to upload your first asset.
    </div>

    <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-6">
      <div
        v-for="media in cmsStore.mediaList"
        :key="media.uuid"
        class="bg-white border border-neutral-gray/10 rounded-3xl overflow-hidden shadow-soft hover:shadow-premium hover:-translate-y-0.5 transition-all duration-300 flex flex-col group relative"
      >
        <!-- Preview -->
        <div class="aspect-square bg-neutral-background flex items-center justify-center overflow-hidden border-b border-neutral-gray/10 relative">
          <img
            v-if="isImage(media.mime_type)"
            :src="resolveCmsMediaUrl(media.url)"
            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
            :alt="mediaLabel(media)"
            loading="lazy"
          />
          <video
            v-else-if="isVideo(media.mime_type)"
            :src="resolveCmsMediaUrl(media.url)"
            class="w-full h-full object-cover bg-black"
            muted
            preload="metadata"
            playsinline
          />
          <div v-else class="text-neutral-black/45 flex flex-col items-center gap-2">
            <component :is="getIcon(media.mime_type)" :size="36" />
            <span class="text-[9px] font-black uppercase tracking-widest font-mono text-neutral-black/30">
              {{ media.mime_type.split('/')[1] || 'FILE' }}
            </span>
          </div>
          <span
            class="absolute top-2 left-2 px-2 py-0.5 rounded-lg bg-white/90 text-[8px] font-black uppercase tracking-wider text-primary shadow-sm"
          >
            {{ mediaTypeLabel(media) }}
          </span>
        </div>

        <!-- Info / Actions -->
        <div class="p-4 space-y-2 flex-grow flex flex-col justify-between">
          <div class="space-y-0.5">
            <h4 class="text-[10px] font-black text-primary truncate" :title="mediaLabel(media)">
              {{ mediaLabel(media) }}
            </h4>
            <div class="text-[8px] text-neutral-black/40 font-bold uppercase tracking-wider truncate">
              {{ media.category?.name || 'Uncategorized' }} · {{ formatSize(media.size) }}
            </div>
            <div
              v-if="media.display_name"
              class="text-[8px] text-neutral-black/30 truncate"
              :title="media.filename"
            >
              {{ media.filename }}
            </div>
          </div>

          <div class="flex gap-2 pt-2 border-t border-neutral-gray/5">
            <button
              @click="handleCopyToClipboard(media.url, media.uuid)"
              class="flex-1 py-2 bg-primary/5 text-primary hover:bg-primary hover:text-white rounded-xl text-[9px] font-black uppercase tracking-wider transition-all flex items-center justify-center gap-1 cursor-pointer"
              title="Copy URL"
            >
              <component :is="copiedUuid === media.uuid ? Check : Copy" :size="10" />
              {{ copiedUuid === media.uuid ? 'Copied' : 'Link' }}
            </button>
            <button
              @click="handleDelete(media.uuid, mediaLabel(media))"
              class="p-2 bg-secondary/5 text-secondary hover:bg-secondary hover:text-white rounded-xl transition-all cursor-pointer"
              title="Delete File"
            >
              <Trash2 :size="10" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create category modal -->
    <div
      v-if="showCategoryModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-black/40"
      @click.self="closeCategoryModal"
    >
      <div class="bg-white rounded-3xl shadow-premium w-full max-w-md p-6 space-y-4">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h3 class="text-lg font-display font-bold text-primary">Create New Category</h3>
            <p class="text-xs text-neutral-black/45 mt-1">Add a category for organizing media uploads.</p>
          </div>
          <button
            type="button"
            @click="closeCategoryModal"
            class="p-2 rounded-xl hover:bg-neutral-background text-neutral-black/40"
            aria-label="Close"
          >
            <X :size="16" />
          </button>
        </div>

        <div class="space-y-1.5">
          <label class="text-[10px] font-black uppercase tracking-widest text-neutral-black/50">
            Category name
          </label>
          <input
            v-model="newCategoryName"
            type="text"
            placeholder="e.g. Community"
            class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3 px-4 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20"
            @keydown.enter.prevent="createCategory"
          />
        </div>

        <div v-if="categoryModalError" class="p-3 bg-secondary/10 border border-secondary/20 text-secondary rounded-2xl text-xs font-bold">
          {{ categoryModalError }}
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button
            type="button"
            @click="closeCategoryModal"
            class="px-5 py-2.5 bg-white border border-neutral-gray/20 rounded-xl text-[10px] font-black uppercase tracking-widest"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="createCategory"
            :disabled="isCreatingCategory"
            class="px-5 py-2.5 bg-primary text-white rounded-xl text-[10px] font-black uppercase tracking-widest disabled:opacity-50 flex items-center gap-2"
          >
            <Loader2 v-if="isCreatingCategory" :size="12" class="animate-spin" />
            Create Category
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
