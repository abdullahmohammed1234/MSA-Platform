<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { 
  Building, 
  Award, 
  Heart, 
  Send, 
  AlertCircle,
  Plus
} from 'lucide-vue-next';
import ScrollReveal from '@/components/shared/ScrollReveal.vue';
import PublicButton from '@/components/shared/PublicButton.vue';
import PublicCard from '@/components/shared/PublicCard.vue';
import { websiteService } from '@/services/website/websiteService';
import type { SponsorItem } from '@/services/website/websiteService';

const sponsors = ref<SponsorItem[]>([]);
const isLoading = ref(true);

const formState = ref({
  companyName: '',
  contactName: '',
  email: '',
  tierPreference: '',
  message: ''
});

const isSubmitting = ref(false);
const isSuccess = ref(false);
const submitError = ref('');

const loadSponsors = async () => {
  try {
    sponsors.value = await websiteService.getSponsors();
  } catch (err) {
    console.error('Failed to load sponsors:', err);
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  loadSponsors();
});

const groupedSponsors = computed(() => {
  const groups = {
    Platinum: [] as SponsorItem[],
    Gold: [] as SponsorItem[],
    Silver: [] as SponsorItem[],
    Bronze: [] as SponsorItem[]
  };
  sponsors.value.forEach(item => {
    if (item.tier in groups) {
      groups[item.tier].push(item);
    }
  });
  return groups;
});

const benefits = [
  {
    title: 'Brand Visibility',
    description: 'Prominent logo placement on our website, weekly newsletters, and promotional event flyers reaching over 5000+ community members.',
    icon: Building
  },
  {
    title: 'Direct Campus Engagement',
    description: 'Interact directly with students through booth presence at orientations, career panels, and sponsored social events.',
    icon: Award
  },
  {
    title: 'Community Goodwill',
    description: 'Associate your brand with positive social impact, charity events, and campus inclusivity, showing support for diversity.',
    icon: Heart
  }
];

const handleSubmit = async () => {
  isSubmitting.value = true;
  submitError.value = '';
  isSuccess.value = false;

  try {
    const result = await websiteService.submitSponsorApplication(formState.value);
    if (result.success) {
      isSuccess.value = true;
      formState.value = {
        companyName: '',
        contactName: '',
        email: '',
        tierPreference: '',
        message: ''
      };
      setTimeout(() => {
        isSuccess.value = false;
      }, 5000);
    }
  } catch (err: any) {
    submitError.value = err.message || 'Something went wrong. Please try again.';
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div class="bg-neutral-background min-h-screen">
    <!-- Hero Section -->
    <section class="relative py-24 sm:py-32 bg-primary text-neutral-white overflow-hidden border-b border-primary/10">
      <div class="absolute inset-0 pattern-islamic opacity-5" />
      <div class="absolute top-0 right-0 w-96 h-96 bg-secondary-light/10 blur-[120px] rounded-full" />
      
      <div class="container-custom relative z-10">
        <div class="max-w-4xl space-y-8">
          <ScrollReveal direction="right">
            <span class="inline-flex items-center gap-2 px-4 py-2 bg-neutral-white/10 border border-neutral-white/20 rounded-full text-accent-gold font-bold uppercase tracking-widest text-[10px]">
              <Plus class="w-3 h-3" /> Sponsor & Partner
            </span>
          </ScrollReveal>

          <ScrollReveal :delay="0.2">
            <h1 class="text-5xl md:text-[92px] font-display font-medium leading-[0.9] tracking-tighter text-neutral-white">
              Empower our <br />
              <span class="text-accent-gold italic font-serif">Community & Growth.</span>
            </h1>
          </ScrollReveal>

          <ScrollReveal :delay="0.3">
            <p class="text-xl md:text-2xl text-neutral-white/70 leading-relaxed max-w-2xl font-light">
              By sponsoring the SFU Muslim Students Association, you support key programming, Jumu'ah facilities, and student mentorship while increasing your brand exposure.
            </p>
          </ScrollReveal>
        </div>
      </div>
    </section>

    <!-- Sponsor Tiers & Grid -->
    <section class="section-padding">
      <div class="container-custom space-y-20">
        <div class="max-w-3xl mb-16 space-y-4">
          <h2 class="text-4xl md:text-5xl font-display font-bold text-primary">
            Our Partners & Sponsors
          </h2>
          <p class="text-lg leading-relaxed text-neutral-black/60">
            We are deeply grateful to the organizations and local businesses that make our programming possible.
          </p>
        </div>

        <div v-if="isLoading" class="flex justify-center py-10">
          <div class="w-10 h-10 border-2 border-primary border-t-transparent rounded-full animate-spin" />
        </div>

        <div v-else class="space-y-16">
          <!-- Platinum Sponsors -->
          <div v-if="groupedSponsors.Platinum.length" class="space-y-6">
            <h3 class="text-xs font-bold uppercase tracking-[0.2em] text-secondary-light border-b border-neutral-gray/20 pb-3">
              Platinum Sponsors
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
              <a 
                v-for="sp in groupedSponsors.Platinum" 
                :key="sp.id"
                :href="sp.websiteUrl || '#'"
                target="_blank"
                class="block"
              >
                <PublicCard variant="premium" class="bg-neutral-white p-8 border border-neutral-gray/20 flex flex-col items-center justify-center min-h-[180px] hover:border-primary/40 transition-all">
                  <img :src="sp.logoUrl" :alt="sp.name" class="max-h-16 max-w-full object-contain mb-4 filter grayscale group-hover:grayscale-0 transition-all duration-300" />
                  <span class="font-bold text-primary text-lg">{{ sp.name }}</span>
                </PublicCard>
              </a>
            </div>
          </div>

          <!-- Gold Sponsors -->
          <div v-if="groupedSponsors.Gold.length" class="space-y-6">
            <h3 class="text-xs font-bold uppercase tracking-[0.2em] text-secondary-light border-b border-neutral-gray/20 pb-3">
              Gold Sponsors
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
              <a 
                v-for="sp in groupedSponsors.Gold" 
                :key="sp.id"
                :href="sp.websiteUrl || '#'"
                target="_blank"
                class="block"
              >
                <PublicCard variant="default" class="bg-neutral-white p-6 border border-neutral-gray/20 flex flex-col items-center justify-center min-h-[140px] hover:border-primary/30 transition-all">
                  <img :src="sp.logoUrl" :alt="sp.name" class="max-h-12 max-w-full object-contain mb-3" />
                  <span class="font-semibold text-primary text-sm text-center">{{ sp.name }}</span>
                </PublicCard>
              </a>
            </div>
          </div>

          <!-- Silver/Bronze Sponsors -->
          <div v-if="groupedSponsors.Silver.length || groupedSponsors.Bronze.length" class="space-y-6">
            <h3 class="text-xs font-bold uppercase tracking-[0.2em] text-secondary-light border-b border-neutral-gray/20 pb-3">
              Community Partners & Supporters
            </h3>
            <div class="flex flex-wrap gap-4">
              <div 
                v-for="sp in [...groupedSponsors.Silver, ...groupedSponsors.Bronze]" 
                :key="sp.id"
                class="px-5 py-3 rounded-xl bg-neutral-white border border-neutral-gray/20 flex items-center gap-3"
              >
                <span class="font-bold text-xs uppercase tracking-wider px-2 py-0.5 rounded" :class="sp.tier === 'Silver' ? 'bg-neutral-gray/10 text-neutral-black/50' : 'bg-amber-100 text-amber-800'">
                  {{ sp.tier }}
                </span>
                <span class="text-primary font-medium text-sm">{{ sp.name }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Sponsorship Benefits -->
    <section class="section-padding bg-neutral-white border-y border-neutral-gray/20">
      <div class="container-custom">
        <div class="max-w-3xl mb-16 space-y-4">
          <h2 class="text-4xl md:text-5xl font-display font-bold text-primary">
            Sponsorship Benefits
          </h2>
          <p class="text-lg leading-relaxed text-neutral-black/60">
            Why support the SFU Muslim Students Association?
          </p>
        </div>

        <div class="grid md:grid-cols-3 gap-8">
          <PublicCard
            v-for="(benefit, idx) in benefits"
            :key="idx"
            variant="premium"
            class="bg-neutral-background border border-neutral-gray/20 p-8 space-y-6"
          >
            <div class="w-12 h-12 rounded-xl bg-primary/5 text-primary flex items-center justify-center border border-primary/10">
              <component :is="benefit.icon" class="w-6 h-6" />
            </div>
            <h3 class="text-xl font-bold text-primary">
              {{ benefit.title }}
            </h3>
            <p class="text-neutral-black/50 text-sm leading-relaxed font-light">
              {{ benefit.description }}
            </p>
          </PublicCard>
        </div>
      </div>
    </section>

    <!-- Sponsorship Application Form -->
    <section class="section-padding">
      <div class="container-custom">
        <div class="grid lg:grid-cols-2 gap-16 items-start">
          <div class="space-y-6 max-w-lg">
            <h2 class="text-4xl font-bold text-primary font-display">
              Become a Sponsor
            </h2>
            <p class="text-neutral-black/60 leading-relaxed font-light">
              Fill out our simple application form, and our sponsorship team will reach out with our detailed partnership package options.
            </p>
            <div class="p-6 bg-neutral-white rounded-2xl border border-neutral-gray/20">
              <h4 class="font-bold text-primary mb-2">Looking for custom partnerships?</h4>
              <p class="text-xs text-neutral-black/50 leading-relaxed">
                If you have specific collaboration ideas, event sponsorships, or student discount initiatives, select the custom tier preference and describe it in your message.
              </p>
            </div>
          </div>

          <!-- Form Card -->
          <PublicCard variant="premium" class="bg-neutral-white p-8 md:p-12 border border-neutral-gray/20 shadow-premium">
            <form @submit.prevent="handleSubmit" class="space-y-6">
              <div class="space-y-2">
                <label class="text-xs font-bold uppercase tracking-widest text-neutral-black/30 font-sans">Company Name</label>
                <input 
                  type="text" 
                  required
                  placeholder="Your business name"
                  v-model="formState.companyName"
                  class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl px-6 py-4 focus:ring-2 focus:ring-primary/20 outline-none transition-all text-neutral-black text-sm"
                />
              </div>

              <div class="space-y-2">
                <label class="text-xs font-bold uppercase tracking-widest text-neutral-black/30 font-sans">Contact Name</label>
                <input 
                  type="text" 
                  required
                  placeholder="Your name"
                  v-model="formState.contactName"
                  class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl px-6 py-4 focus:ring-2 focus:ring-primary/20 outline-none transition-all text-neutral-black text-sm"
                />
              </div>

              <div class="space-y-2">
                <label class="text-xs font-bold uppercase tracking-widest text-neutral-black/30 font-sans">Email Address</label>
                <input 
                  type="email" 
                  required
                  placeholder="partner@company.com"
                  v-model="formState.email"
                  class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl px-6 py-4 focus:ring-2 focus:ring-primary/20 outline-none transition-all text-neutral-black text-sm"
                />
              </div>

              <div class="space-y-2">
                <label class="text-xs font-bold uppercase tracking-widest text-neutral-black/30 font-sans">Preferred Tier</label>
                <select 
                  required
                  v-model="formState.tierPreference"
                  class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl px-6 py-4 focus:ring-2 focus:ring-primary/20 outline-none transition-all appearance-none text-neutral-black text-sm"
                >
                  <option value="" disabled>Select tier preference</option>
                  <option value="Platinum">Platinum Partner ($1500+)</option>
                  <option value="Gold">Gold Partner ($1000)</option>
                  <option value="Silver">Silver Sponsor ($500)</option>
                  <option value="Bronze">Bronze Supporter ($250)</option>
                  <option value="Other">Custom Collaboration</option>
                </select>
              </div>

              <div class="space-y-2">
                <label class="text-xs font-bold uppercase tracking-widest text-neutral-black/30 font-sans">Message / Inquiry Details</label>
                <textarea 
                  required
                  rows="4"
                  placeholder="Describe your sponsorship objectives or questions..."
                  v-model="formState.message"
                  class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl px-6 py-4 focus:ring-2 focus:ring-primary/20 outline-none transition-all text-neutral-black text-sm resize-none"
                />
              </div>

              <PublicButton 
                type="submit" 
                variant="primary" 
                size="lg" 
                class="w-full justify-center gap-3"
                :disabled="isSubmitting"
              >
                <Send class="w-4 h-4" />
                {{ isSubmitting ? "Submitting..." : "Submit Request" }}
              </PublicButton>

              <!-- Success Alert -->
              <div 
                v-if="isSuccess" 
                class="p-4 bg-emerald-50 text-emerald-600 rounded-2xl text-center text-sm font-medium border border-emerald-500/20"
              >
                Jazakullah Khair! Your sponsorship request has been received. Our team will contact you shortly.
              </div>

              <!-- Error Alert -->
              <div 
                v-if="submitError" 
                class="flex items-center justify-center gap-3 p-4 bg-red-50 text-red-600 rounded-2xl text-center text-sm font-medium border border-red-500/20"
              >
                <AlertCircle class="w-[18px] h-[18px] shrink-0" />
                {{ submitError }}
              </div>
            </form>
          </PublicCard>
        </div>
      </div>
    </section>
  </div>
</template>
