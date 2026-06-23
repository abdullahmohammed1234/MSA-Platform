<script setup lang="ts">
import { ref } from 'vue';
import { 
  AlertCircle, Mail, Phone, MapPin, Send, MessageSquare, 
  CheckCircle2, ChevronDown, Instagram, Facebook, Plus 
} from 'lucide-vue-next';
import { Presence } from '@motionone/vue';
import ScrollReveal from '@/components/shared/ScrollReveal.vue';
import FloatingElement from '@/components/shared/FloatingElement.vue';
import { useSeo } from '@/composables/useSeo';
import websiteService from '@/services/website/websiteService';

useSeo({
  title: 'Contact Us | SFU MSA',
  description: 'Get in touch with Simon Fraser University Muslim Students Association. Find our room location, send a message to our departments, or read our FAQs.'
});

const faqs = [
  {
    question: 'Where is the MSA room located?',
    answer: 'The SFU MSA Prayer Room (Musalla) is located at the Burnaby Campus in the Student Union Building (SUB), room 3210. It is open to all students for prayer and contemplation.'
  },
  {
    question: 'How can I join the SFU MSA?',
    answer: 'Joining is easy! Simply follow our social media channels and sign up for our newsletter to stay updated on events. You can also apply to be a volunteer through our Volunteer page.'
  },
  {
    question: 'Do you have programs for both brothers and sisters?',
    answer: 'Yes! We host various inclusive events as well as gender-specific programming like Sisters\' Circles and Brothers\' Sports Nights to cater to our diverse community.'
  },
  {
    question: 'How can I support the MSA?',
    answer: 'You can support us by volunteering your time, attending our events, or through donations which help us maintain our facilities and fund community programs.'
  }
];

const departments = [
  { name: 'General Inquiries', email: 'info@sfumsa.ca' },
  { name: 'Events & Logistics', email: 'events@sfumsa.ca' },
  { name: 'Media & Comms', email: 'media@sfumsa.ca' },
  { name: 'Sisters\' Affairs', email: 'sisters@sfumsa.ca' },
  { name: 'Education & Outreach', email: 'education@sfumsa.ca' },
];

const socialLinks = [
  { icon: Instagram, name: 'Instagram', href: 'https://www.instagram.com/sfu_msa/' },
  { icon: Facebook, name: 'Facebook', href: 'https://www.facebook.com/sfumsa/' },
];

const formState = ref({ name: '', email: '', subject: '', message: '' });
const isSubmitting = ref(false);
const isSuccess = ref(false);
const submitError = ref('');
const openFaq = ref<number | null>(null);

const handleSubmit = async () => {
  isSubmitting.value = true;
  submitError.value = '';
  isSuccess.value = false;

  try {
    await websiteService.submitContact(formState.value);
    isSuccess.value = true;
    formState.value = { name: '', email: '', subject: '', message: '' };
    setTimeout(() => {
      isSuccess.value = false;
    }, 5000);
  } catch (err: any) {
    submitError.value = err.message || 'Your message could not be sent right now.';
  } finally {
    isSubmitting.value = false;
  }
};

const toggleFaq = (index: number) => {
  openFaq.value = openFaq.value === index ? null : index;
};
</script>

<template>
  <div class="min-h-screen bg-neutral-background">
    <!-- Hero Section -->
    <section class="relative py-24 sm:py-32 overflow-hidden bg-primary">
      <div class="absolute inset-0 pattern-dots -z-10 opacity-10" />
      <FloatingElement class="absolute top-0 right-0 w-1/3 h-2/3 bg-secondary/5 rounded-bl-[10rem] -z-10 blur-3xl opacity-50" />
      <div class="container-custom">
        <div class="max-w-3xl space-y-6">
          <ScrollReveal direction="down">
            <span class="inline-flex items-center gap-2 px-4 py-2 bg-white/10 border border-white/20 rounded-full text-accent-gold font-bold uppercase tracking-widest text-[10px]">
              <Plus :size="12" /> Get in Touch
            </span>
          </ScrollReveal>
          
          <ScrollReveal :delay="0.2">
            <h1 class="text-5xl md:text-8xl font-display font-medium text-white leading-[0.9] tracking-tighter">
              We're here to <br />
              <span class="text-accent-gold italic font-serif">support you.</span>
            </h1>
          </ScrollReveal>

          <ScrollReveal :delay="0.3">
            <p class="text-xl md:text-2xl text-white/70 leading-relaxed font-light max-w-2xl">
              Whether you have a question about our programs, need support, or just want to say salam, we'd love to hear from you.
            </p>
          </ScrollReveal>
        </div>
      </div>
    </section>

    <!-- Main Content -->
    <section class="py-12 pb-24">
      <div class="container-custom">
        <div class="grid lg:grid-cols-2 gap-16 items-start">
          
          <!-- Contact Form -->
          <div class="premium-card p-8 md:p-12 bg-white border border-neutral-gray/20 shadow-soft">
            <h2 class="text-3xl font-bold mb-8 flex items-center gap-3 text-primary">
              <MessageSquare class="text-secondary" />
              Send a Message
            </h2>

            <form @submit.prevent="handleSubmit" class="space-y-6">
              <div class="grid md:grid-cols-2 gap-6">
                <div class="space-y-2">
                  <label class="text-xs font-bold uppercase tracking-widest text-neutral-black/40 font-sans">Full Name</label>
                  <input 
                    type="text" 
                    required
                    placeholder="Your name"
                    v-model="formState.name"
                    class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl px-6 py-4 focus:ring-2 focus:ring-primary/20 outline-none transition-all text-neutral-black text-sm"
                  />
                </div>
                <div class="space-y-2">
                  <label class="text-xs font-bold uppercase tracking-widest text-neutral-black/40 font-sans">Email Address</label>
                  <input 
                    type="email" 
                    required
                    placeholder="email@sfumsa.ca"
                    v-model="formState.email"
                    class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl px-6 py-4 focus:ring-2 focus:ring-primary/20 outline-none transition-all text-neutral-black text-sm"
                  />
                </div>
              </div>
              <div class="space-y-2">
                <label class="text-xs font-bold uppercase tracking-widest text-neutral-black/40 font-sans">Subject</label>
                <input 
                  type="text" 
                  required
                  placeholder="What is this regarding?"
                  v-model="formState.subject"
                  class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl px-6 py-4 focus:ring-2 focus:ring-primary/20 outline-none transition-all text-neutral-black text-sm"
                />
              </div>
              <div class="space-y-2">
                <label class="text-xs font-bold uppercase tracking-widest text-neutral-black/40 font-sans">Message</label>
                <textarea 
                  required
                  rows="6"
                  placeholder="How can we help?"
                  v-model="formState.message"
                  class="w-full bg-neutral-background border border-neutral-gray/20 rounded-2xl px-6 py-4 focus:ring-2 focus:ring-primary/20 outline-none transition-all resize-none text-neutral-black text-sm"
                />
              </div>

              <button 
                type="submit"
                :disabled="isSubmitting || isSuccess"
                class="w-full bg-primary text-white py-5 rounded-2xl font-bold uppercase tracking-[0.2em] text-xs hover:bg-secondary transition-all flex items-center justify-center gap-3 shadow-xl shadow-primary/20 disabled:opacity-50 cursor-pointer"
              >
                <div v-if="isSubmitting" class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                <template v-else-if="isSuccess">Sent Successfully</template>
                <template v-else>
                  Send Message
                  <Send :size="16" />
                </template>
              </button>

              <Presence>
                <div 
                  v-if="isSuccess"
                  class="flex items-center gap-3 p-4 bg-emerald-50 border border-emerald-500/20 text-emerald-600 rounded-2xl"
                >
                  <CheckCircle2 :size="20" class="shrink-0" />
                  <p class="text-sm font-medium">Thank you! Your message has been sent. We'll get back to you shortly.</p>
                </div>
              </Presence>
              <Presence>
                <div 
                  v-if="submitError"
                  class="flex items-center gap-3 p-4 bg-red-50 border border-red-500/20 text-red-600 rounded-2xl"
                >
                  <AlertCircle :size="20" class="shrink-0" />
                  <p class="text-sm font-medium">{{ submitError }}</p>
                </div>
              </Presence>
            </form>
          </div>

          <!-- Side Info -->
          <div class="space-y-12">
            <!-- Contact Cards -->
            <div class="grid gap-6 text-neutral-black">
              <div class="flex items-start gap-6 p-6 rounded-3xl bg-white border border-neutral-gray/20 hover:border-primary/30 transition-colors shadow-soft">
                <div class="w-14 h-14 bg-primary/5 rounded-2xl flex items-center justify-center shrink-0 border border-primary/10">
                  <MapPin class="text-primary" />
                </div>
                <div>
                  <h3 class="text-lg font-bold mb-1 text-primary">Our Location</h3>
                  <p class="text-neutral-black/60 text-sm leading-relaxed">
                    AQ 3200, Burnaby Campus<br />
                    8888 University Dr, Burnaby, BC
                  </p>
                </div>
              </div>

              <div class="flex items-start gap-6 p-6 rounded-3xl bg-white border border-neutral-gray/20 hover:border-primary/30 transition-colors shadow-soft">
                <div class="w-14 h-14 bg-primary/5 rounded-2xl flex items-center justify-center shrink-0 border border-primary/10">
                  <Mail class="text-primary" />
                </div>
                <div>
                  <h3 class="text-lg font-bold mb-1 text-primary">General Inquiries</h3>
                  <p class="text-neutral-black/60 text-sm">info@sfumsa.ca</p>
                </div>
              </div>

              <div class="flex items-start gap-6 p-6 rounded-3xl bg-white border border-neutral-gray/20 hover:border-primary/30 transition-colors shadow-soft">
                <div class="w-14 h-14 bg-primary/5 rounded-2xl flex items-center justify-center shrink-0 border border-primary/10">
                  <Phone class="text-primary" />
                </div>
                <div>
                  <h3 class="text-lg font-bold mb-1 text-primary">Emergency Support</h3>
                  <p class="text-neutral-black/60 text-sm">Case-by-case (Via Email)</p>
                </div>
              </div>
            </div>

            <!-- Department Contacts -->
            <div class="premium-card p-8 bg-white border border-neutral-gray/20 shadow-soft">
              <h3 class="text-xl font-bold mb-6 text-primary">Department Contacts</h3>
              <div class="space-y-4">
                <div v-for="dept in departments" :key="dept.name" class="flex items-center justify-between py-3 border-b border-neutral-gray/10 last:border-0">
                  <span class="text-sm font-medium text-neutral-black/50">{{ dept.name }}</span>
                  <a :href="`mailto:${dept.email}`" class="text-sm font-bold text-primary hover:underline">{{ dept.email }}</a>
                </div>
              </div>
            </div>

            <!-- Social Links -->
            <div class="flex gap-4">
              <a 
                v-for="social in socialLinks"
                :key="social.name"
                :href="social.href"
                target="_blank"
                rel="noopener noreferrer"
                :aria-label="social.name"
                class="w-12 h-12 bg-white rounded-xl flex items-center justify-center text-neutral-black/40 hover:text-primary hover:shadow-soft transition-all border border-neutral-gray/20"
              >
                <component :is="social.icon" :size="20" />
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-24 bg-neutral-background relative overflow-hidden">
      <div class="container-custom">
        <div class="relative max-w-5xl mx-auto bg-primary text-white rounded-[3rem] p-8 sm:p-12 md:p-16 overflow-hidden shadow-premium border border-primary/10">
          <div class="absolute inset-0 pattern-islamic opacity-5" />
          <div class="relative z-10 text-center mb-12">
            <h2 class="text-4xl md:text-5xl font-bold mb-4 text-white">Frequently Asked <span class="text-accent-gold italic font-serif">Questions</span></h2>
            <p class="text-white/60">Find quick answers to common queries.</p>
          </div>
          
          <div class="relative z-10 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div 
              v-for="(faq, i) in faqs" 
              :key="i"
              class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-2xl overflow-hidden group hover:border-accent-gold/30 transition-all shadow-lg"
            >
              <button 
                @click="toggleFaq(i)"
                class="w-full min-h-28 px-6 py-5 flex items-center justify-between gap-5 text-left group-hover:text-accent-gold transition-colors cursor-pointer"
              >
                <span class="font-bold leading-snug">{{ faq.question }}</span>
                <ChevronDown :class="['transition-transform duration-300', openFaq === i ? 'rotate-180' : '']" />
              </button>
              <Presence>
                <div 
                  v-if="openFaq === i"
                  class="px-8 pb-6 text-white/60 leading-relaxed text-sm"
                >
                  {{ faq.answer }}
                </div>
              </Presence>
            </div>
          </div>
        </div>
      </div>
    </section>
    <div class="h-12 sm:h-16 bg-neutral-background" />
  </div>
</template>
