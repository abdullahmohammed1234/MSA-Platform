<template>
  <svg
    viewBox="0 0 100 100"
    :class="className"
    fill="none"
    stroke="currentColor"
    stroke-width="1.2"
    aria-hidden="true"
  >
    <!-- Interlocking rotated squares forming 8-pointed Rub el Hizb -->
    <rect x="22" y="22" width="56" height="56" rx="2" />
    <rect x="22" y="22" width="56" height="56" rx="2" transform="rotate(45 50 50)" />
    <!-- Inner ring motifs -->
    <circle cx="50" cy="50" r="14" />
    <circle cx="50" cy="50" r="28" stroke-dasharray="3 3" opacity="0.6" />
    <!-- Center star polygon -->
    <polygon
      points="50,42 53,48 59,48 54,52 56,58 50,54 44,58 46,52 41,48 47,48"
      fill="currentColor"
      opacity="0.5"
    />
  </svg>
</template>

<script setup lang="ts">
withDefaults(
  defineProps<{
    className?: string;
  }>(),
  {
    className: 'w-10 h-10 text-[#640c0e]/30',
  },
);
</script>
