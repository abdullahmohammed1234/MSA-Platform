<script setup lang="ts">
import { ref, computed } from 'vue';
import { 
  Heart, 
  Sparkles, 
  BookOpen, 
  MapPin, 
  Coffee
} from 'lucide-vue-next';
import ScrollReveal from '@/components/shared/ScrollReveal.vue';
import PublicButton from '@/components/shared/PublicButton.vue';
import PublicCard from '@/components/shared/PublicCard.vue';

const frequencies = ['One-time', 'Monthly'];
const selectedFrequency = ref('One-time');

const presetAmounts = [10, 25, 50, 100, 250];
const selectedAmount = ref<number | 'custom'>(50);
const customAmount = ref<number | null>(null);

const finalAmount = computed(() => {
  if (selectedAmount.value === 'custom') {
    return customAmount.value || 0;
  }
  return selectedAmount.value;
});

const isSimulating = ref(false);
const isSuccess = ref(false);

const getImpactMessage = computed(() => {
  const amount = finalAmount.value;
  if (amount <= 0) return 'Enter an amount to see its impact.';
  if (amount < 25) return 'Funds books and resources for study circles.';
  if (amount < 50) return 'Provides a warm, catered meal for a student at Jumu\'ah or iftar.';
  if (amount < 100) return 'Covers weekly cleaning and maintenance supplies for campus musallas.';
  if (amount < 250) return 'Supports purchase of high-quality prayer mats and physical room furnishings.';
  return 'Helps fund speaker travel and hall rentals for major campus lectures and seminars.';
});

const handleDonate = () => {
  if (finalAmount.value <= 0) return;
  isSimulating.value = true;
  setTimeout(() => {
    isSimulating.value = false;
    isSuccess.value = true;
    setTimeout(() => {
      isSuccess.value = false;
    }, 5000);
  }, 2000);
};

const impacts = [
  {
    title: 'Maintain Musallas',
    description: 'We keep our campus prayer rooms furnished, fully stocked with clean prayer mats, Quran copies, and hygiene supplies.',
    icon: MapPin
  },
  {
    title: 'Free Iftars & Dinners',
    description: 'We host multiple free communal dinners and provide daily iftars for hundreds of students during Ramadan.',
    icon: Coffee
  },
  {
    title: 'Educational Material',
    description: 'Donations fund academic seminar guest speakers, books, and booklets distributed to members and interest inquiries.',
    icon: BookOpen
  }
];
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
              <Heart class="w-3 h-3 text-accent-gold" /> Sadaqah & Giving
            </span>
          </ScrollReveal>

          <ScrollReveal :delay="0.2">
            <h1 class="text-5xl md:text-[92px] font-display font-medium leading-[0.9] tracking-tighter text-neutral-white">
              Support our <span class="text-accent-gold italic font-serif">Mission &</span> <br />
              serve the <span class="text-accent-gold italic font-serif">Community.</span>
            </h1>
          </ScrollReveal>

          <ScrollReveal :delay="0.3">
            <p class="text-xl md:text-2xl text-neutral-white/70 leading-relaxed max-w-2xl font-light">
              Your charitable contributions (Sadaqah Jariyah) directly fund physical prayer facilities, educational events, and community support networks for students.
            </p>
          </ScrollReveal>
        </div>
      </div>
    </section>

    <!-- Impact Areas -->
    <section class="section-padding">
      <div class="container-custom">
        <div class="max-w-3xl mb-16 space-y-4">
          <h2 class="text-4xl md:text-5xl font-display font-bold text-primary">
            How Your Donation Helps
          </h2>
          <p class="text-lg leading-relaxed text-neutral-black/60">
            SFU MSA operates completely on community support and student levies. Every dollar goes directly back to serving students.
          </p>
        </div>

        <div class="grid md:grid-cols-3 gap-8">
          <PublicCard
            v-for="(imp, idx) in impacts"
            :key="idx"
            variant="premium"
            class="bg-neutral-white border border-neutral-gray/20 p-8 space-y-6"
          >
            <div class="w-12 h-12 rounded-xl bg-primary/5 text-primary flex items-center justify-center border border-primary/10">
              <component :is="imp.icon" class="w-6 h-6" />
            </div>
            <h3 class="text-xl font-bold text-primary">
              {{ imp.title }}
            </h3>
            <p class="text-neutral-black/50 text-sm leading-relaxed font-light">
              {{ imp.description }}
            </p>
          </PublicCard>
        </div>
      </div>
    </section>

    <!-- Donation Portal (Form + Selector) -->
    <section class="section-padding bg-neutral-white border-y border-neutral-gray/20">
      <div class="container-custom">
        <div class="grid lg:grid-cols-2 gap-16 items-start">
          <div class="space-y-6 max-w-lg">
            <h2 class="text-4xl font-bold text-primary font-display">
              Make a Contribution
            </h2>
            <p class="text-neutral-black/60 leading-relaxed font-light">
              Select an amount and frequency. Your donation is processed securely. 
            </p>
            <blockquote class="p-6 bg-neutral-background rounded-2xl border border-neutral-gray/20 border-l-4 border-l-primary italic text-neutral-black/70">
              "The shade of the believer on the Day of Resurrection will be their charity."
              <span class="block not-italic text-xs font-bold text-primary uppercase tracking-wider mt-2">— Hadith (Al-Tirmidhi)</span>
            </blockquote>
          </div>

          <!-- Donation Selector Box -->
          <PublicCard variant="premium" class="bg-neutral-background p-8 md:p-12 border border-neutral-gray/20 shadow-premium">
            <div class="space-y-8">
              <!-- Frequency selector -->
              <div class="space-y-3">
                <label class="text-xs font-bold uppercase tracking-widest text-neutral-black/30 font-sans">Frequency</label>
                <div class="grid grid-cols-2 gap-2 bg-neutral-white p-1.5 rounded-2xl border border-neutral-gray/20">
                  <button
                    v-for="freq in frequencies"
                    :key="freq"
                    @click="selectedFrequency = freq"
                    class="py-3 rounded-xl text-xs font-bold uppercase tracking-wider transition-all"
                    :class="selectedFrequency === freq ? 'bg-primary text-neutral-white shadow-sm' : 'text-neutral-black/40 hover:text-primary'"
                  >
                    {{ freq }}
                  </button>
                </div>
              </div>

              <!-- Preset amounts -->
              <div class="space-y-3">
                <label class="text-xs font-bold uppercase tracking-widest text-neutral-black/30 font-sans">Donation Amount ($)</label>
                <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                  <button
                    v-for="amt in presetAmounts"
                    :key="amt"
                    @click="selectedAmount = amt"
                    class="py-3 rounded-xl border font-display text-lg transition-all"
                    :class="selectedAmount === amt 
                      ? 'bg-primary border-primary text-neutral-white font-bold shadow-sm' 
                      : 'bg-neutral-white border-neutral-gray/20 text-neutral-black hover:border-primary/50'"
                  >
                    ${{ amt }}
                  </button>
                  <button
                    @click="selectedAmount = 'custom'"
                    class="py-3 rounded-xl border text-xs font-bold uppercase tracking-widest transition-all col-span-1 sm:col-span-1"
                    :class="selectedAmount === 'custom'
                      ? 'bg-primary border-primary text-neutral-white font-bold shadow-sm' 
                      : 'bg-neutral-white border-neutral-gray/20 text-neutral-black hover:border-primary/50'"
                  >
                    Custom
                  </button>
                </div>
              </div>

              <!-- Custom input field -->
              <div v-if="selectedAmount === 'custom'" class="space-y-2">
                <label class="text-xs font-bold uppercase tracking-widest text-neutral-black/30 font-sans">Enter Custom Amount</label>
                <div class="relative">
                  <span class="absolute left-6 top-1/2 -translate-y-1/2 text-neutral-black/40 font-display text-lg">$</span>
                  <input
                    type="number"
                    min="1"
                    placeholder="Other amount"
                    v-model.number="customAmount"
                    class="w-full bg-neutral-white border border-neutral-gray/20 rounded-2xl pl-12 pr-6 py-4 focus:ring-2 focus:ring-primary/20 outline-none transition-all text-neutral-black text-sm"
                  />
                </div>
              </div>

              <!-- Impact estimator display -->
              <div class="p-6 bg-neutral-white rounded-2xl border border-neutral-gray/10 space-y-2">
                <div class="text-[10px] font-black uppercase tracking-wider text-secondary-light flex items-center gap-1.5">
                  <Sparkles class="w-3.5 h-3.5" /> Est. Giving Impact
                </div>
                <p class="text-sm text-neutral-black/60 font-light leading-relaxed">
                  {{ getImpactMessage }}
                </p>
              </div>

              <!-- Donate Submit Button -->
              <PublicButton 
                variant="primary" 
                size="lg" 
                class="w-full justify-center shadow-lg shadow-primary/10"
                @click="handleDonate"
                :disabled="isSimulating || finalAmount <= 0"
              >
                {{ isSimulating ? "Processing securely..." : `Donate $${finalAmount} ${selectedFrequency === 'Monthly' ? '/ month' : ''}` }}
              </PublicButton>

              <!-- Success Alert -->
              <div 
                v-if="isSuccess" 
                class="p-4 bg-emerald-50 text-emerald-600 rounded-2xl text-center text-sm font-medium border border-emerald-500/20"
              >
                Jazakullah Khair! Thank you for your support. (Note: This is currently a simulated donation gateway flow).
              </div>
            </div>
          </PublicCard>
        </div>
      </div>
    </section>

    <!-- Legal & Tax Info -->
    <section class="section-padding">
      <div class="container-custom max-w-4xl mx-auto space-y-8 text-neutral-black text-center">
        <h3 class="text-2xl font-bold text-primary">Donation Transparency</h3>
        <p class="text-neutral-black/50 text-sm leading-relaxed max-w-2xl mx-auto font-light">
          The SFU Muslim Students Association is a student organization registered under the Simon Fraser Student Society (SFSS). For large endowment gifts, corporate matches, or official tax receipts, please contact our financial officer.
        </p>
        <div class="flex justify-center gap-4 text-xs font-bold uppercase tracking-widest text-primary">
          <span>Registered Student Group ID: #4052</span>
          <span class="text-neutral-gray">•</span>
          <span>Security: SSL Encrypted Transaction Vaults</span>
        </div>
      </div>
    </section>
  </div>
</template>
