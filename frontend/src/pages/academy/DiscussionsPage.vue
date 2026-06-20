<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { MessageSquare, Plus, Search, Send, Users, X, Bookmark, Flag, ThumbsUp, Heart } from 'lucide-vue-next';
import client from '@/services/api/client';
import { discussionsService, type ReactionType } from '@/services/academy/discussionsService';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/components/feedback/toast';
import Breadcrumbs from '@/components/navigation/breadcrumbs/Breadcrumbs.vue';

const authStore = useAuthStore();
const toast = useToastStore();

interface Category {
  id: number;
  name: string;
  slug: string;
}

interface Author {
  id: number;
  name: string;
  avatar_url?: string | null;
  roles?: string[];
}

interface Thread {
  id: number;
  title: string;
  content: string;
  created_at: string;
  category: Category;
  author: Author;
  posts_count?: number;
  reactions?: Record<string, number>;
  bookmarked?: boolean;
}

interface Post {
  id: number;
  content: string;
  created_at: string;
  author: Author;
  reactions?: Record<string, number>;
}

const reactionOptions: { type: ReactionType; label: string; icon: typeof ThumbsUp }[] = [
  { type: 'THUMBS_UP', label: 'Like', icon: ThumbsUp },
  { type: 'HEART', label: 'Heart', icon: Heart },
  { type: 'INSIGHTFUL', label: 'Insight', icon: MessageSquare },
];

const threads = ref<Thread[]>([]);
const categories = ref<Category[]>([]);
const postsByThread = ref<Record<number, Post[]>>({});
const expandedThreadId = ref<number | null>(null);
const searchQuery = ref('');
const selectedCategoryId = ref<string | number>('all');
const formOpen = ref(false);

const newTopicTitle = ref('');
const newTopicCategoryId = ref<number | ''>('');
const newTopicContent = ref('');
const newCommentInput = ref('');
const loading = ref(true);
const error = ref('');

const user = computed(() => authStore.user);

const loadThreads = async () => {
  loading.value = true;
  error.value = '';
  try {
    const params: Record<string, any> = {};
    if (selectedCategoryId.value !== 'all') {
      params.category_id = selectedCategoryId.value;
    }
    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim();
    }
    
    const response = await client.get('/discussions', { params });
    threads.value = Array.isArray(response.data.data) ? response.data.data : [];
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Unable to load discussion threads.';
    threads.value = [];
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  try {
    const response = await client.get('/discussion-categories');
    const liveCategories = Array.isArray(response.data.data) ? response.data.data : response.data.categories || [];
    categories.value = liveCategories;
    if (liveCategories.length > 0) {
      newTopicCategoryId.value = liveCategories[0].id;
    }
  } catch (err) {
    console.error('Failed to load categories', err);
    categories.value = [];
  }
  await loadThreads();
});

// Debounced watch for search and category changes
let debounceTimer: number | null = null;
watch([selectedCategoryId, searchQuery], () => {
  if (debounceTimer) clearTimeout(debounceTimer);
  debounceTimer = window.setTimeout(() => {
    loadThreads();
  }, 300);
});

const handleExpand = async (threadId: number) => {
  const nextId = expandedThreadId.value === threadId ? null : threadId;
  expandedThreadId.value = nextId;
  if (!nextId || postsByThread.value[nextId]) return;

  try {
    const response = await client.get(`/discussions/${nextId}/posts`);
    postsByThread.value[nextId] = Array.isArray(response.data.data) ? response.data.data : [];
  } catch (err) {
    console.error('Failed to load posts', err);
  }
};

const handleCreateTopic = async () => {
  if (!newTopicTitle.value.trim() || !newTopicContent.value.trim() || !newTopicCategoryId.value) return;

  try {
    const response = await client.post('/discussions', {
      title: newTopicTitle.value,
      content: newTopicContent.value,
      category_id: newTopicCategoryId.value
    });
    
    if (response.status === 200 || response.status === 201) {
      toast.success('Topic published successfully!');
      formOpen.value = false;
      newTopicTitle.value = '';
      newTopicContent.value = '';
      await loadThreads();
    }
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to publish topic.');
  }
};

const handleAddComment = async (threadId: number) => {
  if (!newCommentInput.value.trim()) return;
  try {
    const response = await client.post(`/discussions/${threadId}/posts`, {
      content: newCommentInput.value
    });
    
    if (response.status === 200 || response.status === 201) {
      const newPost = response.data.data || response.data.post;
      newCommentInput.value = '';
      if (newPost) {
        postsByThread.value[threadId] = [...(postsByThread.value[threadId] || []), newPost];
        const thr = threads.value.find((t) => t.id === threadId);
        if (thr) {
          thr.posts_count = (thr.posts_count || 0) + 1;
        }
      }
      toast.success('Reply added.');
    }
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to add reply.');
  }
};

const handleReaction = async (type: ReactionType, threadId?: number, postId?: number) => {
  if (!user.value) return;
  try {
    const result = await discussionsService.toggleReaction({ type, threadId, postId });
    if (threadId) {
      const thread = threads.value.find((t) => t.id === threadId);
      if (thread) {
        const reactions = { ...(thread.reactions || {}) };
        const current = reactions[type] || 0;
        reactions[type] = Math.max(0, current + (result.active ? 1 : -1));
        if (reactions[type] === 0) delete reactions[type];
        thread.reactions = reactions;
      }
    }
    if (postId && expandedThreadId.value) {
      const posts = postsByThread.value[expandedThreadId.value] || [];
      const post = posts.find((p) => p.id === postId);
      if (post) {
        const reactions = { ...(post.reactions || {}) };
        const current = reactions[type] || 0;
        reactions[type] = Math.max(0, current + (result.active ? 1 : -1));
        if (reactions[type] === 0) delete reactions[type];
        post.reactions = reactions;
      }
    }
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to update reaction.');
  }
};

const handleBookmark = async (threadId: number) => {
  if (!user.value) return;
  try {
    const result = await discussionsService.toggleBookmark(threadId);
    const thread = threads.value.find((t) => t.id === threadId);
    if (thread) thread.bookmarked = result.bookmarked;
    toast.success(result.bookmarked ? 'Thread bookmarked.' : 'Bookmark removed.');
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to update bookmark.');
  }
};

const handleReport = async (threadId?: number, postId?: number) => {
  if (!user.value) return;
  try {
    await discussionsService.submitReport({
      reason: 'INAPPROPRIATE_CONTENT',
      threadId,
      postId,
    });
    toast.success('Report submitted. Moderators will review it.');
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to submit report.');
  }
};

const reactionTotal = (reactions?: Record<string, number>) =>
  Object.values(reactions || {}).reduce((sum, n) => sum + n, 0);
</script>

<template>
  <div class="space-y-8 animate-fade-in font-sans text-left">
    <Breadcrumbs :items="[{ id: 'discussions', label: 'Discussions' }]" />
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
      <div>
        <h1 class="text-3xl font-display font-bold text-primary tracking-tight">
          Peer Discussions Board
        </h1>
        <p class="text-neutral-muted text-sm mt-1 font-light">
          Reflect on methodologies, ask theological questions, and collaborate with peer callers.
        </p>
      </div>
      <div>
        <button
          @click="formOpen = true"
          class="rounded-xl font-bold bg-primary hover:bg-secondary text-white flex items-center gap-2 cursor-pointer shadow-soft h-11 px-5 border text-xs"
          :disabled="!user || categories.length === 0"
        >
          <Plus class="h-4 w-4" />
          Initiate Dialogue
        </button>
      </div>
    </div>

    <!-- Modal Form -->
    <div v-if="formOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs">
      <div class="w-full max-w-xl bg-white rounded-3xl border border-neutral-ivory shadow-premium overflow-hidden">
        <div class="p-6 border-b border-neutral-ivory bg-neutral-background flex justify-between items-center">
          <h3 class="text-lg font-bold text-primary flex items-center gap-2">
            <MessageSquare class="h-5 w-5" />
            Initiate Dawah Dialogue
          </h3>
          <button @click="formOpen = false" class="p-1 rounded-lg text-neutral-muted hover:bg-primary/5 cursor-pointer">
            <X class="h-5 w-5" />
          </button>
        </div>
        <div class="p-6 space-y-5">
          <input
            v-model="newTopicTitle"
            placeholder="Topic title"
            class="w-full bg-neutral-background/40 border border-neutral-ivory rounded-xl px-4 py-3 text-sm font-semibold outline-none"
          />
          <select
            v-model="newTopicCategoryId"
            class="w-full bg-neutral-background/40 border border-neutral-ivory rounded-xl px-4 py-3 text-sm font-semibold outline-none"
          >
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
          </select>
          <textarea
            v-model="newTopicContent"
            placeholder="Dialogue introduction / context"
            rows="5"
            class="w-full bg-neutral-background/40 border border-neutral-ivory rounded-xl p-4 text-sm font-semibold outline-none resize-none"
          ></textarea>
          <div class="flex justify-end gap-3 pt-4 border-t border-neutral-ivory">
            <button @click="formOpen = false" class="px-4 py-2 border border-neutral-ivory rounded-xl text-xs font-bold hover:bg-neutral-background cursor-pointer">Cancel</button>
            <button @click="handleCreateTopic" class="px-4 py-2 bg-primary hover:bg-secondary text-white rounded-xl text-xs font-bold cursor-pointer">Publish Topic</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-col md:flex-row gap-4 justify-between items-center bg-neutral-background/50 p-5 rounded-2xl border border-neutral-ivory/50">
      <div class="relative w-full md:w-96 select-none">
        <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-neutral-muted/65 h-4 w-4" />
        <input
          type="text"
          placeholder="Search discussion threads..."
          v-model="searchQuery"
          class="w-full bg-white border border-neutral-ivory rounded-xl px-12 py-3 text-sm outline-none"
        />
      </div>
      <div class="flex flex-wrap gap-2 justify-center">
        <button
          @click="selectedCategoryId = 'all'"
          class="px-4 py-1.5 rounded-xl text-xs font-bold border transition-all cursor-pointer whitespace-nowrap"
          :class="selectedCategoryId === 'all'
            ? 'bg-primary text-white border-primary shadow-soft'
            : 'bg-white text-neutral-muted border-neutral-ivory hover:text-primary'"
        >
          All Topics
        </button>
        <button
          v-for="cat in categories"
          :key="cat.id"
          @click="selectedCategoryId = cat.id"
          class="px-4 py-1.5 rounded-xl text-xs font-bold border transition-all cursor-pointer whitespace-nowrap"
          :class="selectedCategoryId === cat.id
            ? 'bg-primary text-white border-primary shadow-soft'
            : 'bg-white text-neutral-muted border-neutral-ivory hover:text-primary'"
        >
          {{ cat.name }}
        </button>
      </div>
    </div>

    <!-- Loading / Error states -->
    <div v-if="loading && threads.length === 0" class="bg-white border border-neutral-ivory rounded-3xl p-8 text-sm font-bold text-neutral-muted">
      Loading live discussion threads...
    </div>
    <div v-if="error" class="bg-red-50 border border-red-200 rounded-3xl p-8 text-sm font-bold text-red-700">
      {{ error }}
    </div>

    <!-- Empty state -->
    <div v-if="!loading && !error && threads.length === 0" class="text-center py-20 border border-dashed border-neutral-ivory rounded-3xl bg-white max-w-md mx-auto space-y-4">
      <Users class="h-12 w-12 text-neutral-300 mx-auto" />
      <h4 class="text-sm font-bold text-neutral-black">Create the First Discussion</h4>
      <p class="text-xs text-neutral-muted max-w-[280px] mx-auto font-light">
        Start with a course reflection, campus outreach question, or resource request so peers have a useful place to gather.
      </p>
      <button
        @click="formOpen = true"
        :disabled="!user || categories.length === 0"
        class="mx-auto h-10 px-4 rounded-xl bg-primary hover:bg-secondary text-xs font-bold text-white cursor-pointer"
      >
        Create First Topic
      </button>
    </div>

    <!-- Threads list -->
    <div v-else class="space-y-6">
      <div
        v-for="thread in threads"
        :key="thread.id"
        @click="handleExpand(thread.id)"
        class="rounded-3xl border border-neutral-ivory bg-white shadow-soft overflow-hidden cursor-pointer hover:shadow-premium-md transition-all duration-300"
      >
        <div class="p-8 space-y-5">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
              <div class="h-9 w-9 bg-primary text-white text-xs font-bold rounded-full flex items-center justify-center">
                {{ thread.author.name.split(' ').map((p) => p[0]).join('') }}
              </div>
              <div>
                <p class="text-xs font-bold text-neutral-black leading-tight">{{ thread.author.name }}</p>
                <p class="text-[10px] text-neutral-muted font-mono uppercase">
                  {{ new Date(thread.created_at).toLocaleDateString() }}
                </p>
              </div>
            </div>
            <span class="px-3 py-1 text-[9px] font-bold uppercase tracking-wider rounded-lg border bg-neutral-background text-primary border-primary/10">
              {{ thread.category.name }}
            </span>
          </div>

          <div class="space-y-2">
            <h3 class="text-xl font-bold text-neutral-black tracking-tight leading-snug">{{ thread.title }}</h3>
            <p class="text-sm text-neutral-muted leading-relaxed" :class="expandedThreadId !== thread.id && 'line-clamp-2'">
              {{ thread.content }}
            </p>
          </div>

          <div class="flex items-center gap-6 pt-3 text-neutral-muted font-bold border-t border-dashed border-neutral-ivory/55">
            <div class="flex items-center gap-2 text-xs">
              <MessageSquare class="h-4 w-4 text-primary" />
              <span>{{ thread.posts_count || 0 }} responses</span>
            </div>
            <div v-if="reactionTotal(thread.reactions) > 0" class="text-xs">
              {{ reactionTotal(thread.reactions) }} reactions
            </div>
            <div class="flex items-center gap-2 ml-auto" @click.stop>
              <button
                v-for="opt in reactionOptions"
                :key="opt.type"
                type="button"
                class="p-1.5 rounded-lg border border-neutral-ivory hover:bg-primary/5 cursor-pointer"
                :title="opt.label"
                @click="handleReaction(opt.type, thread.id)"
              >
                <component :is="opt.icon" class="h-3.5 w-3.5 text-primary" />
              </button>
              <button
                type="button"
                class="p-1.5 rounded-lg border border-neutral-ivory hover:bg-primary/5 cursor-pointer"
                :class="thread.bookmarked && 'bg-primary/10 border-primary/20'"
                @click="handleBookmark(thread.id)"
              >
                <Bookmark class="h-3.5 w-3.5 text-primary" />
              </button>
              <button
                type="button"
                class="p-1.5 rounded-lg border border-neutral-ivory hover:bg-red-50 cursor-pointer"
                @click="handleReport(thread.id)"
              >
                <Flag class="h-3.5 w-3.5 text-neutral-muted" />
              </button>
            </div>
          </div>
        </div>

        <!-- Replies Expanded -->
        <div v-if="expandedThreadId === thread.id" @click.stop class="bg-neutral-background/30 p-8 border-t border-neutral-ivory space-y-6">
          <div v-if="!postsByThread[thread.id] || postsByThread[thread.id].length === 0" class="text-xs font-semibold text-neutral-muted italic">
            No replies yet.
          </div>
          <div v-else class="space-y-4">
            <div v-for="post in postsByThread[thread.id]" :key="post.id" class="bg-white p-5 rounded-2xl border border-neutral-ivory shadow-soft space-y-2 text-left">
              <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-neutral-black">{{ post.author.name }}</span>
                <span class="text-[9px] text-neutral-muted font-mono">{{ new Date(post.created_at).toLocaleDateString() }}</span>
              </div>
              <p class="text-xs text-neutral-muted leading-relaxed font-light">{{ post.content }}</p>
              <div class="flex items-center gap-2 pt-1">
                <button
                  v-for="opt in reactionOptions"
                  :key="`${post.id}-${opt.type}`"
                  type="button"
                  class="p-1 rounded-md hover:bg-primary/5 cursor-pointer"
                  @click="handleReaction(opt.type, undefined, post.id)"
                >
                  <component :is="opt.icon" class="h-3 w-3 text-primary" />
                </button>
                <button
                  type="button"
                  class="p-1 rounded-md hover:bg-red-50 cursor-pointer ml-auto"
                  @click="handleReport(undefined, post.id)"
                >
                  <Flag class="h-3 w-3 text-neutral-muted" />
                </button>
              </div>
            </div>
          </div>

          <!-- Comment input -->
          <div v-if="user" class="flex gap-3 pt-4 border-t border-dashed border-neutral-ivory/80">
            <input
              v-model="newCommentInput"
              placeholder="Contribute your reflection..."
              @keydown.enter="handleAddComment(thread.id)"
              class="flex-1 bg-white border border-neutral-ivory rounded-xl px-4 py-3 text-xs outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
            />
            <button
              @click="handleAddComment(thread.id)"
              class="h-11 px-5 rounded-xl bg-primary hover:bg-secondary text-white font-bold text-xs flex items-center gap-1.5 cursor-pointer border"
            >
              <Send class="h-3.5 w-3.5" />
              Reply
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
