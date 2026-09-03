<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Motion, Presence } from '@motionone/vue';
import { useAuthStore } from '@/stores/auth';
import { isPublicAuthEnabled } from '@/config/features';
import { 
  BookOpen, 
  ChevronRight, 
  ChevronDown,
  LogIn,
  LogOut,
  ShoppingBag,
  X 
} from 'lucide-vue-next';

import { useAppAccess } from '@/composables/auth/useAppAccess';

interface NavLink {
  name: string;
  href: string;
  external?: boolean;
}

const navLinks: NavLink[] = [
  { name: 'Home', href: '/' },
  { name: 'About', href: '/about' },
  { name: 'Events', href: '/events' },
  { name: 'Prayer', href: '/prayer' },
  { name: 'Team', href: '/team' },
  { name: 'Media', href: '/media' },
  { name: 'Store', href: '/store' },
  { name: 'Donate', href: '/donate' },
  { name: 'Contact', href: '/contact' },
];

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const { hasCmsAccess, hasDamsAccess, hasEmsAccess, hasStoreAccess, hasAdminAccess } = useAppAccess();

function isNavActive(href: string): boolean {
  if (href === '/') return route.path === '/';
  return route.path === href || route.path.startsWith(`${href}/`);
}

const isOpen = ref(false);
const scrolled = ref(false);

const isAuthenticated = computed(() => authStore.isAuthenticated);
const canAccessAcademy = computed(() => authStore.canAccessAcademy);
const showPublicAuth = computed(() => isPublicAuthEnabled);
const isLoading = ref(false);

const handleScroll = () => {
  scrolled.value = window.scrollY > 20;
};

onMounted(() => {
  window.addEventListener('scroll', handleScroll);
  window.addEventListener('click', closeUserMenu);
  handleScroll(); // Check initial scroll state
});

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll);
  window.removeEventListener('click', closeUserMenu);
  document.body.style.overflow = '';
});

const closeMenu = () => {
  isOpen.value = false;
};

watch(isOpen, (open) => {
  document.body.style.overflow = open ? 'hidden' : '';
});

const isUserMenuOpen = ref(false);
const toggleUserMenu = (event: Event) => {
  event.stopPropagation();
  isUserMenuOpen.value = !isUserMenuOpen.value;
};
const closeUserMenu = () => {
  isUserMenuOpen.value = false;
};

watch(route, () => {
  closeUserMenu();
});

const handleLogout = async () => {
  closeMenu();
  await authStore.logout();
  router.push({ name: 'home' });
};
</script>

<template>
  <div class="fixed top-4 inset-x-0 z-50 w-full max-w-full px-3 sm:px-4 pointer-events-none box-border overflow-x-clip">
    <div
      :class="[
        'pointer-events-auto mx-auto w-full max-w-7xl min-w-0 rounded-full border transition-all duration-500 box-border animate-navbar-in',
        scrolled
          ? 'bg-white/90 backdrop-blur-xl border-neutral-ivory/80 shadow-premium py-2 px-3 sm:py-2.5 sm:px-4'
          : 'bg-neutral-background/75 backdrop-blur-lg border-neutral-ivory/30 shadow-soft py-3 px-3 sm:py-4 sm:px-6'
      ]"
    >
      <div class="flex items-center justify-between gap-2 min-w-0 w-full">
        <router-link to="/" class="flex items-center gap-2 sm:gap-3 group min-w-0 shrink-0 overflow-hidden pl-0.5 sm:pl-2">
          <div class="h-9 w-9 sm:h-11 sm:w-11 shrink-0 flex items-center justify-center">
            <img
              src="/logo.webp"
              alt="SFU MSA logo"
              class="h-full w-full object-contain transition-transform group-hover:scale-105"
            />
          </div>
          <div class="hidden sm:flex flex-col min-w-0 overflow-hidden">
            <span class="text-sm sm:text-lg font-display font-extrabold text-primary leading-none tracking-tight uppercase truncate">SFU MSA</span>
            <span class="hidden md:block text-[8px] uppercase tracking-[0.2em] text-neutral-black/40 font-bold mt-0.5 truncate">Muslim Students Association</span>
          </div>
          <span class="sr-only">SFU MSA</span>
        </router-link>

        <!-- Desktop Nav -->
        <nav class="hidden xl:flex items-center gap-8 shrink-0">
        <div class="flex items-center gap-6">
          <template v-for="link in navLinks" :key="link.name">
            <a
              v-if="link.external"
              :href="link.href"
              target="_blank"
              rel="noopener noreferrer"
              :class="[
                'text-[10px] font-extrabold uppercase tracking-[0.2em] transition-all relative py-2 group',
                'text-neutral-black/55 hover:text-primary'
              ]"
            >
              {{ link.name }}
              <span class="absolute bottom-0 left-0 h-[2px] bg-primary rounded-full transition-all duration-300 w-0 group-hover:w-full" />
            </a>
            <router-link
              v-else
              :to="link.href"
              :class="[
                'text-[10px] font-extrabold uppercase tracking-[0.2em] transition-all relative py-2 group',
                isNavActive(link.href) ? 'text-primary' : 'text-neutral-black/55 hover:text-primary'
              ]"
            >
              {{ link.name }}
              <span 
                :class="[
                  'absolute bottom-0 left-0 h-[2px] bg-primary rounded-full transition-all duration-300',
                  isNavActive(link.href) ? 'w-full' : 'w-0 group-hover:w-full'
                ]"
              />
            </router-link>
          </template>
        </div>
        

        <div class="flex items-center gap-3 pr-2">
          <!-- Guest Actions -->
          <template v-if="!isAuthenticated">
            <router-link
              v-if="!isLoading && showPublicAuth"
              to="/login"
              class="inline-flex items-center gap-2 border border-primary/20 text-primary px-5 py-2.5 rounded-full text-[10px] font-extrabold uppercase tracking-widest hover:bg-primary/5 transition-all"
            >
              <LogIn class="h-3.5 w-3.5" />
              Login
            </router-link>

            <router-link
              v-if="showPublicAuth"
              to="/register"
              class="bg-primary text-white px-6 py-2.5 rounded-full text-[10px] font-extrabold uppercase tracking-widest hover:bg-secondary hover:shadow-premium transition-all hover:-translate-y-0.5 active:scale-95"
            >
              Register
            </router-link>
          </template>

          <!-- Authenticated Account Dropdown -->
          <div v-else-if="!isLoading" class="relative">
            <button
              @click="toggleUserMenu"
              class="inline-flex items-center gap-2 bg-primary text-white hover:bg-secondary px-5 py-2.5 rounded-full text-[10px] font-extrabold uppercase tracking-widest hover:shadow-premium transition-all hover:-translate-y-0.5 active:scale-95 cursor-pointer"
            >
              <span>{{ authStore.user?.name || 'My Account' }}</span>
              <ChevronDown
                :class="['h-3.5 w-3.5 transition-transform duration-300 shrink-0', isUserMenuOpen ? 'rotate-180' : '']"
              />
            </button>

            <!-- Dropdown Options Box -->
            <transition
              enter-active-class="transition duration-200 ease-out"
              enter-from-class="transform scale-95 opacity-0 translate-y-1"
              enter-to-class="transform scale-100 opacity-100 translate-y-0"
              leave-active-class="transition duration-150 ease-in"
              leave-from-class="transform scale-100 opacity-100 translate-y-0"
              leave-to-class="transform scale-95 opacity-0 translate-y-1"
            >
              <div
                v-if="isUserMenuOpen"
                class="absolute right-0 mt-3 w-56 rounded-2xl border border-neutral-ivory bg-white shadow-premium p-2 flex flex-col gap-1 z-50"
                @click.stop
              >
                <router-link
                  to="/my-tickets"
                  class="flex items-center gap-2.5 px-4 py-3 rounded-xl text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/70 hover:bg-neutral-background hover:text-primary transition-all"
                  @click="closeUserMenu"
                >
                  <svg class="h-4 w-4 shrink-0 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                  </svg>
                  My Tickets
                </router-link>

                <router-link
                  v-if="canAccessAcademy"
                  to="/academy"
                  class="flex items-center gap-2.5 px-4 py-3 rounded-xl text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/70 hover:bg-neutral-background hover:text-primary transition-all"
                  @click="closeUserMenu"
                >
                  <BookOpen class="h-4 w-4 shrink-0 text-primary" />
                  Dawah Academy
                </router-link>

                <router-link
                  v-if="hasEmsAccess"
                  to="/ems"
                  class="flex items-center gap-2.5 px-4 py-3 rounded-xl text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/70 hover:bg-neutral-background hover:text-primary transition-all"
                  @click="closeUserMenu"
                >
                  <svg class="h-4 w-4 shrink-0 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  EMS
                </router-link>

                <router-link
                  v-if="hasCmsAccess"
                  to="/cms"
                  class="flex items-center gap-2.5 px-4 py-3 rounded-xl text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/70 hover:bg-neutral-background hover:text-primary transition-all"
                  @click="closeUserMenu"
                >
                  <svg class="h-4 w-4 shrink-0 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
                  </svg>
                  CMS
                </router-link>

                <router-link
                  v-if="hasDamsAccess"
                  to="/dams"
                  class="flex items-center gap-2.5 px-4 py-3 rounded-xl text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/70 hover:bg-neutral-background hover:text-primary transition-all"
                  @click="closeUserMenu"
                >
                  <BookOpen class="h-4 w-4 shrink-0 text-primary" />
                  DAMS
                </router-link>

                <router-link
                  v-if="hasStoreAccess"
                  to="/store/admin"
                  class="flex items-center gap-2.5 px-4 py-3 rounded-xl text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/70 hover:bg-neutral-background hover:text-primary transition-all"
                  @click="closeUserMenu"
                >
                  <ShoppingBag class="h-4 w-4 shrink-0 text-primary" />
                  Store Admin
                </router-link>

                <router-link
                  v-if="hasAdminAccess"
                  to="/admin"
                  class="flex items-center gap-2.5 px-4 py-3 rounded-xl text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/70 hover:bg-neutral-background hover:text-primary transition-all"
                  @click="closeUserMenu"
                >
                  <svg class="h-4 w-4 shrink-0 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                  </svg>
                  Admin Portal
                </router-link>

                <div class="h-px bg-neutral-ivory/80 my-1" />

                <button
                  type="button"
                  @click="handleLogout(); closeUserMenu();"
                  class="flex items-center gap-2.5 px-4 py-3 rounded-xl text-[10px] font-extrabold uppercase tracking-wider text-red-600 hover:bg-red-50 transition-all w-full text-left cursor-pointer"
                >
                  <LogOut class="h-4 w-4 shrink-0" />
                  Logout
                </button>
              </div>
            </transition>
          </div>
        </div>
      </nav>

      <!-- Mobile & Tablet Toggle -->
      <div class="flex xl:hidden items-center shrink-0">
        <button
          class="p-2 sm:p-3 text-primary hover:bg-primary/5 rounded-xl transition-all cursor-pointer shrink-0"
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
    </div>

    <!-- Mobile Nav Overlay - teleported to avoid transform/overflow issues -->
    <Teleport to="body">
      <Presence>
        <div v-if="isOpen" class="fixed inset-0 z-[60] xl:hidden overflow-hidden">
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
            <div class="p-8 flex flex-col h-full border-l border-neutral-gray/20 overflow-y-auto">
              <div class="flex justify-between items-center mb-12">
                <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-black/30">Menu</span>
                <button 
                  @click="closeMenu"
                  class="p-2 -mr-2 text-neutral-black/30 hover:text-primary transition-colors cursor-pointer"
                >
                  <X :size="20" />
                </button>
              </div>

              <div v-if="!isLoading && isAuthenticated && canAccessAcademy" class="mb-4">
                <router-link
                  to="/academy"
                  class="inline-flex items-center gap-2 bg-primary text-white px-5 py-3 rounded-full text-[10px] font-bold uppercase tracking-widest w-full justify-center"
                  @click="closeMenu"
                >
                  <BookOpen class="h-3.5 w-3.5" />
                  Dawah Academy
                </router-link>
              </div>

              <div v-if="!isLoading && isAuthenticated && hasEmsAccess" class="mb-4">
                <router-link
                  to="/ems"
                  class="inline-flex items-center gap-2 bg-primary text-white px-5 py-3 rounded-full text-[10px] font-bold uppercase tracking-widest w-full justify-center"
                  @click="closeMenu"
                >
                  <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  EMS
                </router-link>
              </div>

              <div v-if="!isLoading && isAuthenticated && hasCmsAccess" class="mb-4">
                <router-link
                  to="/cms"
                  class="inline-flex items-center gap-2 bg-primary text-white px-5 py-3 rounded-full text-[10px] font-bold uppercase tracking-widest w-full justify-center"
                  @click="closeMenu"
                >
                  <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
                  </svg>
                  CMS
                </router-link>
              </div>

              <div v-if="!isLoading && isAuthenticated && hasDamsAccess" class="mb-4">
                <router-link
                  to="/dams"
                  class="inline-flex items-center gap-2 bg-primary text-white px-5 py-3 rounded-full text-[10px] font-bold uppercase tracking-widest w-full justify-center"
                  @click="closeMenu"
                >
                  <BookOpen class="h-3.5 w-3.5" />
                  DAMS
                </router-link>
              </div>

              <div v-if="!isLoading && isAuthenticated && hasStoreAccess" class="mb-4">
                <router-link
                  to="/store/admin"
                  class="inline-flex items-center gap-2 bg-primary text-white px-5 py-3 rounded-full text-[10px] font-bold uppercase tracking-widest w-full justify-center"
                  @click="closeMenu"
                >
                  <ShoppingBag class="h-3.5 w-3.5" />
                  Store Admin
                </router-link>
              </div>

              <div v-if="!isLoading && isAuthenticated && hasAdminAccess" class="mb-4">
                <router-link
                  to="/admin"
                  class="inline-flex items-center gap-2 bg-primary text-white px-5 py-3 rounded-full text-[10px] font-bold uppercase tracking-widest w-full justify-center"
                  @click="closeMenu"
                >
                  <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                  </svg>
                  Admin Portal
                </router-link>
              </div>

              <div v-if="!isLoading && isAuthenticated" class="mb-4">
                <router-link
                  to="/my-tickets"
                  class="inline-flex items-center gap-2 bg-primary text-white px-5 py-3 rounded-full text-[10px] font-bold uppercase tracking-widest w-full justify-center"
                  @click="closeMenu"
                >
                  <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                  </svg>
                  My Tickets
                </router-link>
              </div>

              <div v-if="!isLoading && !isAuthenticated && showPublicAuth" class="mb-4">
                <router-link
                  to="/login"
                  class="inline-flex items-center gap-2 border border-primary/20 text-primary px-5 py-3 rounded-full text-[10px] font-bold uppercase tracking-widest w-full justify-center"
                  @click="closeMenu"
                >
                  <LogIn class="h-3.5 w-3.5" />
                  Login
                </router-link>
              </div>

              <nav class="flex flex-col gap-2">
                <div
                  v-for="(link, i) in navLinks"
                  :key="link.name"
                >
                  <a
                    v-if="link.external"
                    :href="link.href"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-2xl font-serif py-3 flex items-center justify-between group transition-colors text-neutral-black hover:text-primary"
                    @click="closeMenu"
                  >
                    <div class="flex items-center gap-4">
                      <span class="text-[10px] font-mono text-neutral-black/20 mt-1">{{ (i + 1).toString().padStart(2, '0') }}</span>
                      {{ link.name }}
                    </div>
                    <ChevronRight class="w-5 h-5 transition-transform group-hover:translate-x-1 opacity-0 group-hover:opacity-100" />
                  </a>
                  <router-link
                    v-else
                    :to="link.href"
                    :class="[
                      'text-2xl font-serif py-3 flex items-center justify-between group transition-colors',
                      isNavActive(link.href) ? 'text-primary font-medium' : 'text-neutral-black hover:text-primary'
                    ]"
                    @click="closeMenu"
                  >
                    <div class="flex items-center gap-4">
                      <span class="text-[10px] font-mono text-neutral-black/20 mt-1">{{ (i + 1).toString().padStart(2, '0') }}</span>
                      {{ link.name }}
                    </div>
                    <ChevronRight :class="['w-5 h-5 transition-transform group-hover:translate-x-1', isNavActive(link.href) ? 'opacity-100' : 'opacity-0']" />
                  </router-link>
                </div>
              </nav>

              <div class="mt-auto pt-12 space-y-8">
                <div v-if="showPublicAuth" class="space-y-3">
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
                    <span>sfumsa@hotmail.com</span>
                    <span>@sfumsa</span>
                  </div>
                </div>
              </div>
            </div>
          </Motion>
        </div>
      </Presence>
    </Teleport>
  </div>
</template>
