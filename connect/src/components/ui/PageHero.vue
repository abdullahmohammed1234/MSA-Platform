<template>
  <header class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-white via-[#fffbf4] to-[#f7f2e7] border border-[#ebe8de] shadow-xs p-5 sm:p-7 transition-all">
    <!-- Subtle Brand Glow Accent -->
    <div class="absolute -right-16 -top-16 w-56 h-56 rounded-full bg-gradient-to-br from-[#ffdc83]/30 via-[#640c0e]/10 to-transparent blur-2xl pointer-events-none"></div>
    <div class="absolute -left-12 -bottom-12 w-40 h-40 rounded-full bg-[#640c0e]/5 blur-xl pointer-events-none"></div>

    <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-5">
      <div class="space-y-2 min-w-0 max-w-3xl">
        <!-- Contextual Label & Badges -->
        <div v-if="label || $slots.label || $slots.badge" class="flex items-center gap-2.5 flex-wrap">
          <slot name="label">
            <span v-if="label" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-white border border-[#ebe8de] shadow-2xs text-[10.5px] font-bold uppercase tracking-wider text-[#640c0e]">
              <span class="w-1.5 h-1.5 rounded-full bg-[#b02e32]"></span>
              {{ label }}
            </span>
          </slot>
          <slot name="badge"></slot>
        </div>

        <!-- Prominent Title -->
        <h1 class="text-2xl sm:text-3xl lg:text-3xl font-extrabold text-[#640c0e] tracking-tight font-display leading-tight truncate">
          {{ title }}
        </h1>

        <!-- Concise Purpose Description -->
        <p v-if="description" class="text-xs sm:text-sm text-neutral-600 leading-relaxed font-normal">
          {{ description }}
        </p>
      </div>

      <!-- Action Buttons Slot -->
      <div v-if="$slots.actions" class="flex items-center gap-2.5 flex-wrap shrink-0">
        <slot name="actions"></slot>
      </div>
    </div>

    <!-- Optional Bottom Meta/Status Bar -->
    <div v-if="$slots.bottom" class="relative z-10 mt-5 pt-4 border-t border-[#ebe8de]/80">
      <slot name="bottom"></slot>
    </div>
  </header>
</template>

<script setup lang="ts">
interface Props {
  title: string;
  description?: string;
  label?: string;
}

defineProps<Props>();
</script>
