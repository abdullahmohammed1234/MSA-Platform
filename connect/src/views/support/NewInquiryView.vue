<template>
  <div class="space-y-6">
    <!-- Page Hero -->
    <PageHero
      label="Member Support"
      title="Submit New Inquiry"
      description="Submit a confidential request for academic adjustments, religious accommodations, or student life support."
    >
      <template #actions>
        <router-link to="/app/support">
          <Button variant="outline" size="sm" class="bg-white hover:bg-[#fffbf4] border-[#ebe8de] text-xs font-semibold shadow-2xs">
            <template #left-icon>
              <ArrowLeft class="w-3.5 h-3.5 text-[#b02e32]" />
            </template>
            <span>Back to Inquiries</span>
          </Button>
        </router-link>
      </template>
    </PageHero>

    <!-- Route-Based Tabs -->
    <RouteTabs :tabs="navTabs" aria-label="Communications and Support Navigation" />

    <div class="max-w-3xl mx-auto space-y-6">

    <!-- Privacy & Reassurance Banner -->
    <div class="p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-[#fffbf4] via-white to-white border border-[#ffdc83] shadow-2xs flex items-start gap-3.5">
      <div class="w-9 h-9 rounded-xl bg-[#ffdc83]/40 text-[#640c0e] flex items-center justify-center shrink-0 mt-0.5">
        <ShieldCheck class="w-5 h-5 text-[#640c0e]" />
      </div>
      <div class="space-y-0.5 text-xs leading-relaxed">
        <h2 class="font-bold text-[#640c0e]">Strict Confidentiality & Privacy</h2>
        <p class="text-neutral-600">
          Your inquiry is strictly confidential. It will only be viewed by assigned SFU MSA executive coordinators to assist your request.
        </p>
      </div>
    </div>

    <!-- Inquiry Submission Form -->
    <form @submit.prevent="handleSubmit" class="p-6 sm:p-8 rounded-2xl bg-white border border-[#ebe8de] shadow-2xs space-y-6">
      <!-- 1. Category Selection -->
      <div class="space-y-2.5">
        <label class="block text-xs font-bold uppercase tracking-wider text-neutral-700">
          Select Inquiry Topic <span class="text-red-600">*</span>
        </label>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <button
            v-for="cat in availableCategories"
            :key="cat.value"
            type="button"
            @click="form.category = cat.value"
            class="p-3.5 rounded-xl border text-left transition-all cursor-pointer flex flex-col justify-between space-y-1.5"
            :class="form.category === cat.value ? 'border-[#640c0e] bg-rose-50/40 ring-2 ring-[#640c0e]' : 'border-[#ebe8de] hover:border-[#640c0e]/30 bg-[#fffbf4]/30'"
          >
            <div class="flex items-center justify-between gap-2">
              <span class="text-xs font-bold" :class="form.category === cat.value ? 'text-[#640c0e]' : 'text-neutral-900'">
                {{ cat.label }}
              </span>
              <CheckCircle2
                v-if="form.category === cat.value"
                class="w-4 h-4 text-[#640c0e] shrink-0"
              />
            </div>
            <p class="text-[11px] text-neutral-600 leading-relaxed">
              {{ cat.description }}
            </p>
          </button>
        </div>
        <p v-if="errors.category" class="text-xs text-red-600 font-medium">
          {{ errors.category }}
        </p>
      </div>

      <!-- 2. Subject -->
      <div class="space-y-1.5">
        <label for="subject-input" class="block text-xs font-bold uppercase tracking-wider text-neutral-700">
          Subject <span class="text-red-600">*</span>
        </label>
        <input
          id="subject-input"
          v-model="form.subject"
          type="text"
          placeholder="Brief summary of your question or request"
          maxlength="255"
          class="w-full px-3.5 py-2.5 rounded-xl border border-[#ebe8de] bg-[#fffbf4]/30 text-xs sm:text-sm text-neutral-800 placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-[#640c0e]/20 focus:border-[#640c0e]"
          :class="{ 'border-red-500 ring-1 ring-red-500': errors.subject }"
        />
        <div class="flex items-center justify-between text-[11px] text-neutral-400">
          <span v-if="errors.subject" class="text-red-600 font-medium">{{ errors.subject }}</span>
          <span v-else>Be specific to help us direct your inquiry to the right coordinator.</span>
          <span>{{ form.subject.length }}/255</span>
        </div>
      </div>

      <!-- 3. Message Body -->
      <div class="space-y-1.5">
        <label for="message-input" class="block text-xs font-bold uppercase tracking-wider text-neutral-700">
          Detailed Message <span class="text-red-600">*</span>
        </label>
        <textarea
          id="message-input"
          v-model="form.message"
          rows="6"
          placeholder="Describe your question or situation in as much detail as you'd like..."
          maxlength="10000"
          class="w-full px-3.5 py-2.5 rounded-xl border border-[#ebe8de] bg-[#fffbf4]/30 text-xs sm:text-sm text-neutral-800 placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-[#640c0e]/20 focus:border-[#640c0e] leading-relaxed resize-y"
          :class="{ 'border-red-500 ring-1 ring-red-500': errors.message }"
        ></textarea>
        <div class="flex items-center justify-between text-[11px] text-neutral-400">
          <span v-if="errors.message" class="text-red-600 font-medium">{{ errors.message }}</span>
          <span v-else>Minimum 10 characters</span>
          <span>{{ form.message.length }}/10,000</span>
        </div>
      </div>

      <!-- Server Error Banner -->
      <div v-if="serverError" class="p-3 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700 flex items-center gap-2">
        <AlertCircle class="w-4 h-4 shrink-0 text-red-600" />
        <span>{{ serverError }}</span>
      </div>

      <!-- Form Actions -->
      <div class="flex items-center justify-end gap-3 pt-4 border-t border-[#ebe8de]/60">
        <router-link to="/app/support">
          <Button variant="outline" size="md" type="button">
            Cancel
          </Button>
        </router-link>

        <Button
          variant="primary"
          size="md"
          type="submit"
          :is-loading="supportStore.isSending"
          :disabled="supportStore.isSending"
        >
          <template #left-icon>
            <Send class="w-4 h-4" />
          </template>
          <span>Send Inquiry</span>
        </Button>
      </div>
    </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useSupportStore } from '@/stores/support';
import { useAuthStore } from '@/stores/auth';
import { useMessagingStore } from '@/stores/messaging';
import PageHero from '@/components/ui/PageHero.vue';
import RouteTabs, { type RouteTabItem } from '@/components/ui/RouteTabs.vue';
import Button from '@/components/ui/Button.vue';
import {
  ArrowLeft,
  ShieldCheck,
  CheckCircle2,
  AlertCircle,
  Send,
  MessageSquareText,
  MessageSquare,
  MessageSquarePlus,
  ShieldAlert,
} from 'lucide-vue-next';

const router = useRouter();
const supportStore = useSupportStore();
const authStore = useAuthStore();
const messagingStore = useMessagingStore();

const canAccessStaffTriage = computed(() => {
  const roles = authStore.roles;
  const permissions = authStore.permissions;
  return (
    roles.includes('super-admin') ||
    roles.includes('admin') ||
    roles.includes('director') ||
    roles.includes('dawah-coordinator') ||
    permissions.includes('manage_support')
  );
});

const navTabs = computed<RouteTabItem[]>(() => {
  const tabs: RouteTabItem[] = [
    {
      to: '/app/support',
      label: 'Support Inquiries',
      icon: MessageSquareText,
      badge: supportStore.memberUnreadCount,
      exact: true,
    },
    {
      to: '/app/messages',
      label: 'Direct Messages',
      icon: MessageSquare,
      badge: messagingStore.unreadCount,
    },
    {
      to: '/app/support/new',
      label: 'New Inquiry',
      icon: MessageSquarePlus,
    },
  ];

  if (canAccessStaffTriage.value) {
    tabs.push({
      to: '/app/support/staff',
      label: 'Staff Triage',
      icon: ShieldAlert,
    });
  }

  return tabs;
});

const categoryDescriptions: Record<string, string> = {
  general: 'General questions about SFU MSA activities, membership, and community',
  new_muslim: 'Welcoming guidance, free learning materials, and peer connection for new Muslims',
  revert_support: 'Welcoming guidance, free learning materials, and peer connection for new Muslims',
  faith_questions: 'Questions regarding campus halaqahs, spiritual guidance, and prayers',
  spiritual: 'Questions regarding campus halaqahs, spiritual guidance, and prayers',
  campus_prayer: 'Prayer room access (Burnaby AQ 3200 & Surrey), Jummah schedules, and wudu facilities',
  academic: 'Jummah / Eid exam conflict letters and academic accommodation guidance',
  event_support: 'Questions regarding upcoming campus gatherings, RSVP, and ticketing',
  events: 'Questions regarding upcoming campus gatherings, RSVP, and ticketing',
  feedback: 'Ideas to improve SFU MSA services and member experience',
  other: 'Any other private inquiry not listed above',
};

const defaultCategories = [
  { value: 'general', label: 'General MSA Support', description: categoryDescriptions.general },
  { value: 'new_muslim', label: 'New Muslim Support', description: categoryDescriptions.new_muslim },
  { value: 'faith_questions', label: 'Faith & Spiritual Guidance', description: categoryDescriptions.faith_questions },
  { value: 'campus_prayer', label: 'Campus & Prayer Resources', description: categoryDescriptions.campus_prayer },
  { value: 'academic', label: 'Academic & Exam Adjustments', description: categoryDescriptions.academic },
  { value: 'event_support', label: 'Event & Registration Support', description: categoryDescriptions.event_support },
  { value: 'feedback', label: 'Feedback & Suggestions', description: categoryDescriptions.feedback },
  { value: 'other', label: 'Other Inquiries', description: categoryDescriptions.other },
];

const availableCategories = computed(() => {
  if (!supportStore.categories || supportStore.categories.length === 0) {
    return defaultCategories;
  }
  return supportStore.categories.map((c: any) => {
    const val = String(c.value || c.key || '').trim();
    const lbl = String(c.label || c.name || val).trim();
    const desc = c.description || categoryDescriptions[val] || `Inquiry regarding ${lbl}`;
    return {
      value: val,
      label: lbl,
      description: desc,
    };
  }).filter((c) => Boolean(c.value));
});

const form = reactive({
  category: 'general',
  subject: '',
  message: '',
});

const errors = reactive({
  category: '',
  subject: '',
  message: '',
});

const serverError = ref<string | null>(null);

function validate(): boolean {
  errors.category = '';
  errors.subject = '';
  errors.message = '';
  serverError.value = null;

  let valid = true;

  if (!form.category) {
    errors.category = 'Please select a topic category.';
    valid = false;
  }

  if (!form.subject.trim()) {
    errors.subject = 'Please enter an inquiry subject.';
    valid = false;
  } else if (form.subject.trim().length < 3) {
    errors.subject = 'Subject must be at least 3 characters.';
    valid = false;
  }

  if (!form.message.trim()) {
    errors.message = 'Please enter your message.';
    valid = false;
  } else if (form.message.trim().length < 10) {
    errors.message = 'Message must be at least 10 characters.';
    valid = false;
  }

  return valid;
}

async function handleSubmit() {
  if (!validate()) return;

  try {
    const created = await supportStore.createInquiry({
      category: form.category,
      subject: form.subject.trim(),
      message: form.message.trim(),
    });

    router.push(`/app/support/${created.reference_code}`);
  } catch (err: any) {
    serverError.value = err.message || 'Unable to submit your inquiry. Please try again.';
  }
}

onMounted(() => {
  supportStore.fetchCategories();
});
</script>
