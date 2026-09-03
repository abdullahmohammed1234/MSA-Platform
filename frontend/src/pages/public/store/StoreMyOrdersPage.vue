<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { ShoppingBag, Calendar, RefreshCw, AlertCircle } from 'lucide-vue-next';
import storeService from '@/services/storeService';
import type { StoreOrder } from '@/types/store';

const orders = ref<StoreOrder[]>([]);
const isLoading = ref(true);
const errorMessage = ref('');

const fetchOrders = async () => {
  isLoading.value = true;
  errorMessage.value = '';

  try {
    const res = await storeService.getMyOrders();
    orders.value = res.data;
  } catch (err: any) {
    errorMessage.value = err.message || 'Failed to load order history.';
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  void fetchOrders();
});

const formatDate = (dateStr?: string | null) => {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};
</script>

<template>
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">
    <div class="border-b border-neutral-gray/10 pb-6">
      <h1 class="text-3xl font-black text-neutral-black">My Store Orders</h1>
      <p class="text-xs text-neutral-muted">Track your past and current MSA merchandise purchases.</p>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="p-16 text-center text-neutral-muted space-y-3">
      <RefreshCw class="animate-spin mx-auto text-primary" :size="28" />
      <p class="text-sm font-medium">Loading order history...</p>
    </div>

    <!-- Error -->
    <div v-else-if="errorMessage" class="p-6 bg-red-50 border border-red-500/20 text-red-600 rounded-3xl flex items-center justify-between">
      <div class="flex items-center gap-3">
        <AlertCircle :size="20" />
        <p class="text-sm font-medium">{{ errorMessage }}</p>
      </div>
      <button @click="fetchOrders" class="text-xs font-bold underline cursor-pointer">Retry</button>
    </div>

    <!-- Empty -->
    <div v-else-if="orders.length === 0" class="text-center py-16 space-y-4 bg-white rounded-3xl border border-neutral-gray/20 p-8 shadow-soft">
      <div class="w-16 h-16 bg-primary/10 rounded-3xl flex items-center justify-center mx-auto text-primary">
        <ShoppingBag :size="32" />
      </div>
      <h3 class="text-lg font-bold text-neutral-black">No store orders found</h3>
      <p class="text-sm text-neutral-muted">You haven't placed any merchandise orders yet.</p>
      <router-link to="/store" class="inline-flex items-center gap-2 px-6 py-3 bg-primary text-white rounded-2xl font-bold text-xs uppercase tracking-wider hover:bg-secondary transition-all">
        Browse Store
      </router-link>
    </div>

    <!-- Orders List -->
    <div v-else class="space-y-6">
      <div
        v-for="order in orders"
        :key="order.uuid"
        class="bg-white rounded-3xl border border-neutral-gray/20 p-6 shadow-soft space-y-4"
      >
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-neutral-gray/10 pb-4">
          <div>
            <span class="text-xs font-bold font-mono text-primary">{{ order.order_number }}</span>
            <p class="text-xs text-neutral-muted flex items-center gap-1.5 mt-0.5">
              <Calendar :size="14" /> Placed on {{ formatDate(order.created_at) }}
            </p>
          </div>

          <div class="flex items-center gap-2">
            <span class="px-3 py-1 bg-neutral-background border border-neutral-gray/20 rounded-full text-xs font-bold capitalize">
              Payment: {{ order.payment_status_label || order.payment_status }}
            </span>
            <span class="px-3 py-1 bg-primary/10 text-primary border border-primary/20 rounded-full text-xs font-bold capitalize">
              Fulfillment: {{ order.fulfillment_status_label || order.fulfillment_status }}
            </span>
          </div>
        </div>

        <!-- Items -->
        <div class="space-y-2 text-xs">
          <div v-for="item in order.items" :key="item.uuid" class="flex items-center justify-between py-1">
            <span class="font-medium text-neutral-black">
              {{ item.product_name }} {{ item.variant_name ? `(${item.variant_name})` : '' }} &times; {{ item.quantity }}
            </span>
            <span class="font-bold text-neutral-black">{{ item.formatted_line_total }}</span>
          </div>
        </div>

        <div class="pt-4 border-t border-neutral-gray/10 flex items-center justify-between text-sm">
          <span class="font-bold text-neutral-muted">Total</span>
          <span class="font-black text-lg text-primary">{{ order.formatted_total }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
