<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Motion } from '@motionone/vue';
import { useCoursesStore } from '@/stores/academy/courses';
import { useProgressStore } from '@/stores/academy/progress';
import { useToastStore } from '@/components/feedback/toast';
import LessonVideoPlayer from '@/components/lms/LessonVideoPlayer.vue';
import LessonTranscript from '@/components/lms/LessonTranscript.vue';
import LessonNotes from '@/components/lms/LessonNotes.vue';
import ModuleTracker from '@/components/lms/ModuleTracker.vue';
import Tabs from '@/components/navigation/tabs/Tabs.vue';
import TabsContent from '@/components/navigation/tabs/TabsContent.vue';
import { buttonHover } from '@/design-system/animations/hover';
import { academyAnalytics } from '@/utils/analytics';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const coursesStore = useCoursesStore();
const progressStore = useProgressStore();

const courseId = computed(() => Number(route.params.courseId));
const lessonId = computed(() => Number(route.params.lessonId));

// Track expanded modules in the sidebar
const expandedModules = ref<Record<number, boolean>>({});
const activeTab = ref('content');

const lessonTabs = [
  { id: 'content', label: 'Content' },
  { id: 'transcript', label: 'Transcript' },
  { id: 'notes', label: 'Notes' },
];

const fetchDetails = async () => {
  await coursesStore.fetchCourseDetails(courseId.value);
  
  // Auto-expand module containing current lesson
  if (course.value?.modules) {
    const activeMod = course.value.modules.find(m => 
      m.lessons?.some(l => l.id === lessonId.value)
    );
    if (activeMod) {
      expandedModules.value[activeMod.id] = true;
    }
  }
};

onMounted(fetchDetails);
watch(() => route.params.lessonId, fetchDetails);

const course = computed(() => coursesStore.currentCourse);

const currentLesson = computed(() => {
  if (!course.value?.modules) return null;
  for (const mod of course.value.modules) {
    const found = mod.lessons?.find(l => l.id === lessonId.value);
    if (found) return found;
  }
  return null;
});

const currentModule = computed(() => {
  if (!course.value?.modules || !currentLesson.value) return null;
  return course.value.modules.find(m => m.id === currentLesson.value!.module_id);
});

// Linear syllabus items list
const flatSyllabus = computed(() => {
  const list: Array<{ type: 'lesson' | 'quiz'; id: number; title: string }> = [];
  if (course.value?.modules) {
    course.value.modules.forEach(mod => {
      if (mod.lessons) {
        mod.lessons.forEach(les => {
          list.push({ type: 'lesson', id: les.id, title: les.title });
        });
      }
    });
  }
  if (course.value?.quizzes) {
    course.value.quizzes.forEach(qz => {
      list.push({ type: 'quiz', id: qz.id, title: qz.title });
    });
  }
  return list;
});

const currentFlatIndex = computed(() => {
  return flatSyllabus.value.findIndex(item => item.type === 'lesson' && item.id === lessonId.value);
});

const prevItem = computed(() => {
  const idx = currentFlatIndex.value;
  return idx > 0 ? flatSyllabus.value[idx - 1] : null;
});

const nextItem = computed(() => {
  const idx = currentFlatIndex.value;
  return idx < flatSyllabus.value.length - 1 ? flatSyllabus.value[idx + 1] : null;
});

const totalLessonCount = computed(() => {
  if (!course.value?.modules) return 0;
  return course.value.modules.reduce((sum, mod) => sum + (mod.lessons?.length || 0), 0);
});

const completedLessonCount = computed(() => progressStore.completedLessonsForCourse(courseId.value));

const isLessonDone = computed(() => {
  return progressStore.isLessonCompleted(courseId.value, lessonId.value);
});

const handleCompleteAndContinue = async () => {
  try {
    if (!isLessonDone.value) {
      await progressStore.completeLesson(
        courseId.value,
        lessonId.value,
        currentLesson.value?.title
      );
      toast.success('Lesson completed!');
      if (currentLesson.value) {
        academyAnalytics.trackLessonCompletion(courseId.value, lessonId.value, currentLesson.value.title);
      }
    }
    
    // Auto-advance
    if (nextItem.value) {
      navigateToItem(nextItem.value.type, nextItem.value.id);
    } else {
      toast.success('Congratulations! You completed all lessons in this program.');
    }
  } catch (err: any) {
    toast.error(err.message || 'Failed to complete lesson');
  }
};

const navigateToItem = (type: 'lesson' | 'quiz', id: number) => {
  if (type === 'lesson') {
    router.push(`/academy/courses/${courseId.value}/lessons/${id}`);
  } else {
    router.push(`/academy/courses/${courseId.value}/quizzes/${id}`);
  }
};

const toggleModule = (id: number) => {
  expandedModules.value[id] = !expandedModules.value[id];
};

const formatSize = (bytes?: number) => {
  if (!bytes) return '';
  const kb = bytes / 1024;
  if (kb < 1024) return `(${Math.round(kb)} KB)`;
  return `(${Math.round(kb / 1024)} MB)`;
};
</script>

<template>
  <div v-if="!course || !currentLesson" class="py-20 text-center text-neutral-muted">
    Loading lesson player...
  </div>
  
  <div v-else class="flex flex-col lg:flex-row gap-8 pb-16 min-h-[calc(100vh-8rem)]">
    
    <!-- 1. Content Area (Left/Main Pane) -->
    <div class="flex-grow space-y-6 lg:max-w-4xl min-w-0">
      <ModuleTracker
        :completed-count="completedLessonCount"
        :total-count="totalLessonCount"
        :course-title="course.title"
      />

      <LessonVideoPlayer
        v-if="currentLesson.video_url"
        :video-url="currentLesson.video_url"
        :title="currentLesson.title"
        :duration-minutes="currentLesson.estimated_duration ?? undefined"
      />

      <div class="bg-white border border-neutral-ivory p-6 sm:p-8 rounded-3xl shadow-soft space-y-6">
        <div>
          <span v-if="currentModule" class="text-[10px] font-mono font-bold uppercase tracking-widest text-neutral-muted">
            {{ currentModule.title }}
          </span>
          <h1 class="text-2xl sm:text-3xl font-display font-bold text-primary mt-1 leading-snug">
            {{ currentLesson.title }}
          </h1>
          <p class="text-xs text-neutral-muted mt-1 font-mono">
            Estimated reading: {{ currentLesson.estimated_duration || 30 }} minutes
          </p>
        </div>

        <Tabs v-model="activeTab" :options="lessonTabs" variant="pill" />

        <TabsContent value="content">
          <article class="prose max-w-none text-neutral-black/80 font-light text-sm sm:text-base leading-relaxed space-y-4">
            <div v-html="currentLesson.content?.replace(/\n/g, '<br/>')"></div>
          </article>
        </TabsContent>

        <TabsContent value="transcript">
          <LessonTranscript
            :content="currentLesson.content"
            :title="currentLesson.title"
          />
        </TabsContent>

        <TabsContent value="notes">
          <LessonNotes :lesson-id="currentLesson.id" />
        </TabsContent>

        <!-- Attachments & Resources box -->
        <div v-if="currentLesson.attachments && currentLesson.attachments.length > 0" class="mt-8 pt-6 border-t border-neutral-ivory/50 space-y-3">
          <h3 class="text-sm font-semibold text-primary uppercase tracking-wider">Lesson Attachments</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <a
              v-for="(file, idx) in currentLesson.attachments"
              :key="idx"
              :href="file.url"
              class="flex items-center gap-3 p-3 bg-neutral-background border border-neutral-ivory rounded-2xl hover:border-primary hover:bg-white transition-all text-xs font-semibold text-primary"
            >
              <svg class="h-5 w-5 text-neutral-gray flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <div class="min-w-0 flex-1">
                <p class="truncate text-neutral-black/85">{{ file.name }}</p>
                <span class="text-[9px] text-neutral-muted font-light block">{{ formatSize(file.size) }}</span>
              </div>
            </a>
          </div>
        </div>

        <!-- Previous & Next controls bottom bar -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-6 border-t border-neutral-ivory/50">
          
          <!-- Return to course syllabus -->
          <router-link
            :to="`/academy/courses/${course.slug}`"
            class="text-xs font-semibold text-neutral-muted hover:text-primary flex items-center gap-1 hover:underline"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            Syllabus Outline
          </router-link>

          <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
            <!-- Prev button -->
            <button
              v-if="prevItem"
              @click="navigateToItem(prevItem.type, prevItem.id)"
              class="px-4 py-2 border border-neutral-ivory hover:bg-neutral-background/50 rounded-xl text-xs font-semibold text-primary cursor-pointer transition-colors"
            >
              Previous
            </button>

            <!-- Complete and Continue action button -->
            <Motion
              :hover="buttonHover.hover"
              :press="buttonHover.tap"
              :transition="buttonHover.transition"
              as="button"
              class="bg-primary hover:bg-primary/95 text-white font-bold text-xs px-6 py-2.5 rounded-xl flex items-center gap-2 cursor-pointer shadow-soft"
              @click="handleCompleteAndContinue"
            >
              <span>{{ isLessonDone ? 'Continue' : 'Complete & Continue' }}</span>
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
              </svg>
            </Motion>
          </div>

        </div>

      </div>

    </div>

    <!-- 2. Course Navigation Sidebar (Right Pane) -->
    <div class="w-full lg:w-72 flex-shrink-0 space-y-6">
      
      <div class="bg-white border border-neutral-ivory p-6 rounded-3xl shadow-soft space-y-4">
        <h3 class="text-base font-display font-semibold text-primary border-b border-neutral-ivory pb-3">
          Course Syllabus
        </h3>

        <!-- Scrollable Syllabus tree list -->
        <div class="space-y-3 max-h-[calc(100vh-20rem)] overflow-y-auto pr-1">
          <div v-for="mod in course.modules" :key="mod.id" class="space-y-1">
            <!-- Module Header Toggle -->
            <div
              @click="toggleModule(mod.id)"
              class="flex items-center justify-between p-2.5 rounded-xl cursor-pointer hover:bg-neutral-background/60 transition-colors select-none"
            >
              <div class="min-w-0 pr-2">
                <h4 class="text-xs font-bold text-primary truncate leading-tight">{{ mod.title }}</h4>
              </div>
              <svg
                class="h-4 w-4 text-primary transition-transform duration-200 flex-shrink-0"
                :class="{ 'transform rotate-180': expandedModules[mod.id] }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </div>

            <!-- Lessons list under Module -->
            <div v-show="expandedModules[mod.id]" class="pl-2 space-y-1 border-l border-neutral-ivory/80 ml-2 pt-1 pb-2">
              <div
                v-for="les in mod.lessons"
                :key="les.id"
                @click="navigateToItem('lesson', les.id)"
                :class="[
                  'flex items-center gap-2 px-3 py-2 rounded-lg text-xs transition-all cursor-pointer select-none',
                  les.id === lessonId
                    ? 'bg-primary/5 text-primary border-l-2 border-primary font-bold'
                    : 'text-neutral-black/85 hover:bg-neutral-background'
                ]"
              >
                <!-- Checked State -->
                <div v-if="progressStore.isLessonCompleted(course.id, les.id)" class="text-success flex-shrink-0">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
                <div v-else class="h-1.5 w-1.5 rounded-full bg-neutral-gray flex-shrink-0 mx-1"></div>
                <span class="truncate">{{ les.title }}</span>
              </div>
            </div>
          </div>

          <!-- Course Quizzes in sidebar list -->
          <div v-if="course.quizzes && course.quizzes.length > 0" class="pt-2 border-t border-neutral-ivory/50 space-y-1">
            <div
              v-for="qz in course.quizzes"
              :key="qz.id"
              @click="navigateToItem('quiz', qz.id)"
              class="flex items-center gap-2 p-2.5 rounded-xl text-xs font-semibold text-primary bg-accent-gold/5 border border-accent-gold/20 hover:bg-accent-gold/10 cursor-pointer select-none"
            >
              <svg class="h-4 w-4 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
              </svg>
              <span class="truncate">{{ qz.title }}</span>
            </div>
          </div>
        </div>

      </div>

    </div>

  </div>
</template>
