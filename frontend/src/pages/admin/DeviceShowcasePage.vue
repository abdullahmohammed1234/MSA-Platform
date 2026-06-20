<script setup lang="ts">
import { ref } from 'vue';
import {
  Laptop,
  Tablet,
  Smartphone,
  Sparkles,
  RefreshCw,
  CheckCircle2,
  Send,
  Sliders,
  Eye,
  Accessibility,
  Zap,
  RotateCcw
} from 'lucide-vue-next';

type DeviceType = 'desktop' | 'tablet' | 'mobile';
type SimulatedTab = 'dashboard' | 'courses' | 'mentor' | 'quiz';

const device = ref<DeviceType>('desktop');
const activeTab = ref<SimulatedTab>('dashboard');
const scale = ref(1);

// Accessibility Simulation States
const contrastEnhanced = ref(false);
const reducedMotionActive = ref(false);

// LMS Interactive Sandbox States
const chatMessage = ref('');
const chatHistory = ref([
  { sender: 'mentor', text: 'Welcome to the SFU MSA Dawah Academy simulation context. How may I assist your academic preparations today?' }
]);
const quizScore = ref<number | null>(null);
const selectedQuizAnswer = ref<number | null>(null);
const isTyping = ref(false);
const mockLoadingState = ref(false);
const notification = ref<string | null>(null);

const showNotification = (msg: string) => {
  notification.value = msg;
  setTimeout(() => {
    notification.value = null;
  }, 3000);
};

const handleSendMessage = () => {
  if (!chatMessage.value.trim()) return;

  const userMsg = chatMessage.value;
  chatHistory.value.push({ sender: 'user', text: userMsg });
  chatMessage.value = '';
  isTyping.value = true;

  // Simulate AI response
  setTimeout(() => {
    isTyping.value = false;
    let reply = 'I have noted your scholastic feedback. True guidance is delivered with humbleness and sound knowledge of standard literature.';
    if (userMsg.toLowerCase().includes('course') || userMsg.toLowerCase().includes('classes')) {
      reply = 'Our core curriculum consists of three essential segments: Foundation of Belief, Campus Speaking Practice, and Comparative Logic. Would you like to check the modular catalog?';
    } else if (userMsg.toLowerCase().includes('quiz') || userMsg.toLowerCase().includes('exam')) {
      reply = 'The quizzes assess your understanding of student counseling and interpersonal skills under pressure. Always read the reflective scenario prompts before confirming.';
    }
    chatHistory.value.push({ sender: 'mentor', text: reply });
  }, 1200);
};

const handleResetSandbox = () => {
  chatHistory.value = [
    { sender: 'mentor', text: 'Welcome to the SFU MSA Dawah Academy simulation context. How may I assist your academic preparations today?' }
  ];
  selectedQuizAnswer.value = null;
  quizScore.value = null;
  mockLoadingState.value = false;
  showNotification('Device Simulator Recalibrated');
};

const triggerMockReload = () => {
  mockLoadingState.value = true;
  setTimeout(() => {
    mockLoadingState.value = false;
  }, 1500);
};
</script>

<template>
  <div class="min-h-screen bg-[#FDFCF8] py-8 px-4 md:px-8 max-w-7xl mx-auto font-sans relative text-left">
    
    <!-- Page Header -->
    <div class="border-b border-stone-200 pb-6 mb-8">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="space-y-1">
          <div class="flex items-center gap-2">
            <span class="p-1.5 bg-[#640c0e]/10 rounded-lg text-[#640c0e] flex items-center justify-center">
              <Sparkles class="h-4 w-4" />
            </span>
            <span class="text-[10px] font-bold text-[#b02e32] uppercase tracking-[0.2em] font-mono leading-none">
              Interactive Presentation Deck
            </span>
          </div>
          <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-stone-900 font-display">
            Multi-Device Showcase & Accessibility Workspace
          </h1>
          <p class="text-xs text-stone-600 max-w-3xl">
            Evaluate and demonstrate the student academy experience across viewport densities. Test keyboard navigation, auditory screen readers, fluid structures, and rapid network simulators instantly.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
          <button
            @click="handleResetSandbox"
            class="px-4 py-2 border border-stone-200 hover:bg-stone-50 rounded-xl text-xs font-bold text-stone-700 flex items-center gap-1.5 cursor-pointer min-h-[44px] bg-white"
          >
            <RotateCcw class="h-3.5 w-3.5" />
            <span>Reset Sandbox</span>
          </button>
          <button
            @click="triggerMockReload"
            class="px-4 py-2 bg-[#640c0e] hover:bg-[#b02e32] text-white rounded-xl text-xs font-bold flex items-center gap-1.5 cursor-pointer min-h-[44px] border-0"
          >
            <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': mockLoadingState }" />
            <span>Trigger Loading Skeleton</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Primary Layout Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
      
      <!-- Left Controls Panel -->
      <div class="lg:col-span-4 space-y-6">
        
        <!-- Showcase Controller -->
        <div class="bg-white rounded-2xl p-5 border border-stone-200 shadow-xs space-y-4">
          <div class="flex items-center justify-between border-b border-stone-100 pb-3">
            <h2 class="text-sm font-bold text-stone-900 flex items-center gap-2">
              <Sliders class="h-4 w-4 text-[#640c0e]" />
              Showcase Controller
            </h2>
            <span class="text-[10px] bg-secondary/10 text-primary border border-secondary/20 px-2 py-0.5 rounded-full font-bold">
              Live Simulator
            </span>
          </div>

          <!-- Device Selector -->
          <div class="space-y-2">
            <label class="text-[10px] font-bold text-stone-500 uppercase tracking-wider block">Viewport Frame</label>
            <div class="grid grid-cols-3 gap-1.5">
              <button
                v-for="d in ['desktop', 'tablet', 'mobile'] as DeviceType[]"
                :key="d"
                @click="() => { device = d; showNotification(`Viewport: ${d} Frame`); }"
                class="flex flex-col items-center justify-center py-2.5 px-1.5 rounded-xl border transition-all cursor-pointer gap-1 min-h-[44px] bg-white"
                :class="device === d ? 'border-[#640c0e] bg-[#640c0e]/5 text-[#640c0e] font-bold' : 'border-stone-200 text-stone-650'"
              >
                <component :is="d === 'desktop' ? Laptop : d === 'tablet' ? Tablet : Smartphone" class="h-4 w-4" />
                <span class="text-[10px] capitalize">{{ d }}</span>
              </button>
            </div>
          </div>

          <!-- Viewport Scale -->
          <div class="space-y-2">
            <div class="flex justify-between items-center text-[10px] font-bold text-stone-500 uppercase tracking-wider">
              <span>Viewport Scale</span>
              <span class="font-mono text-[#640c0e]">{{ Math.round(scale * 100) }}%</span>
            </div>
            <input
              type="range"
              min="0.75"
              max="1.2"
              step="0.05"
              v-model.number="scale"
              class="w-full accent-[#640c0e] cursor-pointer"
            />
          </div>
        </div>

        <!-- Accessibility Toggles -->
        <div class="bg-white rounded-2xl p-5 border border-stone-200 shadow-xs space-y-4">
          <h3 class="text-xs font-bold text-stone-900 border-b border-stone-100 pb-2 flex items-center gap-1.5">
            <Accessibility class="h-4 w-4 text-[#b02e32]" />
            Accessibility Toggles
          </h3>
          
          <div class="space-y-3">
            <!-- Contrast -->
            <button
              @click="() => { contrastEnhanced = !contrastEnhanced; showNotification(contrastEnhanced ? 'Booster contrast active' : 'Standard Contrast Restored'); }"
              class="w-full flex items-center justify-between p-3 rounded-xl border text-left cursor-pointer transition-colors min-h-[44px] bg-white border-stone-200"
              :class="{ 'bg-[#640c0e]/5 border-[#640c0e]/20': contrastEnhanced }"
            >
              <div class="flex items-center gap-2">
                <Eye class="h-4 w-4 text-[#640c0e]" />
                <div class="leading-tight">
                  <span class="text-xs font-bold text-stone-800 block">Contrast Booster</span>
                  <span class="text-[9px] text-stone-500">Elevate contrast ratios under direct sunlight</span>
                </div>
              </div>
            </button>

            <!-- Reduced Motion -->
            <button
              @click="() => { reducedMotionActive = !reducedMotionActive; showNotification(reducedMotionActive ? 'Animations Dampened' : 'Animations Restored'); }"
              class="w-full flex items-center justify-between p-3 rounded-xl border text-left cursor-pointer transition-colors min-h-[44px] bg-white border-stone-200"
              :class="{ 'bg-[#640c0e]/5 border-[#640c0e]/20': reducedMotionActive }"
            >
              <div class="flex items-center gap-2">
                <Zap class="h-4 w-4 text-[#640c0e]" />
                <div class="leading-tight">
                  <span class="text-xs font-bold text-stone-800 block">Dampen Transitions</span>
                  <span class="text-[9px] text-stone-500">Enable clean fade transitions (reduced motion)</span>
                </div>
              </div>
            </button>
          </div>
        </div>
      </div>

      <!-- Right Viewport Simulator Area -->
      <div class="lg:col-span-8 flex flex-col items-center justify-center w-full">
        <div class="w-full flex justify-between items-center bg-stone-50 border border-b-0 border-stone-200 rounded-t-2xl p-3 px-5 text-xs text-stone-600 tracking-tight font-medium">
          <div class="flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-red-400"></span>
            <span class="h-2 w-2 rounded-full bg-yellow-400"></span>
            <span class="h-2 w-2 rounded-full bg-green-400"></span>
            <span class="font-mono text-[10.5px] text-stone-400 ml-2">https://portal.sfumsa.org/academy/showcase</span>
          </div>
          
          <span class="uppercase text-[9.5px] tracking-wider bg-stone-100 px-2 py-0.5 border border-stone-200 rounded font-bold font-mono">
            {{ device === 'desktop' ? 'Laptop Container: 100%' : device === 'tablet' ? 'Tablet Screen: 768px' : 'Mobile Viewport: 390px' }}
          </span>
        </div>

        <!-- Simulator Bounding Box -->
        <div class="w-full border border-stone-200 bg-stone-100 p-8 flex items-center justify-center overflow-auto min-h-[620px] rounded-b-2xl">
          <div
            :style="{
              transform: `scale(${scale})`,
              transition: reducedMotionActive ? 'none' : 'all 350ms cubic-bezier(0.16, 1, 0.3, 1)',
              width: device === 'desktop' ? '100%' : device === 'tablet' ? '768px' : '390px',
              maxWidth: '100%'
            }"
            class="bg-[#FDFCF8] rounded-2xl shadow-2xl relative border overflow-hidden flex flex-col"
            :class="[
              device === 'mobile' ? 'h-[640px]' : 'h-[540px]',
              contrastEnhanced ? 'border-stone-900 contrast-125 saturate-120' : 'border-stone-200'
            ]"
          >
            <!-- Header inside the app simulator -->
            <div class="sticky top-0 z-30 bg-[#640c0e] text-[#FDFCF8] px-4 py-3 flex items-center justify-between shadow-md">
              <div class="flex items-center gap-2">
                <span class="h-6 w-6 rounded bg-white/10 flex items-center justify-center font-display font-bold text-xs text-white">
                  الْأَ
                </span>
                <div class="leading-none text-left">
                  <span class="text-[8px] font-mono tracking-wider opacity-70 block">STUDENT PORTAL</span>
                  <span class="text-xs font-bold font-display tracking-tight block">Dawah Academy</span>
                </div>
              </div>

              <!-- Inner tabs -->
              <div class="flex items-center gap-1.5">
                <button
                  v-for="t in ['dashboard', 'courses', 'mentor', 'quiz'] as SimulatedTab[]"
                  :key="t"
                  @click="() => { activeTab = t; if (t === 'courses') triggerMockReload(); }"
                  class="px-2 py-1 rounded-md text-[10px] font-bold transition-all min-h-[32px] cursor-pointer text-white border-0 bg-transparent"
                  :class="activeTab === t ? 'bg-white/20' : 'opacity-75 hover:bg-white/5'"
                >
                  <span class="capitalize">{{ t === 'mentor' ? 'Chat' : t }}</span>
                </button>
              </div>
            </div>

            <!-- Scrollable Simulator Content -->
            <div class="flex-1 overflow-y-auto p-4 md:p-6 space-y-4">
              <!-- Loading Skeleton -->
              <div v-if="mockLoadingState" class="space-y-4 text-left">
                <div class="w-full bg-white rounded-xl p-4 border border-stone-200 shadow-sm flex items-center gap-3.5 animate-pulse">
                  <div class="h-9 w-9 rounded-full bg-stone-200"></div>
                  <div class="grow space-y-2">
                    <div class="h-3 w-1/3 bg-stone-200 rounded"></div>
                    <div class="h-2 w-1/4 bg-stone-200 rounded"></div>
                  </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                  <div v-for="n in 2" :key="n" class="bg-white rounded-2xl p-5 border border-stone-100 flex flex-col space-y-3 shadow-xs animate-pulse">
                    <div class="w-full aspect-video bg-stone-200 rounded-xl"></div>
                    <div class="h-4 w-3/4 bg-stone-200 rounded"></div>
                    <div class="h-3 w-1/2 bg-stone-200 rounded"></div>
                  </div>
                </div>
              </div>

              <!-- Real views -->
              <div v-else>
                <!-- Dashboard View -->
                <div v-if="activeTab === 'dashboard'" class="space-y-4 text-left">
                  <div class="bg-[#640c0e]/5 border border-[#640c0e]/10 rounded-2xl p-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
                    <div class="space-y-1">
                      <span class="text-[10px] text-amber-800 font-mono tracking-wider font-bold uppercase block">Welcome, student@sfu.ca</span>
                      <h4 class="text-base font-bold text-stone-850">Your Sincerity Streak is Active!</h4>
                      <p class="text-[11px] text-stone-600 leading-relaxed">Earned +20 Ikhlas points in your recent campus conversations. Sincerity ensures your counseling hits the hearts of the students.</p>
                    </div>
                  </div>

                  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div v-for="st in [
                      { label: 'Dawah Catalog', val: '3 Active' },
                      { label: 'Completed Steps', val: '12 Lessons' },
                      { label: 'Reflective Quizzes', val: '84% Avg' },
                      { label: 'Ikhlas Score', val: '+220 Pts' }
                    ]" :key="st.label" class="p-3 rounded-xl border border-stone-200 bg-white flex flex-col justify-between min-h-[75px]">
                      <span class="text-[9px] text-stone-500 uppercase tracking-wider font-bold leading-none">{{ st.label }}</span>
                      <span class="text-sm font-black text-stone-850 mt-1">{{ st.val }}</span>
                    </div>
                  </div>
                </div>

                <!-- Courses View -->
                <div v-if="activeTab === 'courses'" class="space-y-4 text-left">
                  <div class="bg-white rounded-2xl p-4 border border-stone-200 shadow-xs flex flex-col space-y-3" v-for="c in [
                    { title: 'Introduction to Campus Counseling', level: 'Fundamental', progress: 65 },
                    { title: 'Sincerity vs Scholastic Showing-off', level: 'Intermediate', progress: 20 }
                  ]" :key="c.title">
                    <div class="space-y-1">
                      <span class="text-[10px] text-stone-500 block font-mono">{{ c.level }}</span>
                      <h5 class="text-xs font-bold text-stone-900 leading-snug">{{ c.title }}</h5>
                    </div>
                    <div class="w-full bg-stone-100 rounded-full h-1.5">
                      <div class="bg-[#640c0e] h-1.5 rounded-full" :style="{ width: `${c.progress}%` }"></div>
                    </div>
                  </div>
                </div>

                <!-- Chat View -->
                <div v-if="activeTab === 'mentor'" class="flex flex-col space-y-3 text-left">
                  <div class="bg-white border border-stone-200 rounded-xl p-3 flex flex-col space-y-2 h-[260px] overflow-y-auto">
                    <div
                      v-for="(chat, idx) in chatHistory"
                      :key="idx"
                      class="p-2.5 rounded-xl max-w-[85%] text-xs leading-relaxed"
                      :class="chat.sender === 'user'
                        ? 'bg-stone-900 text-white rounded-br-none ml-auto'
                        : 'bg-[#fffbf4] border border-[#ebe8de] text-stone-800 rounded-bl-none mr-auto'"
                    >
                      {{ chat.text }}
                    </div>
                  </div>

                  <form @submit.prevent="handleSendMessage" class="flex gap-1.5">
                    <input
                      type="text"
                      placeholder="Type a campus question..."
                      v-model="chatMessage"
                      class="flex-1 px-3 py-2 bg-stone-50 text-xs border border-stone-200 rounded-xl focus:outline-none focus:border-[#640c0e] min-h-[44px]"
                    />
                    <button
                      type="submit"
                      class="bg-[#640c0e] text-white p-2.5 rounded-xl flex items-center justify-center shrink-0 min-h-[44px] min-w-[44px] border-0 cursor-pointer"
                    >
                      <Send class="h-4 w-4" />
                    </button>
                  </form>
                </div>

                <!-- Quiz View -->
                <div v-if="activeTab === 'quiz'" class="space-y-4 text-left">
                  <div class="bg-stone-950 text-white rounded-2xl p-4 space-y-2">
                    <span class="text-[9.5px] uppercase font-mono tracking-widest text-accent-gold font-bold block">Scenario Roster Exam</span>
                    <h6 class="text-xs md:text-sm font-bold leading-snug">
                      How should you counsel a student who claims peer discussions are causing their focus to drop?
                    </h6>
                  </div>

                  <div class="space-y-2">
                    <button
                      v-for="(opt, idx) in [
                        'Speak loudly with advanced logical arguments to settle the dispute instantly.',
                        'Invite them for a quiet, humble walking coffee, listen deeply, and avoid judging.'
                      ]"
                      :key="idx"
                      @click="selectedQuizAnswer = idx"
                      class="w-full text-left p-3.5 rounded-xl border transition-all text-xs flex gap-2 items-center cursor-pointer min-h-[50px] bg-white"
                      :class="selectedQuizAnswer === idx ? 'border-[#640c0e] bg-[#640c0e]/5 text-[#640c0e] font-semibold' : 'border-stone-200 text-stone-700'"
                    >
                      <span class="h-5 w-5 rounded-full border flex items-center justify-center shrink-0 font-bold font-mono text-[10px]">
                        {{ idx === 0 ? 'A' : 'B' }}
                      </span>
                      <span>{{ opt }}</span>
                    </button>
                  </div>

                  <button
                    @click="() => { quizScore = selectedQuizAnswer === 1 ? 100 : 0; }"
                    :disabled="selectedQuizAnswer === null"
                    class="w-full bg-stone-900 border-0 disabled:opacity-40 text-white min-h-[44px] py-2.5 rounded-xl text-xs font-bold transition-all shadow-subtle cursor-pointer"
                  >
                    Submit Quiz Answer
                  </button>

                  <div
                    v-if="quizScore !== null"
                    class="rounded-xl p-3 text-xs leading-relaxed flex items-start gap-2.5 border"
                    :class="quizScore === 100 ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'"
                  >
                    <CheckCircle2 class="h-4 w-4 shrink-0 mt-0.5" />
                    <div>
                      <span class="font-bold block">{{ quizScore === 100 ? 'Sanad Approved! Score: 100%' : 'Incorrect Answer. Score: 0%' }}</span>
                      <span>{{ quizScore === 100 ? 'Your action embodies true humility (Ikhlas). Quiet counseling and listening build a sanctuary of belief.' : 'Advanced logical arguments often escalate conflict instead of fixing belief. Sincerity involves listening before proving scholarly points.' }}</span>
                    </div>
                  </div>
                </div>

              </div>
            </div>

            <!-- Bottom simulated mobile status bar -->
            <div class="sticky bottom-0 bg-[#FDFCF8] border-t border-stone-200 px-4 py-2 flex items-center justify-between text-[9px] font-mono text-stone-400 select-none">
              <span>SFU MSA © 2026</span>
              <span class="text-[#640c0e]">Live Sandbox Mode</span>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Toast -->
    <div v-if="notification" class="fixed bottom-6 right-6 z-[300] bg-stone-900 border border-stone-850 text-white rounded-xl px-4 py-3 text-xs font-bold shadow-2xl flex items-center gap-2">
      <span class="h-1.5 w-1.5 rounded-full bg-secondary animate-pulse"></span>
      <span>{{ notification }}</span>
    </div>
  </div>
</template>

<style scoped>
.contrast-125 {
  filter: contrast(1.25);
}
.saturate-120 {
  filter: saturate(1.2);
}
</style>
