<script setup lang="ts">
import type { FooterProps } from './types';

const props = withDefaults(defineProps<FooterProps>(), {
  columns: () => [
    {
      title: 'Academy',
      links: [
        { label: 'Courses Catalog', path: '/academy/courses' },
        { label: 'Syllabus Path', path: '/academy/syllabus' },
        { label: 'My Dashboard', path: '/academy/dashboard' }
      ]
    },
    {
      title: 'MSA Connect',
      links: [
        { label: 'Events Calendar', path: '/events' },
        { label: 'Prayer Times', path: '/prayer' },
        { label: 'Volunteers SignUp', path: '/volunteer' }
      ]
    },
    {
      title: 'Resources',
      links: [
        { label: 'Books Library', path: '/library' },
        { label: 'Scholarships Info', path: '/scholarships' },
        { label: 'Contact Support', path: '/contact' }
      ]
    }
  ],
  socials: () => [
    { platform: 'Facebook', url: 'https://facebook.com', icon: 'fb' },
    { platform: 'Twitter', url: 'https://twitter.com', icon: 'tw' },
    { platform: 'Instagram', url: 'https://instagram.com', icon: 'ig' },
    { platform: 'LinkedIn', url: 'https://linkedin.com', icon: 'li' }
  ],
  copyright: 'SFU MSA & Dawah Academy. All rights reserved.'
});
</script>

<template>
  <footer class="bg-white border-t border-neutral-ivory py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      
      <!-- Top Section: Columns & Brand Info -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">
        <!-- Brand identity block -->
        <div class="space-y-4">
          <div class="flex items-center gap-2">
            <div class="h-9 w-9 rounded-full bg-primary flex items-center justify-center text-white font-display font-bold text-lg">
              M
            </div>
            <span class="font-display font-bold text-lg text-primary tracking-wide">SFU MSA</span>
          </div>
          <p class="text-sm text-neutral-muted leading-relaxed">
            Nurturing student success, religious scholarship, and active community outreach at Simon Fraser University.
          </p>
          <!-- Contact details -->
          <div class="text-xs text-neutral-muted space-y-1">
            <div>Email: <a href="mailto:info@sfumsa.ca" class="hover:underline text-primary">info@sfumsa.ca</a></div>
            <div>Address: Simon Fraser University, Burnaby, BC</div>
          </div>
        </div>

        <!-- Links Columns -->
        <div v-for="col in columns" :key="col.title" class="space-y-4">
          <h4 class="text-xs font-bold uppercase tracking-[0.15em] text-neutral-black">
            {{ col.title }}
          </h4>
          <ul class="space-y-2.5">
            <li v-for="link in col.links" :key="link.path">
              <router-link
                :to="link.path"
                class="text-sm text-neutral-muted hover:text-primary transition-colors duration-200"
              >
                {{ link.label }}
              </router-link>
            </li>
          </ul>
        </div>
      </div>

      <!-- Bottom Section: Divider, Socials & Copyright -->
      <div class="pt-8 border-t border-neutral-ivory/50 flex flex-col sm:flex-row items-center justify-between gap-4">
        <p class="text-xs text-neutral-muted text-center sm:text-left">
          &copy; {{ new Date().getFullYear() }} {{ copyright }}
        </p>

        <!-- Social Icons Row -->
        <div class="flex items-center gap-4">
          <a
            v-for="soc in socials"
            :key="soc.platform"
            :href="soc.url"
            target="_blank"
            rel="noopener noreferrer"
            class="text-neutral-muted hover:text-primary transition-colors"
            :aria-label="soc.platform"
          >
            <slot :name="`social-${soc.icon}`">
              <!-- Inline generic social SVG bubble -->
              <span class="text-xs font-bold hover:underline px-1.5 py-0.5 bg-neutral-background rounded">{{ soc.platform[0] }}</span>
            </slot>
          </a>
        </div>
      </div>
      
    </div>
  </footer>
</template>
