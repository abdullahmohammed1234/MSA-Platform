<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Motion, Presence } from '@motionone/vue';
import { useAuthStore } from '@/stores/auth';
import { isPublicAuthEnabled } from '@/config/features';
import { 
  User,
  BookOpen, 
  ChevronRight, 
  ChevronDown,
  LogIn,
  LogOut,
  ShoppingBag,
  Heart,
  Briefcase,
  Bell,
  Search,
  X 
} from 'lucide-vue-next';

import { useAppAccess } from '@/composables/auth/useAppAccess';
import NotificationBell from '@/components/notifications/NotificationBell.vue';
import GlobalSearchModal from '@/components/search/GlobalSearchModal.vue';

interface DropdownItem {
  name: string;
  href: string;
  external?: boolean;
  requiresAuth?: boolean;
}

const aboutDropdown = {
  name: 'About',
  items: [
    { name: 'About', href: '/about' },
    { name: 'Team', href: '/team' },
    { name: 'Sponsorship', href: '/sponsorship' },
    { name: 'Media', href: '/media' },
  ] as DropdownItem[],
};

const libraryDropdown = {
  name: 'Library',
  items: [
    { name: 'Library', href: '/library', requiresAuth: true },
    { name: 'Learning Resources', href: '/featured-opportunities' },
  ] as DropdownItem[],
};

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const { hasCmsAccess, hasDamsAccess, hasEmsAccess, hasStoreAccess, hasDonationsAccess, hasSponsorshipAccess, hasAdminAccess, hasMlibmsAccess, hasVolunteeringAccess } = useAppAccess();

function isNavActive(href: string): boolean {
  if (href === '/') return route.path === '/';
  return route.path === href || route.path.startsWith(`${href}/`);
}

const isAboutActive = computed(() => {
  return aboutDropdown.items.some(item => isNavActive(item.href));
});

const isLibraryActive = computed(() => {
  return libraryDropdown.items.some(item => isNavActive(item.href));
});

const isAboutOpen = ref(false);
const isLibraryOpen = ref(false);
let aboutTimeout: ReturnType<typeof setTimeout> | null = null;
let libraryTimeout: ReturnType<typeof setTimeout> | null = null;

const openAbout = () => {
  if (aboutTimeout) clearTimeout(aboutTimeout);
  isAboutOpen.value = true;
};
const closeAboutDelayed = () => {
  aboutTimeout = setTimeout(() => {
    isAboutOpen.value = false;
  }, 150);
};
const toggleAbout = (event?: Event) => {
  event?.stopPropagation();
  isAboutOpen.value = !isAboutOpen.value;
  if (isAboutOpen.value) {
    isLibraryOpen.value = false;
    isUserMenuOpen.value = false;
  }
};

const openLibrary = () => {
  if (libraryTimeout) clearTimeout(libraryTimeout);
  isLibraryOpen.value = true;
};
const closeLibraryDelayed = () => {
  libraryTimeout = setTimeout(() => {
    isLibraryOpen.value = false;
  }, 150);
};
const toggleLibrary = (event?: Event) => {
  event?.stopPropagation();
  isLibraryOpen.value = !isLibraryOpen.value;
  if (isLibraryOpen.value) {
    isAboutOpen.value = false;
    isUserMenuOpen.value = false;
  }
};

const closeAllDropdowns = () => {
  isAboutOpen.value = false;
  isLibraryOpen.value = false;
  isUserMenuOpen.value = false;
};

const isOpen = ref(false);
const scrolled = ref(false);
const isSearchModalOpen = ref(false);

const openMobileAccordion = ref<Record<string, boolean>>({
  navigation: true,
  about: false,
  library: false,
  applications: false,
  admin: false,
});

const toggleMobileAccordion = (section: string) => {
  openMobileAccordion.value[section] = !openMobileAccordion.value[section];
};

const isAuthenticated = computed(() => authStore.isAuthenticated);
const canAccessAcademy = computed(() => authStore.canAccessAcademy);
const showPublicAuth = computed(() => isPublicAuthEnabled);
const isLoading = ref(false);

const visibleLibraryItems = computed(() => {
  return libraryDropdown.items.filter(item => !item.requiresAuth || isAuthenticated.value);
});

const logoDestination = computed(() => {
  return { name: 'home' };
});
const logoAriaLabel = computed(() => {
  return 'SFU MSA Homepage';
});

const handleScroll = () => {
  scrolled.value = window.scrollY > 20;
};

const handleKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (isOpen.value) closeMenu();
    closeAllDropdowns();
  }
};

onMounted(() => {
  window.addEventListener('scroll', handleScroll);
  window.addEventListener('click', closeAllDropdowns);
  window.addEventListener('keydown', handleKeydown);
  handleScroll();
});

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll);
  window.removeEventListener('click', closeAllDropdowns);
  window.removeEventListener('keydown', handleKeydown);
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
  isAboutOpen.value = false;
  isLibraryOpen.value = false;
  isUserMenuOpen.value = !isUserMenuOpen.value;
};
const closeUserMenu = () => {
  isUserMenuOpen.value = false;
};

watch(route, (newRoute) => {
  closeAllDropdowns();
  if (['/about', '/team', '/sponsorship', '/media'].some(p => newRoute.path === p || newRoute.path.startsWith(`${p}/`))) {
    openMobileAccordion.value.about = true;
  }
  if (['/library', '/featured-opportunities'].some(p => newRoute.path === p || newRoute.path.startsWith(`${p}/`))) {
    openMobileAccordion.value.library = true;
  }
});

const handleLogout = async () => {
  closeMenu();
  await authStore.logout();
  router.push({ name: 'home' });
};
</script>

<template>
  <div class="fixed top-3 sm:top-3.5 inset-x-0 z-50 w-full max-w-full px-3 sm:px-4 pointer-events-none box-border overflow-x-clip">
    <div
      :class="[
        'pointer-events-auto mx-auto w-full max-w-6xl 2xl:max-w-7xl min-w-0 rounded-full border transition-all duration-500 box-border animate-navbar-in',
        scrolled
          ? 'bg-white/90 backdrop-blur-xl border-neutral-ivory/80 shadow-premium py-1.5 px-3 sm:py-2 sm:px-4.5'
          : 'bg-neutral-background/85 backdrop-blur-lg border-neutral-ivory/40 shadow-soft py-2 px-3 sm:py-2.5 sm:px-5'
      ]"
    >
      <div class="flex items-center justify-between gap-2 xl:gap-3.5 min-w-0 w-full">
        <router-link
          :to="logoDestination"
          :aria-label="logoAriaLabel"
          class="flex items-center gap-2 sm:gap-2.5 group min-w-0 shrink-0 overflow-hidden pl-0.5 sm:pl-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded-xl"
        >
          <div class="h-8 w-8 sm:h-9 sm:w-9 shrink-0 flex items-center justify-center">
            <img
              src="/logo.webp"
              alt="SFU MSA logo"
              class="h-full w-full object-contain transition-transform group-hover:scale-105"
            />
          </div>
          <div class="hidden sm:flex flex-col min-w-0 overflow-hidden">
            <span class="text-sm sm:text-[15px] font-display font-extrabold text-primary leading-none tracking-tight uppercase truncate">SFU MSA</span>
            <span class="hidden 2xl:block text-[8px] uppercase tracking-[0.15em] text-neutral-black/40 font-bold mt-0.5 truncate">Muslim Students Association</span>
          </div>
          <span class="sr-only">{{ logoAriaLabel }}</span>
        </router-link>

        <!-- Desktop Nav -->
        <nav class="hidden xl:flex items-center gap-3 2xl:gap-6 shrink-0 min-w-0" aria-label="Main Navigation">
        <div class="flex items-center gap-2 xl:gap-2.5 2xl:gap-4 whitespace-nowrap shrink-0">
          <!-- Home -->
          <router-link
            to="/"
            :class="[
              'text-[10px] 2xl:text-[11px] font-extrabold uppercase tracking-[0.1em] transition-all relative py-1 group shrink-0 whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded-lg',
              isNavActive('/') ? 'text-primary' : 'text-neutral-black/60 hover:text-primary'
            ]"
          >
            Home
            <span
              :class="[
                'absolute bottom-0 left-0 h-[2px] bg-primary rounded-full transition-all duration-300',
                isNavActive('/') ? 'w-full' : 'w-0 group-hover:w-full'
              ]"
            />
          </router-link>

          <!-- About Dropdown -->
          <div
            class="relative"
            @mouseenter="openAbout"
            @mouseleave="closeAboutDelayed"
          >
            <button
              type="button"
              @click="toggleAbout"
              @keydown.esc.prevent="isAboutOpen = false"
              @keydown.down.prevent="openAbout"
              aria-haspopup="true"
              :aria-expanded="isAboutOpen"
              aria-controls="about-desktop-menu"
              :class="[
                'inline-flex items-center gap-1 text-[10px] 2xl:text-[11px] font-extrabold uppercase tracking-[0.1em] transition-all relative py-1 group shrink-0 whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded-lg',
                isAboutActive || isAboutOpen ? 'text-primary' : 'text-neutral-black/60 hover:text-primary'
              ]"
            >
              <span>About</span>
              <ChevronDown
                :class="[
                  'h-3 w-3 transition-transform duration-200 shrink-0',
                  isAboutOpen ? 'rotate-180 text-primary' : 'text-neutral-black/40 group-hover:text-primary'
                ]"
              />
              <span
                :class="[
                  'absolute bottom-0 left-0 h-[2px] bg-primary rounded-full transition-all duration-300',
                  isAboutActive ? 'w-full' : 'w-0 group-hover:w-full'
                ]"
              />
            </button>

            <!-- About Dropdown Menu -->
            <transition
              enter-active-class="transition duration-200 ease-out"
              enter-from-class="transform scale-95 opacity-0 translate-y-1"
              enter-to-class="transform scale-100 opacity-100 translate-y-0"
              leave-active-class="transition duration-150 ease-in"
              leave-from-class="transform scale-100 opacity-100 translate-y-0"
              leave-to-class="transform scale-95 opacity-0 translate-y-1"
            >
              <div
                v-if="isAboutOpen"
                id="about-desktop-menu"
                role="menu"
                aria-label="About Menu"
                class="absolute left-0 mt-1.5 w-44 rounded-xl border border-neutral-ivory/80 bg-white/95 backdrop-blur-xl shadow-premium p-1 flex flex-col gap-0.5 z-50 animate-navbar-in"
                @click.stop
              >
                <router-link
                  v-for="item in aboutDropdown.items"
                  :key="item.name"
                  :to="item.href"
                  role="menuitem"
                  :class="[
                    'flex items-center justify-between px-3 py-1.5 rounded-lg text-[10.5px] font-bold uppercase tracking-wider transition-all',
                    isNavActive(item.href)
                      ? 'bg-primary/10 text-primary font-black'
                      : 'text-neutral-black/70 hover:bg-neutral-background hover:text-primary'
                  ]"
                  @click="isAboutOpen = false"
                >
                  <span>{{ item.name }}</span>
                  <ChevronRight
                    :class="[
                      'w-3 h-3 transition-transform duration-200',
                      isNavActive(item.href) ? 'text-primary translate-x-0.5' : 'text-neutral-black/30 group-hover:translate-x-0.5'
                    ]"
                  />
                </router-link>
              </div>
            </transition>
          </div>

          <!-- Events -->
          <router-link
            to="/events"
            :class="[
              'text-[10px] 2xl:text-[11px] font-extrabold uppercase tracking-[0.1em] transition-all relative py-1 group shrink-0 whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded-lg',
              isNavActive('/events') ? 'text-primary' : 'text-neutral-black/60 hover:text-primary'
            ]"
          >
            Events
            <span
              :class="[
                'absolute bottom-0 left-0 h-[2px] bg-primary rounded-full transition-all duration-300',
                isNavActive('/events') ? 'w-full' : 'w-0 group-hover:w-full'
              ]"
            />
          </router-link>

          <!-- Prayer -->
          <router-link
            to="/prayer"
            :class="[
              'text-[10px] 2xl:text-[11px] font-extrabold uppercase tracking-[0.1em] transition-all relative py-1 group shrink-0 whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded-lg',
              isNavActive('/prayer') ? 'text-primary' : 'text-neutral-black/60 hover:text-primary'
            ]"
          >
            Prayer
            <span
              :class="[
                'absolute bottom-0 left-0 h-[2px] bg-primary rounded-full transition-all duration-300',
                isNavActive('/prayer') ? 'w-full' : 'w-0 group-hover:w-full'
              ]"
            />
          </router-link>

          <!-- Library Dropdown -->
          <div
            class="relative"
            @mouseenter="openLibrary"
            @mouseleave="closeLibraryDelayed"
          >
            <button
              type="button"
              @click="toggleLibrary"
              @keydown.esc.prevent="isLibraryOpen = false"
              @keydown.down.prevent="openLibrary"
              aria-haspopup="true"
              :aria-expanded="isLibraryOpen"
              aria-controls="library-desktop-menu"
              :class="[
                'inline-flex items-center gap-1 text-[10px] 2xl:text-[11px] font-extrabold uppercase tracking-[0.1em] transition-all relative py-1 group shrink-0 whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded-lg',
                isLibraryActive || isLibraryOpen ? 'text-primary' : 'text-neutral-black/60 hover:text-primary'
              ]"
            >
              <span>Library</span>
              <ChevronDown
                :class="[
                  'h-3 w-3 transition-transform duration-200 shrink-0',
                  isLibraryOpen ? 'rotate-180 text-primary' : 'text-neutral-black/40 group-hover:text-primary'
                ]"
              />
              <span
                :class="[
                  'absolute bottom-0 left-0 h-[2px] bg-primary rounded-full transition-all duration-300',
                  isLibraryActive ? 'w-full' : 'w-0 group-hover:w-full'
                ]"
              />
            </button>

            <!-- Library Dropdown Menu -->
            <transition
              enter-active-class="transition duration-200 ease-out"
              enter-from-class="transform scale-95 opacity-0 translate-y-1"
              enter-to-class="transform scale-100 opacity-100 translate-y-0"
              leave-active-class="transition duration-150 ease-in"
              leave-from-class="transform scale-100 opacity-100 translate-y-0"
              leave-to-class="transform scale-95 opacity-0 translate-y-1"
            >
              <div
                v-if="isLibraryOpen"
                id="library-desktop-menu"
                role="menu"
                aria-label="Library Menu"
                class="absolute left-0 mt-1.5 w-52 rounded-xl border border-neutral-ivory/80 bg-white/95 backdrop-blur-xl shadow-premium p-1 flex flex-col gap-0.5 z-50 animate-navbar-in"
                @click.stop
              >
                <router-link
                  v-for="item in visibleLibraryItems"
                  :key="item.name"
                  :to="item.href"
                  role="menuitem"
                  :class="[
                    'flex items-center justify-between px-3 py-1.5 rounded-lg text-[10.5px] font-bold uppercase tracking-wider transition-all',
                    isNavActive(item.href)
                      ? 'bg-primary/10 text-primary font-black'
                      : 'text-neutral-black/70 hover:bg-neutral-background hover:text-primary'
                  ]"
                  @click="isLibraryOpen = false"
                >
                  <span>{{ item.name }}</span>
                  <ChevronRight
                    :class="[
                      'w-3 h-3 transition-transform duration-200',
                      isNavActive(item.href) ? 'text-primary translate-x-0.5' : 'text-neutral-black/30 group-hover:translate-x-0.5'
                    ]"
                  />
                </router-link>
              </div>
            </transition>
          </div>

          <!-- Store -->
          <router-link
            to="/store"
            :class="[
              'text-[10px] 2xl:text-[11px] font-extrabold uppercase tracking-[0.1em] transition-all relative py-1 group shrink-0 whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded-lg',
              isNavActive('/store') ? 'text-primary' : 'text-neutral-black/60 hover:text-primary'
            ]"
          >
            Store
            <span
              :class="[
                'absolute bottom-0 left-0 h-[2px] bg-primary rounded-full transition-all duration-300',
                isNavActive('/store') ? 'w-full' : 'w-0 group-hover:w-full'
              ]"
            />
          </router-link>

          <!-- Donate -->
          <router-link
            to="/donate"
            :class="[
              'text-[10px] 2xl:text-[11px] font-extrabold uppercase tracking-[0.1em] transition-all relative py-1 group shrink-0 whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded-lg',
              isNavActive('/donate') ? 'text-primary' : 'text-neutral-black/60 hover:text-primary'
            ]"
          >
            Donate
            <span
              :class="[
                'absolute bottom-0 left-0 h-[2px] bg-primary rounded-full transition-all duration-300',
                isNavActive('/donate') ? 'w-full' : 'w-0 group-hover:w-full'
              ]"
            />
          </router-link>

          <!-- Contact -->
          <router-link
            to="/contact"
            :class="[
              'text-[10px] 2xl:text-[11px] font-extrabold uppercase tracking-[0.1em] transition-all relative py-1 group shrink-0 whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded-lg',
              isNavActive('/contact') ? 'text-primary' : 'text-neutral-black/60 hover:text-primary'
            ]"
          >
            Contact
            <span
              :class="[
                'absolute bottom-0 left-0 h-[2px] bg-primary rounded-full transition-all duration-300',
                isNavActive('/contact') ? 'w-full' : 'w-0 group-hover:w-full'
              ]"
            />
          </router-link>
        </div>
        

        <div class="flex items-center gap-1.5 2xl:gap-2.5 pr-0.5 shrink-0">
          <!-- Global Search Button (Icon Only) -->
          <button
            type="button"
            @click="isSearchModalOpen = true"
            class="inline-flex items-center justify-center w-8 h-8 2xl:w-8.5 2xl:h-8.5 bg-white/80 hover:bg-white text-neutral-black/70 hover:text-primary rounded-full border border-neutral-ivory/80 shadow-soft hover:shadow-md transition-all shrink-0"
            title="Search Platform (Ctrl+K)"
            aria-label="Search Platform"
          >
            <Search class="h-3.5 w-3.5 text-primary" />
          </button>

          <!-- Guest Actions -->
          <template v-if="!isAuthenticated">
            <router-link
              v-if="!isLoading && showPublicAuth"
              to="/login"
              class="inline-flex items-center gap-1.5 border border-primary/20 text-primary px-3 py-1.5 2xl:px-4 2xl:py-2 rounded-full text-[10px] font-extrabold uppercase tracking-wider hover:bg-primary/5 transition-all shrink-0 whitespace-nowrap"
            >
              <LogIn class="h-3 w-3" />
              Login
            </router-link>

            <router-link
              v-if="showPublicAuth"
              to="/register"
              class="bg-primary text-white px-3.5 py-1.5 2xl:px-4.5 2xl:py-2 rounded-full text-[10px] font-extrabold uppercase tracking-wider hover:bg-secondary hover:shadow-premium transition-all hover:-translate-y-0.5 active:scale-95 shrink-0 whitespace-nowrap"
            >
              Register
            </router-link>
          </template>

          <!-- Authenticated Account Dropdown -->
          <div v-else-if="!isLoading" class="flex items-center gap-1.5 2xl:gap-2.5 shrink-0">
            <NotificationBell />

            <div class="relative shrink-0">
              <button
                @click="toggleUserMenu"
                class="inline-flex items-center gap-1.5 bg-primary text-white hover:bg-secondary px-3.5 py-1.5 2xl:px-4.5 2xl:py-2 rounded-full text-[10px] font-extrabold uppercase tracking-wider hover:shadow-premium transition-all hover:-translate-y-0.5 active:scale-95 cursor-pointer shrink-0 whitespace-nowrap"
              >
                <span>{{ authStore.user?.name || 'My Account' }}</span>
                <ChevronDown
                  :class="['h-3 w-3 transition-transform duration-300 shrink-0', isUserMenuOpen ? 'rotate-180' : '']"
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
                  class="absolute right-0 mt-2 w-52 rounded-xl border border-neutral-ivory bg-white shadow-premium p-1.5 flex flex-col gap-0.5 z-50"
                  @click.stop
                >
                  <router-link
                    to="/notifications"
                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/70 hover:bg-neutral-background hover:text-primary transition-all"
                    @click="closeUserMenu"
                  >
                    <Bell class="h-3.5 w-3.5 shrink-0 text-primary" />
                    Notification Center
                  </router-link>

                  <router-link
                    to="/my-tickets"
                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/70 hover:bg-neutral-background hover:text-primary transition-all"
                    @click="closeUserMenu"
                  >
                    <svg class="h-3.5 w-3.5 shrink-0 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                    </svg>
                    My Tickets
                  </router-link>

                  <router-link
                    to="/account"
                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-[10px] font-extrabold uppercase tracking-wider text-neutral-black/70 hover:bg-neutral-background hover:text-primary transition-all font-black text-primary bg-primary/5"
                    @click="closeUserMenu"
                  >
                    <User class="h-3.5 w-3.5 shrink-0 text-primary" />
                    My Portal & Dashboard
                  </router-link>

                  <div class="h-px bg-neutral-ivory/80 my-0.5" />

                  <button
                    type="button"
                    @click="handleLogout(); closeUserMenu();"
                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-[10px] font-extrabold uppercase tracking-wider text-red-600 hover:bg-red-50 transition-all w-full text-left cursor-pointer"
                  >
                    <LogOut class="h-3.5 w-3.5 shrink-0" />
                    Logout
                  </button>
                </div>
              </transition>
            </div>
          </div>
        </div>
      </nav>

      <!-- Mobile & Tablet Actions & Toggle -->
      <div class="flex xl:hidden items-center gap-1.5 sm:gap-2 shrink-0">
        <!-- Mobile Search Button -->
        <button
          type="button"
          @click="isSearchModalOpen = true"
          class="inline-flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9 bg-white/80 hover:bg-white text-primary rounded-full border border-neutral-ivory/80 shadow-soft transition-all shrink-0"
          title="Search Platform"
          aria-label="Search Platform"
        >
          <Search class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-primary" />
        </button>

        <!-- Mobile Profile / Dashboard Shortcut (Authenticated) -->
        <router-link
          v-if="isAuthenticated"
          to="/account"
          class="inline-flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9 bg-primary text-white rounded-full border border-primary/20 shadow-soft transition-all hover:bg-secondary shrink-0"
          title="My Profile & Dashboard"
          aria-label="My Profile & Dashboard"
        >
          <User class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-white" />
        </router-link>

        <!-- Mobile Menu Toggle Button -->
        <button
          class="p-2 sm:p-2.5 text-primary hover:bg-primary/5 rounded-xl transition-all shrink-0"
          @click="isOpen = !isOpen"
          :aria-label="isOpen ? 'Close menu' : 'Open menu'"
          :aria-expanded="isOpen"
          aria-controls="mobile-menu-drawer"
        >
          <div class="w-5 h-5 sm:w-6 sm:h-6 flex flex-col justify-center items-center relative">
            <span :class="['w-5 sm:w-6 h-0.5 bg-current rounded-full transition-all duration-300 absolute', isOpen ? 'rotate-45' : '-translate-y-1.5']" />
            <span :class="['w-5 sm:w-6 h-0.5 bg-current rounded-full transition-all duration-300 absolute', isOpen ? 'opacity-0' : 'opacity-100']" />
            <span :class="['w-5 sm:w-6 h-0.5 bg-current rounded-full transition-all duration-300 absolute', isOpen ? '-rotate-45' : 'translate-y-1.5']" />
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
            id="mobile-menu-drawer"
            role="dialog"
            aria-modal="true"
            aria-label="Navigation Menu"
            :initial="{ x: '100%' }"
            :animate="{ x: 0 }"
            :exit="{ x: '100%' }"
            :transition="{ type: 'spring', damping: 30, stiffness: 300 }"
            class="absolute top-0 right-0 bottom-0 w-[85%] max-w-sm bg-neutral-background shadow-2xl flex flex-col"
          >
            <div class="p-8 flex flex-col h-full border-l border-neutral-gray/20 overflow-y-auto">
              <div class="flex justify-between items-center mb-6">
                <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-black/30">Menu</span>
                <button 
                  @click="closeMenu"
                  class="p-2 -mr-2 text-neutral-black/30 hover:text-primary transition-colors cursor-pointer"
                  aria-label="Close menu"
                >
                  <X :size="20" />
                </button>
              </div>

              <!-- Mobile Search Trigger Bar -->
              <button
                type="button"
                @click="closeMenu(); isSearchModalOpen = true;"
                class="w-full flex items-center gap-3 px-4 py-3 mb-4 rounded-2xl bg-white border border-neutral-ivory/80 text-neutral-black/60 hover:text-primary hover:border-primary/30 shadow-soft transition-all text-left text-xs font-medium"
                aria-label="Search Platform"
              >
                <Search class="h-4 w-4 text-primary shrink-0" />
                <span class="flex-1 truncate">Search events, books, resources...</span>
                <span class="text-[9px] font-mono bg-neutral-background px-1.5 py-0.5 rounded text-neutral-black/40">Search</span>
              </button>

              <!-- Member Profile & Dashboard Card (Authenticated) -->
              <div v-if="isAuthenticated" class="mb-4 p-4 rounded-2xl bg-primary/5 border border-primary/15 flex flex-col gap-3">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-bold text-xs uppercase shrink-0">
                    {{ authStore.user?.name?.charAt(0) || 'U' }}
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold text-neutral-black truncate">{{ authStore.user?.name || 'Member' }}</p>
                    <p class="text-[10px] text-neutral-black/50 truncate">{{ authStore.user?.email }}</p>
                  </div>
                </div>
                <div class="grid grid-cols-2 gap-2 pt-2 border-t border-primary/10">
                  <router-link
                    to="/account"
                    class="flex items-center justify-center gap-1.5 py-2.5 px-3 rounded-xl bg-primary text-white text-[10px] font-extrabold uppercase tracking-wider hover:bg-secondary transition-all"
                    @click="closeMenu"
                  >
                    <User class="h-3.5 w-3.5" />
                    Dashboard
                  </router-link>
                  <router-link
                    to="/my-tickets"
                    class="flex items-center justify-center gap-1.5 py-2.5 px-3 rounded-xl bg-white text-primary border border-primary/20 text-[10px] font-extrabold uppercase tracking-wider hover:bg-primary/5 transition-all"
                    @click="closeMenu"
                  >
                    Tickets
                  </router-link>
                </div>
              </div>

              <!-- Guest Member Portal Card (Unauthenticated) -->
              <div v-else-if="showPublicAuth" class="mb-4 p-3.5 rounded-2xl bg-white border border-neutral-ivory/80 shadow-soft flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 min-w-0">
                  <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary shrink-0">
                    <User class="h-4 w-4" />
                  </div>
                  <div class="min-w-0">
                    <p class="text-xs font-bold text-neutral-black">Member Portal</p>
                    <p class="text-[10px] text-neutral-black/50">Access your dashboard</p>
                  </div>
                </div>
                <router-link
                  to="/login"
                  class="py-1.5 px-3 rounded-xl bg-primary text-white text-[10px] font-extrabold uppercase tracking-wider hover:bg-secondary transition-all shrink-0"
                  @click="closeMenu"
                >
                  Sign In
                </router-link>
              </div>

              <!-- Accordion 1: Main Navigation -->
              <div class="border-b border-neutral-ivory/80 pb-3 mb-3">
                <button
                  @click="toggleMobileAccordion('navigation')"
                  class="w-full flex items-center justify-between py-2 text-[11px] font-extrabold uppercase tracking-widest text-neutral-black/50 hover:text-primary transition-colors cursor-pointer min-h-[44px]"
                >
                  <span>Navigation</span>
                  <ChevronDown :class="['w-4 h-4 transition-transform duration-200', openMobileAccordion.navigation ? 'rotate-180' : '']" />
                </button>

                <nav v-if="openMobileAccordion.navigation" class="flex flex-col gap-1 pt-1">
                  <!-- Home -->
                  <router-link
                    to="/"
                    :class="[
                      'text-base font-serif min-h-[44px] flex items-center justify-between group transition-colors px-2 rounded-xl',
                      isNavActive('/') ? 'text-primary font-medium bg-primary/5' : 'text-neutral-black hover:text-primary'
                    ]"
                    @click="closeMenu"
                  >
                    <div class="flex items-center gap-3">
                      <span class="text-[10px] font-mono text-neutral-black/30">01</span>
                      Home
                    </div>
                    <ChevronRight :class="['w-4 h-4 transition-transform group-hover:translate-x-1', isNavActive('/') ? 'opacity-100 text-primary' : 'opacity-0']" />
                  </router-link>

                  <!-- About Sub-Accordion / Group -->
                  <div class="rounded-xl border border-neutral-ivory/60 bg-white/50 overflow-hidden my-0.5">
                    <button
                      type="button"
                      @click="toggleMobileAccordion('about')"
                      :class="[
                        'w-full flex items-center justify-between px-3 py-2.5 text-base font-serif transition-colors min-h-[44px]',
                        isAboutActive ? 'text-primary font-medium' : 'text-neutral-black hover:text-primary'
                      ]"
                    >
                      <div class="flex items-center gap-3">
                        <span class="text-[10px] font-mono text-neutral-black/30">02</span>
                        About
                      </div>
                      <ChevronDown :class="['w-4 h-4 transition-transform duration-200', openMobileAccordion.about ? 'rotate-180 text-primary' : 'text-neutral-black/40']" />
                    </button>

                    <div v-if="openMobileAccordion.about" class="pl-7 pr-2 pb-2 space-y-1 flex flex-col border-t border-neutral-ivory/40 pt-1.5 bg-neutral-background/40">
                      <router-link
                        v-for="sub in aboutDropdown.items"
                        :key="sub.name"
                        :to="sub.href"
                        :class="[
                          'text-sm font-sans min-h-[40px] flex items-center justify-between px-2.5 py-1.5 rounded-lg transition-colors',
                          isNavActive(sub.href) ? 'text-primary font-bold bg-primary/10' : 'text-neutral-black/80 hover:text-primary hover:bg-white'
                        ]"
                        @click="closeMenu"
                      >
                        <span>{{ sub.name }}</span>
                        <ChevronRight :class="['w-3.5 h-3.5', isNavActive(sub.href) ? 'text-primary' : 'text-neutral-black/30']" />
                      </router-link>
                    </div>
                  </div>

                  <!-- Events -->
                  <router-link
                    to="/events"
                    :class="[
                      'text-base font-serif min-h-[44px] flex items-center justify-between group transition-colors px-2 rounded-xl',
                      isNavActive('/events') ? 'text-primary font-medium bg-primary/5' : 'text-neutral-black hover:text-primary'
                    ]"
                    @click="closeMenu"
                  >
                    <div class="flex items-center gap-3">
                      <span class="text-[10px] font-mono text-neutral-black/30">03</span>
                      Events
                    </div>
                    <ChevronRight :class="['w-4 h-4 transition-transform group-hover:translate-x-1', isNavActive('/events') ? 'opacity-100 text-primary' : 'opacity-0']" />
                  </router-link>

                  <!-- Prayer -->
                  <router-link
                    to="/prayer"
                    :class="[
                      'text-base font-serif min-h-[44px] flex items-center justify-between group transition-colors px-2 rounded-xl',
                      isNavActive('/prayer') ? 'text-primary font-medium bg-primary/5' : 'text-neutral-black hover:text-primary'
                    ]"
                    @click="closeMenu"
                  >
                    <div class="flex items-center gap-3">
                      <span class="text-[10px] font-mono text-neutral-black/30">04</span>
                      Prayer
                    </div>
                    <ChevronRight :class="['w-4 h-4 transition-transform group-hover:translate-x-1', isNavActive('/prayer') ? 'opacity-100 text-primary' : 'opacity-0']" />
                  </router-link>

                  <!-- Library Sub-Accordion / Group -->
                  <div class="rounded-xl border border-neutral-ivory/60 bg-white/50 overflow-hidden my-0.5">
                    <button
                      type="button"
                      @click="toggleMobileAccordion('library')"
                      :class="[
                        'w-full flex items-center justify-between px-3 py-2.5 text-base font-serif transition-colors min-h-[44px]',
                        isLibraryActive ? 'text-primary font-medium' : 'text-neutral-black hover:text-primary'
                      ]"
                    >
                      <div class="flex items-center gap-3">
                        <span class="text-[10px] font-mono text-neutral-black/30">05</span>
                        Library
                      </div>
                      <ChevronDown :class="['w-4 h-4 transition-transform duration-200', openMobileAccordion.library ? 'rotate-180 text-primary' : 'text-neutral-black/40']" />
                    </button>

                    <div v-if="openMobileAccordion.library" class="pl-7 pr-2 pb-2 space-y-1 flex flex-col border-t border-neutral-ivory/40 pt-1.5 bg-neutral-background/40">
                      <router-link
                        v-for="sub in visibleLibraryItems"
                        :key="sub.name"
                        :to="sub.href"
                        :class="[
                          'text-sm font-sans min-h-[40px] flex items-center justify-between px-2.5 py-1.5 rounded-lg transition-colors',
                          isNavActive(sub.href) ? 'text-primary font-bold bg-primary/10' : 'text-neutral-black/80 hover:text-primary hover:bg-white'
                        ]"
                        @click="closeMenu"
                      >
                        <span>{{ sub.name }}</span>
                        <ChevronRight :class="['w-3.5 h-3.5', isNavActive(sub.href) ? 'text-primary' : 'text-neutral-black/30']" />
                      </router-link>
                    </div>
                  </div>

                  <!-- Store -->
                  <router-link
                    to="/store"
                    :class="[
                      'text-base font-serif min-h-[44px] flex items-center justify-between group transition-colors px-2 rounded-xl',
                      isNavActive('/store') ? 'text-primary font-medium bg-primary/5' : 'text-neutral-black hover:text-primary'
                    ]"
                    @click="closeMenu"
                  >
                    <div class="flex items-center gap-3">
                      <span class="text-[10px] font-mono text-neutral-black/30">06</span>
                      Store
                    </div>
                    <ChevronRight :class="['w-4 h-4 transition-transform group-hover:translate-x-1', isNavActive('/store') ? 'opacity-100 text-primary' : 'opacity-0']" />
                  </router-link>

                  <!-- Donate -->
                  <router-link
                    to="/donate"
                    :class="[
                      'text-base font-serif min-h-[44px] flex items-center justify-between group transition-colors px-2 rounded-xl',
                      isNavActive('/donate') ? 'text-primary font-medium bg-primary/5' : 'text-neutral-black hover:text-primary'
                    ]"
                    @click="closeMenu"
                  >
                    <div class="flex items-center gap-3">
                      <span class="text-[10px] font-mono text-neutral-black/30">07</span>
                      Donate
                    </div>
                    <ChevronRight :class="['w-4 h-4 transition-transform group-hover:translate-x-1', isNavActive('/donate') ? 'opacity-100 text-primary' : 'opacity-0']" />
                  </router-link>

                  <!-- Contact -->
                  <router-link
                    to="/contact"
                    :class="[
                      'text-base font-serif min-h-[44px] flex items-center justify-between group transition-colors px-2 rounded-xl',
                      isNavActive('/contact') ? 'text-primary font-medium bg-primary/5' : 'text-neutral-black hover:text-primary'
                    ]"
                    @click="closeMenu"
                  >
                    <div class="flex items-center gap-3">
                      <span class="text-[10px] font-mono text-neutral-black/30">08</span>
                      Contact
                    </div>
                    <ChevronRight :class="['w-4 h-4 transition-transform group-hover:translate-x-1', isNavActive('/contact') ? 'opacity-100 text-primary' : 'opacity-0']" />
                  </router-link>
                </nav>
              </div>

              <!-- Accordion 2: Applications -->
              <div v-if="!isLoading && isAuthenticated && (hasEmsAccess || hasCmsAccess || hasDamsAccess || hasStoreAccess || hasDonationsAccess || hasSponsorshipAccess || hasMlibmsAccess || hasVolunteeringAccess || canAccessAcademy)" class="border-b border-neutral-ivory/80 pb-3 mb-3">
                <button
                  @click="toggleMobileAccordion('applications')"
                  class="w-full flex items-center justify-between py-2 text-[11px] font-extrabold uppercase tracking-widest text-neutral-black/50 hover:text-primary transition-colors cursor-pointer min-h-[44px]"
                >
                  <span>Applications</span>
                  <ChevronDown :class="['w-4 h-4 transition-transform duration-200', openMobileAccordion.applications ? 'rotate-180' : '']" />
                </button>

                <div v-if="openMobileAccordion.applications" class="space-y-2 pt-1">
                  <router-link
                    v-if="canAccessAcademy"
                    to="/academy"
                    class="flex items-center gap-3 px-4 min-h-[44px] rounded-xl text-xs font-bold uppercase tracking-wider bg-primary/10 text-primary hover:bg-primary/20 transition-all"
                    @click="closeMenu"
                  >
                    <BookOpen class="h-4 w-4" /> Dawah Academy
                  </router-link>
                  <router-link
                    v-if="hasEmsAccess"
                    to="/ems"
                    class="flex items-center gap-3 px-4 min-h-[44px] rounded-xl text-xs font-bold uppercase tracking-wider bg-primary/10 text-primary hover:bg-primary/20 transition-all"
                    @click="closeMenu"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    EMS (Events)
                  </router-link>
                  <router-link
                    v-if="hasCmsAccess"
                    to="/cms"
                    class="flex items-center gap-3 px-4 min-h-[44px] rounded-xl text-xs font-bold uppercase tracking-wider bg-primary/10 text-primary hover:bg-primary/20 transition-all"
                    @click="closeMenu"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
                    </svg>
                    CMS (Content)
                  </router-link>
                  <router-link
                    v-if="hasDamsAccess"
                    to="/dams"
                    class="flex items-center gap-3 px-4 min-h-[44px] rounded-xl text-xs font-bold uppercase tracking-wider bg-primary/10 text-primary hover:bg-primary/20 transition-all"
                    @click="closeMenu"
                  >
                    <BookOpen class="h-4 w-4" /> DAMS (Academy Mgmt)
                  </router-link>
                  <router-link
                    v-if="hasStoreAccess"
                    to="/store/admin"
                    class="flex items-center gap-3 px-4 min-h-[44px] rounded-xl text-xs font-bold uppercase tracking-wider bg-primary/10 text-primary hover:bg-primary/20 transition-all"
                    @click="closeMenu"
                  >
                    <ShoppingBag class="h-4 w-4" /> Store Admin
                  </router-link>
                  <router-link
                    v-if="hasDonationsAccess"
                    to="/donations/admin"
                    class="flex items-center gap-3 px-4 min-h-[44px] rounded-xl text-xs font-bold uppercase tracking-wider bg-primary/10 text-primary hover:bg-primary/20 transition-all"
                    @click="closeMenu"
                  >
                    <Heart class="h-4 w-4" /> DMS (Donations)
                  </router-link>
                  <router-link
                    v-if="hasSponsorshipAccess"
                    to="/sponsorship/admin"
                    class="flex items-center gap-3 px-4 min-h-[44px] rounded-xl text-xs font-bold uppercase tracking-wider bg-primary/10 text-primary hover:bg-primary/20 transition-all"
                    @click="closeMenu"
                  >
                    <Briefcase class="h-4 w-4" /> SPMS (Sponsorship)
                  </router-link>
                  <router-link
                    v-if="hasMlibmsAccess"
                    to="/library/admin"
                    class="flex items-center gap-3 px-4 min-h-[44px] rounded-xl text-xs font-bold uppercase tracking-wider bg-primary/10 text-primary hover:bg-primary/20 transition-all"
                    @click="closeMenu"
                  >
                    <BookOpen class="h-4 w-4" /> MLibMS (Library)
                  </router-link>
                  <router-link
                    v-if="hasVolunteeringAccess"
                    to="/admin/volunteering"
                    class="flex items-center gap-3 px-4 min-h-[44px] rounded-xl text-xs font-bold uppercase tracking-wider bg-primary/10 text-primary hover:bg-primary/20 transition-all"
                    @click="closeMenu"
                  >
                    <Heart class="h-4 w-4" /> Volunteering Admin
                  </router-link>
                </div>
              </div>

              <!-- Accordion 3: Administration -->
              <div v-if="!isLoading && isAuthenticated && hasAdminAccess" class="border-b border-neutral-ivory/80 pb-3 mb-3">
                <button
                  @click="toggleMobileAccordion('admin')"
                  class="w-full flex items-center justify-between py-2 text-[11px] font-extrabold uppercase tracking-widest text-neutral-black/50 hover:text-primary transition-colors cursor-pointer min-h-[44px]"
                >
                  <span>Administration</span>
                  <ChevronDown :class="['w-4 h-4 transition-transform duration-200', openMobileAccordion.admin ? 'rotate-180' : '']" />
                </button>

                <div v-if="openMobileAccordion.admin" class="space-y-2 pt-1">
                  <router-link
                    to="/admin"
                    class="flex items-center gap-3 px-4 min-h-[44px] rounded-xl text-xs font-bold uppercase tracking-wider bg-primary text-white hover:bg-secondary transition-all"
                    @click="closeMenu"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                    Admin Portal
                  </router-link>
                </div>
              </div>

              <div class="mt-auto pt-12 space-y-8">
                <div v-if="showPublicAuth" class="space-y-3">
                  <template v-if="!isAuthenticated">
                    <router-link
                      to="/login"
                      class="block w-full py-4 border border-primary/20 text-primary bg-white rounded-2xl text-center text-xs font-bold uppercase tracking-[0.2em] hover:bg-primary/5 active:scale-[0.98] transition-all"
                      @click="closeMenu"
                    >
                      Login
                    </router-link>
                    <router-link
                      to="/register"
                      class="block w-full py-4 bg-primary text-white rounded-2xl text-center text-xs font-bold uppercase tracking-[0.2em] shadow-xl shadow-primary/10 hover:bg-secondary active:scale-[0.98] transition-all"
                      @click="closeMenu"
                    >
                      Register
                    </router-link>
                  </template>
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
    <!-- Global Search Overlay Modal -->
    <GlobalSearchModal :is-open="isSearchModalOpen" @close="isSearchModalOpen = false" @open="isSearchModalOpen = true" />
  </div>
</template>
