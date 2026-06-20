<template>
  <div class="min-h-screen flex items-center justify-center bg-neutral-background p-6">
    <div class="w-full max-w-md bg-white border border-neutral-ivory rounded-2xl shadow-premium p-8 relative overflow-hidden">
      <!-- Top aesthetic banner border -->
      <div class="absolute top-0 inset-x-0 h-1.5 bg-primary"></div>
      
      <div class="text-center mb-8">
        <router-link to="/" class="text-3xl font-display font-bold text-primary mb-2 inline-block">SFU MSA</router-link>
        <p class="text-[10px] tracking-[0.15em] font-bold text-neutral-muted uppercase">Platform Access</p>
      </div>

      <router-view />
    </div>
  </div>
</template>
