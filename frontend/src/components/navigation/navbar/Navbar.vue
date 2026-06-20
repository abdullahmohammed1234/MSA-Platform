<script setup lang="ts">
import { ref, computed } from 'vue';
import { Motion } from '@motionone/vue';
import type { NavbarProps } from './types';
import { buttonHover } from '@/design-system/animations/hover';

const props = withDefaults(defineProps<NavbarProps>(), {
  brandName: 'SFU MSA',
  isAuthenticated: false,
  user: null
});

const emit = defineEmits<{
  (e: 'login'): void;
  (e: 'logout'): void;
  (e: 'navigate', path: string): void;
}>();

const isMobileMenuOpen = ref(false);
const isUserDropdownOpen = ref(false);

const toggleMobileMenu = () => {
  isMobileMenuOpen.value = !isMobileMenuOpen.value;
};

const toggleUserDropdown = () => {
  isUserDropdownOpen.value = !isUserDropdownOpen.value;
};

const closeUserDropdown = () => {
  setTimeout(() => {
    isUserDropdownOpen.value = false;
  }, 200);
};

const handleLogout = () => {
  isUserDropdownOpen.value = false;
  emit('logout');
};

const canAccessAcademy = computed(() => {
  const roles = props.user?.roles ?? (props.user?.role ? [props.user.role] : []);
  return roles.includes('volunteer')
    || roles.includes('mentor')
    || roles.includes('admin')
    || roles.includes('super-admin');
});
</script>

<template>
  <nav class="sticky top-0 z-40 w-full bg-white/80 backdrop-blur-md border-b border-neutral-ivory">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16">
        
        <!-- Logo/Brand Section -->
        <div class="flex-shrink-0 flex items-center">
          <router-link to="/" class="flex items-center gap-2">
            <slot name="logo">
              <!-- Default scholastic calligraphic logo icon -->
              <div class="h-9 w-9 rounded-full bg-primary flex items-center justify-center text-white font-display font-bold text-lg">
                M
              </div>
              <span class="font-display font-bold text-lg text-primary tracking-wide">
                {{ brandName }}
              </span>
            </slot>
          </router-link>
        </div>

        <!-- Desktop Navigation links -->
        <div class="hidden md:flex items-center gap-6">
          <router-link
            v-for="item in navItems"
            :key="item.path"
            :to="item.path"
            class="text-neutral-muted hover:text-primary transition-colors text-sm font-medium relative py-1"
            active-class="text-primary font-semibold border-b-2 border-accent-gold"
          >
            {{ item.label }}
          </router-link>
        </div>

        <!-- Desktop Action Buttons / User Menu -->
        <div class="hidden md:flex items-center gap-4">
          <!-- Not Authenticated -->
          <div v-if="!isAuthenticated" class="flex items-center gap-3">
            <slot name="auth-buttons">
              <router-link to="/login">
                <button class="text-primary hover:text-primary/80 text-sm font-medium px-3 py-2">
                  Sign In
                </button>
              </router-link>
              <router-link to="/register">
                <Motion
                  :hover="buttonHover.hover"
                  :press="buttonHover.tap"
                  :transition="buttonHover.transition"
                  as="button"
                  class="bg-primary text-white text-sm font-semibold px-4 py-2 rounded-md hover:bg-primary/95 shadow-sm"
                >
                  Join Us
                </Motion>
              </router-link>
            </slot>
          </div>

          <!-- Authenticated User Dropdown -->
          <div v-else class="relative">
            <button
              @click="toggleUserDropdown"
              @blur="closeUserDropdown"
              class="flex items-center gap-2 text-sm font-medium text-neutral-black hover:text-primary focus:outline-none cursor-pointer"
            >
              <slot name="user-trigger">
                <!-- Fallback user avatar -->
                <div class="h-8 w-8 rounded-full bg-neutral-ivory border border-neutral-ivory/50 flex items-center justify-center font-bold text-primary">
                  {{ user?.name ? user.name[0].toUpperCase() : 'U' }}
                </div>
                <span class="max-w-[120px] truncate">{{ user?.name }}</span>
                <svg class="h-4 w-4 text-neutral-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </slot>
            </button>

            <!-- Dropdown Options -->
            <div
              v-if="isUserDropdownOpen"
              class="absolute right-0 mt-2 w-48 bg-white border border-neutral-ivory rounded-xl shadow-premium-md py-1 z-50"
            >
              <div class="px-4 py-2 border-b border-neutral-ivory/50 text-xs text-neutral-muted">
                Signed in as<br />
                <span class="font-semibold text-neutral-black break-all">{{ user?.email }}</span>
              </div>
              <router-link
                v-if="canAccessAcademy"
                to="/academy/dashboard"
                class="block px-4 py-2 text-sm text-neutral-black hover:bg-neutral-background transition-colors"
                @click="isUserDropdownOpen = false"
              >
                Dawah Academy
              </router-link>
              <router-link
                v-if="user?.role === 'admin'"
                to="/admin/dashboard"
                class="block px-4 py-2 text-sm text-neutral-black hover:bg-neutral-background transition-colors"
                @click="isUserDropdownOpen = false"
              >
                Admin Portal
              </router-link>
              <button
                @click="handleLogout"
                class="w-full text-left block px-4 py-2 text-sm text-secondary hover:bg-neutral-background transition-colors border-t border-neutral-ivory/50 cursor-pointer"
              >
                Sign Out
              </button>
            </div>
          </div>
        </div>

        <!-- Mobile Hamburguer Menu Button -->
        <div class="flex items-center md:hidden">
          <button
            @click="toggleMobileMenu"
            class="text-neutral-muted hover:text-primary focus:outline-none"
            aria-label="Toggle Menu"
          >
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                v-if="!isMobileMenuOpen"
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 6h16M4 12h16M4 18h16"
              />
              <path
                v-else
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Mobile Collapsed Menu -->
    <div v-if="isMobileMenuOpen" class="md:hidden border-t border-neutral-ivory bg-white">
      <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
        <router-link
          v-for="item in navItems"
          :key="item.path"
          :to="item.path"
          class="block px-3 py-2 rounded-md text-base font-medium text-neutral-muted hover:text-primary hover:bg-neutral-background transition-colors"
          active-class="text-primary font-semibold bg-neutral-ivory/50"
          @click="isMobileMenuOpen = false"
        >
          {{ item.label }}
        </router-link>
      </div>

      <div class="pt-4 pb-3 border-t border-neutral-ivory/50">
        <div v-if="!isAuthenticated" class="px-5 flex flex-col gap-3">
          <router-link to="/login" class="w-full" @click="isMobileMenuOpen = false">
            <button class="w-full text-center text-primary font-medium py-2 hover:bg-neutral-background rounded-md">
              Sign In
            </button>
          </router-link>
          <router-link to="/register" class="w-full" @click="isMobileMenuOpen = false">
            <button class="w-full text-center bg-primary text-white font-semibold py-2 rounded-md hover:bg-primary/95">
              Join Us
            </button>
          </router-link>
        </div>
        <div v-else class="px-5">
          <div class="flex items-center gap-3 mb-3">
            <div class="h-10 w-10 rounded-full bg-neutral-ivory flex items-center justify-center font-bold text-primary">
              {{ user?.name ? user.name[0].toUpperCase() : 'U' }}
            </div>
            <div>
              <div class="text-sm font-semibold text-neutral-black">{{ user?.name }}</div>
              <div class="text-xs text-neutral-muted">{{ user?.email }}</div>
            </div>
          </div>
          <div class="space-y-1">
            <router-link
              v-if="canAccessAcademy"
              to="/academy/dashboard"
              class="block px-3 py-2 rounded-md text-base font-medium text-neutral-black hover:bg-neutral-background"
              @click="isMobileMenuOpen = false"
            >
              Dawah Academy
            </router-link>
            <router-link
              v-if="user?.role === 'admin'"
              to="/admin/dashboard"
              class="block px-3 py-2 rounded-md text-base font-medium text-neutral-black hover:bg-neutral-background"
              @click="isMobileMenuOpen = false"
            >
              Admin Portal
            </router-link>
            <button
              @click="handleLogout(); isMobileMenuOpen = false"
              class="w-full text-left block px-3 py-2 rounded-md text-base font-medium text-secondary hover:bg-neutral-background cursor-pointer"
            >
              Sign Out
            </button>
          </div>
        </div>
      </div>
    </div>
  </nav>
</template>
