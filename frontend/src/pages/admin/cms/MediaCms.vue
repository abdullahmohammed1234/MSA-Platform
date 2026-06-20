<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useCmsStore } from '@/stores/cms';
import { resolveCmsMediaUrl } from '@/constants/publicAssets';
import { 
  UploadCloud, 
  Search, 
  Copy, 
  Trash2, 
  Check, 
  FileText, 
  FileArchive, 
  Image as ImageIcon 
} from 'lucide-vue-next';

const cmsStore = useCmsStore();
const searchQuery = ref('');
const copiedUuid = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);

onMounted(() => {
  cmsStore.fetchMedia();
});

const handleSearch = () => {
  cmsStore.fetchMedia({ search: searchQuery.value });
};

const triggerFileSelect = () => {
  fileInput.value?.click();
};

const handleFileChange = async (e: Event) => {
  const target = e.target as HTMLInputElement;
  if (target.files && target.files.length > 0) {
    await uploadFile(target.files[0]);
  }
};

const handleDrop = async (e: DragEvent) => {
  isDragging.value = false;
  if (e.dataTransfer?.files && e.dataTransfer.files.length > 0) {
    await uploadFile(e.dataTransfer.files[0]);
  }
};

const uploadFile = async (file: File) => {
  try {
    await cmsStore.uploadMedia(file);
  } catch (err) {
    console.error('File upload failed:', err);
  }
};

const handleCopyToClipboard = (url: string, uuid: string) => {
  navigator.clipboard.writeText(resolveCmsMediaUrl(url)).then(() => {
    copiedUuid.value = uuid;
    setTimeout(() => { copiedUuid.value = null; }, 2000);
  });
};

const handleDelete = async (uuid: string, filename: string) => {
  if (!confirm(`Delete media asset "${filename}"? Any pages using this URL will show broken image links.`)) return;
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

const isImage = (mime: string) => {
  return mime.startsWith('image/');
};

const getIcon = (mime: string) => {
  if (mime.includes('zip') || mime.includes('rar') || mime.includes('tar')) {
    return FileArchive;
  }
  if (mime.includes('pdf') || mime.includes('doc') || mime.includes('txt')) {
    return FileText;
  }
  return ImageIcon;
};
</script>

<template>
  <div class="space-y-8 text-neutral-black">
    <!-- Header -->
    <div>
      <h1 class="text-4xl font-display font-extrabold text-primary">Media Library</h1>
      <p class="text-neutral-black/45 text-sm">Upload, manage, and reuse files (images, PDFs, documents) across all CMS modules.</p>
    </div>

    <!-- Drag & Drop Upload Zone -->
    <div 
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="handleDrop"
      :class="[
        'border-2 border-dashed rounded-[2rem] p-12 text-center transition-all duration-300 flex flex-col items-center justify-center gap-4 cursor-pointer',
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
        accept="image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/zip"
      />
      
      <div class="p-5 bg-primary/5 text-primary rounded-full">
        <UploadCloud :size="32" />
      </div>
      <div>
        <h3 class="text-sm font-bold uppercase tracking-wider text-neutral-black/85">Drag & drop your files here</h3>
        <p class="text-neutral-black/45 text-[11px] mt-1.5 font-medium leading-relaxed">
          Supports JPEG, PNG, WEBP, PDF, DOCX, ZIP up to 10MB
        </p>
      </div>
      <button type="button" class="mt-2 px-6 py-3 bg-white border border-neutral-gray/20 rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-primary hover:text-white hover:border-primary transition-all shadow-sm">
        Select Files
      </button>
    </div>

    <!-- Toolbar Filters -->
    <div class="bg-white border border-neutral-gray/10 p-4 rounded-3xl shadow-soft flex gap-4 items-center">
      <div class="relative flex-grow">
        <Search class="absolute left-4.5 top-1/2 -translate-y-1/2 text-neutral-black/25" :size="16" />
        <input 
          type="text" 
          placeholder="Search by filename..." 
          v-model="searchQuery"
          @input="handleSearch"
          class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3.5 pl-12 pr-4 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black"
        />
      </div>
    </div>

    <!-- Status Alerts -->
    <div v-if="cmsStore.error" class="p-4 bg-secondary/10 border border-secondary/20 text-secondary rounded-2xl text-xs font-bold uppercase tracking-wider">
      {{ cmsStore.error }}
    </div>

    <!-- Media Grid -->
    <div v-if="cmsStore.isLoading && cmsStore.mediaList.length === 0" class="flex items-center justify-center py-20">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
    </div>

    <div v-else-if="cmsStore.mediaList.length === 0" class="py-20 text-center text-neutral-black/30 text-xs">
      No media files uploaded yet. Drag and drop to upload your first file.
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
            alt="media preview" 
          />
          <div v-else class="text-neutral-black/45 flex flex-col items-center gap-2">
            <component :is="getIcon(media.mime_type)" :size="36" />
            <span class="text-[9px] font-black uppercase tracking-widest font-mono text-neutral-black/30">
              {{ media.mime_type.split('/')[1] || 'FILE' }}
            </span>
          </div>
        </div>

        <!-- Info / Actions -->
        <div class="p-4 space-y-2 flex-grow flex flex-col justify-between">
          <div class="space-y-0.5">
            <h4 class="text-[10px] font-black text-primary truncate" :title="media.filename">
              {{ media.filename }}
            </h4>
            <div class="text-[8px] text-neutral-black/40 font-bold uppercase tracking-wider">
              {{ formatSize(media.size) }}
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
              @click="handleDelete(media.uuid, media.filename)"
              class="p-2 bg-secondary/5 text-secondary hover:bg-secondary hover:text-white rounded-xl transition-all cursor-pointer"
              title="Delete File"
            >
              <Trash2 :size="10" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
