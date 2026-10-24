<script setup lang="ts">
import { ref, computed, nextTick } from 'vue';
import { Play, Heart, ArrowDown, Sparkles, AlertCircle } from 'lucide-vue-next';
import PublicButton from '@/components/shared/PublicButton.vue';
import ScrollReveal from '@/components/shared/ScrollReveal.vue';

interface Props {
  videoSrc?: string;
  posterSrc?: string;
  badge?: string;
  title?: string;
  subtitle?: string;
  description?: string;
  ctaText?: string;
  ctaTarget?: string;
}

const props = withDefaults(defineProps<Props>(), {
  videoSrc: 'MSA.mp4',
  posterSrc: 'MSA-poster.jpg',
  badge: 'Community Impact',
  title: 'Your support makes a difference.',
  subtitle: 'Building faith & belonging.',
  description:
    'Discover how your generosity helps support the SFU Muslim community. From daily prayer spaces and Friday Jumu\'ah to educational programs and student support, your contribution creates lasting impact.',
  ctaText: 'Make a Donation',
  ctaTarget: '#donation-console',
});

const isPlaying = ref(false);
const videoRef = ref<HTMLVideoElement | null>(null);
const videoError = ref(false);
const posterAttempt = ref(0);

const resolvedVideoSrc = computed(() => {
  const base = import.meta.env.BASE_URL || '/';
  const cleanPath = props.videoSrc.replace(/^\//, '');
  return `${base}${cleanPath}`.replace(/\/+/g, '/');
});

const resolvedPosterSrc = computed(() => {
  if (posterAttempt.value === 0) {
    const base = import.meta.env.BASE_URL || '/';
    const cleanPath = props.posterSrc.replace(/^\//, '');
    return `${base}${cleanPath}`.replace(/\/+/g, '/');
  }
  // Graceful fallback to existing public hero image
  return '/Hero/jumuahprayer.webp';
});

const handlePosterError = () => {
  if (posterAttempt.value === 0) {
    posterAttempt.value = 1;
  }
};

const handleVideoError = () => {
  videoError.value = true;
};

const playVideo = async () => {
  if (!isPlaying.value) {
    isPlaying.value = true;
    videoError.value = false;
    await nextTick();
    if (videoRef.value) {
      try {
        await videoRef.value.play();
      } catch (err: any) {
        if (err && err.name !== 'AbortError') {
          console.warn('Local video play error:', err);
        }
      }
    }
  }
};

const handleCtaClick = () => {
  if (!props.ctaTarget) return;
  if (props.ctaTarget.startsWith('#')) {
    const targetElement = document.querySelector(props.ctaTarget);
    if (targetElement) {
      targetElement.scrollIntoView({ behavior: 'smooth' });
      return;
    }
  }
};
</script>

<template>
  <section
    class="relative py-16 sm:py-20 lg:py-24 bg-[#fffbf4] border-b border-neutral-ivory/60 overflow-hidden"
    aria-label="Donations Video Feature"
  >
    <!-- Background styling consistent with SFU MSA theme -->
    <div class="absolute inset-0 pattern-islamic opacity-[0.025] pointer-events-none" />
    <div
      class="absolute -top-24 right-0 w-96 h-96 bg-primary/5 rounded-full blur-3xl pointer-events-none -z-10"
    />
    <div
      class="absolute bottom-0 left-10 w-80 h-80 bg-accent-gold/10 rounded-full blur-3xl pointer-events-none -z-10"
    />

    <div class="container-custom relative z-10">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 xl:gap-16 items-center">
        <!-- LEFT COLUMN: Editorial Typography & Messaging -->
        <div class="lg:col-span-5 flex flex-col justify-center space-y-6">
          <ScrollReveal direction="up" :delay="0.1" width="100%">
            <!-- Eyebrow Badge -->
            <div
              class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-primary/5 border border-primary/10 text-primary text-[10px] sm:text-[11px] font-extrabold uppercase tracking-[0.22em]"
            >
              <Heart :size="13" class="text-accent-red fill-accent-red/20 animate-pulse" />
              <span>{{ badge }}</span>
            </div>
          </ScrollReveal>

          <ScrollReveal direction="up" :delay="0.15" width="100%">
            <!-- Editorial Heading -->
            <h2
              class="text-3xl sm:text-4xl md:text-5xl lg:text-[44px] xl:text-[48px] font-display font-extrabold text-primary leading-[1.12] tracking-tight text-balance"
            >
              {{ title }}
              <span class="block mt-1.5 sm:mt-2 font-serif font-light italic text-secondary">
                {{ subtitle }}
              </span>
            </h2>
          </ScrollReveal>

          <ScrollReveal direction="up" :delay="0.2" width="100%">
            <!-- Supporting Copy -->
            <p class="text-neutral-black/75 text-base sm:text-lg font-sans leading-relaxed max-w-xl">
              {{ description }}
            </p>
          </ScrollReveal>

          <!-- Impact Highlights -->
          <ScrollReveal direction="up" :delay="0.22" width="100%">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 max-w-lg">
              <div class="flex items-center gap-2.5 text-xs sm:text-sm font-semibold text-primary">
                <div class="w-7 h-7 rounded-full bg-primary/5 text-primary flex items-center justify-center shrink-0 border border-primary/10">
                  <Sparkles :size="13" class="text-accent-gold" />
                </div>
                <span>Campus Prayer Facilities</span>
              </div>
              <div class="flex items-center gap-2.5 text-xs sm:text-sm font-semibold text-primary">
                <div class="w-7 h-7 rounded-full bg-primary/5 text-primary flex items-center justify-center shrink-0 border border-primary/10">
                  <Heart :size="13" class="text-accent-red" />
                </div>
                <span>Student Support &amp; Iftars</span>
              </div>
            </div>
          </ScrollReveal>

          <ScrollReveal direction="up" :delay="0.25" width="100%">
            <!-- Call To Action -->
            <div class="pt-2">
              <a
                v-if="ctaTarget.startsWith('#')"
                :href="ctaTarget"
                @click.prevent="handleCtaClick"
                class="inline-flex"
              >
                <PublicButton
                  variant="primary"
                  size="lg"
                  type="button"
                  class="shadow-brand group font-bold tracking-wide"
                >
                  <span>{{ ctaText }}</span>
                  <ArrowDown
                    :size="16"
                    class="group-hover:translate-y-0.5 transition-transform duration-200"
                  />
                </PublicButton>
              </a>
              <router-link
                v-else
                :to="ctaTarget"
                class="inline-flex"
              >
                <PublicButton
                  variant="primary"
                  size="lg"
                  type="button"
                  class="shadow-brand group font-bold tracking-wide"
                >
                  <span>{{ ctaText }}</span>
                  <Heart
                    :size="16"
                    class="text-accent-gold fill-accent-gold/20 group-hover:scale-110 transition-transform duration-200"
                  />
                </PublicButton>
              </router-link>
            </div>
          </ScrollReveal>
        </div>

        <!-- RIGHT COLUMN: Cinematic Video Presentation -->
        <div class="lg:col-span-7 w-full">
          <ScrollReveal direction="up" :delay="0.2" width="100%">
            <div class="relative w-full">
              <!-- Warm layered ambient backlight -->
              <div
                class="absolute -inset-2 sm:-inset-4 bg-gradient-to-tr from-primary/15 via-accent-gold/20 to-transparent rounded-[2.5rem] blur-2xl -z-10 pointer-events-none opacity-80"
              />

              <!-- Outer Frame with refined border, radius & subtle shadow -->
              <div
                class="relative rounded-3xl sm:rounded-[2rem] p-2 sm:p-2.5 bg-white/85 backdrop-blur-md border border-neutral-ivory/80 shadow-soft hover:shadow-premium transition-all duration-500 overflow-hidden"
              >
                <!-- 16:9 Aspect Ratio Video Box -->
                <div
                  class="relative w-full aspect-video rounded-2xl sm:rounded-[1.6rem] overflow-hidden bg-neutral-black shadow-inner"
                >
                  <!-- Active Native HTML5 Video Player -->
                  <div v-if="isPlaying" class="w-full h-full relative">
                    <video
                      ref="videoRef"
                      class="w-full h-full object-cover"
                      controls
                      playsinline
                      preload="none"
                      :poster="resolvedPosterSrc"
                      @error="handleVideoError"
                    >
                      <source :src="resolvedVideoSrc" type="video/mp4" />
                      Your browser does not support HTML5 video.
                    </video>

                    <!-- Playback error fallback overlay if video fails to decode/load -->
                    <div
                      v-if="videoError"
                      class="absolute inset-0 bg-neutral-black/90 flex flex-col items-center justify-center p-6 text-center text-white space-y-3 z-20"
                    >
                      <AlertCircle class="w-10 h-10 text-accent-red" />
                      <p class="text-sm font-semibold">Video could not be played.</p>
                      <a
                        :href="resolvedVideoSrc"
                        download
                        class="text-xs text-accent-gold underline hover:text-white"
                      >
                        Download MSA.mp4 directly
                      </a>
                    </div>
                  </div>

                  <!-- Interactive Thumbnail & Custom Play Control Trigger (Before Playback) -->
                  <button
                    v-else
                    type="button"
                    class="group/play relative w-full h-full block cursor-pointer select-none text-left focus:outline-none focus-visible:ring-4 focus-visible:ring-primary focus-visible:ring-offset-2 rounded-2xl sm:rounded-[1.6rem]"
                    :aria-label="`Play video: ${title} ${subtitle}`"
                    @click="playVideo"
                  >
                    <!-- Video Poster Image -->
                    <img
                      :src="resolvedPosterSrc"
                      alt="SFU MSA community story video poster"
                      loading="lazy"
                      decoding="async"
                      class="w-full h-full object-cover transform transition-transform duration-700 ease-out group-hover/play:scale-105 motion-reduce:group-hover/play:scale-100"
                      @error="handlePosterError"
                    />

                    <!-- Rich Dark Vignette / Gradient Overlay for optimal contrast -->
                    <div
                      class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/25 to-black/35 group-hover/play:from-black/65 group-hover/play:via-black/15 group-hover/play:to-black/25 transition-colors duration-300"
                    />

                    <!-- Top-Left Corner Video Badge -->
                    <div
                      class="absolute top-3.5 left-3.5 sm:top-5 sm:left-5 flex items-center gap-2 px-3 py-1.5 rounded-full bg-primary-dark/85 backdrop-blur-md border border-white/15 text-white text-[10px] sm:text-[11px] font-bold tracking-wider uppercase shadow-sm pointer-events-none"
                    >
                      <span class="w-1.5 h-1.5 rounded-full bg-accent-gold" />
                      <span>Community Story</span>
                    </div>

                    <!-- Centered Custom Play Button Control -->
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                      <div
                        class="relative flex items-center justify-center w-16 h-16 sm:w-20 sm:h-20 md:w-24 md:h-24 rounded-full bg-primary text-white shadow-premium ring-4 ring-white/40 backdrop-blur-md transform transition-all duration-300 ease-out group-hover/play:scale-110 group-hover/play:bg-primary-dark group-hover/play:ring-white/60 group-focus/play:scale-110 group-focus/play:ring-accent-gold motion-reduce:group-hover/play:scale-100 motion-reduce:transition-none"
                      >
                        <!-- Restrained pulse ping -->
                        <span
                          class="absolute inset-0 rounded-full bg-primary/40 animate-ping opacity-60 motion-reduce:hidden"
                        />
                        <!-- Play Icon -->
                        <Play
                          :size="32"
                          class="w-6 h-6 sm:w-8 sm:h-8 md:w-9 md:h-9 ml-1 text-white fill-current drop-shadow-sm"
                          aria-hidden="true"
                        />
                      </div>
                    </div>

                    <!-- Bottom Bar Overlay with Video Information -->
                    <div
                      class="absolute bottom-3.5 left-3.5 right-3.5 sm:bottom-5 sm:left-5 sm:right-5 flex items-center justify-between pointer-events-none"
                    >
                      <span class="text-white/95 font-medium text-xs sm:text-sm drop-shadow-md tracking-wide">
                        Watch community video
                      </span>
                      <span
                        class="text-[10px] sm:text-[11px] font-bold text-accent-gold uppercase tracking-wider bg-black/45 px-2 py-0.5 rounded backdrop-blur-sm border border-white/10"
                      >
                        HD
                      </span>
                    </div>
                  </button>
                </div>
              </div>
            </div>
          </ScrollReveal>
        </div>
      </div>
    </div>
  </section>
</template>
