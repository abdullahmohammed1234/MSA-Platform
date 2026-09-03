<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { sponsorshipService } from '@/services/sponsorship.service';
import { Handshake, Send, CheckCircle2, AlertCircle } from 'lucide-vue-next';

const router = useRouter();
const submitting = ref(false);
const submitted = ref(false);
const errorMessage = ref('');

const form = ref({
  organization_name: '',
  contact_name: '',
  contact_email: '',
  contact_phone: '',
  relationship_type: 'sponsor',
  message: '',
});

const handleSubmit = async () => {
  submitting.value = true;
  errorMessage.value = '';

  try {
    const res = await sponsorshipService.submitInquiry(form.value);
    if (res.success) {
      submitted.value = true;
    } else {
      errorMessage.value = res.message || 'Failed to submit inquiry.';
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'An error occurred while submitting your inquiry.';
  } finally {
    submitting.value = false;
  }
};
</script>

<template>
  <div class="min-h-screen bg-neutral-background pt-28 pb-16">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
      
      <div class="bg-white rounded-3xl border border-neutral-ivory p-8 sm:p-12 shadow-premium">
        <div class="text-center max-w-xl mx-auto mb-10">
          <div class="inline-flex items-center gap-2 bg-emerald-50 text-emerald-800 border border-emerald-200 px-4 py-1.5 rounded-full text-xs font-extrabold uppercase tracking-widest mb-4">
            <Handshake class="w-4 h-4 text-emerald-600" />
            <span>Partner Lead Form</span>
          </div>
          <h1 class="text-2xl sm:text-4xl font-display font-extrabold text-neutral-black tracking-tight">
            Sponsorship & Partnership Inquiry
          </h1>
          <p class="mt-2 text-sm text-neutral-black/60">
            Submit your organization details below and our SFU MSA Partnerships Team will get back to you within 2 business days.
          </p>
        </div>

        <div v-if="submitted" class="text-center py-12 space-y-4">
          <CheckCircle2 class="w-16 h-16 text-emerald-600 mx-auto" />
          <h2 class="text-2xl font-bold text-neutral-black">Inquiry Received!</h2>
          <p class="text-sm text-neutral-black/60 max-w-md mx-auto leading-relaxed">
            JazakAllah Khair for reaching out. We have logged your request and assigned a relationship manager to contact you.
          </p>
          <div class="pt-6">
            <button
              @click="router.push('/sponsorship')"
              class="bg-primary text-white px-8 py-3 rounded-full font-bold text-xs uppercase tracking-widest hover:bg-secondary transition-colors cursor-pointer"
            >
              Back to Sponsorships Page
            </button>
          </div>
        </div>

        <form v-else @submit.prevent="handleSubmit" class="space-y-6">
          <div v-if="errorMessage" class="flex items-center gap-2 p-4 bg-red-50 border border-red-200 text-red-700 text-xs font-bold rounded-xl">
            <AlertCircle class="w-4 h-4 shrink-0" />
            <span>{{ errorMessage }}</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
              <label class="block text-xs font-bold text-neutral-black uppercase tracking-wider mb-2">
                Organization / Company Name *
              </label>
              <input
                v-model="form.organization_name"
                type="text"
                required
                placeholder="e.g. Acme Corporation"
                class="w-full px-4 py-3 rounded-xl border border-neutral-ivory text-xs focus:outline-none focus:border-primary"
              />
            </div>

            <div>
              <label class="block text-xs font-bold text-neutral-black uppercase tracking-wider mb-2">
                Contact Full Name *
              </label>
              <input
                v-model="form.contact_name"
                type="text"
                required
                placeholder="e.g. Tariq Mansour"
                class="w-full px-4 py-3 rounded-xl border border-neutral-ivory text-xs focus:outline-none focus:border-primary"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
              <label class="block text-xs font-bold text-neutral-black uppercase tracking-wider mb-2">
                Work Email Address *
              </label>
              <input
                v-model="form.contact_email"
                type="email"
                required
                placeholder="tariq@acme.com"
                class="w-full px-4 py-3 rounded-xl border border-neutral-ivory text-xs focus:outline-none focus:border-primary"
              />
            </div>

            <div>
              <label class="block text-xs font-bold text-neutral-black uppercase tracking-wider mb-2">
                Phone Number
              </label>
              <input
                v-model="form.contact_phone"
                type="tel"
                placeholder="(604) 555-0199"
                class="w-full px-4 py-3 rounded-xl border border-neutral-ivory text-xs focus:outline-none focus:border-primary"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-neutral-black uppercase tracking-wider mb-2">
              Partnership Type
            </label>
            <select
              v-model="form.relationship_type"
              class="w-full px-4 py-3 rounded-xl border border-neutral-ivory text-xs focus:outline-none focus:border-primary"
            >
              <option value="sponsor">Financial Corporate Sponsor</option>
              <option value="in_kind_partner">In-Kind Contribution Partner (Food/Services/Venue)</option>
              <option value="community_partner">Community / Non-Profit Partner</option>
              <option value="media_partner">Media / Promotional Partner</option>
              <option value="vendor_partner">Event Vendor Partner</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-bold text-neutral-black uppercase tracking-wider mb-2">
              Message / Partnership Goals *
            </label>
            <textarea
              v-model="form.message"
              rows="4"
              required
              placeholder="Tell us about your organization and what kind of partnership or event sponsorship you are interested in..."
              class="w-full px-4 py-3 rounded-xl border border-neutral-ivory text-xs focus:outline-none focus:border-primary"
            ></textarea>
          </div>

          <button
            type="submit"
            :disabled="submitting"
            class="w-full bg-primary text-white py-4 rounded-2xl font-extrabold text-xs uppercase tracking-widest hover:bg-secondary transition-all shadow-md cursor-pointer flex items-center justify-center gap-2"
          >
            <Send class="w-4 h-4" />
            <span>{{ submitting ? 'Submitting...' : 'Submit Partnership Inquiry' }}</span>
          </button>
        </form>
      </div>

    </div>
  </div>
</template>
