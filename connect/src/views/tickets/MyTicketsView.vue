<template>
  <div class="space-y-6">
    <!-- Page Hero -->
    <PageHero
      label="Member Wallet"
      title="My Passes Wallet"
      description="Access your digital entry passes, check-in QR codes, and confirmed event reservations. Present directly from your phone upon venue arrival."
    >
      <template #badge>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-rose-50 border border-rose-200 text-[#640c0e] text-[10.5px] font-bold">
          <Ticket class="w-3.5 h-3.5 text-[#b02e32]" />
          <span>{{ activePassesCount }} Active {{ activePassesCount === 1 ? 'Pass' : 'Passes' }}</span>
        </span>
      </template>

      <template #actions>
        <!-- Active Only Toggle -->
        <button
          type="button"
          class="px-3 py-1.5 rounded-xl border text-xs font-semibold transition-colors flex items-center gap-1.5 cursor-pointer shadow-2xs"
          :class="activeOnly ? 'bg-[#640c0e] text-white border-[#640c0e]' : 'bg-white border-[#ebe8de] text-neutral-700 hover:text-[#640c0e] hover:bg-[#fffbf4]'"
          @click="toggleActiveFilter"
        >
          <Ticket class="w-3.5 h-3.5" />
          <span>{{ activeOnly ? 'Active Only' : 'All Passes' }}</span>
        </button>

        <!-- Refresh Button -->
        <Button
          variant="outline"
          size="sm"
          :is-loading="ticketsStore.isLoading"
          @click="refreshTickets"
          title="Refresh tickets"
          class="bg-white hover:bg-[#fffbf4] border-[#ebe8de] text-xs font-semibold shadow-2xs"
        >
          <template #left-icon>
            <RefreshCw class="w-3.5 h-3.5 text-[#b02e32]" :class="{ 'animate-spin': ticketsStore.isLoading }" />
          </template>
          <span>Refresh</span>
        </Button>
      </template>
    </PageHero>

    <!-- Route-Based Tabs -->
    <RouteTabs :tabs="navTabs" aria-label="Events and Passes Navigation" />

    <!-- Error State -->
    <ErrorState
      v-if="ticketsStore.error"
      title="Failed to Retrieve Tickets"
      :message="ticketsStore.error"
      @retry="refreshTickets"
    />

    <!-- Loading State -->
    <div
      v-else-if="ticketsStore.isLoading"
      class="py-24 flex flex-col items-center justify-center space-y-3"
    >
      <Spinner size="lg" />
      <p class="text-xs text-neutral-500 font-medium">Retrieving your event passes...</p>
    </div>

    <!-- Empty State -->
    <EmptyState
      v-else-if="ticketsStore.registrations.length === 0"
      title="No Passes Found"
      :description="activeOnly ? 'You have no active event reservations.' : 'You have not registered for any events yet.'"
    >
      <template #icon>
        <Ticket class="w-8 h-8 text-[#640c0e]" />
      </template>
      <template #actions>
        <router-link to="/app/events">
          <Button variant="primary" size="sm">
            <span>Discover Upcoming Events</span>
            <template #right-icon>
              <ArrowRight class="w-3.5 h-3.5" />
            </template>
          </Button>
        </router-link>
      </template>
    </EmptyState>

    <!-- Tickets List -->
    <div v-else class="space-y-5">
      <TicketCard
        v-for="reg in ticketsStore.registrations"
        :key="reg.uuid"
        :registration="reg"
        @cancel="handleOpenCancelModal"
      />
    </div>

    <!-- Cancel Ticket Confirmation Modal -->
    <CancelTicketModal
      v-model="isCancelModalOpen"
      :registration="selectedRegToCancel"
      @cancelled="handleCancelled"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useTicketsStore } from '@/stores/tickets';
import type { Registration } from '@/types/ems';
import PageHero from '@/components/ui/PageHero.vue';
import RouteTabs, { type RouteTabItem } from '@/components/ui/RouteTabs.vue';
import TicketCard from '@/components/events/TicketCard.vue';
import CancelTicketModal from '@/components/events/CancelTicketModal.vue';
import Button from '@/components/ui/Button.vue';
import Spinner from '@/components/ui/Spinner.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import { Ticket, Calendar, ArrowRight, RefreshCw } from 'lucide-vue-next';

const ticketsStore = useTicketsStore();
const activeOnly = ref<boolean>(false);

const activePassesCount = computed(() => {
  return ticketsStore.registrations.filter((r) => r.is_active).length;
});

const navTabs = computed<RouteTabItem[]>(() => [
  {
    to: '/app/events',
    label: 'Browse Events',
    icon: Calendar,
  },
  {
    to: '/app/tickets',
    label: 'My Passes Wallet',
    icon: Ticket,
  },
]);

const isCancelModalOpen = ref<boolean>(false);
const selectedRegToCancel = ref<Registration | null>(null);

function toggleActiveFilter() {
  activeOnly.value = !activeOnly.value;
  ticketsStore.fetchMyTickets(activeOnly.value);
}

function refreshTickets() {
  ticketsStore.fetchMyTickets(activeOnly.value);
}

function handleOpenCancelModal(reg: Registration) {
  selectedRegToCancel.value = reg;
  isCancelModalOpen.value = true;
}

function handleCancelled() {
  ticketsStore.fetchMyTickets(activeOnly.value);
}

onMounted(() => {
  ticketsStore.fetchMyTickets(activeOnly.value);
});
</script>
