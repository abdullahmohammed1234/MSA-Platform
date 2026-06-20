export interface MentorCriterion {
  rubric: string;
  score: number;
  maxScore: number;
  feedback: string;
}

export interface ConversationOption {
  id: string;
  text: string;
  score: number;
  nextNodeId: string | null; // null represents end of branch
  outcomeSummary?: string;
  emotionalFeedback: {
    characterToneImpact: "calm" | "defensive" | "curious" | "agitated" | "receptive" | "hostile" | "skeptical" | "thoughtful";
    atmosphereChange: string;
  };
  mentorFeedback: {
    mentorName: string;
    avatarUrl?: string;
    scoreExplanation: string;
    encouragement: string;
    improvementSuggestions: string[];
    criteriaScores: MentorCriterion[];
  };
  aiSuggestions: {
    suggestedResponse: string;
    whyItWorks: string;
    improvementTips: string[];
    recommendedLessons: {
      title: string;
      moduleUrl: string;
      difficulty: string;
    }[];
    relatedResources: {
      name: string;
      type: "article" | "video" | "guide";
      url: string;
    }[];
  };
}

export interface ConversationNode {
  id: string;
  characterText: string;
  characterTone: "concerned" | "agitated" | "skeptical" | "curious" | "thoughtful" | "hostile" | "friendly";
  assistantInstruction: string;
  options: ConversationOption[];
  reflectionPrompt?: {
    question: string;
    placeholder: string;
    mentorSampleAnswer: string;
  };
}

export interface PracticeScenario {
  id: string;
  title: string;
  difficulty: "Beginner" | "Intermediate" | "Advanced";
  category: string;
  description: string;
  characterName: string;
  characterRole: string;
  avatarSeed: string; // Used to fetch or generate unique looking avatar style
  initialNodeId: string;
  nodes: Record<string, ConversationNode>;
}

export interface ConversationState {
  currentScenarioId: string;
  currentNodeId: string;
  visitedNodes: string[];
  history: {
    nodeId: string;
    selectedOptionId: string;
    scoreAwarded: number;
    reflectionResponse?: string;
  }[];
  atmosphereScore: number; // 0 to 100 representing how friendly/hostile the talk is
  overallScore: number;
  isCompleted: boolean;
}
