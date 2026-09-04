<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { sponsorshipService } from '@/services/sponsorship.service';
import { Handshake, Award, Building2, Check, ArrowRight, Heart, Send, AlertCircle } from 'lucide-vue-next';
import ScrollReveal from '@/components/shared/ScrollReveal.vue';
import PublicButton from '@/components/shared/PublicButton.vue';
import PublicCard from '@/components/shared/PublicCard.vue';

const loading = ref(true);
const opportunities = ref<any[]>([]);
const featuredPartners = ref<any[]>([]);

const formState = ref({
  organization_name: '',
  contact_name: '',
  contact_email: '',
  contact_phone: '',
  relationship_type: 'sponsor',
  message: '',
});

const isSubmitting = ref(false);
const isSuccess = ref(false);
const submitError = ref('');

onMounted(async () => {
  try {
    const res = await sponsorshipService.getPublicOpportunities();
    if (res?.success) {
      opportunities.value = res.data?.opportunities || [];
      featuredPartners.value = res.data?.featured_partners || [];
    }
  } catch (err) {
    console.error('Failed to load public sponsorship opportunities:', err);
  } finally {
    loading.value = false;
  }
});

const defaultBenefits = [
  {
    title: 'Brand Visibility & Exposure',
    description: 'Prominent logo placement on our website, newsletter, event banners, and social channels reaching 5,000+ students and alumni across Burnaby and Surrey campuses.',
    icon: Building2,
  },
  {
    title: 'Direct Student Engagement',
    description: 'Interact directly with students through booth presence at campus orientations, career networking panels, and sponsored social events.',
    icon: Award,
  },
  {
    title: 'Community Social Impact',
    description: 'Support Friday Jumu\'ah prayer facilities, campus Iftars, newcomer support guides, and student emergency care packages.',
    icon: Heart,
  },
];

const handleSubmit = async () => {
  isSubmitting.value = true;
  submitError.value = '';
  isSuccess.value = false;

  try {
    const result = await sponsorshipService.submitInquiry(formState.value);
    if (result.success) {
      isSuccess.value = true;
      formState.value = {
        organization_name: '',
        contact_name: '',
        contact_email: '',
        contact_phone: '',
        relationship_type: 'sponsor',
        message: '',
      };
    } else {
      submitError.value = result.message || 'Submission failed. Please try again.';
    }
  } catch (err: any) {
    submitError.value = err.response?.data?.message || 'Something went wrong. Please try again.';
  } finally {
    isSubmitting.value = false;
  }
};

const formatCurrency = (cents: number) => {
  return new Intl.NumberFormat('en-CA', {
    style: 'currency',
    currency: 'CAD',
    maximumFractionDigits: 0,
  }).format(cents / 100);
};
</script>

<template>
  <div class="bg-neutral-background min-h-screen overflow-x-hidden w-full">
    <!-- Hero Section -->
    <section class="relative py-24 sm:py-32 bg-primary text-white overflow-hidden border-b border-primary/10">
      <div class="absolute inset-0 pattern-islamic opacity-5" />
      <div class="absolute top-0 right-0 w-96 h-96 bg-secondary-light/10 blur-[120px] rounded-full pointer-events-none" />

      <div class="container-custom relative z-10">
        <div class="max-w-4xl space-y-8">
          <ScrollReveal direction="right">
            <span class="inline-flex items-center gap-2 px-4 py-2 bg-white/10 border border-white/20 rounded-full text-accent-gold font-bold uppercase tracking-widest text-[10px]">
              <Handshake class="w-3.5 h-3.5 text-accent-gold shrink-0" /> SFU MSA Corporate & Community Partnerships
            </span>
          </ScrollReveal>

          <ScrollReveal :delay="0.2">
            <h1 class="text-2xl sm:text-4xl md:text-6xl font-display font-bold leading-tight tracking-tight text-white break-words">
              Partner With the Largest <br class="hidden sm:inline" />
              <span class="text-accent-gold italic font-serif">Muslim Student Community at SFU.</span>
            </h1>
          </ScrollReveal>

          <ScrollReveal :delay="0.3">
            <p class="text-base sm:text-lg md:text-xl text-white/80 leading-relaxed max-w-2xl font-light">
              Connect your brand with thousands of students across Burnaby and Surrey campuses while supporting Friday Jumu'ah services, campus Musallas, Ramadan care, and student development.
            </p>
          </ScrollReveal>

          <ScrollReveal :delay="0.4">
            <div class="flex flex-wrap gap-4 pt-2">
              <a
                href="#partner-inquiry"
                class="bg-accent-gold text-primary font-extrabold px-8 py-3.5 rounded-full text-xs uppercase tracking-widest hover:bg-white transition-all shadow-premium hover:-translate-y-0.5 active:scale-95 flex items-center gap-2"
              >
                <span>Become a Partner</span>
                <ArrowRight class="w-4 h-4" />
              </a>
            </div>
          </ScrollReveal>
        </div>
      </div>
    </section>

    <!-- Value Proposition / Benefits Section -->
    <section class="py-20 bg-white border-b border-neutral-ivory">
      <div class="container-custom">
        <div class="max-w-3xl mb-16 space-y-3">
          <span class="text-xs font-bold uppercase tracking-[0.2em] text-primary">Why Partner With SFU MSA</span>
          <h2 class="text-2xl sm:text-3xl md:text-4xl font-display font-bold text-neutral-black break-words">
            Strategic Brand Impact & Campus Engagement
          </h2>
          <p class="text-sm sm:text-base text-neutral-black/60 leading-relaxed font-light">
            Our corporate and community partners gain authentic access to a highly engaged student demographic while enabling vital spiritual and social infrastructure.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <PublicCard
            v-for="(b, idx) in defaultBenefits"
            :key="idx"
            variant="premium"
            class="bg-neutral-background border border-neutral-ivory p-6 sm:p-8 space-y-5"
          >
            <div class="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center border border-primary/20">
              <component :is="b.icon" class="w-6 h-6" />
            </div>
            <h3 class="text-xl font-bold text-neutral-black">{{ b.title }}</h3>
            <p class="text-neutral-black/60 text-sm leading-relaxed font-light">{{ b.description }}</p>
          </PublicCard>
        </div>
      </div>
    </section>

    <!-- Dynamic Database Opportunities (Rendered Only If Configured in Backend) -->
    <section v-if="opportunities.length > 0" class="py-20 container-custom">
      <div class="max-w-3xl mb-12 space-y-3">
        <span class="text-xs font-bold uppercase tracking-[0.2em] text-primary">Active Event & Drive Opportunities</span>
        <h2 class="text-2xl sm:text-3xl md:text-4xl font-display font-bold text-neutral-black break-words">
          Featured Sponsorship Opportunities
        </h2>
      </div>

      <div class="space-y-12">
        <div
          v-for="opp in opportunities"
          :key="opp.uuid"
          class="bg-white rounded-3xl border border-neutral-ivory p-5 sm:p-8 shadow-soft space-y-6 overflow-hidden"
        >
          <div class="border-b border-neutral-ivory pb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <span class="text-[10px] font-bold uppercase tracking-widest text-primary bg-primary/10 px-3 py-1 rounded-full">
                {{ opp.opportunity_type }} Sponsorship
              </span>
              <h3 class="text-xl sm:text-2xl font-bold text-neutral-black mt-2 break-words">{{ opp.title }}</h3>
              <p v-if="opp.description" class="text-sm text-neutral-black/60 mt-1">{{ opp.description }}</p>
            </div>

            <a
              href="#partner-inquiry"
              class="border border-primary text-primary hover:bg-primary hover:text-white px-6 py-2.5 rounded-full text-xs font-bold uppercase tracking-widest transition-all self-start md:self-auto"
            >
              Inquire
            </a>
          </div>

          <!-- Opportunities Tier Grid -->
          <div v-if="opp.packages && opp.packages.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div
              v-for="pkg in opp.packages"
              :key="pkg.uuid"
              class="bg-neutral-background rounded-2xl p-5 sm:p-6 border border-neutral-ivory flex flex-col justify-between"
            >
              <div>
                <h4 class="text-lg font-bold text-neutral-black">{{ pkg.name }}</h4>
                <div class="mt-2 text-xl sm:text-2xl font-extrabold text-primary">
                  {{ formatCurrency(pkg.price_cents) }}
                  <span class="text-xs font-normal text-neutral-black/50">CAD</span>
                </div>
                <p v-if="pkg.description" class="text-xs text-neutral-black/60 mt-2">{{ pkg.description }}</p>
                <ul v-if="pkg.benefits" class="mt-4 space-y-2 text-xs text-neutral-black/80">
                  <li v-for="benefit in pkg.benefits" :key="benefit.id" class="flex items-start gap-2">
                    <Check class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                    <span>{{ benefit.title }}</span>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Partner Inquiry Form Section -->
    <section id="partner-inquiry" class="py-20 container-custom">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-16 items-start">
        <div class="space-y-6">
          <span class="text-xs font-bold uppercase tracking-[0.2em] text-primary">Get In Touch</span>
          <h2 class="text-3xl sm:text-4xl font-bold text-neutral-black font-display leading-tight break-words">
            Become an SFU MSA Sponsor or Partner
          </h2>
          <p class="text-neutral-black/60 leading-relaxed font-light text-sm sm:text-base">
            Complete our partnership application below. Our Partnerships & Sponsorship Team will review your organization details and reach out within 2 business days.
          </p>

          <div class="p-6 bg-white rounded-2xl border border-neutral-ivory shadow-soft space-y-2">
            <h4 class="font-bold text-primary text-sm">Need a custom collaboration?</h4>
            <p class="text-xs text-neutral-black/60 leading-relaxed font-light">
              We frequently coordinate custom food/catering sponsorships for Friday Chai, event venue support, student discount programs, and co-hosted career workshops.
            </p>
          </div>
        </div>

        <!-- Form Card -->
        <PublicCard variant="premium" class="bg-white p-5 sm:p-8 md:p-10 border border-neutral-ivory shadow-premium rounded-3xl w-full">
          <form @submit.prevent="handleSubmit" class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <div class="space-y-2">
                <label class="text-xs font-bold uppercase tracking-widest text-neutral-black/40">Company / Organization *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Acme Corporation"
                  v-model="formState.organization_name"
                  class="w-full bg-neutral-background border border-neutral-ivory rounded-xl px-4 py-3 text-xs focus:ring-2 focus:ring-primary/20 outline-none transition-all text-neutral-black"
                />
              </div>

              <div class="space-y-2">
                <label class="text-xs font-bold uppercase tracking-widest text-neutral-black/40">Contact Full Name *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Fatima Ahmed"
                  v-model="formState.contact_name"
                  class="w-full bg-neutral-background border border-neutral-ivory rounded-xl px-4 py-3 text-xs focus:ring-2 focus:ring-primary/20 outline-none transition-all text-neutral-black"
                />
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <div class="space-y-2">
                <label class="text-xs font-bold uppercase tracking-widest text-neutral-black/40">Work Email *</label>
                <input
                  type="email"
                  required
                  placeholder="fatima@acme.com"
                  v-model="formState.contact_email"
                  class="w-full bg-neutral-background border border-neutral-ivory rounded-xl px-4 py-3 text-xs focus:ring-2 focus:ring-primary/20 outline-none transition-all text-neutral-black"
                />
              </div>

              <div class="space-y-2">
                <label class="text-xs font-bold uppercase tracking-widest text-neutral-black/40">Phone Number</label>
                <input
                  type="tel"
                  placeholder="(604) 555-0199"
                  v-model="formState.contact_phone"
                  class="w-full bg-neutral-background border border-neutral-ivory rounded-xl px-4 py-3 text-xs focus:ring-2 focus:ring-primary/20 outline-none transition-all text-neutral-black"
                />
              </div>
            </div>

            <div class="space-y-2">
              <label class="text-xs font-bold uppercase tracking-widest text-neutral-black/40">Partnership Type</label>
              <select
                v-model="formState.relationship_type"
                class="w-full bg-neutral-background border border-neutral-ivory rounded-xl px-4 py-3 text-xs focus:ring-2 focus:ring-primary/20 outline-none transition-all text-neutral-black"
              >
                <option value="sponsor">Corporate Sponsor</option>
                <option value="in_kind_partner">In-Kind Partner (Food / Refreshments / Venue)</option>
                <option value="community_partner">Community / Non-Profit Partner</option>
                <option value="media_partner">Media & Promotional Partner</option>
                <option value="vendor_partner">Event Vendor Partner</option>
              </select>
            </div>

            <div class="space-y-2">
              <label class="text-xs font-bold uppercase tracking-widest text-neutral-black/40">Message / Goals *</label>
              <textarea
                required
                rows="4"
                placeholder="Tell us about your organization and what kind of partnership or event sponsorship you're interested in..."
                v-model="formState.message"
                class="w-full bg-neutral-background border border-neutral-ivory rounded-xl px-4 py-3 text-xs focus:ring-2 focus:ring-primary/20 outline-none transition-all text-neutral-black resize-none"
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
              {{ isSubmitting ? 'Submitting Application...' : 'Submit Partnership Application' }}
            </PublicButton>

            <!-- Success Alert -->
            <div
              v-if="isSuccess"
              class="p-4 bg-emerald-50 text-emerald-700 rounded-2xl text-center text-xs font-bold border border-emerald-300"
            >
              JazakAllah Khair! Your inquiry has been submitted. Our Partnerships Team will contact you shortly.
            </div>

            <!-- Error Alert -->
            <div
              v-if="submitError"
              class="flex items-center justify-center gap-2 p-4 bg-red-50 text-red-700 rounded-2xl text-center text-xs font-bold border border-red-300"
            >
              <AlertCircle class="w-4 h-4 shrink-0" />
              <span>{{ submitError }}</span>
            </div>
          </form>
        </PublicCard>
      </div>
    </section>
  </div>
</template>
