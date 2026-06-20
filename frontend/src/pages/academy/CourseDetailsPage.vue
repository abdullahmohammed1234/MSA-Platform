<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Motion } from '@motionone/vue';
import { useCoursesStore } from '@/stores/academy/courses';
import { useProgressStore } from '@/stores/academy/progress';
import { useToastStore } from '@/components/feedback/toast';
import Badge from '@/components/data-display/badge/Badge.vue';
import LmsCircularProgress from '@/components/data-display/progress/LmsCircularProgress.vue';
import Breadcrumbs from '@/components/navigation/breadcrumbs/Breadcrumbs.vue';
import { buttonHover } from '@/design-system/animations/hover';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const coursesStore = useCoursesStore();
const progressStore = useProgressStore();

// Track expanded modules
const expandedModules = ref<Record<number, boolean>>({});

onMounted(async () => {
  const param = route.params.idOrSlug as string;
  await coursesStore.fetchCourseDetails(param);
  
  // Auto-expand first module
  if (coursesStore.currentCourse?.modules && coursesStore.currentCourse.modules.length > 0) {
    expandedModules.value[coursesStore.currentCourse.modules[0].id] = true;
  }
});

const course = computed(() => coursesStore.currentCourse);
const breadcrumbItems = computed(() => {
  if (!course.value) return [];
  return [
    { id: 'catalog', label: 'Courses' },
    { id: 'course', label: course.value.title },
  ];
});
const enrollment = computed(() => coursesStore.currentEnrollment);
const isEnrolled = computed(() => !!enrollment.value);

const progressPercent = computed(() => {
  if (!course.value) return 0;
  return progressStore.courseProgress[course.value.id] || 0;
});

const isCompleted = computed(() => progressPercent.value >= 100);

const toggleModule = (id: number) => {
  expandedModules.value[id] = !expandedModules.value[id];
};

const handleEnroll = async () => {
  if (!course.value) return;
  try {
    await coursesStore.enrollInCourse(course.value.id);
    toast.success('Successfully enrolled in program!');
  } catch (err: any) {
    toast.error(err.message || 'Failed to enroll');
  }
};

const resumeLearning = () => {
  if (!course.value) return;
  
  // Find first lesson that is not completed
  let targetLessonId = null;
  if (course.value.modules) {
    for (const mod of course.value.modules) {
      if (mod.lessons) {
        for (const les of mod.lessons) {
          const completed = progressStore.isLessonCompleted(course.value.id, les.id);
          if (!completed) {
            targetLessonId = les.id;
            break;
          }
        }
      }
      if (targetLessonId) break;
    }
  }

  // Fallback to first lesson
  if (!targetLessonId && course.value.modules?.[0]?.lessons?.[0]) {
    targetLessonId = course.value.modules[0].lessons[0].id;
  }

  if (targetLessonId) {
    router.push(`/academy/courses/${course.value.id}/lessons/${targetLessonId}`);
  } else {
    // If all lessons are completed but quiz isn't taken, go to quiz
    if (course.value.quizzes?.[0]) {
      router.push(`/academy/courses/${course.value.id}/quizzes/${course.value.quizzes[0].id}`);
    } else {
      toast.info('You have completed this course!');
    }
  }
};

const handleLessonQuizClick = (type: 'lesson' | 'quiz', itemId: number) => {
  if (!isEnrolled.value) {
    toast.warning('Please enroll in the course first to view content.');
    return;
  }
  if (!course.value) return;

  if (type === 'lesson') {
    router.push(`/academy/courses/${course.value.id}/lessons/${itemId}`);
  } else {
    router.push(`/academy/courses/${course.value.id}/quizzes/${itemId}`);
  }
};

const getLessonDuration = (les: any) => les.estimated_duration || 30;
</script>

<template>
  <div v-if="coursesStore.loading && !course" class="py-20 text-center text-neutral-muted">
    Loading course details...
  </div>
  <div v-else-if="!course" class="py-20 text-center text-neutral-muted">
    Course not found.
  </div>
  <div v-else class="space-y-8 pb-16">
    <Breadcrumbs
      :items="breadcrumbItems"
      @home="router.push('/academy')"
      @navigate="(item) => item.id === 'catalog' && router.push('/academy/courses')"
    />

    <!-- Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      
      <!-- Left Column: Course details &syllabus -->
      <div class="lg:col-span-2 space-y-8">
        
        <!-- Hero section -->
        <div class="bg-gradient-to-r from-primary to-primary-dark rounded-3xl p-6 sm:p-8 text-white relative overflow-hidden shadow-premium">
          <div class="absolute right-0 bottom-0 opacity-10 pattern-islamic w-64 h-64 pointer-events-none"></div>
          
          <div class="relative z-10 space-y-4">
            <div class="flex flex-wrap gap-2">
              <Badge variant="gold" size="sm">{{ course.difficulty }}</Badge>
              <Badge variant="outline" size="sm" class="text-white border-white/25">
                {{ course.estimated_duration || 90 }} mins
              </Badge>
            </div>
            
            <h1 class="text-2xl sm:text-3xl font-display font-bold text-white tracking-tight leading-tight">
              {{ course.title }}
            </h1>
            
            <p class="text-white/80 text-sm leading-relaxed max-w-2xl font-light">
              {{ course.description || 'No description available for this program.' }}
            </p>
          </div>
        </div>

        <!-- Course Info (Objectives & Requirements) -->
        <div class="bg-white border border-neutral-ivory p-6 sm:p-8 rounded-3xl shadow-soft space-y-6">
          <div>
            <h3 class="text-lg font-display font-semibold text-primary mb-3">Program Learning Objectives</h3>
            <ul class="space-y-2.5">
              <li class="flex items-start gap-2.5 text-sm text-neutral-black/75">
                <svg class="h-5 w-5 text-primary flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4" />
                </svg>
                <span>Understand the scriptural obligations and historical precedent of Dawah.</span>
              </li>
              <li class="flex items-start gap-2.5 text-sm text-neutral-black/75">
                <svg class="h-5 w-5 text-primary flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4" />
                </svg>
                <span>Develop proper interpersonal ethics and communication traits needed in dialogue.</span>
              </li>
              <li class="flex items-start gap-2.5 text-sm text-neutral-black/75">
                <svg class="h-5 w-5 text-primary flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4" />
                </svg>
                <span>Learn practical conversation-shifting models such as G.O.R.A.P.</span>
              </li>
            </ul>
          </div>

          <div class="pt-6 border-t border-neutral-ivory/50">
            <h3 class="text-lg font-display font-semibold text-primary mb-3">Requirements</h3>
            <p class="text-xs sm:text-sm text-neutral-muted leading-relaxed font-light">
              No prior credentials or theology backgrounds required. Access to an internet-connected device is necessary to watch video modules and complete quizzes.
            </p>
          </div>
        </div>

        <!-- Modules Syllabus Accordion -->
        <div class="space-y-4">
          <h2 class="text-xl font-display font-semibold text-primary">Syllabus Curriculum</h2>

          <div class="space-y-4">
            <div
              v-for="mod in course.modules"
              :key="mod.id"
              class="bg-white border border-neutral-ivory rounded-2xl shadow-soft overflow-hidden"
            >
              <!-- Module Header -->
              <div
                @click="toggleModule(mod.id)"
                class="flex items-center justify-between p-5 cursor-pointer hover:bg-neutral-background/40 transition-colors select-none"
              >
                <div class="min-w-0 pr-4">
                  <span class="text-[9px] font-mono font-bold uppercase tracking-wider text-neutral-muted">
                    Module {{ mod.order }}
                  </span>
                  <h3 class="text-base font-semibold text-primary leading-tight mt-0.5 truncate">
                    {{ mod.title }}
                  </h3>
                  <span class="text-[10px] text-neutral-muted mt-1 inline-block">
                    {{ mod.lessons?.length || 0 }} lessons • {{ mod.estimated_duration || 60 }} mins
                  </span>
                </div>
                <div class="flex-shrink-0">
                  <svg
                    class="h-5 w-5 text-primary transition-transform duration-200"
                    :class="{ 'transform rotate-180': expandedModules[mod.id] }"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                  </svg>
                </div>
              </div>

              <!-- Module Description & Lesson List -->
              <div v-show="expandedModules[mod.id]" class="border-t border-neutral-ivory/50 bg-neutral-background/15 p-5 pt-4 space-y-4">
                <p v-if="mod.description" class="text-xs text-neutral-muted font-light leading-relaxed">
                  {{ mod.description }}
                </p>

                <!-- Lessons & Quizzes List -->
                <div class="space-y-2">
                  <!-- Lessons -->
                  <div
                    v-for="les in mod.lessons"
                    :key="les.id"
                    @click="handleLessonQuizClick('lesson', les.id)"
                    :class="[
                      'flex items-center justify-between px-4 py-3 rounded-xl border text-sm transition-all select-none',
                      isEnrolled
                        ? 'border-neutral-ivory bg-white hover:border-primary cursor-pointer'
                        : 'border-neutral-ivory bg-neutral-background/40 text-neutral-muted cursor-not-allowed'
                    ]"
                  >
                    <div class="flex items-center gap-3 min-w-0">
                      <!-- Checked state -->
                      <div v-if="isEnrolled && progressStore.isLessonCompleted(course.id, les.id)" class="text-success flex-shrink-0">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                      </div>
                      <div v-else class="text-neutral-gray flex-shrink-0">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <circle cx="12" cy="12" r="10" stroke-width="2" />
                        </svg>
                      </div>
                      <span :class="['font-medium truncate', isEnrolled && progressStore.isLessonCompleted(course.id, les.id) ? 'line-through text-neutral-muted' : 'text-neutral-black']">
                        {{ les.title }}
                      </span>
                    </div>

                    <span class="text-[10px] font-mono text-neutral-muted/70 flex-shrink-0">
                      {{ getLessonDuration(les) }} mins
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Course Quizzes listing -->
            <div
              v-for="quiz in course.quizzes"
              :key="quiz.id"
              @click="handleLessonQuizClick('quiz', quiz.id)"
              :class="[
                'flex items-center justify-between p-5 rounded-2xl border text-sm transition-all select-none shadow-soft',
                isEnrolled
                  ? 'border-accent-gold/45 bg-accent-gold/5 hover:bg-accent-gold/10 cursor-pointer'
                  : 'border-neutral-ivory bg-neutral-background/40 text-neutral-muted cursor-not-allowed'
              ]"
            >
              <div class="flex items-center gap-3 min-w-0">
                <div class="bg-accent-gold/20 text-primary p-1.5 rounded-xl flex-shrink-0">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                  </svg>
                </div>
                <div class="min-w-0">
                  <h4 class="font-semibold text-primary truncate leading-tight">{{ quiz.title }}</h4>
                  <p class="text-[10px] text-neutral-muted mt-0.5">Final assessment • Passing: {{ quiz.passing_score }}%</p>
                </div>
              </div>

              <!-- Completion state for quiz -->
              <div v-if="isEnrolled && isCompleted" class="text-success flex items-center gap-1 font-bold text-xs uppercase tracking-wider">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4" />
                </svg>
                Passed
              </div>
              <div v-else class="text-primary font-bold text-xs uppercase tracking-wider flex items-center gap-1">
                Take Quiz
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                </svg>
              </div>
            </div>

          </div>
        </div>

      </div>

      <!-- Right Column: Enroll panel & details -->
      <div class="space-y-8">
        
        <!-- Action Card -->
        <div class="bg-white border border-neutral-ivory p-6 rounded-3xl shadow-soft space-y-6">
          <h3 class="text-base font-display font-semibold text-primary border-b border-neutral-ivory pb-3">
            Program Registration
          </h3>

          <!-- Active Enrolled state -->
          <div v-if="isEnrolled" class="space-y-6">
            <div class="flex items-center gap-4">
              <!-- Radial completion -->
              <LmsCircularProgress :value="progressPercent" :size="70" :strokeWidth="6" color="text-secondary" />
              <div>
                <h4 class="text-sm font-semibold text-neutral-black">Enrolled Program</h4>
                <p class="text-xs text-neutral-muted mt-0.5">Status: {{ isCompleted ? 'Completed' : 'In Progress' }}</p>
              </div>
            </div>

            <div class="space-y-3">
              <Motion
                :hover="buttonHover.hover"
                :press="buttonHover.tap"
                :transition="buttonHover.transition"
                as="button"
                class="w-full bg-primary hover:bg-primary/95 text-white font-bold py-3 rounded-2xl text-sm cursor-pointer shadow-soft"
                @click="resumeLearning"
              >
                {{ isCompleted ? 'Review Syllabus' : progressPercent > 0 ? 'Resume Course' : 'Start Course' }}
              </Motion>
            </div>
          </div>

          <!-- Unenrolled State -->
          <div v-else class="space-y-4">
            <p class="text-xs text-neutral-muted leading-relaxed font-light">
              Enroll to gain access to comprehensive lecture materials, structured notes, video walkthroughs, and accredited certificates.
            </p>
            <Motion
              :hover="buttonHover.hover"
              :press="buttonHover.tap"
              :transition="buttonHover.transition"
              as="button"
              class="w-full bg-primary hover:bg-primary/95 text-white font-bold py-3 rounded-2xl text-sm cursor-pointer shadow-soft text-center"
              @click="handleEnroll"
            >
              Enroll in Course
            </Motion>
          </div>
        </div>

        <!-- Instructor Section -->
        <div class="bg-white border border-neutral-ivory p-6 rounded-3xl shadow-soft space-y-4">
          <h3 class="text-base font-display font-semibold text-primary border-b border-neutral-ivory pb-3">Instructor</h3>
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-neutral-ivory/60 flex items-center justify-center font-bold text-primary">
              T
            </div>
            <div>
              <h4 class="text-sm font-semibold text-neutral-black">{{ course.creator?.name || 'Academy Scholar' }}</h4>
              <p class="text-[10px] text-neutral-muted">Senior Instructor • SFU MSA Dawah Academy</p>
            </div>
          </div>
        </div>

      </div>

    </div>
  </div>
</template>
