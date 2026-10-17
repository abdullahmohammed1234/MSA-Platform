<template>
  <footer class="bg-white border-t border-[#ebe8de] py-10 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="flex items-center gap-3">
          <div class="w-7 h-7 rounded-lg bg-white border border-[#ebe8de] shadow-2xs flex items-center justify-center overflow-hidden shrink-0">
            <img
              src="/logo.webp"
              alt="SFU MSA"
              class="w-5 h-5 object-contain"
              onerror="this.style.display='none'"
            />
          </div>
          <span class="text-xs text-neutral-600 font-medium">
            Simon Fraser University Muslim Students Association (SFU MSA)
          </span>
        </div>

        <div class="flex items-center gap-6 text-xs text-neutral-500 font-medium">
          <router-link
            to="/app/help"
            class="hover:text-[#640c0e] transition-colors"
          >
            Help & Guide
          </router-link>
          <a
            href="https://sfumsa.ca"
            target="_blank"
            rel="noopener noreferrer"
            class="hover:text-[#640c0e] transition-colors"
          >
            Official Website
          </a>
          <a
            href="https://sfumsa.ca/events"
            target="_blank"
            rel="noopener noreferrer"
            class="hover:text-[#640c0e] transition-colors"
          >
            EMS Events
          </a>
          <a
            href="https://sfumsa.ca/contact"
            target="_blank"
            rel="noopener noreferrer"
            class="hover:text-[#640c0e] transition-colors"
          >
            Contact Central
          </a>
        </div>

        <div class="text-[11px] text-neutral-400">
          &copy; {{ currentYear }} SFU MSA. Connected through Central Identity.
        </div>
      </div>
    </div>
  </footer>
</template>

<script setup lang="ts">
const currentYear = new Date().getFullYear();
</script>
