<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { 
  MessageSquare,
  AlertOctagon,
  Pin,
  CheckCircle2,
  Trash2,
  ShieldCheck,
  Search,
  Filter,
  CornerDownRight,
  Send,
  AlertTriangle,
  Loader2
} from 'lucide-vue-next';
import client from '@/services/api/client';
import {
  adminDiscussionsService,
  type ModerationThread,
} from '@/services/admin/adminDiscussionsService';
import Tabs from '@/components/navigation/tabs/Tabs.vue';
import EmptyState from '@/components/data-display/empty-state/EmptyState.vue';
import { useToastStore } from '@/components/feedback/toast';

interface MentorReply {
  author: string;
  role: string;
  text: string;
  date: string;
}

type DiscussionThread = ModerationThread & { mentorReplies?: MentorReply[] };

const toast = useToastStore();
const discussions = ref<DiscussionThread[]>([]);
const reportByThreadId = ref<Record<string, string>>({});
const loading = ref(true);
const activeTab = ref<'flagged' | 'all'>('flagged');
const searchQuery = ref('');
const selectedCourse = ref('All');
const replyText = ref('');
const commentingThreadId = ref<string | null>(null);

const tabOptions = computed(() => [
  { id: 'flagged', label: 'Reported Queue', badge: stats.value.flaggedCount },
  { id: 'all', label: 'All Discussions', badge: stats.value.allCount },
]);

const loadDiscussions = async () => {
  loading.value = true;
  try {
    const [threads, reports] = await Promise.all([
      adminDiscussionsService.getThreads(),
      adminDiscussionsService.getReports('open'),
    ]);

    const reportMap: Record<string, string> = {};
    reports.forEach((report) => {
      if (report.thread?.id) {
        reportMap[report.thread.id] = report.id;
      }
    });
    reportByThreadId.value = reportMap;

    discussions.value = threads.map((thread) => ({
      ...thread,
      authorAvatar: thread.authorAvatar || `https://api.dicebear.com/7.x/avataaars/svg?seed=${encodeURIComponent(thread.authorName)}`,
      mentorReplies: thread.mentorReplies ?? [],
    }));
  } catch (err: any) {
    toast.error(err.message || 'Failed to load moderation queue.');
    discussions.value = [];
  } finally {
    loading.value = false;
  }
};

onMounted(loadDiscussions);

const handleApproveThread = async (threadId: string) => {
  const reportId = reportByThreadId.value[threadId];
  if (!reportId) {
    discussions.value = discussions.value.map((d) =>
      d.id === threadId
        ? { ...d, status: 'resolved', flaggedCount: 0, flaggedReason: undefined }
        : d
    );
    return;
  }

  try {
    await adminDiscussionsService.resolveReport(reportId, 'dismissed');
    discussions.value = discussions.value.map((d) =>
      d.id === threadId
        ? { ...d, status: 'resolved', flaggedCount: 0, flaggedReason: undefined }
        : d
    );
    delete reportByThreadId.value[threadId];
    toast.success('Report dismissed.');
  } catch (err: any) {
    toast.error(err.message || 'Failed to dismiss report.');
  }
};

const handleDeleteThread = (threadId: string) => {
  discussions.value = discussions.value.filter((d) => d.id !== threadId);
  toast.success('Thread removed from queue.');
};

const handleTogglePin = (threadId: string) => {
  discussions.value = discussions.value.map((d) =>
    d.id === threadId ? { ...d, isPinned: !d.isPinned } : d
  );
};

const handleSendMentorReply = async (threadId: string) => {
  if (!replyText.value.trim()) return;

  try {
    await client.post(`/discussions/${threadId}/posts`, { content: replyText.value });

    const newReply: MentorReply = {
      author: 'Advising Registrar Board',
      role: 'Lead Academic Mentor',
      text: replyText.value,
      date: 'Just now',
    };

    discussions.value = discussions.value.map((d) => {
      if (d.id === threadId) {
        return {
          ...d,
          repliesCount: d.repliesCount + 1,
          mentorReplies: [...(d.mentorReplies ?? []), newReply],
          status: d.status === 'flagged' ? 'resolved' : d.status,
          flaggedCount: 0,
        };
      }
      return d;
    });

    replyText.value = '';
    commentingThreadId.value = null;
    toast.success('Mentor reply posted.');
  } catch (err: any) {
    toast.error(err.message || 'Failed to post mentor reply.');
  }
};

// Computeds
const courseTitles = computed(() => {
  const raw = discussions.value.map(d => d.courseTitle);
  return ["All", ...Array.from(new Set(raw))];
});

const filteredDiscussions = computed(() => {
  return discussions.value.filter(d => {
    const matchesSearch = d.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                          d.preview.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                          d.authorName.toLowerCase().includes(searchQuery.value.toLowerCase());
    const matchesCourse = selectedCourse.value === "All" || d.courseTitle === selectedCourse.value;
    const matchesTab = activeTab.value === "all" || d.status === "flagged";
    return matchesSearch && matchesCourse && matchesTab;
  });
});

const stats = computed(() => {
  return {
    flaggedCount: discussions.value.filter(d => d.status === "flagged").length,
    allCount: discussions.value.length,
    pinnedCount: discussions.value.filter(d => d.isPinned).length
  };
});
</script>

<template>
  <div class="space-y-8 max-w-7xl mx-auto p-1 text-[#640c0e]">
    <!-- Visual Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white border border-[#ebe8de] rounded-2xl p-6 shadow-premium relative overflow-hidden">
      <div class="absolute top-0 right-0 h-24 w-24 opacity-[0.015] font-display pointer-events-none text-9xl">
        ☪
      </div>
      <div class="space-y-1">
        <h2 class="font-display font-black text-2xl text-[#640c0e] tracking-tight flex items-center gap-2">
          <MessageSquare class="h-6 w-6 text-[#ffdc83]" />
          Dialogue Moderation Registry
        </h2>
        <p class="text-sm text-neutral-muted font-sans">
          Maintain forum health, review flagged comments, and deploy official mentor responses to student dispute threads.
        </p>
      </div>
      <div class="text-[10px] sm:bg-neutral-ivory/50 border border-[#ebe8de] rounded-xl px-4 py-2 font-mono text-primary font-bold text-center">
        Moderation Index: 100% Secure
      </div>
    </div>

    <!-- Metrics Summary -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
      <div class="bg-white border border-[#ebe8de] rounded-xl p-5 shadow-premium flex items-center gap-4">
        <div class="h-11 w-11 rounded-lg bg-red-50 text-[#b02e32] flex items-center justify-center border border-red-100">
          <AlertOctagon class="h-5.5 w-5.5" />
        </div>
        <div>
          <p class="text-[10px] uppercase font-bold tracking-widest text-[#c2c4c7]">Flagged Threads</p>
          <strong class="text-xl font-display font-black text-[#640c0e] mt-0.5 block">
            {{ stats.flaggedCount }} Warnings
          </strong>
        </div>
      </div>

      <div class="bg-white border border-[#ebe8de] rounded-xl p-5 shadow-premium flex items-center gap-4">
        <div class="h-11 w-11 rounded-lg bg-accent-gold/20 text-[#ffdc83] flex items-center justify-center border border-accent-gold/30">
          <Pin class="h-5.5 w-5.5" />
        </div>
        <div>
          <p class="text-[10px] uppercase font-bold tracking-widest text-[#c2c4c7]">Pinned Dialogues</p>
          <strong class="text-xl font-display font-black text-[#640c0e] mt-0.5 block">
            {{ stats.pinnedCount }} Active
          </strong>
        </div>
      </div>

      <div class="bg-white border border-[#ebe8de] rounded-xl p-5 shadow-premium flex items-center gap-4">
        <div class="h-11 w-11 rounded-lg bg-secondary/10 text-secondary flex items-center justify-center border border-secondary/20">
          <CheckCircle2 class="h-5.5 w-5.5" />
        </div>
        <div>
          <p class="text-[10px] uppercase font-bold tracking-widest text-[#c2c4c7]">Total Forum Entries</p>
          <strong class="text-xl font-display font-black text-[#640c0e] mt-0.5 block">
            {{ stats.allCount }} Conversations
          </strong>
        </div>
      </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="flex flex-col sm:flex-row justify-between items-center gap-4 bg-white border border-[#ebe8de] rounded-xl p-3 shadow-sm">
      <Tabs v-model="activeTab" variant="pill" :options="tabOptions" class="w-full sm:w-auto" />

      <div class="flex gap-2 w-full sm:w-auto">
        <div class="relative flex-1 sm:w-56">
          <Search class="absolute left-3 top-2.5 h-3.5 w-3.5 text-neutral-muted" />
          <input
            type="text"
            placeholder="Search comments..."
            v-model="searchQuery"
            class="w-full pl-9 h-9 text-xs rounded-xl border border-[#ebe8de] bg-[#fffbf4]/10 text-[#640c0e] outline-none"
          />
        </div>

        <div class="flex items-center gap-1 bg-white border border-[#ebe8de] rounded-xl px-2 h-9 text-xs font-bold shrink-0">
          <Filter class="h-3.5 w-3.5 text-[#c2c4c7]" />
          <select
            v-model="selectedCourse"
            class="bg-transparent border-none outline-none font-bold text-[#640c0e] text-xs"
          >
            <option value="All">All Course Boards</option>
            <option v-for="c in courseTitles.filter(t => t !== 'All')" :key="c" :value="c">
              {{ c }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <!-- Active Threads Cards List -->
    <div v-if="loading" class="flex justify-center py-16">
      <Loader2 class="h-8 w-8 animate-spin text-primary" />
    </div>

    <EmptyState
      v-else-if="filteredDiscussions.length === 0"
      title="No discussions found"
      description="There are no forum items matching the selected filters."
    />

    <div v-else class="space-y-6">
      <div 
        v-for="disc in filteredDiscussions" 
        :key="disc.id"
        :class="[
          'bg-white border rounded-2xl p-6 shadow-premium transition-all duration-300 relative overflow-hidden space-y-4',
          disc.status === 'flagged' ? 'border-red-100 bg-red-50/5' : 'border-[#ebe8de]'
        ]"
      >
        <!-- Card Header Info -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[#ebe8de]/40 pb-3">
          <div class="flex items-center gap-3">
            <img :src="disc.authorAvatar" alt="" class="w-9 h-9 rounded-lg border border-[#ebe8de]" />
            <div>
              <strong class="text-xs font-bold text-[#640c0e] block sm:inline">{{ disc.authorName }}</strong>
              <span class="text-[10px] text-neutral-muted font-mono sm:ms-2">
                posted {{ new Date(disc.postedAt).toLocaleDateString() }}
              </span>
              <span class="inline-block sm:ms-2 bg-[#fffbf4] border border-[#ebe8de] text-[8px] font-mono px-1.5 py-0.2 rounded font-bold text-[#c2c4c7]">
                {{ disc.courseTitle }}
              </span>
            </div>
          </div>

          <div class="flex items-center gap-1.5 self-end sm:self-auto flex-wrap">
            <span v-if="disc.isPinned" class="bg-[#fffbf4] border border-[#ebe8de] text-[9px] font-bold text-[#640c0e] px-2 py-0.5 rounded-sm flex items-center gap-1">
              <Pin class="h-3 w-3 text-[#ffdc83]" /> PINNED
            </span>
            <span v-if="disc.status === 'flagged'" class="bg-red-50 border border-red-100 text-[9px] font-mono font-bold text-[#b02e32] px-2 py-0.5 rounded-sm flex items-center gap-1 uppercase">
              <AlertTriangle class="h-3 w-3" /> Flagged ({{ disc.flaggedCount }} reports)
            </span>
            <span v-if="disc.status === 'resolved'" class="bg-secondary/10 border border-secondary/20 text-[9px] font-mono font-bold text-primary px-2 py-0.5 rounded-sm flex items-center gap-1 uppercase">
              <CheckCircle2 class="h-3 w-3" /> Resolved
            </span>
          </div>
        </div>

        <!-- Thread Text -->
        <div class="space-y-2">
          <h4 class="font-display font-bold text-[#640c0e] text-base leading-tight">
            {{ disc.title }}
          </h4>
          <p class="text-xs text-neutral-muted/90 font-sans leading-relaxed italic p-3 rounded-xl bg-[#fffbf4]/20 border border-dashed border-[#ebe8de] max-w-3xl">
            "{{ disc.preview }}"
          </p>
        </div>

        <!-- Flag reason box -->
        <div v-if="disc.status === 'flagged' && disc.flaggedReason" class="bg-red-50/40 border border-red-100/60 p-3.5 rounded-xl space-y-1 max-w-2xl">
          <span class="text-[9px] font-mono font-bold uppercase text-[#b02e32]">Report Reason Checklist</span>
          <p class="text-xs text-[#b02e32]/95 leading-snug">
            <strong>Reason code:</strong> "{{ disc.flaggedReason }}"
          </p>
        </div>

        <!-- Mentor replies -->
        <div v-if="disc.mentorReplies && disc.mentorReplies.length > 0" class="space-y-2.5 pt-2 max-w-3xl border-t border-[#ebe8de]/40">
          <span class="text-[9px] font-mono font-bold uppercase tracking-wider text-[#c2c4c7] block">Advising Mentor Commentary</span>
          <div v-for="(reply, rid) in disc.mentorReplies" :key="rid" class="flex gap-2.5 items-start bg-secondary/10/10 border border-secondary/20/40 p-3 rounded-lg">
            <CornerDownRight class="h-4 w-4 text-[#ffdc83] shrink-0 mt-0.5" />
            <div>
              <div class="flex items-center gap-1.5 flex-wrap">
                <strong class="text-xs font-bold text-primary">{{ reply.author }}</strong>
                <span class="text-[10px] text-secondary bg-secondary/10 border border-secondary/20 rounded px-1.5 py-0.1 font-bold font-mono text-[8px] uppercase">{{ reply.role }}</span>
                <span class="text-[10px] text-neutral-muted font-mono">{{ reply.date }}</span>
              </div>
              <p class="text-xs text-[#640c0e] mt-1.5 font-sans leading-relaxed">
                "{{ reply.text }}"
              </p>
            </div>
          </div>
        </div>

        <!-- Reply Interception Form -->
        <div v-if="commentingThreadId === disc.id" class="p-4 bg-[#fffbf4]/20 border border-[#ebe8de] rounded-xl space-y-2 max-w-3xl">
          <div class="flex justify-between items-center pb-2 border-b border-[#ebe8de]/40">
            <span class="text-[10px] font-mono font-bold text-[#640c0e] uppercase">Deploy Registrar Counter-Comment</span>
            <button
              @click="commentingThreadId = null"
              class="text-[10px] h-5 px-1.5 text-neutral-muted font-bold cursor-pointer hover:underline"
            >
              Cancel
            </button>
          </div>
          <div class="flex gap-2">
            <textarea
              v-model="replyText"
              placeholder="Type comforting response / theological counseling details regarding this academic dispute..."
              class="flex-1 text-xs p-3 border border-[#ebe8de] rounded-lg outline-none focus:ring-1 focus:ring-[#640c0e] bg-white min-h-[70px] text-[#640c0e]"
            />
            <button
              @click="handleSendMentorReply(disc.id)"
              class="bg-[#640c0e] hover:bg-[#640c0e]/90 text-white p-3 rounded-lg h-10 self-end flex items-center justify-center cursor-pointer shadow-premium"
            >
              <Send class="h-4 w-4 text-[#ffdc83]" />
            </button>
          </div>
        </div>

        <div v-else class="pt-2">
          <button
            @click="commentingThreadId = disc.id"
            class="h-8 text-[11px] px-3 font-bold border border-[#ebe8de] text-[#c2c4c7] hover:bg-[#fffbf4] hover:text-[#640c0e] rounded-lg cursor-pointer flex items-center gap-1.5 transition-colors"
          >
            <CornerDownRight class="h-3.5 w-3.5" /> Intercept with Mentor Reply
          </button>
        </div>

        <!-- Footer Actions toolbar -->
        <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-[#ebe8de]/40 items-center justify-between mt-4">
          <span class="text-[9px] font-mono text-neutral-muted self-start sm:self-auto">
            Replies Count: {{ disc.repliesCount }} posts • Thread ID: {{ disc.id }}
          </span>

          <div class="flex gap-2 w-full sm:w-auto">
            <button
              v-if="disc.status === 'flagged'"
              @click="handleApproveThread(disc.id)"
              class="flex-1 sm:flex-none h-8 px-3 text-[11px] font-bold border border-secondary/20 bg-secondary/10 hover:bg-secondary/15 text-primary rounded-lg cursor-pointer flex items-center justify-center gap-1 transition-colors"
            >
              <ShieldCheck class="h-3.5 w-3.5" /> Dismiss Flag
            </button>
            
            <button
              @click="handleTogglePin(disc.id)"
              class="flex-1 sm:flex-none h-8 px-3 text-[11px] border border-[#ebe8de] rounded-lg font-bold hover:bg-[#fffbf4] cursor-pointer flex items-center justify-center gap-1 transition-colors"
            >
              <Pin :class="['h-3.5 w-3.5', disc.isPinned ? 'text-[#ffdc83]' : 'text-neutral-muted']" />
              {{ disc.isPinned ? "Unpin Post" : "Pin Discussion" }}
            </button>

            <button
              @click="handleDeleteThread(disc.id)"
              class="flex-1 sm:flex-none h-8 px-3 text-[11px] font-bold bg-[#640c0e] hover:bg-[#640c0e]/95 text-white rounded-lg cursor-pointer flex items-center justify-center gap-1 transition-colors"
            >
              <Trash2 class="h-3 w-3 text-[#ffdc83]" /> Remove Thread
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
