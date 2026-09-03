<script setup lang="ts">
import { ref, computed } from 'vue';
import { donationsService } from '@/services/donations.service';
import { useToastStore } from '@/components/feedback/toast';
import { HERO_IMAGES } from '@/constants/publicAssets';
import {
  Heart,
  ShieldCheck,
  Lock,
  HelpCircle,
  ChevronDown,
} from 'lucide-vue-next';
import ScrollReveal from '@/components/shared/ScrollReveal.vue';

const toast = useToastStore();

const presetAmounts = [10, 25, 50, 100, 250, 500];
const selectedPreset = ref<number | null>(50);
const customAmount = ref<string>('');

const donorName = ref('');
const donorEmail = ref('');
const isAnonymous = ref(false);
const dedication = ref('');
const isLoading = ref(false);
const errorMessage = ref('');

// FAQ Toggle States
const openFaqIndex = ref<number | null>(0);
const toggleFaq = (index: number) => {
  openFaqIndex.value = openFaqIndex.value === index ? null : index;
};

const faqs = [
  {
    question: 'Is my donation processed securely?',
    answer: 'Yes! All transactions are securely processed via Square API v2 with end-to-end 256-bit SSL encryption. We do not store credit card details on our servers.',
  },
  {
    question: 'Can I choose to remain anonymous?',
    answer: 'Absolutely. Check the "Make this donation anonymous" box during checkout. Your name and email will be kept private from public donor rosters and website summaries.',
  },
  {
    question: 'Will I receive a receipt for my contribution?',
    answer: 'Yes! An instant payment confirmation receipt with your unique donation transaction reference will be delivered directly to your email address upon successful processing.',
  },
];

const effectiveAmountCents = computed(() => {
  if (selectedPreset.value !== null) {
    return selectedPreset.value * 100;
  }
  const parsed = parseFloat(customAmount.value);
  if (isNaN(parsed) || parsed <= 0) return 0;
  return Math.round(parsed * 100);
});

const selectPreset = (amount: number) => {
  selectedPreset.value = amount;
  customAmount.value = '';
};

const handleCustomAmountInput = () => {
  selectedPreset.value = null;
};

const handleDonate = async () => {
  errorMessage.value = '';

  if (effectiveAmountCents.value < 100) {
    errorMessage.value = 'Please select or enter a valid donation amount of at least $1.00 CAD.';
    return;
  }
  if (!donorName.value.trim()) {
    errorMessage.value = 'Please enter your full name.';
    return;
  }
  if (!donorEmail.value.trim() || !donorEmail.value.includes('@')) {
    errorMessage.value = 'Please enter a valid email address.';
    return;
  }

  isLoading.value = true;
  try {
    const response = await donationsService.createCheckout({
      donor_name: donorName.value.trim(),
      donor_email: donorEmail.value.trim(),
      amount_cents: effectiveAmountCents.value,
      is_anonymous: isAnonymous.value,
      dedication: dedication.value.trim() || undefined,
    });

    if (response.checkout_url) {
      window.location.href = response.checkout_url;
    } else {
      toast.success('Donation initiated successfully!');
    }
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || 'Failed to initiate donation. Please try again.';
    toast.error(errorMessage.value);
  } finally {
    isLoading.value = false;
  }
};
</script>

<template>
  <div class="bg-neutral-background min-h-screen font-sans selection:bg-accent-gold/30 selection:text-primary w-full">
    <!-- ========================================== -->
    <!-- 1. HERO SECTION -->
    <!-- ========================================== -->
    <section class="relative min-h-[55vh] sm:min-h-[62vh] flex items-center justify-center pt-32 sm:pt-40 pb-20 sm:pb-24 overflow-hidden bg-primary text-white text-center w-full">
      <!-- Background Image & Overlay -->
      <div class="absolute inset-0 z-0">
        <img
          :src="HERO_IMAGES.jumuahPrayer"
          class="w-full h-full object-cover opacity-25 grayscale-[15%] scale-105"
          alt="SFU MSA Community"
          referrerpolicy="no-referrer"
        />
        <div class="absolute inset-0 bg-gradient-to-b from-primary/95 via-primary/85 to-primary z-10" />
        <div class="absolute top-0 right-0 w-96 h-96 bg-accent-gold/10 rounded-full blur-3xl opacity-50 z-10" />
      </div>

      <div class="container-custom relative z-20 flex flex-col items-center justify-center w-full">
        <div class="max-w-4xl w-full mx-auto text-center space-y-6 sm:space-y-8 flex flex-col items-center">
          <!-- Hero Badge -->
          <ScrollReveal direction="down" width="100%" class="w-full flex justify-center">
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 text-accent-gold font-bold text-xs sm:text-sm uppercase tracking-widest border border-white/20 backdrop-blur-md shadow-soft">
              <Heart class="w-4 h-4 fill-accent-gold/30 text-accent-gold" />
              <span>SFU MSA Community Giving</span>
            </div>
          </ScrollReveal>

          <!-- Hero Main Title -->
          <ScrollReveal :delay="0.1" width="100%" class="w-full flex justify-center">
            <h1 class="text-4xl sm:text-6xl md:text-7xl font-display font-bold text-white tracking-tight leading-tight">
              Support Your Community <br class="hidden sm:block" />
              <span class="italic font-serif text-accent-gold underline decoration-accent-gold/40 underline-offset-8">with Pride</span>
            </h1>
          </ScrollReveal>

          <!-- Subtitle / Qur'anic Verse Callout -->
          <ScrollReveal :delay="0.2" width="100%" class="w-full flex justify-center">
            <p class="text-base sm:text-xl text-white/80 max-w-2xl mx-auto font-light leading-relaxed">
              “The example of those who spend their wealth in the way of Allah is like a seed of grain which grows seven ears; in each ear is a hundred grains.”
              <span class="block text-xs uppercase tracking-widest font-bold text-accent-gold mt-2">— Surah Al-Baqarah 2:261</span>
            </p>
          </ScrollReveal>

          <!-- Impact Stat Badges -->
          <ScrollReveal :delay="0.3" width="100%" class="w-full flex justify-center">
            <div class="pt-4 grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-3xl mx-auto w-full">
              <div class="p-4 rounded-2xl bg-white/10 border border-white/15 backdrop-blur-md text-center">
                <p class="text-2xl sm:text-3xl font-display font-bold text-accent-gold">1,000+</p>
                <p class="text-xs text-white/70 font-medium">Students Served Weekly</p>
              </div>
              <div class="p-4 rounded-2xl bg-white/10 border border-white/15 backdrop-blur-md text-center">
                <p class="text-2xl sm:text-3xl font-display font-bold text-accent-gold">52 Gatherings</p>
                <p class="text-xs text-white/70 font-medium">Friday Jumu'ah & Community Gatherings</p>
              </div>
              <div class="p-4 rounded-2xl bg-white/10 border border-white/15 backdrop-blur-md text-center">
                <p class="text-2xl sm:text-3xl font-display font-bold text-accent-gold">100%</p>
                <p class="text-xs text-white/70 font-medium">Volunteer & Student Driven</p>
              </div>
            </div>
          </ScrollReveal>
        </div>
      </div>
    </section>

    <!-- ========================================== -->
    <!-- 2. WIDE & CENTERED DONATION CONSOLE -->
    <!-- ========================================== -->
    <div class="section-padding space-y-24 w-full">
      <div class="container-custom flex flex-col items-center justify-center w-full">
        <div class="max-w-6xl w-full mx-auto flex flex-col items-center justify-center">
          <ScrollReveal direction="up" :delay="0.1" width="100%" class="w-full flex justify-center">
            <div class="bg-white border border-neutral-ivory rounded-3xl p-6 sm:p-14 shadow-2xl space-y-8 relative overflow-hidden w-full max-w-6xl mx-auto">
              <div class="absolute top-0 right-0 w-40 h-40 bg-accent-gold/10 rounded-bl-full pointer-events-none" />

              <!-- Form Header (Centered) -->
              <div class="space-y-3 border-b border-neutral-ivory/60 pb-6 text-center flex flex-col items-center justify-center w-full">
                <span class="inline-flex items-center gap-1.5 text-xs font-bold text-neutral-muted bg-neutral-background px-4 py-1.5 rounded-full border border-neutral-ivory">
                  <Lock class="w-3.5 h-3.5 text-primary" /> 256-Bit SSL Encrypted • CAD Currency
                </span>
                <h2 class="text-3xl sm:text-4xl font-display font-bold text-primary">Make a Donation</h2>
                <p class="text-xs sm:text-sm text-neutral-muted max-w-lg mx-auto text-center">Select a preset amount or enter a custom contribution to support SFU student initiatives.</p>
              </div>

              <!-- Amount Selection Grid (Full Width) -->
              <div class="space-y-4 w-full">
                <label class="block text-xs font-bold uppercase tracking-wider text-neutral-black text-center">
                  1. Select Donation Amount (CAD)
                </label>
                <div class="grid grid-cols-2 sm:grid-cols-6 gap-3.5 w-full">
                  <button
                    v-for="amt in presetAmounts"
                    :key="amt"
                    type="button"
                    @click="selectPreset(amt)"
                    :class="[
                      'py-4 px-4 text-center rounded-2xl border transition-all cursor-pointer select-none font-bold text-lg w-full',
                      selectedPreset === amt
                        ? 'bg-primary text-white border-primary shadow-lg scale-102 ring-2 ring-primary/20'
                        : 'bg-neutral-background/60 text-neutral-black border-neutral-ivory hover:border-primary/40 hover:bg-neutral-background'
                    ]"
                  >
                    ${{ amt }}
                  </button>
                </div>

                <!-- Custom Amount Input (Full Width) -->
                <div class="space-y-1.5 pt-2 w-full text-center">
                  <label class="block text-xs font-semibold text-neutral-muted text-center">Or enter a custom donation amount (CAD)</label>
                  <div class="relative w-full">
                    <span class="absolute left-4 top-3.5 text-sm font-bold text-primary">$</span>
                    <input
                      v-model="customAmount"
                      @input="handleCustomAmountInput"
                      type="number"
                      step="0.01"
                      min="1"
                      placeholder="Enter custom amount (min $1.00 CAD)"
                      class="w-full pl-8 pr-16 py-3.5 text-sm rounded-2xl border border-neutral-ivory focus:border-primary focus:ring-2 focus:ring-primary/10 focus:outline-none bg-neutral-background/40 font-bold text-neutral-black text-center"
                    />
                    <span class="absolute right-4 top-3.5 text-xs font-bold uppercase tracking-wider text-neutral-muted">CAD</span>
                  </div>
                </div>

                <!-- Selected Amount Summary Badge (Full Width) -->
                <div class="p-4 rounded-xl bg-primary/5 border border-primary/10 flex items-center justify-between text-sm sm:text-base w-full">
                  <span class="font-semibold text-neutral-black">Total Contribution:</span>
                  <span class="text-2xl font-bold font-display text-primary">${{ (effectiveAmountCents / 100).toFixed(2) }} CAD</span>
                </div>
              </div>

              <!-- Donor Details Form (Full Width) -->
              <div class="space-y-4 border-t border-neutral-ivory/60 pt-6 w-full">
                <label class="block text-xs font-bold uppercase tracking-wider text-neutral-black text-center">
                  2. Donor Information
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 w-full">
                  <div>
                    <label class="block text-xs font-semibold text-neutral-muted mb-1 text-left">Full Name *</label>
                    <input
                      v-model="donorName"
                      type="text"
                      placeholder="e.g. Fatima Ahmed"
                      class="w-full px-4 py-3 text-sm rounded-xl border border-neutral-ivory focus:border-primary focus:ring-2 focus:ring-primary/10 focus:outline-none bg-neutral-background/40"
                    />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-neutral-muted mb-1 text-left">Email Address *</label>
                    <input
                      v-model="donorEmail"
                      type="email"
                      placeholder="fatima@sfu.ca"
                      class="w-full px-4 py-3 text-sm rounded-xl border border-neutral-ivory focus:border-primary focus:ring-2 focus:ring-primary/10 focus:outline-none bg-neutral-background/40"
                    />
                  </div>
                </div>

                <!-- Optional Dedication Message (Full Width) -->
                <div class="w-full">
                  <label class="block text-xs font-semibold text-neutral-muted mb-1 text-left">Optional Dedication / Note</label>
                  <textarea
                    v-model="dedication"
                    rows="2"
                    placeholder="e.g. Dedicated to Friday Jumu'ah Chai or in memory of family..."
                    class="w-full px-4 py-3 text-sm rounded-xl border border-neutral-ivory focus:border-primary focus:ring-2 focus:ring-primary/10 focus:outline-none bg-neutral-background/40"
                  ></textarea>
                </div>

                <!-- Anonymous Checkbox (Full Width) -->
                <div class="w-full">
                  <label class="flex items-center gap-3 cursor-pointer select-none p-3.5 rounded-xl bg-neutral-background/60 border border-neutral-ivory hover:border-primary/30 transition-colors w-full">
                    <input
                      v-model="isAnonymous"
                      type="checkbox"
                      class="h-4 w-4 rounded border-neutral-ivory text-primary focus:ring-primary cursor-pointer"
                    />
                    <div class="text-xs text-left">
                      <span class="font-bold text-neutral-black">Make this donation anonymous</span>
                      <span class="block text-neutral-muted">Your name and email will be hidden from public rosters.</span>
                    </div>
                  </label>
                </div>
              </div>

              <!-- Error Alert -->
              <div v-if="errorMessage" class="p-4 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl font-medium flex items-center justify-center gap-2 w-full">
                <HelpCircle class="w-4 h-4 shrink-0 text-red-500" />
                <span>{{ errorMessage }}</span>
              </div>

              <!-- Submit Button (Full Width) -->
              <div class="w-full">
                <button
                  type="button"
                  @click="handleDonate"
                  :disabled="isLoading || effectiveAmountCents < 100"
                  class="w-full py-4 px-6 bg-primary hover:bg-primary-hover text-white text-base sm:text-lg font-bold rounded-2xl shadow-xl hover:shadow-primary/25 transition-all duration-200 cursor-pointer disabled:opacity-50 flex items-center justify-center gap-3"
                >
                  <span v-if="isLoading" class="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></span>
                  <template v-else>
                    <Heart class="w-5 h-5 text-accent-gold fill-accent-gold/20" />
                    <span>Donate ${{ (effectiveAmountCents / 100).toFixed(2) }} CAD via Square</span>
                  </template>
                </button>
              </div>

              <div class="flex flex-wrap items-center justify-center gap-4 text-[11px] sm:text-xs text-neutral-muted border-t border-neutral-ivory/60 pt-4 w-full">
                <span class="flex items-center gap-1"><Lock class="w-3.5 h-3.5 text-primary" /> Square 256-bit SSL</span>
                <span>•</span>
                <span class="flex items-center gap-1"><ShieldCheck class="w-3.5 h-3.5 text-primary" /> Instant Email Receipt</span>
              </div>
            </div>
          </ScrollReveal>
        </div>
      </div>

      <!-- ========================================== -->
      <!-- 3. CENTERED FREQUENTLY ASKED QUESTIONS (FAQ) -->
      <!-- ========================================== -->
      <div class="container-custom flex flex-col items-center justify-center w-full">
        <div class="max-w-6xl w-full mx-auto flex flex-col items-center justify-center">
          <ScrollReveal direction="up" width="100%" class="w-full flex justify-center">
            <div class="space-y-10 text-center flex flex-col items-center justify-center w-full max-w-6xl mx-auto">
              <div class="space-y-3 text-center flex flex-col items-center justify-center w-full">
                <span class="text-xs font-black uppercase tracking-widest text-primary bg-primary/10 px-4 py-1.5 rounded-full inline-block">
                  Questions & Answers
                </span>
                <h2 class="text-3xl sm:text-4xl font-display font-bold text-primary">
                  Frequently Asked Questions
                </h2>
                <p class="text-neutral-black/60 text-sm sm:text-base max-w-xl mx-auto text-center">
                  Everything you need to know about donating to the SFU Muslim Students Association.
                </p>
              </div>

              <!-- FAQ Accordion Container (Exact same max-w-5xl width as form card) -->
              <div class="space-y-4 text-left w-full">
                <div
                  v-for="(faq, idx) in faqs"
                  :key="faq.question"
                  class="bg-white border border-neutral-ivory rounded-2xl overflow-hidden shadow-soft w-full"
                >
                  <button
                    type="button"
                    @click="toggleFaq(idx)"
                    class="w-full px-6 py-5 text-left font-bold text-base sm:text-lg text-primary flex items-center justify-between gap-4 cursor-pointer hover:bg-neutral-background/40 transition-colors"
                  >
                    <span>{{ faq.question }}</span>
                    <ChevronDown
                      :class="[
                        'w-5 h-5 text-primary shrink-0 transition-transform duration-200',
                        openFaqIndex === idx ? 'rotate-180 text-accent-gold' : ''
                      ]"
                    />
                  </button>

                  <div
                    v-if="openFaqIndex === idx"
                    class="px-6 pb-6 text-sm sm:text-base text-neutral-black/70 leading-relaxed border-t border-neutral-ivory/60 pt-4"
                  >
                    {{ faq.answer }}
                  </div>
                </div>
              </div>
            </div>
          </ScrollReveal>
        </div>
      </div>
    </div>
  </div>
</template>
