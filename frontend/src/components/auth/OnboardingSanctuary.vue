<script setup lang="ts">
import { ref, computed } from 'vue';
import { Sparkles, ArrowRight, ShieldCheck } from 'lucide-vue-next';
import { useAuthStore } from '@/stores/auth';

const emit = defineEmits<{
  (e: 'complete'): void;
}>();

const authStore = useAuthStore();
const step = ref(1);
const pledgeChecked = ref(false);
const goal = ref('Strengthen campus dawah skills');

const userName = computed(() => authStore.user?.name || 'Scholar');

const finish = () => {
  if (!pledgeChecked.value) return;
  emit('complete');
};
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[125] flex items-center justify-center bg-neutral-background/95 backdrop-blur-sm px-6">
      <div class="w-full max-w-2xl rounded-[2rem] border border-neutral-ivory bg-white p-8 shadow-premium-lg">
        <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.2em] text-primary">
          <Sparkles class="h-3.5 w-3.5" />
          Academy Onboarding
        </div>

        <h2 class="mt-4 text-3xl font-display font-bold text-primary">
          Welcome, {{ userName }}
        </h2>
        <p class="mt-2 text-sm text-neutral-muted">
          Set your intention before entering the Dawah Academy workspace.
        </p>

        <div v-if="step === 1" class="mt-8 space-y-5">
          <label class="flex items-start gap-3 rounded-2xl border border-neutral-ivory bg-neutral-background/60 p-4 cursor-pointer">
            <input v-model="pledgeChecked" type="checkbox" class="mt-1" />
            <span class="text-sm leading-relaxed text-neutral-black/80">
              I intend to seek knowledge sincerely, speak with wisdom, and represent the MSA with excellent character.
            </span>
          </label>

          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-full bg-primary px-5 py-2.5 text-xs font-bold uppercase tracking-wider text-white disabled:opacity-50"
            :disabled="!pledgeChecked"
            @click="step = 2"
          >
            Continue
            <ArrowRight class="h-4 w-4" />
          </button>
        </div>

        <div v-else class="mt-8 space-y-5">
          <label class="block space-y-2">
            <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Primary learning goal</span>
            <select v-model="goal" class="input-base">
              <option>Strengthen campus dawah skills</option>
              <option>Master theology foundations</option>
              <option>Improve dialogue and outreach confidence</option>
            </select>
          </label>

          <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 flex items-start gap-3">
            <ShieldCheck class="h-5 w-5 text-emerald-700 mt-0.5" />
            <p class="text-sm text-emerald-900">
              Your profile is ready. You can update goals anytime from Settings.
            </p>
          </div>

          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-full bg-primary px-5 py-2.5 text-xs font-bold uppercase tracking-wider text-white"
            @click="finish"
          >
            Enter Academy
            <ArrowRight class="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
