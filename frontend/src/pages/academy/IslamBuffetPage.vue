<script setup lang="ts">
import { ref } from 'vue';
import {
  BookOpenCheck,
  CheckCircle2,
  ClipboardList,
  MessageCircleQuestion,
  Soup,
  Users,
  Compass
} from 'lucide-vue-next';

const stations = [
  {
    title: "Who Is Allah?",
    icon: BookOpenCheck,
    detail: "A calm introduction to tawhid, mercy, worship, and the purpose of life.",
  },
  {
    title: "Meet the Prophet",
    icon: Users,
    detail: "Short stories from the seerah that show character, mercy, and service.",
  },
  {
    title: "Quran Table",
    icon: Soup,
    detail: "Translations, guided reading cards, and simple ways to begin exploring the Quran.",
  },
  {
    title: "Questions Corner",
    icon: MessageCircleQuestion,
    detail: "A low-pressure place for students to ask sincere questions and get thoughtful follow-up.",
  },
];

const checklist = ref([
  { text: "Confirm table booking, room access, and campus food requirements.", checked: true },
  { text: "Label food clearly for allergens and dietary notes.", checked: true },
  { text: "Pair newer volunteers with experienced mentors for each station.", checked: false },
  { text: "Prepare follow-up QR codes for Dawah Academy, MSA events, and chaplain support.", checked: false },
  { text: "Debrief after the event and record common questions for future training.", checked: false },
]);

const studentFlows = [
  { step: "1", title: "Welcome and food table", desc: "Greet guests, offer hot food or coffee, and set a welcoming, zero-pressure atmosphere." },
  { step: "2", title: "Choose any learning station", desc: "Guests browse whatever interests them: seerah, Quran, or foundational beliefs." },
  { step: "3", title: "Ask questions or take resources", desc: "Interact with our trained volunteers and browse translation prints to take home." },
  { step: "4", title: "Optional follow-up", desc: "Guests can scan QR codes to join Dawah Academy, attend MSA socials, or contact our chaplain." }
];

const toggleChecklist = (index: number) => {
  checklist.value[index].checked = !checklist.value[index].checked;
};
</script>

<template>
  <div class="space-y-8 max-w-7xl mx-auto font-sans">
    <!-- Header Hero Banner -->
    <div class="rounded-3xl border border-neutral-ivory bg-white p-6 md:p-8 shadow-premium">
      <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
        <div class="max-w-3xl space-y-4">
          <span class="inline-flex items-center gap-1.5 bg-primary/10 text-primary text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-widest font-mono border border-primary/20">
            <Compass class="h-3.5 w-3.5" /> Campus Hospitality Outreach
          </span>
          <h1 class="font-display text-3xl md:text-4xl font-extrabold text-primary tracking-tight leading-tight">
            Make Islam easy to approach, thoughtful to learn, and beautiful to remember.
          </h1>
          <p class="text-sm leading-relaxed text-neutral-muted">
            Islam Buffet is built around gentle curiosity: students can take food, visit learning stations,
            ask questions, and leave with a clear next step if they want to keep learning.
          </p>
        </div>
        <div class="flex h-24 w-24 shrink-0 items-center justify-center rounded-2xl border border-accent-gold/30 bg-accent-gold/10 text-primary shadow-soft">
          <Soup class="h-10 w-10 text-primary" />
        </div>
      </div>
    </div>

    <!-- Main Grid Content -->
    <div class="grid gap-8 lg:grid-cols-[1.25fr_0.75fr]">
      <!-- Left Column: Stations -->
      <section class="space-y-6">
        <div>
          <h2 class="text-lg font-display font-bold text-primary">Learning Stations</h2>
          <p class="text-xs text-neutral-muted mt-1">Four guided stations designed to showcase different facets of Islamic knowledge and character.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div
            v-for="station in stations"
            :key="station.title"
            class="rounded-2xl border border-neutral-ivory bg-white p-6 shadow-soft hover:shadow-premium hover:-translate-y-0.5 transition-all duration-300 flex flex-col justify-between"
          >
            <div class="space-y-4">
              <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <component :is="station.icon" class="h-5 w-5" />
              </div>
              <div class="space-y-1">
                <h3 class="font-display font-semibold text-neutral-black text-base">{{ station.title }}</h3>
                <p class="text-xs leading-relaxed text-neutral-muted font-light">{{ station.detail }}</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Right Column: Checklist & Student Flow -->
      <aside class="space-y-8">
        <!-- Event Checklist -->
        <div class="rounded-3xl border border-neutral-ivory bg-white p-6 shadow-premium space-y-5">
          <div class="flex items-center gap-2 border-b border-neutral-ivory/50 pb-3">
            <ClipboardList class="h-5 w-5 text-primary" />
            <h3 class="font-display font-bold text-primary text-base">Event Checklist</h3>
          </div>

          <div class="space-y-3">
            <div
              v-for="(item, idx) in checklist"
              :key="idx"
              @click="toggleChecklist(idx)"
              class="flex gap-3 cursor-pointer select-none group items-start"
            >
              <CheckCircle2
                class="mt-0.5 h-4 w-4 shrink-0 transition-colors"
                :class="item.checked ? 'text-green-650 fill-green-50' : 'text-neutral-350 group-hover:text-primary'"
              />
              <p
                class="text-xs leading-relaxed transition-all"
                :class="item.checked ? 'text-neutral-muted line-through font-light' : 'text-neutral-black font-medium'"
              >
                {{ item.text }}
              </p>
            </div>
          </div>
        </div>

        <!-- Student Flow -->
        <div class="rounded-3xl border border-neutral-ivory bg-white p-6 shadow-premium space-y-5">
          <h3 class="font-display font-bold text-primary text-base border-b border-neutral-ivory/50 pb-3">Student Flow & Journey</h3>
          
          <div class="space-y-4">
            <div v-for="flow in studentFlows" :key="flow.step" class="flex gap-4">
              <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent-gold/20 font-mono text-xs font-black text-primary border border-accent-gold/30">
                {{ flow.step }}
              </div>
              <div class="space-y-0.5">
                <h4 class="text-xs font-bold text-neutral-black">{{ flow.title }}</h4>
                <p class="text-[11px] leading-relaxed text-neutral-muted font-light">{{ flow.desc }}</p>
              </div>
            </div>
          </div>
        </div>
      </aside>
    </div>
  </div>
</template>
