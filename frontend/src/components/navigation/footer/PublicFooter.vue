<script setup lang="ts">
import { ref } from 'vue';
import {
  Instagram,
  Facebook,
  Mail,
  MapPin,
  Youtube,
  Heart,
  Send,
  CheckCircle2,
  AlertCircle,
} from 'lucide-vue-next';
import websiteService from '@/services/website/websiteService';

const currentYear = new Date().getFullYear();
const email = ref('');
const isSubmitting = ref(false);
const successMessage = ref('');
const errorMessage = ref('');

const socialLinks = [
  { icon: Instagram, href: 'https://www.instagram.com/sfu_msa/', label: 'Instagram' },
  { icon: Facebook, href: 'https://www.facebook.com/sfumsa/', label: 'Facebook' },
  { icon: Youtube, href: 'https://www.youtube.com/@sfumsa', label: 'YouTube' },
];

const handleSubmit = async () => {
  if (!email.value.trim()) {
    return;
  }

  isSubmitting.value = true;
  successMessage.value = '';
  errorMessage.value = '';

  try {
    const result = await websiteService.subscribeNewsletter(email.value.trim());
    successMessage.value = result.message;
    email.value = '';
  } catch (err: any) {
    errorMessage.value = err.message || 'We could not process your subscription right now. Please try again later.';
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <footer class="bg-primary text-white pt-20 pb-10 overflow-hidden relative border-t border-white/10">
    <!-- Subtle Background Pattern -->
    <div class="absolute inset-0 opacity-[0.03] pointer-events-none pattern-islamic scale-150" />
    
    <div class="max-w-7xl mx-auto px-6 md:px-12 relative z-10">
      <!-- Top Section: Branding & Newsletter -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-20 pb-16 border-b border-white/10">
        <div class="lg:col-span-5 space-y-8">
          <router-link to="/" class="flex items-center gap-4 group">
            <div class="h-14 w-14 shrink-0 flex items-center justify-center">
              <img
                src="/logo.webp"
                alt="SFU MSA logo"
                class="h-full w-full object-contain transition-transform group-hover:scale-105"
              />
            </div>
            <div class="flex flex-col text-white">
              <span class="text-2xl font-display font-bold leading-none tracking-tight uppercase">SFU MSA</span>
              <span class="text-[10px] uppercase tracking-[0.3em] text-accent-gold font-bold mt-1">Faith • Community • Excellence</span>
            </div>
          </router-link>
          <p class="text-white/60 text-base leading-relaxed max-w-md">
            Building a vibrant Muslim community at Simon Fraser University since 1977. We provide a space for practice, learning, and connection for all students.
          </p>
          <div class="flex items-center gap-3">
            <a
              v-for="social in socialLinks"
              :key="social.label"
              :href="social.href"
              target="_blank"
              rel="noopener noreferrer"
              class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center hover:bg-secondary hover:border-secondary hover:text-white transition-all duration-300"
              :aria-label="social.label"
            >
              <component :is="social.icon" :size="18" />
            </a>
          </div>
        </div>

        <div class="lg:col-span-7 space-y-6">
          <div class="bg-primary-dark/20 border border-white/10 rounded-3xl p-8 md:p-10">
            <h4 class="text-xl font-display font-bold text-white mb-2">Join our Newsletter</h4>
            <p class="text-white/50 text-sm mb-6">Stay updated with our latest events, prayer times, and community news.</p>
            <form @submit.prevent="handleSubmit" class="flex flex-col sm:flex-row gap-3">
              <input 
                type="email" 
                placeholder="Your email address" 
                v-model="email"
                required
                :disabled="isSubmitting"
                class="flex-grow bg-white/5 border border-white/10 text-white px-6 py-4 rounded-2xl focus:outline-none focus:border-secondary/50 focus:bg-white/10 transition-all placeholder:text-white/30 disabled:opacity-60"
              />
              <button 
                type="submit"
                :disabled="isSubmitting"
                class="bg-secondary text-white px-8 py-4 rounded-2xl font-bold uppercase text-[11px] tracking-widest hover:bg-secondary-light hover:shadow-premium transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
              >
                {{ isSubmitting ? 'Subscribing...' : 'Subscribe' }} <Send :size="14" />
              </button>
            </form>
            <p
              v-if="successMessage"
              class="mt-4 flex items-start gap-2 text-sm text-emerald-300"
            >
              <CheckCircle2 :size="16" class="mt-0.5 shrink-0" />
              <span>{{ successMessage }}</span>
            </p>
            <p
              v-if="errorMessage"
              class="mt-4 flex items-start gap-2 text-sm text-red-300"
            >
              <AlertCircle :size="16" class="mt-0.5 shrink-0" />
              <span>{{ errorMessage }}</span>
            </p>
          </div>
        </div>
      </div>

      <!-- Middle Section: Columns -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-12 py-16 border-b border-white/10">
        <div class="space-y-6">
          <h5 class="text-accent-gold text-[10px] uppercase tracking-[0.3em] font-black">MSA Pages</h5>
          <ul class="space-y-3">
            <li v-for="item in [
              { name: 'Home', link: '/' },
              { name: 'About Us', link: '/about' },
              { name: 'Events', link: '/events' },
              { name: 'Team', link: '/team' },
              { name: 'Media Gallery', link: '/media' },
              { name: 'Resources', link: '/resources' }
            ]" :key="item.name">
              <router-link :to="item.link" class="text-white/40 text-sm hover:text-white transition-colors">{{ item.name }}</router-link>
            </li>
          </ul>
        </div>

        <div class="space-y-6">
          <h5 class="text-accent-gold text-[10px] uppercase tracking-[0.3em] font-black">Prayer & Services</h5>
          <ul class="space-y-3">
            <li v-for="item in [
              { name: 'Daily Prayer Times', link: '/prayer' },
              { name: 'Jummah Schedule', link: '/prayer' },
              { name: 'Wudu Facilities', link: '/prayer' },
              { name: 'Sisters Space', link: '/prayer' },
              { name: 'Halal Food Options', link: '/resources' },
              { name: 'Chaplaincy Services', link: '/resources' }
            ]" :key="item.name">
              <router-link :to="item.link" class="text-white/40 text-sm hover:text-white transition-colors">{{ item.name }}</router-link>
            </li>
          </ul>
        </div>

        <div class="space-y-6">
          <h5 class="text-accent-gold text-[10px] uppercase tracking-[0.3em] font-black">Volunteer</h5>
          <ul class="space-y-3">
            <li v-for="item in [
              { name: 'Become a Member', link: '/membership' },
              { name: 'Join Committees', link: '/membership' },
              { name: 'Event Logistics', link: '/membership' },
              { name: 'Graphic Design', link: '/membership' },
              { name: 'Content Creation', link: '/membership' },
              { name: 'Mentorship', link: '/membership' }
            ]" :key="item.name">
              <router-link :to="item.link" class="text-white/40 text-sm hover:text-white transition-colors">{{ item.name }}</router-link>
            </li>
          </ul>
        </div>

        <div class="space-y-6">
          <h5 class="text-accent-gold text-[10px] uppercase tracking-[0.3em] font-black">Get in Touch</h5>
          <ul class="space-y-4">
            <li class="flex items-start gap-4">
              <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center shrink-0">
                <MapPin :size="14" class="text-accent-gold" />
              </div>
              <span class="text-white/40 text-sm leading-relaxed">
                Multifaith Centre, Simon Fraser University <br />
                8888 University Dr, Burnaby <br />
                AQ 3200
              </span>
            </li>
            <li class="flex items-center gap-4">
              <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center shrink-0">
                <Mail :size="14" class="text-accent-gold" />
              </div>
              <a href="mailto:info@sfumsa.ca" class="text-white/40 text-sm hover:text-white transition-colors">info@sfumsa.ca</a>
            </li>
          </ul>
        </div>
      </div>

      <!-- Bottom Section: Copyright -->
      <div class="pt-10 flex flex-col md:flex-row items-center justify-between gap-6 text-[10px] uppercase tracking-[0.2em] font-bold text-white/20">
        <div class="flex items-center gap-2">
          <span>© {{ currentYear }} SFU Muslim Students Association.</span>
          <span class="hidden sm:inline">•</span>
          <span class="flex items-center gap-1">Made with <Heart :size="10" class="text-accent-gold" /> for the Ummah</span>
        </div>
      </div>
    </div>
  </footer>
</template>
