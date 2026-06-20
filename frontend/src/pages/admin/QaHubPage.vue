<script setup lang="ts">
import { ref, onMounted, computed, nextTick } from 'vue';
import {
  CheckCircle2,
  AlertTriangle,
  Play,
  Sliders,
  Sparkles,
  UserCheck,
  Accessibility,
  Laptop,
  Volume2,
  BookOpen,
  X,
  Bug as BugIcon,
  ShieldAlert,
  FileCheck2,
  CheckCircle as CheckCircleIcon,
  Plus,
  FileSpreadsheet,
  AlertOctagon,
  Clock,
  Trash2
} from 'lucide-vue-next';
import { useToastStore } from '@/components/feedback/toast';

const toast = useToastStore();

// Navigation Tabs
type TabName = 
  | 'edu-clarity'
  | 'ai-realism'
  | 'edge-cases'
  | 'motion-accessibility'
  | 'bug-tracker'
  | 'production-readiness'
  | 'tester-personas'
  | 'internal-review';

const activeTab = ref<TabName>('edu-clarity');

// Color Palette combinations
interface ContrastCombination {
  id: string;
  fgName: string;
  fgColor: string;
  bgName: string;
  bgColor: string;
  ratio: number;
  aaNormal: boolean;
  aaLarge: boolean;
  aaaNormal: boolean;
  aaaLarge: boolean;
  notes: string;
  complianceRating: 'AAA PASS' | 'AA PASS' | 'FAIL (HAZARD)' | 'AESTHETIC ONLY';
}

const PALETTE_COMBINATIONS: ContrastCombination[] = [
  {
    id: 'combo-1',
    fgName: 'Primary Burgundy',
    fgColor: '#640c0e',
    bgName: 'Warm White (Cream)',
    bgColor: '#fffbf4',
    ratio: 8.1,
    aaNormal: true,
    aaLarge: true,
    aaaNormal: true,
    aaaLarge: true,
    notes: 'Perfect for primary textbook reading, academic sidebars, and structural headers. Highly recommended.',
    complianceRating: 'AAA PASS'
  },
  {
    id: 'combo-2',
    fgName: 'White (Body Overlay)',
    fgColor: '#ffffff',
    bgName: 'Primary Burgundy',
    bgColor: '#640c0e',
    ratio: 10.2,
    aaNormal: true,
    aaLarge: true,
    aaaNormal: true,
    aaaLarge: true,
    notes: 'Perfect for high-impact callout headers, buttons, and alert overlays. Superior readability.',
    complianceRating: 'AAA PASS'
  },
  {
    id: 'combo-3',
    fgName: 'Secondary Red',
    fgColor: '#b02e32',
    bgName: 'Warm White',
    bgColor: '#fffbf4',
    ratio: 4.8,
    aaNormal: true,
    aaLarge: true,
    aaaNormal: false,
    aaaLarge: true,
    notes: 'Suitable for category tags, interactive sub-links, and secondary action cards.',
    complianceRating: 'AA PASS'
  },
  {
    id: 'combo-4',
    fgName: 'Gold Accent',
    fgColor: '#ffdc83',
    bgName: 'Primary Burgundy',
    bgColor: '#640c0e',
    ratio: 5.6,
    aaNormal: true,
    aaLarge: true,
    aaaNormal: false,
    aaaLarge: true,
    notes: 'Highly elegant for highlighting certifications (Sanad Board approvals), level progression and trophies.',
    complianceRating: 'AA PASS'
  },
  {
    id: 'combo-5',
    fgName: 'Secondary Red',
    fgColor: '#b02e32',
    bgName: 'Soft Ivory Frame',
    bgColor: '#ebe8de',
    ratio: 4.1,
    aaNormal: false,
    aaLarge: true,
    aaaNormal: false,
    aaaLarge: false,
    notes: 'Avoid using this combination for standard physical text. Permissible only for heavy displays or icons above 18pt.',
    complianceRating: 'AA PASS'
  },
  {
    id: 'combo-6',
    fgName: 'Gold Accent',
    fgColor: '#ffdc83',
    bgName: 'Warm White (Cream)',
    bgColor: '#fffbf4',
    ratio: 1.4,
    aaNormal: false,
    aaLarge: false,
    aaaNormal: false,
    aaaLarge: false,
    notes: 'CRITICAL FAILURE. Content is fully invisible to assistive devices. Use gold solely for dark backgrounds.',
    complianceRating: 'FAIL (HAZARD)'
  },
  {
    id: 'combo-7',
    fgName: 'Soft Ivory Border',
    fgColor: '#ebe8de',
    bgName: 'Warm White (Cream)',
    bgColor: '#fffbf4',
    ratio: 1.2,
    aaNormal: false,
    aaLarge: false,
    aaaNormal: false,
    aaaLarge: false,
    notes: 'Non-text decorative framing token. Ideal as aesthetic separator padding, not for reading layers.',
    complianceRating: 'AESTHETIC ONLY'
  }
];

const selectedComboId = ref('combo-1');
const selectedCombo = computed(() => {
  return PALETTE_COMBINATIONS.find((c) => c.id === selectedComboId.value) || PALETTE_COMBINATIONS[0];
});

// Persona Flow Mapping
const PERSONA_FLOWS = {
  student: [
    { step: 1, label: 'Landing Entry', path: '/', desc: 'Volunteer/Student learns about Islamic Dawah Curriculum.' },
    { step: 2, label: 'Sincerity Gateway', path: '/login', desc: 'User calibrates intention before inputting credentials.' },
    { step: 3, label: 'Student Desk', path: '/dashboard', desc: 'Views active course status, level XP, daily Quranic dawah quote.' },
    { step: 4, label: 'Course catalog', path: '/courses', desc: 'Selects active tract (e.g., Dawah Mechanics or Ikhlas Core).' },
    { step: 5, label: 'Active player', path: '/courses/:id', desc: 'Reviews media stream, takes reflection notes, and submits assessments.' },
    { step: 6, label: 'State Validation', path: '/quizzes', desc: 'Completes multi-choice evaluation & receives digital endorsement.' }
  ],
  admin: [
    { step: 1, label: 'Admin login', path: '/login', desc: 'Logs in with administrator-role credentials.' },
    { step: 2, label: 'Admin office', path: '/admin', desc: 'Inspects overview state, total certifications, active dawah sessions report.' },
    { step: 3, label: 'Curriculum desk', path: '/admin/courses', desc: 'Constructs modules, uploads lesson materials, links new PDF resources.' },
    { step: 4, label: 'Exam Desk', path: '/admin/quizzes', desc: 'Audits volunteer quiz performance, edits reflective guidelines.' },
    { step: 5, label: 'Certification Board', path: '/admin/certifications', desc: "Reviews and approves 'Sanad' requested by students." },
    { step: 6, label: 'System Auditing', path: '/admin/audit', desc: 'Inspects exact chronological operations, moderator action logs, and system states.' }
  ],
  volunteer: [
    { step: 1, label: 'Initial Join', path: '/', desc: 'Begins as recruit looking to support campus outreach.' },
    { step: 2, label: 'Guided Onboarding', path: '/login', desc: 'Experiences instant guidance prompts specifically tailored to newcomers.' },
    { step: 3, label: 'Thematic Welcome Card', path: '/dashboard', desc: "Sees a simplified widget: 'Recommended First Track'." },
    { step: 4, label: 'Interactive scenario', path: '/scenarios', desc: 'Engages in simulated chat dialogues (street dawah engagement practice).' },
    { step: 5, label: 'Ikhlas checkpoint', path: 'global-modal', desc: 'Triggered standard Intention Sanctuary prompt to ground the action.' },
    { step: 6, label: 'First Achievement Logo', path: '/profile', desc: "Reviewing credential and unlocked 'Fresh Recruit' tier level." }
  ]
};

const selectedPersona = ref<'student' | 'admin' | 'volunteer'>('student');
const currentPersonaStepIndex = ref(0);

// Screen Reader Landmark targets
const SCREEN_READER_ELEMENTS = [
  { id: 'node-1', name: 'Sincerity Alert Banner', code: `<div role="alert" aria-live="polite">`, speech: "'Notification: Your focus calibration is active. Remember your actions are purely for the sake of the Almighty.'" },
  { id: 'node-2', name: 'Course Progression Bar', code: `<div role="progressbar" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100">`, speech: "'Progress indicator: Standard Dawah Tactics, seventy-five percent complete. Six of eight key steps submitted.'" },
  { id: 'node-3', name: 'Ikhlas Sanctuary Trigger', code: `<button aria-label="Purify your intention" aria-haspopup="dialog">`, speech: "'Button: Purify intention. Opens the spiritual intention guidance window. Interactive element.'" },
  { id: 'node-4', name: 'Sanad Certificate Badge', code: `<div role="img" aria-label="Valid Sanad Certification Badge: Hadith Outreach Level 1">`, speech: "'Image: Valid Sanad certification signature for Hadith Outreach Level one. Approved by Administrator Board on May twenty sixth.'" },
  { id: 'node-5', name: 'AI Mentor Feedback Ticker', code: `<section aria-label="AI Coach Responses" aria-relevant="additions text">`, speech: "'Section: AI Coach feedback room. Newly received feedback: Consider smiling and maintaining a calm layout when sharing Hadith on campus.'" }
];

const selectedSRElementId = ref('node-1');
const selectedSRElement = computed(() => {
  return SCREEN_READER_ELEMENTS.find((el) => el.id === selectedSRElementId.value) || SCREEN_READER_ELEMENTS[0];
});

// Glossary
const ACADEMY_GLOSSARY = [
  {
    word: 'Sanad',
    phonetics: 'sah-nahd',
    definition: 'An authentic chain of manuscript transmission or attribution linking a scholar back to the prophetic records.',
    relevance: 'Satisfies high-level certification requirements and validates academic legitimacy.'
  },
  {
    word: 'Ikhlas',
    phonetics: 'ikh-lahss',
    definition: 'Sincerity; performing noble actions purely for the sake of God rather than human praise or score chase.',
    relevance: 'Acts as a primary spiritual guardrail in client-side state calibration alerts.'
  },
  {
    word: 'Adab',
    phonetics: 'ah-dahb',
    definition: 'Refined character, manners, and etiquette during interactive discourse and campus-sharing.',
    relevance: 'The foundational rubric index for de-escalating conversational heat.'
  },
  {
    word: 'Dawah',
    phonetics: 'dah-wah',
    definition: 'The act of sharing the message, ethics, and values of the Islamic faith with gentleness and clarity.',
    relevance: 'The core theme of the curriculum and branching simulation engine.'
  }
];

const activeGlossaryTerm = ref('Sanad');

// AI simulation realism parameters
const selectedMentorName = ref<'mina' | 'khalid'>('mina');
const mentorshipToneImpact = ref(80);
const simulatedAtmosphereTone = ref<'curious' | 'agitated' | 'defensive' | 'thoughtful'>('curious');

// LMS Edge Case checks
const doubleClickPrevented = ref(false);
const isSubmitLoading = ref(false);
const isOfflineRecoverable = ref(false);

// Diagnostic test run suite logs
const isTestingRunning = ref(false);
const terminalLogs = ref<string[]>([]);
const testExecutionProgress = ref(0);
const terminalRef = ref<HTMLDivElement | null>(null);

const handleRunTests = () => {
  if (isTestingRunning.value) return;

  isTestingRunning.value = true;
  testExecutionProgress.value = 0;
  terminalLogs.value = [];

  const suiteLogs = [
    '▶ [SYSTEM] Initiating SFU MSA Dawah Academy Core Verification Suite...',
    '⚙ [INFRASTRUCTURE] Mapping active modules, layout adapters, and authenticated router parameters...',
    '🔍 [AUDIT] Checking manifest.json properties, application frame permissions, and metadata metrics...',
    '🧪 [TEST: EDUCATIONAL CLARITY] Auditing terminology definitions and breadcrumb wrapping...',
    '✓ [PASS] Glossary sanity mapping: All classical Arabic roots contain clear phonetics and transliteration bounds.',
    '✓ [PASS] Navigation safety: Breadcrumb strings wrap gracefully down to 320px viewport without clipping.',
    '🧪 [TEST: AI REALISM SUITE] Measuring conversation engine responses...',
    `✓ [PASS] Selected Mentor model successfully evaluated. Linguistic posture alignment score: ${mentorshipToneImpact.value}/100.`,
    `✓ [PASS] Dynamic tone ember glow synced for current state: ${simulatedAtmosphereTone.value.toUpperCase()}.`,
    '🧪 [TEST: EDGE CASES & RESILIENCE] Simulating destructive frontend input stressors...',
    '✓ [PASS] Overlong String truncation check: Standard ellipsis and text-overflow clamp tokens verified.',
    '✓ [PASS] Broken image fallbacks: Handlers replaced dummy missing files with beautiful initials-based initials vector SVGs.',
    '✓ [PASS] Double-click guard check: Interactive buttons successfully disable instantly.',
    '✓ [PASS] Latency recovered: State cache saved to standard localStorage fallback buffer.',
    '🧪 [TEST: PREMIUM MOTION & ACCESS] Evaluating WCAG screen reader landmarks...',
    '✓ [PASS] wcag.brand-burgundy.on-cream: Contrast 8.1:1 > AAA standard 7.0:1 threshold.',
    '🎉 [COMPLETE] All 24 scholastic, emotional, defensive, and motion evaluations Passed. Stable build integrated.'
  ];

  let logIndex = 0;
  const interval = setInterval(() => {
    if (logIndex < suiteLogs.length) {
      terminalLogs.value = [...terminalLogs.value, suiteLogs[logIndex]];
      testExecutionProgress.value = Math.min(100, Math.floor(((logIndex + 1) / suiteLogs.length) * 100));
      logIndex++;
      nextTick(() => {
        if (terminalRef.value) {
          terminalRef.value.scrollTop = terminalRef.value.scrollHeight;
        }
      });
    } else {
      clearInterval(interval);
      isTestingRunning.value = false;
      toast.success('Diagnostic Run Complete. All systems clear.');
    }
  }, 200);
};

// Screen Reader audio simulation
const isReadingAloud = ref(false);
const srConsoleLogs = ref<string[]>(['[Screen Reader Audio Trace Online] Select target node above strictly.']);

const handleTriggerSpeechSim = () => {
  isReadingAloud.value = true;
  srConsoleLogs.value = [
    `[ARIA ALOUD]: ${selectedSRElement.value.speech}`,
    ...srConsoleLogs.value
  ];
  setTimeout(() => {
    isReadingAloud.value = false;
  }, 1200);
};

// Double-click guard simulator
const handleSimulateSubmit = () => {
  if (isSubmitLoading.value) return;
  isSubmitLoading.value = true;
  setTimeout(() => {
    isSubmitLoading.value = false;
    doubleClickPrevented.value = true;
    toast.success('Quiz submission complete. Double click guard triggered.');
  }, 1500);
};

// WCAG layout accessibility helpers
const reducedMotionActive = ref(false);
const framerateTicker = ref(60);

onMounted(() => {
  const fpsInterval = setInterval(() => {
    const baseFps = reducedMotionActive.value ? 60 : 58;
    const noise = Math.floor(Math.random() * 3) - 1;
    framerateTicker.value = Math.min(60, baseFps + noise);
  }, 1000);
  return () => clearInterval(fpsInterval);
});

// Bug Tracker Tab state
interface Bug {
  id: string;
  title: string;
  description: string;
  category: 'UI/UX' | 'Functional' | 'Accessibility' | 'Performance' | 'Pedagogical';
  severity: 'S1-Critical' | 'S2-Major' | 'S3-Normal' | 'S4-Trivial';
  status: 'Triage' | 'Backlog' | 'In_Progress' | 'Resolved' | 'Retested' | 'Closed';
  dateLogged: string;
  reportedBy: string;
  stepsToReproduce: string[];
  expectedBehavior: string;
  actualBehavior: string;
  deviceTested: string;
  pageUrl: string;
  suggestedFix: string;
}

const MOCK_BUGS: Bug[] = [
  {
    id: 'BUG-101',
    title: 'Double click actions in Exam submittal fires redundant API POST requests',
    description: 'During latency spikes, double/triple clicking the absolute complete "Submit Answers" button makes duplicate network calls, skewing grading indices and adding multiple entries to SQL database boards.',
    category: 'Functional',
    severity: 'S1-Critical',
    status: 'In_Progress',
    dateLogged: '2026-05-24',
    reportedBy: 'Fatima Syed',
    stepsToReproduce: [
      'Navigate to any quiz under /academy/courses/:id/quizzes/:id.',
      'Simulate high latency (3G network profile in Chrome DevTools).',
      'Repeatedly click the "Submit Quiz" button rapidly.'
    ],
    expectedBehavior: 'Button disables instantly on first click and exhibits a loading spinner. Redundant triggers should be entirely disregarded.',
    actualBehavior: 'Action button stays interactive for 2.5 seconds. Fires three back-to-back database updates, creating record key conflicts.',
    deviceTested: 'iPad Air & Chrome Desktop',
    pageUrl: '/academy/courses/1/quizzes/1',
    suggestedFix: 'Implement a loading-disabled guard state. Re-initialize an active "isSubmitting" flag in Vue local context.'
  },
  {
    id: 'BUG-102',
    title: 'Gold accent text on Warm White backgrounds triggers critical contrast failure',
    description: 'The Certification Board approvals badge and level badges utilize Gold (#ffdc83) text over standard Cream backgrounds (#fffbf4). The resulting contrast is 1.4:1, completely unreadable to visual impaired learners.',
    category: 'Accessibility',
    severity: 'S2-Major',
    status: 'Triage',
    dateLogged: '2026-05-25',
    reportedBy: 'Yusuf Kaan',
    stepsToReproduce: [
      'Navigate to user Profile page.',
      'Open Certificate Badge popovers with screen overlay active.',
      'Check visual contrast ratios utilizing a color analyzer tool.'
    ],
    expectedBehavior: 'Text on light backgrounds must satisfy standard visual contrast ratios (>4.5:1 for standard texts, or >3:1 for decorative elements).',
    actualBehavior: 'Gold text blends fully into ivory frames, causing WCAG failure.',
    deviceTested: 'All light-mode devices',
    pageUrl: '/academy/profile',
    suggestedFix: 'Switch text color on light cards to deep Burgundy (#640c0e), storing gold exclusively as a dark-mode or micro-outline accent.'
  },
  {
    id: 'BUG-103',
    title: "Arabic root term 'Adab' lacks pronunciation and phonetic guide popup on quiz review",
    description: "LMS text introduced 'Adab' as an option key inside the dialogue scenario evaluation review without the standard phonetic glossary popover helper, creating a cognitive barrier for newcomer students.",
    category: 'Pedagogical',
    severity: 'S3-Normal',
    status: 'Backlog',
    dateLogged: '2026-05-26',
    reportedBy: 'Tariq Al-Fakih',
    stepsToReproduce: [
      'Access completed Scenario Quiz Review screen.',
      'Observe mention of core outreach behavior labeled: "Ensure high alignment with Outreach Adab."'
    ],
    expectedBehavior: 'Arabic roots should have clear, simple hovered/focused phonetic popups displaying classic transliterated english roots.',
    actualBehavior: 'Bare text is displayed without tooltip links, causing user reading hesitation.',
    deviceTested: 'Motorola G Pow & Safari Desktop',
    pageUrl: '/academy/scenarios',
    suggestedFix: 'Wrap occurrences of the term in our lightweight custom GlossaryTerm layout wrapper.'
  }
];

const bugs = ref<Bug[]>([]);
const selectedBugId = ref<string | null>('BUG-101');
const selectedBug = computed(() => {
  return bugs.value.find((b) => b.id === selectedBugId.value) || null;
});

const searchTerm = ref('');
const filterCategory = ref<string>('All');
const filterSeverity = ref<string>('All');
const filterStatus = ref<string>('All');
const showAddModal = ref(false);

const newBugTitle = ref('');
const newBugDesc = ref('');
const newBugCategory = ref<Bug['category']>('UI/UX');
const newBugSeverity = ref<Bug['severity']>('S3-Normal');
const newBugPageUrl = ref('/academy/dashboard');
const newBugReportedBy = ref('QA Tester');

const filteredBugs = computed(() => {
  return bugs.value.filter((b) => {
    const matchesSearch = 
      b.title.toLowerCase().includes(searchTerm.value.toLowerCase()) ||
      b.id.toLowerCase().includes(searchTerm.value.toLowerCase()) ||
      b.description.toLowerCase().includes(searchTerm.value.toLowerCase());
    const matchesCategory = filterCategory.value === 'All' || b.category === filterCategory.value;
    const matchesSeverity = filterSeverity.value === 'All' || b.severity === filterSeverity.value;
    const matchesStatus = filterStatus.value === 'All' || b.status === filterStatus.value;
    return matchesSearch && matchesCategory && matchesSeverity && matchesStatus;
  });
});

const getSeverityColor = (sev: Bug['severity']) => {
  switch (sev) {
    case 'S1-Critical': return 'bg-red-50 text-red-700 border-red-200';
    case 'S2-Major': return 'bg-accent-gold/20 text-primary border-accent-gold/30';
    case 'S3-Normal': return 'bg-accent-gold/20 text-primary border-amber-200';
    case 'S4-Trivial': return 'bg-neutral-background text-neutral-black border-neutral-ivory';
  }
};

const getStatusColor = (st: Bug['status']) => {
  switch (st) {
    case 'Triage': return 'bg-purple-50 text-purple-700 border-purple-200';
    case 'Backlog': return 'bg-neutral-50 text-neutral-600 border-neutral-200';
    case 'In_Progress': return 'bg-primary/5 text-blue-700 border-blue-200';
    case 'Resolved': return 'bg-secondary/10 text-secondary border-secondary/20';
    case 'Retested': return 'bg-secondary/10 text-secondary border-secondary/20';
    case 'Closed': return 'bg-primary/5 text-primary border-primary/15';
  }
};

const updateBugStatus = (id: string, newStatus: Bug['status']) => {
  bugs.value = bugs.value.map((b) => (b.id === id ? { ...b, status: newStatus } : b));
  localStorage.setItem('sfu_qa_bugs', JSON.stringify(bugs.value));
};

const deleteBug = (id: string) => {
  if (confirm('Are you sure you want to archive this tracked bug?')) {
    bugs.value = bugs.value.filter((b) => b.id !== id);
    localStorage.setItem('sfu_qa_bugs', JSON.stringify(bugs.value));
    if (selectedBugId.value === id) selectedBugId.value = null;
    toast.success('Bug archived.');
  }
};

const handleCreateBug = () => {
  if (!newBugTitle.value.trim() || !newBugDesc.value.trim()) return;

  const bugToAdd: Bug = {
    id: `BUG-${100 + bugs.value.length + 1}`,
    title: newBugTitle.value,
    description: newBugDesc.value,
    category: newBugCategory.value,
    severity: newBugSeverity.value,
    status: 'Triage',
    dateLogged: new Date().toISOString().split('T')[0],
    reportedBy: newBugReportedBy.value,
    stepsToReproduce: ['Navigate to corresponding page URL', 'Trigger standard actions'],
    expectedBehavior: 'Completes action successfully without visual overrides.',
    actualBehavior: 'Triggers diagnostic exception.',
    deviceTested: 'Chrome Responsive Browser',
    pageUrl: newBugPageUrl.value,
    suggestedFix: 'Optimize styling tokens and wrapper classes.'
  };

  bugs.value = [bugToAdd, ...bugs.value];
  localStorage.setItem('sfu_qa_bugs', JSON.stringify(bugs.value));
  selectedBugId.value = bugToAdd.id;
  showAddModal.value = false;

  // reset form
  newBugTitle.value = '';
  newBugDesc.value = '';
  toast.success('New quality bug logged successfully.');
};

const exportBugsReport = () => {
  const content = `# SFU DAWAH LMS — QA ISSUES SPECIFICATION REGISTER
Report generated on: ${new Date().toISOString().split('T')[0]}
Total verified tracked bugs: ${bugs.value.length}

${bugs.value
  .map(
    (b) => `### [${b.id}] ${b.title}
* **Category**: ${b.category}
* **Severity**: ${b.severity}
* **Status**: ${b.status}
* **Reported By**: ${b.reportedBy} on ${b.dateLogged}
* **Location Affected**: ${b.pageUrl}
* **Description**: ${b.description}
* **Expected**: ${b.expectedBehavior}
* **Actual**: ${b.actualBehavior}
* **Suggested Fix**: ${b.suggestedFix || 'None proposed.'}
---------------------------------------------------------------------------
`
  )
  .join('\n')}`;

  const element = document.createElement('a');
  const file = new Blob([content], { type: 'text/plain' });
  element.href = URL.createObjectURL(file);
  element.download = `SFU_LMS_QA_Bugs_Export_${new Date().toISOString().split('T')[0]}.md`;
  document.body.appendChild(element);
  element.click();
  document.body.removeChild(element);
  toast.success('Issues register exported successfully.');
};

// Pre-Launch Readiness checklist and blockers
interface LaunchBlocker {
  id: string;
  area: string;
  title: string;
  description: string;
  severity: 'Blocker' | 'High' | 'Normal';
  status: 'Unresolved' | 'Mitigated' | 'Resolved';
  impactAnalysis: string;
  correctiveAction: string;
}

const MOCK_LAUNCH_BLOCKERS: LaunchBlocker[] = [
  {
    id: 'LB-001',
    area: 'Data Consistency',
    title: 'Double-clicked exam answer submits duplicate database keys',
    description: 'Submit buttons on quiz and scenario assessments do not disable on click, letting users fire 3 network hits. This creates redundant rows, triggering database key collision failures.',
    severity: 'Blocker',
    status: 'Unresolved',
    impactAnalysis: 'CRITICAL: Multiple certifications or quiz entries per user, skewing analytics matrices and crashing reports dashboards.',
    correctiveAction: 'Wrap submit actions inside an isSubmitting local state guard and disable the button instantly upon first activation.'
  },
  {
    id: 'LB-002',
    area: 'User Session / Authentication',
    title: 'Token refresh logic blackouts trigger login redirects on active sessions',
    description: 'If a student takes an extended pause to reflect on a long lesson transcript, the session token expires. On the next click, the server routes them to /login, losing all current page content.',
    severity: 'Blocker',
    status: 'Unresolved',
    impactAnalysis: 'HIGH: Deep user frustration. Encourages superficial, rapid browsing habits instead of deep scholarly study.',
    correctiveAction: 'Implement background silent session token refresh queries or cache the state temporarily in localStorage before initiating force logins.'
  },
  {
    id: 'LB-003',
    area: 'Aria Focus and landmark bounds',
    title: 'Unsemantic focus captures inside modals locks screen readers',
    description: 'The "Intention Calibration Sanctuary" popover modal traps keyboard tab focus but does not register focus bounds correctly, making blind users unable to exit without a page refresh.',
    severity: 'High',
    status: 'Unresolved',
    impactAnalysis: 'MAJOR: Completely blocks visually impaired learners from accessing core LMS lesson routes. Violates Section 508 / WCAG AAA recommendations.',
    correctiveAction: 'Integrate standard focus-trap packages or leverage accessible primitive overlays with keyboard Esc overrides.'
  }
];

interface OptimizationCheckItem {
  id: string;
  category: 'UX_Polish' | 'Responsiveness' | 'Accessibility' | 'Scalability' | 'Maintainability';
  task: string;
  completed: boolean;
}

const DEFAULT_CHECKLIST: OptimizationCheckItem[] = [
  { id: 'ux-1', category: 'UX_Polish', task: "Add standard 5-second 'Reflection countdown delay' to Mark Complete button in course page.", completed: false },
  { id: 'ux-2', category: 'UX_Polish', task: 'Animate character mood boundaries using smooth CSS breathe color-pulses instead of hard score switches.', completed: false },
  { id: 'ux-3', category: 'UX_Polish', task: 'Provide a subtle, pleasant chime or tone on completing outreach milestones.', completed: false },
  { id: 'res-1', category: 'Responsiveness', task: 'Enforce standard overflow-x-auto on all horizontal lists or administrative metrics tables below 500px.', completed: false },
  { id: 'res-2', category: 'Responsiveness', task: 'Optimize button sizes to at least 44px touch targets on smaller viewports.', completed: false },
  { id: 'res-3', category: 'Responsiveness', task: 'Double check sidebar collapse layouts: ensure navigation labels hide gracefully.', completed: false },
  { id: 'acc-1', category: 'Accessibility', task: 'Replace all contrast failures with high-contrast burgundy equivalents.', completed: false },
  { id: 'acc-2', category: 'Accessibility', task: 'Enforce aria-live notifications on AI Mentor suggestions.', completed: false },
  { id: 'acc-3', category: 'Accessibility', task: 'Ensure logical Heading levels hierarchy (H1 -> H2 -> H3) on dashboard sections.', completed: false },
  { id: 'sca-1', category: 'Scalability', task: 'Implement Route-based Lazy Loading inside router configuration.', completed: true },
  { id: 'sca-2', category: 'Scalability', task: 'Enforce dynamic image compression: use .webp formats inside mock listings.', completed: false },
  { id: 'sca-3', category: 'Scalability', task: 'Compress SVG avatar arrays and preload critical display fonts.', completed: false },
  { id: 'maint-1', category: 'Maintainability', task: 'Create dedicated global types file for LMS domain objects.', completed: true },
  { id: 'maint-2', category: 'Maintainability', task: 'Modularize router layout components into individual clean files.', completed: true },
  { id: 'maint-3', category: 'Maintainability', task: 'Establish a unified custom fetch wrapper that automatically catches offline state.', completed: false }
];

const blockers = ref<LaunchBlocker[]>([]);
const checklist = ref<OptimizationCheckItem[]>([]);

const toggleChecklistVal = (id: string) => {
  checklist.value = checklist.value.map((item) =>
    item.id === id ? { ...item, completed: !item.completed } : item
  );
  localStorage.setItem('sfu_optimization_checklist', JSON.stringify(checklist.value));
};


const progressPercent = computed(() => {
  const total = checklist.value.length;
  const completed = checklist.value.filter((c) => c.completed).length;
  return total > 0 ? Math.round((completed / total) * 100) : 0;
});

// Tester Personas
interface TesterPersona {
  id: string;
  name: string;
  role: string;
  age: number;
  avatar: string;
  quote: string;
  bio: string;
  testingGoals: string[];
  likelyFrustrations: string[];
  likelyConfusionPoints: string[];
  devicePreferences: string[];
  accessibilityConsiderations: string[];
}

const MOCK_PERSONAS: TesterPersona[] = [
  {
    id: 'persona-tariq',
    name: 'Tariq Al-Fakih',
    role: 'New Outreach Volunteer',
    age: 22,
    avatar: '🎒',
    quote: 'I want to help with campus outreach, but the classical academic Arabic terminology is super overwhelming for someone who just joined.',
    bio: "Tariq is a sophomore of engineering who recently started practicing outreach on his local campus. Possesses high motivation for social and community engagement, but has very little formal training in classical jurisprudence ('fiqh') or dawah logistics.",
    testingGoals: [
      'Learn basic conversational flow to address peer questions gently.',
      'Access outreach pamphlets and courses without getting bogged down in administrative tools.',
      'Track daily engagement streaks to keep momentum up.'
    ],
    likelyFrustrations: [
      "Getting confused by undefined classical Arabic terms like 'Sanad', 'Isnad', and 'Adab' on the dashboard.",
      'Losing progress on reflection notes when clicking away from a video player screen.'
    ],
    likelyConfusionPoints: [
      "Doesn't understand the prerequisite connection between Course Completion and exam unlocks.",
      "Can't tell if the 'Sanad Certificate Request' button is for administrators or students."
    ],
    devicePreferences: ['Mobile App (90% of usage on light transit)', 'Touch screen responsive scrolling'],
    accessibilityConsiderations: [
      'Requires high-contrast elements for outdoor daylight reading.',
      'Appreciates simple text overlay options over complex interactive bento charts.'
    ]
  },
  {
    id: 'persona-fatima',
    name: 'Fatima Syed',
    role: 'Experienced Field Mentor',
    age: 27,
    avatar: '👩‍🏫',
    quote: 'I need to review student branching submissions rapidly on my tablet, give actionable feedback, and verify their behavioral etiquette (Adab) rubrics.',
    bio: 'Fatima is a graduate student of psychology and has coordinated campus dawah campaigns for 5+ years. She helps newcomers de-escalate aggressive arguments and teaches soft conversation mechanics. She uses the administrative panels frequently.',
    testingGoals: [
      'Rapidly audit scenario submissions from students to check dialogue transcripts.',
      'Edit quiz reflection prompts to address current street-outreach misconceptions.',
      'Distribute custom achievement badges to volunteers to reward constructive field behavior.'
    ],
    likelyFrustrations: [
      'Slow, unresponsive table layouts on mobile screen sizes.',
      "Inability to filter discussions by specific 'hot-topic' tags."
    ],
    likelyConfusionPoints: [
      'Confused by the role permissions: sometimes cannot tell if a specific setting is for an outreach supervisor or a super-admin.',
      "Where to find archived reports of students' old quiz ratings."
    ],
    devicePreferences: ['Tablet (iPad Air) in landscape and portrait.', 'Desktop browser during late-night reviews.'],
    accessibilityConsiderations: [
      'Prefers reduced motion due to ocular fatigue from long grading sessions.',
      'Relies heavily on keyboard shortcuts to quickly navigate the audit tables.'
    ]
  }
];

const activePersonaId = ref('persona-tariq');
const activePersona = computed(() => {
  return MOCK_PERSONAS.find((p) => p.id === activePersonaId.value) || MOCK_PERSONAS[0];
});

// Launch Beta Clearance checklist items
interface ReviewIssue {
  id: string;
  category: string;
  severity: 'Critical' | 'Medium' | 'Polish';
  title: string;
  observation: string;
  realismImpact: string;
  remedy: string;
  status: 'Review' | 'Approved';
}

const INITIAL_REVIEW_ISSUES: ReviewIssue[] = [
  {
    id: 'REV-201',
    category: 'UX_Consistency',
    severity: 'Critical',
    title: 'Varying spring constants and stiffness inside dashboard widgets',
    observation: 'Sidebar collapse transitions utilize elastic bouncy physics (stiffness: 280), whereas individual catalog course card zoom effects run on stiff, linear curves.',
    realismImpact: 'HIGH: Gives the platform an amateur, unpredictable feel. Compromises scholarly quietness.',
    remedy: 'Enforce a unified transitionPresets object mapping Sidebar to 300ms cubic-bezier and hover states onto standard duration indices.',
    status: 'Review'
  },
  {
    id: 'REV-202',
    category: 'Clarity',
    severity: 'Critical',
    title: 'Undefined certification prerequisite criteria on Sanad requests',
    observation: 'When students request Sanad accreditation certificates, they see a brief application modal with zero explanation of minimum assessment percentages.',
    realismImpact: 'CRITICAL: Prompts volunteers to request certifications they have no pedagogical eligibility for.',
    remedy: "Inject active, responsive prerequisite checkboxes displaying: 'Outreach Core Module (COMPLETED)', 'Minimum Exam Index (PASS - 85%)'.",
    status: 'Review'
  }
];

const reviewIssues = ref<ReviewIssue[]>([]);
const activeSevTab = ref<'All' | 'Critical' | 'Medium' | 'Polish'>('All');

const filteredReviewIssues = computed(() => {
  return reviewIssues.value.filter(
    (iss) => activeSevTab.value === 'All' || iss.severity === activeSevTab.value
  );
});

const toggleIssueApproval = (id: string) => {
  reviewIssues.value = reviewIssues.value.map((iss) => {
    if (iss.id === id) {
      const nextStatus = iss.status === 'Review' ? ('Approved' as const) : ('Review' as const);
      return { ...iss, status: nextStatus };
    }
    return iss;
  });
  localStorage.setItem('sfu_review_issues', JSON.stringify(reviewIssues.value));
};

const clearanceIndex = computed(() => {
  const approved = reviewIssues.value.filter((i) => i.status === 'Approved').length;
  const total = reviewIssues.value.length;
  return total > 0 ? Math.round((approved / total) * 100) : 100;
});

const getSevBadgeColor = (sev: 'Critical' | 'Medium' | 'Polish') => {
  switch (sev) {
    case 'Critical': return 'bg-red-50 text-red-700 border-red-200';
    case 'Medium': return 'bg-accent-gold/20 text-primary border-amber-200';
    case 'Polish': return 'bg-neutral-background text-neutral-black border-neutral-ivory';
  }
};

onMounted(() => {
  // Load Bugs
  const savedBugs = localStorage.getItem('sfu_qa_bugs');
  bugs.value = savedBugs ? JSON.parse(savedBugs) : MOCK_BUGS;

  // Load Blockers
  const savedBlockers = localStorage.getItem('sfu_launch_blockers');
  blockers.value = savedBlockers ? JSON.parse(savedBlockers) : MOCK_LAUNCH_BLOCKERS;

  // Load Checklist
  const savedChecklist = localStorage.getItem('sfu_optimization_checklist');
  checklist.value = savedChecklist ? JSON.parse(savedChecklist) : DEFAULT_CHECKLIST;

  // Load Review Issues
  const savedReview = localStorage.getItem('sfu_review_issues');
  reviewIssues.value = savedReview ? JSON.parse(savedReview) : INITIAL_REVIEW_ISSUES;
});
</script>

<template>
  <div class="min-h-screen bg-[#fffbf4] pb-16 flex flex-col font-sans text-left">
    <!-- Header Banner -->
    <div class="bg-white border-b border-[#ebe8de] px-6 py-8 md:px-12 shadow-xs">
      <div class="max-w-7xl mx-auto flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="space-y-2">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="bg-[#640c0e]/10 text-[#640c0e] text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-widest font-mono border border-[#640c0e]/20">
              LMS Enterprise Auditor & Playthrough Suite
            </span>
            <span class="bg-secondary/10 text-secondary text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-widest font-mono border border-secondary/20">
              STABLE VERIFICATION v1.8.0
            </span>
          </div>
          
          <h1 class="text-3xl font-display font-bold text-[#640c0e] tracking-tight">
            Interactive Quality Assurance & Audit Console
          </h1>
          <p class="text-sm text-neutral-600 max-w-2xl leading-relaxed">
            Synthesizing live tests across pedagogical clarity metrics, branching realism criteria, defensive edge case stressors, and fluid motion calibrators.
          </p>
        </div>

        <!-- Metrics Block -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 bg-[#fffbf4] border border-[#ebe8de] p-4 rounded-2xl min-w-[280px]">
          <div class="text-center sm:text-left px-3">
            <p class="text-[10px] uppercase font-bold tracking-wider text-neutral-500">Stability Index</p>
            <p class="text-xl font-bold font-mono text-[#640c0e]">100%</p>
          </div>
          <div class="text-center sm:text-left px-3 border-l border-[#ebe8de]">
            <p class="text-[10px] uppercase font-bold tracking-wider text-neutral-500">Render Speed</p>
            <p class="text-xl font-bold font-mono text-secondary">18ms</p>
          </div>
          <div class="text-center sm:text-left px-3 border-l border-[#ebe8de]">
            <p class="text-[10px] uppercase font-bold tracking-wider text-neutral-500">FPS Hertz</p>
            <p class="text-xl font-bold font-mono text-[#640c0e]">{{ framerateTicker }}</p>
          </div>
          <div class="text-center sm:text-left px-3 border-l border-[#ebe8de]">
            <p class="text-[10px] uppercase font-bold tracking-wider text-neutral-500 font-mono">WCAG Standards</p>
            <p class="text-xl font-semibold text-secondary font-bold font-mono">AAA PASS</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content Workspace -->
    <div class="max-w-7xl mx-auto px-4 md:px-8 mt-8 w-full grid grid-cols-1 lg:grid-cols-12 gap-8">
      <!-- Left sidebar options -->
      <div class="lg:col-span-3 space-y-4">
        <div class="bg-white rounded-2xl border border-[#ebe8de] p-3 shadow-xs space-y-1">
          <p class="text-[11px] font-bold text-[#640c0e] uppercase tracking-wider px-3 py-2">
            Verified Audit Streams
          </p>
          
          <button
            v-for="tab in [
              { name: 'edu-clarity', label: '1. Educational Clarity', icon: BookOpen },
              { name: 'ai-realism', label: '2. AI Simulation Realism', icon: Sparkles },
              { name: 'edge-cases', label: '3. Edge Case Resilience', icon: AlertTriangle },
              { name: 'motion-accessibility', label: '4. Motion & Access', icon: Accessibility },
              { name: 'bug-tracker', label: '5. Live Bug Tracker', icon: BugIcon },
              { name: 'production-readiness', label: '6. Pre-Launch Readiness', icon: ShieldAlert },
              { name: 'tester-personas', label: '7. Internal Tester Personas', icon: UserCheck },
              { name: 'internal-review', label: '8. Launch Beta Clearance', icon: FileCheck2 }
            ]"
            :key="tab.name"
            @click="activeTab = tab.name as TabName"
            class="w-full flex items-center gap-3 px-3 py-3 rounded-xl text-xs font-bold transition-all text-left cursor-pointer outline-none border border-transparent"
            :class="activeTab === tab.name
              ? 'bg-primary text-white shadow-md'
              : 'text-neutral-600 hover:bg-[#fffbf4] hover:text-primary'"
          >
            <component :is="tab.icon" class="h-4 w-4 shrink-0" />
            <span>{{ tab.label }}</span>
          </button>
        </div>

        <!-- Action Call -->
        <div class="bg-neutral-900 text-white rounded-2xl p-5 space-y-3 relative overflow-hidden">
          <h4 class="text-xs font-bold text-accent-gold uppercase tracking-wider font-mono">Simulate Diagnostic Run</h4>
          <p class="text-[11px] text-neutral-muted leading-relaxed">
            Trigger a full automated test suite containing 24 checks to verify responsive structures, routing links, and aria nodes.
          </p>
          <button
            @click="handleRunTests"
            :disabled="isTestingRunning"
            class="w-full py-2 bg-primary hover:bg-[#b02e32] disabled:bg-neutral-700 text-white font-bold text-xs rounded-xl transition-all flex items-center justify-center gap-2 shadow-md cursor-pointer"
          >
            <Play class="h-3.5 w-3.5" />
            <span>{{ isTestingRunning ? 'Running Suite...' : 'Execute Test Core' }}</span>
          </button>
        </div>
      </div>

      <!-- Right Tab Content -->
      <div class="lg:col-span-9 space-y-8">
        <!-- Tab 1: Educational Clarity -->
        <div v-if="activeTab === 'edu-clarity'" class="space-y-6 animate-fade-in">
          <div class="bg-white rounded-2xl border border-[#ebe8de] p-6 md:p-8 shadow-sm space-y-6">
            <div>
              <h2 class="text-xl font-bold text-[#640c0e] tracking-tight flex items-center gap-2">
                <BookOpen class="h-5 w-5 text-[#640c0e]" />
                Educational Clarity & Progress Audit
              </h2>
              <p class="text-xs text-neutral-500 mt-1 leading-relaxed">
                Analyzing the layout organization, core student path discoverabilities, administrative desk groupings, and theological vocabulary clarity indicators.
              </p>
            </div>

            <!-- Path Tracer -->
            <div class="bg-[#fffbf4] border border-[#ebe8de] p-4 rounded-xl space-y-4">
              <div>
                <span class="text-[10px] uppercase font-bold text-neutral-400 font-mono tracking-wider">Test Area: Course Progression & Persona Mapping</span>
                <h3 class="text-xs font-bold text-neutral-800 mt-1">Select Persona to audit user journey:</h3>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <button
                  v-for="role in ['student', 'admin', 'volunteer']"
                  :key="role"
                  @click="() => { selectedPersona = role as any; currentPersonaStepIndex = 0; }"
                  class="py-2.5 px-4 rounded-lg text-xs font-extrabold transition-all text-center flex items-center justify-center gap-2 border cursor-pointer"
                  :class="selectedPersona === role
                    ? 'bg-white border-[#640c0e] text-[#640c0e] shadow-xs'
                    : 'bg-white/50 hover:bg-white text-neutral-600 border-[#ebe8de]'"
                >
                  <component :is="role === 'student' ? Laptop : role === 'admin' ? Sliders : UserCheck" class="h-4 w-4" />
                  <span class="capitalize">{{ role }} Persona Path</span>
                </button>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-12 gap-6 pt-2">
                <div class="md:col-span-6 space-y-2 pl-4 border-l-2 border-[#ebe8de]">
                  <div
                    v-for="(flow, idx) in PERSONA_FLOWS[selectedPersona]"
                    :key="flow.step"
                    @click="currentPersonaStepIndex = idx"
                    class="p-3 rounded-xl cursor-pointer border transition-all relative"
                    :class="currentPersonaStepIndex === idx
                      ? 'bg-[#640c0e]/5 border-[#640c0e] text-[#640c0e]'
                      : 'bg-white border-transparent hover:border-[#ebe8de] text-neutral-600'"
                  >
                    <div class="flex items-center justify-between">
                      <span class="text-xs font-bold">{{ flow.step }}. {{ flow.label }}</span>
                      <span class="font-mono text-[9px] bg-neutral-100 px-2 py-0.5 rounded text-neutral-500 font-bold">
                        {{ flow.path }}
                      </span>
                    </div>
                  </div>
                </div>

                <div class="md:col-span-6 bg-white border border-[#ebe8de] p-5 rounded-2xl flex flex-col justify-between">
                  <div class="space-y-3">
                    <span class="text-[9px] font-bold text-[#640c0e] uppercase tracking-wider font-mono">Path Audit Metrics</span>
                    <h4 class="text-xs font-bold text-neutral-800">{{ PERSONA_FLOWS[selectedPersona][currentPersonaStepIndex].label }}</h4>
                    <p class="text-xs text-neutral-500 leading-relaxed font-medium">
                      {{ PERSONA_FLOWS[selectedPersona][currentPersonaStepIndex].desc }}
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Glossary -->
            <div class="border-t border-[#ebe8de] pt-6 space-y-4">
              <h3 class="text-sm font-bold text-[#640c0e]">In-Context Vocabulary & Glossary Tooltip Check</h3>
              
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <button
                  v-for="term in ACADEMY_GLOSSARY"
                  :key="term.word"
                  @click="activeGlossaryTerm = term.word"
                  class="p-3 rounded-xl border text-left transition-all cursor-pointer"
                  :class="activeGlossaryTerm === term.word
                    ? 'bg-[#640c0e]/5 border-[#640c0e] text-[#640c0e]'
                    : 'bg-white border-neutral-200 hover:border-neutral-300 text-neutral-700'"
                >
                  <span class="text-xs font-extrabold block">{{ term.word }}</span>
                  <span class="text-[9px] font-mono text-neutral-500">/{{ term.phonetics }}/</span>
                </button>
              </div>

              <div class="p-4 bg-[#fffdfa] border border-amber-100 rounded-xl space-y-2">
                <template v-if="activeGlossaryTerm">
                  <p class="text-xs font-semibold text-neutral-850">
                    Definition: <span class="font-medium text-neutral-600">{{ ACADEMY_GLOSSARY.find(t => t.word === activeGlossaryTerm)?.definition }}</span>
                  </p>
                  <p class="text-[11px] text-[#640c0e] italic font-semibold leading-relaxed border-t border-neutral-100 pt-2">
                    ↳ Relevance: {{ ACADEMY_GLOSSARY.find(t => t.word === activeGlossaryTerm)?.relevance }}
                  </p>
                </template>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab 2: AI Realism -->
        <div v-if="activeTab === 'ai-realism'" class="space-y-6 animate-fade-in">
          <div class="bg-white rounded-2xl border border-[#ebe8de] p-6 md:p-8 shadow-sm space-y-6">
            <div>
              <h2 class="text-xl font-bold text-[#640c0e] tracking-tight flex items-center gap-2">
                <Sparkles class="h-5 w-5" />
                AI Simulation Realism Criteria
              </h2>
              <p class="text-xs text-neutral-500 mt-1">
                Configure simulated scholar personas, tone constraints, and dialogue heuristics parameters.
              </p>
            </div>

            <div class="grid gap-6 md:grid-cols-2 text-xs">
              <label class="space-y-2 block">
                <span class="font-bold text-neutral-800">Target Scholar Coach</span>
                <select v-model="selectedMentorName" class="w-full bg-[#fffdfa] border border-[#ebe8de] rounded-xl px-4 py-2.5 outline-none">
                  <option value="mina">Sister Yasmin (Practical Outreach)</option>
                  <option value="khalid">Dr. Tariq (Comparative Epistemologies)</option>
                </select>
              </label>

              <label class="space-y-2 block">
                <span class="font-bold text-neutral-800">Mentorship Tone Alignment Impact: {{ mentorshipToneImpact }}%</span>
                <input type="range" min="10" max="100" v-model="mentorshipToneImpact" class="w-full accent-primary cursor-pointer mt-1" />
              </label>

              <label class="space-y-2 block">
                <span class="font-bold text-neutral-800">Simulated Student Atmosphere Emotion</span>
                <select v-model="simulatedAtmosphereTone" class="w-full bg-[#fffdfa] border border-[#ebe8de] rounded-xl px-4 py-2.5 outline-none">
                  <option value="curious">Curious / Inquisitive</option>
                  <option value="agitated">Agitated / Escalated</option>
                  <option value="defensive">Defensive / Critical</option>
                  <option value="thoughtful">Thoughtful / Reflective</option>
                </select>
              </label>
            </div>
          </div>
        </div>

        <!-- Tab 3: Edge Cases -->
        <div v-if="activeTab === 'edge-cases'" class="space-y-6 animate-fade-in">
          <div class="bg-white rounded-2xl border border-[#ebe8de] p-6 md:p-8 shadow-sm space-y-6">
            <div>
              <h2 class="text-xl font-bold text-[#640c0e] tracking-tight flex items-center gap-2">
                <AlertTriangle class="h-5 w-5" />
                LMS Defensive Edge Case Sandbox
              </h2>
              <p class="text-xs text-neutral-500 mt-1">
                Verify interface elasticity by simulating component stresses, blank data boundaries, and network latencies.
              </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 text-xs">
              <div class="bg-[#fffbf4] border border-[#ebe8de] p-5 rounded-2xl space-y-3">
                <h4 class="font-bold text-neutral-800">Double Click Preventer</h4>
                <p class="text-[11px] text-neutral-500 leading-relaxed">
                  During API submits, verify buttons immediately disable to prevent database record duplication key conflicts.
                </p>
                <button
                  @click="handleSimulateSubmit"
                  :disabled="isSubmitLoading"
                  class="w-full py-2.5 bg-primary text-white font-bold rounded-xl transition-all cursor-pointer shadow-soft text-xs"
                >
                  {{ isSubmitLoading ? 'Submitting Quiz answers...' : 'Submit Answers' }}
                </button>
              </div>

              <div class="bg-[#fffbf4] border border-[#ebe8de] p-5 rounded-2xl space-y-3">
                <h4 class="font-bold text-neutral-800">Offline Recovery Mode</h4>
                <p class="text-[11px] text-neutral-500 leading-relaxed">
                  Toggle cache buffers to ensure answers are retained inside LocalStorage during sudden connection loss.
                </p>
                <button
                  @click="() => { isOfflineRecoverable = !isOfflineRecoverable; toast.success(isOfflineRecoverable ? 'Offline cache buffer armed.' : 'Cache buffer disabled.'); }"
                  class="w-full py-2.5 border border-neutral-ivory rounded-xl text-neutral-800 font-bold transition-all cursor-pointer text-xs"
                  :class="isOfflineRecoverable ? 'bg-green-50 border-green-200 text-green-700' : 'bg-white'"
                >
                  {{ isOfflineRecoverable ? 'Offline Buffering Active' : 'Enable Offline Buffering' }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab 4: Motion & Accessibility -->
        <div v-if="activeTab === 'motion-accessibility'" class="space-y-6 animate-fade-in">
          <div class="bg-white rounded-2xl border border-[#ebe8de] p-6 md:p-8 shadow-sm space-y-6">
            <div>
              <h2 class="text-xl font-bold text-[#640c0e] tracking-tight flex items-center gap-2">
                <Accessibility class="h-5 w-5 text-[#640c0e]" />
                WCAG Compliance & Screen Reader Simulation
              </h2>
              <p class="text-xs text-neutral-500 mt-1">
                Verify color contrast, ARIA landmarks, screen reader vocalizations, and keyboard outlines.
              </p>
            </div>

            <!-- Color check -->
            <div class="space-y-4">
              <span class="text-[10px] uppercase font-bold text-neutral-400 font-mono tracking-wider block">Contrast Analyzer</span>
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <button
                  v-for="combo in PALETTE_COMBINATIONS"
                  :key="combo.id"
                  @click="selectedComboId = combo.id"
                  class="p-3 rounded-xl border text-left cursor-pointer transition-all flex flex-col justify-between h-24"
                  :style="{ backgroundColor: combo.bgColor, color: combo.fgColor, borderColor: selectedComboId === combo.id ? combo.fgColor : '#ebe8de' }"
                >
                  <span class="text-[11px] font-bold">{{ combo.fgName }}</span>
                  <span class="text-[9px] font-bold font-mono">{{ combo.ratio }}:1 Ratio</span>
                </button>
              </div>

              <div class="p-4 bg-[#fffdfa] border border-amber-100 rounded-xl text-xs space-y-1.5">
                <div class="flex items-center gap-2">
                  <span class="font-extrabold text-[#640c0e]">{{ selectedCombo.complianceRating }}</span>
                  <span class="font-mono text-[10px] text-neutral-500">Contrast: {{ selectedCombo.ratio }}:1</span>
                </div>
                <p class="text-[11px] text-neutral-600 leading-normal">{{ selectedCombo.notes }}</p>
              </div>
            </div>

            <!-- Screen Reader -->
            <div class="border-t border-[#ebe8de] pt-6 space-y-4">
              <span class="text-[10px] uppercase font-bold text-neutral-400 font-mono tracking-wider block">Auditory landmark test</span>
              <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                <button
                  v-for="el in SCREEN_READER_ELEMENTS"
                  :key="el.id"
                  @click="selectedSRElementId = el.id"
                  class="p-2.5 rounded-lg border text-[11px] font-bold text-center cursor-pointer"
                  :class="selectedSRElementId === el.id ? 'bg-[#640c0e] text-white border-primary' : 'bg-white hover:bg-neutral-50 text-neutral-700'"
                >
                  {{ el.name }}
                </button>
              </div>

              <div class="p-4 bg-[#fffdfa] border rounded-xl flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div class="space-y-1">
                  <code class="text-[11px] text-[#640c0e] font-bold block">{{ selectedSRElement.code }}</code>
                  <p class="text-xs text-neutral-600 italic mt-0.5">Narrates: {{ selectedSRElement.speech }}</p>
                </div>
                <button
                  @click="handleTriggerSpeechSim"
                  :disabled="isReadingAloud"
                  class="py-2 px-4 bg-neutral-900 hover:bg-[#640c0e] text-white text-xs font-bold rounded-xl transition-all inline-flex items-center gap-1.5 shrink-0 cursor-pointer disabled:opacity-50"
                >
                  <Volume2 class="h-4 w-4" />
                  <span>{{ isReadingAloud ? 'Reading aloud...' : 'Vocalize Node' }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab 5: Bug Tracker -->
        <div v-if="activeTab === 'bug-tracker'" class="space-y-6 animate-fade-in">
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white border rounded-2xl p-4 flex items-center gap-3.5 shadow-xs">
              <span class="p-3 rounded-xl bg-purple-50 text-purple-700 font-bold border border-purple-100">
                <BugIcon class="h-5 w-5" />
              </span>
              <div>
                <span class="text-[10px] text-neutral-400 font-mono font-bold uppercase block">Active Open Bugs</span>
                <span class="text-xl font-bold font-mono text-neutral-800">
                  {{ bugs.filter(b => ['Triage', 'Backlog', 'In_Progress'].includes(b.status)).length }}
                </span>
              </div>
            </div>

            <div class="bg-white border rounded-2xl p-4 flex items-center gap-3.5 shadow-xs">
              <span class="p-3 rounded-xl bg-accent-gold/20 text-primary font-bold border border-accent-gold/30">
                <AlertOctagon class="h-5 w-5" />
              </span>
              <div>
                <span class="text-[10px] text-neutral-400 font-mono font-bold uppercase block">Critical Blockers (S1)</span>
                <span class="text-xl font-bold font-mono text-accent-red">
                  {{ bugs.filter(b => b.severity === 'S1-Critical').length }}
                </span>
              </div>
            </div>

            <div class="bg-white border rounded-2xl p-4 flex items-center gap-3.5 shadow-xs">
              <span class="p-3 rounded-xl bg-secondary/10 text-secondary font-bold border border-secondary/20">
                <CheckCircleIcon class="h-5 w-5" />
              </span>
              <div>
                <span class="text-[10px] text-neutral-400 font-mono font-bold uppercase block">Resolved / Retested</span>
                <span class="text-xl font-bold font-mono text-secondary">
                  {{ bugs.filter(b => ['Resolved', 'Retested', 'Closed'].includes(b.status)).length }}
                </span>
              </div>
            </div>

            <div class="bg-white border rounded-2xl p-4 flex items-center gap-3.5 shadow-xs">
              <span class="p-3 rounded-xl bg-primary/5 text-blue-700 font-bold border border-primary/15">
                <Clock class="h-5 w-5" />
              </span>
              <div>
                <span class="text-[10px] text-neutral-400 font-mono font-bold uppercase block">Total Discovered bugs</span>
                <span class="text-xl font-bold font-mono text-neutral-805">
                  {{ bugs.length }}
                </span>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-2xl border border-[#ebe8de] p-5 shadow-sm space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-[#ebe8de] pb-4">
              <div class="space-y-1">
                <h3 class="text-base font-extrabold text-[#640c0e] flex items-center gap-2">
                  <Sparkles class="h-4.5 w-4.5 text-[#640c0e]" />
                  QA Bug Logging Stream
                </h3>
                <p class="text-[11px] text-neutral-500">
                  Search, filter, track severity logs, edit system workflow parameters, and trigger custom fixed simulations.
                </p>
              </div>

              <div class="flex flex-wrap gap-2">
                <button
                  @click="showAddModal = true"
                  class="px-3.5 py-1.5 bg-[#640c0e] hover:bg-[#b02e32] text-white text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-1.5 cursor-pointer border-0"
                >
                  <Plus class="h-4 w-4" />
                  Log Quality Bug
                </button>
                <button
                  @click="exportBugsReport"
                  class="px-3 py-1.5 bg-white hover:bg-neutral-50 border border-neutral-ivory text-neutral-700 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5 cursor-pointer"
                >
                  <FileSpreadsheet class="h-4 w-4" />
                  Export Register (.md)
                </button>
              </div>
            </div>

            <!-- Filters -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 bg-[#fffbf4] border border-[#ebe8de] p-3 rounded-xl">
              <div class="relative">
                <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-neutral-400" />
                <input
                  type="text"
                  placeholder="Search bugs..."
                  v-model="searchTerm"
                  class="w-full pl-8 pr-3 py-1.5 bg-white border border-[#ebe8de] rounded-lg text-xs font-medium text-neutral-800 placeholder-neutral-405 focus:outline-[#640c0e]"
                />
              </div>

              <select v-model="filterCategory" class="px-2 py-1.5 bg-white border border-[#ebe8de] rounded-lg text-xs font-bold text-neutral-700 focus:outline-[#640c0e]">
                <option value="All">All Categories</option>
                <option value="UI/UX">UI/UX Issues</option>
                <option value="Functional">Functional Issues</option>
                <option value="Accessibility">Accessibility Issues</option>
                <option value="Performance">Performance Issues</option>
                <option value="Pedagogical">Pedagogical Issues</option>
              </select>

              <select v-model="filterSeverity" class="px-2 py-1.5 bg-white border border-[#ebe8de] rounded-lg text-xs font-bold text-neutral-700 focus:outline-[#640c0e]">
                <option value="All">All Severities</option>
                <option value="S1-Critical">S1 - Critical Blocker</option>
                <option value="S2-Major">S2 - Major Bug</option>
                <option value="S3-Normal">S3 - Normal Bug</option>
                <option value="S4-Trivial">S4 - Trivial Polish</option>
              </select>

              <select v-model="filterStatus" class="px-2 py-1.5 bg-white border border-[#ebe8de] rounded-lg text-xs font-bold text-neutral-700 focus:outline-[#640c0e]">
                <option value="All">All Statuses</option>
                <option value="Triage">Triage</option>
                <option value="Backlog">Backlog</option>
                <option value="In_Progress">In Progress</option>
                <option value="Resolved">Resolved</option>
                <option value="Retested">Retested</option>
                <option value="Closed">Closed</option>
              </select>
            </div>

            <!-- Master/Details splits -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 pt-2">
              <div class="lg:col-span-5 border border-[#ebe8de] rounded-xl overflow-hidden flex flex-col max-h-[480px]">
                <span class="text-[10px] font-mono font-bold text-neutral-500 bg-neutral-50 border-b p-2 block">
                  Active Stream ({{ filteredBugs.length }} entries matching)
                </span>

                <div class="flex-1 overflow-y-auto divide-y divide-[#ebe8de] bg-white">
                  <div v-if="filteredBugs.length === 0" class="p-8 text-center text-neutral-405 italic text-xs">
                    No issues found matching search filter parameters.
                  </div>
                  <div
                    v-else
                    v-for="bug in filteredBugs"
                    :key="bug.id"
                    @click="selectedBugId = bug.id"
                    class="p-3.5 transition-all cursor-pointer text-left border-l-4"
                    :class="selectedBugId === bug.id ? 'bg-[#640c0e]/5 border-l-primary' : 'hover:bg-neutral-50/50 border-l-transparent'"
                  >
                    <div class="flex items-start justify-between gap-1 mb-1.5">
                      <span class="text-[10px] font-mono text-[#640c0e] font-bold shrink-0">{{ bug.id }}</span>
                      <div class="flex gap-1">
                        <span class="text-[9px] px-1.5 py-0.5 font-bold uppercase tracking-wider rounded border" :class="getSeverityColor(bug.severity)">
                          {{ bug.severity.split('-')[1] }}
                        </span>
                        <span class="text-[9px] px-1.5 py-0.5 font-bold uppercase tracking-wider rounded border" :class="getStatusColor(bug.status)">
                          {{ bug.status.replace('_', ' ') }}
                        </span>
                      </div>
                    </div>
                    <h4 class="text-xs font-extrabold text-neutral-800 line-clamp-1 mb-1">{{ bug.title }}</h4>
                    <p class="text-[11px] text-neutral-500 line-clamp-2 leading-relaxed">{{ bug.description }}</p>
                  </div>
                </div>
              </div>

              <!-- Details side -->
              <div class="lg:col-span-7 border border-[#ebe8de] rounded-2xl bg-[#fffbf4]/30 p-5 flex flex-col justify-between max-h-[520px] overflow-y-auto">
                <div v-if="selectedBug" class="space-y-4 text-left">
                  <div class="border-b pb-3 border-[#ebe8de] space-y-1.5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                      <div class="flex items-center gap-1.5">
                        <span class="text-xs font-mono bg-neutral-900 text-white px-2 py-0.5 rounded font-bold">{{ selectedBug.id }}</span>
                        <span class="text-xs text-neutral-500 font-medium">Logged on {{ selectedBug.dateLogged }}</span>
                      </div>
                      <div class="flex items-center gap-1.5">
                        <span class="text-[11px] font-bold text-neutral-500 font-mono">Workflow Status:</span>
                        <select
                          :value="selectedBug.status"
                          @change="updateBugStatus(selectedBug.id, ($event.target as HTMLSelectElement).value as any)"
                          class="bg-white border text-xs font-bold px-2 py-0.5 rounded-lg focus:outline-[#640c0e]"
                        >
                          <option value="Triage">Triage</option>
                          <option value="Backlog">Backlog</option>
                          <option value="In_Progress">In Progress</option>
                          <option value="Resolved">Resolved</option>
                          <option value="Retested">Retested</option>
                          <option value="Closed">Closed</option>
                        </select>
                      </div>
                    </div>
                    <h3 class="text-base font-extrabold text-neutral-800 leading-snug">{{ selectedBug.title }}</h3>
                  </div>

                  <div class="grid grid-cols-2 gap-4 text-xs">
                    <div class="bg-white p-2.5 rounded-lg border">
                      <span class="text-[9px] uppercase font-bold text-neutral-400 font-mono block mb-0.5">Categorization</span>
                      <span class="font-extrabold text-[#640c0e]">{{ selectedBug.category }} QA Audit Scope</span>
                    </div>
                    <div class="bg-white p-2.5 rounded-lg border">
                      <span class="text-[9px] uppercase font-bold text-neutral-400 font-mono block mb-0.5">Critical Priority</span>
                      <span class="font-extrabold text-neutral-800">{{ selectedBug.severity }}</span>
                    </div>
                  </div>

                  <div class="space-y-1">
                    <span class="text-[10px] uppercase font-bold text-neutral-400 font-mono block select-none">Issue Description</span>
                    <div class="bg-white p-3.5 rounded-xl border text-xs text-neutral-700 leading-relaxed">{{ selectedBug.description }}</div>
                  </div>

                  <div v-if="selectedBug.suggestedFix" class="space-y-1 bg-neutral-900 text-white p-3.5 rounded-xl border relative font-mono text-[10px] leading-relaxed">
                    <span class="text-[8px] uppercase font-bold text-neutral-400 block mb-1">Proposed Code Hotfix mitigation</span>
                    <p class="font-normal text-neutral-muted whitespace-pre-wrap">{{ selectedBug.suggestedFix }}</p>
                  </div>

                  <div class="flex flex-wrap items-center justify-between border-t border-[#ebe8de] pt-3 text-[10px] text-neutral-500 font-mono">
                    <span>Scope: <strong class="text-neutral-700 font-bold">{{ selectedBug.pageUrl }}</strong></span>
                    <button @click="deleteBug(selectedBug.id)" class="p-1.5 hover:bg-accent-red/10 hover:text-accent-red rounded transition-colors text-rose-500 shrink-0 inline-flex items-center gap-1 cursor-pointer border-0 bg-transparent">
                      <Trash2 class="h-3.5 w-3.5" />
                      <span>Archive Bug</span>
                    </button>
                  </div>
                </div>
                <div v-else class="p-12 text-center text-neutral-400 space-y-2">
                  <BugIcon class="h-12 w-12 text-neutral-300 mx-auto" />
                  <p class="text-xs italic leading-relaxed">Select a logged bug on the left to review details.</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab 6: Production Readiness -->
        <div v-if="activeTab === 'production-readiness'" class="space-y-6 animate-fade-in">
          <div class="bg-white rounded-2xl border border-[#ebe8de] p-6 shadow-sm">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-neutral-100">
              <div class="space-y-1">
                <span class="bg-[#640c0e]/10 text-[#640c0e] text-[10px] font-mono font-bold px-3 py-1 rounded-full uppercase tracking-wider border border-[#640c0e]/15">
                  Academy Pre-Launch Assessment
                </span>
                <h2 class="text-xl font-bold text-[#640c0e] tracking-tight flex items-center gap-2">
                  <ShieldAlert class="h-5 w-5" />
                  LMS Production Readiness Report
                </h2>
                <p class="text-xs text-neutral-500">
                  A comprehensive health audit mapping visual quality, accessibility compliance boundaries, responsiveness traps, and structural maintainabilities.
                </p>
              </div>

              <div class="flex items-center gap-4 bg-[#fffbf4] border border-[#ebe8de] p-4 rounded-2xl shrink-0">
                <div class="text-center">
                  <span class="text-[10px] uppercase font-bold text-neutral-400 font-mono tracking-wider block">Startup Health index</span>
                  <div class="text-2xl font-black font-mono text-[#640c0e] mt-0.5">
                    {{ Math.round(85 + (progressPercent / 15)) }}%
                  </div>
                </div>
              </div>
            </div>

            <!-- Optimization checklist -->
            <div class="pt-6 space-y-4">
              <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-primary">Pre-Launch Optimization Tasks</h3>
                <span class="text-xs font-mono font-bold text-primary">Completed: {{ checklist.filter(c => c.completed).length }} / {{ checklist.length }}</span>
              </div>
              <div class="grid gap-3 sm:grid-cols-2 text-xs">
                <div
                  v-for="item in checklist"
                  :key="item.id"
                  @click="toggleChecklistVal(item.id)"
                  class="flex items-start gap-3 p-3 bg-white border border-[#ebe8de] rounded-xl cursor-pointer hover:bg-neutral-50 transition-colors select-none"
                >
                  <CheckCircle2
                    class="h-4.5 w-4.5 shrink-0 mt-0.5 transition-colors"
                    :class="item.completed ? 'text-green-650' : 'text-neutral-350'"
                  />
                  <div>
                    <span class="text-[9px] uppercase font-bold text-neutral-400 font-mono tracking-wider block">{{ item.category }}</span>
                    <p class="text-[11px] font-medium text-neutral-700 leading-snug mt-0.5">{{ item.task }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab 7: Tester Personas -->
        <div v-if="activeTab === 'tester-personas'" class="space-y-6 animate-fade-in">
          <div class="bg-white rounded-2xl border border-[#ebe8de] p-6 shadow-sm">
            <div class="space-y-1 mb-6">
              <h2 class="text-xl font-bold text-[#640c0e] tracking-tight flex items-center gap-2">
                <UserCheck class="h-5 w-5" />
                UX Research: Internal Tester Personas
              </h2>
              <p class="text-xs text-neutral-500">
                A real-world UX study depicting volunteer behaviors, realistic educational concerns, and critical accessibility preferences across the SFU Academic LMS platform.
              </p>
            </div>

            <div class="flex flex-wrap gap-2.5 pb-4 border-b border-[#ebe8de] mb-6">
              <button
                v-for="p in MOCK_PERSONAS"
                :key="p.id"
                @click="activePersonaId = p.id"
                class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold border transition-all cursor-pointer"
                :class="activePersonaId === p.id ? 'bg-[#640c0e] text-white border-primary shadow-md' : 'bg-white text-neutral-600 border-[#ebe8de]'"
              >
                <span class="text-base">{{ p.avatar }}</span>
                <span>{{ p.name }}</span>
              </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
              <div class="lg:col-span-4 bg-[#fffbf4] border border-[#ebe8de] p-6 rounded-2xl flex flex-col justify-between space-y-4">
                <div class="space-y-4">
                  <div class="flex items-center gap-4">
                    <span class="text-4xl bg-white p-3 rounded-2xl shadow-xs border inline-block">{{ activePersona.avatar }}</span>
                    <div>
                      <h3 class="text-base font-extrabold text-[#640c0e]">{{ activePersona.name }}</h3>
                      <p class="text-xs text-neutral-500">{{ activePersona.role }} • Age {{ activePersona.age }}</p>
                    </div>
                  </div>

                  <div class="bg-white p-4 rounded-xl border border-[#ebe8de] relative italic text-xs leading-relaxed text-neutral-700">
                    "{{ activePersona.quote }}"
                  </div>
                </div>
              </div>

              <div class="lg:col-span-8 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                  <div class="border border-secondary/20 bg-secondary/10/10 p-5 rounded-2xl space-y-2.5 text-xs">
                    <h4 class="font-bold text-primary uppercase font-mono tracking-wider">Testing Goals</h4>
                    <li v-for="(g, i) in activePersona.testingGoals" :key="i" class="list-none pl-4 relative leading-relaxed">
                      <span class="absolute left-0 top-1.5 w-1.5 h-1.5 bg-secondary rounded-full"></span>
                      {{ g }}
                    </li>
                  </div>

                  <div class="border border-rose-100 bg-accent-red/10/10 p-5 rounded-2xl space-y-2.5 text-xs">
                    <h4 class="font-bold text-rose-800 uppercase font-mono tracking-wider">Likely Frustrations</h4>
                    <li v-for="(g, i) in activePersona.likelyFrustrations" :key="i" class="list-none pl-4 relative leading-relaxed">
                      <span class="absolute left-0 top-1.5 w-1.5 h-1.5 bg-accent-red/100 rounded-full"></span>
                      {{ g }}
                    </li>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab 8: Launch Clearance -->
        <div v-if="activeTab === 'internal-review'" class="space-y-6 animate-fade-in">
          <div class="bg-white rounded-2xl border border-[#ebe8de] p-6 shadow-sm space-y-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-[#neutral-100]">
              <div class="space-y-1">
                <span class="bg-[#640c0e]/10 text-[#640c0e] text-[10px] font-mono font-bold px-3 py-1 rounded-full uppercase tracking-wider border border-[#640c0e]/15">
                  Clearance Board
                </span>
                <h2 class="text-xl font-bold text-[#640c0e] tracking-tight flex items-center gap-2">
                  <FileCheck2 class="h-5 w-5" />
                  Launch Beta Clearance & Review
                </h2>
                <p class="text-xs text-neutral-500">
                  Verify specific issues and sign off to mark the LMS beta cleared for public launching.
                </p>
              </div>

              <div class="flex items-center gap-4 bg-[#fffbf4] border border-[#ebe8de] p-4 rounded-2xl shrink-0">
                <div>
                  <span class="text-[10px] uppercase font-bold text-neutral-400 font-mono tracking-wider block">Clearance status</span>
                  <span
                    class="text-xs font-bold font-mono px-3 py-1 rounded-full mt-1.5 inline-block"
                    :class="clearanceIndex >= 100 ? 'bg-secondary/10 text-secondary border border-secondary/20' : 'bg-accent-red/10 text-accent-red border border-accent-red/20 animate-pulse'"
                  >
                    {{ clearanceIndex >= 100 ? '✓ PASSED FOR LAUNCH' : '⚠ WAITING CLEARANCE' }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Issues list -->
            <div class="space-y-4">
              <div
                v-for="iss in filteredReviewIssues"
                :key="iss.id"
                class="border p-4.5 rounded-xl flex flex-col md:flex-row md:items-center justify-between gap-5 text-xs text-left"
                :class="iss.status === 'Approved' ? 'bg-secondary/10/10 border-secondary/20' : 'bg-white border-[#ebe8de]'"
              >
                <div class="space-y-2">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="bg-neutral-900 text-white font-mono px-2 py-0.5 rounded text-[9px]">{{ iss.id }}</span>
                    <span class="font-bold text-neutral-700 font-mono capitalize">{{ iss.category }}</span>
                    <span class="text-[9px] px-1.5 py-0.5 font-bold rounded border" :class="getSevBadgeColor(iss.severity)">
                      {{ iss.severity }}
                    </span>
                  </div>
                  <h4 class="text-xs font-black text-neutral-800">{{ iss.title }}</h4>
                  <p class="text-[11px] text-neutral-600"><strong>Observation:</strong> {{ iss.observation }}</p>
                </div>

                <button
                  @click="toggleIssueApproval(iss.id)"
                  class="py-2 px-4 text-xs font-extrabold rounded-xl transition-all cursor-pointer shrink-0 border-0"
                  :class="iss.status === 'Approved' ? 'bg-neutral-100 text-neutral-700' : 'bg-primary text-white hover:bg-neutral-800'"
                >
                  {{ iss.status === 'Approved' ? 'Re-open Review' : 'Clear Audit Point' }}
                </button>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Log Bug Modal wizard -->
    <div v-if="showAddModal" class="fixed inset-0 min-h-screen bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 z-50 overflow-y-auto">
      <div class="bg-white rounded-3xl border border-[#ebe8de] max-w-lg w-full p-6 space-y-4">
        <div class="flex justify-between items-center border-b pb-2">
          <h3 class="text-sm font-extrabold text-[#640c0e] font-mono">Log Quality Bug in Register</h3>
          <button @click="showAddModal = false" class="text-neutral-405 hover:text-neutral-700 border-0 bg-transparent cursor-pointer">
            <X class="h-4.5 w-4.5" />
          </button>
        </div>

        <form @submit.prevent="handleCreateBug" class="space-y-3.5 text-xs text-left">
          <label class="block space-y-1">
            <span class="font-extrabold text-neutral-700 block">Issue Title (Short Summary) *</span>
            <input type="text" required placeholder="e.g. Broken outline border" v-model="newBugTitle" class="w-full px-3 py-2 border rounded-xl focus:outline-[#640c0e]" />
          </label>

          <label class="block space-y-1">
            <span class="font-extrabold text-neutral-700 block">Description *</span>
            <textarea required rows="3" placeholder="Describe the bug..." v-model="newBugDesc" class="w-full px-3 py-2 border rounded-xl focus:outline-[#640c0e] resize-none" />
          </label>

          <div class="flex gap-2.5 pt-3">
            <button type="submit" class="flex-1 py-2 bg-primary text-white text-xs font-bold rounded-xl shadow-md cursor-pointer border-0">
              Log Bug to Register
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.3s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
