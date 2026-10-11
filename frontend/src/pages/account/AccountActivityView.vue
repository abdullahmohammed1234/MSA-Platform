<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { accountService, type AccountSummaryResponse } from '@/services/account/accountService';
import { 
  Calendar, 
  ShoppingBag, 
  Ticket, 
  ExternalLink, 
  Clock, 
  MapPin 
} from 'lucide-vue-next';

const summary = ref<AccountSummaryResponse | null>(null);
const isLoading = ref<boolean>(true);
const errorMessage = ref<string | null>(null);

const loadData = async () => {
  isLoading.value = true;
  errorMessage.value = null;
  try {
    summary.value = await accountService.getSummary();
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to load activity.';
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  loadData();
});
</script>

<template>
  <div>
    <!-- Loading State -->
    <div v-if="isLoading" class="py-16 flex flex-col items-center justify-center space-y-4">
      <div class="h-10 w-10 border-4 border-primary/20 border-t-primary rounded-full animate-spin" />
      <p class="text-xs font-bold text-neutral-black/60 uppercase tracking-wider">Loading activity history...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="errorMessage" class="bg-red-50 border border-red-200 rounded-2xl p-6 text-center text-red-700">
      <p class="font-bold text-sm">{{ errorMessage }}</p>
      <button @click="loadData" class="mt-3 px-4 py-2 bg-primary text-white rounded-xl text-xs font-bold hover:bg-secondary transition-all">
        Retry
      </button>
    </div>

    <div v-else-if="summary" class="space-y-8">
      <!-- Section Header -->
      <div class="bg-white p-6 rounded-3xl border border-neutral-ivory shadow-soft">
        <h2 class="text-lg font-display font-black text-primary">Activity & History</h2>
        <p class="text-xs text-neutral-black/60 font-medium mt-1">
          Summaries of your user-linked event tickets, store orders, and participation.
        </p>
      </div>

      <!-- 1. Event Registrations & Tickets -->
      <div class="bg-white p-6 rounded-3xl border border-neutral-ivory shadow-soft">
        <div class="flex items-center justify-between border-b border-neutral-ivory pb-4 mb-6">
          <div class="flex items-center gap-2">
            <Calendar class="h-5 w-5 text-primary" />
            <h3 class="text-sm font-extrabold uppercase tracking-wider text-primary">Event Tickets & Registrations</h3>
          </div>
          <router-link to="/my-tickets" class="text-xs font-bold text-primary hover:underline">
            View All Tickets &rarr;
          </router-link>
        </div>

        <div v-if="summary.activity.events.length > 0" class="space-y-4">
          <div 
            v-for="eventReg in summary.activity.events" 
            :key="eventReg.id"
            class="p-4 rounded-2xl border border-neutral-ivory bg-neutral-background/40 hover:bg-white hover:border-primary/30 transition-all flex flex-col md:flex-row md:items-center justify-between gap-4"
          >
            <div>
              <div class="flex items-center gap-2 flex-wrap mb-1">
                <h4 class="font-black text-sm text-neutral-black">{{ eventReg.event?.title || 'MSA Event' }}</h4>
                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-primary/10 text-primary border border-primary/20">
                  {{ eventReg.status }}
                </span>
                <span v-if="eventReg.event?.category" class="px-2 py-0.5 rounded text-[10px] font-bold bg-neutral-background text-neutral-black/70 border border-neutral-ivory">
                  {{ eventReg.event.category }}
                </span>
              </div>

              <div class="flex items-center gap-4 text-xs text-neutral-black/60 font-medium flex-wrap mt-2">
                <span v-if="eventReg.event?.start_at" class="flex items-center gap-1">
                  <Clock class="h-3.5 w-3.5 text-primary" />
                  {{ new Date(eventReg.event.start_at).toLocaleDateString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) }}
                </span>

                <span v-if="eventReg.event?.location" class="flex items-center gap-1">
                  <MapPin class="h-3.5 w-3.5 text-primary" />
                  {{ eventReg.event.location }}
                </span>

                <span class="font-mono text-[11px] text-neutral-black/50">Ref: {{ eventReg.reference }}</span>
              </div>
            </div>

            <div class="flex items-center gap-3 shrink-0">
              <router-link 
                to="/my-tickets" 
                class="px-4 py-2 bg-primary text-white rounded-xl text-xs font-bold hover:bg-secondary transition-all inline-flex items-center gap-1.5"
              >
                <Ticket class="h-3.5 w-3.5" />
                View Ticket
              </router-link>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-8 text-neutral-black/50">
          <Ticket class="h-8 w-8 mx-auto text-primary/30 mb-2" />
          <p class="text-xs font-bold">No active event tickets found linked to your account.</p>
          <router-link to="/events" class="mt-3 inline-block px-4 py-2 bg-primary text-white rounded-xl text-xs font-bold hover:bg-secondary transition-all">
            Browse Upcoming Events
          </router-link>
        </div>
      </div>

      <!-- 2. Store Orders -->
      <div class="bg-white p-6 rounded-3xl border border-neutral-ivory shadow-soft">
        <div class="flex items-center justify-between border-b border-neutral-ivory pb-4 mb-6">
          <div class="flex items-center gap-2">
            <ShoppingBag class="h-5 w-5 text-primary" />
            <h3 class="text-sm font-extrabold uppercase tracking-wider text-primary">Store Purchases & Orders</h3>
          </div>
          <router-link to="/store" class="text-xs font-bold text-primary hover:underline">
            MSA Store &rarr;
          </router-link>
        </div>

        <div v-if="summary.activity.orders.length > 0" class="space-y-4">
          <div 
            v-for="order in summary.activity.orders" 
            :key="order.id"
            class="p-4 rounded-2xl border border-neutral-ivory bg-neutral-background/40 hover:bg-white hover:border-primary/30 transition-all flex flex-col md:flex-row md:items-center justify-between gap-4"
          >
            <div>
              <div class="flex items-center gap-2 flex-wrap mb-1">
                <h4 class="font-black text-sm text-neutral-black">Order #{{ order.order_number }}</h4>
                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-primary/10 text-primary border border-primary/20">
                  {{ order.payment_status }}
                </span>
                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-neutral-background text-neutral-700 border border-neutral-ivory">
                  Fulfillment: {{ order.fulfillment_status }}
                </span>
              </div>

              <div class="flex items-center gap-4 text-xs text-neutral-black/60 font-medium flex-wrap mt-2">
                <span>Total: <strong class="text-primary font-black">${{ Number(order.total_amount).toFixed(2) }} {{ order.currency }}</strong></span>
                <span>Items: {{ order.items_count }}</span>
                <span v-if="order.created_at">Date: {{ new Date(order.created_at).toLocaleDateString() }}</span>
              </div>
            </div>

            <div class="flex items-center gap-3 shrink-0">
              <router-link 
                to="/store" 
                class="px-4 py-2 bg-neutral-background hover:bg-white text-neutral-black rounded-xl text-xs font-bold border border-neutral-ivory transition-all inline-flex items-center gap-1.5"
              >
                Store Details
                <ExternalLink class="h-3.5 w-3.5" />
              </router-link>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-8 text-neutral-black/50">
          <ShoppingBag class="h-8 w-8 mx-auto text-primary/30 mb-2" />
          <p class="text-xs font-bold">No store orders found linked to your account.</p>
          <router-link to="/store" class="mt-3 inline-block px-4 py-2 bg-primary text-white rounded-xl text-xs font-bold hover:bg-secondary transition-all">
            Visit MSA Store
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>
