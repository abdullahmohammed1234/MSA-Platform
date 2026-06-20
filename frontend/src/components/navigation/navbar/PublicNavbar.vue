<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Motion, Presence } from '@motionone/vue';
import { useAuthStore } from '@/stores/auth';
import { 
  BookOpen, 
  ChevronRight, 
  LogIn,
  LogOut,
  X 
} from 'lucide-vue-next';

const navLinks = [
  { name: 'Home', href: '/' },
  { name: 'About', href: '/about' },
  { name: 'Events', href: '/events' },
  { name: 'Prayer', href: '/prayer' },
  { name: 'Team', href: '/team' },
  { name: 'Media', href: '/media' },
  { name: 'Resources', href: '/resources' },
  { name: 'Contact', href: '/contact' },
];

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const isOpen = ref(false);
const scrolled = ref(false);

const isAuthenticated = computed(() => authStore.isAuthenticated);
const canAccessAcademy = computed(() => authStore.canAccessAcademy);
const isLoading = ref(false);

const handleScroll = () => {
  scrolled.value = window.scrollY > 20;
};

onMounted(() => {
  window.addEventListener('scroll', handleScroll);
  handleScroll(); // Check initial scroll state
});

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll);
});

const closeMenu = () => {
  isOpen.value = false;
};

const handleLogout = async () => {
  closeMenu();
  await authStore.logout();
  router.push({ name: 'home' });
};
</script>

<template>
  <div class="fixed top-4 left-0 right-0 z-50 flex justify-center px-[3%] pointer-events-none">
    <Motion
      tag="div"
      :initial="{ y: -50, opacity: 0 }"
      :animate="{ y: 0, opacity: 1 }"
      :transition="{ duration: 0.6 }"
      :class="[
        'pointer-events-auto w-full max-w-7xl rounded-full border transition-all duration-500',
        scrolled
          ? 'bg-white/90 backdrop-blur-xl border-neutral-ivory/80 shadow-premium py-2.5 px-4'
          : 'bg-neutral-background/75 backdrop-blur-lg border-neutral-ivory/30 shadow-soft py-4 px-6'
      ]"
    >
    <div class="flex items-center justify-between">
      <router-link to="/" class="flex items-center gap-3 group pl-2">
        <div class="h-10 w-10 sm:h-11 sm:w-11 shrink-0 flex items-center justify-center">
          <img
            src="/logo.webp"
            alt="SFU MSA logo"
            class="h-full w-full object-contain transition-transform group-hover:scale-105"
          />
        </div>
        <div class="flex flex-col">
          <span class="text-base sm:text-lg font-display font-extrabold text-primary leading-none tracking-tight uppercase">SFU MSA</span>
          <span class="text-[8px] uppercase tracking-[0.2em] text-neutral-black/40 font-bold mt-0.5">Muslim Students Association</span>
        </div>
      </router-link>

      <!-- Desktop Nav -->
      <nav class="hidden xl:flex items-center gap-8">
        <div class="flex items-center gap-6">
          <router-link
            v-for="link in navLinks"
            :key="link.name"
            :to="link.href"
            :class="[
              'text-[10px] font-extrabold uppercase tracking-[0.2em] transition-all relative py-2 group',
              route.path === link.href ? 'text-primary' : 'text-neutral-black/55 hover:text-primary'
            ]"
          >
            {{ link.name }}
            <span 
              :class="[
                'absolute bottom-0 left-0 h-[2px] bg-primary rounded-full transition-all duration-300',
                route.path === link.href ? 'w-full' : 'w-0 group-hover:w-full'
              ]"
            />
          </router-link>
        </div>
        
        <div class="flex items-center gap-3 pr-2">
          <router-link
            v-if="!isLoading && isAuthenticated && canAccessAcademy"
            to="/academy"
            :class="[
              'inline-flex items-center gap-2 px-5 py-2.5 rounded-full text-[10px] font-extrabold uppercase tracking-widest hover:shadow-premium transition-all hover:-translate-y-0.5 active:scale-95',
              route.path.startsWith('/academy')
                ? 'bg-secondary text-white'
                : 'bg-primary text-white hover:bg-secondary'
            ]"
          >
            <BookOpen class="h-3.5 w-3.5" />
            Dawah
          </router-link>

          <router-link
            v-else-if="!isLoading && !isAuthenticated"
            to="/login"
            class="inline-flex items-center gap-2 border border-primary/20 text-primary px-5 py-2.5 rounded-full text-[10px] font-extrabold uppercase tracking-widest hover:bg-primary/5 transition-all"
          >
            <LogIn class="h-3.5 w-3.5" />
            Login
          </router-link>

          <router-link
            v-if="!isAuthenticated"
            to="/register"
            class="bg-primary text-white px-6 py-2.5 rounded-full text-[10px] font-extrabold uppercase tracking-widest hover:bg-secondary hover:shadow-premium transition-all hover:-translate-y-0.5 active:scale-95"
          >
            Register
          </router-link>
          <button
            v-else-if="!isLoading"
            type="button"
            class="inline-flex items-center gap-2 bg-primary text-white px-6 py-2.5 rounded-full text-[10px] font-extrabold uppercase tracking-widest hover:bg-secondary hover:shadow-premium transition-all hover:-translate-y-0.5 active:scale-95 cursor-pointer"
            @click="handleLogout"
          >
            <LogOut class="h-3.5 w-3.5" />
            Logout
          </button>
        </div>
      </nav>

      <!-- Mobile & Tablet Toggle -->
      <div class="flex xl:hidden items-center gap-2 pr-2">
        <router-link
          v-if="!isLoading && isAuthenticated && canAccessAcademy"
          to="/academy"
          class="inline-flex items-center gap-1.5 bg-primary text-white px-4 py-2.5 rounded-full text-[9px] font-bold uppercase tracking-widest active:scale-95 transition-transform"
          aria-label="Open Dawah Academy"
        >
          <BookOpen class="h-3.5 w-3.5" />
          Academy
        </router-link>
        <router-link
          v-else-if="!isLoading && !isAuthenticated"
          to="/login"
          class="bg-primary text-white px-5 py-2.5 rounded-full text-[10px] font-bold uppercase tracking-widest active:scale-95 transition-transform"
        >
          Login
        </router-link>
        <button
          v-else-if="!isLoading && isAuthenticated"
          type="button"
          class="bg-primary text-white px-4 py-2.5 rounded-full text-[9px] font-bold uppercase tracking-widest active:scale-95 transition-transform cursor-pointer"
          @click="handleLogout"
        >
          Logout
        </button>
        <button
          class="p-3 text-primary hover:bg-primary/5 rounded-xl transition-all cursor-pointer"
          @click="isOpen = !isOpen"
          :aria-label="isOpen ? 'Close menu' : 'Open menu'"
        >
          <div class="w-6 h-6 flex flex-col justify-center items-center relative">
            <span :class="['w-6 h-0.5 bg-current rounded-full transition-all duration-300 absolute', isOpen ? 'rotate-45' : '-translate-y-1.5']" />
            <span :class="['w-6 h-0.5 bg-current rounded-full transition-all duration-300 absolute', isOpen ? 'opacity-0' : 'opacity-100']" />
            <span :class="['w-6 h-0.5 bg-current rounded-full transition-all duration-300 absolute', isOpen ? '-rotate-45' : 'translate-y-1.5']" />
          </div>
        </button>
      </div>
    </div>

    <!-- Mobile Nav Overlay - Premium Drawer -->
    <Presence>
      <div v-if="isOpen" class="fixed inset-0 z-40 xl:hidden overflow-hidden">
        <!-- Backdrop Blur -->
        <Motion
          :initial="{ opacity: 0 }"
          :animate="{ opacity: 1 }"
          :exit="{ opacity: 0 }"
          class="absolute inset-0 bg-primary/20 backdrop-blur-md"
          @click="closeMenu"
        />
        
        <!-- Main Drawer Content -->
        <Motion
          :initial="{ x: '100%' }"
          :animate="{ x: 0 }"
          :exit="{ x: '100%' }"
          :transition="{ type: 'spring', damping: 30, stiffness: 300 }"
          class="absolute top-0 right-0 bottom-0 w-[85%] max-w-sm bg-neutral-background shadow-2xl flex flex-col"
        >
          <div class="p-8 flex flex-col h-full border-l border-neutral-gray/20">
            <div class="flex justify-between items-center mb-12">
              <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-black/30">Menu</span>
              <button 
                @click="closeMenu"
                class="p-2 -mr-2 text-neutral-black/30 hover:text-primary transition-colors cursor-pointer"
              >
                <X :size="20" />
              </button>
            </div>

            <nav class="flex flex-col gap-2">
              <div v-if="!isLoading && isAuthenticated && canAccessAcademy">
                <router-link
                  to="/academy"
                  class="text-2xl font-serif py-3 flex w-full items-center justify-between group transition-colors text-primary"
                  @click="closeMenu"
                >
                  <div class="flex items-center gap-4">
                    <span class="text-[10px] font-mono text-neutral-black/20 mt-1">00</span>
                    Academy
                  </div>
                  <BookOpen class="w-5 h-5" />
                </router-link>
              </div>
              
              <div
                v-for="(link, i) in navLinks"
                :key="link.name"
              >
                <router-link
                  :to="link.href"
                  :class="[
                    'text-2xl font-serif py-3 flex items-center justify-between group transition-colors',
                    route.path === link.href ? 'text-primary font-medium' : 'text-neutral-black hover:text-primary'
                  ]"
                  @click="closeMenu"
                >
                  <div class="flex items-center gap-4">
                    <span class="text-[10px] font-mono text-neutral-black/20 mt-1">{{ (i + 1).toString().padStart(2, '0') }}</span>
                    {{ link.name }}
                  </div>
                  <ChevronRight :class="['w-5 h-5 transition-transform group-hover:translate-x-1', route.path === link.href ? 'opacity-100' : 'opacity-0']" />
                </router-link>
              </div>
            </nav>

            <div class="mt-auto pt-12 space-y-8">
              <div class="space-y-3">
                <router-link
                  v-if="!isAuthenticated"
                  to="/register"
                  class="block w-full py-5 bg-primary text-white rounded-2xl text-center text-xs font-bold uppercase tracking-[0.2em] shadow-xl shadow-primary/10 hover:bg-secondary active:scale-[0.98] transition-all"
                  @click="closeMenu"
                >
                  Register
                </router-link>
                <button
                  v-else
                  type="button"
                  class="block w-full py-5 bg-primary text-white rounded-2xl text-center text-xs font-bold uppercase tracking-[0.2em] shadow-xl shadow-primary/10 hover:bg-secondary active:scale-[0.98] transition-all cursor-pointer"
                  @click="handleLogout"
                >
                  Logout
                </button>
              </div>
              
              <div class="flex flex-col gap-2">
                <span class="text-[9px] uppercase tracking-widest text-neutral-black/30 font-bold">Connect With Us</span>
                <div class="text-[11px] text-neutral-black/60 flex flex-col gap-y-1">
                  <span>info@sfumsa.ca</span>
                  <span>@sfumsa</span>
                </div>
              </div>
            </div>
          </div>
        </Motion>
      </div>
    </Presence>
    </Motion>
  </div>
</template>
