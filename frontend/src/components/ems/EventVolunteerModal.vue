<script setup lang="ts">
import { reactive, ref } from 'vue';
import { HeartHandshake, X, CheckCircle2, Loader2 } from 'lucide-vue-next';
import client from '@/services/api';

const props = defineProps<{
  show: boolean;
  eventSlug: string;
  eventName: string;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'submitted'): void;
}>();

const submitting = ref(false);
const submitted = ref(false);
const errorMessage = ref('');

const form = reactive({
  name: '',
  email: '',
  phone: '',
  interests: [] as string[],
  availability: '',
  experience: '',
  notes: '',
});

const interestOptions = [
  'Logistics & Setup',
  'Registration & Check-in',
  'Media & Photography',
  'Hospitality & Food',
  'Technical & AV Support',
  'General Assistance',
];

const toggleInterest = (option: string) => {
  const index = form.interests.indexOf(option);
  if (index > -1) {
    form.interests.splice(index, 1);
  } else {
    form.interests.push(option);
  }
};

const handleSubmit = async () => {
  errorMessage.value = '';
  if (!form.name || !form.email) {
    errorMessage.value = 'Please provide your name and email address.';
    return;
  }

  submitting.value = true;
  try {
    const res = await client.post(`/ems/public/events/${props.eventSlug}/volunteers`, {
      name: form.name,
      email: form.email,
      phone: form.phone || null,
      interests: form.interests,
      availability: form.availability || null,
      experience: form.experience || null,
      notes: form.notes || null,
    });

    if (res.data?.success) {
      submitted.value = true;
      emit('submitted');
    } else {
      errorMessage.value = res.data?.message || 'Failed to submit application.';
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || err.message || 'An error occurred during submission.';
  } finally {
    submitting.value = false;
  }
};

const resetAndClose = () => {
  submitted.value = false;
  errorMessage.value = '';
  form.name = '';
  form.email = '';
  form.phone = '';
  form.interests = [];
  form.availability = '';
  form.experience = '';
  form.notes = '';
  emit('close');
};
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/75 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-emerald-500/30 rounded-2xl max-w-lg w-full p-6 text-white shadow-2xl relative overflow-hidden">
      <!-- Top Decorative Accent -->
      <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-emerald-500 via-teal-400 to-emerald-600"></div>

      <!-- Close Button -->
      <button 
        @click="resetAndClose"
        class="absolute top-4 right-4 text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition-colors"
      >
        <X class="w-5 h-5" />
      </button>

      <!-- Submitted Confirmation State -->
      <div v-if="submitted" class="py-8 text-center space-y-4">
        <div class="w-16 h-16 bg-emerald-500/20 text-emerald-400 rounded-full flex items-center justify-center mx-auto">
          <CheckCircle2 class="w-10 h-10" />
        </div>
        <h3 class="text-2xl font-bold text-white">Application Received!</h3>
        <p class="text-slate-300 text-sm max-w-md mx-auto">
          Thank you for offering to volunteer for <span class="font-semibold text-emerald-400">{{ eventName }}</span>.
          Our team will review your application and reach out shortly.
        </p>
        <button
          @click="resetAndClose"
          class="mt-4 px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-xl transition-all shadow-lg shadow-emerald-900/40"
        >
          Close
        </button>
      </div>

      <!-- Form State -->
      <div v-else>
        <div class="flex items-center gap-3 mb-4">
          <div class="p-2.5 bg-emerald-500/10 text-emerald-400 rounded-xl border border-emerald-500/20">
            <HeartHandshake class="w-6 h-6" />
          </div>
          <div>
            <h2 class="text-xl font-bold text-white">Volunteer Sign-up</h2>
            <p class="text-xs text-slate-400">{{ eventName }}</p>
          </div>
        </div>

        <div v-if="errorMessage" class="mb-4 p-3 bg-rose-500/10 border border-rose-500/30 rounded-xl text-rose-400 text-sm">
          {{ errorMessage }}
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-4 max-h-[70vh] overflow-y-auto pr-1">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Full Name *</label>
            <input 
              v-model="form.name"
              type="text" 
              required 
              placeholder="e.g. Fatima Ali"
              class="w-full bg-slate-800/80 border border-slate-700 focus:border-emerald-500 rounded-xl px-3.5 py-2 text-sm text-white focus:outline-none focus:ring-1 focus:ring-emerald-500"
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Email Address *</label>
              <input 
                v-model="form.email"
                type="email" 
                required 
                placeholder="you@example.com"
                class="w-full bg-slate-800/80 border border-slate-700 focus:border-emerald-500 rounded-xl px-3.5 py-2 text-sm text-white focus:outline-none focus:ring-1 focus:ring-emerald-500"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Phone Number</label>
              <input 
                v-model="form.phone"
                type="tel" 
                placeholder="778-123-4567"
                class="w-full bg-slate-800/80 border border-slate-700 focus:border-emerald-500 rounded-xl px-3.5 py-2 text-sm text-white focus:outline-none focus:ring-1 focus:ring-emerald-500"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Areas of Interest</label>
            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                v-for="opt in interestOptions"
                :key="opt"
                @click="toggleInterest(opt)"
                :class="[
                  'px-3 py-1.5 rounded-lg text-xs font-medium transition-all border',
                  form.interests.includes(opt)
                    ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/50 shadow-sm'
                    : 'bg-slate-800/50 text-slate-400 border-slate-700 hover:border-slate-600 hover:text-slate-300'
                ]"
              >
                {{ opt }}
              </button>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Availability</label>
            <input 
              v-model="form.availability"
              type="text" 
              placeholder="e.g. Morning shift, Full day, Setup only"
              class="w-full bg-slate-800/80 border border-slate-700 focus:border-emerald-500 rounded-xl px-3.5 py-2 text-sm text-white focus:outline-none focus:ring-1 focus:ring-emerald-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Relevant Experience</label>
            <textarea 
              v-model="form.experience"
              rows="2" 
              placeholder="Briefly describe any past event or volunteer experience..."
              class="w-full bg-slate-800/80 border border-slate-700 focus:border-emerald-500 rounded-xl px-3.5 py-2 text-sm text-white focus:outline-none focus:ring-1 focus:ring-emerald-500"
            ></textarea>
          </div>

          <div class="pt-2 flex justify-end gap-3">
            <button
              type="button"
              @click="resetAndClose"
              class="px-4 py-2 text-slate-400 hover:text-white text-sm font-medium transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="submitting"
              class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-xl text-sm transition-all shadow-lg shadow-emerald-900/30 flex items-center gap-2 disabled:opacity-50"
            >
              <Loader2 v-if="submitting" class="w-4 h-4 animate-spin" />
              <span>Submit Application</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
