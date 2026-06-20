import client from '@/services/api/client';

export interface SimulationTranscriptStep {
  nodeId: string;
  inquiry: string;
  selectedResponse: string;
  score: number;
  mentorAdvice: string;
}

export interface SimulationReflection {
  question: string;
  answer: string;
  mentorSample: string;
}

export interface SimulationSessionRecord {
  id: string;
  scenarioId: string;
  scenarioTitle: string;
  category?: string;
  difficulty?: string;
  characterName?: string;
  avatarSeed?: string;
  overallScore: number;
  atmosphereScore: number;
  transcript: SimulationTranscriptStep[];
  reflections: SimulationReflection[];
  isBookmarked: boolean;
  completedAt?: string;
}

export interface SimulationHistorySummary {
  totalXp: number;
  avgScore: number;
  avgAtmosphere: number;
  attemptCount: number;
}

export const simulationsService = {
  async getHistory(): Promise<{
    sessions: SimulationSessionRecord[];
    summary: SimulationHistorySummary;
  }> {
    const response = await client.get('/simulations/history');
    const data = response.data;
    if (!data?.success) {
      throw new Error(data?.message || 'Failed to load simulation history.');
    }
    return {
      sessions: data.sessions ?? [],
      summary: data.summary ?? { totalXp: 0, avgScore: 0, avgAtmosphere: 0, attemptCount: 0 },
    };
  },

  async saveSession(payload: {
    scenarioId: string;
    scenarioTitle: string;
    category?: string;
    difficulty?: string;
    characterName?: string;
    avatarSeed?: string;
    overallScore: number;
    atmosphereScore: number;
    transcript: SimulationTranscriptStep[];
    reflections: SimulationReflection[];
    completedAt?: string;
  }): Promise<SimulationSessionRecord> {
    const response = await client.post('/simulations/sessions', {
      scenario_id: payload.scenarioId,
      scenario_title: payload.scenarioTitle,
      category: payload.category,
      difficulty: payload.difficulty,
      character_name: payload.characterName,
      avatar_seed: payload.avatarSeed,
      overall_score: payload.overallScore,
      atmosphere_score: payload.atmosphereScore,
      transcript: payload.transcript,
      reflections: payload.reflections,
      completed_at: payload.completedAt,
    });
    const data = response.data;
    if (!data?.success) {
      throw new Error(data?.message || 'Failed to save simulation session.');
    }
    return data.session;
  },

  async getScenarios(): Promise<import('@/services/academy/scenariosTypes').PracticeScenario[]> {
    const response = await client.get('/simulations/scenarios');
    const data = response.data;
    if (!data?.success) {
      throw new Error(data?.message || 'Failed to load scenarios.');
    }
    return Array.isArray(data.scenarios) ? data.scenarios : [];
  },

  async toggleBookmark(sessionId: string, isBookmarked: boolean): Promise<SimulationSessionRecord> {
    const response = await client.patch(`/simulations/sessions/${sessionId}/bookmark`, {
      is_bookmarked: isBookmarked,
    });
    const data = response.data;
    if (!data?.success) {
      throw new Error(data?.message || 'Failed to update bookmark.');
    }
    return data.session;
  },
};
