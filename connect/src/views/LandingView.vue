<template>
  <div class="min-h-screen flex flex-col bg-[#fffbf4] pattern-islamic-crescent">
    <AppNavbar />

    <main class="flex-1">
      <!-- 1. Welcoming Community Hero -->
      <section class="relative py-12 sm:py-20 lg:py-24 overflow-hidden border-b border-[#ebe8de]/80">
        <!-- Subtle Warm Illumination -->
        <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-[700px] h-[400px] bg-gradient-to-b from-[#ffdc83]/25 via-[#640c0e]/8 to-transparent rounded-full blur-3xl pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
          <div class="max-w-3xl mx-auto text-center space-y-6">
            <!-- Authentic Crest Badge with Subtle Crescent Accent -->
            <div class="inline-flex items-center gap-2.5 px-4 py-2 rounded-full bg-white/90 backdrop-blur-xs text-[#640c0e] border border-[#ebe8de] shadow-2xs text-xs font-semibold tracking-wide">
              <img
                :src="getPublicAssetUrl('logo.webp')"
                alt="SFU MSA"
                class="w-4 h-4 object-contain"
                onerror="this.style.display='none'"
              />
              <span>Simon Fraser University • Muslim Students Association</span>
              <Moon class="w-3 h-3 text-[#b02e32]" aria-hidden="true" />
            </div>

            <!-- Headline with Welcoming Editorial Accent -->
            <h1 class="text-4xl sm:text-6xl lg:text-7xl font-extrabold text-[#640c0e] tracking-tight leading-[1.08] font-display">
              You don't have to walk this journey <span class="font-cormorant font-normal italic text-[#b02e32] text-5xl sm:text-7xl lg:text-8xl block sm:inline">alone.</span>
            </h1>

            <!-- Subheading -->
            <p class="text-base sm:text-lg text-neutral-700 leading-relaxed max-w-2xl mx-auto font-normal">
              Welcome to <strong>MSA Connect</strong> — your campus community hub for prayer spaces, weekly halaqahs, sister & brother circles, and student life at Simon Fraser University.
            </p>

            <!-- Action Buttons -->
            <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3.5">
              <template v-if="authStore.isAuthenticated">
                <router-link to="/app/dashboard" class="w-full sm:w-auto">
                  <Button variant="primary" size="lg" block>
                    <span>Enter Member Dashboard</span>
                    <template #right-icon>
                      <ArrowRight class="w-4 h-4" />
                    </template>
                  </Button>
                </router-link>
              </template>
              <template v-else>
                <router-link to="/login" class="w-full sm:w-auto">
                  <Button variant="primary" size="lg" block>
                    <span>Sign In to Connect</span>
                    <template #right-icon>
                      <ArrowRight class="w-4 h-4" />
                    </template>
                  </Button>
                </router-link>
              </template>

              <router-link
                to="/events"
                class="w-full sm:w-auto"
              >
                <Button variant="outline" size="lg" block>
                  <span>Explore Events</span>
                  <template #right-icon>
                    <Compass class="w-4 h-4" />
                  </template>
                </Button>
              </router-link>
            </div>

            <!-- Campus Anchors -->
            <div class="pt-4 flex flex-wrap items-center justify-center gap-x-8 gap-y-2.5 text-xs text-neutral-600 font-medium">
              <span class="flex items-center gap-2">
                <MapPin class="w-4 h-4 text-[#b02e32]" />
                Multi-Faith Centre (AQ 3200)
              </span>
              <span class="flex items-center gap-2">
                <CalendarCheck class="w-4 h-4 text-[#b02e32]" />
                Weekly Friday Jummah
              </span>
              <span class="flex items-center gap-2">
                <HeartHandshake class="w-4 h-4 text-[#b02e32]" />
                Warm, Inclusive Atmosphere
              </span>
            </div>

            <!-- Section 1 Primary Decorative Visual: Book Illustration in Balanced Contrasting Vignette -->
            <div class="pt-4 sm:pt-6 flex justify-center">
              <div class="relative group p-4 sm:p-5 rounded-2xl bg-gradient-to-br from-[#640c0e] via-[#7a0f12] to-[#49080a] border border-[#ffdc83]/30 shadow-md">
                <!-- Decorative crescent moon accent pin -->
                <div class="absolute -top-2.5 -right-2.5 w-6 h-6 rounded-full bg-[#fffbf4] border border-[#ffdc83]/60 flex items-center justify-center shadow-xs" aria-hidden="true">
                  <Moon class="w-3.5 h-3.5 text-[#b02e32]" />
                </div>
                <img
                  :src="getPublicAssetUrl('book.png')"
                  alt=""
                  aria-hidden="true"
                  class="w-36 sm:w-48 h-auto max-h-28 sm:max-h-32 object-contain drop-shadow-sm select-none pointer-events-none"
                />
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- 2. Special Welcome for New Muslims & Seekers (Restored with Compact Spacing) -->
      <section class="py-8 sm:py-12 bg-gradient-to-b from-white via-white to-[#fffbf4] border-b border-[#ebe8de]/80">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="premium-card p-6 sm:p-8 bg-white border border-[#ebe8de] border-t-4 border-t-[#d4af37] shadow-sm relative overflow-hidden">
            <div class="space-y-4 relative z-10">
              <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#ffdc83]/40 border border-[#ffdc83] text-[#640c0e] text-xs font-semibold">
                <Moon class="w-3.5 h-3.5 text-[#640c0e]" aria-hidden="true" />
                <span>A Warm Welcome to New Muslims & Faith Seekers</span>
              </div>

              <h2 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-[#640c0e] font-display">
                Every journey begins with a first step.
              </h2>

              <p class="text-sm text-neutral-700 leading-relaxed">
                If you have recently embraced Islam, are asking questions about the faith, or are trying to find fellow Muslims on campus, you belong here. You do not need to have everything figured out, know Arabic, or feel like an expert to join our circles. We are students just like you, dedicated to creating an environment of gentle encouragement, zero judgment, and genuine fellowship.
              </p>

              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                <div class="p-4 rounded-2xl bg-[#fffbf4] border border-[#ebe8de] space-y-2 hover:border-[#640c0e]/30 transition-colors">
                  <div class="w-9 h-9 rounded-xl bg-rose-50 text-[#b02e32] flex items-center justify-center font-bold text-xs shadow-2xs">
                    <Heart class="w-4 h-4" />
                  </div>
                  <h3 class="text-sm font-bold text-[#640c0e]">Gentle Pace</h3>
                  <p class="text-xs text-neutral-600 leading-relaxed">
                    Learn, ask questions, or just sit in comfortably. No expectations or pressure.
                  </p>
                </div>

                <div class="p-4 rounded-2xl bg-[#fffbf4] border border-[#ebe8de] space-y-2 hover:border-[#640c0e]/30 transition-colors">
                  <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-800 flex items-center justify-center font-bold text-xs shadow-2xs">
                    <Users class="w-4 h-4" />
                  </div>
                  <h3 class="text-sm font-bold text-[#640c0e]">Brothers & Sisters</h3>
                  <p class="text-xs text-neutral-600 leading-relaxed">
                    Friendly student circles to share a tea, study together, and navigate university life.
                  </p>
                </div>

                <div class="p-4 rounded-2xl bg-[#fffbf4] border border-[#ebe8de] space-y-2 hover:border-[#640c0e]/30 transition-colors">
                  <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-800 flex items-center justify-center font-bold text-xs shadow-2xs">
                    <MapPin class="w-4 h-4" />
                  </div>
                  <h3 class="text-sm font-bold text-[#640c0e]">Campus Spaces</h3>
                  <p class="text-xs text-neutral-600 leading-relaxed">
                    Accessible prayer rooms, ablution stations, and study spots across Burnaby and Surrey.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- 3. Campus Updates & Public Announcements -->
      <section id="campus-activities" class="py-8 sm:py-12">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
          <!-- Section 2 Header with Mosque Illustration in Contrasting Container -->
          <div class="premium-card p-5 sm:p-6 bg-white border border-[#ebe8de] flex flex-col md:flex-row items-center justify-between gap-5 overflow-hidden relative">
            <div class="space-y-1.5 text-center md:text-left flex-1 relative z-10">
              <span class="text-xs font-semibold uppercase tracking-wider text-[#b02e32] block">Live Announcements</span>
              <h2 class="text-xl sm:text-2xl font-bold text-[#640c0e]">
                What's Happening on Campus
              </h2>
              <p class="text-xs sm:text-sm text-neutral-600 max-w-xl">
                Official notices, prayer updates, and student initiatives from the SFU MSA team.
              </p>
              <div class="pt-1.5">
                <a
                  href="https://sfumsa.ca"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#b02e32] hover:text-[#640c0e] transition-colors"
                >
                  <span>Visit SFU MSA Main Website</span>
                  <ExternalLink class="w-3.5 h-3.5" />
                </a>
              </div>
            </div>

            <!-- Section 2 Primary Decorative Visual: Mosque Illustration in Contrasting Container -->
            <div class="w-full md:w-auto shrink-0 flex items-center justify-center relative z-10">
              <div class="relative p-2.5 sm:p-3 rounded-xl bg-gradient-to-br from-[#640c0e] via-[#7a0f12] to-[#49080a] border border-[#ffdc83]/30 shadow-md">
                <!-- Decorative crescent moon accent pin -->
                <div class="absolute -top-2 -right-2 w-5 h-5 rounded-full bg-white border border-[#ffdc83]/60 flex items-center justify-center shadow-xs" aria-hidden="true">
                  <Moon class="w-3 h-3 text-[#b02e32]" />
                </div>
                <img
                  :src="getPublicAssetUrl('mosque.png')"
                  alt=""
                  aria-hidden="true"
                  class="w-28 sm:w-36 h-auto max-h-20 sm:max-h-24 object-contain drop-shadow-sm select-none pointer-events-none"
                />
              </div>
            </div>
          </div>

          <!-- Announcements Grid -->
          <div
            v-if="publicAnnouncements.length > 0"
            class="grid grid-cols-1 gap-5"
            :class="publicAnnouncements.length === 2 ? 'md:grid-cols-2' : 'md:grid-cols-3'"
          >
            <article
              v-for="item in publicAnnouncements.slice(0, 3)"
              :key="item.id"
              class="premium-card p-5 sm:p-6 bg-white border border-[#ebe8de] flex flex-col justify-between space-y-4 hover:border-[#640c0e]/30 transition-all"
            >
              <div class="space-y-2">
                <div class="flex items-center justify-between gap-2">
                  <Badge variant="gold" size="sm">
                    {{ item.category || 'Community Update' }}
                  </Badge>
                  <span class="text-[11px] text-neutral-500 font-medium">
                    {{ formatEventDate(item.date) }}
                  </span>
                </div>

                <h3 class="text-base font-bold text-[#640c0e] leading-snug">
                  {{ item.title }}
                </h3>

                <p class="text-xs text-neutral-600 line-clamp-3 leading-relaxed">
                  {{ item.summary || item.content }}
                </p>
              </div>

              <div class="pt-2 border-t border-[#ebe8de]/60 flex items-center justify-between text-xs text-neutral-500">
                <span>SFU MSA Platform</span>
                <span class="text-[11px] font-medium text-[#b02e32]">Official Notice</span>
              </div>
            </article>
          </div>

          <!-- Fallback when no live announcements loaded -->
          <div
            v-else
            class="p-8 rounded-2xl bg-white border border-[#ebe8de] text-center max-w-xl mx-auto space-y-3"
          >
            <div class="inline-block p-3 rounded-2xl bg-gradient-to-br from-[#640c0e] via-[#7a0f12] to-[#49080a] border border-[#ffdc83]/30 shadow-xs mb-2">
              <img
                :src="getPublicAssetUrl('mosque.png')"
                alt=""
                aria-hidden="true"
                class="w-24 h-auto object-contain select-none pointer-events-none"
              />
            </div>
            <h3 class="text-sm font-bold text-neutral-800">Weekly Community Gatherings</h3>
            <p class="text-xs text-neutral-600 max-w-md mx-auto leading-relaxed">
              Friday Jummah prayers and weekly halaqahs run regularly during the semester. Sign in to your member account to view current event dates and reserve passes.
            </p>
          </div>
        </div>
      </section>

      <!-- 4. Community Pillars -->
      <section class="py-12 sm:py-16 bg-white/70 border-y border-[#ebe8de]/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="text-center max-w-2xl mx-auto mb-10 space-y-2">
            <span class="text-xs font-semibold uppercase tracking-wider text-[#b02e32]">The Connect Experience</span>
            <h2 class="text-2xl sm:text-3xl font-bold text-[#640c0e]">
              Four Pillars of Our Campus Community
            </h2>
            <p class="text-xs sm:text-sm text-neutral-600">
              Designed to make student life enriching, supportive, and spiritually grounding.
            </p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            <!-- Pillar 1 -->
            <div class="community-card p-5 sm:p-6 flex flex-col items-start bg-white border border-[#ebe8de] hover:border-[#640c0e]/30 hover:shadow-brand transition-all">
              <div class="w-11 h-11 rounded-xl bg-rose-50 text-[#b02e32] border border-rose-100/80 flex items-center justify-center mb-3.5 shadow-2xs">
                <Calendar class="w-5 h-5" />
              </div>
              <h3 class="text-base font-bold text-[#640c0e] font-display">
                Campus Gatherings & Jummah
              </h3>
            </div>

            <!-- Pillar 2 -->
            <div class="community-card p-5 sm:p-6 flex flex-col items-start bg-white border border-[#ebe8de] hover:border-[#640c0e]/30 hover:shadow-brand transition-all">
              <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-800 border border-amber-100/80 flex items-center justify-center mb-3.5 shadow-2xs">
                <HeartHandshake class="w-5 h-5" />
              </div>
              <h3 class="text-base font-bold text-[#640c0e] font-display">
                Peer Support & Circles
              </h3>
            </div>

            <!-- Pillar 3 -->
            <div class="community-card p-5 sm:p-6 flex flex-col items-start bg-white border border-[#ebe8de] hover:border-[#640c0e]/30 hover:shadow-brand transition-all">
              <div class="w-11 h-11 rounded-xl bg-sky-50 text-sky-800 border border-sky-100/80 flex items-center justify-center mb-3.5 shadow-2xs">
                <Ticket class="w-5 h-5" />
              </div>
              <h3 class="text-base font-bold text-[#640c0e] font-display">
                Instant Event Wallet
              </h3>
            </div>

            <!-- Pillar 4 -->
            <div class="community-card p-5 sm:p-6 flex flex-col items-start bg-white border border-[#ebe8de] hover:border-[#640c0e]/30 hover:shadow-brand transition-all">
              <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-100/80 flex items-center justify-center mb-3.5 shadow-2xs">
                <ShieldCheck class="w-5 h-5" />
              </div>
              <h3 class="text-base font-bold text-[#640c0e] font-display">
                Unified Student Identity
              </h3>
            </div>
          </div>
        </div>
      </section>

      <!-- 5. Community Questions / FAQ -->
      <section class="py-14 sm:py-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
          <div class="text-center space-y-2">
            <span class="text-xs font-semibold uppercase tracking-wider text-[#b02e32]">Got Questions?</span>
            <h2 class="text-2xl sm:text-3xl font-bold text-[#640c0e] font-display">
              Frequently Asked Questions
            </h2>
            <p class="text-xs sm:text-sm text-neutral-600">
              Clear answers for new students, converts, and community members.
            </p>
          </div>

          <div class="space-y-4">
            <div class="premium-card p-5 sm:p-6 bg-white border border-[#ebe8de] space-y-2 hover:border-[#640c0e]/30 transition-colors">
              <h3 class="text-sm sm:text-base font-bold text-[#640c0e] flex items-center gap-2.5">
                <HelpCircle class="w-4 h-4 text-[#b02e32] shrink-0" />
                <span>I am new to Islam or exploring the faith. Can I attend MSA activities?</span>
              </h3>
              <p class="text-xs sm:text-sm text-neutral-600 leading-relaxed pl-6 sm:pl-7">
                Yes, completely! Every student and seeker at SFU is warmly invited to attend our halaqahs, lectures, and social gatherings. You are never expected to have formal knowledge or make public disclosures. We are here to support your personal journey at your own comfort.
              </p>
            </div>

            <div class="premium-card p-5 sm:p-6 bg-white border border-[#ebe8de] space-y-2 hover:border-[#640c0e]/30 transition-colors">
              <h3 class="text-sm sm:text-base font-bold text-[#640c0e] flex items-center gap-2.5">
                <HelpCircle class="w-4 h-4 text-[#b02e32] shrink-0" />
                <span>Where can I pray and do Wudu on campus?</span>
              </h3>
              <p class="text-xs sm:text-sm text-neutral-600 leading-relaxed pl-6 sm:pl-7">
                On the Burnaby campus, primary prayer spaces include the Multi-Faith Centre (Academic Quadrangle AQ 3200) and the Student Union Building (SUB 2402). At SFU Surrey, dedicated prayer rooms are located in SRYC 3002 (Central City) and SRYE 3004 (Sustainable Energy Engineering building).
              </p>
            </div>

            <div class="premium-card p-5 sm:p-6 bg-white border border-[#ebe8de] space-y-2 hover:border-[#640c0e]/30 transition-colors">
              <h3 class="text-sm sm:text-base font-bold text-[#640c0e] flex items-center gap-2.5">
                <HelpCircle class="w-4 h-4 text-[#b02e32] shrink-0" />
                <span>Is membership in MSA Connect free?</span>
              </h3>
              <p class="text-xs sm:text-sm text-neutral-600 leading-relaxed pl-6 sm:pl-7">
                Yes. Creating an account, browsing events, and accessing official announcements are free for all students. General campus halaqahs and gatherings are free, while select special banquets or workshops indicate any applicable ticket fees.
              </p>
            </div>
          </div>

          <!-- Section 3 Final Sign-in Call to Action: Two Distinct Side-by-Side Illustrations (Book | Mosque) -->
          <div class="premium-card p-8 sm:p-12 text-center bg-gradient-to-br from-[#640c0e] via-[#7a0f12] to-[#49080a] text-white border border-[#640c0e] shadow-lg relative overflow-hidden space-y-5">
            <div class="relative z-10 max-w-xl mx-auto space-y-4">
              <!-- Two Distinct Side-by-Side Illustrations with Crescent Accents -->
              <div class="flex items-center justify-center gap-4 sm:gap-8 py-2 select-none pointer-events-none">
                <div class="relative flex flex-col items-center p-2 rounded-xl bg-white/5 border border-white/10 backdrop-blur-xs">
                  <img
                    :src="getPublicAssetUrl('book.png')"
                    alt=""
                    aria-hidden="true"
                    class="w-24 sm:w-36 h-auto max-h-20 sm:max-h-24 object-contain drop-shadow-md"
                  />
                </div>

                <!-- Center Divider with Subtle Crescent -->
                <div class="flex flex-col items-center justify-center gap-1.5" aria-hidden="true">
                  <div class="w-px h-6 sm:h-8 bg-[#ffdc83]/30"></div>
                  <Moon class="w-3.5 h-3.5 text-[#ffdc83]/70" />
                  <div class="w-px h-6 sm:h-8 bg-[#ffdc83]/30"></div>
                </div>

                <div class="relative flex flex-col items-center p-2 rounded-xl bg-white/5 border border-white/10 backdrop-blur-xs">
                  <img
                    :src="getPublicAssetUrl('mosque.png')"
                    alt=""
                    aria-hidden="true"
                    class="w-24 sm:w-36 h-auto max-h-20 sm:max-h-24 object-contain drop-shadow-md"
                  />
                </div>
              </div>

              <h3 class="text-2xl sm:text-3xl font-extrabold text-white font-display">
                Stay Connected With Your Campus Community
              </h3>
              <p class="text-xs sm:text-sm text-white/85 leading-relaxed max-w-md mx-auto">
                Sign in with your SFU MSA account to reserve event tickets, receive reminders, and customize your preferences.
              </p>
              <div class="pt-2 flex justify-center">
                <router-link to="/login">
                  <Button variant="gold" size="lg" class="shadow-brand">
                    <span>Sign In to MSA Connect</span>
                    <template #right-icon>
                      <ArrowRight class="w-4 h-4" />
                    </template>
                  </Button>
                </router-link>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <AppFooter />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { announcementService } from '@/services/announcement.service';
import type { Announcement } from '@/types/announcement';
import { formatEventDate } from '@/utils/formatters';
import AppNavbar from '@/components/layout/AppNavbar.vue';
import AppFooter from '@/components/layout/AppFooter.vue';
import Button from '@/components/ui/Button.vue';
import Badge from '@/components/ui/Badge.vue';
import {
  ArrowRight,
  Calendar,
  HeartHandshake,
  ShieldCheck,
  Compass,
  CalendarCheck,
  Heart,
  Users,
  MapPin,
  ExternalLink,
  Ticket,
  HelpCircle,
  Moon,
} from 'lucide-vue-next';

const authStore = useAuthStore();
const publicAnnouncements = ref<Announcement[]>([]);

function getPublicAssetUrl(path: string): string {
  const base = import.meta.env.BASE_URL || '/';
  const cleanBase = base.endsWith('/') ? base : `${base}/`;
  const cleanPath = path.startsWith('/') ? path.slice(1) : path;
  return `${cleanBase}${cleanPath}`;
}

onMounted(async () => {
  try {
    const list = await announcementService.getAnnouncements();
    publicAnnouncements.value = list;
  } catch {
    // Non-fatal; gracefully fallback to community guidance
  }
});
</script>
