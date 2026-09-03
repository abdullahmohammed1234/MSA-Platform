<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { donationsService, type DonationItem } from '@/services/donations.service';

const route = useRoute();
const donationUuid = ref<string>((route.query.donation_uuid as string) || '');
const donation = ref<DonationItem | null>(null);
const isLoading = ref(true);

onMounted(async () => {
  if (donationUuid.value) {
    try {
      const response = await donationsService.getDonationStatus(donationUuid.value);
      donation.value = response.donation;
    } catch (e) {
      console.error('Failed to load donation details', e);
    } finally {
      isLoading.value = false;
    }
  } else {
    isLoading.value = false;
  }
});
</script>

<template>
  <div class="min-h-screen bg-neutral-background py-16 px-4 sm:px-6 lg:px-8 font-sans flex items-center justify-center">
    <div class="max-w-md w-full bg-white border border-neutral-ivory rounded-3xl p-8 shadow-soft text-center space-y-6">
      <!-- Success Icon -->
      <div class="h-16 w-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto">
        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
        </svg>
      </div>

      <div class="space-y-2">
        <h1 class="text-2xl font-bold text-neutral-black">Jazakum Allahu Khairan!</h1>
        <p class="text-xs text-neutral-muted leading-relaxed">
          Thank you for supporting the SFU Muslim Students Association. Your donation has been received.
        </p>
      </div>

      <!-- Donation Details Card -->
      <div v-if="donation" class="bg-neutral-background/60 p-4 rounded-2xl border border-neutral-ivory text-left space-y-2.5 text-xs">
        <div class="flex justify-between items-center">
          <span class="text-neutral-muted font-medium">Donation Reference:</span>
          <span class="font-mono font-bold text-neutral-black">{{ donation.donation_number }}</span>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-neutral-muted font-medium">Amount:</span>
          <span class="font-bold text-primary text-sm">{{ donation.formatted_amount }}</span>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-neutral-muted font-medium">Status:</span>
          <span
            :class="[
              'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider',
              donation.status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'
            ]"
          >
            {{ donation.status === 'paid' ? 'Confirmed Paid' : 'Processing Confirmation' }}
          </span>
        </div>
        <div class="flex justify-between items-center" v-if="donation.donor_name">
          <span class="text-neutral-muted font-medium">Donor:</span>
          <span class="font-semibold text-neutral-black">{{ donation.is_anonymous ? 'Anonymous' : donation.donor_name }}</span>
        </div>
      </div>

      <div class="space-y-3 pt-2">
        <router-link
          to="/"
          class="block w-full py-3 bg-primary hover:bg-primary-hover text-white text-xs font-bold rounded-xl transition-all shadow-md"
        >
          Return to Homepage
        </router-link>
        <router-link
          to="/donate"
          class="block w-full py-2.5 text-xs font-semibold text-neutral-muted hover:text-primary transition-colors"
        >
          Make Another Donation
        </router-link>
      </div>
    </div>
  </div>
</template>
