<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useCmsStore } from '@/stores/cms';
import { Save, Sparkles, Eye } from 'lucide-vue-next';
import { resolvePublicImagePath } from '@/constants/publicAssets';

const cmsStore = useCmsStore();
const activeTab = ref('hero');

// Local form bindings
const heroForm = ref<Record<string, string>>({
  tagline: '',
  title: '',
  subtitle: '',
  background_image: '',
  cta_primary_text: '',
  cta_primary_url: '',
  cta_secondary_text: '',
  cta_secondary_url: '',
});

const offeringsForm = ref<Record<string, string>>({
  section_title: '',
  section_subtitle: '',
  offering_1_title: '',
  offering_1_desc: '',
  offering_1_icon: '',
  offering_2_title: '',
  offering_2_desc: '',
  offering_2_icon: '',
  offering_3_title: '',
  offering_3_desc: '',
  offering_3_icon: '',
});

const ctaForm = ref<Record<string, string>>({
  title: '',
  subtitle: '',
  button_text: '',
  button_url: '',
});

const isSaving = ref(false);
const saveMessage = ref<string | null>(null);

onMounted(async () => {
  await loadData();
});

const loadData = async () => {
  try {
    await cmsStore.fetchHomepageSections();
    
    // Map loaded values to forms
    cmsStore.sections.forEach(sec => {
      const form: Record<string, string> = {};
      sec.blocks.forEach(b => {
        form[b.key] = b.value || '';
      });

      if (sec.key === 'hero') heroForm.value = { ...heroForm.value, ...form };
      if (sec.key === 'offerings') offeringsForm.value = { ...offeringsForm.value, ...form };
      if (sec.key === 'cta') ctaForm.value = { ...ctaForm.value, ...form };
    });
  } catch (err) {
    console.error('Failed to load homepage content sections:', err);
  }
};

const handleSave = async (sectionKey: string) => {
  isSaving.value = true;
  saveMessage.value = null;
  try {
    let blocks: Record<string, string> = {};
    if (sectionKey === 'hero') blocks = heroForm.value;
    if (sectionKey === 'offerings') blocks = offeringsForm.value;
    if (sectionKey === 'cta') blocks = ctaForm.value;

    await cmsStore.updateHomepageSection(sectionKey, blocks);
    saveMessage.value = 'Section saved successfully!';
    setTimeout(() => { saveMessage.value = null; }, 3000);
  } catch (err) {
    console.error('Save failed:', err);
  } finally {
    isSaving.value = false;
  }
};

const tabs = [
  { key: 'hero', name: 'Hero Banner' },
  { key: 'offerings', name: 'Framework Offerings' },
  { key: 'cta', name: 'Bottom Call-To-Action' },
];
</script>

<template>
  <div class="space-y-8 text-neutral-black">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
      <div>
        <h1 class="text-4xl font-display font-extrabold text-primary">Homepage CMS</h1>
        <p class="text-neutral-black/45 text-sm">Update text headings, buttons, and graphics displayed on the public landing page.</p>
      </div>
      <router-link to="/" target="_blank" class="w-fit flex items-center gap-2 px-5 py-3 border border-neutral-gray/20 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-primary/5 hover:text-primary transition-all duration-300">
        <Eye :size="14" /> Preview Live Site
      </router-link>
    </div>

    <!-- Tab Navigation -->
    <div class="flex border-b border-neutral-gray/20 gap-2 overflow-x-auto no-scrollbar">
      <button 
        v-for="tab in tabs" 
        :key="tab.key"
        @click="activeTab = tab.key"
        :class="[
          'px-6 py-4 text-[10px] font-black uppercase tracking-widest whitespace-nowrap transition-all duration-300 cursor-pointer relative',
          activeTab === tab.key ? 'text-primary' : 'text-neutral-black/45 hover:text-primary/75'
        ]"
      >
        {{ tab.name }}
        <div v-if="activeTab === tab.key" class="absolute bottom-0 inset-x-0 h-0.5 bg-primary" />
      </button>
    </div>

    <!-- Alert status -->
    <div v-if="saveMessage" class="p-4 bg-secondary/10 border border-secondary/20 text-secondary rounded-2xl text-xs font-bold uppercase tracking-wider">
      {{ saveMessage }}
    </div>
    <div v-if="cmsStore.error" class="p-4 bg-secondary/10 border border-secondary/20 text-secondary rounded-2xl text-xs font-bold uppercase tracking-wider">
      {{ cmsStore.error }}
    </div>

    <!-- Tab Contents -->
    <div class="bg-white border border-neutral-gray/10 rounded-[2rem] p-8 sm:p-10 shadow-soft">
      <!-- HERO FORM -->
      <form v-if="activeTab === 'hero'" @submit.prevent="handleSave('hero')" class="space-y-6">
        <div class="space-y-2">
          <h2 class="text-xl font-display font-extrabold text-primary uppercase">Hero Banner Options</h2>
          <p class="text-neutral-black/40 text-xs">Edit main tagline, title, descriptions, and button links.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="space-y-2">
            <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Top Tagline</label>
            <input type="text" v-model="heroForm.tagline" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-medium" />
          </div>
          <div class="space-y-2">
            <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Hero Title / Heading</label>
            <input type="text" v-model="heroForm.title" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-medium" />
          </div>
        </div>

        <div class="space-y-2">
          <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Hero Subtitle</label>
          <textarea v-model="heroForm.subtitle" rows="3" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-medium leading-relaxed"></textarea>
        </div>

        <div class="space-y-2">
          <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Hero Image URL</label>
          <input type="text" v-model="heroForm.background_image" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-medium" />
          <p class="text-[10px] text-neutral-black/30">Use a high resolution landscape image. (e.g. <code>/Hero/FOTO2.webp</code> or copy URL from Media Library)</p>
          <div v-if="heroForm.background_image" class="pt-2">
            <img
              :src="resolvePublicImagePath(heroForm.background_image)"
              alt="Hero preview"
              class="w-full max-h-48 object-cover rounded-2xl border border-neutral-gray/20"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-t border-neutral-gray/10 pt-6">
          <div class="space-y-4">
            <h4 class="text-xs font-black uppercase tracking-widest text-secondary">Primary Button (Left)</h4>
            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-1.5">
                <label class="text-[9px] font-bold text-neutral-black/45 uppercase">Text Label</label>
                <input type="text" v-model="heroForm.cta_primary_text" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-xl py-3 px-4 text-xs text-neutral-black font-medium" />
              </div>
              <div class="space-y-1.5">
                <label class="text-[9px] font-bold text-neutral-black/45 uppercase">URL Path</label>
                <input type="text" v-model="heroForm.cta_primary_url" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-xl py-3 px-4 text-xs text-neutral-black font-medium" />
              </div>
            </div>
          </div>
          <div class="space-y-4">
            <h4 class="text-xs font-black uppercase tracking-widest text-secondary">Secondary Button (Right)</h4>
            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-1.5">
                <label class="text-[9px] font-bold text-neutral-black/45 uppercase">Text Label</label>
                <input type="text" v-model="heroForm.cta_secondary_text" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-xl py-3 px-4 text-xs text-neutral-black font-medium" />
              </div>
              <div class="space-y-1.5">
                <label class="text-[9px] font-bold text-neutral-black/45 uppercase">URL Path</label>
                <input type="text" v-model="heroForm.cta_secondary_url" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-xl py-3 px-4 text-xs text-neutral-black font-medium" />
              </div>
            </div>
          </div>
        </div>

        <div class="pt-6 border-t border-neutral-gray/10 flex justify-end">
          <button type="submit" :disabled="isSaving" class="flex items-center gap-2 px-8 py-4 bg-primary text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-secondary hover:shadow-brand transition-all cursor-pointer">
            <Save :size="14" /> {{ isSaving ? 'Saving...' : 'Save Banner Edits' }}
          </button>
        </div>
      </form>

      <!-- OFFERINGS FORM -->
      <form v-if="activeTab === 'offerings'" @submit.prevent="handleSave('offerings')" class="space-y-8">
        <div class="space-y-2">
          <h2 class="text-xl font-display font-extrabold text-primary uppercase">Framework Offerings</h2>
          <p class="text-neutral-black/40 text-xs">Configure subtitle, main title, and details for the three highlights cards on the home page.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="space-y-2">
            <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Section Subtitle</label>
            <input type="text" v-model="offeringsForm.section_subtitle" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-medium" />
          </div>
          <div class="space-y-2">
            <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Section Title</label>
            <input type="text" v-model="offeringsForm.section_title" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-medium" />
          </div>
        </div>

        <!-- 3 Offerings Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 border-t border-neutral-gray/10 pt-8">
          <!-- Card 1 -->
          <div class="p-6 bg-neutral-background rounded-3xl space-y-4 border border-neutral-gray/10">
            <h4 class="text-xs font-black uppercase tracking-widest text-primary flex items-center gap-1.5"><Sparkles :size="14" /> Card 1</h4>
            <div class="space-y-1.5">
              <label class="text-[9px] font-bold text-neutral-black/45 uppercase">Title</label>
              <input type="text" v-model="offeringsForm.offering_1_title" class="w-full bg-white border border-neutral-gray/20 rounded-xl py-3 px-4 text-xs text-neutral-black font-bold" />
            </div>
            <div class="space-y-1.5">
              <label class="text-[9px] font-bold text-neutral-black/45 uppercase">Description</label>
              <textarea v-model="offeringsForm.offering_1_desc" rows="3" class="w-full bg-white border border-neutral-gray/20 rounded-xl py-3 px-4 text-xs text-neutral-black font-medium leading-relaxed"></textarea>
            </div>
            <div class="space-y-1.5">
              <label class="text-[9px] font-bold text-neutral-black/45 uppercase">Lucide Icon name</label>
              <input type="text" v-model="offeringsForm.offering_1_icon" class="w-full bg-white border border-neutral-gray/20 rounded-xl py-3 px-4 text-xs text-neutral-black font-semibold" />
            </div>
          </div>

          <!-- Card 2 -->
          <div class="p-6 bg-neutral-background rounded-3xl space-y-4 border border-neutral-gray/10">
            <h4 class="text-xs font-black uppercase tracking-widest text-primary flex items-center gap-1.5"><Sparkles :size="14" /> Card 2</h4>
            <div class="space-y-1.5">
              <label class="text-[9px] font-bold text-neutral-black/45 uppercase">Title</label>
              <input type="text" v-model="offeringsForm.offering_2_title" class="w-full bg-white border border-neutral-gray/20 rounded-xl py-3 px-4 text-xs text-neutral-black font-bold" />
            </div>
            <div class="space-y-1.5">
              <label class="text-[9px] font-bold text-neutral-black/45 uppercase">Description</label>
              <textarea v-model="offeringsForm.offering_2_desc" rows="3" class="w-full bg-white border border-neutral-gray/20 rounded-xl py-3 px-4 text-xs text-neutral-black font-medium leading-relaxed"></textarea>
            </div>
            <div class="space-y-1.5">
              <label class="text-[9px] font-bold text-neutral-black/45 uppercase">Lucide Icon name</label>
              <input type="text" v-model="offeringsForm.offering_2_icon" class="w-full bg-white border border-neutral-gray/20 rounded-xl py-3 px-4 text-xs text-neutral-black font-semibold" />
            </div>
          </div>

          <!-- Card 3 -->
          <div class="p-6 bg-neutral-background rounded-3xl space-y-4 border border-neutral-gray/10">
            <h4 class="text-xs font-black uppercase tracking-widest text-primary flex items-center gap-1.5"><Sparkles :size="14" /> Card 3</h4>
            <div class="space-y-1.5">
              <label class="text-[9px] font-bold text-neutral-black/45 uppercase">Title</label>
              <input type="text" v-model="offeringsForm.offering_3_title" class="w-full bg-white border border-neutral-gray/20 rounded-xl py-3 px-4 text-xs text-neutral-black font-bold" />
            </div>
            <div class="space-y-1.5">
              <label class="text-[9px] font-bold text-neutral-black/45 uppercase">Description</label>
              <textarea v-model="offeringsForm.offering_3_desc" rows="3" class="w-full bg-white border border-neutral-gray/20 rounded-xl py-3 px-4 text-xs text-neutral-black font-medium leading-relaxed"></textarea>
            </div>
            <div class="space-y-1.5">
              <label class="text-[9px] font-bold text-neutral-black/45 uppercase">Lucide Icon name</label>
              <input type="text" v-model="offeringsForm.offering_3_icon" class="w-full bg-white border border-neutral-gray/20 rounded-xl py-3 px-4 text-xs text-neutral-black font-semibold" />
            </div>
          </div>
        </div>

        <div class="pt-6 border-t border-neutral-gray/10 flex justify-end">
          <button type="submit" :disabled="isSaving" class="flex items-center gap-2 px-8 py-4 bg-primary text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-secondary hover:shadow-brand transition-all cursor-pointer">
            <Save :size="14" /> {{ isSaving ? 'Saving...' : 'Save Offerings Cards' }}
          </button>
        </div>
      </form>

      <!-- CTA FORM -->
      <form v-if="activeTab === 'cta'" @submit.prevent="handleSave('cta')" class="space-y-6">
        <div class="space-y-2">
          <h2 class="text-xl font-display font-extrabold text-primary uppercase">Bottom Call-To-Action</h2>
          <p class="text-neutral-black/40 text-xs">Edit the heading, descriptions, and buttons displayed inside the Islamic pattern bottom CTA banner.</p>
        </div>

        <div class="space-y-2">
          <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Main CTA Heading</label>
          <input type="text" v-model="ctaForm.title" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-medium" />
        </div>

        <div class="space-y-2">
          <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Detailed Subtitle</label>
          <textarea v-model="ctaForm.subtitle" rows="4" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-medium leading-relaxed"></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-t border-neutral-gray/10 pt-6">
          <div class="space-y-2">
            <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Button Text Label</label>
            <input type="text" v-model="ctaForm.button_text" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-medium" />
          </div>
          <div class="space-y-2">
            <label class="text-[10px] font-black uppercase tracking-widest text-primary/70">Button URL / Link</label>
            <input type="text" v-model="ctaForm.button_url" class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl py-4 px-5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-medium" />
          </div>
        </div>

        <div class="pt-6 border-t border-neutral-gray/10 flex justify-end">
          <button type="submit" :disabled="isSaving" class="flex items-center gap-2 px-8 py-4 bg-primary text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-secondary hover:shadow-brand transition-all cursor-pointer">
            <Save :size="14" /> {{ isSaving ? 'Saving...' : 'Save CTA Edits' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
