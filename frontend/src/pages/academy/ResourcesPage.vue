<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { 
  Download, 
  ExternalLink, 
  Search, 
  BookOpen, 
  Layers, 
  CheckCircle, 
  Loader2, 
  Database, 
  Activity, 
  Upload, 
  Trash2,
  HardDrive
} from 'lucide-vue-next';
import client from '@/services/api/client';
import { academyResourcesService } from '@/services/academy/academyResourcesService';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/components/feedback/toast';

const authStore = useAuthStore();
const toast = useToastStore();

interface Resource {
  id: string;
  title: string;
  category: 'Handbook' | 'Slide Deck' | 'Methodology Document' | 'Interactive Link' | string;
  size: string;
  description: string;
  lessons: string[];
  linkText?: string;
  url?: string;
}

const vaultResources = ref<Resource[]>([]);
const vaultLoading = ref(true);

interface DBMediaAsset {
  uuid: string;
  filename: string;
  filepath: string;
  url: string;
  mime_type: string;
  size: number;
  uploaded_by: number;
  created_at: string;
}

const activeTab = ref<'vault' | 'storage-console'>('vault');
const searchQuery = ref('');
const selectedCategory = ref<string>('All');
const downloadingStates = ref<Record<string, 'idle' | 'loading' | 'done'>>({});

// Console upload states
const localBucket = ref<DBMediaAsset[]>([]);
const isFetching = ref(false);
const isUploading = ref(false);
const selectedFile = ref<File | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const dragActive = ref(false);

const isStaff = computed(() => authStore.isAdmin || authStore.isMentor || authStore.isDirector);

const fetchAssets = async () => {
  if (!isStaff.value) return;
  isFetching.value = true;
  try {
    const response = await client.get('/admin/cms/media');
    localBucket.value = Array.isArray(response.data.data) ? response.data.data : response.data.media || [];
  } catch (err) {
    console.error('Failed to load local media assets', err);
  } finally {
    isFetching.value = false;
  }
};

onMounted(async () => {
  vaultLoading.value = true;
  try {
    vaultResources.value = await academyResourcesService.getResources();
  } catch (err) {
    console.error('Failed to load academy resources', err);
    vaultResources.value = [];
  } finally {
    vaultLoading.value = false;
  }
  fetchAssets();
});

const combinedResources = computed(() => {
  const assets: Resource[] = localBucket.value.map((item) => ({
    id: item.uuid,
    title: item.filename,
    category: item.mime_type.includes('pdf') ? 'Handbook' : 'Slide Deck',
    size: `${(item.size / (1024 * 1024)).toFixed(2)} MB`,
    description: 'Staff-uploaded learning resource synchronized via CMS media.',
    lessons: ['Uploaded Attachment'],
    url: item.url,
  }));
  return [...vaultResources.value, ...assets];
});

const handleDownloadTrigger = (res: Resource) => {
  if (res.category === 'Interactive Link' && res.url) {
    window.open(res.url, '_blank');
    return;
  }
  
  if (res.url) {
    window.open(res.url, '_blank');
    return;
  }

  downloadingStates.value[res.id] = 'loading';
  setTimeout(() => {
    downloadingStates.value[res.id] = 'done';
    toast.success(`Downloaded: ${res.title}`);
    setTimeout(() => {
      downloadingStates.value[res.id] = 'idle';
    }, 1500);
  }, 1200);
};

const filteredResources = computed(() => {
  return combinedResources.value.filter((res) => {
    const matchesSearch = res.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                          res.description.toLowerCase().includes(searchQuery.value.toLowerCase());
    const matchesCategory = selectedCategory.value === 'All' || res.category === selectedCategory.value;
    return matchesSearch && matchesCategory;
  });
});

// File picker triggers
const triggerFilePicker = () => {
  fileInput.value?.click();
};

const handleFileChange = (e: Event) => {
  const target = e.target as HTMLInputElement;
  if (target.files && target.files[0]) {
    selectedFile.value = target.files[0];
  }
};

const handleDragOver = (e: DragEvent) => {
  e.preventDefault();
  dragActive.value = true;
};

const handleDragLeave = () => {
  dragActive.value = false;
};

const handleDrop = (e: DragEvent) => {
  e.preventDefault();
  dragActive.value = false;
  if (e.dataTransfer?.files && e.dataTransfer.files[0]) {
    selectedFile.value = e.dataTransfer.files[0];
  }
};

const uploadFile = async () => {
  if (!selectedFile.value) return;
  isUploading.value = true;
  const formData = new FormData();
  formData.append('file', selectedFile.value);

  try {
    const response = await client.post('/admin/cms/media', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });

    if (response.status === 200 || response.status === 201) {
      toast.success('File uploaded successfully!');
      selectedFile.value = null;
      await fetchAssets();
    }
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to upload file.');
  } finally {
    isUploading.value = false;
  }
};

const deleteAsset = async (uuid: string) => {
  if (!confirm('Are you sure you want to delete this resource?')) return;
  try {
    const response = await client.delete(`/admin/cms/media/${uuid}`);
    if (response.status === 200) {
      toast.success('Resource deleted successfully.');
      await fetchAssets();
    }
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to delete resource.');
  }
};
</script>

<template>
  <div class="space-y-8 font-sans text-left">
    <!-- Header -->
    <div>
      <h1 class="text-3xl font-display font-bold text-primary tracking-tight">
        Storage Vault & Media Infrastructure
      </h1>
      <p class="text-neutral-muted text-sm mt-1 font-light">
        Access public study aids, text handbooks, presentation decks, or manage local server storage files.
      </p>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex border-b border-neutral-ivory">
      <button
        @click="activeTab = 'vault'"
        class="py-4 px-6 font-bold text-sm border-b-2 transition-all flex items-center gap-2 cursor-pointer"
        :class="activeTab === 'vault' ? 'border-primary text-primary' : 'border-transparent text-neutral-muted hover:text-neutral-black'"
      >
        <BookOpen class="h-4 w-4" />
        <span>Academic Resources Vault</span>
      </button>
      
      <button
        v-if="isStaff"
        @click="activeTab = 'storage-console'"
        class="py-4 px-6 font-bold text-sm border-b-2 transition-all flex items-center gap-2 cursor-pointer"
        :class="activeTab === 'storage-console' ? 'border-primary text-primary' : 'border-transparent text-neutral-muted hover:text-neutral-black'"
      >
        <Database class="h-4 w-4" />
        <span>Local Storage Console</span>
        <span class="bg-primary/10 text-primary text-[9px] font-bold px-1.5 py-0.5 rounded-full uppercase tracking-wider font-mono">
          Staff Console
        </span>
      </button>
    </div>

    <!-- VAULT TAB -->
    <div v-if="activeTab === 'vault'" class="space-y-8 animate-fade-in">
      <div class="flex flex-col md:flex-row gap-4 justify-between items-center bg-neutral-background/50 p-5 rounded-2xl border border-neutral-ivory">
        <div class="relative w-full md:w-96 select-none">
          <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-neutral-muted/60 h-4 w-4" />
          <input
            type="text"
            placeholder="Search resource files..."
            v-model="searchQuery"
            class="w-full bg-white border border-neutral-ivory rounded-xl px-12 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
          />
        </div>
        <div class="flex flex-wrap gap-2 justify-center">
          <button
            v-for="cat in ['All', 'Handbook', 'Slide Deck', 'Methodology Document', 'Interactive Link']"
            :key="cat"
            @click="selectedCategory = cat"
            class="px-4 py-1.5 rounded-xl text-xs font-bold border transition-all cursor-pointer whitespace-nowrap"
            :class="selectedCategory === cat ? 'bg-primary text-white border-primary shadow-soft' : 'bg-white text-neutral-muted border-neutral-ivory hover:text-primary'"
          >
            {{ cat }}
          </button>
        </div>
      </div>

      <!-- Resources grid -->
      <div v-if="filteredResources.length === 0" class="text-center py-20 border border-dashed border-neutral-ivory rounded-3xl bg-white max-w-md mx-auto space-y-4">
        <Layers class="h-12 w-12 text-neutral-300 mx-auto" />
        <h4 class="text-sm font-bold text-neutral-black">No Matching Files Found</h4>
        <button @click="searchQuery = ''; selectedCategory = 'All';" class="px-4 py-2 bg-primary text-white rounded-xl text-xs font-bold hover:bg-secondary">
          Reset Filter
        </button>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div
          v-for="res in filteredResources"
          :key="res.id"
          class="rounded-3xl border border-neutral-ivory bg-white hover:shadow-premium-md hover:border-primary/20 transition-all duration-300 overflow-hidden flex flex-col justify-between group h-full"
        >
          <div class="p-8 border-b border-neutral-background bg-neutral-background/10">
            <div class="flex justify-between items-center gap-3 mb-4 select-none">
              <span class="px-3 py-1 text-[9px] font-bold uppercase tracking-wider rounded-lg border bg-[#FAF6EE] text-primary border-primary/10">
                {{ res.category }}
              </span>
              <span class="text-[10px] font-mono font-bold text-neutral-muted">
                {{ res.size }}
              </span>
            </div>
            <h3 class="text-lg font-bold text-neutral-black tracking-tight leading-snug group-hover:text-primary transition-colors">
              {{ res.title }}
            </h3>
          </div>
          <div class="p-8 space-y-6 flex-1 flex flex-col justify-between">
            <p class="text-xs leading-relaxed text-neutral-muted font-light">
              {{ res.description }}
            </p>

            <div class="space-y-4 pt-4 border-t border-dashed border-neutral-ivory">
              <div class="flex flex-wrap items-center gap-2 select-none">
                <span class="text-[10px] font-bold text-neutral-muted uppercase tracking-widest block font-mono">Synergized Units:</span>
                <span v-for="les in res.lessons" :key="les" class="inline-block px-2.5 py-0.5 rounded-md bg-neutral-background border border-neutral-ivory text-[9px] text-primary font-bold">
                  {{ les }}
                </span>
              </div>

              <div class="flex justify-end pt-2">
                <button
                  @click="handleDownloadTrigger(res)"
                  :disabled="downloadingStates[res.id] === 'loading'"
                  class="w-full sm:w-auto h-11 px-5 rounded-xl font-bold text-xs flex items-center justify-center gap-2 cursor-pointer border"
                  :class="downloadingStates[res.id] === 'done' ? 'bg-green-600 text-white border-green-600' : 'bg-primary hover:bg-secondary text-white border-primary'"
                >
                  <template v-if="downloadingStates[res.id] === 'loading'">
                    <Loader2 class="h-4 w-4 animate-spin shrink-0" />
                    <span>Piping study media...</span>
                  </template>
                  <template v-else-if="downloadingStates[res.id] === 'done'">
                    <CheckCircle class="h-4 w-4 shrink-0" />
                    <span>Download Ready ✓</span>
                  </template>
                  <template v-else>
                    <Download v-if="res.category !== 'Interactive Link'" class="h-4 w-4 shrink-0" />
                    <ExternalLink v-else class="h-4 w-4 shrink-0" />
                    <span>{{ res.category === 'Interactive Link' ? (res.linkText || 'Launch Portal') : 'Download Resource' }}</span>
                  </template>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- STORAGE CONSOLE TAB (Staff only) -->
    <div v-if="activeTab === 'storage-console' && isStaff" class="space-y-8 animate-fade-in">
      <!-- Info Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-zinc-50 border border-neutral-ivory rounded-2xl p-6 flex items-center gap-4">
          <div class="bg-primary/10 p-3 rounded-xl text-primary">
            <HardDrive class="h-6 w-6" />
          </div>
          <div>
            <p class="text-[10px] font-bold text-neutral-muted uppercase tracking-widest font-mono">Storage Gateway</p>
            <p class="text-sm font-bold text-green-600 flex items-center gap-1.5 mt-0.5">
              <span class="h-2 w-2 rounded-full bg-green-500 inline-block animate-pulse" />
              LOCAL PUBLIC DISK
            </p>
          </div>
        </div>

        <div class="bg-zinc-50 border border-neutral-ivory rounded-2xl p-6 flex items-center gap-4">
          <div class="bg-primary/10 p-3 rounded-xl text-primary">
            <Database class="h-6 w-6" />
          </div>
          <div>
            <p class="text-[10px] font-bold text-neutral-muted uppercase tracking-widest font-mono">Ledger Database</p>
            <p class="text-sm font-bold text-neutral-black mt-0.5">Relational MySQL/SQLite</p>
          </div>
        </div>

        <div class="bg-zinc-50 border border-neutral-ivory rounded-2xl p-6 flex items-center gap-4">
          <div class="bg-primary/10 p-3 rounded-xl text-primary">
            <Activity class="h-6 w-6" />
          </div>
          <div>
            <p class="text-[10px] font-bold text-neutral-muted uppercase tracking-widest font-mono">Active Ledger Records</p>
            <p class="text-sm font-bold text-neutral-black mt-0.5">{{ localBucket.length }} Total Objects</p>
          </div>
        </div>
      </div>

      <!-- File upload workspace -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Left: Upload Box -->
        <div class="lg:col-span-5 space-y-6">
          <div class="bg-white border border-neutral-ivory rounded-3xl p-6 shadow-soft space-y-5">
            <div>
              <h3 class="text-lg font-bold text-neutral-black">S3-Compatible Local Handshake</h3>
              <p class="text-xs text-neutral-muted font-light">Upload files directly to the server's public asset directory.</p>
            </div>

            <!-- Drag and drop zone -->
            <div
              @dragover="handleDragOver"
              @dragleave="handleDragLeave"
              @drop="handleDrop"
              @click="triggerFilePicker"
              class="border-2 border-dashed rounded-3xl p-8 text-center cursor-pointer transition-all flex flex-col items-center justify-center space-y-3"
              :class="selectedFile ? 'border-green-300 bg-green-50/10' : dragActive ? 'border-primary bg-primary/5' : 'border-neutral-ivory hover:border-primary bg-neutral-background/20'"
            >
              <input
                type="file"
                ref="fileInput"
                @change="handleFileChange"
                class="hidden"
              />
              <Upload class="h-8 w-8 text-neutral-400" />
              <div v-if="selectedFile" class="space-y-1">
                <p class="text-xs font-bold text-green-700">File Selected ✓</p>
                <p class="text-[11px] text-neutral-black font-semibold truncate max-w-[200px]">{{ selectedFile.name }}</p>
                <p class="text-[9px] text-neutral-muted font-mono">{{ (selectedFile.size / (1024 * 1024)).toFixed(2) }} MB</p>
              </div>
              <div v-else>
                <p class="text-xs font-bold text-neutral-black">Click or drag file here</p>
                <p class="text-[10px] text-neutral-muted mt-1">PDF, PPTX, MP4 (Max 150MB)</p>
              </div>
            </div>

            <button
              @click="uploadFile"
              :disabled="!selectedFile || isUploading"
              class="w-full h-11 bg-primary hover:bg-secondary text-white rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center justify-center gap-1.5 disabled:opacity-50"
            >
              <Loader2 v-if="isUploading" class="h-4 w-4 animate-spin" />
              <span>{{ isUploading ? 'Uploading Payload...' : 'Initiate Local Upload' }}</span>
            </button>
          </div>
        </div>

        <!-- Right: Ledger items list -->
        <div class="lg:col-span-7 space-y-4">
          <div class="bg-white border border-neutral-ivory rounded-3xl p-6 shadow-soft">
            <h3 class="text-xs font-bold uppercase tracking-widest text-neutral-muted font-mono mb-4 border-b border-neutral-background pb-2">Local Disk Registry Ledger</h3>

            <div v-if="isFetching && localBucket.length === 0" class="text-center py-6 text-xs text-neutral-muted italic">
              Loading local ledger files...
            </div>
            <div v-else-if="localBucket.length === 0" class="text-center py-10 text-xs text-neutral-muted italic">
              No files uploaded to the local public directory yet.
            </div>
            <div v-else class="space-y-3 max-h-96 overflow-y-auto pr-1 scrollbar-thin">
              <div
                v-for="asset in localBucket"
                :key="asset.uuid"
                class="p-4 border border-neutral-ivory rounded-2xl flex justify-between items-center gap-4 bg-[#FAF6EE]/25 hover:border-primary/10 transition-all"
              >
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-2 mb-1">
                    <span class="text-[8px] font-bold font-mono px-1.5 py-0.5 rounded bg-green-50 text-green-700 border border-green-150 uppercase">ACTIVE</span>
                    <span class="text-[9px] text-neutral-muted font-mono">{{ (asset.size / (1024 * 1024)).toFixed(2) }} MB</span>
                  </div>
                  <h4 class="text-xs font-bold text-neutral-black truncate leading-tight">{{ asset.filename }}</h4>
                  <span class="text-[9px] text-neutral-muted font-mono block mt-1">Uploaded: {{ new Date(asset.created_at).toLocaleDateString() }}</span>
                </div>
                <div class="flex gap-2">
                  <a
                    :href="asset.url"
                    target="_blank"
                    class="p-2 border border-neutral-ivory rounded-xl text-neutral-500 hover:text-primary hover:border-primary/20 bg-white transition-all"
                    title="Download asset"
                  >
                    <Download class="h-4 w-4" />
                  </a>
                  <button
                    @click="deleteAsset(asset.uuid)"
                    class="p-2 border border-neutral-ivory rounded-xl text-neutral-400 hover:text-red-650 hover:border-red-200 bg-white transition-all cursor-pointer"
                    title="Delete ledger row"
                  >
                    <Trash2 class="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
