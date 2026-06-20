<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import {
  CheckCircle,
  ArrowRight,
  RotateCcw,
  Sparkles,
  Zap,
  Flame,
  HelpCircle,
  Smile,
  Frown,
  PenTool,
  History,
  ChevronRight,
  BookMarked
} from 'lucide-vue-next';
import { useBranchingEngine } from '@/composables/useBranchingEngine';
import { useToastStore } from '@/components/feedback/toast';
import { simulationsService, type SimulationSessionRecord } from '@/services/academy/simulationsService';
import Accordion from '@/components/navigation/accordion/Accordion.vue';
import Tabs from '@/components/navigation/tabs/Tabs.vue';
import EmptyState from '@/components/data-display/empty-state/EmptyState.vue';
import Breadcrumbs from '@/components/navigation/breadcrumbs/Breadcrumbs.vue';

const toast = useToastStore();
const {
  scenarios,
  scenariosLoading,
  scenariosError,
  activeScenario,
  currentNode,
  state,
  selectedOptionId,
  reflectionInput,
  showReflection,
  startScenario,
  resetEngine,
  selectOption,
  submitReflection,
  proceedNext,
  rewindOneStep
} = useBranchingEngine();

const activeTab = ref<'library' | 'analytics' | 'history' | 'journals' | 'achievements'>('library');
const tabOptions = [
  { id: 'library', label: 'Scenario Library' },
  { id: 'history', label: 'Session History' },
  { id: 'analytics', label: 'Analytics' },
];
const selectedCategory = ref('All');
const searchQuery = ref('');
const selectedHistorySession = ref<SimulationSessionRecord | null>(null);
const historyLoading = ref(true);
const historySummary = ref({ totalXp: 0, avgScore: 0, avgAtmos: 0 });

const completedSessions = ref<SimulationSessionRecord[]>([]);

const loadHistory = async () => {
  historyLoading.value = true;
  try {
    const { sessions, summary } = await simulationsService.getHistory();
    completedSessions.value = sessions;
    historySummary.value = {
      totalXp: summary.totalXp,
      avgScore: summary.avgScore,
      avgAtmos: summary.avgAtmosphere,
    };
  } catch (err: any) {
    toast.error(err.message || 'Failed to load session history.');
    completedSessions.value = [];
  } finally {
    historyLoading.value = false;
  }
};

const TONE_METRICS: Record<string, { label: string; color: string; bg: string; icon: any }> = {
  concerned: { label: 'Concerned', color: 'text-amber-700', bg: 'bg-amber-50 border-amber-200', icon: Frown },
  agitated: { label: 'Agitated ⚡', color: 'text-red-700', bg: 'bg-red-50 border-red-200', icon: Flame },
  skeptical: { label: 'Skeptical 🔍', color: 'text-indigo-700', bg: 'bg-indigo-50 border-indigo-200', icon: HelpCircle },
  curious: { label: 'Curious 💡', color: 'text-sky-700', bg: 'bg-sky-50 border-sky-200', icon: Sparkles },
  thoughtful: { label: 'Thoughtful 💭', color: 'text-teal-700', bg: 'bg-teal-50 border-teal-200', icon: Smile },
  hostile: { label: 'Hostile ⛔', color: 'text-rose-800', bg: 'bg-rose-100 border-rose-300', icon: Flame },
  friendly: { label: 'Friendly ♥', color: 'text-emerald-700', bg: 'bg-emerald-50 border-emerald-200', icon: Smile },
};

onMounted(loadHistory);

// Watch state completion to record session history
watch(
  () => state.value?.isCompleted,
  async (completed) => {
    if (completed && activeScenario.value && state.value) {
      const nowKey = new Date().toISOString().slice(0, 16);
      const alreadySaved = completedSessions.value.some(
        (s) => s.completedAt?.slice(0, 16) === nowKey && s.scenarioId === activeScenario.value?.id
      );

      if (!alreadySaved) {
        const journalReflections = state.value.history
          .map((h) => {
            const node = activeScenario.value?.nodes[h.nodeId];
            return {
              question: node?.reflectionPrompt?.question || 'Dialogue Reflection Case',
              answer: h.reflectionResponse || 'Self assessment submitted.',
              mentorSample: node?.reflectionPrompt?.mentorSampleAnswer || 'Empathy precedes dense proofs.',
            };
          })
          .filter((r) => r.answer !== 'Self assessment submitted.');

        const playTranscript = state.value.history.map((h) => {
          const node = activeScenario.value?.nodes[h.nodeId];
          const choice = node?.options.find((o) => o.id === h.selectedOptionId);
          return {
            nodeId: h.nodeId,
            inquiry: node?.characterText || 'Incoming query text.',
            selectedResponse: choice?.text || 'Answer selected.',
            score: h.scoreAwarded,
            mentorAdvice: choice?.mentorFeedback.scoreExplanation || 'Approved by mentor guild.',
          };
        });

        try {
          await simulationsService.saveSession({
            scenarioId: activeScenario.value.id,
            scenarioTitle: activeScenario.value.title,
            category: activeScenario.value.category,
            difficulty: activeScenario.value.difficulty,
            characterName: activeScenario.value.characterName,
            avatarSeed: activeScenario.value.avatarSeed,
            overallScore: state.value.overallScore,
            atmosphereScore: state.value.atmosphereScore,
            transcript: playTranscript,
            reflections: journalReflections,
            completedAt: new Date().toISOString(),
          });
          await loadHistory();
        } catch (err: any) {
          toast.error(err.message || 'Failed to save session.');
        }
      }
    }
  }
);

// Toggle Bookmark
const handleToggleBookmark = async (id: string, e: Event) => {
  e.stopPropagation();
  const session = completedSessions.value.find((s) => s.id === id);
  if (!session) return;

  const next = !session.isBookmarked;
  session.isBookmarked = next;

  try {
    await simulationsService.toggleBookmark(id, next);
    toast.success('Updated bookmark status');
  } catch {
    session.isBookmarked = !next;
    toast.error('Failed to update bookmark.');
  }
};

// Filter scenarios library
const filteredScenarios = computed(() => {
  return scenarios.value.filter((s) => {
    const matchesCategory = selectedCategory.value === 'All' || s.category === selectedCategory.value;
    const matchesSearch = s.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                          s.description.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                          s.characterName.toLowerCase().includes(searchQuery.value.toLowerCase());
    return matchesCategory && matchesSearch;
  });
});

// Categories list
const CATEGORIES = [
  'All',
  'Beginner Conversations',
  'Misconceptions',
  'Difficult Questions',
  'Emotional Conversations',
  'Interfaith Dialogue',
  'Booth Etiquette',
  'Advanced Dawah Scenarios'
];

// Stats computed
const analyticsStats = computed(() => {
  if (historySummary.value.totalXp > 0 || completedSessions.value.length === 0) {
    return {
      totalXP: historySummary.value.totalXp,
      avgScore: historySummary.value.avgScore,
      avgAtmos: historySummary.value.avgAtmos,
    };
  }
  const totalXP = completedSessions.value.reduce((acc, s) => acc + s.overallScore, 0);
  const avgScore = completedSessions.value.length ? Math.round(totalXP / completedSessions.value.length) : 0;
  const avgAtmos = completedSessions.value.length
    ? Math.round(completedSessions.value.reduce((acc, s) => acc + s.atmosphereScore, 0) / completedSessions.value.length)
    : 0;
  return { totalXP, avgScore, avgAtmos };
});

const activeOption = computed(() => {
  if (currentNode.value && selectedOptionId.value) {
    return currentNode.value.options.find((o) => o.id === selectedOptionId.value) || null;
  }
  return null;
});

const getAtmosphereInfo = (score: number) => {
  if (score >= 80) return { text: 'Prophetic Synergy (Warm)', color: 'bg-green-500', textClass: 'text-green-700' };
  if (score >= 60) return { text: 'Intellectual Trust (Healthy)', color: 'bg-teal-500', textClass: 'text-teal-700' };
  if (score >= 40) return { text: 'Neutral Stance', color: 'bg-amber-400', textClass: 'text-amber-700' };
  return { text: 'Conflict (Fragile)', color: 'bg-red-500', textClass: 'text-red-700' };
};
</script>

<template>
  <div class="space-y-6 max-w-7xl mx-auto px-1 sm:px-4 pb-12">
    <Breadcrumbs :items="[{ id: 'lab', label: 'Practice Lab' }]" />

    <!-- Header -->
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-neutral-ivory pb-5">
      <div>
        <div class="flex items-center gap-2">
          <h1 class="text-2xl font-display font-bold text-primary tracking-tight">
            Interactive Practice Lab
          </h1>
          <span class="flex items-center gap-1.5 py-0.5 px-2.5 rounded-full text-[9px] font-bold tracking-widest uppercase bg-accent-gold text-primary border border-accent-gold/20 animate-pulse">
            <Zap class="h-3 w-3 fill-current" /> High Fidelity Simulator
          </span>
        </div>
        <p class="text-xs text-neutral-muted mt-1 max-w-2xl leading-relaxed">
          Acquire and polish comparative dialogue techniques. Traverse deep, multi-branching conversational pathways, review expert mentor comments, formulate reflection logs, and review quantitative metrics.
        </p>
      </div>

      <Tabs
        v-if="!activeScenario"
        v-model="activeTab"
        variant="pill"
        :options="tabOptions"
        class="shrink-0"
      />

      <button
        v-else
        @click="resetEngine"
        class="px-4 py-2 text-xs font-bold rounded-xl border border-neutral-ivory hover:bg-neutral-background text-neutral-black transition-all flex items-center gap-2 cursor-pointer bg-white"
      >
        <RotateCcw class="h-3.5 w-3.5" /> Return to Library
      </button>
    </div>

    <!-- Active Scenario Simulator -->
    <div v-if="activeScenario" class="space-y-6">
      
      <!-- Completed Screen -->
      <div v-if="state?.isCompleted" class="max-w-3xl mx-auto bg-white rounded-3xl border border-neutral-ivory p-8 shadow-premium space-y-8 relative overflow-hidden text-center">
        <div class="w-16 h-16 bg-green-500/10 text-green-600 rounded-2xl flex items-center justify-center mx-auto ring-4 ring-green-500/15">
          <CheckCircle class="h-9 w-9 animate-bounce" />
        </div>

        <div class="space-y-1">
          <span class="font-mono text-[10px] uppercase font-bold tracking-widest text-primary">
            Dynamic Simulation Completed
          </span>
          <h2 class="text-2xl font-display font-bold text-primary">
            {{ activeScenario.title }}
          </h2>
          <p class="text-xs text-neutral-muted">
            Participant: {{ activeScenario.characterName }} • {{ activeScenario.characterRole }}
          </p>
        </div>

        <!-- Score metrics -->
        <div class="grid md:grid-cols-2 gap-4 max-w-lg mx-auto bg-neutral-background/30 p-5 rounded-2xl border border-neutral-ivory">
          <div class="text-center border-b md:border-b-0 md:border-r border-neutral-ivory pb-4 md:pb-0 md:pr-4 flex flex-col justify-center">
            <span class="text-[10px] font-bold uppercase text-neutral-muted tracking-wider">Accrued Experience</span>
            <span class="text-3xl font-mono font-bold text-primary mt-1">+{{ state.overallScore }} XP</span>
          </div>

          <div class="text-center flex flex-col justify-center pt-4 md:pt-0">
            <span class="text-[10px] font-bold uppercase text-neutral-muted tracking-wider">Dialogue Posture</span>
            <span class="font-display font-semibold text-sm text-accent-gold mt-1.5">
              ★ {{ state.overallScore >= 180 ? 'Mumtaz (Excellent)' : 'Qualified Ambassador' }}
            </span>
            <span class="text-[9px] text-neutral-muted block mt-1 uppercase">Vibe Index: {{ state.atmosphereScore }}%</span>
          </div>
        </div>

        <!-- Reflection Journal completed -->
        <div class="space-y-3 max-w-xl mx-auto border-t border-neutral-ivory/60 pt-5 text-left">
          <h4 class="font-display font-bold text-xs uppercase tracking-wider text-primary flex items-center gap-1.5 justify-center">
            <PenTool class="h-4 w-4" /> Completed Reflection Journal
          </h4>
          <div class="space-y-3.5 mt-2">
            <div v-for="(hist, idx) in state.history" :key="idx">
              <div v-if="hist.reflectionResponse" class="p-4 bg-accent-gold/5 border border-accent-gold/20 rounded-xl space-y-1.5 text-xs leading-relaxed">
                <span class="font-bold text-primary block">Reflection point {{ idx + 1 }}</span>
                <p class="text-neutral-black italic">"{{ hist.reflectionResponse }}"</p>
                <div class="pt-2 border-t border-accent-gold/10 mt-2 text-[10.5px] text-neutral-muted">
                  <span class="font-bold text-accent-gold">Reference answer:</span>
                  {{ activeScenario.nodes[hist.nodeId]?.reflectionPrompt?.mentorSampleAnswer }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-center gap-3 pt-3">
          <button
            @click="resetEngine"
            class="px-6 py-3 rounded-xl border border-neutral-ivory text-neutral-black font-bold hover:bg-neutral-background transition-all text-xs cursor-pointer bg-white"
          >
            Back to Library
          </button>
          <button
            @click="startScenario(activeScenario.id)"
            class="px-6 py-3 rounded-xl bg-primary hover:bg-secondary text-white font-bold transition-all text-xs cursor-pointer shadow-soft"
          >
            Replay Simulation
          </button>
        </div>
      </div>

      <!-- Active Ongoing simulation -->
      <div v-else class="space-y-6">
        <!-- Telemetry bar -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
          <div class="bg-white p-4 rounded-xl border border-neutral-ivory shadow-soft flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-primary/5 text-primary border border-neutral-ivory flex items-center justify-center font-bold text-xs">
              {{ activeScenario.characterName.slice(0, 2).toUpperCase() }}
            </div>
            <div>
              <span class="text-[9px] font-bold uppercase tracking-wider text-neutral-muted block">Converser</span>
              <span class="text-xs font-bold text-neutral-black">{{ activeScenario.characterName }}</span>
            </div>
          </div>

          <div v-if="currentNode" class="p-4 rounded-xl border shadow-soft flex items-center gap-3 transition-all duration-300" :class="TONE_METRICS[currentNode.characterTone]?.bg">
            <div class="w-10 h-10 rounded-xl bg-white/85 flex items-center justify-center font-bold shadow-soft">
              <component :is="TONE_METRICS[currentNode.characterTone]?.icon" class="h-5 w-5" />
            </div>
            <div>
              <span class="text-[9px] font-bold uppercase tracking-wider text-neutral-muted block">Tone State</span>
              <span class="text-xs font-bold uppercase tracking-wider" :class="TONE_METRICS[currentNode.characterTone]?.color">
                {{ TONE_METRICS[currentNode.characterTone]?.label }}
              </span>
            </div>
          </div>

          <div class="bg-white p-4 rounded-xl border border-neutral-ivory shadow-soft space-y-1">
            <div class="flex items-center justify-between">
              <span class="text-[9px] font-bold uppercase tracking-wider text-neutral-muted block">Vibe Index</span>
              <span class="text-[9.5px] font-mono font-bold" :class="getAtmosphereInfo(state!.atmosphereScore).textClass">
                {{ state!.atmosphereScore }}%
              </span>
            </div>
            <div class="h-2 bg-neutral-background rounded-full overflow-hidden border border-neutral-ivory/50">
              <div
                class="h-full transition-all duration-500"
                :class="getAtmosphereInfo(state!.atmosphereScore).color"
                :style="{ width: `${state!.atmosphereScore}%` }"
              ></div>
            </div>
          </div>

          <div class="bg-primary text-white p-4 rounded-xl shadow-soft flex items-center justify-between border border-primary">
            <div>
              <span class="text-[9px] font-bold uppercase tracking-wider text-white/70 block">Experience</span>
              <span class="text-lg font-mono font-bold text-accent-gold">{{ state!.overallScore }} XP</span>
            </div>
            <div class="text-right">
              <span class="text-[8.5px] font-bold uppercase text-white/70 block">Turn</span>
              <span class="text-xs font-bold">{{ state!.history.length + 1 }}</span>
            </div>
          </div>
        </div>

        <!-- Ongoing question node & choice options -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
          <div class="lg:col-span-8 space-y-6">
            <div v-if="currentNode" class="bg-white rounded-2xl border border-neutral-ivory p-6 shadow-soft relative overflow-hidden space-y-5">
              <div class="flex items-center justify-between gap-3 border-b border-neutral-ivory/70 pb-3">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-xl bg-primary/5 font-bold text-primary flex items-center justify-center text-xs border border-neutral-ivory">
                    {{ activeScenario.characterName.substring(0, 2).toUpperCase() }}
                  </div>
                  <div>
                    <h4 class="font-display font-semibold text-xs text-primary">
                      {{ activeScenario.characterName }}
                    </h4>
                    <p class="text-[9.5px] font-mono text-neutral-muted uppercase">Active dialogue partner</p>
                  </div>
                </div>
              </div>

              <!-- Statement bubble -->
              <div class="p-4 bg-neutral-background rounded-xl border border-neutral-ivory/50 text-sm italic text-neutral-black relative pt-5 text-left">
                <span class="absolute -top-2.5 left-5 px-2 py-0.5 font-bold uppercase text-[8px] bg-primary text-white rounded leading-none tracking-widest font-mono">
                  Inquiry Statement
                </span>
                "{{ currentNode.characterText }}"
              </div>

              <!-- Socratic advice directive -->
              <div class="text-[11px] p-3 bg-accent-gold/10 border border-accent-gold/30 text-neutral-black rounded-xl flex items-start gap-2.5 leading-relaxed text-left">
                <HelpCircle class="h-4 w-4 text-accent-gold shrink-0 mt-0.5" />
                <div>
                  <span class="font-bold text-primary block mb-0.5">Socratic Tip:</span>
                  {{ currentNode.assistantInstruction }}
                </div>
              </div>
            </div>

            <!-- Choice Options or Reflection panel -->
            <div class="space-y-3">
              <template v-if="showReflection && currentNode?.reflectionPrompt">
                <div class="bg-accent-gold/5 rounded-2xl border border-accent-gold/30 p-6 shadow-soft space-y-4 text-left">
                  <div class="flex items-center gap-2 border-b border-accent-gold/20 pb-3">
                    <PenTool class="h-4.5 w-4.5 text-primary" />
                    <h4 class="font-display font-bold text-xs text-primary uppercase tracking-wider">
                      Post-Response Sincere Reflection
                    </h4>
                  </div>
                  <p class="text-xs text-neutral-black">
                    {{ currentNode.reflectionPrompt.question }}
                  </p>
                  <textarea
                    v-model="reflectionInput"
                    :placeholder="currentNode.reflectionPrompt.placeholder"
                    rows="3"
                    class="w-full text-xs p-3 border border-neutral-ivory rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 bg-white"
                  ></textarea>
                  <button
                    @click="submitReflection"
                    :disabled="!reflectionInput.trim()"
                    class="px-5 py-2.5 bg-primary text-white rounded-xl text-xs font-bold hover:bg-secondary cursor-pointer disabled:opacity-50"
                  >
                    Save Reflection
                  </button>
                </div>
              </template>

              <!-- Option buttons -->
              <template v-else-if="currentNode">
                <div class="text-xs font-bold text-neutral-muted uppercase tracking-widest pl-2 mb-2 font-mono text-left">Select your dialogue response:</div>
                <div class="space-y-3">
                  <button
                    v-for="opt in currentNode.options"
                    :key="opt.id"
                    @click="selectOption(opt)"
                    :disabled="selectedOptionId !== null"
                    class="w-full text-left p-4 rounded-xl border bg-white transition-all text-xs flex justify-between items-center group cursor-pointer"
                    :class="selectedOptionId === opt.id ? 'border-primary bg-primary/5 shadow-soft' : 'border-neutral-ivory hover:border-primary hover:shadow-soft'"
                  >
                    <span class="flex-1 pr-4 leading-relaxed text-neutral-black">{{ opt.text }}</span>
                    <ChevronRight class="h-4 w-4 text-neutral-400 group-hover:text-primary shrink-0 transition-colors" />
                  </button>
                </div>
              </template>

              <!-- Feedback / Proceed controls -->
              <div v-if="selectedOptionId && activeOption && !showReflection" class="bg-neutral-background/40 p-5 rounded-2xl border border-neutral-ivory space-y-4 text-left">
                <div class="border-b border-neutral-ivory pb-2 flex items-center gap-2">
                  <CheckCircle class="h-4 w-4 text-green-600" />
                  <span class="text-[10px] font-bold text-primary uppercase tracking-wider font-mono">Mentor Evaluation</span>
                </div>
                <div class="text-xs leading-relaxed text-neutral-700">
                  <span class="font-bold text-primary block mb-1">Feedback:</span>
                  "{{ activeOption.mentorFeedback.scoreExplanation }}"
                </div>
                <div class="text-xs leading-relaxed text-neutral-700">
                  <span class="font-bold text-accent-gold block mb-1">Encouragement:</span>
                  "{{ activeOption.mentorFeedback.encouragement }}"
                </div>

                <div class="flex justify-between items-center pt-2">
                  <button
                    @click="rewindOneStep"
                    class="text-xs font-bold text-neutral-muted hover:text-primary transition-colors cursor-pointer flex items-center gap-1.5"
                  >
                    <RotateCcw class="h-3.5 w-3.5" /> Rewind Step
                  </button>
                  <button
                    @click="proceedNext"
                    class="px-5 py-2.5 bg-primary hover:bg-secondary text-white rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer shadow-soft"
                  >
                    Proceed <ArrowRight class="h-3.5 w-3.5" />
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Suggestions sidebar (Right column) -->
          <div class="lg:col-span-4 space-y-6">
            <div v-if="activeOption" class="bg-[#fffdfa] border border-accent-gold/40 rounded-2xl p-5 shadow-soft space-y-4 text-left">
              <div class="flex items-center gap-1.5 border-b border-accent-gold/20 pb-2">
                <Sparkles class="h-4 w-4 text-accent-gold" />
                <span class="text-[10px] font-bold text-primary uppercase tracking-wider font-mono">AI Suggestion Engine</span>
              </div>
              <div class="text-xs leading-relaxed text-[#856404] bg-[#fffbf2] p-3 rounded-xl border border-accent-gold/25 font-light">
                <span class="font-bold block mb-1">Suggested wording:</span>
                "{{ activeOption.aiSuggestions.suggestedResponse }}"
              </div>
              <div class="text-[11px] leading-relaxed text-neutral-muted space-y-1">
                <span class="font-bold text-primary block">Why this is effective:</span>
                <p>{{ activeOption.aiSuggestions.whyItWorks }}</p>
              </div>
            </div>

            <!-- Default info box when no option is selected -->
            <div v-else class="bg-zinc-50 border border-neutral-ivory rounded-2xl p-5 shadow-soft space-y-3 text-left">
              <h4 class="text-xs font-bold text-primary font-mono uppercase tracking-wider">Practice Parameters</h4>
              <p class="text-[11px] leading-relaxed text-neutral-muted">
                Each choice alters the vibe level of the dialogist. Sincerity and validating arguments disarms Skepticism, whereas raw text lectures increase defenses.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- LIBRARY TAB -->
    <div v-else-if="activeTab === 'library'" class="space-y-6">
      <EmptyState
        v-if="scenariosLoading"
        title="Loading scenarios"
        description="Fetching practice scenarios from the server…"
      />
      <EmptyState
        v-else-if="scenariosError"
        title="Unable to load scenarios"
        :description="scenariosError"
      />
      <EmptyState
        v-else-if="filteredScenarios.length === 0"
        title="No scenarios available"
        description="Published practice scenarios will appear here."
      />
      <template v-else>
      <div class="flex flex-col sm:flex-row gap-4 justify-between items-center bg-neutral-background/30 p-4 rounded-xl border border-neutral-ivory">
        <div class="flex flex-wrap gap-1.5 justify-center">
          <button
            v-for="cat in CATEGORIES"
            :key="cat"
            @click="selectedCategory = cat"
            class="px-3.5 py-1.5 rounded-lg text-xs font-semibold border transition-all cursor-pointer"
            :class="selectedCategory === cat ? 'bg-primary text-white border-primary shadow-soft' : 'bg-white text-neutral-muted border-neutral-ivory hover:text-primary'"
          >
            {{ cat }}
          </button>
        </div>
      </div>

      <!-- Scenarios Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="scen in filteredScenarios"
          :key="scen.id"
          class="bg-white border border-neutral-ivory rounded-2xl p-6 shadow-soft hover:shadow-premium-md hover:border-primary/25 transition-all duration-300 flex flex-col justify-between text-left"
        >
          <div>
            <div class="flex justify-between items-center mb-3">
              <span class="px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider rounded-md bg-neutral-ivory text-primary border border-neutral-ivory/50">
                {{ scen.difficulty }}
              </span>
              <span class="text-[10px] text-neutral-muted font-mono">{{ scen.category }}</span>
            </div>
            <h3 class="text-base font-bold text-neutral-black tracking-tight mb-2 group-hover:text-primary">
              {{ scen.title }}
            </h3>
            <p class="text-xs text-neutral-muted leading-relaxed font-light mb-4">
              {{ scen.description }}
            </p>
          </div>

          <div class="pt-4 border-t border-dashed border-neutral-ivory flex items-center justify-between">
            <span class="text-xs font-semibold text-neutral-black">Target: {{ scen.characterName }}</span>
            <button
              @click="startScenario(scen.id)"
              class="px-4 py-2 bg-primary hover:bg-secondary text-white rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer shadow-soft"
            >
              Start Lab <ArrowRight class="h-3.5 w-3.5" />
            </button>
          </div>
        </div>
      </div>
      </template>
    </div>

    <!-- SESSION HISTORY TAB -->
    <div v-else-if="activeTab === 'history'" class="space-y-6">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 text-left">
        <!-- List -->
        <div class="lg:col-span-1 space-y-3">
          <h3 class="text-xs font-bold uppercase tracking-widest text-neutral-muted pl-1 mb-2 font-mono">Attempt Records</h3>
          <div
            v-for="sess in completedSessions"
            :key="sess.id"
            @click="selectedHistorySession = sess"
            class="p-4 bg-white border border-neutral-ivory rounded-2xl shadow-soft hover:border-primary/20 cursor-pointer transition-all"
            :class="selectedHistorySession?.id === sess.id ? 'border-primary bg-primary/[0.02]' : ''"
          >
            <div class="flex justify-between items-start mb-2">
              <h4 class="text-xs font-bold text-neutral-black truncate flex-1 pr-2">{{ sess.scenarioTitle }}</h4>
              <button
                @click="handleToggleBookmark(sess.id, $event)"
                class="text-neutral-300 hover:text-accent-gold cursor-pointer"
                :class="sess.isBookmarked ? 'text-accent-gold' : ''"
              >
                <BookMarked class="h-4 w-4 fill-current" />
              </button>
            </div>
            <p class="text-[10px] text-neutral-muted mb-3 font-light">With {{ sess.characterName }} • Completed {{ sess.completedAt ? new Date(sess.completedAt).toLocaleDateString() : '—' }}</p>
            <div class="flex justify-between items-center">
              <span class="text-xs font-mono font-bold text-primary">+{{ sess.overallScore }} XP</span>
              <span class="text-[10px] text-green-700 font-bold bg-green-50 px-2 py-0.5 rounded border border-green-150">{{ sess.atmosphereScore }}% Vibe</span>
            </div>
          </div>

          <div v-if="completedSessions.length === 0" class="text-center py-12 text-xs text-neutral-muted italic">
            No history attempts found. Complete your first practice scenario!
          </div>
        </div>

        <!-- Details -->
        <div class="lg:col-span-2 space-y-4">
          <div v-if="selectedHistorySession" class="bg-white border border-neutral-ivory rounded-2xl p-6 shadow-soft space-y-5">
            <div class="border-b border-neutral-ivory pb-3 flex justify-between items-center flex-wrap gap-2">
              <div>
                <h3 class="text-base font-bold text-primary">{{ selectedHistorySession.scenarioTitle }}</h3>
                <p class="text-[10px] text-neutral-muted mt-0.5 font-light">
                  Difficulty: {{ selectedHistorySession.difficulty }} • Vibe score: {{ selectedHistorySession.atmosphereScore }}%
                </p>
              </div>
              <button
                @click="startScenario(selectedHistorySession.scenarioId)"
                class="px-3.5 py-1.5 bg-primary hover:bg-secondary text-white rounded-lg text-xs font-bold cursor-pointer"
              >
                Retry Scenario
              </button>
            </div>

            <!-- Dialogue script timeline -->
            <div class="space-y-3">
              <Accordion
                v-for="(step, idx) in selectedHistorySession.transcript"
                :key="`${selectedHistorySession.id}-${idx}`"
                :title="`Turn ${idx + 1}: ${step.inquiry.slice(0, 60)}${step.inquiry.length > 60 ? '…' : ''}`"
              >
                <div class="space-y-3 pt-2">
                  <div class="p-3 bg-neutral-background rounded-xl border border-neutral-ivory/50 text-xs italic text-neutral-black">
                    <strong>{{ selectedHistorySession.characterName }}:</strong> "{{ step.inquiry }}"
                  </div>
                  <div class="p-3 bg-primary/5 rounded-xl border border-primary/15 text-xs text-neutral-black">
                    <strong>Your choice (+{{ step.score }} XP):</strong> {{ step.selectedResponse }}
                  </div>
                  <div class="text-[10.5px] text-neutral-muted leading-relaxed">
                    <span class="font-bold text-accent-gold">Mentor Evaluation:</span>
                    "{{ step.mentorAdvice }}"
                  </div>
                </div>
              </Accordion>
            </div>
          </div>

          <div v-else class="h-64 border border-dashed border-neutral-ivory rounded-2xl flex flex-col items-center justify-center text-center p-6 text-neutral-muted bg-white">
            <History class="h-8 w-8 text-neutral-300 mb-2" />
            <h4 class="text-xs font-bold">No Attempt Selected</h4>
            <p class="text-[11px] leading-relaxed max-w-[200px] mt-1 font-light">Select a record from the list to review the full dialogue script and mentor evaluations.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- ANALYTICS TAB (Custom SVG charts) -->
    <div v-else-if="activeTab === 'analytics'" class="space-y-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-left">
        <!-- Overview Card -->
        <div class="bg-white border border-neutral-ivory rounded-2xl p-6 shadow-soft space-y-4">
          <h3 class="text-xs font-bold uppercase tracking-widest text-neutral-muted font-mono border-b border-neutral-ivory pb-2">Performance Summary</h3>
          <div class="space-y-4">
            <div>
              <span class="text-[10px] text-neutral-muted font-mono uppercase">Total Accumulated XP</span>
              <p class="text-2xl font-bold text-primary mt-0.5">{{ analyticsStats.totalXP }} XP</p>
            </div>
            <div>
              <span class="text-[10px] text-neutral-muted font-mono uppercase">Average Score</span>
              <p class="text-2xl font-bold text-primary mt-0.5">{{ analyticsStats.avgScore }} XP</p>
            </div>
            <div>
              <span class="text-[10px] text-neutral-muted font-mono uppercase">Average Dialogue Vibe</span>
              <p class="text-2xl font-bold text-green-700 mt-0.5">{{ analyticsStats.avgAtmos }}%</p>
            </div>
          </div>
        </div>

        <!-- Progress Chart (SVG bar chart representation) -->
        <div class="bg-white border border-neutral-ivory rounded-2xl p-6 shadow-soft space-y-4 md:col-span-2">
          <h3 class="text-xs font-bold uppercase tracking-widest text-neutral-muted font-mono border-b border-neutral-ivory pb-2">Attempt Score Progression</h3>
          
          <div v-if="completedSessions.length > 0" class="h-48 flex items-end justify-around pt-6 font-mono text-[9px] text-neutral-muted">
            <div
              v-for="(sess, idx) in completedSessions.slice().reverse()"
              :key="sess.id"
              class="flex flex-col items-center gap-2 group relative w-12"
            >
              <!-- Tooltip on hover -->
              <span class="opacity-0 group-hover:opacity-100 absolute -top-8 bg-primary text-white text-[9px] px-2 py-0.5 rounded transition-opacity duration-200 pointer-events-none shadow-soft">
                {{ sess.overallScore }} XP
              </span>
              <!-- Bar -->
              <div
                class="w-8 bg-primary rounded-t-lg transition-all duration-500 hover:bg-secondary cursor-pointer"
                :style="{ height: `${(sess.overallScore / 300) * 100}px` }"
              ></div>
              <span>S-{{ idx + 1 }}</span>
            </div>
          </div>

          <div v-else class="h-48 flex items-center justify-center text-xs text-neutral-muted italic">
            Complete some practice attempts to unlock data visualization metrics.
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(10px);
}
</style>
