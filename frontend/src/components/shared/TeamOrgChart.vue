<script setup lang="ts">
import { computed } from 'vue';
import type { TeamMember } from '@/services/website/websiteService';
import { TEAM_FALLBACK_IMAGE } from '@/constants/publicAssets';
import {
  EXEC_ROLES,
  EXEC_DEPTS,
  independentCoordinators,
  membersMatching,
  vicePresidentMembers,
  buildOrgBranches,
} from '@/data/teamOrg';

const props = defineProps<{
  members: TeamMember[];
}>();

const emit = defineEmits<{
  select: [member: TeamMember];
}>();

const president = computed(() =>
  membersMatching(props.members, EXEC_ROLES.president, EXEC_DEPTS.president)
);
const vicePresidents = computed(() => vicePresidentMembers(props.members));
const secretary = computed(() =>
  membersMatching(props.members, EXEC_ROLES.secretary, EXEC_DEPTS.secretary)
);
const execRow = computed(() => [...vicePresidents.value, ...secretary.value]);
const independents = computed(() => independentCoordinators(props.members));
const branches = computed(() => buildOrgBranches(props.members));

const hasChart = computed(
  () =>
    president.value.length > 0
    || execRow.value.length > 0
    || branches.value.length > 0
    || independents.value.length > 0
);
</script>

<template>
  <section v-if="hasChart" class="container-custom py-20 sm:py-28">
    <div class="max-w-3xl mx-auto text-center mb-14 sm:mb-16">
      <p class="text-[10px] font-black uppercase tracking-[0.35em] text-secondary mb-4">
        2026–2027 Council
      </p>
      <h2 class="text-3xl sm:text-5xl font-display font-semibold text-primary tracking-tight">
        Organizational Structure
      </h2>
      <p class="mt-4 text-sm sm:text-base text-neutral-black/50 font-light leading-relaxed">
        The President and Vice Presidents set direction. Leads run each portfolio, with Coordinators supporting the work.
      </p>
    </div>

    <div class="flex flex-col items-center">
      <!-- President -->
      <div v-if="president.length" class="flex flex-wrap justify-center gap-4">
        <button
          v-for="member in president"
          :key="member.name"
          type="button"
          class="group w-44 sm:w-52 text-center cursor-zoom-in"
          @click="emit('select', member)"
        >
          <div class="relative mx-auto mb-4 h-28 w-28 sm:h-32 sm:w-32 rounded-full overflow-hidden ring-4 ring-accent-gold/70 shadow-premium bg-primary/5">
            <img
              :src="member.img"
              :alt="member.name"
              class="h-full w-full object-cover grayscale group-hover:grayscale-0 transition-all duration-700"
              @error="($event.target as HTMLImageElement).src = TEAM_FALLBACK_IMAGE"
            />
          </div>
          <p class="text-sm sm:text-base font-display font-semibold text-primary leading-tight">{{ member.name }}</p>
          <p class="mt-1 text-[9px] font-black uppercase tracking-[0.22em] text-accent-gold">{{ member.role }}</p>
        </button>
      </div>

      <div v-if="president.length && execRow.length" class="h-10 w-px bg-primary/20" />
      <div
        v-if="president.length && execRow.length"
        class="h-px bg-primary/20"
        :style="{ width: `min(100%, ${Math.max(execRow.length - 1, 1) * 11}rem)` }"
      />

      <!-- VPs + Secretary -->
      <div v-if="execRow.length" class="flex flex-wrap justify-center gap-8 sm:gap-12 pt-0">
        <div v-for="member in execRow" :key="member.name" class="flex flex-col items-center">
          <div class="h-10 w-px bg-primary/20" />
          <button
            type="button"
            class="group w-36 sm:w-40 text-center cursor-zoom-in"
            @click="emit('select', member)"
          >
            <div class="relative mx-auto mb-3 h-20 w-20 sm:h-24 sm:w-24 rounded-full overflow-hidden ring-2 ring-primary/25 group-hover:ring-secondary/60 shadow-soft bg-primary/5 transition-all">
              <img
                :src="member.img"
                :alt="member.name"
                class="h-full w-full object-cover grayscale group-hover:grayscale-0 transition-all duration-700"
                @error="($event.target as HTMLImageElement).src = TEAM_FALLBACK_IMAGE"
              />
            </div>
            <p class="text-xs sm:text-sm font-display font-semibold text-primary leading-tight">{{ member.name }}</p>
            <p class="mt-1 text-[8px] font-black uppercase tracking-[0.18em] text-neutral-black/40">{{ member.role }}</p>
          </button>
        </div>
      </div>

      <!-- Departments -->
      <div v-if="branches.length" class="mt-16 sm:mt-20 w-full">
        <p class="text-center text-[10px] font-black uppercase tracking-[0.35em] text-neutral-black/30 mb-10">
          Leads &amp; Teams
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-8 xl:gap-4">
          <article
            v-for="branch in branches"
            :key="branch.id"
            class="rounded-[2rem] border border-neutral-gray/15 bg-white/70 p-5 text-center shadow-soft"
          >
            <p class="text-[9px] font-black uppercase tracking-[0.28em] text-secondary mb-5">{{ branch.label }}</p>

            <div
              :class="branch.leads.length > 1
                ? 'grid grid-cols-2 gap-2 items-start'
                : 'flex justify-center'"
            >
              <button
                v-for="lead in branch.leads"
                :key="lead.name"
                type="button"
                class="group min-w-0 cursor-zoom-in"
                :class="branch.leads.length > 1 ? 'w-full' : 'w-[8.5rem]'"
                @click="emit('select', lead)"
              >
                <div
                  class="relative mx-auto mb-2 rounded-full overflow-hidden ring-2 ring-primary/20 group-hover:ring-secondary/50 bg-primary/5 transition-all"
                  :class="branch.leads.length > 1 ? 'h-14 w-14' : 'h-[4.5rem] w-[4.5rem]'"
                >
                  <img
                    :src="lead.img"
                    :alt="lead.name"
                    class="h-full w-full object-cover grayscale group-hover:grayscale-0 transition-all duration-700"
                    @error="($event.target as HTMLImageElement).src = TEAM_FALLBACK_IMAGE"
                  />
                </div>
                <p class="text-[11px] font-display font-semibold text-primary leading-tight">{{ lead.name }}</p>
                <p class="mt-1 text-[7px] font-black uppercase tracking-[0.14em] text-neutral-black/40 leading-tight">{{ lead.role }}</p>
              </button>
            </div>

            <div v-if="branch.coordinators.length" class="mx-auto my-4 h-6 w-px bg-primary/15" />

            <div class="space-y-4">
              <button
                v-for="coordinator in branch.coordinators"
                :key="coordinator.name"
                type="button"
                class="group mx-auto flex w-full max-w-[12rem] items-center gap-3 rounded-2xl px-2 py-1.5 text-left hover:bg-primary/5 cursor-zoom-in transition-colors"
                @click="emit('select', coordinator)"
              >
                <div class="h-10 w-10 shrink-0 rounded-full overflow-hidden ring-1 ring-neutral-gray/20 bg-primary/5">
                  <img
                    :src="coordinator.img"
                    :alt="coordinator.name"
                    class="h-full w-full object-cover grayscale group-hover:grayscale-0 transition-all duration-500"
                    @error="($event.target as HTMLImageElement).src = TEAM_FALLBACK_IMAGE"
                  />
                </div>
                <div class="min-w-0">
                  <p class="text-[11px] font-display font-semibold text-primary leading-tight truncate">{{ coordinator.name }}</p>
                  <p class="text-[8px] font-black uppercase tracking-[0.14em] text-neutral-black/35 truncate">{{ coordinator.role }}</p>
                </div>
              </button>
            </div>
          </article>
        </div>
      </div>

      <!-- Independent coordinators -->
      <div v-if="independents.length" class="mt-16 sm:mt-20 w-full">
        <p class="text-center text-[10px] font-black uppercase tracking-[0.35em] text-neutral-black/30 mb-8">
          Coordinators
        </p>
        <p class="text-center text-xs text-neutral-black/40 font-light mb-8 max-w-md mx-auto">
          These coordinators work independently, without a department lead.
        </p>
        <div class="flex flex-wrap justify-center gap-8 sm:gap-10">
          <button
            v-for="member in independents"
            :key="member.name"
            type="button"
            class="group w-36 text-center cursor-zoom-in"
            @click="emit('select', member)"
          >
            <div class="relative mx-auto mb-3 h-20 w-20 rounded-full overflow-hidden ring-2 ring-neutral-gray/20 group-hover:ring-secondary/50 shadow-soft bg-primary/5 transition-all">
              <img
                :src="member.img"
                :alt="member.name"
                class="h-full w-full object-cover grayscale group-hover:grayscale-0 transition-all duration-700"
                @error="($event.target as HTMLImageElement).src = TEAM_FALLBACK_IMAGE"
              />
            </div>
            <p class="text-xs font-display font-semibold text-primary leading-tight">{{ member.name }}</p>
            <p class="mt-1 text-[8px] font-black uppercase tracking-[0.16em] text-neutral-black/40">{{ member.role }}</p>
          </button>
        </div>
      </div>
    </div>
  </section>
</template>
