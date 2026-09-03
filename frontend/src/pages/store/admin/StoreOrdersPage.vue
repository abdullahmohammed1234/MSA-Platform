<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { Search, RefreshCw, Eye, X } from 'lucide-vue-next';
import storeService from '@/services/storeService';
import { useToastStore } from '@/components/feedback/toast';
import type { StoreOrder } from '@/types/store';

const toast = useToastStore();

const orders = ref<StoreOrder[]>([]);
const isLoading = ref(true);
const search = ref('');
const paymentStatus = ref('all');
const fulfillmentStatus = ref('all');

const selectedOrder = ref<StoreOrder | null>(null);
const isUpdatingFulfillment = ref(false);
const isRefunding = ref(false);

const fetchOrders = async () => {
  isLoading.value = true;
  try {
    const res = await storeService.getAdminOrders({
      search: search.value,
      payment_status: paymentStatus.value,
      fulfillment_status: fulfillmentStatus.value,
    });
    orders.value = res.data;
  } catch (err: any) {
    toast.error('Failed to load store orders.');
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  void fetchOrders();
});

watch([search, paymentStatus, fulfillmentStatus], () => {
  void fetchOrders();
});

const openDetail = (order: StoreOrder) => {
  selectedOrder.value = order;
};

const handleUpdateFulfillment = async (newStatus: string) => {
  if (!selectedOrder.value) return;

  isUpdatingFulfillment.value = true;

  try {
    const updated = await storeService.updateFulfillmentStatus(selectedOrder.value.uuid, newStatus);
    selectedOrder.value = updated;
    toast.success(`Fulfillment status updated to ${newStatus}.`);
    void fetchOrders();
  } catch (err: any) {
    toast.error(err.message || 'Failed to update fulfillment status.');
  } finally {
    isUpdatingFulfillment.value = false;
  }
};

const handleRefund = async () => {
  if (!selectedOrder.value) return;
  if (!confirm(`Are you sure you want to refund Order #${selectedOrder.value.order_number}?`)) return;

  isRefunding.value = true;

  try {
    const updated = await storeService.refundAdminOrder(selectedOrder.value.uuid, 'Admin initiated refund');
    selectedOrder.value = updated;
    toast.success('Order refunded successfully.');
    void fetchOrders();
  } catch (err: any) {
    toast.error(err.message || 'Failed to refund order.');
  } finally {
    isRefunding.value = false;
  }
};
</script>

<template>
  <div class="space-y-8">
    <div class="border-b border-neutral-gray/10 pb-6">
      <h1 class="text-3xl font-black text-neutral-black">Store Orders</h1>
      <p class="text-xs text-neutral-muted">Review merchandise orders, track Square payment status, and manage campus pickup fulfillment.</p>
    </div>

    <!-- Filters & Search -->
    <div class="flex flex-col md:flex-row gap-4 items-stretch md:items-center justify-between">
      <div class="relative flex-1 max-w-md">
        <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-neutral-muted" :size="16" />
        <input
          type="text"
          v-model="search"
          placeholder="Search order #, customer name, email..."
          class="w-full bg-white border border-neutral-gray/20 rounded-2xl pl-11 pr-4 py-2.5 text-xs text-neutral-black outline-none"
        />
      </div>

      <div class="flex items-center gap-3">
        <select v-model="paymentStatus" class="bg-white border border-neutral-gray/20 rounded-2xl px-4 py-2 text-xs text-neutral-black outline-none cursor-pointer">
          <option value="all">All Payment Statuses</option>
          <option value="paid">Paid</option>
          <option value="pending">Pending</option>
          <option value="refunded">Refunded</option>
        </select>

        <select v-model="fulfillmentStatus" class="bg-white border border-neutral-gray/20 rounded-2xl px-4 py-2 text-xs text-neutral-black outline-none cursor-pointer">
          <option value="all">All Fulfillment Statuses</option>
          <option value="pending">Pending</option>
          <option value="preparing">Preparing</option>
          <option value="ready_for_pickup">Ready for Pickup</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
    </div>

    <!-- Orders Table -->
    <div class="bg-white rounded-3xl border border-neutral-gray/20 shadow-soft overflow-hidden p-6 space-y-4">
      <div v-if="isLoading" class="p-8 text-center text-neutral-muted space-y-2">
        <RefreshCw class="animate-spin mx-auto text-primary" :size="24" />
        <p class="text-xs">Loading orders...</p>
      </div>

      <div v-else-if="orders.length === 0" class="p-8 text-center text-neutral-muted text-sm">
        No merchandise orders matching criteria.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-neutral-background border-b border-neutral-gray/10 text-neutral-muted font-bold uppercase tracking-wider">
              <th class="p-3">Order #</th>
              <th class="p-3">Customer</th>
              <th class="p-3">Total</th>
              <th class="p-3">Payment</th>
              <th class="p-3">Fulfillment</th>
              <th class="p-3 text-right">Details</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-gray/10">
            <tr v-for="order in orders" :key="order.uuid" class="hover:bg-neutral-background/40 transition-colors">
              <td class="p-3 font-mono font-bold text-primary">{{ order.order_number }}</td>
              <td class="p-3">
                <p class="font-semibold text-neutral-black">{{ order.customer_name }}</p>
                <p class="text-[10px] text-neutral-muted font-mono">{{ order.customer_email }}</p>
              </td>
              <td class="p-3 font-bold text-neutral-black">{{ order.formatted_total }}</td>
              <td class="p-3">
                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase border capitalize" :class="order.payment_status === 'paid' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'">
                  {{ order.payment_status_label || order.payment_status }}
                </span>
              </td>
              <td class="p-3">
                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase border capitalize" :class="order.fulfillment_status === 'completed' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-blue-50 text-blue-700 border-blue-200'">
                  {{ order.fulfillment_status_label || order.fulfillment_status }}
                </span>
              </td>
              <td class="p-3 text-right">
                <button @click="openDetail(order)" class="p-1.5 text-neutral-muted hover:text-primary rounded-lg transition-colors cursor-pointer">
                  <Eye :size="18" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Order Detail Modal -->
    <div v-if="selectedOrder" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl max-w-2xl w-full p-6 md:p-8 space-y-6 shadow-xl text-xs max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-neutral-gray/10 pb-4">
          <div>
            <span class="font-mono font-bold text-primary text-sm">Order #{{ selectedOrder.order_number }}</span>
            <p class="text-neutral-muted">Customer & Fulfillment Details</p>
          </div>
          <button @click="selectedOrder = null" class="text-neutral-muted hover:text-neutral-black">
            <X :size="20" />
          </button>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div class="p-4 bg-neutral-background/50 rounded-2xl space-y-1">
            <span class="font-bold text-neutral-muted uppercase">Customer</span>
            <p class="font-bold text-neutral-black">{{ selectedOrder.customer_name }}</p>
            <p class="font-mono text-neutral-muted">{{ selectedOrder.customer_email }}</p>
            <p v-if="selectedOrder.customer_phone" class="font-mono text-neutral-muted">{{ selectedOrder.customer_phone }}</p>
          </div>

          <div class="p-4 bg-neutral-background/50 rounded-2xl space-y-1">
            <span class="font-bold text-neutral-muted uppercase">Payment Status</span>
            <p class="font-bold text-neutral-black capitalize">{{ selectedOrder.payment_status_label || selectedOrder.payment_status }}</p>
            <p v-if="selectedOrder.square_payment_id" class="font-mono text-[10px] text-neutral-muted">Square ID: {{ selectedOrder.square_payment_id }}</p>
          </div>
        </div>

        <!-- Purchased Items -->
        <div class="space-y-2">
          <h4 class="font-bold uppercase tracking-wider text-neutral-muted">Purchased Items</h4>
          <div class="divide-y divide-neutral-gray/10 border border-neutral-gray/20 rounded-2xl overflow-hidden bg-white">
            <div v-for="item in selectedOrder.items" :key="item.uuid" class="p-3 flex items-center justify-between">
              <div>
                <p class="font-bold text-neutral-black">{{ item.product_name }}</p>
                <p v-if="item.variant_name" class="text-neutral-muted">Option: {{ item.variant_name }}</p>
                <p class="text-[10px] text-neutral-muted font-mono">Qty: {{ item.quantity }} &times; {{ item.formatted_unit_price }}</p>
              </div>
              <span class="font-black text-neutral-black">{{ item.formatted_line_total }}</span>
            </div>
          </div>
        </div>

        <!-- Fulfillment Actions -->
        <div class="space-y-3 pt-2 border-t border-neutral-gray/10">
          <h4 class="font-bold uppercase tracking-wider text-neutral-muted">Manage Fulfillment Status</h4>
          <div class="flex flex-wrap gap-2">
            <button
              @click="handleUpdateFulfillment('preparing')"
              :disabled="selectedOrder.fulfillment_status === 'preparing' || isUpdatingFulfillment"
              class="px-3 py-2 bg-blue-50 text-blue-700 rounded-xl font-bold hover:bg-blue-100 disabled:opacity-40 cursor-pointer"
            >
              Mark Preparing
            </button>
            <button
              @click="handleUpdateFulfillment('ready_for_pickup')"
              :disabled="selectedOrder.fulfillment_status === 'ready_for_pickup' || isUpdatingFulfillment"
              class="px-3 py-2 bg-amber-50 text-amber-700 rounded-xl font-bold hover:bg-amber-100 disabled:opacity-40 cursor-pointer"
            >
              Mark Ready for Pickup
            </button>
            <button
              @click="handleUpdateFulfillment('completed')"
              :disabled="selectedOrder.fulfillment_status === 'completed' || isUpdatingFulfillment"
              class="px-3 py-2 bg-emerald-50 text-emerald-700 rounded-xl font-bold hover:bg-emerald-100 disabled:opacity-40 cursor-pointer"
            >
              Mark Completed
            </button>
          </div>
        </div>

        <div v-if="selectedOrder.payment_status === 'paid'" class="pt-4 border-t border-neutral-gray/10">
          <button @click="handleRefund" :disabled="isRefunding" class="px-4 py-2 bg-red-50 text-red-600 border border-red-200 rounded-xl font-bold hover:bg-red-100 cursor-pointer">
            Issue Square Refund
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
