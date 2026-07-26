<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { EmsErrorState, EmsPageHeader } from '@/components/ems';
import { feedbackService, type FeedbackSummary } from '@/services/ems/feedbackService';
import { eventsService } from '@/services/ems/eventsService';
import { useEmsApiError } from '@/composables/ems/useEmsApiError';
import { Star, MessageSquare, Award, Calendar, BarChart3 } from 'lucide-vue-next';
import type { Event } from '@/types/ems';

const { handle } = useEmsApiError();

const eventsList = ref<Event[]>([]);
const selectedEventUuid = ref<string>('');
const summary = ref<FeedbackSummary | null>(null);

const isLoadingEvents = ref(true);
const isLoadingFeedback = ref(false);
const error = ref<string | null>(null);

const loadEvents = async () => {
  isLoadingEvents.value = true;
  error.value = null;
  try {
    const res = await eventsService.list({ per_page: 100 });
    eventsList.value = res.items;
    if (res.items.length > 0) {
      selectedEventUuid.value = res.items[0].uuid;
    }
  } catch (caught) {
    error.value = handle(caught, { silent: true }).message;
  } finally {
    isLoadingEvents.value = false;
  }
};

const fetchFeedback = async () => {
  if (!selectedEventUuid.value) {
    summary.value = null;
    return;
  }
  isLoadingFeedback.value = true;
  try {
    summary.value = await feedbackService.eventFeedback(selectedEventUuid.value);
  } catch (caught) {
    handle(caught);
    summary.value = null;
  } finally {
    isLoadingFeedback.value = false;
  }
};

onMounted(loadEvents);
watch(selectedEventUuid, fetchFeedback);

const formatDate = (val: string) => {
  return new Date(val).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
  <div class="space-y-6">
    <EmsPageHeader
      title="Feedback & Surveys"
      description="View attendee survey ratings, response rates, and text comments."
      back-to="/ems"
      back-label="Dashboard"
    />

    <!-- Event Selection -->
    <div class="rounded-2xl border border-neutral-ivory bg-white p-4 shadow-soft">
      <div class="flex items-center gap-3">
        <label for="event-select" class="text-xs font-bold uppercase tracking-wider text-neutral-black whitespace-nowrap">
          Select Event:
        </label>
        <select
          id="event-select"
          v-model="selectedEventUuid"
          :disabled="isLoadingEvents"
          class="max-w-md rounded-lg border border-neutral-ivory bg-neutral-background/40 px-3 py-2 text-xs text-neutral-black focus:border-primary focus:outline-none"
        >
          <option value="" disabled>Select an event to load feedback</option>
          <option v-for="e in eventsList" :key="e.uuid" :value="e.uuid">
            {{ e.name }}
          </option>
        </select>
      </div>
    </div>

    <div v-if="isLoadingEvents || isLoadingFeedback" class="space-y-4">
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div v-for="i in 4" :key="i" class="h-24 animate-pulse rounded-2xl bg-neutral-ivory/50" />
      </div>
      <div class="h-48 animate-pulse rounded-2xl bg-neutral-ivory/50" />
    </div>

    <EmsErrorState v-else-if="error" title="Unable to load feedback" :message="error" can-retry @retry="loadEvents" />

    <template v-else-if="summary">
      <!-- Ratings Summary Bento Cards -->
      <section aria-label="Average ratings" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <!-- Overall -->
        <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft hover:shadow-premium-md transition-shadow">
          <div class="flex items-center justify-between text-neutral-muted">
            <span class="text-[10px] font-bold uppercase tracking-wider">Overall Experience</span>
            <Award class="h-4 w-4 text-amber-500" />
          </div>
          <div class="mt-2 flex items-baseline gap-2">
            <span class="text-3xl font-bold font-serif text-neutral-black">{{ summary.average_overall_rating }}</span>
            <span class="text-xs text-neutral-muted">/ 5</span>
          </div>
          <div class="mt-2 flex gap-0.5">
            <Star v-for="i in 5" :key="i" class="h-3.5 w-3.5" :class="i <= Math.round(summary.average_overall_rating) ? 'fill-amber-400 text-amber-400' : 'text-neutral-200'" />
          </div>
        </div>

        <!-- Organization -->
        <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft hover:shadow-premium-md transition-shadow">
          <div class="flex items-center justify-between text-neutral-muted">
            <span class="text-[10px] font-bold uppercase tracking-wider">Event Organization</span>
            <Calendar class="h-4 w-4 text-blue-500" />
          </div>
          <div class="mt-2 flex items-baseline gap-2">
            <span class="text-3xl font-bold font-serif text-neutral-black">{{ summary.average_organization_rating }}</span>
            <span class="text-xs text-neutral-muted">/ 5</span>
          </div>
          <div class="mt-2 flex gap-0.5">
            <Star v-for="i in 5" :key="i" class="h-3.5 w-3.5" :class="i <= Math.round(summary.average_organization_rating) ? 'fill-amber-400 text-amber-400' : 'text-neutral-200'" />
          </div>
        </div>

        <!-- Program -->
        <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft hover:shadow-premium-md transition-shadow">
          <div class="flex items-center justify-between text-neutral-muted">
            <span class="text-[10px] font-bold uppercase tracking-wider">Program & Content</span>
            <MessageSquare class="h-4 w-4 text-emerald-600" />
          </div>
          <div class="mt-2 flex items-baseline gap-2">
            <span class="text-3xl font-bold font-serif text-neutral-black">{{ summary.average_program_rating }}</span>
            <span class="text-xs text-neutral-muted">/ 5</span>
          </div>
          <div class="mt-2 flex gap-0.5">
            <Star v-for="i in 5" :key="i" class="h-3.5 w-3.5" :class="i <= Math.round(summary.average_program_rating) ? 'fill-amber-400 text-amber-400' : 'text-neutral-200'" />
          </div>
        </div>

        <!-- Venue -->
        <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft hover:shadow-premium-md transition-shadow">
          <div class="flex items-center justify-between text-neutral-muted">
            <span class="text-[10px] font-bold uppercase tracking-wider">Venue & Amenities</span>
            <BarChart3 class="h-4 w-4 text-purple-600" />
          </div>
          <div class="mt-2 flex items-baseline gap-2">
            <span class="text-3xl font-bold font-serif text-neutral-black">{{ summary.average_venue_rating }}</span>
            <span class="text-xs text-neutral-muted">/ 5</span>
          </div>
          <div class="mt-2 flex gap-0.5">
            <Star v-for="i in 5" :key="i" class="h-3.5 w-3.5" :class="i <= Math.round(summary.average_venue_rating) ? 'fill-amber-400 text-amber-400' : 'text-neutral-200'" />
          </div>
        </div>
      </section>

      <!-- Response rate summary bar -->
      <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft">
        <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-black mb-3">Response Rate Metric</h3>
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div class="space-y-1">
            <p class="text-sm font-semibold text-neutral-black">{{ summary.total_responses }} surveys completed</p>
            <p class="text-xs text-neutral-muted">Out of all expected checked-in and confirmed attendees.</p>
          </div>
          <div class="text-right">
            <p class="text-2xl font-bold font-serif text-primary">{{ summary.response_rate }}%</p>
            <p class="text-[10px] text-neutral-muted">Response Rate</p>
          </div>
        </div>
        <div class="w-full bg-neutral-background rounded-full h-2 mt-4">
          <div class="bg-primary h-2 rounded-full transition-all duration-300" :style="{ width: `${summary.response_rate}%` }"></div>
        </div>
      </div>

      <!-- Text Feedback Comments -->
      <div class="space-y-4">
        <h3 class="text-sm font-bold uppercase tracking-wider text-neutral-black flex items-center gap-2">
          <MessageSquare class="h-4 w-4 text-primary" />
          Written Submissions
        </h3>

        <div class="grid gap-4 md:grid-cols-2">
          <div v-for="comment in summary.comments" :key="comment.uuid" class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft hover:shadow-premium-md transition-shadow flex flex-col justify-between">
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-neutral-black">
                  {{ comment.author_name }}
                  <span v-if="comment.is_anonymous" class="ml-1 text-[10px] font-normal text-neutral-muted border border-neutral-ivory bg-neutral-background px-1.5 py-0.5 rounded">
                    Anonymous
                  </span>
                </span>
                <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-xs border border-amber-200">
                  <Star class="h-3 w-3 fill-amber-400 text-amber-400" />
                  {{ comment.overall_rating }} / 5
                </span>
              </div>
              <p class="text-xs text-neutral-black leading-relaxed whitespace-pre-line bg-neutral-background/30 p-3 rounded-xl border border-neutral-ivory/50">
                {{ comment.text_feedback }}
              </p>
            </div>
            <div class="mt-4 pt-3 border-t border-neutral-ivory/40 text-[10px] text-neutral-muted">
              Submitted on {{ formatDate(comment.created_at) }}
            </div>
          </div>
        </div>

        <div v-if="summary.comments.length === 0" class="p-8 border border-neutral-ivory border-dashed rounded-2xl text-center text-neutral-muted text-sm">
          No detailed comments submitted for this event.
        </div>
      </div>
    </template>

    <div v-else class="p-8 border border-neutral-ivory border-dashed rounded-2xl text-center text-neutral-muted text-sm">
      Please select an event from the dropdown to load attendee feedback.
    </div>
  </div>
</template>
