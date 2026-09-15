<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { Star, MessageSquare, CheckCircle, ArrowLeft } from 'lucide-vue-next';
import { useToastStore } from '@/components/feedback/toast';
import client from '@/services/api';

const route = useRoute();
const toast = useToastStore();

const slug = route.params.slug as string;
const registrationUuid = (route.query.token || route.query.registration_uuid || '') as string;
const eventName = ref('MSA Event');
const isLoading = ref(true);
const isSubmitted = ref(false);
const isSubmitting = ref(false);

const form = ref({
  overall_rating: 5,
  program_rating: 5,
  organization_rating: 5,
  venue_rating: 5,
  text_feedback: '',
  email: '',
  is_anonymous: false,
});

onMounted(async () => {
  try {
    const res = await client.get(`/ems/public/events/${slug}`);
    eventName.value = res.data?.data?.name || 'MSA Event';
  } catch (e) {
    // Graceful fallback if event detail lookup fails
  } finally {
    isLoading.value = false;
  }
});

const submitFeedback = async () => {
  isSubmitting.value = true;
  try {
    await client.post(`/ems/public/events/${slug}/feedback`, {
      overall_rating: form.value.overall_rating,
      program_rating: form.value.program_rating,
      organization_rating: form.value.organization_rating,
      venue_rating: form.value.venue_rating,
      text_feedback: form.value.text_feedback.trim() || undefined,
      email: form.value.email.trim() || undefined,
      registration_uuid: registrationUuid || undefined,
      is_anonymous: form.value.is_anonymous,
    });

    isSubmitted.value = true;
    toast.success('Thank you! Your feedback has been submitted successfully.');
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to submit feedback. Please try again.');
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div class="min-h-screen bg-neutral-background py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-2xl mx-auto space-y-8">
      <div>
        <router-link :to="`/events/${slug}`" class="inline-flex items-center gap-1 text-xs font-bold text-neutral-muted hover:text-primary transition-colors">
          <ArrowLeft class="w-4 h-4" />
          <span>Back to Event</span>
        </router-link>
        <h1 class="text-2xl sm:text-3xl font-display font-extrabold text-neutral-black mt-2">
          Event Feedback & Survey
        </h1>
        <p class="text-sm text-neutral-muted mt-1">
          Share your experience for <span class="font-bold text-primary">{{ eventName }}</span> to help us improve future MSA community programs.
        </p>
      </div>

      <!-- Thank You Confirmation View -->
      <div v-if="isSubmitted" class="bg-white border border-emerald-200 rounded-3xl p-8 text-center space-y-4 shadow-soft animate-fadeIn">
        <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto">
          <CheckCircle class="w-10 h-10" />
        </div>
        <h2 class="text-xl font-extrabold text-neutral-black">Jazakumullahu Khairan!</h2>
        <p class="text-sm text-neutral-muted max-w-md mx-auto">
          Thank you for taking the time to provide valuable feedback. Your responses help the SFU MSA organize better events for our campus community.
        </p>
        <div class="pt-4">
          <router-link :to="`/events/${slug}`" class="inline-flex items-center justify-center px-6 py-3 rounded-2xl bg-primary text-white font-bold text-sm hover:bg-primary/90 transition-all shadow-soft">
            Return to Event Page
          </router-link>
        </div>
      </div>

      <!-- Feedback Form View -->
      <div v-else class="bg-white border border-neutral-ivory rounded-3xl p-6 sm:p-8 space-y-6 shadow-soft">
        <form @submit.prevent="submitFeedback" class="space-y-6">
          
          <!-- Rating 1: Overall Experience -->
          <div class="space-y-2">
            <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black">
              1. Overall Event Experience *
            </label>
            <div class="flex items-center gap-2">
              <button
                v-for="star in 5"
                :key="star"
                type="button"
                @click="form.overall_rating = star"
                class="p-1 text-amber-400 hover:scale-110 transition-transform cursor-pointer"
              >
                <Star :class="['w-8 h-8', star <= form.overall_rating ? 'fill-amber-400 text-amber-400' : 'text-neutral-200']" />
              </button>
              <span class="ml-2 text-xs font-bold text-neutral-muted">{{ form.overall_rating }} / 5</span>
            </div>
          </div>

          <!-- Rating 2: Program & Content -->
          <div class="space-y-2">
            <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black">
              2. Program & Content Quality *
            </label>
            <div class="flex items-center gap-2">
              <button
                v-for="star in 5"
                :key="star"
                type="button"
                @click="form.program_rating = star"
                class="p-1 text-amber-400 hover:scale-110 transition-transform cursor-pointer"
              >
                <Star :class="['w-7 h-7', star <= form.program_rating ? 'fill-amber-400 text-amber-400' : 'text-neutral-200']" />
              </button>
              <span class="ml-2 text-xs font-bold text-neutral-muted">{{ form.program_rating }} / 5</span>
            </div>
          </div>

          <!-- Rating 3: Organization & Logistics -->
          <div class="space-y-2">
            <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black">
              3. Organization & Operations *
            </label>
            <div class="flex items-center gap-2">
              <button
                v-for="star in 5"
                :key="star"
                type="button"
                @click="form.organization_rating = star"
                class="p-1 text-amber-400 hover:scale-110 transition-transform cursor-pointer"
              >
                <Star :class="['w-7 h-7', star <= form.organization_rating ? 'fill-amber-400 text-amber-400' : 'text-neutral-200']" />
              </button>
              <span class="ml-2 text-xs font-bold text-neutral-muted">{{ form.organization_rating }} / 5</span>
            </div>
          </div>

          <!-- Rating 4: Venue & Environment -->
          <div class="space-y-2">
            <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black">
              4. Venue & Facility Environment *
            </label>
            <div class="flex items-center gap-2">
              <button
                v-for="star in 5"
                :key="star"
                type="button"
                @click="form.venue_rating = star"
                class="p-1 text-amber-400 hover:scale-110 transition-transform cursor-pointer"
              >
                <Star :class="['w-7 h-7', star <= form.venue_rating ? 'fill-amber-400 text-amber-400' : 'text-neutral-200']" />
              </button>
              <span class="ml-2 text-xs font-bold text-neutral-muted">{{ form.venue_rating }} / 5</span>
            </div>
          </div>

          <!-- Written Comments & Suggestions -->
          <div class="space-y-2">
            <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black">
              5. Comments & Suggestions for Improvement
            </label>
            <textarea
              v-model="form.text_feedback"
              rows="4"
              placeholder="What did you enjoy most? What can we do better next time?"
              class="w-full p-4 bg-white border border-neutral-ivory rounded-2xl text-neutral-black text-sm placeholder-neutral-muted focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm"
            ></textarea>
          </div>

          <!-- Email (if non-tokenized guest entry) -->
          <div v-if="!registrationUuid" class="space-y-2">
            <label class="block text-xs font-extrabold uppercase tracking-wider text-neutral-black">
              Your Email Address (Optional for Verification)
            </label>
            <input
              v-model="form.email"
              type="email"
              placeholder="e.g. student@sfu.ca"
              class="w-full px-4 py-3 bg-white border border-neutral-ivory rounded-xl text-neutral-black text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 shadow-sm"
            />
          </div>

          <!-- Anonymous Checkbox -->
          <div class="flex items-center space-x-3 pt-2">
            <input
              v-model="form.is_anonymous"
              type="checkbox"
              id="isAnon"
              class="rounded border-neutral-ivory text-primary focus:ring-primary/20"
            />
            <label for="isAnon" class="text-xs text-neutral-black font-semibold">
              Submit feedback anonymously (hide my name/email from organizers)
            </label>
          </div>

          <div class="pt-4 border-t border-neutral-ivory">
            <button
              type="submit"
              :disabled="isSubmitting"
              class="w-full py-4 rounded-2xl bg-primary hover:bg-primary/90 text-white font-extrabold text-sm uppercase tracking-wider transition-all shadow-soft disabled:opacity-50 flex items-center justify-center space-x-2 cursor-pointer"
            >
              <MessageSquare class="w-4 h-4" />
              <span>{{ isSubmitting ? 'Submitting Feedback...' : 'Submit Feedback' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
