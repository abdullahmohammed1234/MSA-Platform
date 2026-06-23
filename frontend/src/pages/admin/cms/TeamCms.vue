<script setup lang="ts">
import { ref, onMounted } from 'vue';
import cmsService from '@/services/cms/cmsService';
import type { TeamMember, CmsRevision } from '@/types/cms';
import { 
  Plus, 
  Search, 
  Edit, 
  Trash2, 
  History, 
  X, 
  Save, 
  RotateCcw,
  ArrowUp,
  ArrowDown,
  Mail,
  Linkedin,
  UploadCloud,
  Image as ImageIcon
} from 'lucide-vue-next';
import { TEAM_FALLBACK_IMAGE, resolvePublicImagePath, toStorableImagePath } from '@/constants/publicAssets';

const resolveTeamImage = (img?: string | null) => resolvePublicImagePath(img || TEAM_FALLBACK_IMAGE);

// State variables
const members = ref<TeamMember[]>([]);
const pagination = ref({ page: 1, lastPage: 1, total: 0 });
const searchQuery = ref('');
const deptFilter = ref('');
const isLoading = ref(false);
const error = ref<string | null>(null);
const formError = ref<string | null>(null);
const isUploadingPhoto = ref(false);
const photoInput = ref<HTMLInputElement | null>(null);

// Modal state
const isFormOpen = ref(false);
const isRevisionsOpen = ref(false);
const activeMember = ref<TeamMember | null>(null);
const revisionsList = ref<CmsRevision[]>([]);

// Form state
const form = ref({
  name: '',
  role: '',
  dept: 'Directors',
  img: '',
  bio: '',
  email: '',
  linkedin: '',
  status: 'published' as 'draft' | 'published' | 'archived',
});

const DEPARTMENTS = [
  'President',
  'Vice Presidents',
  'Directors',
  'Secretary',
  'Coordinators'
];

onMounted(() => {
  fetchMembers();
});

const fetchMembers = async (page = 1) => {
  isLoading.value = true;
  error.value = null;
  try {
    const res = await cmsService.getTeamMembers({
      search: searchQuery.value,
      dept: deptFilter.value,
      page
    });
    members.value = res.data;
    pagination.value = {
      page: res.current_page,
      lastPage: res.last_page,
      total: res.total
    };
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to retrieve team members.';
  } finally {
    isLoading.value = false;
  }
};

const openCreateForm = () => {
  activeMember.value = null;
  formError.value = null;
  form.value = {
    name: '',
    role: '',
    dept: 'Directors',
    img: '',
    bio: '',
    email: '',
    linkedin: '',
    status: 'published',
  };
  isFormOpen.value = true;
};

const openEditForm = (m: TeamMember) => {
  activeMember.value = m;
  formError.value = null;
  form.value = {
    name: m.name,
    role: m.role,
    dept: m.dept,
    img: m.img || '',
    bio: m.bio || '',
    email: m.email || '',
    linkedin: m.linkedin || '',
    status: m.status,
  };
  isFormOpen.value = true;
};

const formatValidationError = (err: any, fallback: string) => {
  const data = err?.response?.data;
  if (!data) {
    return err?.message || fallback;
  }

  if (data.errors && typeof data.errors === 'object') {
    const firstError = Object.values(data.errors).flat()[0];
    if (typeof firstError === 'string') {
      return firstError;
    }
  }

  return data.message || fallback;
};

const buildPayload = () => ({
  name: form.value.name.trim(),
  role: form.value.role.trim(),
  dept: form.value.dept,
  img: toStorableImagePath(form.value.img),
  bio: form.value.bio.trim() || null,
  email: form.value.email.trim() || null,
  linkedin: form.value.linkedin.trim() || null,
  status: form.value.status,
});

const triggerPhotoUpload = () => {
  photoInput.value?.click();
};

const handlePhotoChange = async (event: Event) => {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  target.value = '';

  if (!file) {
    return;
  }

  if (!file.type.startsWith('image/')) {
    formError.value = 'Please choose an image file (JPEG, PNG, or WEBP).';
    return;
  }

  isUploadingPhoto.value = true;
  formError.value = null;

  try {
    const media = await cmsService.uploadMedia(file);
    form.value.img = toStorableImagePath(media.url) || media.url;
  } catch (err: any) {
    formError.value = formatValidationError(err, 'Failed to upload photo.');
  } finally {
    isUploadingPhoto.value = false;
  }
};

const handleSubmit = async () => {
  isLoading.value = true;
  error.value = null;
  formError.value = null;
  try {
    const payload = buildPayload();

    if (activeMember.value) {
      await cmsService.updateTeamMember(activeMember.value.uuid, payload);
    } else {
      await cmsService.createTeamMember(payload);
    }
    isFormOpen.value = false;
    fetchMembers(pagination.value.page);
  } catch (err: any) {
    formError.value = formatValidationError(err, 'Failed to save team member details.');
  } finally {
    isLoading.value = false;
  }
};

const handleDelete = async (m: TeamMember) => {
  if (!confirm(`Remove "${m.name}" from the team?`)) return;
  isLoading.value = true;
  error.value = null;
  try {
    await cmsService.deleteTeamMember(m.uuid);
    fetchMembers(pagination.value.page);
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to remove team member.';
  } finally {
    isLoading.value = false;
  }
};

// Reordering Logic
const moveItem = async (index: number, direction: 'up' | 'down') => {
  const newIndex = direction === 'up' ? index - 1 : index + 1;
  if (newIndex < 0 || newIndex >= members.value.length) return;

  // Swap locally first
  const temp = members.value[index];
  members.value[index] = members.value[newIndex];
  members.value[newIndex] = temp;

  // Get full list of UUIDs in new order
  const uuids = members.value.map(m => m.uuid);
  
  try {
    await cmsService.reorderTeamMembers(uuids);
  } catch (err) {
    console.error('Failed to sync team member display order:', err);
    fetchMembers(pagination.value.page); // rollback view on error
  }
};

const openRevisions = async (m: TeamMember) => {
  activeMember.value = m;
  isRevisionsOpen.value = true;
  revisionsList.value = [];
  try {
    revisionsList.value = await cmsService.getTeamMemberRevisions(m.uuid);
  } catch (err) {
    console.error('Failed to load version history:', err);
  }
};

const handleRollback = async (version: number) => {
  if (!activeMember.value) return;
  if (!confirm(`Rollback "${activeMember.value.name}" to version ${version}?`)) return;
  
  isLoading.value = true;
  try {
    await cmsService.rollbackTeamMember(activeMember.value.uuid, version);
    isRevisionsOpen.value = false;
    fetchMembers(pagination.value.page);
  } catch (err: any) {
    alert(err.response?.data?.message || 'Rollback failed.');
  } finally {
    isLoading.value = false;
  }
};
</script>

<template>
  <div class="space-y-8 text-neutral-black">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
      <div>
        <h1 class="text-4xl font-display font-extrabold text-primary">Team Members CMS</h1>
        <p class="text-neutral-black/45 text-sm">Add department directors, executives, and volunteers. Customize names, roles, and profiles.</p>
      </div>
      <button 
        @click="openCreateForm"
        class="w-fit flex items-center gap-2 px-5 py-3 bg-primary text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-secondary hover:shadow-brand transition-all cursor-pointer"
      >
        <Plus :size="14" /> Add Representative
      </button>
    </div>

    <!-- Filters -->
    <div class="bg-white border border-neutral-gray/10 p-4 rounded-3xl shadow-soft flex flex-col md:flex-row gap-4 items-center">
      <div class="relative flex-grow w-full">
        <Search class="absolute left-4.5 top-1/2 -translate-y-1/2 text-neutral-black/25" :size="16" />
        <input 
          type="text" 
          placeholder="Search team members..." 
          v-model="searchQuery"
          @input="fetchMembers(1)"
          class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3.5 pl-12 pr-4 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black"
        />
      </div>
      
      <div class="w-full md:w-56">
        <select 
          v-model="deptFilter" 
          @change="fetchMembers(1)"
          class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-3.5 px-4 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-semibold"
        >
          <option value="">All Departments</option>
          <option v-for="dept in DEPARTMENTS" :key="dept" :value="dept">{{ dept }}</option>
        </select>
      </div>
    </div>

    <!-- Alert status -->
    <div v-if="error" class="p-4 bg-secondary/10 border border-secondary/20 text-secondary rounded-2xl text-xs font-bold uppercase tracking-wider">
      {{ error }}
    </div>

    <!-- Team Members List -->
    <div class="bg-white border border-neutral-gray/10 rounded-[2rem] shadow-soft overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-xs text-neutral-black">
          <thead class="bg-neutral-background/50 border-b border-neutral-gray/10 uppercase font-black text-neutral-black/40 tracking-wider">
            <tr>
              <th class="p-6 w-16">Reorder</th>
              <th class="p-6">Member info</th>
              <th class="p-6">Department</th>
              <th class="p-6">Status</th>
              <th class="p-6">Contact Links</th>
              <th class="p-6 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-gray/10">
            <tr v-if="members.length === 0">
              <td colspan="6" class="p-12 text-center text-neutral-black/30">
                No team representatives found. Click "Add Representative" to add one.
              </td>
            </tr>
            <tr 
              v-for="(m, index) in members" 
              :key="m.uuid"
              class="hover:bg-neutral-background/20 transition-colors"
            >
              <!-- Order actions -->
              <td class="p-6">
                <div class="flex items-center gap-1.5">
                  <button 
                    @click="moveItem(index, 'up')" 
                    :disabled="index === 0"
                    class="p-1 hover:bg-primary/10 hover:text-primary rounded transition disabled:opacity-30 cursor-pointer"
                  >
                    <ArrowUp :size="12" />
                  </button>
                  <button 
                    @click="moveItem(index, 'down')" 
                    :disabled="index === members.length - 1"
                    class="p-1 hover:bg-primary/10 hover:text-primary rounded transition disabled:opacity-30 cursor-pointer"
                  >
                    <ArrowDown :size="12" />
                  </button>
                </div>
              </td>
              <td class="p-6">
                <div class="flex items-center gap-4">
                  <img 
                    :src="resolveTeamImage(m.img)" 
                    class="w-10 h-10 object-cover rounded-full border border-neutral-gray/20 bg-neutral-background"
                    alt="avatar"
                    @error="($event.target as HTMLImageElement).src = TEAM_FALLBACK_IMAGE"
                  />
                  <div>
                    <div class="font-bold text-primary">{{ m.name }}</div>
                    <div class="text-[10px] text-neutral-black/35 font-semibold">{{ m.role }}</div>
                  </div>
                </div>
              </td>
              <td class="p-6 text-neutral-black/60 font-semibold">
                {{ m.dept }}
              </td>
              <td class="p-6">
                <span 
                  :class="[
                    'px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider',
                    m.status === 'published' ? 'bg-secondary/10 text-secondary border border-secondary/20' : '',
                    m.status === 'draft' ? 'bg-neutral-gray/20 text-neutral-black/60 border border-neutral-gray/30' : '',
                    m.status === 'archived' ? 'bg-secondary/10 text-secondary border border-secondary/20' : ''
                  ]"
                >
                  {{ m.status }}
                </span>
              </td>
              <td class="p-6 space-y-1">
                <div v-if="m.email" class="flex items-center gap-1 text-[10px] text-neutral-black/55"><Mail :size="10" /> {{ m.email }}</div>
                <div v-if="m.linkedin" class="flex items-center gap-1 text-[10px] text-neutral-black/55"><Linkedin :size="10" /> {{ m.linkedin }}</div>
                <div v-if="!m.email && !m.linkedin" class="text-neutral-black/30 font-medium">—</div>
              </td>
              <td class="p-6 text-right space-x-2">
                <button @click="openEditForm(m)" class="p-2.5 bg-primary/5 hover:bg-primary hover:text-white rounded-xl text-primary transition-all duration-300 cursor-pointer" title="Edit">
                  <Edit :size="14" />
                </button>
                <button @click="openRevisions(m)" class="p-2.5 bg-accent-gold/5 hover:bg-accent-gold hover:text-white rounded-xl text-accent-gold transition-all duration-300 cursor-pointer" title="Revisions">
                  <History :size="14" />
                </button>
                <button @click="handleDelete(m)" class="p-2.5 bg-secondary/5 hover:bg-secondary hover:text-white rounded-xl text-secondary transition-all duration-300 cursor-pointer" title="Delete">
                  <Trash2 :size="14" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.lastPage > 1" class="p-6 bg-neutral-background/30 border-t border-neutral-gray/10 flex justify-between items-center">
        <span class="text-[10px] text-neutral-black/40 font-bold uppercase">Total: {{ pagination.total }} items</span>
        <div class="flex gap-2">
          <button 
            :disabled="pagination.page === 1"
            @click="fetchMembers(pagination.page - 1)"
            class="px-4 py-2 border border-neutral-gray/20 rounded-xl text-[10px] font-bold uppercase cursor-pointer disabled:opacity-40"
          >
            Prev
          </button>
          <button 
            :disabled="pagination.page === pagination.lastPage"
            @click="fetchMembers(pagination.page + 1)"
            class="px-4 py-2 border border-neutral-gray/20 rounded-xl text-[10px] font-bold uppercase cursor-pointer disabled:opacity-40"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- FORM MODAL -->
    <div v-if="isFormOpen" class="fixed inset-0 z-50 flex items-center justify-center p-5">
      <div class="absolute inset-0 bg-primary/20 backdrop-blur-md" @click="isFormOpen = false"></div>
      <div class="bg-white rounded-[2.5rem] border border-neutral-gray/20 p-8 sm:p-10 shadow-premium max-w-2xl w-full relative z-10 max-h-[90vh] overflow-y-auto">
        <button @click="isFormOpen = false" class="absolute top-6 right-6 p-2 text-neutral-black/35 hover:text-primary transition-colors cursor-pointer">
          <X :size="20" />
        </button>

        <h3 class="text-2xl font-display font-extrabold text-primary uppercase mb-6">
          {{ activeMember ? 'Edit Representative' : 'Add Team Representative' }}
        </h3>

        <div v-if="formError" class="mb-5 p-4 bg-secondary/10 border border-secondary/20 text-secondary rounded-2xl text-xs font-bold uppercase tracking-wider">
          {{ formError }}
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-5" novalidate>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Full Name</label>
              <input type="text" required v-model="form.name" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black" placeholder="Ahmad Ali" />
            </div>
            
            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Department Category</label>
              <select v-model="form.dept" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-semibold">
                <option v-for="dept in DEPARTMENTS" :key="dept" :value="dept">{{ dept }}</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Display Role Title</label>
              <input type="text" required v-model="form.role" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black" placeholder="Lead IT Developer" />
            </div>
            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Profile Photo</label>
              <input
                type="file"
                ref="photoInput"
                class="hidden"
                accept="image/jpeg,image/png,image/webp,image/gif"
                @change="handlePhotoChange"
              />
              <div class="flex flex-col gap-3">
                <button
                  type="button"
                  @click="triggerPhotoUpload"
                  :disabled="isUploadingPhoto || isLoading"
                  class="flex items-center justify-center gap-2 w-full bg-neutral-background border border-dashed border-neutral-gray/30 rounded-2xl py-4 px-5 text-xs font-bold uppercase tracking-wider text-primary hover:border-primary/40 hover:bg-primary/5 transition-all cursor-pointer disabled:opacity-50"
                >
                  <UploadCloud :size="16" />
                  {{ isUploadingPhoto ? 'Uploading photo...' : 'Upload photo' }}
                </button>
                <input
                  type="text"
                  v-model="form.img"
                  class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black"
                  placeholder="Or paste image URL / path (e.g. /Team/hamza.webp)"
                />
              </div>
              <div v-if="form.img" class="flex items-center gap-4 pt-2">
                <img
                  :src="resolveTeamImage(form.img)"
                  alt="Photo preview"
                  class="w-16 h-16 object-cover rounded-2xl border border-neutral-gray/20 bg-neutral-background"
                  @error="($event.target as HTMLImageElement).src = TEAM_FALLBACK_IMAGE"
                />
                <p class="text-[10px] text-neutral-black/40 leading-relaxed flex items-center gap-1.5">
                  <ImageIcon :size="12" /> Upload a new photo or use a path from <code>public/Team/</code> or the Media Library.
                </p>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Email Address (Optional)</label>
              <input type="text" v-model="form.email" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black" placeholder="name@sfu.ca" />
            </div>
            <div class="space-y-2">
              <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">LinkedIn Profile URL</label>
              <input type="text" v-model="form.linkedin" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black" />
            </div>
          </div>

          <div class="space-y-2">
            <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Short Biography</label>
            <textarea v-model="form.bio" rows="3" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-medium leading-relaxed"></textarea>
          </div>

          <div class="space-y-2">
            <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Status</label>
            <div class="flex gap-4">
              <label 
                v-for="st in (['draft', 'published', 'archived'] as const)" 
                :key="st"
                :class="[
                  'flex-1 border rounded-2xl p-4 text-center cursor-pointer transition-all duration-300 font-bold uppercase tracking-wider text-[10px]',
                  form.status === st ? 'border-primary bg-primary/5 text-primary' : 'border-neutral-gray/20 text-neutral-black/45'
                ]"
              >
                <input type="radio" v-model="form.status" :value="st" class="hidden" />
                {{ st }}
              </label>
            </div>
          </div>

          <div class="pt-6 border-t border-neutral-gray/10 flex justify-end gap-4">
            <button type="button" @click="isFormOpen = false" class="px-6 py-4 border border-neutral-gray/20 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-neutral-background cursor-pointer">
              Cancel
            </button>
            <button type="submit" :disabled="isLoading || isUploadingPhoto" class="flex items-center gap-2 px-8 py-4 bg-primary text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-secondary hover:shadow-brand transition-all cursor-pointer disabled:opacity-50">
              <Save :size="14" /> {{ isLoading ? 'Saving...' : 'Save Representative' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- REVISIONS MODAL -->
    <div v-if="isRevisionsOpen" class="fixed inset-0 z-50 flex items-center justify-center p-5">
      <div class="absolute inset-0 bg-primary/20 backdrop-blur-md" @click="isRevisionsOpen = false"></div>
      <div class="bg-white rounded-[2.5rem] border border-neutral-gray/20 p-8 sm:p-10 shadow-premium max-w-xl w-full relative z-10 max-h-[80vh] overflow-y-auto">
        <button @click="isRevisionsOpen = false" class="absolute top-6 right-6 p-2 text-neutral-black/35 hover:text-primary transition-colors cursor-pointer">
          <X :size="20" />
        </button>

        <h3 class="text-2xl font-display font-extrabold text-primary uppercase mb-6 flex items-center gap-2">
          <History :size="24" /> Revisions Log
        </h3>
        
        <p class="text-neutral-black/40 text-xs mb-6" v-if="activeMember">
          Version history tracking for <b>{{ activeMember.name }}</b>.
        </p>

        <div v-if="revisionsList.length === 0" class="py-12 text-center text-neutral-black/30 text-xs">
          No revision history found. Make changes to create versions.
        </div>

        <div v-else class="space-y-4">
          <div 
            v-for="rev in revisionsList" 
            :key="rev.id"
            class="p-5 border border-neutral-gray/10 bg-neutral-background/30 rounded-3xl flex items-center justify-between hover:border-accent-gold/45 hover:bg-white transition-all duration-300 group"
          >
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="text-xs font-black text-primary">Version {{ rev.version }}</span>
                <span class="text-[9px] font-bold text-neutral-black/30">{{ new Date(rev.created_at).toLocaleString() }}</span>
              </div>
              <p class="text-[10px] text-neutral-black/55 font-medium leading-relaxed">
                By: <b class="text-secondary">{{ rev.user?.name || 'Admin Seeder' }}</b>
              </p>
            </div>
            
            <button 
              @click="handleRollback(rev.version)"
              class="px-4 py-2.5 bg-accent-gold/5 border border-accent-gold/10 text-accent-gold rounded-xl text-[9px] font-black uppercase tracking-wider hover:bg-accent-gold hover:text-white transition-all duration-300 flex items-center gap-1.5 cursor-pointer shadow-sm"
            >
              <RotateCcw :size="10" /> Rollback
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
