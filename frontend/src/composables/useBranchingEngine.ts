import { ref, computed, onMounted } from 'vue';
import type { PracticeScenario, ConversationState, ConversationOption } from '@/services/academy/scenariosTypes';
import { simulationsService } from '@/services/academy/simulationsService';
import { useToastStore } from '@/components/feedback/toast';
import { useGamificationStore } from '@/stores/gamification';

const INITIAL_ATMOSPHERE = 60;

export function useBranchingEngine() {
  const toast = useToastStore();
  const gamification = useGamificationStore();
  const scenarios = ref<PracticeScenario[]>([]);
  const scenariosLoading = ref(true);
  const scenariosError = ref<string | null>(null);
  const activeScenarioId = ref<string | null>(null);
  const currentNodeId = ref<string | null>(null);
  const state = ref<ConversationState | null>(null);
  const selectedOptionId = ref<string | null>(null);
  const reflectionInput = ref<string>('');
  const showReflection = ref(false);
  const isLoadingFeedback = ref(false);

  onMounted(async () => {
    scenariosLoading.value = true;
    scenariosError.value = null;
    try {
      scenarios.value = await simulationsService.getScenarios();
    } catch (err: any) {
      scenariosError.value = err.message || 'Failed to load practice scenarios.';
      scenarios.value = [];
    } finally {
      scenariosLoading.value = false;
    }
  });

  const activeScenario = computed(() =>
    scenarios.value.find((s) => s.id === activeScenarioId.value)
  );
  
  const currentNode = computed(() => {
    if (activeScenario.value && currentNodeId.value) {
      return activeScenario.value.nodes[currentNodeId.value];
    }
    return null;
  });

  const startScenario = (scenarioId: string) => {
    const scen = scenarios.value.find((s) => s.id === scenarioId);
    if (!scen) return;

    activeScenarioId.value = scenarioId;
    currentNodeId.value = scen.initialNodeId;
    selectedOptionId.value = null;
    reflectionInput.value = '';
    showReflection.value = false;
    isLoadingFeedback.value = false;

    state.value = {
      currentScenarioId: scenarioId,
      currentNodeId: scen.initialNodeId,
      visitedNodes: [scen.initialNodeId],
      history: [],
      atmosphereScore: INITIAL_ATMOSPHERE,
      overallScore: 0,
      isCompleted: false,
    };
  };

  const resetEngine = () => {
    activeScenarioId.value = null;
    currentNodeId.value = null;
    selectedOptionId.value = null;
    reflectionInput.value = '';
    showReflection.value = false;
    isLoadingFeedback.value = false;
    state.value = null;
  };

  const selectOption = (option: ConversationOption) => {
    if (!state.value || !currentNodeId.value || selectedOptionId.value) return;

    isLoadingFeedback.value = true;
    selectedOptionId.value = option.id;

    setTimeout(() => {
      isLoadingFeedback.value = false;

      let atmosphereAdjustment = 0;
      switch (option.emotionalFeedback.characterToneImpact) {
        case 'calm': atmosphereAdjustment = 15; break;
        case 'receptive': atmosphereAdjustment = 20; break;
        case 'curious': atmosphereAdjustment = 10; break;
        case 'defensive': atmosphereAdjustment = -15; break;
        case 'agitated': atmosphereAdjustment = -20; break;
        case 'hostile': atmosphereAdjustment = -25; break;
        case 'skeptical': atmosphereAdjustment = -5; break;
        case 'thoughtful': atmosphereAdjustment = 12; break;
      }

      if (state.value) {
        state.value.atmosphereScore = Math.min(100, Math.max(0, state.value.atmosphereScore + atmosphereAdjustment));
        state.value.overallScore += option.score;
        state.value.history.push({
          nodeId: currentNodeId.value!,
          selectedOptionId: option.id,
          scoreAwarded: option.score,
        });
      }

      if (currentNode.value?.reflectionPrompt) {
        showReflection.value = true;
      }
    }, 450);
  };

  const submitReflection = () => {
    if (!state.value || !currentNodeId.value || !selectedOptionId.value) return;

    if (state.value && state.value.history.length > 0) {
      state.value.history[state.value.history.length - 1].reflectionResponse = reflectionInput.value;
    }

    showReflection.value = false;
  };

  const proceedNext = () => {
    if (!state.value || !currentNodeId.value || !selectedOptionId.value || !currentNode.value) return;

    const chosenOption = currentNode.value.options.find((o) => o.id === selectedOptionId.value);
    const nextNodeId = chosenOption?.nextNodeId ?? null;

    if (nextNodeId && activeScenario.value?.nodes[nextNodeId]) {
      currentNodeId.value = nextNodeId;
      selectedOptionId.value = null;
      reflectionInput.value = '';
      showReflection.value = false;

      state.value.currentNodeId = nextNodeId;
      state.value.visitedNodes.push(nextNodeId);
    } else {
      state.value.isCompleted = true;

      const finalScore = state.value.overallScore + (chosenOption?.score ?? 0);
      const totalPossibleScore = state.value.history.length * 100 + 100;
      const percent = Math.round((finalScore / totalPossibleScore) * 100);

      const xpWord = percent >= 90 ? 'Al-Hakeem Honor Unlocked!' : 'Dialogue Practice Completed!';
      const awardXp = percent >= 90 ? 250 : 150;

      toast.success(`${xpWord} Completed with ${percent}% efficiency. Earned +${awardXp} XP.`);
      gamification.pushToast({
        type: 'milestone',
        title: 'Practice Lab complete',
        subtitle: activeScenario.value?.title ?? 'Scenario',
        xpBonus: awardXp,
      });
    }
  };

  const rewindOneStep = () => {
    if (!state.value || state.value.history.length === 0) return;

    const newHistory = [...state.value.history];
    const undone = newHistory.pop();
    const prevNodeId = undone ? undone.nodeId : activeScenario.value!.initialNodeId;
    const subbedScore = undone ? undone.scoreAwarded : 0;

    currentNodeId.value = prevNodeId;
    selectedOptionId.value = null;
    reflectionInput.value = '';
    showReflection.value = false;
    isLoadingFeedback.value = false;

    state.value.currentNodeId = prevNodeId;
    state.value.overallScore = Math.max(0, state.value.overallScore - subbedScore);
    state.value.visitedNodes.pop();
    state.value.history = newHistory;
    state.value.isCompleted = false;
  };

  return {
    scenarios,
    scenariosLoading,
    scenariosError,
    activeScenario,
    currentNode,
    state,
    selectedOptionId,
    reflectionInput,
    showReflection,
    isLoadingFeedback,
    startScenario,
    resetEngine,
    selectOption,
    submitReflection,
    proceedNext,
    rewindOneStep,
  };
}
