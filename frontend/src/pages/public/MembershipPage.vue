<script setup lang="ts">
import { ref } from 'vue';
import { 
  Users, 
  BookOpen, 
  Calendar, 
  ShieldCheck, 
  HelpCircle,
  Plus,
  ChevronDown
} from 'lucide-vue-next';
import ScrollReveal from '@/components/shared/ScrollReveal.vue';
import PublicButton from '@/components/shared/PublicButton.vue';
import PublicCard from '@/components/shared/PublicCard.vue';

const benefits = [
  {
    title: 'Exclusive Events',
    description: 'Get priority RSVP and discounted tickets for our major events, social dinners, and academic seminars.',
    icon: Calendar
  },
  {
    title: 'Dawah Academy Discounts',
    description: 'Access premium classes, specialized study circles, and text materials at discounted rates or for free.',
    icon: BookOpen
  },
  {
    title: 'Voting Rights',
    description: 'Actively shape the future of the MSA. Cast your vote in executive board elections and at our AGMs.',
    icon: ShieldCheck
  },
  {
    title: 'Community Network',
    description: 'Connect with a vast network of current SFU students, academic mentors, and professional alumni.',
    icon: Users
  }
];

const steps = [
  {
    number: '01',
    title: 'Create Account',
    description: 'Fill out the online registration form with your details and SFU email address.'
  },
  {
    number: '02',
    title: 'Verify Email',
    description: 'Click the verification link sent to your inbox to activate your student membership profile.'
  },
  {
    number: '03',
    title: 'Join Channels',
    description: 'Gain access to our private WhatsApp announcement community and Discord study groups.'
  },
  {
    number: '04',
    title: 'Attend Socials',
    description: 'Come out to our start-of-semester orientations and welcome lunches to meet the team.'
  }
];

const faqs = ref([
  {
    question: 'Is SFU MSA membership free?',
    answer: 'Yes, membership is completely free for all active Simon Fraser University undergraduate and graduate students.',
    isOpen: false
  },
  {
    question: 'Can non-SFU students or alumni join?',
    answer: 'Absolutely! Non-SFU students and alumni are welcome to join as Associate Members. While they can participate in all events and programs, voting rights at elections are restricted to active SFU students.',
    isOpen: false
  },
  {
    question: 'How do I renew my membership?',
    answer: 'Memberships are active for the academic year (September to August). A quick renewal form will be shared at the start of each Fall semester.',
    isOpen: false
  },
  {
    question: 'How can I apply for volunteer or executive roles?',
    answer: 'We open volunteer applications at the beginning of each semester. Executive board applications and elections take place annually in March/April.',
    isOpen: false
  }
]);

const toggleFaq = (index: number) => {
  faqs.value[index].isOpen = !faqs.value[index].isOpen;
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
              <Plus class="w-3 h-3" /> Connect with the Community
            </span>
          </ScrollReveal>

          <ScrollReveal :delay="0.2">
            <h1 class="text-5xl md:text-[92px] font-display font-medium leading-[0.9] tracking-tighter text-neutral-white">
              Become a <span class="text-accent-gold italic font-serif">Member</span> <br />
              of the SFU <span class="text-accent-gold italic font-serif">MSA.</span>
            </h1>
          </ScrollReveal>

          <ScrollReveal :delay="0.3">
            <p class="text-xl md:text-2xl text-neutral-white/70 leading-relaxed max-w-2xl font-light">
              Join a supportive brotherhood and sisterhood on campus. Gain access to resources, build friendships, and make the most of your university experience.
            </p>
          </ScrollReveal>
        </div>
      </div>
    </section>

    <!-- Membership Benefits -->
    <section class="section-padding">
      <div class="container-custom">
        <div class="max-w-3xl mb-16 space-y-4">
          <h2 class="text-4xl md:text-5xl font-display font-bold text-primary">
            Membership Benefits
          </h2>
          <p class="text-lg leading-relaxed text-neutral-black/60">
            What you get when you sign up to be a member of the SFU Muslim Students Association.
          </p>
        </div>

        <div class="grid md:grid-cols-2 gap-8">
          <PublicCard
            v-for="(benefit, idx) in benefits"
            :key="idx"
            variant="premium"
            class="bg-neutral-white border border-neutral-gray/20 shadow-soft group hover:border-primary/30 transition-all p-8"
          >
            <div class="flex items-start gap-6">
              <div class="w-12 h-12 rounded-xl bg-primary/5 text-primary flex items-center justify-center border border-primary/10 group-hover:scale-110 transition-transform shrink-0">
                <component :is="benefit.icon" class="w-6 h-6" />
              </div>
              <div class="space-y-2">
                <h3 class="text-xl font-bold text-primary group-hover:text-secondary-light transition-colors">
                  {{ benefit.title }}
                </h3>
                <p class="text-neutral-black/50 text-sm leading-relaxed font-light">
                  {{ benefit.description }}
                </p>
              </div>
            </div>
          </PublicCard>
        </div>
      </div>
    </section>

    <!-- How to Join (Steps) -->
    <section class="section-padding bg-neutral-white border-y border-neutral-gray/20">
      <div class="container-custom">
        <div class="max-w-3xl mb-16 space-y-4">
          <h2 class="text-4xl md:text-5xl font-display font-bold text-primary">
            How to Register
          </h2>
          <p class="text-lg leading-relaxed text-neutral-black/60">
            A simple, four-step process to activate your student membership.
          </p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8">
          <div
            v-for="(step, idx) in steps"
            :key="idx"
            class="space-y-4 relative group"
          >
            <div class="text-5xl md:text-6xl font-display font-light text-primary/15 group-hover:text-secondary-light/35 transition-colors">
              {{ step.number }}
            </div>
            <h4 class="font-bold text-lg text-primary">{{ step.title }}</h4>
            <p class="text-neutral-black/50 text-sm leading-relaxed font-light">
              {{ step.description }}
            </p>
          </div>
        </div>

        <!-- Registration CTA Button -->
        <div class="mt-16 text-center">
          <router-link to="/register">
            <PublicButton variant="primary" size="lg" class="shadow-xl shadow-primary/20">
              Start Registration
            </PublicButton>
          </router-link>
        </div>
      </div>
    </section>

    <!-- FAQs Section -->
    <section class="section-padding">
      <div class="container-custom">
        <div class="max-w-3xl mb-16 space-y-4">
          <h2 class="text-4xl md:text-5xl font-display font-bold text-primary">
            Frequently Asked Questions
          </h2>
          <p class="text-lg leading-relaxed text-neutral-black/60">
            Got questions about becoming a member? We have answers.
          </p>
        </div>

        <div class="max-w-4xl mx-auto space-y-4 text-neutral-black">
          <div 
            v-for="(faq, idx) in faqs" 
            :key="idx"
            class="border border-neutral-gray/20 rounded-2xl bg-neutral-white overflow-hidden transition-all duration-300"
            :class="faq.isOpen ? 'border-primary/30 ring-1 ring-primary/10' : ''"
          >
            <button
              @click="toggleFaq(idx)"
              class="w-full flex items-center justify-between p-6 text-left hover:bg-neutral-background transition-colors focus:outline-none"
            >
              <span class="font-bold text-primary text-base md:text-lg flex items-center gap-3">
                <HelpCircle class="w-5 h-5 text-secondary-light shrink-0" />
                {{ faq.question }}
              </span>
              <ChevronDown 
                class="w-5 h-5 text-neutral-black/40 transition-transform duration-300 shrink-0" 
                :class="faq.isOpen ? 'rotate-180 text-primary' : ''"
              />
            </button>
            
            <div 
              v-show="faq.isOpen"
              class="px-6 pb-6 pt-2 border-t border-neutral-gray/10 text-neutral-black/60 leading-relaxed font-light text-sm"
            >
              {{ faq.answer }}
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>
